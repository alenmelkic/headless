---
name: new-acf-block
description: Scaffold a new ACF (acf/*) or native (headless/*) block in the theme — block.json, render.php, field group JSON or editor.js — and list the matching rvkweb frontend work.
argument-hint: "<block-name> [acf|native] [fields: name:type, ...]"
model: sonnet
effort: medium
---

Block: $ARGUMENTS

1. **Template** — copy patterns from the closest existing block (use `explorer`):
   ACF → `blocks/youtube-video/` + `acf-json/group_block_youtube_video.json`;
   native → `blocks/soundcloud/` or `blocks/video-popup/` (editor.js + editor.asset.php).
2. **block.json** — `$schema`, `apiVersion: 3`, `name` (`acf/<name>` | `headless/<name>`), `title`,
   `category: "theme"`, `icon`, `keywords`, `supports` (anchor, html:false, align as needed).
   ACF: `"acf": { "version": 3, "mode": "preview", "renderTemplate": "blocks/<name>/render.php" }`.
3. **Fields** — `acf-json/group_block_<name>.json`: unique `group_`/`field_` keys, location
   `block == acf/<name>`, `modified` = current unix ts, `show_in_rest: 1` if the group exposes it.
4. **render.php** — editor preview only: `get_field()`, validate (URL host allowlists), escape all
   output, graceful empty state in `$is_preview`.
5. Auto-registered — no require needed. Run `/verify`.
6. **Hand-off** — print the exact frontend tasks for rvkweb:
   - type in `src/lib/types/blocks.ts` (field names/types as returned by `/headless/v1/posts/{id}/blocks`)
   - component `src/components/blocks/<Pascal>Block.tsx` + `BLOCK_MAP` entry `'<block name>'`
   - CSP update in `next.config.ts` if it embeds a third party
   (or run `/new-block` from the rvkweb session, which covers both sides).
