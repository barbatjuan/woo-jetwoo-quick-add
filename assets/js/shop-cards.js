/**
 * Woo JetWooBuilder Quick Add — front-end behaviour.
 *
 * Two independent features, each gated by its own flag from wjqaSettings:
 *
 *   1. cardNavigationFix — keep a click on the cart control from triggering
 *      JetWooBuilder's "Clickable item" navigation.
 *   2. archiveVariations — initialise the archive swatches, keep the quantity
 *      wired to the button, and relabel the button once a variant is chosen.
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
	 * out. Stopping propagation here keeps the event from reaching `document`,
	 * while WooCommerce's handler still runs because `stopPropagation()` does not
	 * affect other handlers on the same element.
	 *
	 * The default action is deliberately left alone, so a variable product with no
	 * variant chosen still follows its link to the product page, which is what
	 * WooCommerce requires.
	 */
	function initCardNavigationFix() {
		$( document.body ).on(
			'click',
			'.jet-woo-item-overlay-wrap .jet-woo-product-button',
			function ( event ) {
				event.stopPropagation();
			}
		);
	}

	/**
	 * Feature 2 — archive variations.
	 */
	function initArchiveVariations() {
		/**
		 * Variation Swatches Pro initialises archive swatches lazily through an
		 * IntersectionObserver, and that observer never fires for swatches rendered
		 * inside JetWooBuilder cards. Without initialisation the button is never
		 * rewired to the chosen variation, so clicking it just follows its href.
		 *
		 * Calling the plugin's own public jQuery plugin is the supported way in.
		 * The `:not(.wvs-pro-loaded)` guard is the same one the plugin uses, so if
		 * it ever starts initialising these itself nothing is done twice.
		 */
		function initSwatches() {
			if ( ! $.fn.WooVariationSwatchesPro ) {
				return;
			}

			$( '.jet-woo-product-button .wvs-archive-variations-wrapper' )
				.not( '.wvs-pro-loaded' )
				.each( function () {
					try {
						$( this ).WooVariationSwatchesPro();
					} catch ( error ) {
						if ( window.console ) {
							window.console.log( 'Woo JetWooBuilder Quick Add:', error );
						}
					}
				} );
		}

		function cartButtonFor( element ) {
			return $( element ).closest( '.jet-woo-product-button' ).find( '.wvs-add-to-cart-button' );
		}

		/**
		 * wc-add-to-cart.js reads the button through jQuery's `.data()`, so both the
		 * attribute and the data cache have to be written or the quantity is ignored.
		 */
		function syncQuantity( input ) {
			var $button = cartButtonFor( input ),
				value = parseInt( input.value, 10 );

			if ( ! $button.length ) {
				return;
			}

			if ( isNaN( value ) || value < 1 ) {
				value = 1;
			}

			$button.attr( 'data-quantity', value ).data( 'quantity', value );
		}

		function setButtonLabel( $button, text ) {
			var $label = $button.find( '.jet-woo-product-button__label' );

			if ( $label.length ) {
				$label.text( text );
			} else {
				$button.text( text );
			}
		}

		$( initSwatches );
		$( window ).on( 'load', initSwatches );

		// JetSmartFilters swaps the whole grid out, so fresh cards need initialising.
		$( document ).on( 'jet-filter-content-rendered', function () {
			window.setTimeout( initSwatches, 0 );
		} );

		$( document.body ).on( 'change input', '.jet-woo-product-button .quantity input.qty', function () {
			syncQuantity( this );
		} );

		$( document.body ).on( 'show_variation', '.wvs-archive-variations-wrapper', function ( event, variation ) {
			var $wrapper = $( this ).closest( '.jet-woo-product-button' ),
				$button = $wrapper.find( '.wvs-add-to-cart-button' ),
				$input = $wrapper.find( 'input.qty' );

			if ( ! variation ) {
				return;
			}

			if ( $input.length ) {
				if ( variation.max_qty ) {
					$input.attr( 'max', variation.max_qty );
				} else {
					$input.removeAttr( 'max' );
				}

				$input.attr( 'min', variation.min_qty || 1 );
			}

			// JetWooBuilder rewires the button on a zero timeout, so run after it.
			window.setTimeout( function () {
				if ( ! $button.length ) {
					return;
				}

				if ( ! $button.data( 'wjqaDefaultLabel' ) ) {
					$button.data( 'wjqaDefaultLabel', $button.text().trim() );
				}

				// Until this runs the label still reads "Select options", which hides
				// the fact that the button now adds the chosen variation to the cart.
				setButtonLabel( $button, settings.addToCartText );

				if ( $input.length ) {
					syncQuantity( $input.get( 0 ) );
				}
			}, 10 );
		} );

		$( document.body ).on( 'reset_data', '.wvs-archive-variations-wrapper', function () {
			var $button = $( this ).closest( '.jet-woo-product-button' ).find( '.wvs-add-to-cart-button' ),
				defaultLabel = $button.data( 'wjqaDefaultLabel' );

			if ( $button.length && defaultLabel ) {
				setButtonLabel( $button, defaultLabel );
			}
		} );
	}

	if ( settings.cardNavigationFix ) {
		initCardNavigationFix();
	}

	if ( settings.archiveVariations ) {
		initArchiveVariations();
	}
} )( window.jQuery, window.wjqaSettings );
