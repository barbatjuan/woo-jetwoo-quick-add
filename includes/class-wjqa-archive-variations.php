<?php
/**
 * Feature B — let variable products be configured and added from the listing.
 *
 * @package Woo_JetWooBuilder_Quick_Add
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chooses which variation picker the product cards get, and starts it.
 *
 * THE PROBLEM
 *
 * WooCommerce cannot add a variable product to the cart without knowing which
 * variation is wanted, so JetWooBuilder renders a plain link to the product page.
 * The customer has to leave the listing to buy.
 *
 * WHAT MAKES EITHER FIX SMALL
 *
 * `WC_AJAX::add_to_cart()` accepts a variation ID directly: it resolves the parent
 * product and the variation attributes on its own. The browser never has to send
 * attribute pairs — it only has to put the right ID on the button. Both pickers below
 * are just different ways of letting a customer choose that ID.
 *
 * WHY THERE ARE TWO
 *
 * Variation Swatches Pro renders real archive swatches, and where a site owns that
 * licence they are the better control: bigger targets, colour and image swatches, and
 * the same widget the customer already meets on the product page. JetWooBuilder and
 * Variation Swatches Pro even ship an integration with each other — it just never
 * fires, because JetWooBuilder does not run the hooks it depends on.
 *
 * But that licence is per-site and paid, the free edition has no archive swatches at
 * all, and most sites do not have it. Making the whole feature conditional on a paid
 * plugin means most sites get nothing.
 *
 * So this asks what the site actually has and picks accordingly:
 *
 *   Variation Swatches Pro present -> reconnect its archive swatches  (WJQA_Variations_Swatches)
 *   otherwise                      -> render a native dropdown        (WJQA_Variations_Select)
 *
 * Neither path needs the other, and a site that installs or drops Variation Swatches
 * Pro switches over on the next request with nothing to configure.
 */
class WJQA_Archive_Variations {

	/**
	 * Reconnect the archive swatches Variation Swatches Pro already knows how to draw.
	 */
	const PICKER_SWATCHES = 'swatches';

	/**
	 * Render a native dropdown of the buyable variations.
	 */
	const PICKER_SELECT = 'select';

	/**
	 * Register the feature.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		if ( self::PICKER_SWATCHES === self::picker() ) {
			WJQA_Variations_Swatches::init();
		} else {
			WJQA_Variations_Select::init();
		}

		add_filter( 'body_class', [ __CLASS__, 'body_class' ] );
	}

	/**
	 * Whether this feature applies to the current site.
	 *
	 * Note what is NOT checked here: Variation Swatches Pro. It decides which picker
	 * runs, never whether the feature runs at all.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled = WJQA_Support::has_jet_woo_builder()
			&& WJQA_Support::ajax_add_to_cart_enabled();

		/**
		 * Filters whether variable products get a variation picker in the listing.
		 *
		 * @param bool $enabled Whether the feature is active.
		 */
		return (bool) apply_filters( 'wjqa_enable_archive_variations', $enabled );
	}

	/**
	 * Which picker this site gets.
	 *
	 * @return string One of the PICKER_* constants.
	 */
	public static function picker() {
		$picker = WJQA_Support::has_variation_swatches_pro()
			? self::PICKER_SWATCHES
			: self::PICKER_SELECT;

		/**
		 * Filters which variation picker the product cards use.
		 *
		 * Detection is a default, not a verdict. A site that owns Variation Swatches
		 * Pro but wants the plainer dropdown in the listing — because its attributes
		 * are long phrases rather than colours, say — can force it:
		 *
		 *     add_filter( 'wjqa_variation_picker', fn() => 'select' );
		 *
		 * Forcing 'swatches' on a site without the Pro archive component is refused
		 * below, because there would be nothing to draw and the card would end up with
		 * no way to buy at all.
		 *
		 * @param string $picker Either 'swatches' or 'select'.
		 */
		$picker = apply_filters( 'wjqa_variation_picker', $picker );

		if ( self::PICKER_SWATCHES === $picker && WJQA_Support::has_variation_swatches_pro() ) {
			return self::PICKER_SWATCHES;
		}

		return self::PICKER_SELECT;
	}

	/**
	 * Say which picker is running, on the body element.
	 *
	 * Two jobs. The stylesheet uses it to keep each picker's rules off the other's
	 * cards, and it tells anyone debugging a card which path they are looking at
	 * without reading any PHP.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( ! WJQA_Support::is_product_listing() ) {
			return $classes;
		}

		$classes[] = 'wjqa-picker-' . self::picker();

		return $classes;
	}
}
