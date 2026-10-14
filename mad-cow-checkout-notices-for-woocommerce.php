<?php
/**
 * Plugin Name:       Mad Cow Checkout Notices for WooCommerce
 * Description:       Admin-managed WooCommerce checkout banners with optional cart-based targeting and an optional question (radio or dropdown) that saves the customer's answer to the order.
 * Version:           1.0.0
 * Author:            Mad Cow Web
 * Author URI:        https://madcowweb.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mad-cow-checkout-notices-for-woocommerce
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:      11.1.0
 *
 * @package Mad_Cow_Checkout_Notices_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCWCCN_VERSION', '1.0.0' );
define( 'MCWCCN_FILE', __FILE__ );
define( 'MCWCCN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCWCCN_URL', plugin_dir_url( __FILE__ ) );
define( 'MCWCCN_POST_TYPE', 'mcwccn_notice' );

/**
 * Upper bound on how many notices are ever loaded in one request. Checkout
 * renders every notice, so this query runs on a customer-facing page; a site
 * past this count is misconfigured rather than served an unbounded query.
 */
define( 'MCWCCN_MAX_NOTICES', 100 );

add_action( 'before_woocommerce_init', 'mcwccn_declare_hpos_compat' );
add_action( 'plugins_loaded', 'mcwccn_bootstrap' );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 * Order meta is written and read through the order CRUD API, which is HPOS-safe.
 */
function mcwccn_declare_hpos_compat() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MCWCCN_FILE, true );
	}
}

/**
 * Boot the plugin. Requires WooCommerce.
 */
function mcwccn_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'mcwccn_wc_missing_notice' );
		return;
	}

	require_once MCWCCN_DIR . 'includes/mcwccn-helpers.php';
	require_once MCWCCN_DIR . 'includes/class-mcwccn-cpt.php';
	require_once MCWCCN_DIR . 'includes/class-mcwccn-editor.php';
	require_once MCWCCN_DIR . 'includes/class-mcwccn-frontend.php';
	require_once MCWCCN_DIR . 'includes/class-mcwccn-order-display.php';

	new MCWCCN_CPT();
	new MCWCCN_Editor();
	new MCWCCN_Frontend();
	new MCWCCN_Order_Display();
}

/**
 * Admin notice when WooCommerce is inactive.
 */
function mcwccn_wc_missing_notice() {
	echo '<div class="notice notice-error"><p>'
		. esc_html__( 'Mad Cow Checkout Notices for WooCommerce requires WooCommerce to be installed and active.', 'mad-cow-checkout-notices-for-woocommerce' )
		. '</p></div>';
}
