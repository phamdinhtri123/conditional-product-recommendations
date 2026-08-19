<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package Conditional_Product_Recommendations
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$rules = get_posts(
	array(
		'post_type'      => 'crw_recommendation',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $rules as $rule_id ) {
	wp_delete_post( $rule_id, true );
}
