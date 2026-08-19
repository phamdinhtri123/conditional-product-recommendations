<?php
/**
 * Main plugin bootstrap.
 *
 * @package Conditional_Product_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates plugin services.
 */
class CRW_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var CRW_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Rule repository.
	 *
	 * @var CRW_Rule_Repository
	 */
	private $repository;

	/**
	 * Cart service.
	 *
	 * @var CRW_Cart_Condition_Service
	 */
	private $cart_service;

	/**
	 * Rule evaluator.
	 *
	 * @var CRW_Rule_Evaluator
	 */
	private $evaluator;

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		new CRW_Github_Updater();

		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$this->repository   = new CRW_Rule_Repository();
		$this->cart_service = new CRW_Cart_Condition_Service();
		$this->evaluator    = new CRW_Rule_Evaluator( $this->cart_service );

		if ( is_admin() ) {
			new CRW_Admin( $this->repository );
		}

		new CRW_Frontend( $this->repository, $this->evaluator );
		new CRW_Ajax( $this->cart_service );
	}

	/**
	 * Register the lightweight storage model.
	 *
	 * CPT is used instead of a custom table because recommendation rules are
	 * small WordPress-managed records and post meta is enough for the settings.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			CRW_POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Product Recommendations', 'conditional-product-recommendations' ),
					'singular_name' => __( 'Product Recommendation', 'conditional-product-recommendations' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * Check WooCommerce availability.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Admin notice when WooCommerce is missing.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Conditional Product Recommendations for WooCommerce requires WooCommerce to be active.', 'conditional-product-recommendations' );
		echo '</p></div>';
	}
}
