---
name: new-endpoint
description: Add or change a headless/v1 REST route or a WPGraphQL field (register_graphql_field) safely, with revalidation and frontend contract notes.
argument-hint: "<rest|graphql> <what it returns>"
model: sonnet
effort: medium
---

Request: $ARGUMENTS

## REST (`includes/rest-<name>.php`)
```php
<?php
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function (): void {
    register_rest_route( 'headless/v1', '/<name>/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_<name>',
        'permission_callback' => '__return_true', // public data only
        'args'                => [ 'id' => [ 'required' => true, 'validate_callback' => fn( $v ) => is_numeric( $v ) ] ],
    ] );
} );
```
- Return `rest_ensure_response( $data )` / `WP_Error( 'not_found', ..., [ 'status' => 404 ] )`.
- Post-based: guard with `is_post_publicly_viewable()`. Bound all queries.
- Add `require_once` in `functions.php` (REST API section) + row in README endpoint table.

## GraphQL
- Inside `add_action( 'graphql_register_types', function (): void { ... } )`:
  `register_graphql_field( '<Type>', '<camelName>', [ 'type' => 'String', 'description' => '…', 'resolve' => fn( $source ) => … ] )`.
  Use `$source->databaseId` for post/term/media models. Follow `includes/photo-credit.php`.

## Always
1. Does content behind it change? Ensure `revalidation.php` fires for it and tell which Next tag must
   be invalidated (`POST_TYPE_TAGS` in rvkweb `src/lib/utils/revalidation.ts`).
2. `/verify <endpoint|field>` then `wp-probe` to show the real shape.
3. Summary includes the exact TS type for rvkweb `src/lib/types/` and which data fn will consume it.
