<?php
/**
 * Dependency and context checks shared by every feature.
 *
 * @package Woo_JetWooBuilder_Quick_Add
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capability checks.
 *
 * Every feature in this plugin compensates for something a specific third-party
 * plugin does. Asking these questions before hooking anything is what makes the
 * plugin a no-op on a site that does not have that plugin, instead of a source
 * of mysterious side effects.
 */
class WJQA_Support {

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function has_woocommerce() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Whether JetWooBuilder is active.
	 *
	 * Everything this plugin does exists because JetWooBuilder replaces the
	 * WooCommerce product loop with its own markup. Without it there is nothing
	 * to fix.
	 *
	 * @return bool
	 */
	public static function has_jet_woo_builder() {
		return function_exists( 'jet_woo_builder' );
	}

	/**
	 * Whether WooCommerce is set to add products to the cart over AJAX.
	 *
	 * With this off, WooCommerce renders plain links that reload the page and the
	 * whole point of the plugin disappears.
	 *
	 * @return bool
	 */
	public static function ajax_add_to_cart_enabled() {
		return 'yes' === get_option( 'woocommerce_enable_ajax_add_to_cart' );
	}

	/**
	 * Whether the current request renders a product listing worth touching.
	 *
	 * Defaults to the shop, product taxonomies, search, the front page and single
	 * product pages, the last one because related and upsell grids live there.
	 *
	 * @return bool
	 */
	public static function is_product_listing() {
		if ( is_admin() || ! function_exists( 'is_shop' ) ) {
			return false;
		}

		$is_listing = is_shop()
			|| is_product_taxonomy()
			|| is_product()
			|| is_search()
			|| is_front_page();

		/**
		 * Filters whether the current request counts as a product listing.
		 *
		 * Useful when a theme renders a JetWooBuilder grid somewhere unusual, such
		 * as a landing page built with a shortcode.
		 *
		 * @param bool $is_listing Whether assets and hooks should apply here.
		 */
		return (bool) apply_filters( 'wjqa_is_product_listing', $is_listing );
	}
}
