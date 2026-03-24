# Headless WordPress Theme

**Headless v3** — WordPress as a pure CMS/API backend. All content is consumed via REST API by a Next.js frontend deployed on Vercel.

By Alen M

---

## Architecture

```
functions.php         — All theme logic (REST routes, ACF init, CORS, SEO, preview, revalidation)
includes/cpt.php      — Custom post types
includes/patterns.php — Block patterns
blocks/               — Custom blocks (one folder per block)
  {name}/
    block.json        — Block manifest (required)
    render.php        — Server-side render template (required)
    fields.php        — ACF field group registration (optional)
    editor.js         — Editor UI script (only for native Gutenberg blocks)
    editor.asset.php  — Script dependency manifest (only with editor.js)
acf-json/             — ACF field group JSON (auto-synced, committed to version control)
```

---

## REST API

**Base URL:** `/wp-json/headless/v1`

| Endpoint | Description |
|----------|-------------|
| `GET /menus/{location}` | Nav menu items — locations: `primary`, `footer`, `mobile` |
| `GET /options` | Basic site info (name, description, timezone, etc.) |
| `GET /options/global` | ACF Global Options page fields |
| `GET /posts/{id}/blocks` | All blocks as structured JSON (`name`, `attrs`, `html`, `fields`, `innerBlocks`) |
| `GET /seo/{id}` | SEO meta — auto-detects Yoast → RankMath → fallback |
| `GET /preview?id=X&token=Y` | Draft preview content (token verified via HMAC) |
| `GET /soundcloud/tracks` | Track list from RSS feed, cached 1 hour |

ACF custom fields are also embedded on all post-type REST responses as `"acf": {}`.

---

## Vercel + Next.js Setup

### 1. WordPress — `wp-config.php`

Add these constants (WordPress must be on a publicly accessible host):

```php
define( 'HEADLESS_FRONTEND_URL',     'https://your-project.vercel.app' );
define( 'HEADLESS_PREVIEW_SECRET',   'random-strong-secret' );
define( 'HEADLESS_REVALIDATE_SECRET','another-strong-secret' );

// Optional — derived from HEADLESS_FRONTEND_URL if omitted:
define( 'HEADLESS_REVALIDATE_URL',   'https://your-project.vercel.app/api/revalidate' );
```

### 2. Vercel — Environment Variables

Set in Vercel dashboard → **Settings → Environment Variables**:

```
NEXT_PUBLIC_WP_API_URL   = https://your-wp-site.com/wp-json
PREVIEW_SECRET           = same value as HEADLESS_PREVIEW_SECRET
REVALIDATE_SECRET        = same value as HEADLESS_REVALIDATE_SECRET
```

### 3. Next.js — Required API Routes

#### `app/api/revalidate/route.ts`

Receives a webhook from WordPress on every post save, menu update, or Global Options save, and purges the Next.js cache.

The `type` field in the payload tells you what changed:
- `"post"` — a page/post was saved (use `body.slug` / `body.permalink`)
- `"menu"` — a nav menu was updated (revalidate all pages using the menu)
- `"options"` — ACF Global Options were saved (revalidate everything)

```ts
import { NextRequest, NextResponse } from 'next/server';
import { revalidatePath, revalidateTag } from 'next/cache';

export async function POST(req: NextRequest) {
  const secret = req.headers.get('x-revalidate-secret');
  if (secret !== process.env.REVALIDATE_SECRET) {
    return NextResponse.json({ message: 'Invalid secret' }, { status: 401 });
  }

  const body = await req.json();

  if (body.type === 'post') {
    revalidatePath('/' + body.slug);
  } else if (body.type === 'menu' || body.type === 'options') {
    // Menu or global options changed — revalidate everything.
    revalidatePath('/', 'layout');
  }

  return NextResponse.json({ revalidated: true, type: body.type });
}
```

#### `app/api/preview/route.ts`

Enables Next.js Draft Mode and redirects editors from the WP preview button to the live page.

```ts
import { NextRequest, NextResponse } from 'next/server';
import { draftMode } from 'next/headers';

export async function GET(req: NextRequest) {
  const { searchParams } = req.nextUrl;
  const token    = searchParams.get('token');
  const id       = searchParams.get('id');
  const postType = searchParams.get('post_type') ?? 'page';
  const slug     = searchParams.get('slug') ?? '';

  // Verify token against WP preview endpoint.
  const wpRes = await fetch(
    `${process.env.NEXT_PUBLIC_WP_API_URL}/headless/v1/preview?id=${id}&token=${token}`
  );

  if (!wpRes.ok) {
    return NextResponse.json({ message: 'Invalid token' }, { status: 401 });
  }

  const data = await wpRes.json();

  (await draftMode()).enable();

  // Redirect to the appropriate page — adjust path to your routing.
  return NextResponse.redirect(new URL('/' + (data.slug || slug), req.url));
}
```

### 4. CORS

The theme automatically allows:
- `http://localhost:3000`, `localhost:3001`, `localhost:5173` (local dev)
- The production URL set in `HEADLESS_FRONTEND_URL`
- All `https://*.vercel.app` preview/branch deployment URLs

To disable the Vercel wildcard, add to your theme's `functions.php`:
```php
add_filter( 'headless_cors_allow_vercel', '__return_false' );
```

---

## Blocks

| Block | Type | Name |
|-------|------|------|
| Accordion | ACF | `acf/accordion` |
| Card | ACF | `acf/card` |
| CTA Banner | ACF | `acf/cta-banner` |
| Facebook Video | ACF | `acf/facebook-video` |
| Hero | ACF | `acf/hero` |
| Image Gallery | ACF | `acf/image-gallery` |
| PDF Documents | ACF | `acf/pdf-documents` |
| Section | ACF | `acf/section` |
| Testimonial | ACF | `acf/testimonial` |
| YouTube Video | ACF | `acf/youtube-video` |
| SoundCloud | Native | `headless/soundcloud` |

### Adding a New Block

1. Create `blocks/{name}/block.json` and `blocks/{name}/render.php`
2. Auto-registered — no changes to `functions.php` needed
3. ACF blocks: set `"name": "acf/{name}"` and include the `"acf"` key in `block.json`
4. Native blocks: set `"name": "headless/{name}"`, add `editor.js` + `editor.asset.php`

---

## Image Sizes

| Key | Dimensions | Crop |
|-----|------------|------|
| `headless-thumbnail` | 400×300 | yes |
| `headless-medium` | 800×600 | no |
| `headless-large` | 1200×900 | no |

---

## Next.js Checklist (easy to forget)

Things that live in the Next.js app, not in this theme.

### `next.config.js` — allow WordPress image domain

Without this, `next/image` throws a runtime error for any image hosted on WordPress.

```js
/** @type {import('next').NextConfig} */
const nextConfig = {
  images: {
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'your-wp-site.com',
      },
    ],
  },
};

module.exports = nextConfig;
```

### `app/api/disable-preview/route.ts` — exit Draft Mode

Once Draft Mode is enabled (via the WP Preview button) it sticks for the entire browser session. Add this route so editors can exit it.

```ts
import { NextResponse } from 'next/server';
import { draftMode } from 'next/headers';

export async function GET() {
  (await draftMode()).disable();
  return NextResponse.redirect('/');
}
```

Editors can visit `/api/disable-preview` manually, or you can add an exit banner to your layout when `draftMode().isEnabled` is true:

```tsx
// In your root layout or page component
import { draftMode } from 'next/headers';

export default async function Layout({ children }) {
  const { isEnabled } = await draftMode();
  return (
    <html>
      <body>
        {isEnabled && (
          <div style={{ background: '#f59e0b', padding: '8px', textAlign: 'center' }}>
            Draft Mode active —{' '}
            <a href="/api/disable-preview">Exit preview</a>
          </div>
        )}
        {children}
      </body>
    </html>
  );
}
```
