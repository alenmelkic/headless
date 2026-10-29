# Headless v3 — WordPress theme for radiovkladusa.ba

WordPress as a pure CMS/API backend (`admin.radiovkladusa.ba`). No frontend templates — content is
consumed by the Next.js app **rvkweb** (`C:/Users/Alen/source/repos/rvkweb`, readable from here)
via WPGraphQL + REST `/wp-json/headless/v1/*`. Hosting: CloudPanel VPS (not Vercel — README is outdated there).

Local WP: `http://localhost:10010` (Local by Flywheel, site "headless"). PHP 8+, ACF Pro 6.3+ (Blocks v3),
WPGraphQL, Composer (`enshrined/svg-sanitize`).

## Map

```
functions.php        bootstraps includes/ (add new require_once here, grouped by section)
includes/cpt.php …   core: CPTs, patterns, theme setup, editor caps, ACF
includes/rest-*.php  REST endpoints (headless/v1)
includes/revalidation.php  fire-and-forget webhook → Next /api/revalidate
includes/*-settings.php, marketing.php, photo-credit.php, term-color.php, author-image.php
                     admin UI + register_graphql_field()
blocks/<name>/       block.json + render.php (+ fields.php | editor.js + editor.asset.php)
acf-json/            ACF field groups / CPT / taxonomy JSON (auto-synced by ACF UI)
scripts/             one-off read-only reports (run via `wp eval-file` or php.exe)
```

README.md has the full endpoint/CPT tables — grep it instead of reading it whole.
Detailed rules live in `.claude/rules/` and load only when you touch matching files.

## Commands (Windows, Git Bash)

```bash
/c/xampp/php/php.exe -l <file.php>        # syntax check (php may not be on PATH)
curl -s http://localhost:10010/wp-json/headless/v1/<endpoint>
curl -s -X POST http://localhost:10010/graphql -H "Content-Type: application/json" -d '{"query":"..."}'
```
`python` is the WindowsApps stub — use `node -e` / `php -r` for scripting.
WP-CLI on the server: prefer `wp eval-file` over inline `wp eval` (shell escaping).

## Core rules (always)

- Every PHP file starts with `defined( 'ABSPATH' ) || exit;` (except `scripts/`).
- Prefix all functions/options/meta with `headless_` (meta for frontend: existing `rvk_` keys stay).
- Sanitize input, escape output, check capabilities. Public REST = read-only, public data only.
- REST/GraphQL shapes are a **contract with rvkweb** — before changing one, grep its consumer in
  `rvkweb/src/lib/rest/` or `rvkweb/src/lib/graphql/`.
- Content changes must trigger revalidation (`includes/revalidation.php`) or the site won't update.
- Admin UI copy in Bosnian (pure Bosnian forms, ijekavica); code/comments in English.
- Match the existing file's indentation (some files use tabs, some 4 spaces).
- Plugin deploys are file copies: `register_activation_hook` does not fire — use self-healing defaults.

## Model & token routing

Main session runs `opusplan` (Opus in plan mode, Sonnet when executing). Delegate:

| Need | Use | Model |
|---|---|---|
| Find where something lives | `explorer` agent | haiku |
| Check REST/GraphQL output | `wp-probe` agent | haiku |
| php -l / JSON / endpoint smoke test | `/verify` (php-verifier) | haiku |
| Root-cause a bug (no edits) | `/diagnose` (diagnoser) | opus |
| Security/WP-standards review of diff | `/review` (wp-reviewer) | sonnet |
| Commit message | `/commit` | haiku |

- Read with `offset/limit` (settings/marketing files are 400–600 lines); Grep first.
- Never read `vendor/`, `composer.phar`, `screenshot.png`.
- One task per session; suggest `/clear` when switching tasks.

## Workflow

- Briefs from Claude chat: `/brief <file>` — allowlist, VERIFY markers, stop gates.
- Diagnose before fix; one issue at a time; evidence first.
