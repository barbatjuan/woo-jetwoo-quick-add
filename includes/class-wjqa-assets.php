<?php
/**
 * Front-end asset registration.
 *
 * @package Woo_JetWooBuilder_Quick_Add
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the stylesheet and script, and hands the script its feature flags.
 */
class WJQA_Assets {

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'wjqa-shop-cards';

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 20 );
	}

	/**
	 * Enqueue the assets on product listings.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$navigation_fix = WJQA_Card_Navigation::is_enabled();
		$variations     = WJQA_Archive_Variations::is_enabled();
		$mobile_cart    = WJQA_Mobile_Cart::is_enabled();

		if ( ! $navigation_fix && ! $variations && ! $mobile_cart ) {
			return;
		}

		if ( ! WJQA_Support::is_product_listing() ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			WJQA_URL . 'assets/css/shop-cards.css',
			[],
			WJQA_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			WJQA_URL . 'assets/js/shop-cards.js',
			[ 'jquery' ],
			WJQA_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'wjqaSettings',
			[
				'cardNavigationFix'   => $navigation_fix,
				'archiveVariations'   => $variations,
				'variationPicker'     => $variations ? WJQA_Archive_Variations::picker() : '',
				'mobileCartPlacement' => $mobile_cart,
				'mobileBreakpoint'    => WJQA_Mobile_Cart::breakpoint(),
				'addToCartText'       => __( 'Add to cart', 'woocommerce' ),
			]
		);

		if ( $variations && WJQA_Archive_Variations::PICKER_SWATCHES === WJQA_Archive_Variations::picker() ) {
			// Variation Swatches Pro only enqueues its script when it actually renders
			// a variable product. Load it on every listing so that a JetSmartFilters
			// AJAX render which pulls variable products into a grid that had none
			// still has the JS it needs.
			wp_enqueue_script( 'woo-variation-swatches-pro' );
		}
	}
}
