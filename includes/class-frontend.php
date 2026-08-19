<?php
/**
 * Frontend rendering.
 *
 * @package Conditional_Product_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers frontend hooks and renders sections.
 */
class CRW_Frontend {
	/**
	 * Repository.
	 *
	 * @var CRW_Rule_Repository
	 */
	private $repository;

	/**
	 * Evaluator.
	 *
	 * @var CRW_Rule_Evaluator
	 */
	private $evaluator;

	/**
	 * Constructor.
	 *
	 * @param CRW_Rule_Repository $repository Repository.
	 * @param CRW_Rule_Evaluator  $evaluator Evaluator.
	 */
	public function __construct( CRW_Rule_Repository $repository, CRW_Rule_Evaluator $evaluator ) {
		$this->repository = $repository;
		$this->evaluator  = $evaluator;

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_product_recommendations' ) );
		add_action( 'woocommerce_after_cart_table', array( $this, 'render_cart_recommendations' ) );
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'render_checkout_recommendations' ) );
	}

	/**
	 * Enqueue frontend assets only on relevant WooCommerce screens.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_recommendation_screen() ) {
			return;
		}

		wp_enqueue_style( 'crw-frontend', CRW_PLUGIN_URL . 'assets/css/frontend.css', array(), CRW_VERSION );
		wp_enqueue_script( 'crw-frontend', CRW_PLUGIN_URL . 'assets/js/frontend.js', array(), CRW_VERSION, true );
		wp_localize_script(
			'crw-frontend',
			'crwRecommendations',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'crw_add_to_cart' ),
				'i18n'    => array(
					'adding' => __( 'Adding...', 'conditional-product-recommendations' ),
					'error'  => __( 'Unable to add product. Please try again.', 'conditional-product-recommendations' ),
				),
			)
		);
	}

	/**
	 * Render product recommendations.
	 *
	 * @return void
	 */
	public function render_product_recommendations() {
		$this->render_recommendations( 'product' );
	}

	/**
	 * Render cart recommendations.
	 *
	 * @return void
	 */
	public function render_cart_recommendations() {
		$this->render_recommendations( 'cart' );
	}

	/**
	 * Render checkout recommendations.
	 *
	 * @return void
	 */
	public function render_checkout_recommendations() {
		$this->render_recommendations( 'checkout' );
	}

	/**
	 * Shared renderer for all locations.
	 *
	 * @param string $location Location.
	 * @return void
	 */
	public function render_recommendations( $location ) {
		$product_ids = $this->get_recommendation_product_ids( $location );

		if ( empty( $product_ids ) ) {
			return;
		}

		$products = array();
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$products[] = $product;
			}
		}

		if ( empty( $products ) ) {
			return;
		}

		$heading = apply_filters( 'crw_recommendations_heading', __( "Don't Forget the Essentials", 'conditional-product-recommendations' ), $location );

		include CRW_PLUGIN_DIR . 'templates/recommendations.php';
	}

	/**
	 * Collect unique product IDs from active rules.
	 *
	 * @param string $location Location.
	 * @return array
	 */
	private function get_recommendation_product_ids( $location ) {
		$rules       = $this->repository->get_rules( true );
		$product_ids = array();

		foreach ( $rules as $rule ) {
			if ( ! $this->evaluator->should_display_rule( $rule, $location ) ) {
				continue;
			}

			$product_ids = array_merge( $product_ids, $this->evaluator->get_display_products( $rule ) );
		}

		$product_ids = array_values( array_unique( array_map( 'absint', $product_ids ) ) );

		if ( 'product' === $location ) {
			$product_ids = array_values( array_diff( $product_ids, $this->get_current_product_exclusion_ids() ) );
		}

		return $product_ids;
	}

	/**
	 * Product IDs that should not appear on the current single product page.
	 *
	 * @return array
	 */
	private function get_current_product_exclusion_ids() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return array();
		}

		$current_product_id = absint( get_the_ID() );
		$current_product    = wc_get_product( $current_product_id );
		$exclude_ids        = array_filter( array( $current_product_id ) );

		if ( $current_product && $current_product->is_type( 'variable' ) ) {
			$exclude_ids = array_merge( $exclude_ids, array_map( 'absint', $current_product->get_children() ) );
		}

		if ( $current_product && $current_product->is_type( 'variation' ) ) {
			$exclude_ids[] = absint( $current_product->get_parent_id() );
		}

		return array_values( array_unique( array_filter( $exclude_ids ) ) );
	}

	/**
	 * Determine current frontend screen.
	 *
	 * @return bool
	 */
	private function is_recommendation_screen() {
		return ( function_exists( 'is_product' ) && is_product() )
			|| ( function_exists( 'is_cart' ) && is_cart() )
			|| ( function_exists( 'is_checkout' ) && is_checkout() );
	}
}
