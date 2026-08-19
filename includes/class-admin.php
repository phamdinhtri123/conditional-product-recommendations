<?php
/**
 * Admin screens.
 *
 * @package Conditional_Product_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin rule management.
 */
class CRW_Admin {
	/**
	 * Repository.
	 *
	 * @var CRW_Rule_Repository
	 */
	private $repository;

	/**
	 * Page hook.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Constructor.
	 *
	 * @param CRW_Rule_Repository $repository Repository.
	 */
	public function __construct( CRW_Rule_Repository $repository ) {
		$this->repository = $repository;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->page_hook = add_submenu_page(
			'woocommerce',
			__( 'Product Recommendations', 'conditional-product-recommendations' ),
			__( 'Product Recommendations', 'conditional-product-recommendations' ),
			'manage_woocommerce',
			'crw-product-recommendations',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'crw-admin', CRW_PLUGIN_URL . 'assets/css/admin.css', array(), CRW_VERSION );
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'crw-admin', CRW_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'selectWoo', 'wc-enhanced-select' ), CRW_VERSION, true );
	}

	/**
	 * Handle CRUD actions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( empty( $_REQUEST['page'] ) || 'crw-product-recommendations' !== sanitize_key( wp_unslash( $_REQUEST['page'] ) ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$action = isset( $_REQUEST['crw_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['crw_action'] ) ) : '';

		if ( 'save' === $action && isset( $_POST['crw_rule_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crw_rule_nonce'] ) ), 'crw_save_rule' ) ) {
			$result = $this->repository->save_rule( $_POST );
			$args   = array( 'page' => 'crw-product-recommendations' );

			if ( is_wp_error( $result ) ) {
				$args['crw_error'] = rawurlencode( $result->get_error_message() );
			} else {
				$args['crw_saved'] = 1;
			}

			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_GET['rule_id'], $_GET['_wpnonce'] ) ) {
			$rule_id = absint( $_GET['rule_id'] );
			$nonce   = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

			if ( 'delete' === $action && wp_verify_nonce( $nonce, 'crw_delete_rule_' . $rule_id ) ) {
				$this->repository->delete_rule( $rule_id );
				wp_safe_redirect( add_query_arg( array( 'page' => 'crw-product-recommendations', 'crw_deleted' => 1 ), admin_url( 'admin.php' ) ) );
				exit;
			}

			if ( 'toggle' === $action && wp_verify_nonce( $nonce, 'crw_toggle_rule_' . $rule_id ) ) {
				$this->repository->toggle_rule( $rule_id );
				wp_safe_redirect( add_query_arg( array( 'page' => 'crw-product-recommendations', 'crw_toggled' => 1 ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage recommendations.', 'conditional-product-recommendations' ) );
		}

		$editing = isset( $_GET['crw_action'], $_GET['rule_id'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['crw_action'] ) );
		$rule    = $editing ? $this->repository->get_rule( absint( $_GET['rule_id'] ) ) : null;

		echo '<div class="wrap crw-admin-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Product Recommendations', 'conditional-product-recommendations' ) . '</h1> ';
		echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'crw-product-recommendations', 'crw_action' => 'new' ), admin_url( 'admin.php' ) ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'conditional-product-recommendations' ) . '</a>';

		$this->render_notices();

		if ( $editing || ( isset( $_GET['crw_action'] ) && 'new' === sanitize_key( wp_unslash( $_GET['crw_action'] ) ) ) ) {
			$this->render_form( $rule );
		} else {
			$this->render_list();
		}

		echo '</div>';
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	private function render_notices() {
		if ( ! empty( $_GET['crw_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule saved.', 'conditional-product-recommendations' ) . '</p></div>';
		}

		if ( ! empty( $_GET['crw_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule deleted.', 'conditional-product-recommendations' ) . '</p></div>';
		}

		if ( ! empty( $_GET['crw_toggled'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule status updated.', 'conditional-product-recommendations' ) . '</p></div>';
		}

		if ( ! empty( $_GET['crw_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['crw_error'] ) ) ) . '</p></div>';
		}
	}

	/**
	 * Render rule list.
	 *
	 * @return void
	 */
	private function render_list() {
		$rules = $this->repository->get_rules();

		echo '<table class="widefat striped crw-rules-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Rule Name', 'conditional-product-recommendations' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'conditional-product-recommendations' ) . '</th>';
		echo '<th>' . esc_html__( 'Locations', 'conditional-product-recommendations' ) . '</th>';
		echo '<th>' . esc_html__( 'Products to Display', 'conditional-product-recommendations' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'conditional-product-recommendations' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $rules ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No recommendation rules yet.', 'conditional-product-recommendations' ) . '</td></tr>';
		}

		foreach ( $rules as $rule ) {
			$edit_url = add_query_arg(
				array(
					'page'       => 'crw-product-recommendations',
					'crw_action' => 'edit',
					'rule_id'    => $rule['id'],
				),
				admin_url( 'admin.php' )
			);
			$toggle_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'       => 'crw-product-recommendations',
						'crw_action' => 'toggle',
						'rule_id'    => $rule['id'],
					),
					admin_url( 'admin.php' )
				),
				'crw_toggle_rule_' . $rule['id']
			);
			$delete_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'       => 'crw-product-recommendations',
						'crw_action' => 'delete',
						'rule_id'    => $rule['id'],
					),
					admin_url( 'admin.php' )
				),
				'crw_delete_rule_' . $rule['id']
			);

			echo '<tr>';
			echo '<td><strong>' . esc_html( $rule['name'] ) . '</strong></td>';
			echo '<td>' . esc_html( $rule['enabled'] ? __( 'Enabled', 'conditional-product-recommendations' ) : __( 'Disabled', 'conditional-product-recommendations' ) ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', $rule['display_locations'] ) ) . '</td>';
			echo '<td>' . esc_html( count( $rule['display_products'] ) ) . '</td>';
			echo '<td>';
			echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'conditional-product-recommendations' ) . '</a> | ';
			echo '<a href="' . esc_url( $toggle_url ) . '">' . esc_html( $rule['enabled'] ? __( 'Disable', 'conditional-product-recommendations' ) : __( 'Enable', 'conditional-product-recommendations' ) ) . '</a> | ';
			echo '<a class="submitdelete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this rule?', 'conditional-product-recommendations' ) ) . '\');">' . esc_html__( 'Delete', 'conditional-product-recommendations' ) . '</a>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render rule form.
	 *
	 * @param array|null $rule Rule.
	 * @return void
	 */
	private function render_form( $rule = null ) {
		$rule = wp_parse_args(
			(array) $rule,
			array(
				'id'                 => 0,
				'name'               => '',
				'enabled'            => true,
				'display_products'   => array(),
				'display_locations'  => array( 'product', 'cart', 'checkout' ),
			)
		);

		echo '<form method="post" class="crw-rule-form">';
		wp_nonce_field( 'crw_save_rule', 'crw_rule_nonce' );
		echo '<input type="hidden" name="crw_action" value="save">';
		echo '<input type="hidden" name="rule_id" value="' . esc_attr( $rule['id'] ) . '">';

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="crw-rule-name">' . esc_html__( 'Rule Name', 'conditional-product-recommendations' ) . '</label></th><td>';
		echo '<input id="crw-rule-name" class="regular-text" type="text" name="rule_name" value="' . esc_attr( $rule['name'] ) . '" required>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Enabled', 'conditional-product-recommendations' ) . '</th><td>';
		echo '<label><input type="checkbox" name="rule_enabled" value="1" ' . checked( $rule['enabled'], true, false ) . '> ' . esc_html__( 'Enable this rule', 'conditional-product-recommendations' ) . '</label>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="crw-display-products">' . esc_html__( 'Products to Display', 'conditional-product-recommendations' ) . '</label></th><td>';
		$this->render_product_select( 'display_products[]', 'crw-display-products', $rule['display_products'] );
		echo '<p class="description">' . esc_html__( 'Products already in the cart are automatically hidden from the recommendation section.', 'conditional-product-recommendations' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Display Locations', 'conditional-product-recommendations' ) . '</th><td class="crw-location-field">';
		$this->render_location_checkbox( 'product', __( 'Product Page', 'conditional-product-recommendations' ), $rule['display_locations'] );
		$this->render_location_checkbox( 'cart', __( 'Cart', 'conditional-product-recommendations' ), $rule['display_locations'] );
		$this->render_location_checkbox( 'checkout', __( 'Checkout', 'conditional-product-recommendations' ), $rule['display_locations'] );
		echo '</td></tr>';

		echo '</tbody></table>';

		submit_button( __( 'Save Rule', 'conditional-product-recommendations' ) );
		echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'crw-product-recommendations' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Cancel', 'conditional-product-recommendations' ) . '</a>';
		echo '</form>';
	}

	/**
	 * Render WooCommerce AJAX product select.
	 *
	 * @param string $name Field name.
	 * @param string $id Field ID.
	 * @param array  $selected_ids Selected IDs.
	 * @return void
	 */
	private function render_product_select( $name, $id, array $selected_ids ) {
		echo '<select id="' . esc_attr( $id ) . '" class="wc-product-search crw-product-search" multiple="multiple" name="' . esc_attr( $name ) . '" data-placeholder="' . esc_attr__( 'Search for a product...', 'conditional-product-recommendations' ) . '" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">';

		foreach ( $selected_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			echo '<option value="' . esc_attr( $product_id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
		}

		echo '</select>';
	}

	/**
	 * Render location checkbox.
	 *
	 * @param string $value Value.
	 * @param string $label Label.
	 * @param array  $selected Selected values.
	 * @return void
	 */
	private function render_location_checkbox( $value, $label, array $selected ) {
		echo '<label><input type="checkbox" name="display_locations[]" value="' . esc_attr( $value ) . '" ' . checked( in_array( $value, $selected, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
}
