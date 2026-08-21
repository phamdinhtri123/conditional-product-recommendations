<?php
/**
 * GitHub release updater.
 *
 * @package Conditional_Product_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires Plugin Update Checker to a GitHub repository.
 */
class CRW_Github_Updater {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$repository_url = $this->get_repository_url();

		if ( '' === $repository_url ) {
			return;
		}

		$library = CRW_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

		if ( ! file_exists( $library ) ) {
			add_action( 'admin_notices', array( $this, 'missing_library_notice' ) );
			return;
		}

		require_once $library;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_library_notice' ) );
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			$repository_url,
			CRW_PLUGIN_FILE,
			$this->get_slug()
		);

		$branch = $this->get_branch();
		if ( '' !== $branch && method_exists( $update_checker, 'setBranch' ) ) {
			$update_checker->setBranch( $branch );
		}

		$token = $this->get_access_token();
		if ( '' !== $token && method_exists( $update_checker, 'setAuthentication' ) ) {
			$update_checker->setAuthentication( $token );
		}

		if ( method_exists( $update_checker, 'addFilter' ) ) {
			$update_checker->addFilter( 'pre_inject_update', array( $this, 'prefer_codeload_download_url' ) );
		}
	}

	/**
	 * Use GitHub's direct archive endpoint instead of the API zipball URL.
	 *
	 * @param object|null $update Update metadata.
	 * @return object|null
	 */
	public function prefer_codeload_download_url( $update ) {
		if ( empty( $update->download_url ) ) {
			return $update;
		}

		$parts = wp_parse_url( $update->download_url );
		if (
			empty( $parts['host'] )
			|| 'api.github.com' !== strtolower( $parts['host'] )
			|| empty( $parts['path'] )
		) {
			return $update;
		}

		if ( ! preg_match( '#^/repos/([^/]+)/([^/]+)/zipball/(.+)$#', $parts['path'], $matches ) ) {
			return $update;
		}

		$owner = rawurldecode( $matches[1] );
		$repo  = rawurldecode( $matches[2] );
		$ref   = rawurldecode( $matches[3] );

		$update->download_url = sprintf(
			'https://codeload.github.com/%1$s/%2$s/zip/refs/tags/%3$s',
			rawurlencode( $owner ),
			rawurlencode( $repo ),
			rawurlencode( $ref )
		);

		return $update;
	}

	/**
	 * Notice when the bundled PUC library is missing.
	 *
	 * @return void
	 */
	public function missing_library_notice() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Conditional Product Recommendations updater is configured, but the Plugin Update Checker library is missing.', 'conditional-product-recommendations' );
		echo '</p></div>';
	}

	/**
	 * Get GitHub repository URL.
	 *
	 * @return string
	 */
	private function get_repository_url() {
		$url = defined( 'CRW_GITHUB_REPOSITORY_URL' ) ? CRW_GITHUB_REPOSITORY_URL : '';

		return esc_url_raw( apply_filters( 'crw_github_repository_url', $url ) );
	}

	/**
	 * Get optional GitHub token. Public repositories do not need this.
	 *
	 * @return string
	 */
	private function get_access_token() {
		$token = defined( 'CRW_GITHUB_ACCESS_TOKEN' ) ? CRW_GITHUB_ACCESS_TOKEN : '';

		return sanitize_text_field( apply_filters( 'crw_github_access_token', $token ) );
	}

	/**
	 * Optional stable branch.
	 *
	 * @return string
	 */
	private function get_branch() {
		$branch = defined( 'CRW_GITHUB_BRANCH' ) ? CRW_GITHUB_BRANCH : '';

		return sanitize_text_field( apply_filters( 'crw_github_branch', $branch ) );
	}

	/**
	 * Plugin slug should match the plugin folder name.
	 *
	 * @return string
	 */
	private function get_slug() {
		return dirname( CRW_PLUGIN_BASENAME );
	}
}
