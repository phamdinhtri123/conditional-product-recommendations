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
	const META_DISPLAY_SETTINGS   = '_display_settings';

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
		$display_settings  = $this->sanitize_display_settings( isset( $data['display_settings'] ) ? (array) $data['display_settings'] : array() );

		update_post_meta( $saved_id, self::META_ENABLED, $enabled );
		update_post_meta( $saved_id, self::META_CONDITION_TYPE, 'none' );
		update_post_meta( $saved_id, self::META_CONDITION_PRODUCTS, array() );
		update_post_meta( $saved_id, self::META_DISPLAY_PRODUCTS, $display_products );
		update_post_meta( $saved_id, self::META_DISPLAY_LOCATIONS, $display_locations );
		update_post_meta( $saved_id, self::META_DISPLAY_SETTINGS, $display_settings );

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
			'display_settings'   => $this->sanitize_display_settings( (array) get_post_meta( $post->ID, self::META_DISPLAY_SETTINGS, true ) ),
		);
	}

	/**
	 * Get default display settings.
	 *
	 * @return array
	 */
	public function get_default_display_settings() {
		return array(
			'heading_text'        => __( "Don't Forget the Essentials", 'conditional-product-recommendations' ),
			'heading_icon_url'    => '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
										<path d="M0 0h24v24H0z" fill="none" />
										<path fill="none" stroke="#2563eb" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="1.5" d="M15 18a3 3 0 1 1-6 0m-2.957 0a2.03 2.03 0 0 1-1.718-.95a2.08 2.08 0 0 1-.142-1.972l.545-1.212A13.5 13.5 0 0 0 5.882 9.23l.031-.473A6.2 6.2 0 0 1 7.83 4.666A6.07 6.07 0 0 1 11.998 3a6.07 6.07 0 0 1 4.168 1.666a6.2 6.2 0 0 1 1.917 4.09l.031.474a13.5 13.5 0 0 0 1.154 4.636l.546 1.211a2.08 2.08 0 0 1-.138 1.976a2.03 2.03 0 0 1-1.723.947z" />
									</svg>',
			'subtitle'            => __( 'Make sure you have everything you need.', 'conditional-product-recommendations' ),
			'max_products'        => 6,
			'layout_mode'         => 'columns',
			'columns_desktop'     => 3,
			'columns_tablet'      => 2,
			'columns_mobile'      => 1,
			'product_layout_mode'         => 'columns',
			'product_columns_desktop'     => 3,
			'product_columns_tablet'      => 2,
			'product_columns_mobile'      => 1,
			'product_desktop_layout_mode' => 'columns',
			'product_tablet_layout_mode'  => 'columns',
			'product_mobile_layout_mode'  => 'columns',
			'cart_layout_mode'            => 'columns',
			'cart_columns_desktop'        => 2,
			'cart_columns_tablet'         => 2,
			'cart_columns_mobile'         => 1,
			'cart_desktop_layout_mode'    => 'columns',
			'cart_tablet_layout_mode'     => 'columns',
			'cart_mobile_layout_mode'     => 'columns',
			'checkout_layout_mode'        => 'rows',
			'checkout_columns_desktop'    => 1,
			'checkout_columns_tablet'     => 1,
			'checkout_columns_mobile'     => 1,
			'checkout_desktop_layout_mode' => 'rows',
			'checkout_tablet_layout_mode'  => 'rows',
			'checkout_mobile_layout_mode'  => 'rows',
			'show_out_of_stock'   => false,
			'show_price'          => true,
			'show_add_button'     => true,
			'add_button_style'    => 'icon_plus',
			'add_button_icon_url' => '',
			'product_order_by'    => 'default',
			'custom_css_class'    => '',
			'enable_animation'    => true,
			'primary_color'       => '#2563eb',
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

	/**
	 * Sanitize display settings.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	private function sanitize_display_settings( array $settings ) {
		$defaults = $this->get_default_display_settings();
		$settings = wp_parse_args( $settings, $defaults );

		$primary_color = sanitize_hex_color( wp_unslash( $settings['primary_color'] ) );
		$legacy_layout_mode = $this->sanitize_choice( $settings['layout_mode'], array( 'columns', 'rows' ), $defaults['layout_mode'] );
		$legacy_columns_desktop = $this->sanitize_column_count( $settings['columns_desktop'], $defaults['columns_desktop'] );
		$legacy_columns_tablet  = $this->sanitize_column_count( $settings['columns_tablet'], $defaults['columns_tablet'] );
		$legacy_columns_mobile  = $this->sanitize_column_count( $settings['columns_mobile'], $defaults['columns_mobile'] );
		$product_layout_mode = $this->sanitize_choice( $settings['product_layout_mode'], array( 'columns', 'rows', 'slider' ), $legacy_layout_mode );
		$cart_layout_mode = $this->sanitize_choice( $settings['cart_layout_mode'], array( 'columns', 'rows', 'slider' ), $legacy_layout_mode );
		$checkout_layout_mode = $this->sanitize_choice( $settings['checkout_layout_mode'], array( 'columns', 'rows', 'slider' ), $defaults['checkout_layout_mode'] );

		return array(
			'heading_text'        => sanitize_text_field( wp_unslash( $settings['heading_text'] ) ),
			'heading_icon_url'    => esc_url_raw( wp_unslash( $settings['heading_icon_url'] ) ),
			'subtitle'            => sanitize_text_field( wp_unslash( $settings['subtitle'] ) ),
			'max_products'        => max( 1, min( 24, absint( $settings['max_products'] ) ) ),
			'layout_mode'         => $legacy_layout_mode,
			'columns_desktop'     => $legacy_columns_desktop,
			'columns_tablet'      => $legacy_columns_tablet,
			'columns_mobile'      => $legacy_columns_mobile,
			'product_layout_mode'      => $product_layout_mode,
			'product_columns_desktop'  => $this->sanitize_column_count( $settings['product_columns_desktop'], $legacy_columns_desktop ),
			'product_columns_tablet'   => $this->sanitize_column_count( $settings['product_columns_tablet'], $legacy_columns_tablet ),
			'product_columns_mobile'   => $this->sanitize_column_count( $settings['product_columns_mobile'], $legacy_columns_mobile ),
			'product_desktop_layout_mode' => $this->sanitize_choice( $settings['product_desktop_layout_mode'], array( 'columns', 'rows', 'slider' ), $product_layout_mode ),
			'product_tablet_layout_mode'  => $this->sanitize_choice( $settings['product_tablet_layout_mode'], array( 'columns', 'rows', 'slider' ), $product_layout_mode ),
			'product_mobile_layout_mode'  => $this->sanitize_choice( $settings['product_mobile_layout_mode'], array( 'columns', 'rows', 'slider' ), $product_layout_mode ),
			'cart_layout_mode'         => $cart_layout_mode,
			'cart_columns_desktop'     => $this->sanitize_column_count( $settings['cart_columns_desktop'], $legacy_columns_desktop ),
			'cart_columns_tablet'      => $this->sanitize_column_count( $settings['cart_columns_tablet'], $legacy_columns_tablet ),
			'cart_columns_mobile'      => $this->sanitize_column_count( $settings['cart_columns_mobile'], $legacy_columns_mobile ),
			'cart_desktop_layout_mode' => $this->sanitize_choice( $settings['cart_desktop_layout_mode'], array( 'columns', 'rows', 'slider' ), $cart_layout_mode ),
			'cart_tablet_layout_mode'  => $this->sanitize_choice( $settings['cart_tablet_layout_mode'], array( 'columns', 'rows', 'slider' ), $cart_layout_mode ),
			'cart_mobile_layout_mode'  => $this->sanitize_choice( $settings['cart_mobile_layout_mode'], array( 'columns', 'rows', 'slider' ), $cart_layout_mode ),
			'checkout_layout_mode'     => $checkout_layout_mode,
			'checkout_columns_desktop' => $this->sanitize_column_count( $settings['checkout_columns_desktop'], $defaults['checkout_columns_desktop'] ),
			'checkout_columns_tablet'  => $this->sanitize_column_count( $settings['checkout_columns_tablet'], $defaults['checkout_columns_tablet'] ),
			'checkout_columns_mobile'  => $this->sanitize_column_count( $settings['checkout_columns_mobile'], $defaults['checkout_columns_mobile'] ),
			'checkout_desktop_layout_mode' => $this->sanitize_choice( $settings['checkout_desktop_layout_mode'], array( 'columns', 'rows', 'slider' ), $checkout_layout_mode ),
			'checkout_tablet_layout_mode'  => $this->sanitize_choice( $settings['checkout_tablet_layout_mode'], array( 'columns', 'rows', 'slider' ), $checkout_layout_mode ),
			'checkout_mobile_layout_mode'  => $this->sanitize_choice( $settings['checkout_mobile_layout_mode'], array( 'columns', 'rows', 'slider' ), $checkout_layout_mode ),
			'show_out_of_stock'   => ! empty( $settings['show_out_of_stock'] ),
			'show_price'          => ! empty( $settings['show_price'] ),
			'show_add_button'     => ! empty( $settings['show_add_button'] ),
			'add_button_style'    => $this->sanitize_choice( $settings['add_button_style'], array( 'icon_plus', 'text', 'custom_icon' ), $defaults['add_button_style'] ),
			'add_button_icon_url' => esc_url_raw( wp_unslash( $settings['add_button_icon_url'] ) ),
			'product_order_by'    => $this->sanitize_choice( $settings['product_order_by'], array( 'default', 'name_asc', 'price_asc', 'price_desc', 'random' ), $defaults['product_order_by'] ),
			'custom_css_class'    => $this->sanitize_html_classes( $settings['custom_css_class'] ),
			'enable_animation'    => ! empty( $settings['enable_animation'] ),
			'primary_color'       => $primary_color ?: $defaults['primary_color'],
		);
	}

	/**
	 * Sanitize a select choice.
	 *
	 * @param string $value Value.
	 * @param array  $allowed Allowed values.
	 * @param string $default Default value.
	 * @return string
	 */
	private function sanitize_choice( $value, array $allowed, $default ) {
		$value = sanitize_key( wp_unslash( $value ) );

		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Sanitize column count.
	 *
	 * @param int $value Value.
	 * @param int $default Default value.
	 * @return int
	 */
	private function sanitize_column_count( $value, $default ) {
		$value = absint( $value );

		if ( $value < 1 || $value > 6 ) {
			return absint( $default );
		}

		return $value;
	}

	/**
	 * Sanitize a whitespace-separated CSS class list.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function sanitize_html_classes( $value ) {
		$classes = preg_split( '/\s+/', (string) wp_unslash( $value ) );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
		$classes = array_diff( $classes, array( 'crw-recommendations' ) );

		return implode( ' ', array_values( array_unique( $classes ) ) );
	}
}
