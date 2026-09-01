# Woo JetWooBuilder Quick Add

Lets customers add products to the cart straight from a JetWooBuilder product grid —
variable products included — instead of being sent to the single product page.

No paid dependency, but it uses one if the site has it. Version 1.x required Variation
Swatches Pro; 2.x replaced it with a native dropdown; 3.0 detects which of the two the
site can do and runs that.

## Why this exists

**On a stock WooCommerce loop, half of this is not needed.** WooCommerce renders AJAX
add-to-cart buttons on its own.

JetWooBuilder replaces that loop with its own card markup and its own click handling,
which breaks them. This plugin is the glue that puts the behaviour back — and adds the
one thing WooCommerce has never done in a listing: letting a variable product be
chosen without opening its page.

On a site without JetWooBuilder it deliberately does nothing.

## The three features

Each one is independent, has its own dependency check, and is a no-op when its stack
is absent.

### A. The card stops navigating away

**Needs:** JetWooBuilder.

With the products widget option *Clickable item* enabled, JetWooBuilder wraps the card
in `.jet-woo-item-overlay-wrap` and binds a delegated click handler on `document` that
ends in `window.location = permalink`. It excludes the compare, wishlist and quick view
buttons — but not the add-to-cart button, and not the variation picker.

The result: clicking *Add to cart* fires the WooCommerce AJAX request **and** navigates
to the product page. The item is genuinely added, but the customer is thrown out of the
listing and it looks broken.

WooCommerce calls `preventDefault()`, which stops the link's default action and does
nothing about propagation, so both handlers run.

**The fix.** The two listeners sit on different roots: WooCommerce on `document.body`,
JetWooBuilder on `document`, which is further out. A handler on `document.body` that
calls `stopPropagation()` keeps the event from ever reaching `document`, while
WooCommerce's own handler still runs — `stopPropagation()` does not affect other
handlers bound to the same element.

### B. Variable products can be chosen from the card

**Needs:** JetWooBuilder. Variation Swatches **Pro** is optional and changes which
control you get.

WooCommerce cannot add a variable product to the cart without knowing which variation
is wanted, so JetWooBuilder renders a plain link to the product page.

**What makes either fix small.** `WC_AJAX::add_to_cart()` accepts a variation ID
directly: it resolves the parent product and the variation attributes on its own. The
browser never has to send attribute pairs — it only has to put the right ID on the
button. Both pickers below are just different ways of letting a customer choose it.

#### Two pickers, chosen from what the site has

| Site has | Picker | Class |
|---|---|---|
| Variation Swatches **Pro** | its archive swatches, reconnected | `WJQA_Variations_Swatches` |
| anything else | a native `<select>` of buyable variations | `WJQA_Variations_Select` |

Where a site owns the Pro licence the swatches are the better control: bigger targets,
colour and image swatches, and the same widget the customer already meets on the
product page. But that licence is per-site and paid, the free edition has no archive
swatches at all, and most sites do not have it — so making the whole feature
conditional on it means most sites get nothing.

Detection is a default, not a verdict: `wjqa_variation_picker` forces either one.
Forcing `swatches` without the Pro archive component is refused, because there would
be nothing to draw and the card would end up with no way to buy at all.

The running picker is announced as `wjqa-picker-swatches` or `wjqa-picker-select` on
the `<body>`, which is both how the stylesheet keeps each picker's rules off the
other's cards and how you tell which path a card is on without reading any PHP.

#### The swatches picker

JetWooBuilder and Variation Swatches Pro already ship an integration with each other.
Variation Swatches Pro fires `show_variation`; JetWooBuilder's
`handleVariationSwatchesAddToCart` rewrites the card button into a native WooCommerce
AJAX add-to-cart pointing at the chosen variation. Four links are missing:

| # | Missing | Why | Fixed by |
|---|---------|-----|----------|
| 1 | Swatches are never printed | JetWooBuilder never fires `woocommerce_after_shop_loop_item` | `woocommerce_loop_add_to_cart_link` at priority 20 |
| 2 | Button lacks `wvs-add-to-cart-button` | JetWooBuilder calls `wc_get_template( 'loop/add-to-cart.php' )` directly, skipping `woocommerce_loop_add_to_cart_args` | `jet-woo-builder/template-functions/product-add-to-cart-settings` |
| 3 | No quantity field on variable products | `qty_for_woocommerce_loop_add_to_cart_link()` only handles `simple` and `variation` | Same filter as #1 |
| 4 | Swatches never initialise | Variation Swatches Pro's IntersectionObserver does not fire inside JetWooBuilder cards | `$( el ).WooVariationSwatchesPro()`, the plugin's own public API |

#### The dropdown picker

| Step | Where |
|---|---|
| Print a `<select>` of buyable variations before the button | `woocommerce_loop_add_to_cart_link` at priority 20 |
| Tag the button so the script can find it | `jet-woo-builder/template-functions/product-add-to-cart-settings` |
| Point the button at the chosen variation and relabel it | `assets/js/shop-cards.js` |
| Swap the card's price range for the chosen variation's price | same |

Priority 20 matters for both: JetWooBuilder's own quantity filter runs at 10 and
replaces the markup wholesale.

No template overrides are used — every hook above is a documented extension point.

##### Scope, deliberately

The dropdown handles variable products with a **single variation attribute**. Matching
a combination of attributes to a variation needs a resolution matrix plus handling for
partially-chosen and "any" combinations, and getting that subtly wrong on a live shop
means selling the wrong thing. The swatches picker has no such limit, because the Pro
archive component resolves combinations itself.

A multi-attribute product keeps the stock behaviour: the card links to the product
page, where WooCommerce does it properly. The same fallback applies when a product has
no buyable variations left after filtering.

**Variations that are not purchasable are left out of the list.** A variation with no
price set is the common case — it is in stock and published, so it looks fine in the
admin, but `is_purchasable()` is false and adding it fails silently. Offering it would
hand the customer a button that does nothing.

### C. The cart controls are reachable on a phone

**Needs:** JetWooBuilder.

Several products presets put the add-to-cart control inside `.hovered-content`, which
is `visibility: hidden` until the card is hovered. A touch screen has no hover, so on
a phone the control is simply unreachable.

JetWooBuilder's own answer is the widget's *Hover on touch* option, which reveals the
overlay on the first tap. That spends the tap the customer meant for the card and
leaves the controls floating over the product image, so sites routinely give up and
hide the overlay on mobile with a stylesheet rule — which removes the last way to buy
from the listing on the device most customers are using.

**The fix.** Below the breakpoint, move `.jet-woo-product-button` out of the overlay
and append it to the card's inner box, after the title and price, where it is a normal
block in the flow. Nothing is cloned and nothing is restyled: the same node is
re-parented, so it keeps every rule that applies to it.

That placement also sidesteps any "hide the overlay on mobile" rule without having to
fight it, because the moved control is no longer inside the overlay that rule targets.
The overlay is left where it is, empty and still hidden — the site's own styling
decision is preserved rather than overridden.

The control stays inside `.jet-woo-item-overlay-wrap`, so feature A keeps protecting
it from the card navigation in its new home.

## Requirements

- WordPress 6.0+, PHP 7.4+
- WooCommerce 8.0+ with **Enable AJAX add to cart buttons on archives** turned on
- JetWooBuilder
- Optionally **Variation Swatches for WooCommerce — Pro**, which upgrades feature B's
  dropdown to real archive swatches. Nothing breaks without it.

## Install

```bash
git clone https://github.com/barbatjuan/woo-jetwoo-quick-add.git
```

Drop the folder in `wp-content/plugins/` and activate. No settings screen: the plugin
decides what to run from what is installed.

## Per-site configuration

The plugin ships the code. These are settings, and they belong to each site.

**WooCommerce → Settings → Products**
- *Enable AJAX add to cart buttons on archives*: **on** (required — feature B checks it)
- *Redirect to the cart page after successful addition*: **off**

Give the customer somewhere to see the result: an Elementor Menu Cart, a side cart, or
any widget that refreshes on WooCommerce's cart fragments. Without one, a successful
add is invisible.

## Customising

The stylesheet sets layout and nothing else, with one unavoidable exception: the
button once feature C has moved it. Everywhere else there are no colours, no borders
and no typography, and the dropdown keeps its native browser appearance so it inherits
whatever the site already looks like.

**Give the moved button an appearance. The defaults are grey on purpose.**

Card buttons in a hover overlay are routinely given a transparent background, because
they sit on the product photo and the photo is the background. Moved onto the card's
own background, a transparent button stops reading as a button and looks like loose
text — so feature C has to give it an appearance, and only the site knows the right
one. A site that styles its card button usually scopes that styling to the widths
where the button was visible, which is exactly the range feature C changes, so that
rule will not follow the button on its own.

**Preferred: extend the site's own rule, scoped to `.wjqa-inline-cart`.** If the site
already styles this button somewhere, add a copy of that rule scoped to the moved
control and leave the plugin alone. One palette, one owner, nothing to keep in sync. A
host rule carrying `!important` overrides the plugin without a fight.

Scope it to the class rather than to a width. A width-based rule keeps applying when
the plugin is switched off, which changes the site at breakpoints where the button was
never moved; a class-based one simply stops matching, and the site returns to exactly
what it was. Put the palette in a custom property so the two rules share it:

```css
body.woocommerce-shop{ --shop-btn-bg:#4b151d; --shop-btn-fg:#e7b9c3; }

/* wherever the button already lived */
@media (min-width:1025px){
  .jet-woo-product-button .add_to_cart_button{ background:var(--shop-btn-bg)!important; … }
}
/* and where this plugin moves it */
.jet-woo-product-button.wjqa-inline-cart .add_to_cart_button{ background:var(--shop-btn-bg)!important; … }
```

Leave `width` out of the moved rule: that is placement, and the plugin owns it.

### Styling hooks

These class names are part of the plugin's contract with a site's stylesheet and will
not change without a major version:

| Class | On | Meaning |
|---|---|---|
| `.wjqa-inline-cart` | the cart control | feature C has moved it out of the hover overlay |
| `.wjqa-add-to-cart` | the card's button | this is a variable product's cart button |
| `.wjqa-variations` / `.wjqa-variation-select` | the dropdown | the native picker |
| `body.wjqa-picker-swatches` / `body.wjqa-picker-select` | `<body>` | which picker is running |

Only when there is no such rule to widen, set these:

```css
:root {
	--wjqa-button-bg: #222;      /* achromatic placeholder — replace it */
	--wjqa-button-color: #fff;
	--wjqa-button-border: 1px solid #222;
	--wjqa-button-radius: 0;
	--wjqa-button-padding: 13px 20px;
	--wjqa-button-width: 100%;
	--wjqa-button-font: 400 14px/1 Inter, sans-serif;
	--wjqa-button-letter-spacing: 0.01em;
}
```

The defaults are grey rather than a real palette on purpose. Shipping one shop's brand
colours as a plugin default is how a plugin quietly paints somebody else's site the
wrong colour, and how one palette ends up defined in two places that drift apart.

Layout properties, same place:

```css
:root {
	--wjqa-control-gap: 6px;       /* gap between two controls sharing a row */
	--wjqa-select-gap: 6px;        /* gap under the dropdown */
	--wjqa-select-height: auto;    /* dropdown height; 40px on touch pointers */
	--wjqa-swatch-gap: 8px;        /* gap under the swatches */
	--wjqa-qty-width: 54px;        /* quantity field width */
	--wjqa-touch-target: 44px;     /* swatch size on touch pointers */
	--wjqa-inline-cart-gap: 8px;   /* gap above the controls once moved on mobile */
	--wjqa-inline-cart-order: 9;   /* flex order of the moved controls in the card */
}
```

Filters:

| Filter | Default | Purpose |
|---|---|---|
| `wjqa_enable_card_navigation_fix` | JetWooBuilder present | Turn feature A off |
| `wjqa_enable_archive_variations` | JetWooBuilder + AJAX add to cart | Turn feature B off |
| `wjqa_variation_picker` | `swatches` if Variation Swatches Pro is present, else `select` | Force one picker |
| `wjqa_enable_mobile_cart_placement` | JetWooBuilder present | Turn feature C off |
| `wjqa_mobile_breakpoint` | `767` | Width at or below which feature C moves the controls |
| `wjqa_show_variable_quantity` | `true` on swatches, `false` on the dropdown | Quantity field beside the button |
| `wjqa_is_product_listing` | shop, product taxonomies, search, front page, single product | Where assets load |

Align `wjqa_mobile_breakpoint` with whatever breakpoint the theme's own mobile rules
use, so the two never disagree about what "mobile" means.

A site that owns Variation Swatches Pro but whose attributes are long phrases rather
than colours may well prefer the plainer control:

```php
add_filter( 'wjqa_variation_picker', fn() => 'select' );
```

`wjqa_show_variable_quantity` differs by picker on purpose. Swatches occupy their own
row and leave the button room beside a quantity field; a dropdown and a button already
stack into two rows in a narrow card, and JetWooBuilder is usually not showing a
quantity on simple products either, so switching it on for only half the grid looks
like a bug.

## Verifying an install

In a private window, as a guest:

1. Shop, simple product → URL does not change, cart counter rises.
2. Card image or title → still opens the product page.
3. Variable card → a dropdown appears, nothing preselected.
4. *Add to cart* without choosing → goes to the product page. Correct: WooCommerce
   cannot add a variable product without a variation.
5. Pick a variation → the label becomes *Add to cart*, the card price narrows from a
   range to that variation's price, and `data-product_id` on the button is the
   **variation** ID, not the parent's.
6. Add → URL does not change, counter rises, and the dropdown resets itself.
7. Cart page → correct variation on the line.
8. A variation with no price is absent from the dropdown.
9. Apply a JetSmartFilters filter, repeat 5–6 → still works after the AJAX render.
10. Phone width → the controls sit below the price rather than in the overlay, the
    dropdown is at least 40px tall, and there is no horizontal overflow.
11. Phone width → tapping the image or title still opens the product page, and
    tapping the dropdown or the button does not.
12. Resize across the breakpoint without reloading → the controls move back into the
    overlay above it and back below the price under it.
13. Console → no new errors.

Useful probe after picking a variation:

```js
document.querySelector( '.wjqa-add-to-cart' ).dataset
```

## Upstream workarounds

Feature A compensates for third-party behaviour rather than extending it. It is
guarded, so if upstream changes it becomes dead weight rather than breakage — but it
should be revisited.

Verified against:

| Plugin | Version |
|---|---|
| WooCommerce | 11.0.1 |
| JetWooBuilder | 2.3.4 |

The most fragile piece is the class names the CSS and JS key on
(`.jet-woo-item-overlay-wrap`, `.jet-woo-product-button`, `.jet-woo-products__item`).
If JetWooBuilder renames them, the symptoms return; nothing else breaks.

## License

GPL-2.0-or-later.
