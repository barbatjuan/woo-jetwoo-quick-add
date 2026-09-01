<?php
/**
 * Feature C — make the cart controls reachable on a touch screen.
 *
 * @package Woo_JetWooBuilder_Quick_Add
 */

defined( 'ABSPATH' ) || exit;

/**
 * Moves the card's cart controls out of the hover overlay on small screens.
 *
 * THE PROBLEM
 *
 * Most JetWooBuilder products presets — every one except the first — put the
 * add-to-cart control inside `.hovered-content`, which is `visibility: hidden` until
 * the card is hovered. A touch screen has no hover, so on a phone the control is
 * simply unreachable.
 *
 * A preset that already shows its button in the normal flow has nothing to fix, and
 * the script requires the control to be inside a `.hovered-content` before touching
 * it. Moving a button that was never hidden would be an unasked-for redesign.
 *
 * JetWooBuilder's own answer is the widget's "Hover on touch" option, which reveals
 * the overlay on the first tap. That spends the tap the customer meant for the card
 * and leaves the controls floating over the product image, so sites routinely give up
 * and hide the overlay on mobile with a stylesheet rule — which removes the last way
 * to buy from the listing on the device most customers are using.
 *
 * THE FIX
 *
 * On small screens, move `.jet-woo-product-button` out of the overlay and append it
 * to the card's inner box, after the title and price, where it is a normal block in
 * the flow. Nothing is cloned and nothing is restyled: the same node is re-parented,
 * so it keeps every rule that applies to it.
 *
 * That placement also sidesteps any "hide the overlay on mobile" rule without having
 * to fight it, because the moved control is no longer inside the overlay that rule
 * targets. The overlay is left where it is, empty and still hidden — the site's own
 * styling decision is preserved rather than overridden.
 *
 * The control stays inside `.jet-woo-item-overlay-wrap`, so the click handling in
 * WJQA_Card_Navigation keeps it from triggering JetWooBuilder's card navigation.
 *
 * Implemented in assets/js/shop-cards.js — the card markup is built by JetWooBuilder
 * and there is no server-side filter that exposes it.
 */
class WJQA_Mobile_Cart {

	/**
	 * Default breakpoint, in pixels, below which the controls are moved.
	 *
	 * Matches the breakpoint Elementor and most themes treat as "mobile".
	 */
	const DEFAULT_BREAKPOINT = 767;

	/**
	 * Register the feature.
	 *
	 * @return void
	 */
	public static function init() {
		// Nothing to hook in PHP: the move happens in the browser, and the script is
		// enqueued by WJQA_Assets. This class holds the capability check and the
		// explanation so that both live in one place.
	}

	/**
	 * Whether this fix applies to the current site.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled = WJQA_Support::has_jet_woo_builder();

		/**
		 * Filters whether the cart controls move out of the hover overlay on mobile.
		 *
		 * Turn this off when a site genuinely wants the overlay behaviour on touch,
		 * for instance because it uses a preset that shows the controls anyway.
		 *
		 * @param bool $enabled Whether to move the controls.
		 */
		return (bool) apply_filters( 'wjqa_enable_mobile_cart_placement', $enabled );
	}

	/**
	 * Screen width, in pixels, at or below which the controls are moved.
	 *
	 * @return int
	 */
	public static function breakpoint() {
		/**
		 * Filters the mobile breakpoint used to place the cart controls.
		 *
		 * Worth aligning with whatever breakpoint the theme's own mobile rules use.
		 *
		 * @param int $breakpoint Width in pixels.
		 */
		return (int) apply_filters( 'wjqa_mobile_breakpoint', self::DEFAULT_BREAKPOINT );
	}
}
