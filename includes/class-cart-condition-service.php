<?php
/**
 * Cart condition helpers.
 *
 * @package Conditional_Product_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized WooCommerce cart detection.
 */
class CRW_Cart_Condition_Service {
	/**
	 * Check whether cart contains a product, parent product, or variation.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool
	 */
	public function cart_contains_product( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id || ! $this->cart_is_available() ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( $this->cart_item_matches_product( $cart_item, $product_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether cart contains any selected product.
	 *
	 * @param array $product_ids Product IDs.
	 * @return bool
	 */
	public function cart_contains_any_product( array $product_ids ) {
		foreach ( $product_ids as $product_id ) {
			if ( $this->cart_contains_product( $product_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove products already represented in the cart.
	 *
	 * @param array $product_ids Product IDs.
	 * @return array
	 */
	public function filter_products_already_in_cart( array $product_ids ) {
		$filtered = array();

		foreach ( $product_ids as $product_id ) {
			$product_id = absint( $product_id );

			if ( $product_id && ! $this->cart_contains_product( $product_id ) ) {
				$filtered[] = $product_id;
			}
		}

		return array_values( array_unique( $filtered ) );
	}

	/**
	 * Determine if cart APIs are available.
	 *
	 * @return bool
	 */
	private function cart_is_available() {
		return function_exists( 'WC' ) && WC() && WC()->cart;
	}

	/**
	 * Match simple, variable parent, and variation IDs.
	 *
	 * @param array $cart_item Cart item.
	 * @param int   $target_id Target product ID.
	 * @return bool
	 */
	private function cart_item_matches_product( array $cart_item, $target_id ) {
		$item_product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		$item_variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

		if ( $target_id === $item_product_id || $target_id === $item_variation_id ) {
			return true;
		}

		if ( $item_variation_id ) {
			$item_variation = wc_get_product( $item_variation_id );
			if ( $item_variation && $target_id === $item_variation->get_parent_id() ) {
				return true;
			}
		}

		return false;
	}
}
