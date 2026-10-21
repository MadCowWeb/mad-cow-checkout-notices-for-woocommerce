<?php
/**
 * Drag-and-drop ordering on the notice list table.
 *
 * Checkout renders notices by menu_order ascending, then date ascending (see
 * mcwccn_get_notices()). This class lets an admin set that order by dragging
 * rows on the list screen instead of typing numbers into Page Attributes.
 *
 * Three jobs:
 *   1. Make the list open in checkout order rather than newest-first.
 *   2. Load the drag script, only when the list is actually in checkout order.
 *   3. Save a dragged order over admin-ajax.php.
 *
 * @package Mad_Cow_Checkout_Notices_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MCWCCN_List_Sort
 */
class MCWCCN_List_Sort {

	const AJAX_ACTION  = 'mcwccn_save_order';
	const NONCE_ACTION = 'mcwccn_save_order';

	public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'default_list_order' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_save_order' ) );
	}

	/**
	 * Open the notice list in checkout order, and break ties the same way the
	 * checkout does so what the admin sees is what customers get.
	 *
	 * Only touches the main list-table query for this post type, and only when
	 * no orderby was asked for or the Order column header was clicked. Any other
	 * column sort, and the Draft/Pending views (which core sorts by modified
	 * date), are left exactly as core built them.
	 *
	 * @param WP_Query $query The query being built.
	 */
	public function default_list_order( $query ) {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
			return;
		}
		if ( MCWCCN_POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		$order   = 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC';

		if ( empty( $orderby ) ) {
			// No explicit sort: core defaults the direction to DESC, which would
			// show the checkout order backwards.
			$orderby = 'menu_order';
			$order   = 'ASC';
		}

		if ( 'menu_order' !== $orderby ) {
			return;
		}

		$query->set(
			'orderby',
			array(
				'menu_order' => $order,
				'date'       => $order,
				'ID'         => $order,
			)
		);
	}

	/**
	 * Whether the list currently on screen is in checkout order, which is the
	 * only state where dragging a row means something.
	 *
	 * @return bool
	 */
	private function list_is_in_checkout_order() {
		global $wp_query;

		if ( ! $wp_query instanceof WP_Query ) {
			return false;
		}
		if ( 'trash' === $wp_query->get( 'post_status' ) ) {
			return false;
		}

		$orderby = $wp_query->get( 'orderby' );

		return is_array( $orderby )
			&& 'menu_order' === key( $orderby )
			&& 'ASC' === strtoupper( (string) current( $orderby ) );
	}

	/**
	 * Load the drag script and styles on the notice list screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || MCWCCN_POST_TYPE !== $screen->post_type ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $this->list_is_in_checkout_order() ) {
			return;
		}

		wp_enqueue_style(
			'mcwccn-list-sort',
			MCWCCN_URL . 'assets/list-sort.css',
			array(),
			$this->asset_version( 'assets/list-sort.css' )
		);

		wp_enqueue_script(
			'mcwccn-list-sort',
			MCWCCN_URL . 'assets/list-sort.js',
			array( 'wp-a11y' ),
			$this->asset_version( 'assets/list-sort.js' ),
			true
		);

		wp_localize_script(
			'mcwccn-list-sort',
			'mcwccnListSort',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'handleLabel' => __( 'Reorder notice', 'mad-cow-checkout-notices-for-woocommerce' ),
					'handleTitle' => __( 'Drag to reorder, or focus and use the up and down arrow keys', 'mad-cow-checkout-notices-for-woocommerce' ),
					/* translators: 1: new position, 2: total notices on this screen */
					'moved'       => __( 'Moved to position %1$s of %2$s.', 'mad-cow-checkout-notices-for-woocommerce' ),
					'saved'       => __( 'Notice order saved.', 'mad-cow-checkout-notices-for-woocommerce' ),
					'failed'      => __( 'The new order could not be saved, so the list has been put back. Please try again.', 'mad-cow-checkout-notices-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Cache-busting version for a bundled asset: the file's own mtime.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string|int
	 */
	private function asset_version( $relative ) {
		$path = MCWCCN_DIR . $relative;
		return file_exists( $path ) ? filemtime( $path ) : MCWCCN_VERSION;
	}

	/**
	 * Every notice in checkout order, capped at the same limit the checkout
	 * itself applies. Ties on menu_order and date fall back to ID so the result
	 * is deterministic.
	 *
	 * @return WP_Post[]
	 */
	private function get_ordered_notices() {
		$query = new WP_Query(
			array(
				'post_type'              => MCWCCN_POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => MCWCCN_MAX_NOTICES,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'date'       => 'ASC',
					'ID'         => 'ASC',
				),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $query->posts;
	}

	/**
	 * AJAX: save a dragged order.
	 *
	 * The browser sends only the rows on the current list page, in their new
	 * order. Those rows are slotted back into the positions they already
	 * occupied among ALL notices, so reordering page 2 never disturbs page 1,
	 * and a filtered or searched view reorders correctly too. The full sequence
	 * is then renumbered 1..n and only the rows whose number changed are written.
	 */
	public function ajax_save_order() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to reorder notices.', 'mad-cow-checkout-notices-for-woocommerce' ) ),
				403
			);
		}

		$raw    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$posted = array_values( array_unique( array_filter( $raw ) ) );

		$notices = $this->get_ordered_notices();
		$all     = wp_list_pluck( $notices, 'ID' );

		// Ignore anything that is not one of our notices.
		$posted  = array_values( array_intersect( $posted, $all ) );
		$current = wp_list_pluck( $notices, 'menu_order', 'ID' );

		if ( count( $posted ) > 1 ) {
			$slots = array();
			foreach ( $all as $position => $id ) {
				if ( in_array( $id, $posted, true ) ) {
					$slots[] = $position;
				}
			}

			$sequence = $all;
			foreach ( $slots as $k => $position ) {
				$sequence[ $position ] = $posted[ $k ];
			}

			foreach ( $sequence as $index => $id ) {
				$wanted = $index + 1;
				if ( (int) $current[ $id ] === $wanted ) {
					continue;
				}

				$result = wp_update_post(
					array(
						'ID'         => $id,
						'menu_order' => $wanted,
					),
					true
				);

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
				}

				$current[ $id ] = $wanted;
			}
		}

		wp_send_json_success( array( 'orders' => array_map( 'intval', $current ) ) );
	}
}
