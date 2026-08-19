<?php
/**
 * Rule evaluator.
 *
 * @package Conditional_Product_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates recommendation rules.
 */
class CRW_Rule_Evaluator {
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
	}

	/**
	 * Check if a rule is eligible at the current location.
	 *
	 * @param array  $rule Rule.
	 * @param string $location Location.
	 * @return bool
	 */
	public function should_display_rule( array $rule, $location ) {
		if ( empty( $rule['enabled'] ) ) {
			return false;
		}

		if ( empty( $rule['display_locations'] ) || ! in_array( $location, $rule['display_locations'], true ) ) {
			return false;
		}

		return $this->passes_condition( $rule );
	}

	/**
	 * Get valid display product IDs for a rule.
	 *
	 * @param array $rule Rule.
	 * @return array
	 */
	public function get_display_products( array $rule ) {
		$product_ids = isset( $rule['display_products'] ) ? (array) $rule['display_products'] : array();
		$product_ids = array_map( 'absint', $product_ids );
		$product_ids = array_filter( $product_ids );
		$product_ids = $this->cart_service->filter_products_already_in_cart( $product_ids );

		$valid_ids = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( $this->is_valid_recommendation_product( $product, ! empty( $rule['display_settings']['show_out_of_stock'] ) ) ) {
				$valid_ids[] = $product_id;
			}
		}

		return array_values( array_unique( $valid_ids ) );
	}

	/**
	 * Evaluate the selected condition type.
	 *
	 * @param array $rule Rule.
	 * @return bool
	 */
	private function passes_condition( array $rule ) {
		return true;
	}

	/**
	 * Validate product visibility for recommendations.
	 *
	 * @param WC_Product|false|null $product Product.
	 * @param bool                  $show_out_of_stock Whether out-of-stock products can be shown.
	 * @return bool
	 */
	private function is_valid_recommendation_product( $product, $show_out_of_stock = false ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		if ( 'publish' !== get_post_status( $product->get_id() ) ) {
			return false;
		}

		if ( ! $product->is_purchasable() ) {
			return false;
		}

		if ( ! $show_out_of_stock && ! $product->is_in_stock() ) {
			return false;
		}

		return true;
	}
}
