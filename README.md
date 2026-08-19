# Conditional Product Recommendations for WooCommerce

Displays conditional WooCommerce product recommendations on product pages, Classic Cart, and Classic Checkout.

## Installation

1. Copy this plugin folder to `wp-content/plugins/conditional-product-recommendations`.
2. Make sure WooCommerce is installed and active.
3. Activate **Conditional Product Recommendations for WooCommerce** in WordPress Admin.
4. Go to **WooCommerce > Product Recommendations**.

## Private GitHub Updates

This plugin bundles Plugin Update Checker and can update from a private GitHub repository.

Recommended flow:

```text
VSCode
Git commit + push
GitHub private repository
Tag / Release v1.0.3
WordPress sites see "Version 1.0.3 is available"
Update now
```

Configure each WordPress site in `wp-config.php`:

```php
define( 'CRW_GITHUB_REPOSITORY_URL', 'https://github.com/your-org/conditional-product-recommendations/' );
define( 'CRW_GITHUB_ACCESS_TOKEN', 'github_pat_xxx' );
```

Use a fine-grained GitHub token with read-only access to the private repository contents. Do not commit the token into the plugin repository.

Optional, if you want updates to use a stable branch instead of releases/tags:

```php
define( 'CRW_GITHUB_BRANCH', 'main' );
```

For each release:

1. Update the plugin header `Version`, the `CRW_VERSION` constant, and `readme.txt` stable tag.
2. Commit and push.
3. Create a Git tag like `v1.0.3`.
4. Create a GitHub Release from that tag.
5. WordPress sites with the token configured will detect the update.

## Create Your First Rule

1. Click **Add New**.
2. Enter a rule name, for example `Recommend BAC`.
3. Search and select one or more **Products to Display**.
4. Choose one or more locations: Product Page, Cart, Checkout.
5. Save the rule.

Products already in the cart are hidden automatically. If all selected display products are already in the cart, the whole recommendation section is hidden.

## Class Overview

- `CRW_Plugin`: bootstraps the plugin, checks WooCommerce, registers the CPT, and wires services.
- `CRW_Admin`: renders the WooCommerce admin submenu, rule list, and rule form.
- `CRW_Rule_Repository`: stores rules in the `crw_recommendation` CPT and post meta.
- `CRW_Cart_Condition_Service`: centralizes simple product, variable product, and variation cart detection.
- `CRW_Rule_Evaluator`: evaluates enabled/location/condition checks and filters invalid display products.
- `CRW_Frontend`: registers frontend hooks and uses one renderer for product, cart, and checkout locations.
- `CRW_Ajax`: securely adds recommended products to cart and returns mini-cart fragments.

## Test Cases

1. Display products are `A` and `B`, cart is empty: both products appear.
2. Display products are `A` and `B`, cart contains `A`: only `B` appears.
3. Cart contains all display products: the recommendation section is hidden.
4. Two rules return the same product at one location: the product appears once.
5. Click the `+` button: product is added by AJAX, mini-cart fragments update, and that card disappears.
6. Disable a rule: it no longer renders anywhere.
7. Select a variation as a display product: the AJAX add uses the parent product plus variation ID.

## Classic vs Blocks

This version supports WooCommerce Product Page hooks, Classic Cart, and Classic Checkout. Cart Block and Checkout Block support should be added as a separate integration layer, for example `class-blocks.php` or `class-store-api.php`, while reusing the existing repository, cart service, and evaluator.
