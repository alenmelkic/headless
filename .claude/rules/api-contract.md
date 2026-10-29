---
paths:
  - "includes/rest-*.php"
  - "includes/revalidation.php"
  - "includes/cors.php"
  - "includes/*settings*.php"
  - "includes/marketing.php"
  - "includes/photo-credit.php"
  - "includes/term-color.php"
  - "includes/author-image.php"
  - "includes/theme.php"
  - "includes/cpt.php"
  - "includes/cron-cleanup.php"
---

# API contract with rvkweb (Next.js)

- REST: `register_rest_route( 'headless/v1', ... )` with typed `args` + `validate_callback`.
  Public routes use `'permission_callback' => '__return_true'` ONLY for public data; check
  `is_post_publicly_viewable()` for post-based routes. Return `WP_Error` with a `status`.
- GraphQL: register inside `add_action( 'graphql_register_types', ... )` via
  `register_graphql_field( 'Type', 'camelName', [ 'type' => ..., 'resolve' => ... ] )`.
  Return types honestly (`Int` not numeric string) — the frontend uses them as-is.
- Consumers: rvkweb `src/lib/rest/*.ts`, `src/lib/graphql/queries/*.ts`, types in
  `src/lib/types/*.ts`. Grep there before changing a shape; list affected frontend files in your summary.
- Revalidation: `headless_send_revalidation( [ 'type' => post|menu|options|term|attachment, ... ] )`
  → Next `/api/revalidate` maps `post_type` → tags via `POST_TYPE_TAGS`. A new CPT/option needs both
  a WP trigger here and a tag mapping there.
- Cache-Control/CORS headers live in `cors.php` — Cloudflare sits in front; don't make private data cacheable.
- Preview tokens are HMAC-verified (`rest-preview.php`) — compare with `hash_equals`.
- Secrets via `headless_get_setting()`; never expose them in REST/GraphQL output.
