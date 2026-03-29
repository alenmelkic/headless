# CPT Taxonomies + GraphQL Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add shared `kategorija` (hierarchical) and `oznaka` (flat) taxonomies to both CPTs, exposed via REST API and WPGraphQL.

**Architecture:** Single function `headless_register_taxonomies()` added to `includes/cpt.php`, called on the same `init` hook as `headless_register_cpts()`. Both CPT registrations updated to declare the taxonomies.

**Tech Stack:** PHP 8+, WordPress `register_taxonomy()`, WPGraphQL plugin (`show_in_graphql`)

---

## File Map

| File | Action |
|------|--------|
| `includes/cpt.php` | Modify — update header comment, add taxonomies to both CPT registrations, add `headless_register_taxonomies()` function |

---

### Task 1: Update CPT declarations to include taxonomies

**Files:**
- Modify: `includes/cpt.php:68` (obavijest `taxonomies` key)
- Modify: `includes/cpt.php:118` (servisna_info `taxonomies` key)

- [ ] **Step 1: Update `obavijest` taxonomies key**

In `includes/cpt.php`, find line 68:
```php
        // No taxonomies — supports only core post fields
        'taxonomies'         => [],
```
Replace with:
```php
        'taxonomies'         => [ 'kategorija', 'oznaka' ],
```

- [ ] **Step 2: Update `servisna_info` taxonomies key**

In `includes/cpt.php`, find line 118:
```php
        // No taxonomies — supports only core post fields
        'taxonomies'         => [],
```
Replace with:
```php
        'taxonomies'         => [ 'kategorija', 'oznaka' ],
```

- [ ] **Step 3: Update header comment**

Replace the comment line:
```php
 *  - No taxonomies      (no categories or tags attached)
```
With:
```php
 *  - Taxonomies         (kategorija / hierarchical, oznaka / flat — shared across CPTs)
```

- [ ] **Step 4: Commit**

```bash
git add includes/cpt.php
git commit -m "feat: attach kategorija and oznaka taxonomies to CPTs"
```

---

### Task 2: Register the taxonomies

**Files:**
- Modify: `includes/cpt.php` — add `headless_register_taxonomies()` and call it on `init`

- [ ] **Step 1: Add the taxonomy registration function**

Append the following to the end of `includes/cpt.php` (after the closing `}` of `headless_register_cpts()`):

```php
add_action( 'init', function (): void {
    headless_register_taxonomies();
} );

/**
 * Registers shared taxonomies for all CPTs.
 *
 * kategorija — hierarchical (category-style)
 *   REST:    GET /wp-json/wp/v2/kategorije
 *   GraphQL: query { categories { nodes { ... } } }
 *
 * oznaka — flat (tag-style)
 *   REST:    GET /wp-json/wp/v2/oznake
 *   GraphQL: query { tags { nodes { ... } } }
 */
function headless_register_taxonomies(): void {

    // -----------------------------------------------------------------------
    // Kategorija — hierarchical, shared across all CPTs
    // -----------------------------------------------------------------------
    register_taxonomy( 'kategorija', [ 'obavijest', 'servisna_info' ], [
        'labels' => [
            'name'              => 'Kategorije',
            'singular_name'     => 'Kategorija',
            'search_items'      => 'Pretraži kategorije',
            'all_items'         => 'Sve kategorije',
            'parent_item'       => 'Nadređena kategorija',
            'parent_item_colon' => 'Nadređena kategorija:',
            'edit_item'         => 'Uredi kategoriju',
            'update_item'       => 'Ažuriraj kategoriju',
            'add_new_item'      => 'Dodaj novu kategoriju',
            'new_item_name'     => 'Naziv nove kategorije',
            'menu_name'         => 'Kategorije',
        ],
        'hierarchical'       => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_nav_menus'  => false,
        'show_in_rest'       => true,
        'rest_base'          => 'kategorije',
        'show_in_graphql'    => true,
        'graphql_single_name' => 'category',
        'graphql_plural_name' => 'categories',
        'rewrite'            => [ 'slug' => 'kategorije', 'with_front' => false ],
        'show_admin_column'  => true,
    ] );

    // -----------------------------------------------------------------------
    // Oznaka — flat (tag-style), shared across all CPTs
    // -----------------------------------------------------------------------
    register_taxonomy( 'oznaka', [ 'obavijest', 'servisna_info' ], [
        'labels' => [
            'name'                       => 'Oznake',
            'singular_name'              => 'Oznaka',
            'search_items'               => 'Pretraži oznake',
            'popular_items'              => 'Popularne oznake',
            'all_items'                  => 'Sve oznake',
            'edit_item'                  => 'Uredi oznaku',
            'update_item'                => 'Ažuriraj oznaku',
            'add_new_item'               => 'Dodaj novu oznaku',
            'new_item_name'              => 'Naziv nove oznake',
            'separate_items_with_commas' => 'Odvojite oznake zarezima',
            'add_or_remove_items'        => 'Dodaj ili ukloni oznake',
            'choose_from_most_used'      => 'Odaberi iz najkorištenijih',
            'menu_name'                  => 'Oznake',
        ],
        'hierarchical'       => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_nav_menus'  => false,
        'show_in_rest'       => true,
        'rest_base'          => 'oznake',
        'show_in_graphql'    => true,
        'graphql_single_name' => 'tag',
        'graphql_plural_name' => 'tags',
        'rewrite'            => [ 'slug' => 'oznake', 'with_front' => false ],
        'show_admin_column'  => true,
    ] );
}
```

- [ ] **Step 2: Verify WordPress loads without errors**

In WP Admin, navigate to the Obavijesti post list — confirm "Kategorije" and "Oznake" columns appear. Navigate to **Obavijesti → Kategorije** and **Obavijesti → Oznake** in the sidebar — both menus should be present.

- [ ] **Step 3: Verify REST API**

Open in browser or curl:
```
GET /wp-json/wp/v2/kategorije
GET /wp-json/wp/v2/oznake
```
Expected: JSON array response (empty `[]` if no terms yet — that is correct).

- [ ] **Step 4: Verify GraphQL**

In WP Admin → GraphQL → GraphiQL IDE (or via frontend), run:
```graphql
query {
  obavijesti {
    nodes {
      title
      categories { nodes { name slug } }
      tags { nodes { name slug } }
    }
  }
}
```
Expected: valid response with `categories` and `tags` as empty arrays (no terms yet).

Also confirm taxonomy terms appear in the GraphQL schema:
```graphql
query {
  categories { nodes { name slug } }
  tags { nodes { name slug } }
}
```

- [ ] **Step 5: Commit**

```bash
git add includes/cpt.php
git commit -m "feat: register kategorija and oznaka taxonomies with REST and GraphQL support"
```
