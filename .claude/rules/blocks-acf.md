---
paths:
  - "blocks/**"
  - "acf-json/**"
  - "includes/acf.php"
  - "includes/patterns.php"
---

# Blocks & ACF rules

- Blocks auto-register from `blocks/<name>/block.json` (includes/acf.php) — no manual registration.
- ACF blocks: `name: "acf/<name>"`, `apiVersion: 3`, `category: "theme"`,
  `"acf": { "version": 3, "mode": "preview", "renderTemplate": "blocks/<name>/render.php" }`.
- Native blocks (`headless/<name>`): `editor.js` + `editor.asset.php` (dependency manifest), no build step.
- `render.php` is the **editor preview / SSR fallback only** — the real UI is the React renderer in
  rvkweb `src/components/blocks/` registered in `BLOCK_MAP`. A new block without a frontend renderer
  falls back to raw `html`.
- Data reaches the frontend via `GET /headless/v1/posts/{id}/blocks` → `fields` (ACF) / `attrs`.
  Renaming a field = breaking change for rvkweb.
- `acf-json/*.json` is written by the ACF UI. Hand edits: keep `key`s stable, bump `modified`
  (unix ts) so ACF shows "sync available"; validate JSON after editing.
- Field group location for a block: `[[{"param":"block","operator":"==","value":"acf/<name>"}]]`.
- Use `/new-acf-block` for the full flow.
