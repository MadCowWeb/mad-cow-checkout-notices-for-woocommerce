<?php
/**
 * Uninstall handler. Fires when the user clicks "Delete" on the plugin in
 * wp-admin. Removes every notice this plugin created, along with its post meta.
 *
 * Order meta is NOT removed. The admin chooses those meta keys themselves and
 * the values belong to the order record, so deleting the plugin must never
 * rewrite order history.
 *
 * @package Mad_Cow_Checkout_Notices_For_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$mcwccn_statuses = array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' );

do {
	$mcwccn_notices = get_posts(
		array(
			'post_type'              => 'mcwccn_notice',
			'post_status'            => $mcwccn_statuses,
			'numberposts'            => 100,
			'fields'                 => 'ids',
			'suppress_filters'       => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $mcwccn_notices as $mcwccn_notice_id ) {
		wp_delete_post( (int) $mcwccn_notice_id, true );
	}
} while ( count( $mcwccn_notices ) === 100 );
