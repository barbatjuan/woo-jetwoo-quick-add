<?php
/**
 * Plugin Name:       Woo JetWooBuilder Quick Add
 * Plugin URI:        https://github.com/barbatjuan/woo-jetwoo-quick-add
 * Update URI:        https://github.com/barbatjuan/woo-jetwoo-quick-add
 * Description:       Lets customers add products to the cart straight from a JetWooBuilder product grid — variable products included — instead of being sent to the single product page.
 * Version:           3.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            barbatjuan
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-jetwoo-quick-add
 *
 * WC requires at least: 8.0
 * WC tested up to:      11.0
 *
 * ---------------------------------------------------------------------------
 * WHY THIS PLUGIN EXISTS
 *
 * On a stock WooCommerce loop half of this is not needed: WooCommerce renders AJAX
 * add-to-cart buttons on its own. JetWooBuilder replaces that loop with its own card
 * markup and its own click handling, which breaks them. This plugin is the glue that
 * puts the behaviour back, and adds the one thing WooCommerce has never done in a
 * listing — letting a variable product be chosen without opening its page.
 *
 * On a site without JetWooBuilder it deliberately does nothing.
 * ---------------------------------------------------------------------------
 */

defined( 'ABSPATH' ) || exit;

define( 'WJQA_VERSION', '3.3.0' );
define( 'WJQA_FILE', __FILE__ );
define( 'WJQA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WJQA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Versions the workarounds in this plugin were verified against.
 *
 * Both features compensate for behaviour in third-party code. If either plugin
 * ever changes that behaviour these workarounds become dead weight rather than
 * breakage — they are all guarded — but they should be revisited. Keep these
 * numbers honest; a workaround with no recorded expiry date is debt.
 */
define( 'WJQA_TESTED_JET_WOO_BUILDER', '2.3.4' );
define( 'WJQA_TESTED_WOOCOMMERCE', '11.0.1' );
define( 'WJQA_TESTED_VARIATION_SWATCHES_PRO', '2.3.0' );

require_once WJQA_PATH . 'includes/class-wjqa-support.php';
require_once WJQA_PATH . 'includes/class-wjqa-updates.php';
require_once WJQA_PATH . 'includes/class-wjqa-assets.php';
require_once WJQA_PATH . 'includes/class-wjqa-card-navigation.php';
require_once WJQA_PATH . 'includes/class-wjqa-mobile-cart.php';
require_once WJQA_PATH . 'includes/class-wjqa-variations-swatches.php';
require_once WJQA_PATH . 'includes/class-wjqa-variations-select.php';
require_once WJQA_PATH . 'includes/class-wjqa-archive-variations.php';

/**
 * Boot the plugin once every dependency has had a chance to load.
 *
 * Each feature checks its own dependencies, so a site with only some of the
 * stack gets only the parts that apply.
 */
function wjqa_init() {
	// Deliberately outside the WooCommerce check below. A site that has switched
	// WooCommerce off still has this plugin installed, and it should still be told when
	// a new version exists — an update channel that goes quiet exactly when the site is
	// mid-surgery is the one that leaves it on an old version for a year.
	WJQA_Updates::init();

	if ( ! WJQA_Support::has_woocommerce() ) {
		return;
	}

	WJQA_Assets::init();
	WJQA_Card_Navigation::init();
	WJQA_Mobile_Cart::init();
	WJQA_Archive_Variations::init();
}
add_action( 'plugins_loaded', 'wjqa_init', 20 );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage.
 *
 * This plugin never touches order storage, so the declaration is safe and keeps
 * WooCommerce from flagging it as incompatible.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WJQA_FILE, true );
		}
	}
);
