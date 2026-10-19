<?php
/**
 * Register the mcwccn_notice CPT, its admin menu placement under WooCommerce,
 * and the custom list-table columns that let the admin see each banner's
 * status at a glance.
 *
 * @package Mad_Cow_Checkout_Notices_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MCWCCN_CPT
 */
class MCWCCN_CPT {

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'move_menu_under_woocommerce' ) );

		add_filter( 'manage_' . MCWCCN_POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . MCWCCN_POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
		add_filter( 'manage_edit-' . MCWCCN_POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
	}

	/**
	 * Register the CPT. Menu is set to false so we can place it manually under
	 * WooCommerce → MCW Checkout Notices via move_menu_under_woocommerce().
	 */
	public function register_post_type() {
		$labels = array(
			// The list screen's heading. Full plugin name, matching the other Mad Cow plugin screens.
			'name'               => __( 'Mad Cow Checkout Notices for WooCommerce', 'mad-cow-checkout-notices-for-woocommerce' ),
			'singular_name'      => __( 'Checkout Notice', 'mad-cow-checkout-notices-for-woocommerce' ),
			'menu_name'          => __( 'MCW Checkout Notices', 'mad-cow-checkout-notices-for-woocommerce' ),
			'add_new'            => __( 'Add Notice', 'mad-cow-checkout-notices-for-woocommerce' ),
			'add_new_item'       => __( 'Add New Checkout Notice', 'mad-cow-checkout-notices-for-woocommerce' ),
			'edit_item'          => __( 'Edit Checkout Notice', 'mad-cow-checkout-notices-for-woocommerce' ),
			'new_item'           => __( 'New Checkout Notice', 'mad-cow-checkout-notices-for-woocommerce' ),
			'view_item'          => __( 'View Checkout Notice', 'mad-cow-checkout-notices-for-woocommerce' ),
			'search_items'       => __( 'Search Checkout Notices', 'mad-cow-checkout-notices-for-woocommerce' ),
			'not_found'          => __( 'No notices found.', 'mad-cow-checkout-notices-for-woocommerce' ),
			'not_found_in_trash' => __( 'No notices found in Trash.', 'mad-cow-checkout-notices-for-woocommerce' ),
			'all_items'          => __( 'MCW Checkout Notices', 'mad-cow-checkout-notices-for-woocommerce' ),
		);

		register_post_type(
			MCWCCN_POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				/*
				 * Only PRIMITIVE capabilities are remapped here. The three meta
				 * capabilities - edit_post, read_post, delete_post - are
				 * deliberately absent, and must stay absent.
				 *
				 * When map_meta_cap is true, register_post_type() passes this
				 * array to _post_type_meta_capabilities(), which builds the
				 * global $post_type_meta_caps lookup keyed by the VALUE of each
				 * meta capability. Naming a shared primitive such as
				 * manage_woocommerce as the value of edit_post/read_post/
				 * delete_post therefore registers manage_woocommerce as a meta
				 * capability site-wide. Every later current_user_can(
				 * 'manage_woocommerce' ) call - including WooCommerce's own -
				 * then falls into map_meta_cap()'s default branch, is rewritten
				 * to delete_post with no post ID, and resolves to do_not_allow.
				 *
				 * With only primitives listed, core maps the meta capabilities
				 * down to them correctly and manage_woocommerce is left alone.
				 */
				'capabilities'        => array(
					'edit_posts'             => 'manage_woocommerce',
					'edit_others_posts'      => 'manage_woocommerce',
					'edit_published_posts'   => 'manage_woocommerce',
					'edit_private_posts'     => 'manage_woocommerce',
					'publish_posts'          => 'manage_woocommerce',
					'read_private_posts'     => 'manage_woocommerce',
					'delete_posts'           => 'manage_woocommerce',
					'delete_others_posts'    => 'manage_woocommerce',
					'delete_published_posts' => 'manage_woocommerce',
					'delete_private_posts'   => 'manage_woocommerce',
				),
				'hierarchical'        => false,
				'supports'            => array( 'title', 'page-attributes' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'exclude_from_search' => true,
				'menu_icon'           => 'dashicons-megaphone',
			)
		);
	}

	/**
	 * Add the CPT list screen under the WooCommerce menu.
	 */
	public function move_menu_under_woocommerce() {
		add_submenu_page(
			'woocommerce',
			__( 'Mad Cow Checkout Notices for WooCommerce', 'mad-cow-checkout-notices-for-woocommerce' ),
			__( 'MCW Checkout Notices', 'mad-cow-checkout-notices-for-woocommerce' ),
			'manage_woocommerce',
			'edit.php?post_type=' . MCWCCN_POST_TYPE
		);
	}

	/**
	 * Replace the default columns on the CPT list table with useful summary
	 * columns (enabled state, meta key, targeting, date window, order).
	 *
	 * @param array $columns Original columns.
	 * @return array
	 */
	public function admin_columns( $columns ) {
		return array(
			'cb'                => $columns['cb'] ?? '<input type="checkbox" />',
			'title'             => __( 'Notice', 'mad-cow-checkout-notices-for-woocommerce' ),
			'mcwccn_enabled'    => __( 'Enabled', 'mad-cow-checkout-notices-for-woocommerce' ),
			'mcwccn_question'   => __( 'Question', 'mad-cow-checkout-notices-for-woocommerce' ),
			'mcwccn_targeting'  => __( 'Targeting', 'mad-cow-checkout-notices-for-woocommerce' ),
			'mcwccn_dates'      => __( 'Date Window', 'mad-cow-checkout-notices-for-woocommerce' ),
			'mcwccn_menu_order' => __( 'Order', 'mad-cow-checkout-notices-for-woocommerce' ),
			'date'              => $columns['date'] ?? __( 'Date', 'mad-cow-checkout-notices-for-woocommerce' ),
		);
	}

	/**
	 * Render a single custom column for a given notice row.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_admin_column( $column, $post_id ) {
		$config = mcwccn_get_notice_config( $post_id );

		switch ( $column ) {
			case 'mcwccn_enabled':
				if ( ! empty( $config['enabled'] ) ) {
					echo '<span style="color:#008a20;font-weight:600;">&#9679; ' . esc_html__( 'On', 'mad-cow-checkout-notices-for-woocommerce' ) . '</span>';
				} else {
					echo '<span style="color:#8c8f94;">&#9675; ' . esc_html__( 'Off', 'mad-cow-checkout-notices-for-woocommerce' ) . '</span>';
				}
				break;

			case 'mcwccn_question':
				if ( empty( $config['question_enabled'] ) ) {
					echo '<span style="color:#8c8f94;">&mdash;</span>';
					break;
				}
				$type_label = 'radio' === $config['question_type']
					? __( 'Radio', 'mad-cow-checkout-notices-for-woocommerce' )
					: __( 'Dropdown', 'mad-cow-checkout-notices-for-woocommerce' );
				echo esc_html( $type_label );
				break;

			case 'mcwccn_targeting':
				$parts = array();
				if ( ! empty( $config['include_cats'] ) ) {
					$parts[] = sprintf(
						/* translators: %d: number of product categories */
						_n( '%d category', '%d categories', count( $config['include_cats'] ), 'mad-cow-checkout-notices-for-woocommerce' ),
						count( $config['include_cats'] )
					);
				}
				if ( ! empty( $config['include_ids'] ) ) {
					$parts[] = sprintf(
						/* translators: %d: number of product IDs */
						_n( '%d product', '%d products', count( $config['include_ids'] ), 'mad-cow-checkout-notices-for-woocommerce' ),
						count( $config['include_ids'] )
					);
				}
				if ( empty( $parts ) ) {
					// Muted, like the Date Window column's "Always": an unrestricted
					// notice should read as the quiet default, not as a setting.
					echo '<span style="color:#8c8f94;">' . esc_html__( 'All checkouts', 'mad-cow-checkout-notices-for-woocommerce' ) . '</span>';
					break;
				}

				echo esc_html( implode( ', ', $parts ) );
				break;

			case 'mcwccn_dates':
				$start = $config['start_date'] ?: '';
				$end   = $config['end_date'] ?: '';
				if ( '' === $start && '' === $end ) {
					echo '<span style="color:#8c8f94;">' . esc_html__( 'Always', 'mad-cow-checkout-notices-for-woocommerce' ) . '</span>';
				} else {
					echo esc_html( ( $start ?: '…' ) . ' → ' . ( $end ?: '…' ) );
				}
				break;

			case 'mcwccn_menu_order':
				$post = get_post( $post_id );
				echo (int) $post->menu_order;
				break;
		}
	}

	/**
	 * Make Order + Enabled sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function sortable_columns( $columns ) {
		$columns['mcwccn_menu_order'] = 'menu_order';
		return $columns;
	}
}
