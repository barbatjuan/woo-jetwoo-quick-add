/**
 * Woo JetWooBuilder Quick Add — front-end behaviour.
 *
 * Two independent features, each gated by its own flag from wjqaSettings:
 *
 *   1. cardNavigationFix — keep a click on the cart controls from triggering
 *      JetWooBuilder's "Clickable item" navigation.
 *   2. archiveVariations — turn the card's "Select options" link into a real AJAX
 *      add-to-cart once the customer picks a variation.
 *
 * Every handler is delegated on `document.body`, so cards that arrive later — a
 * JetSmartFilters AJAX render, for instance — work with no re-initialisation.
 */
( function ( $, settings ) {
	'use strict';

	if ( ! $ || ! settings ) {
		return;
	}

	/**
	 * Feature 1 — stop the card from navigating.
	 *
	 * WooCommerce delegates its add-to-cart handler on `document.body`.
	 * JetWooBuilder delegates its card navigation on `document`, which is further
	 * out. Stopping propagation here keeps the event from reaching `document`, while
	 * WooCommerce's handler still runs because `stopPropagation()` does not affect
	 * other handlers bound to the same element.
	 *
	 * The variation picker needs the same protection for a different reason: without
	 * it, the click that opens the dropdown navigates to the product page instead.
	 *
	 * The default action is deliberately left alone, so a variable product with no
	 * variation chosen still follows its link to the product page, which is what
	 * WooCommerce requires.
	 */
	function initCardNavigationFix() {
		$( document.body ).on(
			'click',
			'.jet-woo-item-overlay-wrap .jet-woo-product-button, .jet-woo-item-overlay-wrap .wjqa-variations',
			function ( event ) {
				event.stopPropagation();
			}
		);
	}

	/**
	 * Feature 2 — archive variations.
	 */
	function initArchiveVariations() {
		function buttonFor( $select ) {
			return $select.closest( '.jet-woo-product-button' ).find( '.wjqa-add-to-cart' );
		}

		function priceFor( $select ) {
			return $select.closest( '.jet-woo-products__item' ).find( '.jet-woo-product-price' ).first();
		}

		function setLabel( $button, text ) {
			var $label = $button.find( '.jet-woo-product-button__label' );

			if ( $label.length ) {
				$label.text( text );
			} else {
				$button.text( text );
			}
		}

		/**
		 * Record the card's untouched state the first time it is altered, so that
		 * clearing the dropdown can put every one of those things back.
		 */
		function remember( $button, $price ) {
			if ( undefined === $button.data( 'wjqaDefaults' ) ) {
				$button.data( 'wjqaDefaults', {
					id: $button.attr( 'data-product_id' ),
					quantity: $button.attr( 'data-quantity' ),
					href: $button.attr( 'href' ),
					label: $button.text().trim(),
					ajax: $button.hasClass( 'ajax_add_to_cart' )
				} );
			}

			if ( $price.length && undefined === $price.data( 'wjqaDefaultPrice' ) ) {
				$price.data( 'wjqaDefaultPrice', $price.html() );
			}
		}

		/**
		 * Point the card's button at the chosen variation.
		 *
		 * `WC_AJAX::add_to_cart()` resolves the parent product and the variation
		 * attributes from the variation ID alone, so this is all it takes.
		 */
		function apply( $select ) {
			var $button = buttonFor( $select ),
				$price = priceFor( $select ),
				$option = $select.find( 'option:selected' ),
				id = parseInt( $select.val(), 10 ),
				price;

			if ( ! $button.length ) {
				return;
			}

			remember( $button, $price );

			if ( ! id ) {
				restore( $select );
				return;
			}

			// wc-add-to-cart.js reads the button through jQuery's `.data()`, so both
			// the attribute and the data cache have to be written or the click adds
			// the parent product — or nothing at all.
			$button.attr( 'data-product_id', id ).data( 'product_id', id );
			$button.attr( 'data-quantity', 1 ).data( 'quantity', 1 );
			$button.attr( 'href', '?add-to-cart=' + id );

			// WooCommerce only intercepts the click when this class is present; until
			// now the button was a plain link to the product page.
			$button.addClass( 'ajax_add_to_cart' );

			// Until this runs the label still reads "Select options", which hides the
			// fact that the button now adds the chosen variation to the cart.
			setLabel( $button, settings.addToCartText );

			price = $option.attr( 'data-wjqa-price' );

			if ( $price.length && price ) {
				$price.html( price );
			}
		}

		/**
		 * Put the card back exactly as it was rendered.
		 */
		function restore( $select ) {
			var $button = buttonFor( $select ),
				$price = priceFor( $select ),
				defaults = $button.data( 'wjqaDefaults' );

			if ( ! $button.length || ! defaults ) {
				return;
			}

			$button.attr( 'data-product_id', defaults.id ).data( 'product_id', defaults.id );
			$button.attr( 'href', defaults.href );

			if ( undefined === defaults.quantity ) {
				$button.removeAttr( 'data-quantity' ).removeData( 'quantity' );
			} else {
				$button.attr( 'data-quantity', defaults.quantity ).data( 'quantity', defaults.quantity );
			}

			if ( ! defaults.ajax ) {
				$button.removeClass( 'ajax_add_to_cart' );
			}

			setLabel( $button, defaults.label );

			if ( $price.length && undefined !== $price.data( 'wjqaDefaultPrice' ) ) {
				$price.html( $price.data( 'wjqaDefaultPrice' ) );
			}
		}

		$( document.body ).on( 'change', '.wjqa-variation-select', function () {
			apply( $( this ) );
		} );

		// Leave the card the way it was found once the item is in the cart, so the
		// next customer action starts from a clean, unambiguous state.
		$( document.body ).on( 'added_to_cart', function ( event, fragments, cartHash, $button ) {
			var $select;

			if ( ! $button || ! $button.length ) {
				return;
			}

			$select = $button.closest( '.jet-woo-product-button' ).find( '.wjqa-variation-select' );

			if ( ! $select.length ) {
				return;
			}

			$select.val( '' );
			restore( $select );
		} );
	}

	if ( settings.cardNavigationFix ) {
		initCardNavigationFix();
	}

	if ( settings.archiveVariations ) {
		initArchiveVariations();
	}
} )( window.jQuery, window.wjqaSettings );
