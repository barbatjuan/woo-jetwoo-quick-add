<?php
/**
 * Feature B — let variable products be configured and added from the listing.
 *
 * @package Woo_JetWooBuilder_Quick_Add
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bridges Variation Swatches Pro archive swatches into JetWooBuilder product cards.
 *
 * WHAT ALREADY WORKS WITHOUT THIS PLUGIN
 *
 * JetWooBuilder and Variation Swatches Pro ship an integration with each other.
 * Variation Swatches Pro fires `show_variation`; JetWooBuilder's
 * `handleVariationSwatchesAddToCart` (assets/js/frontend.js) listens for it,
 * finds `.wvs-add-to-cart-button` in the card, and rewrites it into a native
 * WooCommerce AJAX add-to-cart pointing at the chosen variation ID. WooCommerce's
 * `WC_AJAX::add_to_cart()` accepts a variation ID and resolves the parent product
 * and its attributes on its own.
 *
 * WHAT IS MISSING, AND WHY
 *
 * 1. Swatches are never printed. Variation Swatches Pro hooks
 *    `woocommerce_after_shop_loop_item`; JetWooBuilder renders its own card and
 *    never fires it.
 *
 * 2. The button never gets the `wvs-add-to-cart-button` class the integration
 *    looks for. Variation Swatches Pro adds it through
 *    `woocommerce_loop_add_to_cart_args`, but JetWooBuilder assembles its own args
 *    and calls `wc_get_template( 'loop/add-to-cart.php' )` directly instead of
 *    `woocommerce_template_loop_add_to_cart()`, which is where WooCommerce applies
 *    that filter.
 *
 * 3. Variable products get no quantity field.
 *    `Jet_Woo_Builder_Template_Functions::qty_for_woocommerce_loop_add_to_cart_link()`
 *    only builds one for `simple` and `variation` products.
 *
 * 4. The swatches are never initialised. Variation Swatches Pro boots them lazily
 *    through an IntersectionObserver that does not fire for swatches inside
 *    JetWooBuilder cards, so the button is never rewired and a click just follows
 *    its href to the product page. Handled in assets/js/shop-cards.js by calling
 *    the plugin's own public jQuery plugin.
 *
 * Points 1 to 3 are fixed here, through documented extension points — no template
 * overrides are needed.
 */
class WJQA_Archive_Variations {

	/**
	 * Class name of the Variation Swatches Pro archive component.
	 */
	const WVS_ARCHIVE_CLASS = 'Woo_Variation_Swatches_Pro_Archive_Page';

	/**
	 * Register the feature.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		add_filter( 'jet-woo-builder/template-functions/product-add-to-cart-settings', [ __CLASS__, 'add_swatch_button_classes' ], 10, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_link', [ __CLASS__, 'render_swatches_and_quantity' ], 20, 2 );
	}

	/**
	 * Whether this feature applies to the current site.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled = WJQA_Support::has_jet_woo_builder()
			&& WJQA_Support::has_variation_swatches_pro()
			&& WJQA_Support::ajax_add_to_cart_enabled();

		/**
		 * Filters whether variable products get swatches and a quantity in the listing.
		 *
		 * @param bool $enabled Whether the feature is active.
		 */
		return (bool) apply_filters( 'wjqa_enable_archive_variations', $enabled );
	}

	/**
	 * Add the classes Variation Swatches Pro would normally add itself.
	 *
	 * `wvs-add-to-cart-button` is the hook JetWooBuilder's JS looks for;
	 * `wvs_ajax_add_to_cart` marks the button as not yet resolved to a variation,
	 * and JetWooBuilder swaps it for `ajax_add_to_cart` once one is chosen.
	 *
	 * @param array      $args    Add-to-cart template args assembled by JetWooBuilder.
	 * @param WC_Product $product Product being rendered.
	 * @return array
	 */
	public static function add_swatch_button_classes( $args, $product ) {
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return $args;
		}

		$classes   = array_filter( explode( ' ', isset( $args['class'] ) ? $args['class'] : '' ) );
		$classes[] = 'wvs-add-to-cart-button';
		$classes[] = 'wvs_ajax_add_to_cart';

		$args['class'] = implode( ' ', array_unique( $classes ) );

		return $args;
	}

	/**
	 * Prepend the swatches and a quantity field to a variable product's cart control.
	 *
	 * Priority 20 matters: JetWooBuilder's own quantity filter runs at 10 and
	 * replaces the markup wholesale, so anything added earlier is discarded.
	 *
	 * The quantity and the button are wrapped together so they lay out as one row
	 * beneath the swatches, mirroring the `form.cart` JetWooBuilder builds for
	 * simple products.
	 *
	 * @param string     $html    Add-to-cart markup.
	 * @param WC_Product $product Product being rendered.
	 * @return string
	 */
	public static function render_swatches_and_quantity( $html, $product ) {
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return $html;
		}

		if ( ! class_exists( self::WVS_ARCHIVE_CLASS ) ) {
			return $html;
		}

		ob_start();
		call_user_func( [ self::WVS_ARCHIVE_CLASS, 'instance' ] )->display_swatches( $product );
		$swatches = ob_get_clean();

		if ( '' === trim( (string) $swatches ) ) {
			// Variation Swatches Pro declined to render, for instance because the
			// product's category is excluded in its settings. Leave the card alone.
			return $html;
		}

		return $swatches . '<div class="wjqa-variation-cart-row">' . self::quantity_field( $product ) . $html . '</div>';
	}

	/**
	 * Build the quantity field for a variable product.
	 *
	 * The meaningful bounds belong to the chosen variation, which is only known in
	 * the browser. The script narrows them on `show_variation`, and WooCommerce
	 * validates server side regardless.
	 *
	 * @param WC_Product $product Product being rendered.
	 * @return string
	 */
	private static function quantity_field( $product ) {
		/**
		 * Filters whether variable product cards show a quantity field.
		 *
		 * @param bool       $show    Whether to render the field.
		 * @param WC_Product $product Product being rendered.
		 */
		if ( ! apply_filters( 'wjqa_show_variable_quantity', true, $product ) ) {
			return '';
		}

		return woocommerce_quantity_input(
			[
				'min_value'   => 1,
				'max_value'   => -1,
				'input_value' => 1,
			],
			$product,
			false
		);
	}
}
