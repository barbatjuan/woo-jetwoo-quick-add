<?php
/**
 * Feature A — stop the product card navigating away when the cart control is used.
 *
 * @package Woo_JetWooBuilder_Quick_Add
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps clicks on the add-to-cart control from reaching JetWooBuilder's
 * "Clickable item" handler.
 *
 * THE PROBLEM
 *
 * With the JetWooBuilder products widget option "Clickable item" enabled, the whole
 * card is wrapped in `.jet-woo-item-overlay-wrap` carrying a `data-url`, and the
 * plugin registers a delegated handler on `document`:
 *
 *   $( document ).on( 'click.JetWooBuilder', '.jet-woo-item-overlay-wrap',
 *                     JetWooBuilder.handleListingItemClick )
 *
 * That handler ends in `window.location = url`, and it only excludes the compare,
 * wishlist and quick view buttons. A click on the add-to-cart button therefore
 * fires the WooCommerce AJAX request AND navigates to the product page. The item
 * really is added, but the customer is thrown out of the listing.
 *
 * WooCommerce calls `preventDefault()`, which stops the link's default action but
 * does nothing about propagation, so both handlers run.
 *
 * THE FIX
 *
 * The two listeners sit on different roots: WooCommerce delegates on
 * `document.body`, JetWooBuilder on `document`, which is further out. Stopping
 * propagation from a handler on `document.body` keeps the event from ever reaching
 * `document`, while WooCommerce's own handler still runs — `stopPropagation()`
 * does not affect other handlers bound to the same element.
 *
 * Implemented in assets/js/shop-cards.js.
 */
class WJQA_Card_Navigation {

	/**
	 * Register the feature.
	 *
	 * @return void
	 */
	public static function init() {
		// Nothing to hook in PHP: the fix is purely a click handler, enqueued by
		// WJQA_Assets. This class exists to hold the capability check and the
		// explanation, so that both live in one place.
	}

	/**
	 * Whether this fix applies to the current site.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled = WJQA_Support::has_jet_woo_builder();

		/**
		 * Filters whether the card navigation fix is active.
		 *
		 * Turn this off when a site genuinely wants the whole card, cart button
		 * included, to open the product page.
		 *
		 * @param bool $enabled Whether to stop the card from navigating.
		 */
		return (bool) apply_filters( 'wjqa_enable_card_navigation_fix', $enabled );
	}
}
