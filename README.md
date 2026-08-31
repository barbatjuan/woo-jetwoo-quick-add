# Woo JetWooBuilder Quick Add

Lets customers add products to the cart straight from a JetWooBuilder product grid —
variable products included — instead of being sent to the single product page.

## Why this exists

**On a stock WooCommerce loop none of this is needed.** WooCommerce renders AJAX
add-to-cart buttons on its own, and Variation Swatches Pro hooks
`woocommerce_after_shop_loop_item` to print archive swatches.

JetWooBuilder replaces that loop with its own card markup and never fires those
hooks. This plugin is the glue that puts the behaviour back. On a site without
JetWooBuilder it deliberately does nothing.

## The two features

Each one is independent, has its own dependency check, and is a no-op when its
stack is absent.

### A. The card stops navigating away

**Needs:** JetWooBuilder.

With the products widget option *Clickable item* enabled, JetWooBuilder wraps the
card in `.jet-woo-item-overlay-wrap` and binds a delegated click handler on
`document` that ends in `window.location = permalink`. It excludes the compare,
wishlist and quick view buttons — but not the add-to-cart button.

The result: clicking *Add to cart* fires the WooCommerce AJAX request **and**
navigates to the product page. The item is genuinely added, but the customer is
thrown out of the listing and it looks broken.

WooCommerce calls `preventDefault()`, which stops the link's default action and
does nothing about propagation, so both handlers run.

**The fix.** The two listeners sit on different roots: WooCommerce on
`document.body`, JetWooBuilder on `document`, which is further out. A handler on
`document.body` that calls `stopPropagation()` keeps the event from ever reaching
`document`, while WooCommerce's own handler still runs — `stopPropagation()` does
not affect other handlers bound to the same element.

### B. Variable products get variants and a quantity in the card

**Needs:** JetWooBuilder **and** Variation Swatches **Pro** (see licensing below).

JetWooBuilder and Variation Swatches Pro already ship an integration with each
other. Variation Swatches Pro fires `show_variation`; JetWooBuilder's
`handleVariationSwatchesAddToCart` rewrites the card button into a native
WooCommerce AJAX add-to-cart pointing at the chosen variation ID, and
`WC_AJAX::add_to_cart()` resolves the parent product and attributes from that ID.

Four links are missing, and this plugin supplies them:

| # | Missing | Why | Fixed by |
|---|---------|-----|----------|
| 1 | Swatches are never printed | JetWooBuilder never fires `woocommerce_after_shop_loop_item` | `woocommerce_loop_add_to_cart_link` at priority 20 |
| 2 | Button lacks `wvs-add-to-cart-button` | JetWooBuilder calls `wc_get_template( 'loop/add-to-cart.php' )` directly, skipping `woocommerce_loop_add_to_cart_args` | `jet-woo-builder/template-functions/product-add-to-cart-settings` |
| 3 | No quantity field on variable products | `qty_for_woocommerce_loop_add_to_cart_link()` only handles `simple` and `variation` | Same filter as #1 |
| 4 | Swatches never initialise | Variation Swatches Pro's IntersectionObserver does not fire inside JetWooBuilder cards | `$( el ).WooVariationSwatchesPro()`, the plugin's own public API |

No template overrides are used — every hook above is a documented extension point.

## Requirements

- WordPress 6.0+, PHP 7.4+
- WooCommerce 8.0+ with **Enable AJAX add to cart buttons on archives** turned on
- JetWooBuilder
- Feature B only: **Variation Swatches for WooCommerce — Pro**

> **Licensing.** Variation Swatches Pro is licensed per site and the free edition
> has no archive swatches at all. Feature B simply will not run without it. If you
> sell this as a service, the licence is part of the deal or the feature is not.

## Install

```bash
git clone https://github.com/barbatjuan/woo-jetwoo-quick-add.git
```

Drop the folder in `wp-content/plugins/` and activate. No settings screen: the
plugin decides what to run from what is installed.

## Per-site configuration

The plugin ships the code. These are settings, and they belong to each site.

**WooCommerce → Settings → Products**
- *Enable AJAX add to cart buttons on archives*: **on** (required)
- *Redirect to the cart page after successful addition*: **off**

**WooCommerce → Variation Swatches → Archive** (feature B)

| Setting | Value | Why |
|---|---|---|
| Show on archive | on | Prints the swatches at all |
| Product wrapper selector | `.jet-woo-products__item, .wvs-archive-product-wrapper` | The script runs `.closest()` with this; JetWooBuilder cards do not carry the default class |
| Default selected | **off** (recommended) | With it on, a distracted customer buys a variant they never picked |
| AJAX variation threshold | ≥ your largest variation count | At `0` every card fetches its variations over AJAX; above the threshold they are embedded in the HTML |
| Alignment | center | Matches the centred card layout |

**Watch out — the attribute type is resolved product-first.** A product can carry
its own `_woo_variation_swatches_product_settings` override, including
`default_to_button`, and it beats the global setting. If a global change appears to
do nothing, check the product.

## Customising

CSS custom properties, set them anywhere after the stylesheet:

```css
:root {
	--wjqa-qty-width: 64px;     /* quantity field width */
	--wjqa-control-gap: 8px;    /* gap between quantity and button */
	--wjqa-swatch-gap: 10px;    /* gap under the swatches */
	--wjqa-touch-target: 48px;  /* swatch size on touch pointers */
}
```

Filters:

| Filter | Default | Purpose |
|---|---|---|
| `wjqa_enable_card_navigation_fix` | JetWooBuilder present | Turn feature A off |
| `wjqa_enable_archive_variations` | full stack present | Turn feature B off |
| `wjqa_show_variable_quantity` | `true` | Hide the quantity field on variable cards |
| `wjqa_is_product_listing` | shop, product taxonomies, search, front page, single product | Where assets load |

## Verifying an install

In a private window, as a guest:

1. Shop, simple product → URL does not change, cart counter rises.
2. Card image or title → still opens the product page.
3. Variable card → swatches visible, none preselected.
4. *Add to cart* without choosing → goes to the product page. Correct: WooCommerce
   cannot add a variable product without a variant.
5. Pick a variant → label becomes *Add to cart*, and `data-product_id` on the
   button is the **variation** ID, not the parent's.
6. Set a quantity, add → URL does not change, counter rises by that quantity.
7. Cart page → correct variant and quantity on the line.
8. Apply a JetSmartFilters filter, repeat 5–6 → still works after the AJAX render.
9. Phone width → swatches at least 44px tall, no horizontal overflow.
10. Console → no new errors.

Useful probe after picking a variant:

```js
document.querySelector( '.wvs-add-to-cart-button' ).dataset
```

## Upstream workarounds

Both features compensate for third-party behaviour rather than extending it. They
are all guarded, so if upstream changes they become dead weight rather than
breakage — but they should be revisited.

Verified against:

| Plugin | Version |
|---|---|
| WooCommerce | 11.0.1 |
| JetWooBuilder | 2.3.4 |
| Variation Swatches Pro | 2.3.0 |

The most fragile piece is the class names the CSS and JS key on
(`.jet-woo-item-overlay-wrap`, `.jet-woo-product-button`, `.wvs-add-to-cart-button`).
If JetWooBuilder renames them, the symptoms return; nothing else breaks.

## License

GPL-2.0-or-later.
