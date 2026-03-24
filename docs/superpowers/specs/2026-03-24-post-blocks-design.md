# Post Blocks — Design Spec
Date: 2026-03-24

## Overview

Add 5 new ACF/Gutenberg blocks to the headless WordPress theme for use on Posts. All blocks follow the existing pattern: `block.json` + `render.php` (+ `fields.php` for ACF blocks), outputting structured `data-props` JSON on a wrapper element for headless front-end consumption.

---

## Block 1: YouTube Video (`acf/youtube-video`)

### Files
- `blocks/youtube-video/block.json`
- `blocks/youtube-video/render.php`
- `blocks/youtube-video/fields.php`

### ACF Field Group
Key: `group_block_youtube_video`

| Name | Key | Type | Notes |
|---|---|---|---|
| `video_url` | `field_youtube_video_url` | url | Required |
| `title` | `field_youtube_title` | text | Optional |
| `caption` | `field_youtube_caption` | textarea | Optional |

### URL Handling
`render.php` validates the `video_url` host is `youtube.com` or `youtu.be` (scheme must be `https`). If validation fails the field is treated as empty and omitted from `data-props`. A regex extracts the `video_id` — supports:
- `https://www.youtube.com/watch?v=VIDEO_ID` (and `?v=VIDEO_ID&si=TRACKING_TOKEN` variants)
- `https://youtu.be/VIDEO_ID` and `https://youtu.be/VIDEO_ID?si=TRACKING_TOKEN`
- `https://www.youtube.com/embed/VIDEO_ID`

### render.php output (data-props)
```json
{
  "video_id": "dQw4w9WgXcQ",
  "video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
  "title": "...",
  "caption": "..."
}
```

---

## Block 2: Facebook Video (`acf/facebook-video`)

### Files
- `blocks/facebook-video/block.json`
- `blocks/facebook-video/render.php`
- `blocks/facebook-video/fields.php`

### ACF Field Group
Key: `group_block_facebook_video`

| Name | Key | Type | Notes |
|---|---|---|---|
| `video_url` | `field_facebook_video_url` | url | Required |
| `title` | `field_facebook_title` | text | Optional |
| `caption` | `field_facebook_caption` | textarea | Optional |

### URL Handling
`render.php` validates the `video_url` host is `facebook.com` or `www.facebook.com` (scheme must be `https`). If validation fails the field is treated as empty and omitted from `data-props`. The front-end uses the Facebook embed SDK with the validated URL.

### render.php output (data-props)
```json
{
  "video_url": "https://www.facebook.com/watch/?v=123456789",
  "title": "...",
  "caption": "..."
}
```

---

## Block 3: SoundCloud (`headless/soundcloud`)

Custom native Gutenberg block (no ACF). Optimised for headless.

### Files
- `blocks/soundcloud/block.json`
- `blocks/soundcloud/render.php`
- `blocks/soundcloud/editor.js` (vanilla JS, no build step)

### Block Registration
**Registered on `init` (not `acf/init`).** The existing `acf/init` auto-registration loop handles only ACF blocks. The SoundCloud block has no ACF dependency and must be registered separately via `register_block_type()` on the WordPress `init` hook. The existing `allowed_block_types_all` filter in `functions.php` reads all `blocks/*/block.json` files dynamically — the block name `headless/soundcloud` will be picked up by this glob and included in the allowed list automatically. No change to the filter is needed.

### block.json — Script Dependencies
`editorScript` declared as `"file:./editor.js"`. The following WordPress script handles must be listed as dependencies so they are enqueued before `editor.js` runs:
- `wp-blocks`
- `wp-element`
- `wp-block-editor`
- `wp-api-fetch`
- `wp-components`

These are declared via a hand-written `editor.asset.php` placed alongside `editor.js`. WordPress's script loader requires this file to return an array with exactly two keys:
```php
return [
    'dependencies' => [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-api-fetch', 'wp-components' ],
    'version'      => '1.0.0',
];
```

### REST Endpoint
`GET /wp-json/headless/v1/soundcloud/tracks`
Permission: `__return_true` (public, read-only).
Registered on `rest_api_init` in `functions.php`.

**Logic — `headless_soundcloud_get_tracks()`:**

1. Check WP transient `headless_soundcloud_tracks`. If set and non-empty, return cached array.
2. **Check negative-cache:** if transient `headless_soundcloud_bootstrap_failed` is set, return `WP_Error('soundcloud_bootstrap_failed', ...)` immediately without fetching anything.
3. On miss: retrieve `get_option('headless_soundcloud_rss_url')`.
4. **Bootstrap** (option is empty): fetch `https://soundcloud.com/radio-velika-kladu-a` via `wp_remote_get( $url, ['timeout' => 10] )`.
   - On `is_wp_error()` or HTTP status ≠ 200: set transient `headless_soundcloud_bootstrap_failed` with TTL 300s to prevent hammering SoundCloud on every editor load. Return `WP_Error('soundcloud_bootstrap_failed', ...)`.
   - On success: parse response body with regex for `<link[^>]+type="application/rss\+xml"[^>]+href="([^"]+)"`. If not found, same failure path as above.
   - Store discovered RSS URL in `update_option('headless_soundcloud_rss_url', $rss_url)`.
5. Fetch RSS URL via `wp_remote_get( $rss_url, ['timeout' => 10] )`.
   - On error or non-200: return `WP_Error('soundcloud_rss_failed', ...)` with HTTP 502. Do NOT cache a failed result.
6. Parse body with `new SimpleXMLElement( $body )`. Register the `itunes` namespace: `$xml->registerXPathNamespace('itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd')`.
7. Map each `<item>` to: `title` (string), `url` (`<link>` text), `duration` (`<itunes:duration>` text), `artwork_url` (XPath `itunes:image/@href` relative to the item node).
   - URL validation: validate each item's `url` matches `https://soundcloud.com/*`. Items failing validation are **silently skipped** (not included in the returned array). This prevents front-end breakage if SoundCloud's RSS includes redirect or mobile URLs.
8. Store result array in transient `headless_soundcloud_tracks` with TTL 3600 (1 hour).
9. Return array `[{title, url, duration, artwork_url}]`.

### Block Attributes (`block.json`)
```json
{
  "track_url":      { "type": "string", "default": "" },
  "track_title":    { "type": "string", "default": "" },
  "track_duration": { "type": "string", "default": "" },
  "track_artwork":  { "type": "string", "default": "" }
}
```
`track_artwork` stores the artwork URL at selection time so `render.php` can include it in `data-props` without a live API call.

### Editor UI (`editor.js`)
- Plain JS using `wp.blocks.registerBlockType` + `wp.element.createElement` (no JSX, no build step).
- Uses `wp.apiFetch` to call `/wp-json/headless/v1/soundcloud/tracks`.
- **States:**
  - `loading` — spinner while fetching tracks.
  - `error` — error message with retry button if fetch fails.
  - `list` — scrollable list of tracks with a text input to filter by title. Each row shows artwork thumbnail (if available), title, and duration.
  - `selected` — shows chosen track (artwork, title, duration) and a "Change track" button to return to `list` state.
- On track click: calls `setAttributes({ track_url, track_title, track_duration, track_artwork })`.
- On initial load, if `track_url` is already set (existing saved block), start in `selected` state.

### render.php output (data-props)
```json
{
  "track_url":      "https://soundcloud.com/radio-velika-kladu-a/some-track",
  "track_title":    "Track Title",
  "track_duration": "1:23:45",
  "track_artwork":  "https://i1.sndcdn.com/artworks-..."
}
```
`data-block="soundcloud"` (slug only, matching existing convention).

---

## Block 4: Image Gallery (`acf/image-gallery`)

### Files
- `blocks/image-gallery/block.json`
- `blocks/image-gallery/render.php`
- `blocks/image-gallery/fields.php`

### ACF Field Group
Key: `group_block_image_gallery`

| Name | Key | Type | Notes |
|---|---|---|---|
| `title` | `field_gallery_title` | text | Optional heading |
| `images` | `field_gallery_images` | gallery | Return format: `id` (array of attachment IDs) |
| `columns` | `field_gallery_columns` | select | Options: 2, 3, 4 — default 3 |

### render.php — Image Resolution
For each attachment ID, resolve using `wp_get_attachment_image_src( $id, 'headless-large' )` → returns `[ url, width, height, is_intermediate ]`. Width and height are the generated file's actual dimensions (not the original upload). Alt text retrieved via `get_post_meta( $id, '_wp_attachment_image_alt', true )` — consistent with the rest of this codebase.

### render.php output (data-props)
```json
{
  "title": "...",
  "columns": 3,
  "images": [
    { "id": 42, "url": "...", "width": 1200, "height": 900, "alt": "..." }
  ]
}
```

---

## Block 5: PDF Documents (`acf/pdf-documents`)

### Files
- `blocks/pdf-documents/block.json`
- `blocks/pdf-documents/render.php`
- `blocks/pdf-documents/fields.php`

### ACF Field Group
Key: `group_block_pdf_documents`

| Name | Key | Type | Notes |
|---|---|---|---|
| `title` | `field_pdf_title` | text | Optional heading |
| `documents` | `field_pdf_documents` | repeater | min: 1 |
| ↳ `doc_title` | `field_pdf_doc_title` | text | Required |
| ↳ `file` | `field_pdf_doc_file` | file | Mime types: `application/pdf`; return format: `array` |

### render.php — File Size
The ACF `file` field's `return_format: array` provides `$file['filesize']` as a **human-readable formatted string** (e.g. `"200 kB"`). Use this directly — the front-end displays it as-is. Raw byte count is not required.

### render.php output (data-props)
```json
{
  "title": "...",
  "documents": [
    {
      "title": "Annual Report 2025",
      "url": "https://example.com/wp-content/uploads/report.pdf",
      "filename": "report.pdf",
      "filesize": "200 kB"
    }
  ]
}
```

---

## Shared Conventions (all blocks)

- Block category: `"theme"`
- Supports: `"anchor": true`
- CSS class pattern: `block block-{slug}` on wrapper (e.g. `block block-youtube-video`)
- `data-block="{slug}"` attribute on wrapper (slug only, e.g. `data-block="soundcloud"`)
- `data-props="{json}"` attribute on wrapper — escaped with `esc_attr( wp_json_encode( $props ) )`
- **ACF blocks** auto-registered by existing `acf/init` glob loop in `functions.php`
- **SoundCloud block** registered separately on `init` in `functions.php` (see Block 3)
- SoundCloud REST endpoint added to `functions.php` alongside existing endpoints

---

## File Count

| Block | New Files |
|---|---|
| youtube-video | 3 (block.json, render.php, fields.php) |
| facebook-video | 3 |
| soundcloud | 4 (block.json, render.php, editor.js, editor.asset.php) |
| image-gallery | 3 |
| pdf-documents | 3 |
| functions.php | 1 modified (SoundCloud block registration + REST endpoint) |

**Total: 16 new files, 1 modified.**
