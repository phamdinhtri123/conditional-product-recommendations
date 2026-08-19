<?php
/**
 * Rule persistence.
 *
 * @package Conditional_Product_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes recommendation rules.
 */
class CRW_Rule_Repository {
	const META_ENABLED            = '_rule_enabled';
	const META_CONDITION_TYPE     = '_condition_type';
	const META_CONDITION_PRODUCTS = '_condition_products';
	const META_DISPLAY_PRODUCTS   = '_display_products';
	const META_DISPLAY_LOCATIONS  = '_display_locations';

	/**
	 * Get all rules.
	 *
	 * @param bool $enabled_only Whether to fetch enabled rules only.
	 * @return array
	 */
	public function get_rules( $enabled_only = false ) {
		$args = array(
			'post_type'      => CRW_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		if ( $enabled_only ) {
			$args['meta_query'] = array(
				array(
					'key'   => self::META_ENABLED,
					'value' => 'yes',
				),
			);
		}

		$query = new WP_Query( $args );
		$rules = array();

		foreach ( $query->posts as $post ) {
			$rules[] = $this->normalize_rule( $post );
		}

		return $rules;
	}

	/**
	 * Get one rule.
	 *
	 * @param int $rule_id Rule ID.
	 * @return array|null
	 */
	public function get_rule( $rule_id ) {
		$post = get_post( absint( $rule_id ) );

		if ( ! $post || CRW_POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $this->normalize_rule( $post );
	}

	/**
	 * Save a rule.
	 *
	 * @param array $data Unsanitized form data.
	 * @return int|WP_Error
	 */
	public function save_rule( array $data ) {
		$rule_id = isset( $data['rule_id'] ) ? absint( $data['rule_id'] ) : 0;
		$title   = isset( $data['rule_name'] ) ? sanitize_text_field( wp_unslash( $data['rule_name'] ) ) : '';

		if ( '' === $title ) {
			return new WP_Error( 'crw_missing_name', __( 'Rule name is required.', 'conditional-product-recommendations' ) );
		}

		$post_data = array(
			'post_title'  => $title,
			'post_type'   => CRW_POST_TYPE,
			'post_status' => 'publish',
		);

		if ( $rule_id ) {
			$post_data['ID'] = $rule_id;
			$saved_id        = wp_update_post( $post_data, true );
		} else {
			$saved_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $saved_id ) ) {
			return $saved_id;
		}

		$enabled           = ! empty( $data['rule_enabled'] ) ? 'yes' : 'no';
		$display_products  = $this->sanitize_id_array( isset( $data['display_products'] ) ? (array) $data['display_products'] : array() );
		$display_locations = $this->sanitize_locations( isset( $data['display_locations'] ) ? (array) $data['display_locations'] : array() );

		update_post_meta( $saved_id, self::META_ENABLED, $enabled );
		update_post_meta( $saved_id, self::META_CONDITION_TYPE, 'none' );
		update_post_meta( $saved_id, self::META_CONDITION_PRODUCTS, array() );
		update_post_meta( $saved_id, self::META_DISPLAY_PRODUCTS, $display_products );
		update_post_meta( $saved_id, self::META_DISPLAY_LOCATIONS, $display_locations );

		return absint( $saved_id );
	}

	/**
	 * Delete rule.
	 *
	 * @param int $rule_id Rule ID.
	 * @return void
	 */
	public function delete_rule( $rule_id ) {
		wp_delete_post( absint( $rule_id ), true );
	}

	/**
	 * Toggle rule status.
	 *
	 * @param int $rule_id Rule ID.
	 * @return void
	 */
	public function toggle_rule( $rule_id ) {
		$rule = $this->get_rule( $rule_id );

		if ( ! $rule ) {
			return;
		}

		update_post_meta( $rule_id, self::META_ENABLED, $rule['enabled'] ? 'no' : 'yes' );
	}

	/**
	 * Convert a post into a rule array.
	 *
	 * @param WP_Post $post Rule post.
	 * @return array
	 */
	private function normalize_rule( WP_Post $post ) {
		return array(
			'id'                 => absint( $post->ID ),
			'name'               => $post->post_title,
			'enabled'            => 'yes' === get_post_meta( $post->ID, self::META_ENABLED, true ),
			'condition_type'     => get_post_meta( $post->ID, self::META_CONDITION_TYPE, true ) ?: 'none',
			'condition_products' => array(),
			'display_products'   => $this->sanitize_id_array( (array) get_post_meta( $post->ID, self::META_DISPLAY_PRODUCTS, true ) ),
			'display_locations'  => $this->sanitize_locations( (array) get_post_meta( $post->ID, self::META_DISPLAY_LOCATIONS, true ) ),
		);
	}

	/**
	 * Sanitize product IDs.
	 *
	 * @param array $ids IDs.
	 * @return array
	 */
	private function sanitize_id_array( array $ids ) {
		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Sanitize locations.
	 *
	 * @param array $locations Locations.
	 * @return array
	 */
	private function sanitize_locations( array $locations ) {
		$allowed = array( 'product', 'cart', 'checkout' );
		$clean   = array();

		foreach ( $locations as $location ) {
			$location = sanitize_key( wp_unslash( $location ) );
			if ( in_array( $location, $allowed, true ) ) {
				$clean[] = $location;
			}
		}

		return array_values( array_unique( $clean ) );
	}
}
