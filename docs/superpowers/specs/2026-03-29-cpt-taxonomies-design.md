# CPT Taxonomies + GraphQL — Design Spec

**Date:** 2026-03-29
**Status:** Approved

## Summary

Add two shared taxonomies — `kategorija` (hierarchical) and `oznaka` (flat) — to both existing CPTs (`obavijest`, `servisna_info`), exposed via REST API and WPGraphQL.

## Scope

- File changed: `includes/cpt.php` only
- No new files

## Taxonomies

### kategorija (Category-style)

| Property | Value |
|----------|-------|
| Slug | `kategorija` |
| Hierarchical | `true` |
| REST base | `kategorije` |
| GraphQL single | `category` |
| GraphQL plural | `categories` |
| Attached to | `obavijest`, `servisna_info` |

### oznaka (Tag-style)

| Property | Value |
|----------|-------|
| Slug | `oznaka` |
| Hierarchical | `false` |
| REST base | `oznake` |
| GraphQL single | `tag` |
| GraphQL plural | `tags` |
| Attached to | `obavijest`, `servisna_info` |

## Changes to cpt.php

1. **New function** `headless_register_taxonomies()` registered on `init`, called after `headless_register_cpts()`
2. **Both CPT registrations** updated: `'taxonomies' => ['kategorija', 'oznaka']`
3. **Header comment** updated to reflect taxonomies

## REST API

```
GET /wp-json/wp/v2/kategorije
GET /wp-json/wp/v2/oznake
```

Both taxonomies are also embedded on CPT REST responses when `_embed` is used.

## GraphQL

```graphql
query {
  obavijesti {
    nodes {
      categories { nodes { name slug } }
      tags { nodes { name slug } }
    }
  }
}

query {
  servisneInfo {
    nodes {
      categories { nodes { name slug } }
      tags { nodes { name slug } }
    }
  }
}
```

## Out of Scope

- No ACF fields on taxonomies
- No custom taxonomy term meta
- No changes to REST endpoints in `headless/v1` namespace
