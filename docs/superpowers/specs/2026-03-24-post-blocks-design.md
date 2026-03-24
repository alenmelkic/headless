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

### ACF Fields
| Name | Key | Type | Notes |
|---|---|---|---|
| `video_url` | `field_youtube_video_url` | url | Required |
| `title` | `field_youtube_title` | text | Optional |
| `caption` | `field_youtube_caption` | textarea | Optional |

### render.php output (data-props)
```json
{
  "video_id": "dQw4w9WgXcQ",
  "video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
  "title": "...",
  "caption": "..."
}
```
`video_id` extracted via regex from the URL (supports standard, short `youtu.be`, and embed URL formats).

---

## Block 2: Facebook Video (`acf/facebook-video`)

### Files
- `blocks/facebook-video/block.json`
- `blocks/facebook-video/render.php`
- `blocks/facebook-video/fields.php`

### ACF Fields
| Name | Key | Type | Notes |
|---|---|---|---|
| `video_url` | `field_facebook_video_url` | url | Required |
| `title` | `field_facebook_title` | text | Optional |
| `caption` | `field_facebook_caption` | textarea | Optional |

### render.php output (data-props)
```json
{
  "video_url": "https://www.facebook.com/watch/?v=123456789",
  "title": "...",
  "caption": "..."
}
```
Full URL passed through — the front-end uses the Facebook embed SDK directly.

---

## Block 3: SoundCloud (`headless/soundcloud`)

Custom native Gutenberg block (no ACF). Optimised for headless.

### Files
- `blocks/soundcloud/block.json`
- `blocks/soundcloud/render.php`
- `blocks/soundcloud/editor.js` (vanilla JS, no build step)

### REST Endpoint
`GET /wp-json/headless/v1/soundcloud/tracks`

Logic (in `functions.php`):
1. Check WP transient `headless_soundcloud_tracks` (1-hour TTL).
2. On miss: check `wp_options` key `headless_soundcloud_rss_url`.
3. If no RSS URL stored (bootstrap): fetch `https://soundcloud.com/radio-velika-kladu-a`, parse `<link rel="alternate" type="application/rss+xml">` from HTML `<head>`, store the discovered RSS URL in `wp_options`.
4. Fetch the RSS URL via `wp_remote_get()`.
5. Parse with `SimpleXMLElement` — extract per track: `title`, `link` (SoundCloud track URL), `itunes:duration`, `itunes:image href`.
6. Store result in transient for 1 hour.
7. Return `[{title, url, duration, artwork_url}]`.

### Block Attributes (stored in block.json)
```json
{
  "track_url":      { "type": "string", "default": "" },
  "track_title":    { "type": "string", "default": "" },
  "track_duration": { "type": "string", "default": "" }
}
```

### Editor UI (`editor.js`)
- Plain JS using `wp.blocks.registerBlockType` + `wp.element.createElement` (no JSX, no build).
- On mount: `apiFetch({ path: '/headless/v1/soundcloud/tracks' })`.
- Displays a scrollable, filterable list of tracks (input filter by title).
- On track click: sets `track_url`, `track_title`, `track_duration` attributes.
- Shows currently selected track with a "change" button.
- Loading / error states handled.

### render.php output (data-props)
```json
{
  "track_url": "https://soundcloud.com/radio-velika-kladu-a/some-track",
  "track_title": "Track Title",
  "track_duration": "1:23:45"
}
```

---

## Block 4: Image Gallery (`acf/image-gallery`)

### Files
- `blocks/image-gallery/block.json`
- `blocks/image-gallery/render.php`
- `blocks/image-gallery/fields.php`

### ACF Fields
| Name | Key | Type | Notes |
|---|---|---|---|
| `title` | `field_gallery_title` | text | Optional heading |
| `images` | `field_gallery_images` | gallery | Returns array of attachment IDs |
| `columns` | `field_gallery_columns` | select | Options: 2, 3, 4 — default 3 |

### render.php output (data-props)
```json
{
  "title": "...",
  "columns": 3,
  "images": [
    { "id": 42, "url": "...", "width": 1200, "height": 900, "alt": "..." },
    ...
  ]
}
```
Images resolved using `headless-large` size via `wp_get_attachment_image_src()` and `get_post_meta()` for alt text.

---

## Block 5: PDF Documents (`acf/pdf-documents`)

### Files
- `blocks/pdf-documents/block.json`
- `blocks/pdf-documents/render.php`
- `blocks/pdf-documents/fields.php`

### ACF Fields
| Name | Key | Type | Notes |
|---|---|---|---|
| `title` | `field_pdf_title` | text | Optional heading |
| `documents` | `field_pdf_documents` | repeater | min: 1 |
| ↳ `doc_title` | `field_pdf_doc_title` | text | Required |
| ↳ `file` | `field_pdf_doc_file` | file | Mime types: `application/pdf`; return format: `array` |

### render.php output (data-props)
```json
{
  "title": "...",
  "documents": [
    {
      "title": "Annual Report 2025",
      "url": "https://example.com/wp-content/uploads/report.pdf",
      "filename": "report.pdf",
      "filesize": 204800
    },
    ...
  ]
}
```

---

## Shared Conventions (all blocks)

- Block category: `"theme"`
- Supports: `"anchor": true`
- CSS class pattern: `block block-{name}` on wrapper
- `data-block="{name}"` attribute on wrapper
- `data-props="{json}"` attribute on wrapper — escaped with `esc_attr( wp_json_encode( $props ) )`
- All blocks auto-registered by existing `glob( .../blocks/*/block.json )` loop in `functions.php`
- SoundCloud REST endpoint added to `functions.php` alongside existing endpoints

---

## File Count

| Block | New Files |
|---|---|
| youtube-video | 3 (block.json, render.php, fields.php) |
| facebook-video | 3 |
| soundcloud | 3 (block.json, render.php, editor.js) |
| image-gallery | 3 |
| pdf-documents | 3 |
| functions.php | 1 modified (SoundCloud REST endpoint added) |

**Total: 15 new files, 1 modified.**
