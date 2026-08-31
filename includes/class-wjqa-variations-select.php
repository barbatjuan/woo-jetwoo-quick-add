<?php
/**
 * Feature B, dropdown path — render a native list of the buyable variations.
 *
 * @package Woo_JetWooBuilder_Quick_Add
 */

defined( 'ABSPATH' ) || exit;

/**
 * Puts a native variation dropdown inside JetWooBuilder product cards.
 *
 * Selected by WJQA_Archive_Variations when the site has no Variation Swatches Pro,
 * which is most sites. It needs nothing beyond WooCommerce and JetWooBuilder.
 *
 * WHAT MAKES IT SMALL
 *
 * `WC_AJAX::add_to_cart()` accepts a variation ID directly: it resolves the parent
 * product and the variation attributes on its own. The browser never has to send
 * attribute pairs — it only has to put the right ID on the button. That turns the
 * whole picker into "render a list of variation IDs and let the customer pick one",
 * which is why this needs no swatch library behind it.
 *
 * SCOPE, DELIBERATELY
 *
 * Only variable products with a SINGLE variation attribute are handled. Matching a
 * combination of attributes to a variation needs a resolution matrix plus handling
 * for partially-chosen and "any" combinations, and getting that subtly wrong on a
 * live shop means selling the wrong thing. A multi-attribute product keeps the stock
 * behaviour: the card links to the product page, where WooCommerce does it properly.
 *
 * A site that owns Variation Swatches Pro gets WJQA_Variations_Swatches instead,
 * which has no such limit because the Pro archive component resolves combinations
 * itself.
 *
 * Everything here runs through documented extension points. No template overrides.
 */
class WJQA_Variations_Select {

	/**
	 * Register the picker.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'jet-woo-builder/template-functions/product-add-to-cart-settings', [ __CLASS__, 'mark_variable_button' ], 10, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_link', [ __CLASS__, 'render_variation_picker' ], 20, 2 );
	}

	/**
	 * Tag a variable product's cart control so the script can find it.
	 *
	 * JetWooBuilder assembles its own template args and calls
	 * `wc_get_template( 'loop/add-to-cart.php' )` directly, so the classes WooCommerce
	 * would normally apply through `woocommerce_loop_add_to_cart_args` never arrive.
	 * This filter is JetWooBuilder's own equivalent of that one.
	 *
	 * @param array      $args    Add-to-cart template args assembled by JetWooBuilder.
	 * @param WC_Product $product Product being rendered.
	 * @return array
	 */
	public static function mark_variable_button( $args, $product ) {
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return $args;
		}

		$classes   = array_filter( explode( ' ', isset( $args['class'] ) ? $args['class'] : '' ) );
		$classes[] = 'wjqa-add-to-cart';

		$args['class'] = implode( ' ', array_unique( $classes ) );

		return $args;
	}

	/**
	 * Prepend a variation picker to a variable product's cart control.
	 *
	 * Priority 20 matters: JetWooBuilder's own quantity filter runs at 10 and replaces
	 * the markup wholesale, so anything added earlier would be discarded.
	 *
	 * Every early return leaves `$html` exactly as JetWooBuilder built it, so an
	 * unsupported product falls back to the stock behaviour rather than breaking.
	 *
	 * @param string     $html    Add-to-cart markup.
	 * @param WC_Product $product Product being rendered.
	 * @return string
	 */
	public static function render_variation_picker( $html, $product ) {
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return $html;
		}

		$attributes = $product->get_variation_attributes();

		// See the class docblock: single-attribute products only.
		if ( 1 !== count( $attributes ) ) {
			return $html;
		}

		$choices = self::get_choices( $product );

		if ( empty( $choices ) ) {
			return $html;
		}

		$select_id = 'wjqa-variation-' . $product->get_id();
		$label     = wc_attribute_label( (string) key( $attributes ), $product );

		// WooCommerce's own placeholder, so it arrives already translated and matches
		// the wording of the dropdown on the single product page.
		$options = '<option value="">' . esc_html__( 'Choose an option', 'woocommerce' ) . '</option>';

		foreach ( $choices as $choice ) {
			$options .= sprintf(
				'<option value="%1$s" data-wjqa-price="%2$s">%3$s</option>',
				esc_attr( $choice['id'] ),
				esc_attr( $choice['price'] ),
				esc_html( $choice['label'] )
			);
		}

		$picker = sprintf(
			'<div class="wjqa-variations"><label class="screen-reader-text" for="%1$s">%2$s</label><select class="wjqa-variation-select" id="%1$s">%3$s</select></div>',
			esc_attr( $select_id ),
			esc_html( $label ),
			$options
		);

		$quantity = self::quantity_field( $product );

		if ( '' === $quantity ) {
			return $picker . $html;
		}

		return $picker . '<div class="wjqa-variation-cart-row">' . $quantity . $html . '</div>';
	}

	/**
	 * Build the list of variations a customer may actually buy.
	 *
	 * Building this for every variable product in the catalogue at once measured at
	 * 24ms, so there is no cache. A cache here would buy nothing and add a way for a
	 * price to go stale on a live shop.
	 *
	 * @param WC_Product $product Parent variable product.
	 * @return array List of [ id, label, price ] rows.
	 */
	private static function get_choices( $product ) {
		$choices = [];

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}

			// A variation with no price is not purchasable. Offering it would hand the
			// customer a button that fails silently, so it is left out of the list.
			if ( ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
				continue;
			}

			// An "any" variation leaves its attribute empty, which is a range rather
			// than a choice and cannot be labelled. array_filter drops those.
			$values = array_filter( $variation->get_variation_attributes() );

			if ( 1 !== count( $values ) ) {
				continue;
			}

			$choices[] = [
				'id'    => $variation_id,
				'label' => self::choice_label( (string) key( $values ), (string) reset( $values ) ),
				// Wrapped the way JetWooBuilder wraps the card price, so swapping one
				// for the other in the browser cannot disturb the card's styling.
				'price' => '<span class="price">' . $variation->get_price_html() . '</span>',
			];
		}

		return $choices;
	}

	/**
	 * Human-readable label for one variation attribute value.
	 *
	 * A custom product attribute stores its value verbatim and is already readable.
	 * A global attribute stores a term slug, which is not.
	 *
	 * @param string $key   Variation meta key, for example `attribute_pa_size`.
	 * @param string $value Stored value.
	 * @return string
	 */
	private static function choice_label( $key, $value ) {
		$taxonomy = str_replace( 'attribute_', '', $key );

		if ( taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', $value, $taxonomy );

			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
		}

		return $value;
	}

	/**
	 * Build the quantity field for a variable product.
	 *
	 * Off by default here, unlike the swatches path. A dropdown and a button already
	 * stack into two rows inside a narrow card, a third control crowds them, and
	 * JetWooBuilder is usually not showing a quantity on simple products either — so
	 * switching it on for only the variable half of the grid looks like a bug.
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
		if ( ! apply_filters( 'wjqa_show_variable_quantity', false, $product ) ) {
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
