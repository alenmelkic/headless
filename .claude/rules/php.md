---
paths:
  - "**/*.php"
---

# PHP / WordPress rules

- `defined( 'ABSPATH' ) || exit;` at the top of every include/block file.
- Typed signatures (`function headless_x( int $id ): array`), PHP 8 features OK (`fn`, `match`, `str_starts_with`).
- Prefix: `headless_` for functions, options, hooks, transients. Wrap hooks in closures like existing code.
- Input: `sanitize_text_field`, `absint`, `esc_url_raw`, `wp_kses_post`; validate URLs by scheme + host
  allowlist (see `blocks/youtube-video/render.php`).
- Output: `esc_html`, `esc_attr`, `esc_url`, `wp_json_encode`.
- Admin forms/actions: nonce (`wp_nonce_field` / `check_admin_referer`) + `current_user_can()`.
- Meta: `register_post_meta(..., 'show_in_rest' => true, 'sanitize_callback', 'auth_callback')`.
- Outgoing HTTP: `wp_remote_*` with timeout; webhooks non-blocking.
- No `error_log` spam left in code; no `var_dump`.
- After editing: `/c/xampp/php/php.exe -l <file>` (the PostToolUse hook does this automatically).
- New include → add `require_once` in `functions.php` in the right section with a one-line comment.
