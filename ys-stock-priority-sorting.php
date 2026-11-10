<?php
/**
 * Plugin Name:       YS | Stock priority sorting
 * Plugin URI:        https://github.com/ysaintlary/ys-stock-priority-sorting
 * Description:       Trie les produits WooCommerce par priorité de stock : en stock, stock faible, rupture.
 * Version: 1.0.1
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * Author:            Yves Saint-Lary
 * Author URI:        https://ysaintlary.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       ys-stock-priority-sorting
 * Domain Path:       /languages
 *
 * WC requires at least: 9.0
 * WC tested up to:      11.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YS_SPS_VERSION', '1.0.0' );
define( 'YS_SPS_FILE', __FILE__ );
define( 'YS_SPS_PATH', plugin_dir_path( __FILE__ ) );

require_once __DIR__ . '/lib/wp-plugin-base/wp-plugin-base-runtime-updater.php';

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__ );
	}
} );

add_action( 'plugins_loaded', 'ys_sps_init' );

function ys_sps_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once YS_SPS_PATH . 'includes/class-effective-step.php';
	require_once YS_SPS_PATH . 'includes/class-sorting.php';

	YS_SPS_Effective_Step::init();
	YS_SPS_Sorting::init();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once YS_SPS_PATH . 'includes/class-cli.php';
		YS_SPS_CLI::register();
	}
}

register_activation_hook( __FILE__, 'ys_sps_activate' );

function ys_sps_activate() {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'as_schedule_single_action' ) ) {
		return;
	}

	as_unschedule_all_actions( 'ys_sps_recalculate_all_batch', null, 'ys-sps' );
	as_schedule_single_action( time(), 'ys_sps_recalculate_all_batch', array( 0 ), 'ys-sps' );
}

register_deactivation_hook( __FILE__, 'ys_sps_deactivate' );

function ys_sps_deactivate() {
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'ys_sps_recalculate_all_batch', null, 'ys-sps' );
		as_unschedule_all_actions( 'ys_sps_recalculate_category_batch', null, 'ys-sps' );
	}
}
