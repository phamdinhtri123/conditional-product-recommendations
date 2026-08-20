<?php
/**
 * Frontend AJAX handlers.
 *
 * @package Conditional_Product_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX endpoints.
 */
class CRW_Ajax {
	/**
	 * Cart service.
	 *
	 * @var CRW_Cart_Condition_Service
	 */
	private $cart_service;

	/**
	 * Constructor.
	 *
	 * @param CRW_Cart_Condition_Service $cart_service Cart service.
	 */
	public function __construct( CRW_Cart_Condition_Service $cart_service ) {
		$this->cart_service = $cart_service;

		add_action( 'wp_ajax_crw_add_to_cart', array( $this, 'add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_crw_add_to_cart', array( $this, 'add_to_cart' ) );
	}

	/**
	 * Add recommended product to cart.
	 *
	 * @return void
	 */
	public function add_to_cart() {
		check_ajax_referer( 'crw_add_to_cart', 'nonce' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error(
				array(
					'message' => __( 'Cart is not available.', 'conditional-product-recommendations' ),
				),
				400
			);
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;
		$product    = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error(
				array(
					'message' => __( 'This product cannot be added to the cart.', 'conditional-product-recommendations' ),
				),
				400
			);
		}

		if ( $product->is_type( 'variable' ) ) {
			$single_variation = $this->get_single_addable_variation( $product );

			if ( $single_variation ) {
				$product    = $single_variation;
				$product_id = $single_variation->get_id();
			}
		}

		if ( ! $product->is_type( array( 'simple', 'variation' ) ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please choose product options before adding this product to the cart.', 'conditional-product-recommendations' ),
				),
				400
			);
		}

		if ( $this->cart_service->cart_contains_product( $product_id ) ) {
			wp_send_json_success(
				array(
					'product_id'      => $product_id,
					'fragments'       => $this->get_cart_fragments(),
					'cart_hash'       => WC()->cart->get_cart_hash(),
					'already_in_cart' => true,
					'message'         => __( 'This product is already in your cart.', 'conditional-product-recommendations' ),
				)
			);
		}

		if ( $product->is_type( 'variation' ) ) {
			$added = WC()->cart->add_to_cart(
				$product->get_parent_id(),
				$quantity,
				$product_id,
				$product->get_variation_attributes()
			);
		} else {
			$added = WC()->cart->add_to_cart( $product_id, $quantity );
		}

		if ( ! $added ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unable to add this product to the cart.', 'conditional-product-recommendations' ),
				),
				400
			);
		}

		WC()->cart->calculate_totals();

		if ( method_exists( WC()->cart, 'set_session' ) ) {
			WC()->cart->set_session();
		}

		if ( method_exists( WC()->cart, 'maybe_set_cart_cookies' ) ) {
			WC()->cart->maybe_set_cart_cookies();
		}

		if ( WC()->session && method_exists( WC()->cart, 'get_cart_for_session' ) ) {
			WC()->session->set( 'cart', WC()->cart->get_cart_for_session() );

			if ( method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
				WC()->session->set_customer_session_cookie( true );
			}
		}

		do_action( 'woocommerce_ajax_added_to_cart', $product_id );

		wp_send_json_success(
			array(
				'product_id' => $product_id,
				'fragments'  => $this->get_cart_fragments(),
				'cart_hash'  => WC()->cart->get_cart_hash(),
				'message'    => __( 'Product added to your cart.', 'conditional-product-recommendations' ),
			)
		);
	}

	/**
	 * Get the only addable variation for a variable product.
	 *
	 * @param WC_Product $product Variable product.
	 * @return WC_Product_Variation|null
	 */
	private function get_single_addable_variation( $product ) {
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return null;
		}

		$single_variation = null;

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
				continue;
			}

			if ( $single_variation ) {
				return null;
			}

			$single_variation = $variation;
		}

		return $single_variation;
	}

	/**
	 * Build basic cart fragments for mini cart refresh.
	 *
	 * @return array
	 */
	private function get_cart_fragments() {
		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();

		return apply_filters(
			'woocommerce_add_to_cart_fragments',
			array(
				'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
			)
		);
	}
}
