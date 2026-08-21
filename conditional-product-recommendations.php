<?php
/**
 * Plugin Name: Conditional Product Recommendations for WooCommerce
 * Description: Display conditional WooCommerce product recommendations on product, cart, and checkout pages.
 * Version: 1.1.7
 * Author: SeaMKT
 * Text Domain: conditional-product-recommendations
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 *
 * @package Conditional_Product_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRW_VERSION', '1.1.7' );
define( 'CRW_PLUGIN_FILE', __FILE__ );
define( 'CRW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CRW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CRW_POST_TYPE', 'crw_recommendation' );

if ( ! defined( 'CRW_GITHUB_REPOSITORY_URL' ) ) {
	define( 'CRW_GITHUB_REPOSITORY_URL', 'https://github.com/phamdinhtri123/conditional-product-recommendations' );
}

if ( ! defined( 'CRW_GITHUB_ACCESS_TOKEN' ) ) {
	define( 'CRW_GITHUB_ACCESS_TOKEN', '' );
}

if ( ! defined( 'CRW_GITHUB_BRANCH' ) ) {
	define( 'CRW_GITHUB_BRANCH', '' );
}

require_once CRW_PLUGIN_DIR . 'includes/class-rule-repository.php';
require_once CRW_PLUGIN_DIR . 'includes/class-cart-condition-service.php';
require_once CRW_PLUGIN_DIR . 'includes/class-rule-evaluator.php';
require_once CRW_PLUGIN_DIR . 'includes/class-admin.php';
require_once CRW_PLUGIN_DIR . 'includes/class-frontend.php';
require_once CRW_PLUGIN_DIR . 'includes/class-ajax.php';
require_once CRW_PLUGIN_DIR . 'includes/class-github-updater.php';
require_once CRW_PLUGIN_DIR . 'includes/class-plugin.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'CRW_Plugin', 'init' ) );
