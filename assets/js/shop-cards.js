/**
 * Woo JetWooBuilder Quick Add — front-end behaviour.
 *
 * Two independent features, each gated by its own flag from wjqaSettings:
 *
 *   1. cardNavigationFix — keep a click on the cart controls from triggering
 *      JetWooBuilder's "Clickable item" navigation.
 *   2. archiveVariations — turn the card's "Select options" link into a real AJAX
 *      add-to-cart once the customer picks a variation. Which control does the
 *      picking is decided server-side and arrives as `variationPicker`: 'swatches'
 *      where the site has Variation Swatches Pro, 'select' everywhere else.
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
	 * Label of a card's cart button, wherever JetWooBuilder put the text.
	 *
	 * Shared: both pickers relabel the button once a variation is chosen, because
	 * until then it reads "Select options" and hides the fact that it now adds
	 * something to the cart.
	 */
	function setLabel( $button, text ) {
		var $label = $button.find( '.jet-woo-product-button__label' );

		if ( $label.length ) {
			$label.text( text );
		} else {
			$button.text( text );
		}
	}

	/**
	 * Feature 2, swatches path — drive Variation Swatches Pro's archive swatches.
	 *
	 * The swatches themselves, and the rewiring of the button to the chosen variation,
	 * are Variation Swatches Pro's and JetWooBuilder's own code. This only starts them
	 * and tidies up after them.
	 */
	function initSwatchesPicker() {
		/**
		 * Variation Swatches Pro initialises archive swatches lazily through an
		 * IntersectionObserver, and that observer never fires for swatches rendered
		 * inside JetWooBuilder cards. Without initialisation the button is never
		 * rewired to the chosen variation, so clicking it just follows its href.
		 *
		 * Calling the plugin's own public jQuery plugin is the supported way in. The
		 * `:not(.wvs-pro-loaded)` guard is the same one the plugin uses, so if it ever
		 * starts initialising these itself nothing is done twice.
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

				setLabel( $button, settings.addToCartText );

				if ( $input.length ) {
					syncQuantity( $input.get( 0 ) );
				}
			}, 10 );
		} );

		$( document.body ).on( 'reset_data', '.wvs-archive-variations-wrapper', function () {
			var $button = $( this ).closest( '.jet-woo-product-button' ).find( '.wvs-add-to-cart-button' ),
				defaultLabel = $button.data( 'wjqaDefaultLabel' );

			if ( $button.length && defaultLabel ) {
				setLabel( $button, defaultLabel );
			}
		} );
	}

	/**
	 * Feature 2, dropdown path — drive the native picker this plugin renders.
	 */
	function initSelectPicker() {
		function buttonFor( $select ) {
			return $select.closest( '.jet-woo-product-button' ).find( '.wjqa-add-to-cart' );
		}

		function priceFor( $select ) {
			return $select.closest( '.jet-woo-products__item' ).find( '.jet-woo-product-price' ).first();
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
			if ( query.matches ) {
				$( '.jet-woo-products__item' ).each( function () {
					var $item = $( this ),
						$inner = $item.children( '.jet-woo-products__inner-box' ).first(),
						// Only a control that is INSIDE the hover overlay. That is the
						// one this feature exists to rescue, and requiring it keeps the
						// feature off presets that already show their button in the
						// normal flow — moving that button would be an unasked-for
						// redesign, not a fix. It also means an already-moved control
						// cannot match twice.
						$controls = $item.find( '.hovered-content .jet-woo-product-button' ).first();

					if ( ! $inner.length || ! $controls.length ) {
						return;
					}

					// Remember the overlay so a resize back to a wide screen can put
					// the card back exactly as JetWooBuilder rendered it.
					$controls.data( 'wjqaOverlay', $controls.parent() );
					$inner.append( $controls.addClass( 'wjqa-inline-cart' ) );
				} );

				return;
			}

			// Above the breakpoint, put back only what this feature moved.
			$( '.jet-woo-product-button.wjqa-inline-cart' ).each( function () {
				var $controls = $( this ),
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
		// Which picker exists on the page was decided server-side, from what the site
		// has installed. Running the wrong one would bind handlers for controls that
		// were never rendered.
		if ( 'swatches' === settings.variationPicker ) {
			initSwatchesPicker();
		} else {
			initSelectPicker();
		}
	}

	if ( settings.mobileCartPlacement ) {
		initMobileCartPlacement();
	}
} )( window.jQuery, window.wjqaSettings );
