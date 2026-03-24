# Post Blocks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 5 new blocks (YouTube Video, Facebook Video, SoundCloud, Image Gallery, PDF Documents) to the headless WordPress theme, each outputting structured `data-props` JSON for headless front-end consumption.

**Architecture:** Four blocks are ACF Pro blocks following the existing `block.json` + `render.php` + `fields.php` pattern. The SoundCloud block is a custom native Gutenberg block (no ACF) that fetches tracks from the channel's public RSS feed via a WordPress REST endpoint and provides a searchable track picker in the block editor. All blocks are auto-discovered by the existing glob in `functions.php`.

**Tech Stack:** PHP 8.1+, WordPress 6.x, ACF Pro 6.3+, SimpleXML (PHP built-in), vanilla JS (wp.blocks / wp.element / wp.apiFetch — no build step).

**Spec:** `docs/superpowers/specs/2026-03-24-post-blocks-design.md`

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `blocks/youtube-video/block.json` | Create | Block manifest |
| `blocks/youtube-video/fields.php` | Create | ACF field group |
| `blocks/youtube-video/render.php` | Create | Props extraction + HTML wrapper |
| `blocks/facebook-video/block.json` | Create | Block manifest |
| `blocks/facebook-video/fields.php` | Create | ACF field group |
| `blocks/facebook-video/render.php` | Create | Props extraction + URL validation |
| `blocks/image-gallery/block.json` | Create | Block manifest |
| `blocks/image-gallery/fields.php` | Create | ACF field group |
| `blocks/image-gallery/render.php` | Create | Map image IDs → URL/dimensions/alt |
| `blocks/pdf-documents/block.json` | Create | Block manifest |
| `blocks/pdf-documents/fields.php` | Create | ACF field group |
| `blocks/pdf-documents/render.php` | Create | Map repeater → file metadata |
| `blocks/soundcloud/block.json` | Create | Block manifest + attribute schema |
| `blocks/soundcloud/render.php` | Create | Output stored track attrs as data-props |
| `blocks/soundcloud/editor.asset.php` | Create | Script dependency declaration |
| `blocks/soundcloud/editor.js` | Create | Track picker UI (vanilla JS) |
| `functions.php` | Modify | SoundCloud REST endpoint (block auto-registered by existing glob loop — no extra registration needed) |

---

## Task 1: YouTube Video Block

**Files:**
- Create: `blocks/youtube-video/block.json`
- Create: `blocks/youtube-video/fields.php`
- Create: `blocks/youtube-video/render.php`

- [ ] **Step 1: Create `blocks/youtube-video/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "acf/youtube-video",
  "title": "YouTube Video",
  "description": "Embed a YouTube video with optional title and caption.",
  "category": "theme",
  "icon": "video-alt3",
  "keywords": ["youtube", "video", "embed"],
  "acf": {
    "version": 3,
    "mode": "preview",
    "renderTemplate": "blocks/youtube-video/render.php"
  },
  "supports": {
    "anchor": true
  }
}
```

- [ ] **Step 2: Create `blocks/youtube-video/fields.php`**

```php
<?php
/**
 * YouTube Video Block — ACF Field Group
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_youtube_video',
        'title'  => 'YouTube Video',
        'fields' => [
            [
                'key'          => 'field_youtube_video_url',
                'label'        => 'Video URL',
                'name'         => 'video_url',
                'type'         => 'url',
                'required'     => 1,
                'placeholder'  => 'https://www.youtube.com/watch?v=...',
                'instructions' => 'Paste the YouTube video URL. Supports youtube.com and youtu.be formats.',
            ],
            [
                'key'         => 'field_youtube_title',
                'label'       => 'Title',
                'name'        => 'title',
                'type'        => 'text',
                'placeholder' => 'Optional title',
            ],
            [
                'key'         => 'field_youtube_caption',
                'label'       => 'Caption',
                'name'        => 'caption',
                'type'        => 'textarea',
                'rows'        => 3,
                'placeholder' => 'Optional caption',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/youtube-video',
                ],
            ],
        ],
    ] );
} );
```

- [ ] **Step 3: Create `blocks/youtube-video/render.php`**

```php
<?php
/**
 * YouTube Video Block — Render Template (ACF Block v3)
 *
 * Variables injected by ACF Pro 6.3+:
 *   $block      (array) Block attributes.
 *   $is_preview (bool)  True when rendered inside the block editor.
 *
 * @package Headless
 */

$video_url = get_field( 'video_url' );
$title     = get_field( 'title' );
$caption   = get_field( 'caption' );

// Validate host and extract video ID.
$video_id = null;
if ( $video_url ) {
    $scheme  = wp_parse_url( $video_url, PHP_URL_SCHEME );
    $host    = wp_parse_url( $video_url, PHP_URL_HOST );
    $allowed = [ 'youtube.com', 'www.youtube.com', 'youtu.be' ];

    if ( $scheme === 'https' && in_array( $host, $allowed, true ) ) {
        if ( $host === 'youtu.be' ) {
            // https://youtu.be/VIDEO_ID or https://youtu.be/VIDEO_ID?si=TOKEN
            $path     = trim( wp_parse_url( $video_url, PHP_URL_PATH ), '/' );
            $video_id = strtok( $path, '/' );
        } else {
            $path = wp_parse_url( $video_url, PHP_URL_PATH );
            if ( str_starts_with( $path ?? '', '/embed/' ) ) {
                // https://www.youtube.com/embed/VIDEO_ID
                $video_id = trim( substr( $path, strlen( '/embed/' ) ), '/' );
            } else {
                // https://www.youtube.com/watch?v=VIDEO_ID&si=TOKEN
                parse_str( wp_parse_url( $video_url, PHP_URL_QUERY ) ?? '', $query );
                $video_id = $query['v'] ?? null;
            }
        }
        // Video IDs are alphanumeric + hyphen + underscore only.
        if ( $video_id && ! preg_match( '/^[a-zA-Z0-9_-]+$/', $video_id ) ) {
            $video_id = null;
        }
    } else {
        $video_url = null; // invalid host / scheme — omit from props
    }
}

$props = [
    'video_id'  => $video_id,
    'video_url' => $video_url,
    'title'     => $title   ?: null,
    'caption'   => $caption ?: null,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-youtube-video',
    $block['className'] ?? '',
] );
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="youtube-video"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $is_preview ) : ?>
        <p style="padding:1rem;background:#f0f0f0;margin:0;">
            <?php if ( $video_id ) : ?>
                YouTube: <code><?php echo esc_html( $video_id ); ?></code>
                <?php if ( $title ) : ?> &mdash; <strong><?php echo esc_html( $title ); ?></strong><?php endif; ?>
            <?php else : ?>
                Add a YouTube URL above to preview.
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>
```

- [ ] **Step 4: Verify the block appears in the WordPress block editor**

Open any Post in the WordPress admin. In the block inserter, search for "YouTube". The block should appear under the "Theme" category. Insert it, paste `https://www.youtube.com/watch?v=dQw4w9WgXcQ` in the Video URL field. The preview should show `YouTube: dQw4w9WgXcQ`.

- [ ] **Step 5: Verify data-props via REST API**

Save the post (note the post ID from the URL, e.g. `42`). Then run:

```bash
curl -s "http://localhost/wp-json/headless/v1/posts/42/blocks" | python -m json.tool
```

Find the `acf/youtube-video` block entry. Confirm `fields.video_id` equals `dQw4w9WgXcQ` and `fields.video_url` is set. Confirm `youtu.be` short URLs also extract the correct ID by editing the block and using `https://youtu.be/dQw4w9WgXcQ` — same result expected.

- [ ] **Step 6: Commit**

```bash
git add blocks/youtube-video/
git commit -m "feat: add YouTube Video ACF block"
```

---

## Task 2: Facebook Video Block

**Files:**
- Create: `blocks/facebook-video/block.json`
- Create: `blocks/facebook-video/fields.php`
- Create: `blocks/facebook-video/render.php`

- [ ] **Step 1: Create `blocks/facebook-video/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "acf/facebook-video",
  "title": "Facebook Video",
  "description": "Embed a Facebook video with optional title and caption.",
  "category": "theme",
  "icon": "facebook",
  "keywords": ["facebook", "video", "embed", "fb"],
  "acf": {
    "version": 3,
    "mode": "preview",
    "renderTemplate": "blocks/facebook-video/render.php"
  },
  "supports": {
    "anchor": true
  }
}
```

- [ ] **Step 2: Create `blocks/facebook-video/fields.php`**

```php
<?php
/**
 * Facebook Video Block — ACF Field Group
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_facebook_video',
        'title'  => 'Facebook Video',
        'fields' => [
            [
                'key'          => 'field_facebook_video_url',
                'label'        => 'Video URL',
                'name'         => 'video_url',
                'type'         => 'url',
                'required'     => 1,
                'placeholder'  => 'https://www.facebook.com/watch/?v=...',
                'instructions' => 'Paste the Facebook video URL from the address bar or share dialog.',
            ],
            [
                'key'         => 'field_facebook_title',
                'label'       => 'Title',
                'name'        => 'title',
                'type'        => 'text',
                'placeholder' => 'Optional title',
            ],
            [
                'key'         => 'field_facebook_caption',
                'label'       => 'Caption',
                'name'        => 'caption',
                'type'        => 'textarea',
                'rows'        => 3,
                'placeholder' => 'Optional caption',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/facebook-video',
                ],
            ],
        ],
    ] );
} );
```

- [ ] **Step 3: Create `blocks/facebook-video/render.php`**

```php
<?php
/**
 * Facebook Video Block — Render Template (ACF Block v3)
 *
 * @package Headless
 */

$video_url = get_field( 'video_url' );
$title     = get_field( 'title' );
$caption   = get_field( 'caption' );

// Validate: must be https and from facebook.com.
if ( $video_url ) {
    $scheme  = wp_parse_url( $video_url, PHP_URL_SCHEME );
    $host    = wp_parse_url( $video_url, PHP_URL_HOST );
    $allowed = [ 'facebook.com', 'www.facebook.com' ];

    if ( $scheme !== 'https' || ! in_array( $host, $allowed, true ) ) {
        $video_url = null;
    }
}

$props = [
    'video_url' => $video_url,
    'title'     => $title   ?: null,
    'caption'   => $caption ?: null,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-facebook-video',
    $block['className'] ?? '',
] );
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="facebook-video"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $is_preview ) : ?>
        <p style="padding:1rem;background:#f0f0f0;margin:0;">
            <?php if ( $video_url ) : ?>
                Facebook Video: <code><?php echo esc_html( $video_url ); ?></code>
                <?php if ( $title ) : ?> &mdash; <strong><?php echo esc_html( $title ); ?></strong><?php endif; ?>
            <?php else : ?>
                Add a Facebook video URL above to preview.
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>
```

- [ ] **Step 4: Verify in block editor**

Insert the "Facebook Video" block in a Post. Paste `https://www.facebook.com/watch/?v=123456789`. Preview should show the URL. Try a non-Facebook URL (`https://example.com`) — `video_url` should be `null` in data-props.

- [ ] **Step 5: Verify data-props via REST API**

```bash
curl -s "http://localhost/wp-json/headless/v1/posts/42/blocks" | python -m json.tool
```

Find `acf/facebook-video`. Confirm `fields.video_url` is the Facebook URL. Confirm invalid URLs result in `null`.

- [ ] **Step 6: Commit**

```bash
git add blocks/facebook-video/
git commit -m "feat: add Facebook Video ACF block"
```

---

## Task 3: Image Gallery Block

**Files:**
- Create: `blocks/image-gallery/block.json`
- Create: `blocks/image-gallery/fields.php`
- Create: `blocks/image-gallery/render.php`

- [ ] **Step 1: Create `blocks/image-gallery/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "acf/image-gallery",
  "title": "Image Gallery",
  "description": "A responsive image gallery with configurable column count.",
  "category": "theme",
  "icon": "format-gallery",
  "keywords": ["gallery", "images", "photos", "galerija"],
  "acf": {
    "version": 3,
    "mode": "preview",
    "renderTemplate": "blocks/image-gallery/render.php"
  },
  "supports": {
    "anchor": true
  }
}
```

- [ ] **Step 2: Create `blocks/image-gallery/fields.php`**

```php
<?php
/**
 * Image Gallery Block — ACF Field Group
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_image_gallery',
        'title'  => 'Image Gallery',
        'fields' => [
            [
                'key'         => 'field_gallery_title',
                'label'       => 'Title',
                'name'        => 'title',
                'type'        => 'text',
                'placeholder' => 'Optional gallery heading',
            ],
            [
                'key'           => 'field_gallery_images',
                'label'         => 'Images',
                'name'          => 'images',
                'type'          => 'gallery',
                'required'      => 1,
                'return_format' => 'id',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'instructions'  => 'Select or upload images for the gallery.',
            ],
            [
                'key'           => 'field_gallery_columns',
                'label'         => 'Columns',
                'name'          => 'columns',
                'type'          => 'select',
                'choices'       => [
                    '2' => '2 Columns',
                    '3' => '3 Columns',
                    '4' => '4 Columns',
                ],
                'default_value' => '3',
                'return_format' => 'value',
                'ui'            => 0,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/image-gallery',
                ],
            ],
        ],
    ] );
} );
```

- [ ] **Step 3: Create `blocks/image-gallery/render.php`**

```php
<?php
/**
 * Image Gallery Block — Render Template (ACF Block v3)
 *
 * @package Headless
 */

$title   = get_field( 'title' );
$images  = get_field( 'images' ) ?: []; // array of attachment IDs
$columns = (int) ( get_field( 'columns' ) ?: 3 );

$image_data = [];
foreach ( $images as $image_id ) {
    $src = wp_get_attachment_image_src( (int) $image_id, 'headless-large' );
    if ( ! $src ) {
        continue;
    }
    $image_data[] = [
        'id'     => (int) $image_id,
        'url'    => $src[0],
        'width'  => (int) $src[1],  // dimensions of the generated file, not original
        'height' => (int) $src[2],
        'alt'    => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
    ];
}

$props = [
    'title'   => $title ?: null,
    'columns' => $columns,
    'images'  => $image_data,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-image-gallery',
    $block['className'] ?? '',
] );
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="image-gallery"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $is_preview ) : ?>
        <p style="padding:1rem;background:#f0f0f0;margin:0;">
            Image Gallery
            <?php if ( $title ) : ?> &mdash; <strong><?php echo esc_html( $title ); ?></strong><?php endif; ?>
            (<?php echo count( $image_data ); ?> image<?php echo count( $image_data ) !== 1 ? 's' : ''; ?>,
            <?php echo esc_html( $columns ); ?> columns)
        </p>
    <?php endif; ?>
</div>
```

- [ ] **Step 4: Verify in block editor**

Insert "Image Gallery" in a Post. Upload 3 images, set columns to 3. Preview should show the count. Switch to 4 columns, confirm the count updates.

- [ ] **Step 5: Verify data-props via REST API**

```bash
curl -s "http://localhost/wp-json/headless/v1/posts/42/blocks" | python -m json.tool
```

Find `acf/image-gallery`. Confirm `fields.images` is an array of objects with `id`, `url`, `width`, `height`, `alt`. Confirm `fields.columns` is the integer you selected.

- [ ] **Step 6: Commit**

```bash
git add blocks/image-gallery/
git commit -m "feat: add Image Gallery ACF block"
```

---

## Task 4: PDF Documents Block

**Files:**
- Create: `blocks/pdf-documents/block.json`
- Create: `blocks/pdf-documents/fields.php`
- Create: `blocks/pdf-documents/render.php`

- [ ] **Step 1: Create `blocks/pdf-documents/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "acf/pdf-documents",
  "title": "PDF Documents",
  "description": "A list of downloadable PDF documents.",
  "category": "theme",
  "icon": "media-document",
  "keywords": ["pdf", "document", "download", "file", "dokumenti"],
  "acf": {
    "version": 3,
    "mode": "preview",
    "renderTemplate": "blocks/pdf-documents/render.php"
  },
  "supports": {
    "anchor": true
  }
}
```

- [ ] **Step 2: Create `blocks/pdf-documents/fields.php`**

```php
<?php
/**
 * PDF Documents Block — ACF Field Group
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_pdf_documents',
        'title'  => 'PDF Documents',
        'fields' => [
            [
                'key'         => 'field_pdf_title',
                'label'       => 'Title',
                'name'        => 'title',
                'type'        => 'text',
                'placeholder' => 'Optional section heading',
            ],
            [
                'key'          => 'field_pdf_documents',
                'label'        => 'Documents',
                'name'         => 'documents',
                'type'         => 'repeater',
                'required'     => 1,
                'min'          => 1,
                'layout'       => 'block',
                'button_label' => 'Add Document',
                'sub_fields'   => [
                    [
                        'key'      => 'field_pdf_doc_title',
                        'label'    => 'Document Title',
                        'name'     => 'doc_title',
                        'type'     => 'text',
                        'required' => 1,
                        'placeholder' => 'e.g. Annual Report 2025',
                    ],
                    [
                        'key'           => 'field_pdf_doc_file',
                        'label'         => 'PDF File',
                        'name'          => 'file',
                        'type'          => 'file',
                        'required'      => 1,
                        'return_format' => 'array',
                        'library'       => 'all',
                        'mime_types'    => 'pdf',
                        'instructions'  => 'Upload a PDF file.',
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/pdf-documents',
                ],
            ],
        ],
    ] );
} );
```

- [ ] **Step 3: Create `blocks/pdf-documents/render.php`**

```php
<?php
/**
 * PDF Documents Block — Render Template (ACF Block v3)
 *
 * @package Headless
 */

$title     = get_field( 'title' );
$documents = get_field( 'documents' ) ?: [];

$doc_data = [];
foreach ( $documents as $doc ) {
    $file = $doc['file'] ?? null; // ACF file array: url, filename, filesize (formatted string), mime_type, etc.
    if ( ! $file || empty( $file['url'] ) ) {
        continue;
    }
    $doc_data[] = [
        'title'    => (string) ( $doc['doc_title'] ?? '' ),
        'url'      => (string) $file['url'],
        'filename' => (string) ( $file['filename'] ?? '' ),
        'filesize' => (string) ( $file['filesize'] ?? '' ), // ACF returns formatted string, e.g. "200 kB"
    ];
}

$props = [
    'title'     => $title ?: null,
    'documents' => $doc_data,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-pdf-documents',
    $block['className'] ?? '',
] );
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="pdf-documents"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $is_preview ) : ?>
        <p style="padding:1rem;background:#f0f0f0;margin:0;">
            PDF Documents
            <?php if ( $title ) : ?> &mdash; <strong><?php echo esc_html( $title ); ?></strong><?php endif; ?>
            (<?php echo count( $doc_data ); ?> file<?php echo count( $doc_data ) !== 1 ? 's' : ''; ?>)
        </p>
    <?php endif; ?>
</div>
```

- [ ] **Step 4: Verify in block editor**

Insert "PDF Documents" in a Post. Add a document row, enter a title and upload a PDF. Preview should show the file count. Add a second row to confirm the repeater works.

- [ ] **Step 5: Verify data-props via REST API**

```bash
curl -s "http://localhost/wp-json/headless/v1/posts/42/blocks" | python -m json.tool
```

Find `acf/pdf-documents`. Confirm `fields.documents` is an array with `title`, `url`, `filename`, `filesize` (a string like `"200 kB"`).

- [ ] **Step 6: Commit**

```bash
git add blocks/pdf-documents/
git commit -m "feat: add PDF Documents ACF block"
```

---

## Task 5: SoundCloud REST Endpoint + Block Registration

**Files:**
- Modify: `functions.php` (append two new sections at the end)

- [ ] **Step 1: Append the SoundCloud REST endpoint to `functions.php`**

**No separate block registration is needed.** The existing glob loop in `functions.php` section 9 calls `register_block_type( $block_dir )` on every `blocks/*/block.json` — this is a plain WordPress function that works for native blocks as well as ACF blocks. When `blocks/soundcloud/block.json` exists it will be picked up and registered automatically on `acf/init`. Adding a second `add_action( 'init', ... )` would double-register the block and cause errors. Do NOT add one.

Add only the REST endpoint at the very end of `functions.php` (after section 17):

```php
// ---------------------------------------------------------------------------
// 18. SoundCloud Tracks Endpoint  GET /wp-json/headless/v1/soundcloud/tracks
//
// Fetches the public RSS feed for the SoundCloud channel and returns a
// structured track list. Results are cached in a WP transient for 1 hour.
//
// Bootstrap: on first call the channel page is fetched to discover the RSS
// feed URL (embedded as <link rel="alternate" type="application/rss+xml">
// in the page <head>), which is then stored in wp_options for reuse.
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/soundcloud/tracks', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_soundcloud_tracks',
        'permission_callback' => '__return_true',
    ] );
} );

/**
 * REST callback — wraps headless_soundcloud_fetch_tracks() for the REST layer.
 */
function headless_get_soundcloud_tracks(): WP_REST_Response|WP_Error {
    $tracks = headless_soundcloud_fetch_tracks();
    if ( is_wp_error( $tracks ) ) {
        return $tracks;
    }
    return rest_ensure_response( $tracks );
}

/**
 * Fetches, parses, and caches tracks from the SoundCloud channel RSS feed.
 *
 * @return array|WP_Error Array of track objects on success, WP_Error on failure.
 */
function headless_soundcloud_fetch_tracks(): array|WP_Error {

    // 1. Return cached result if available.
    $cached = get_transient( 'headless_soundcloud_tracks' );
    if ( false !== $cached ) {
        return $cached;
    }

    // 2. Respect negative-cache: bootstrap recently failed — don't retry for 5 min.
    if ( get_transient( 'headless_soundcloud_bootstrap_failed' ) ) {
        return new WP_Error(
            'soundcloud_bootstrap_failed',
            __( 'SoundCloud channel is temporarily unavailable. Please try again shortly.', 'headless' ),
            [ 'status' => 503 ]
        );
    }

    // 3. Get stored RSS URL, or bootstrap by fetching the channel page.
    $rss_url = get_option( 'headless_soundcloud_rss_url', '' );

    if ( ! $rss_url ) {
        $channel_url = 'https://soundcloud.com/radio-velika-kladu-a';
        $response    = wp_remote_get( $channel_url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( 'headless_soundcloud_bootstrap_failed', true, 300 );
            return new WP_Error(
                'soundcloud_bootstrap_failed',
                __( 'Failed to reach the SoundCloud channel page.', 'headless' ),
                [ 'status' => 503 ]
            );
        }

        $body = wp_remote_retrieve_body( $response );

        // Match <link ... type="application/rss+xml" ... href="..."> in either attribute order.
        if ( ! preg_match( '/<link[^>]+type=["\']application\/rss\+xml["\'][^>]+href=["\']([^"\']+)["\']/', $body, $m ) &&
             ! preg_match( '/<link[^>]+href=["\']([^"\']+)["\'][^>]+type=["\']application\/rss\+xml["\']/', $body, $m ) ) {
            set_transient( 'headless_soundcloud_bootstrap_failed', true, 300 );
            return new WP_Error(
                'soundcloud_bootstrap_failed',
                __( 'Could not find RSS feed link on the SoundCloud channel page.', 'headless' ),
                [ 'status' => 503 ]
            );
        }

        $rss_url = esc_url_raw( $m[1] );
        update_option( 'headless_soundcloud_rss_url', $rss_url );
    }

    // 4. Fetch the RSS feed.
    $rss_response = wp_remote_get( $rss_url, [ 'timeout' => 10 ] );

    if ( is_wp_error( $rss_response ) || wp_remote_retrieve_response_code( $rss_response ) !== 200 ) {
        return new WP_Error(
            'soundcloud_rss_failed',
            __( 'Failed to fetch the SoundCloud RSS feed.', 'headless' ),
            [ 'status' => 502 ]
        );
    }

    // 5. Parse the RSS XML.
    $xml_body = wp_remote_retrieve_body( $rss_response );
    libxml_use_internal_errors( true );
    $xml = simplexml_load_string( $xml_body );

    if ( ! $xml ) {
        return new WP_Error(
            'soundcloud_rss_failed',
            __( 'Failed to parse the SoundCloud RSS feed.', 'headless' ),
            [ 'status' => 502 ]
        );
    }

    // 6. Map items to track objects.
    $xml->registerXPathNamespace( 'itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd' );

    $tracks = [];

    foreach ( $xml->channel->item as $item ) {
        $item->registerXPathNamespace( 'itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd' );

        $url = (string) $item->link;

        // Skip items whose URL is not on soundcloud.com (e.g. redirect or mobile URLs).
        if ( ! preg_match( '#^https://soundcloud\.com/#', $url ) ) {
            continue;
        }

        $duration_nodes = $item->xpath( 'itunes:duration' );
        $image_nodes    = $item->xpath( 'itunes:image' );

        $tracks[] = [
            'title'       => (string) $item->title,
            'url'         => $url,
            'duration'    => $duration_nodes ? (string) $duration_nodes[0] : '',
            'artwork_url' => $image_nodes    ? (string) $image_nodes[0]['href'] : '',
        ];
    }

    // 7. Cache for 1 hour and return.
    set_transient( 'headless_soundcloud_tracks', $tracks, HOUR_IN_SECONDS );

    return $tracks;
}
```

- [ ] **Step 2: Verify the REST endpoint returns tracks**

```bash
curl -s "http://localhost/wp-json/headless/v1/soundcloud/tracks" | python -m json.tool
```

Expected: a JSON array of objects with `title`, `url`, `duration`, `artwork_url`. If the channel page bootstrap fails, you will see a 503 error body — check `WP_DEBUG` output or the WordPress error log for the reason.

- [ ] **Step 3: Test negative-cache behaviour**

Delete the `headless_soundcloud_rss_url` option from the database (via phpMyAdmin or WP-CLI: `wp option delete headless_soundcloud_rss_url`). Then make two rapid requests to the endpoint. Confirm the second request within 5 minutes of a bootstrap failure returns 503 immediately without re-fetching the SoundCloud page.

- [ ] **Step 4: Commit**

```bash
git add functions.php
git commit -m "feat: add SoundCloud REST endpoint and block registration"
```

---

## Task 6: SoundCloud Block Files

**Files:**
- Create: `blocks/soundcloud/block.json`
- Create: `blocks/soundcloud/render.php`

- [ ] **Step 1: Create `blocks/soundcloud/block.json`**

Note: this is a **native Gutenberg block** — no `"acf"` key. The `"render"` key (not `"acf.renderTemplate"`) points to the PHP render template. The `"editorScript"` key is resolved against `editor.asset.php` automatically by WordPress when that file exists alongside the JS file.

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "headless/soundcloud",
  "title": "SoundCloud",
  "description": "Select and embed a SoundCloud track from the radio channel.",
  "category": "theme",
  "icon": "format-audio",
  "keywords": ["soundcloud", "audio", "radio", "muzika"],
  "attributes": {
    "track_url":      { "type": "string", "default": "" },
    "track_title":    { "type": "string", "default": "" },
    "track_duration": { "type": "string", "default": "" },
    "track_artwork":  { "type": "string", "default": "" }
  },
  "editorScript": "file:./editor.js",
  "render": "file:./render.php",
  "supports": {
    "anchor": true
  }
}
```

- [ ] **Step 2: Create `blocks/soundcloud/render.php`**

**Important:** this is a native WordPress dynamic block, not an ACF block. WordPress injects `$attributes` (array), `$content` (string), and `$block` (WP_Block object). Do NOT use `get_field()` here — read from `$attributes` instead.

```php
<?php
/**
 * SoundCloud Block — Render Template (Native Gutenberg dynamic block)
 *
 * Variables injected by WordPress (NOT ACF):
 *   $attributes (array)    Block attributes as defined in block.json.
 *   $content    (string)   Inner block HTML (empty — leaf block).
 *   $block      (WP_Block) Block instance object.
 *
 * Unlike ACF blocks, fields are NOT accessed via get_field().
 * Use $attributes['key'] directly.
 *
 * @package Headless
 */

$track_url      = $attributes['track_url']      ?? '';
$track_title    = $attributes['track_title']    ?? '';
$track_duration = $attributes['track_duration'] ?? '';
$track_artwork  = $attributes['track_artwork']  ?? '';

$props = [
    'track_url'      => $track_url      ?: null,
    'track_title'    => $track_title    ?: null,
    'track_duration' => $track_duration ?: null,
    'track_artwork'  => $track_artwork  ?: null,
];

// For native blocks, anchor comes from $attributes (not $block['anchor']).
$block_id = ! empty( $attributes['anchor'] ) ? $attributes['anchor'] : '';

$classes = array_filter( [
    'block',
    'block-soundcloud',
    $attributes['className'] ?? '',
] );
?>
<div
    <?php if ( $block_id ) : ?>id="<?php echo esc_attr( $block_id ); ?>"<?php endif; ?>
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="soundcloud"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
></div>
```

- [ ] **Step 3: Confirm the block folder exists and block.json is in place**

Check that `blocks/soundcloud/block.json` and `blocks/soundcloud/render.php` both exist. The editor JS files (Task 7) must also be in place before the block will load in the editor — proceed to Task 7 before testing.

- [ ] **Step 4: Commit**

```bash
git add blocks/soundcloud/block.json blocks/soundcloud/render.php
git commit -m "feat: add SoundCloud block manifest and render template"
```

---

## Task 7: SoundCloud Editor Script

**Files:**
- Create: `blocks/soundcloud/editor.asset.php`
- Create: `blocks/soundcloud/editor.js`

- [ ] **Step 1: Create `blocks/soundcloud/editor.asset.php`**

WordPress's block asset loader looks for `{script-name}.asset.php` alongside the JS file. It must return an array with exactly `dependencies` and `version` keys.

```php
<?php
/**
 * SoundCloud Block — Editor Script Asset File
 *
 * WordPress uses this to enqueue the correct script dependencies before
 * editor.js runs. Must return an array with 'dependencies' and 'version'.
 *
 * @package Headless
 */
return [
    'dependencies' => [
        'wp-blocks',
        'wp-element',
        'wp-block-editor',
        'wp-api-fetch',
        'wp-components',
    ],
    'version' => '1.0.0',
];
```

- [ ] **Step 2: Create `blocks/soundcloud/editor.js`**

Plain vanilla JS — no JSX, no build step. Uses `wp.*` globals loaded from `editor.asset.php` dependencies.

```js
/**
 * SoundCloud Block — Editor Script
 *
 * Registers the block type and provides the track-picker editor UI.
 * Vanilla JS only — no JSX, no build step required.
 *
 * @package Headless
 */
( function ( blocks, element, blockEditor, apiFetch, components ) {
    'use strict';

    var el         = element.createElement;
    var useState   = element.useState;
    var useEffect  = element.useEffect;
    var Spinner    = components.Spinner;

    blocks.registerBlockType( 'headless/soundcloud', {

        edit: function ( props ) {
            var attributes   = props.attributes;
            var setAttributes = props.setAttributes;

            // State: tracks list, loading flag, error message, current view, filter text.
            var stateHook = useState( {
                tracks:  [],
                loading: false,
                error:   null,
                view:    attributes.track_url ? 'selected' : 'list',
                filter:  '',
            } );
            var state    = stateHook[0];
            var setState = stateHook[1];

            function merge( patch ) {
                setState( function ( s ) { return Object.assign( {}, s, patch ); } );
            }

            // Fetch tracks when the list view is active and tracks are not yet loaded.
            useEffect( function () {
                if ( state.view !== 'list' || state.tracks.length > 0 || state.loading ) {
                    return;
                }
                merge( { loading: true, error: null } );
                apiFetch( { path: '/headless/v1/soundcloud/tracks' } )
                    .then( function ( tracks ) {
                        merge( { tracks: tracks, loading: false } );
                    } )
                    .catch( function ( err ) {
                        merge( {
                            error:   ( err && err.message ) ? err.message : 'Failed to load tracks.',
                            loading: false,
                        } );
                    } );
            }, [ state.view ] );  // re-run whenever view changes to 'list'

            // ---- SELECTED VIEW ----
            if ( state.view === 'selected' && attributes.track_url ) {
                return el( 'div', { className: 'headless-soundcloud-block' },
                    el( 'div', {
                            style: {
                                display:     'flex',
                                gap:         '12px',
                                alignItems:  'center',
                                padding:     '12px',
                                background:  '#f6f7f7',
                                borderRadius: '4px',
                                border:      '1px solid #e0e0e0',
                            },
                        },
                        attributes.track_artwork
                            ? el( 'img', {
                                src:   attributes.track_artwork,
                                width:  56,
                                height: 56,
                                style: { objectFit: 'cover', borderRadius: '4px', flexShrink: 0 },
                              } )
                            : el( 'div', {
                                style: {
                                    width: 56, height: 56, background: '#ff5500',
                                    borderRadius: '4px', flexShrink: 0,
                                },
                              } ),
                        el( 'div', { style: { overflow: 'hidden', flex: 1 } },
                            el( 'div', {
                                style: {
                                    fontWeight: 600,
                                    overflow:   'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace: 'nowrap',
                                },
                            }, attributes.track_title || 'SoundCloud Track' ),
                            attributes.track_duration
                                ? el( 'div', { style: { fontSize: '12px', color: '#757575', marginTop: '2px' } },
                                    attributes.track_duration )
                                : null
                        )
                    ),
                    el( 'button', {
                        onClick: function () { merge( { view: 'list' } ); },
                        style:   { marginTop: '8px', cursor: 'pointer' },
                    }, 'Change track' )
                );
            }

            // ---- LOADING STATE ----
            if ( state.loading ) {
                return el( 'div', {
                        className: 'headless-soundcloud-block',
                        style:     { padding: '16px', textAlign: 'center' },
                    },
                    el( Spinner, null ),
                    el( 'p', { style: { marginTop: '8px' } }, 'Loading tracks\u2026' )
                );
            }

            // ---- ERROR STATE ----
            if ( state.error ) {
                return el( 'div', {
                        className: 'headless-soundcloud-block',
                        style:     { padding: '16px' },
                    },
                    el( 'p', { style: { color: '#cc0000', margin: '0 0 8px' } }, state.error ),
                    el( 'button', {
                        onClick: function () { merge( { error: null, loading: false, tracks: [] } ); },
                        style:   { cursor: 'pointer' },
                    }, 'Retry' )
                );
            }

            // ---- LIST VIEW ----
            var filtered = state.tracks.filter( function ( t ) {
                return ! state.filter ||
                    t.title.toLowerCase().indexOf( state.filter.toLowerCase() ) !== -1;
            } );

            return el( 'div', { className: 'headless-soundcloud-block' },

                // Filter input
                el( 'input', {
                    type:        'text',
                    placeholder: 'Filter tracks\u2026',
                    value:       state.filter,
                    onChange:    function ( e ) { merge( { filter: e.target.value } ); },
                    style:       {
                        width:       '100%',
                        marginBottom: '6px',
                        padding:     '6px 8px',
                        boxSizing:   'border-box',
                        border:      '1px solid #ccc',
                        borderRadius: '3px',
                    },
                } ),

                // Track list
                el( 'div', {
                        style: {
                            maxHeight:   '320px',
                            overflowY:   'auto',
                            border:      '1px solid #e0e0e0',
                            borderRadius: '4px',
                        },
                    },
                    filtered.length === 0
                        ? el( 'p', { style: { padding: '12px', color: '#757575', margin: 0 } }, 'No tracks found.' )
                        : filtered.map( function ( track, i ) {
                            var isSelected = attributes.track_url === track.url;
                            return el( 'div', {
                                    key:     track.url || i,
                                    onClick: function () {
                                        setAttributes( {
                                            track_url:      track.url,
                                            track_title:    track.title,
                                            track_duration: track.duration   || '',
                                            track_artwork:  track.artwork_url || '',
                                        } );
                                        merge( { view: 'selected' } );
                                    },
                                    style: {
                                        display:     'flex',
                                        gap:         '10px',
                                        alignItems:  'center',
                                        padding:     '8px 12px',
                                        cursor:      'pointer',
                                        borderBottom: '1px solid #f0f0f0',
                                        background:  isSelected ? '#e8f0fe' : 'white',
                                    },
                                },
                                track.artwork_url
                                    ? el( 'img', {
                                        src:    track.artwork_url,
                                        width:  40,
                                        height: 40,
                                        style:  { objectFit: 'cover', borderRadius: '2px', flexShrink: 0 },
                                      } )
                                    : el( 'div', {
                                        style: {
                                            width: 40, height: 40, background: '#ff5500',
                                            borderRadius: '2px', flexShrink: 0,
                                        },
                                      } ),
                                el( 'div', { style: { overflow: 'hidden', flex: 1 } },
                                    el( 'div', {
                                        style: {
                                            fontWeight:   isSelected ? 600 : 400,
                                            overflow:     'hidden',
                                            textOverflow: 'ellipsis',
                                            whiteSpace:   'nowrap',
                                        },
                                    }, track.title ),
                                    track.duration
                                        ? el( 'div', { style: { fontSize: '12px', color: '#757575' } }, track.duration )
                                        : null
                                )
                            );
                        } )
                )
            );
        },

        // Dynamic block — server-side rendered via render.php. save() must return null.
        save: function () {
            return null;
        },
    } );

} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.apiFetch,
    window.wp.components
);
```

- [ ] **Step 3: Verify the SoundCloud block appears in the block editor**

Open a Post. In the block inserter, search for "SoundCloud". The block should appear under "Theme". Insert it — you should see either a loading spinner or the track list.

- [ ] **Step 4: Select a track and verify data-props**

Click a track in the list. The view should switch to the "selected" state showing artwork, title, and duration. Save the post. Run:

```bash
curl -s "http://localhost/wp-json/headless/v1/posts/42/blocks" | python -m json.tool
```

Find `headless/soundcloud`. Confirm `attrs.track_url`, `attrs.track_title`, `attrs.track_duration`, `attrs.track_artwork` are all populated. Also confirm `html` contains `data-block="soundcloud"` and `data-props` with the track data.

- [ ] **Step 5: Verify "Change track" button**

Re-open the post editor. Click the SoundCloud block — the selected state should be shown with the "Change track" button. Click it — the list should reappear (tracks already loaded, no spinner). Select a different track and save.

- [ ] **Step 6: Commit**

```bash
git add blocks/soundcloud/editor.asset.php blocks/soundcloud/editor.js
git commit -m "feat: add SoundCloud block editor UI with track picker"
```

---

## Task 8: Final Smoke Test

- [ ] **Step 1: Verify all 5 blocks appear in the block inserter**

Open a Post. In the inserter search for: YouTube, Facebook, SoundCloud, Image Gallery, PDF Documents. All 5 should appear under "Theme".

- [ ] **Step 2: Insert all 5 blocks in one post and check REST output**

Add one of each block to a test post. Fill in all fields. Save. Run:

```bash
curl -s "http://localhost/wp-json/headless/v1/posts/{ID}/blocks" | python -m json.tool
```

Confirm all 5 blocks appear in the array with correct `name`, `attrs`/`fields`, and `html` containing `data-props`.

- [ ] **Step 3: Verify `data-props` is valid JSON on every block**

Extract the `html` from each block and check the `data-props` attribute decodes correctly. A quick sanity check:

```bash
curl -s "http://localhost/wp-json/headless/v1/posts/{ID}/blocks" \
  | python -c "import sys,json; blocks=json.load(sys.stdin); [print(b['name'], ':', b.get('html','')[:120]) for b in blocks]"
```

Each line should show the block name and the start of its HTML with `data-block=` and `data-props=`.

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete post blocks (YouTube, Facebook, SoundCloud, Gallery, PDFs)"
```
