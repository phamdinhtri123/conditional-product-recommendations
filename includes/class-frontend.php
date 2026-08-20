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
	 * Render guard by location.
	 *
	 * @var array
	 */
	private $rendered_locations = array();

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
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'render_checkout_recommendations' ) );
		add_filter( 'render_block', array( $this, 'append_block_recommendations' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'append_cart_content_recommendations' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_cart_footer_fallback' ), 5 );
		add_action( 'wp_footer', array( $this, 'render_debug_comment' ), 999 );
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

		wp_enqueue_style( 'crw-swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11' );
		wp_enqueue_style( 'crw-frontend', CRW_PLUGIN_URL . 'assets/css/frontend.css', array( 'crw-swiper' ), CRW_VERSION );
		wp_enqueue_script( 'crw-swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11', true );
		wp_enqueue_script( 'crw-frontend', CRW_PLUGIN_URL . 'assets/js/frontend.js', array( 'crw-swiper' ), CRW_VERSION, true );
		wp_localize_script(
			'crw-frontend',
			'crwRecommendations',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'crw_add_to_cart' ),
				'i18n'    => array(
					'adding' => __( 'Adding...', 'conditional-product-recommendations' ),
					'added'  => __( 'Product added to your cart.', 'conditional-product-recommendations' ),
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
	 * Fallback render for Cart Block pages where classic cart hooks do not run.
	 *
	 * @param string $content Page content.
	 * @return string
	 */
	public function append_cart_content_recommendations( $content ) {
		if (
			is_admin()
			|| ! $this->is_cart_screen()
		) {
			return $content;
		}

		return $content . $this->get_recommendations_html( 'cart' );
	}

	/**
	 * Append recommendations inside WooCommerce Cart and Checkout blocks.
	 *
	 * @param string $block_content Rendered block content.
	 * @param array  $block Block data.
	 * @return string
	 */
	public function append_block_recommendations( $block_content, $block ) {
		if ( empty( $block['blockName'] ) ) {
			return $block_content;
		}

		if ( 'woocommerce/cart' === $block['blockName'] ) {
			return $this->append_inside_block( $block_content, $this->get_recommendations_html( 'cart' ) );
		}

		if (
			'woocommerce/checkout-order-summary-block' === $block['blockName']
			&& function_exists( 'is_checkout' )
			&& is_checkout()
		) {
			return $this->append_inside_block( $block_content, $this->get_recommendations_html( 'checkout' ) );
		}

		return $block_content;
	}

	/**
	 * Last-resort cart render for themes that bypass cart hooks/content filters.
	 *
	 * @return void
	 */
	public function render_cart_footer_fallback() {
		if (
			! $this->is_cart_screen()
			|| ! empty( $this->rendered_locations['cart'] )
		) {
			return;
		}

		echo '<div class="crw-recommendations__cart-footer-fallback">';
		$this->render_cart_recommendations();
		echo '</div>';
	}

	/**
	 * Print opt-in diagnostics for admins checking cart rendering.
	 *
	 * @return void
	 */
	public function render_debug_comment() {
		if (
			! isset( $_GET['crw_debug'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| ! current_user_can( 'manage_woocommerce' )
		) {
			return;
		}

		$context     = $this->get_recommendation_context( 'cart' );
		$product_ids = implode( ',', array_map( 'absint', $context['product_ids'] ) );
		$settings    = $context['settings'];

		printf(
			"\n<!-- CRW debug cart: is_cart=%s; has_cart_block=%s; has_cart_shortcode=%s; is_cart_screen=%s; rendered_cart=%s; product_count=%d; product_ids=%s; max_products=%d -->\n",
			esc_html( function_exists( 'is_cart' ) && is_cart() ? 'yes' : 'no' ),
			esc_html( $this->current_page_has_block( 'woocommerce/cart' ) ? 'yes' : 'no' ),
			esc_html( $this->current_page_has_shortcode( 'woocommerce_cart' ) ? 'yes' : 'no' ),
			esc_html( $this->is_cart_screen() ? 'yes' : 'no' ),
			esc_html( empty( $this->rendered_locations['cart'] ) ? 'no' : 'yes' ),
			absint( count( $context['product_ids'] ) ),
			esc_html( $product_ids ),
			absint( $settings['max_products'] )
		);
	}

	/**
	 * Render recommendations and return captured HTML.
	 *
	 * @param string $location Location.
	 * @return string
	 */
	private function get_recommendations_html( $location ) {
		ob_start();
		$this->render_recommendations( $location );
		return ob_get_clean();
	}

	/**
	 * Insert recommendations inside a rendered block wrapper.
	 *
	 * @param string $block_content Rendered block content.
	 * @param string $recommendations Recommendations HTML.
	 * @return string
	 */
	private function append_inside_block( $block_content, $recommendations ) {
		if ( '' === trim( $recommendations ) ) {
			return $block_content;
		}

		$position = strripos( $block_content, '</div>' );

		if ( false === $position ) {
			return $block_content . $recommendations;
		}

		return substr_replace( $block_content, $recommendations, $position, 0 );
	}

	/**
	 * Shared renderer for all locations.
	 *
	 * @param string $location Location.
	 * @return void
	 */
	public function render_recommendations( $location ) {
		if ( ! empty( $this->rendered_locations[ $location ] ) ) {
			return;
		}

		$context     = $this->get_recommendation_context( $location );
		$product_ids = $context['product_ids'];
		$settings    = $context['settings'];

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

		$heading = apply_filters( 'crw_recommendations_heading', $settings['heading_text'], $location );

		$this->rendered_locations[ $location ] = true;

		include CRW_PLUGIN_DIR . 'templates/recommendations.php';
	}

	/**
	 * Collect unique product IDs from active rules.
	 *
	 * @param string $location Location.
	 * @return array
	 */
	private function get_recommendation_context( $location ) {
		$rules       = $this->repository->get_rules( true );
		$product_ids = array();
		$settings    = $this->repository->get_default_display_settings();

		foreach ( $rules as $rule ) {
			if ( ! $this->evaluator->should_display_rule( $rule, $location ) ) {
				continue;
			}

			$rule_product_ids = $this->evaluator->get_display_products( $rule );

			if ( empty( $rule_product_ids ) ) {
				continue;
			}

			if ( empty( $product_ids ) && ! empty( $rule['display_settings'] ) ) {
				$settings = $rule['display_settings'];
			}

			$product_ids = array_merge( $product_ids, $rule_product_ids );
		}

		$product_ids = array_values( array_unique( array_map( 'absint', $product_ids ) ) );

		if ( 'product' === $location ) {
			$product_ids = array_values( array_diff( $product_ids, $this->get_current_product_exclusion_ids() ) );
		}

		$product_ids = $this->sort_product_ids( $product_ids, $settings['product_order_by'] );
		$product_ids = array_slice( $product_ids, 0, absint( $settings['max_products'] ) );

		return array(
			'product_ids' => $product_ids,
			'settings'    => $settings,
		);
	}

	/**
	 * Sort products by selected display option.
	 *
	 * @param array  $product_ids Product IDs.
	 * @param string $order_by Order by option.
	 * @return array
	 */
	private function sort_product_ids( array $product_ids, $order_by ) {
		if ( 'random' === $order_by ) {
			shuffle( $product_ids );
			return $product_ids;
		}

		if ( ! in_array( $order_by, array( 'name_asc', 'price_asc', 'price_desc' ), true ) ) {
			return $product_ids;
		}

		usort(
			$product_ids,
			function ( $a, $b ) use ( $order_by ) {
				$product_a = wc_get_product( $a );
				$product_b = wc_get_product( $b );

				if ( ! $product_a || ! $product_b ) {
					return 0;
				}

				if ( 'name_asc' === $order_by ) {
					return strcasecmp( $product_a->get_name(), $product_b->get_name() );
				}

				$price_a = (float) $product_a->get_price();
				$price_b = (float) $product_b->get_price();

				if ( 'price_desc' === $order_by ) {
					return $price_b <=> $price_a;
				}

				return $price_a <=> $price_b;
			}
		);

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
			|| $this->is_cart_screen()
			|| ( function_exists( 'is_checkout' ) && is_checkout() );
	}

	/**
	 * Detect cart pages, including Cart Block pages where is_cart() is false.
	 *
	 * @return bool
	 */
	private function is_cart_screen() {
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		return $this->current_page_has_block( 'woocommerce/cart' ) || $this->current_page_has_shortcode( 'woocommerce_cart' );
	}

	/**
	 * Check if the current singular page contains a specific block.
	 *
	 * @param string $block_name Block name.
	 * @return bool
	 */
	private function current_page_has_block( $block_name ) {
		if ( ! is_singular() || ! function_exists( 'has_block' ) ) {
			return false;
		}

		$post = get_post();

		return $post && has_block( $block_name, $post );
	}

	/**
	 * Check if the current singular page contains a shortcode.
	 *
	 * @param string $shortcode Shortcode tag.
	 * @return bool
	 */
	private function current_page_has_shortcode( $shortcode ) {
		if ( ! is_singular() || ! function_exists( 'has_shortcode' ) ) {
			return false;
		}

		$post = get_post();

		return $post && has_shortcode( $post->post_content, $shortcode );
	}
}
