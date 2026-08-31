/**
 * Woo JetWooBuilder Quick Add — front-end behaviour.
 *
 * Two independent features, each gated by its own flag from wjqaSettings:
 *
 *   1. cardNavigationFix — keep a click on the cart controls from triggering
 *      JetWooBuilder's "Clickable item" navigation.
 *   2. archiveVariations — turn the card's "Select options" link into a real AJAX
 *      add-to-cart once the customer picks a variation.
 *   3. mobileCartPlacement — move the cart controls out of the hover overlay on
 *      small screens, where there is no hover to reveal them with.
 *
 * Handlers are delegated on `document.body`, so cards that arrive later — a
 * JetSmartFilters AJAX render, for instance — work with no re-initialisation.
 * Feature 3 is the exception: re-parenting a node is not something a delegated
 * handler can do for elements that do not exist yet, so it listens for the filter.
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

	/**
	 * Feature 3 — reachable cart controls on a touch screen.
	 *
	 * The control is moved, never cloned or rebuilt, so it keeps every style rule
	 * that applies to it and every piece of state the other features wrote onto it.
	 *
	 * It also stays inside `.jet-woo-item-overlay-wrap`, which is what keeps feature
	 * 1 protecting it from JetWooBuilder's card navigation in its new home.
	 */
	function initMobileCartPlacement() {
		var query = window.matchMedia( '(max-width: ' + parseInt( settings.mobileBreakpoint, 10 ) + 'px)' );

		function place() {
			$( '.jet-woo-products__item' ).each( function () {
				var $item = $( this ),
					$inner = $item.children( '.jet-woo-products__inner-box' ).first(),
					$controls = $item.find( '.jet-woo-product-button' ).first(),
					$home;

				if ( ! $inner.length || ! $controls.length ) {
					return;
				}

				if ( query.matches ) {
					if ( $controls.parent().is( $inner ) ) {
						return;
					}

					// Remember the overlay so a resize back to a wide screen can put
					// the card back exactly as JetWooBuilder rendered it.
					$controls.data( 'wjqaOverlay', $controls.parent() );
					$inner.append( $controls.addClass( 'wjqa-inline-cart' ) );

					return;
				}

				$home = $controls.data( 'wjqaOverlay' );

				if ( ! $home || ! $home.length ) {
					return;
				}

				$home.append( $controls.removeClass( 'wjqa-inline-cart' ) );
				$controls.removeData( 'wjqaOverlay' );
			} );
		}

		$( place );

		// Rotating a tablet crosses the breakpoint without reloading the page.
		if ( query.addEventListener ) {
			query.addEventListener( 'change', place );
		} else if ( query.addListener ) {
			query.addListener( place );
		}

		// JetSmartFilters swaps the whole grid out, so fresh cards need placing.
		$( document ).on( 'jet-filter-content-rendered', function () {
			window.setTimeout( place, 0 );
		} );
	}

	if ( settings.cardNavigationFix ) {
		initCardNavigationFix();
	}

	if ( settings.archiveVariations ) {
		initArchiveVariations();
	}

	if ( settings.mobileCartPlacement ) {
		initMobileCartPlacement();
	}
} )( window.jQuery, window.wjqaSettings );
