<?php
/**
 * Notice editor: meta boxes on the mcwccn_notice edit screen and the save handler
 * that writes every post_meta value the frontend reads.
 *
 * Meta boxes are split by responsibility so each is small and focused:
 *   - Display     → enabled toggle, heading, message, icon toggle
 *   - Targeting   → start/end dates, include categories, include product IDs
 *   - Question    → optional radio/dropdown question rendered at checkout
 *
 * @package Mad_Cow_Checkout_Notices_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MCWCCN_Editor
 */
class MCWCCN_Editor {

	public function __construct() {
		add_action( 'add_meta_boxes_' . MCWCCN_POST_TYPE, array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . MCWCCN_POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue editor CSS + JS only on the notice edit screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_editor_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || MCWCCN_POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'mcwccn-admin',
			MCWCCN_URL . 'assets/admin.css',
			array(),
			$this->asset_version( 'assets/admin.css' )
		);

		/*
		 * jquery-ui-sortable ships with WordPress and is what core uses for
		 * widget and menu ordering, so nothing extra is distributed with this
		 * plugin. It is the only jQuery dependency here, it loads on this one
		 * admin screen, and admin.js degrades to a plain list without it.
		 */
		wp_enqueue_script(
			'mcwccn-admin',
			MCWCCN_URL . 'assets/admin.js',
			array( 'jquery-ui-sortable' ),
			$this->asset_version( 'assets/admin.js' ),
			true
		);
	}

	/**
	 * Cache-busting version for a bundled asset: the file's own mtime, so an
	 * edited asset is picked up without bumping the plugin version. Falls back
	 * to the plugin version if the file is unreadable.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string|int
	 */
	private function asset_version( $relative ) {
		$path = MCWCCN_DIR . $relative;
		return file_exists( $path ) ? filemtime( $path ) : MCWCCN_VERSION;
	}

	/**
	 * Register all editor meta boxes.
	 */
	public function register_meta_boxes() {
		/*
		 * Registration order decides the on-screen order. do_meta_boxes() walks
		 * priorities in turn and, within one priority, renders boxes in the order
		 * they were added - so Notice Appearance is registered first and both
		 * boxes share the 'high' priority to keep it at the top of the page.
		 */
		add_meta_box(
			'mcwccn_appearance',
			__( 'Notice Appearance', 'mad-cow-checkout-notices-for-woocommerce' ),
			array( $this, 'render_appearance_box' ),
			MCWCCN_POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'mcwccn_display',
			__( 'Notice Content', 'mad-cow-checkout-notices-for-woocommerce' ),
			array( $this, 'render_display_box' ),
			MCWCCN_POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'mcwccn_targeting',
			__( 'When to Show', 'mad-cow-checkout-notices-for-woocommerce' ),
			array( $this, 'render_targeting_box' ),
			MCWCCN_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'mcwccn_question',
			__( 'Customer Question', 'mad-cow-checkout-notices-for-woocommerce' ),
			array( $this, 'render_question_box' ),
			MCWCCN_POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * "Notice Content" meta box: enabled, heading, message, icon toggle.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_display_box( $post ) {
		wp_nonce_field( 'mcwccn_save_meta', 'mcwccn_nonce' );
		$config  = mcwccn_get_notice_config( $post->ID );
		$weights = mcwccn_heading_weights();

		$weight_labels = array(
			'400' => __( 'Normal (400)', 'mad-cow-checkout-notices-for-woocommerce' ),
			'500' => __( 'Medium (500)', 'mad-cow-checkout-notices-for-woocommerce' ),
			'600' => __( 'Semi-bold (600)', 'mad-cow-checkout-notices-for-woocommerce' ),
			'700' => __( 'Bold (700)', 'mad-cow-checkout-notices-for-woocommerce' ),
			'800' => __( 'Extra-bold (800)', 'mad-cow-checkout-notices-for-woocommerce' ),
		);
		?>
		<table class="form-table mcwccn-form">
			<tr>
				<th scope="row"><label for="mcwccn_enabled"><?php esc_html_e( 'Enabled', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="mcwccn_enabled" name="mcwccn_enabled" value="1" <?php checked( 1, (int) $config['enabled'] ); ?>>
						<?php esc_html_e( 'Show this notice at checkout.', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Master switch. Independent of Draft/Publish status. When off, the notice is always hidden at checkout regardless of the settings below.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="mcwccn_heading"><?php esc_html_e( 'Heading', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
				<td>
					<input type="text" id="mcwccn_heading" name="mcwccn_heading" value="<?php echo esc_attr( $config['heading'] ); ?>" class="large-text">
					<p class="description"><?php esc_html_e( 'Shown above the message. Leave blank to hide it.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Heading Style', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
				<td>
					<label class="mcwccn-field-label"><?php esc_html_e( 'Size (px)', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
						<input type="number" min="8" max="72" name="mcwccn_heading_size" value="<?php echo esc_attr( (string) $config['heading_size'] ); ?>" class="small-text">
					</label>
					<label class="mcwccn-field-label"><?php esc_html_e( 'Weight', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
						<select name="mcwccn_heading_weight">
							<?php foreach ( $weights as $weight ) : ?>
								<option value="<?php echo esc_attr( $weight ); ?>" <?php selected( $weight, $config['heading_weight'] ); ?>>
									<?php echo esc_html( $weight_labels[ $weight ] ?? $weight ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="mcwccn-field-label"><?php esc_html_e( 'Colour', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
						<input type="color" name="mcwccn_heading_color" value="<?php echo esc_attr( $config['heading_color'] ); ?>">
					</label>
					<label class="mcwccn-inline-check">
						<input type="checkbox" name="mcwccn_heading_inherit" value="1" <?php checked( 1, (int) $config['heading_inherit'] ); ?>>
						<?php esc_html_e( 'Inherit colour from the theme', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Ignored when the Heading above is left blank.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Message', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
				<td>
					<?php
					/*
					 * The editor ID is deliberately letters-only. It becomes an
					 * HTML id and a TinyMCE instance key, and textarea_name keeps
					 * the POST key the save handler already expects.
					 */
					wp_editor(
						$config['message'],
						'mcwccnmessage',
						array(
							'textarea_name' => 'mcwccn_message',
							'textarea_rows' => 10,
							'media_buttons' => true,
							'teeny'         => false,
							'quicktags'     => true,
							/*
							 * No toolbar override, so TinyMCE keeps WordPress's own
							 * default rows. wordpress_adv_hidden opens the second row
							 * on load instead of hiding it behind the toolbar toggle,
							 * which is where the text colour control lives.
							 */
							'tinymce'       => array(
								'wordpress_adv_hidden' => false,
							),
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'Basic formatting is allowed. The notice container is added automatically.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Icon', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
				<td>
					<label>
						<input type="checkbox" id="mcwccn_show_icon" name="mcwccn_show_icon" value="1" <?php checked( 1, (int) $config['show_icon'] ); ?>>
						<?php esc_html_e( 'Prefix the message with an arrow icon.', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * "When to Show" meta box: date window and cart targeting.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_targeting_box( $post ) {
		$config = mcwccn_get_notice_config( $post->ID );
		$ids    = implode( ', ', array_filter( array_map( 'absint', (array) $config['include_ids'] ) ) );
		?>
		<table class="form-table mcwccn-form">
			<tr>
				<th scope="row"><label for="mcwccn_start_date"><?php esc_html_e( 'Start Date', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
				<td>
					<input type="date" id="mcwccn_start_date" name="mcwccn_start_date" value="<?php echo esc_attr( $config['start_date'] ); ?>" class="mcwccn-date">
					<p class="description"><?php esc_html_e( 'Optional. Notice begins showing on this date.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="mcwccn_end_date"><?php esc_html_e( 'End Date', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
				<td>
					<input type="date" id="mcwccn_end_date" name="mcwccn_end_date" value="<?php echo esc_attr( $config['end_date'] ); ?>" class="mcwccn-date">
					<p class="description"><?php esc_html_e( 'Optional. Notice stops showing after this date (inclusive). If the Question below has dated options, the notice also auto-hides once every date is in the past.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Include Categories', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
				<td>
					<?php mcwccn_render_category_grid( 'mcwccn_include_cats', (array) $config['include_cats'] ); ?>
					<p class="description"><?php esc_html_e( 'Optional. Notice will show when the cart contains any product in a checked category. If no categories AND no product IDs are chosen, the notice shows on every non-empty checkout page.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="mcwccn_include_ids"><?php esc_html_e( 'Include Product IDs', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
				<td>
					<input type="text" id="mcwccn_include_ids" name="mcwccn_include_ids" value="<?php echo esc_attr( $ids ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Optional. Comma-separated product IDs.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * "Appearance" meta box: the notice box's own background, border, corner
	 * radius, padding and margin. Every value is rendered as an inline style on
	 * the notice wrapper, so the stylesheet only carries layout.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_appearance_box( $post ) {
		$config = mcwccn_get_notice_config( $post->ID );
		$styles = mcwccn_border_styles();

		$style_labels = array(
			'solid'  => __( 'Solid', 'mad-cow-checkout-notices-for-woocommerce' ),
			'dashed' => __( 'Dashed', 'mad-cow-checkout-notices-for-woocommerce' ),
			'dotted' => __( 'Dotted', 'mad-cow-checkout-notices-for-woocommerce' ),
			'double' => __( 'Double', 'mad-cow-checkout-notices-for-woocommerce' ),
			'none'   => __( 'None', 'mad-cow-checkout-notices-for-woocommerce' ),
		);

		$box_sides = array(
			'top'    => __( 'Top', 'mad-cow-checkout-notices-for-woocommerce' ),
			'right'  => __( 'Right', 'mad-cow-checkout-notices-for-woocommerce' ),
			'bottom' => __( 'Bottom', 'mad-cow-checkout-notices-for-woocommerce' ),
			'left'   => __( 'Left', 'mad-cow-checkout-notices-for-woocommerce' ),
		);
		?>
		<table class="form-table mcwccn-form">
			<tr>
				<th scope="row"><?php esc_html_e( 'Background', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
				<td>
					<input type="color" id="mcwccn_bg_color" name="mcwccn_bg_color" value="<?php echo esc_attr( $config['bg_color'] ); ?>">
					<label class="mcwccn-inline-check">
						<input type="checkbox" name="mcwccn_bg_transparent" value="1" <?php checked( 1, (int) $config['bg_transparent'] ); ?>>
						<?php esc_html_e( 'Transparent (no background)', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Border', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
				<td>
					<label class="mcwccn-field-label"><?php esc_html_e( 'Width', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
						<input type="number" min="0" max="40" name="mcwccn_border_width" value="<?php echo esc_attr( (string) $config['border_width'] ); ?>" class="small-text">
					</label>
					<label class="mcwccn-field-label"><?php esc_html_e( 'Style', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
						<select name="mcwccn_border_style">
							<?php foreach ( $styles as $style ) : ?>
								<option value="<?php echo esc_attr( $style ); ?>" <?php selected( $style, $config['border_style'] ); ?>>
									<?php echo esc_html( $style_labels[ $style ] ?? $style ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="mcwccn-field-label"><?php esc_html_e( 'Colour', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
						<input type="color" name="mcwccn_border_color" value="<?php echo esc_attr( $config['border_color'] ); ?>">
					</label>
					<p class="description"><?php esc_html_e( 'Set width to 0, or style to None, to remove the border entirely.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="mcwccn_accent_width"><?php esc_html_e( 'Left Accent Bar', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
				<td>
					<input type="number" min="0" max="40" id="mcwccn_accent_width" name="mcwccn_accent_width" value="<?php echo esc_attr( (string) $config['accent_width'] ); ?>" class="small-text"> px
					<p class="description"><?php esc_html_e( 'Thickens the left border only, for the stripe-down-the-side look. Set to 0 for a uniform border. Uses the border colour and needs a border width above 0.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="mcwccn_border_radius"><?php esc_html_e( 'Corner Radius', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
				<td>
					<input type="number" min="0" max="100" id="mcwccn_border_radius" name="mcwccn_border_radius" value="<?php echo esc_attr( (string) $config['border_radius'] ); ?>" class="small-text"> px
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Padding (px)', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
				<td>
					<?php foreach ( $box_sides as $side => $label ) : ?>
						<label class="mcwccn-field-label"><?php echo esc_html( $label ); ?>
							<input type="number" min="0" max="200" name="mcwccn_padding_<?php echo esc_attr( $side ); ?>" value="<?php echo esc_attr( (string) $config[ 'padding_' . $side ] ); ?>" class="small-text">
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Space between the notice border and its text.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Margin (px)', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
				<td>
					<?php foreach ( $box_sides as $side => $label ) : ?>
						<label class="mcwccn-field-label"><?php echo esc_html( $label ); ?>
							<input type="number" min="0" max="200" name="mcwccn_margin_<?php echo esc_attr( $side ); ?>" value="<?php echo esc_attr( (string) $config[ 'margin_' . $side ] ); ?>" class="small-text">
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Space outside the notice, separating it from the fields around it.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * "Customer Question" meta box: whether to render an input at checkout,
	 * its type (radio or dropdown), the options list, default selection, and
	 * where to store the answer on the order.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_question_box( $post ) {
		$config  = mcwccn_get_notice_config( $post->ID );
		$weights = mcwccn_heading_weights();

		$weight_labels = array(
			'400' => __( 'Normal (400)', 'mad-cow-checkout-notices-for-woocommerce' ),
			'500' => __( 'Medium (500)', 'mad-cow-checkout-notices-for-woocommerce' ),
			'600' => __( 'Semi-bold (600)', 'mad-cow-checkout-notices-for-woocommerce' ),
			'700' => __( 'Bold (700)', 'mad-cow-checkout-notices-for-woocommerce' ),
			'800' => __( 'Extra-bold (800)', 'mad-cow-checkout-notices-for-woocommerce' ),
		);

		$rows = array();
		foreach ( (array) $config['question_options'] as $opt ) {
			if ( ! is_array( $opt ) ) {
				continue;
			}
			$rows[] = array(
				'value' => isset( $opt['value'] ) ? (string) $opt['value'] : '',
				'label' => isset( $opt['label'] ) ? (string) $opt['label'] : '',
				'date'  => isset( $opt['date'] ) ? (string) $opt['date'] : '',
			);
		}
		if ( empty( $rows ) ) {
			$rows[] = array( 'value' => '', 'label' => '', 'date' => '' );
		}
		?>
		<table class="form-table mcwccn-form">
			<tr>
				<th scope="row"><label for="mcwccn_question_enabled"><?php esc_html_e( 'Ask a Question', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="mcwccn_question_enabled" name="mcwccn_question_enabled" value="1" <?php checked( 1, (int) $config['question_enabled'] ); ?> data-mcwccn-toggle="question">
						<?php esc_html_e( 'Show a radio or dropdown question with this notice. The customer\'s answer is saved to the order.', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
					</label>
					<p class="description mcwccn-block-warning" data-mcwccn-target="question">
						<strong><?php esc_html_e( 'Classic checkout only.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></strong>
						<?php esc_html_e( 'A notice that asks a question does not appear on a block-based checkout page. That checkout submits through WooCommerce\'s Store API rather than a form, so an answer given there could never reach the order, and showing a field that quietly loses data would be worse than showing none.', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
						<br>
						<?php
						printf(
							/* translators: %s: the woocommerce_checkout shortcode, wrapped in a code tag. */
							esc_html__( 'To use a question, edit your Checkout page and replace the Checkout block with the %s shortcode. Notices without a question appear on both kinds of checkout.', 'mad-cow-checkout-notices-for-woocommerce' ),
							'<code>[woocommerce_checkout]</code>'
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<div class="mcwccn-question-wrap" data-mcwccn-target="question">
			<table class="form-table mcwccn-form">
				<tr>
					<th scope="row"><label for="mcwccn_question_type"><?php esc_html_e( 'Field Type', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
					<td>
						<select id="mcwccn_question_type" name="mcwccn_question_type">
							<option value="dropdown" <?php selected( 'dropdown', $config['question_type'] ); ?>><?php esc_html_e( 'Dropdown', 'mad-cow-checkout-notices-for-woocommerce' ); ?></option>
							<option value="radio" <?php selected( 'radio', $config['question_type'] ); ?>><?php esc_html_e( 'Radio Buttons', 'mad-cow-checkout-notices-for-woocommerce' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Require an Answer', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="mcwccn_question_required" value="1" <?php checked( 1, (int) $config['question_required'] ); ?>>
							<?php esc_html_e( 'The customer must choose an option before the order can be placed.', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Nothing is pre-selected when this is on, so the customer has to make a real choice. Leave Default Selection blank too, or that option is already chosen for them and the requirement is satisfied before they do anything.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="mcwccn_question_label"><?php esc_html_e( 'Question Label', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
					<td>
						<input type="text" id="mcwccn_question_label" name="mcwccn_question_label" value="<?php echo esc_attr( $config['question_label'] ); ?>" class="large-text">
						<p class="description"><?php esc_html_e( 'Shown above the field. For example: "Choose a delivery date" or "Which option would you prefer?"', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Label Style', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
					<td>
						<label class="mcwccn-field-label"><?php esc_html_e( 'Size (px)', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
							<input type="number" min="8" max="72" name="mcwccn_qlabel_size" value="<?php echo esc_attr( (string) $config['qlabel_size'] ); ?>" class="small-text">
						</label>
						<label class="mcwccn-field-label"><?php esc_html_e( 'Weight', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
							<select name="mcwccn_qlabel_weight">
								<?php foreach ( $weights as $weight ) : ?>
									<option value="<?php echo esc_attr( $weight ); ?>" <?php selected( $weight, $config['qlabel_weight'] ); ?>>
										<?php echo esc_html( $weight_labels[ $weight ] ?? $weight ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="mcwccn-field-label"><?php esc_html_e( 'Colour', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
							<input type="color" name="mcwccn_qlabel_color" value="<?php echo esc_attr( $config['qlabel_color'] ); ?>">
						</label>
						<label class="mcwccn-inline-check">
							<input type="checkbox" name="mcwccn_qlabel_inherit" value="1" <?php checked( 1, (int) $config['qlabel_inherit'] ); ?>>
							<?php esc_html_e( 'Inherit colour from the theme', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Ignored when the Question Label above is left blank.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
					<td>
						<table class="widefat mcwccn-options" id="mcwccn-options-table" style="max-width:820px;">
							<thead>
								<tr>
									<th style="width:34px;"><span class="screen-reader-text"><?php esc_html_e( 'Reorder', 'mad-cow-checkout-notices-for-woocommerce' ); ?></span></th>
									<th><?php esc_html_e( 'Display Label', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
									<th style="width:160px;"><?php esc_html_e( 'Auto-Hide On/After', 'mad-cow-checkout-notices-for-woocommerce' ); ?></th>
									<th style="width:90px;"></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $i => $row ) : ?>
									<tr class="mcwccn-option-row">
										<td class="mcwccn-option-handle" title="<?php esc_attr_e( 'Drag to reorder', 'mad-cow-checkout-notices-for-woocommerce' ); ?>">
											<span class="dashicons dashicons-menu" aria-hidden="true"></span>
										</td>
										<td>
											<input type="text" name="mcwccn_question_options[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'shown to customer', 'mad-cow-checkout-notices-for-woocommerce' ); ?>">
										</td>
										<td>
											<input type="date" name="mcwccn_question_options[<?php echo (int) $i; ?>][date]" value="<?php echo esc_attr( $row['date'] ); ?>">
										</td>
										<td>
											<button type="button" class="button mcwccn-option-remove"><?php esc_html_e( 'Remove', 'mad-cow-checkout-notices-for-woocommerce' ); ?></button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p>
							<button type="button" class="button" id="mcwccn-option-add">
								<?php esc_html_e( '+ Add Option', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
							</button>
						</p>
						<p class="description">
							<?php esc_html_e( 'Drag the handle on the left to change the order options appear in at checkout.', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
							<br><strong><?php esc_html_e( 'Display Label', 'mad-cow-checkout-notices-for-woocommerce' ); ?></strong>:
							<?php esc_html_e( 'what the customer sees. If left blank AND a date is set, the label is auto-formatted (e.g. "Tuesday, December 16").', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
							<br><strong><?php esc_html_e( 'Auto-Hide On/After', 'mad-cow-checkout-notices-for-woocommerce' ); ?></strong>:
							<?php esc_html_e( 'optional date. This option disappears from checkout on and after that date. Leave blank for options that should always be available.', 'mad-cow-checkout-notices-for-woocommerce' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="mcwccn_question_default"><?php esc_html_e( 'Default Selection', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
					<td>
						<select id="mcwccn_question_default" name="mcwccn_question_default">
							<option value=""><?php esc_html_e( '— First option —', 'mad-cow-checkout-notices-for-woocommerce' ); ?></option>
							<?php foreach ( $rows as $row ) : ?>
								<?php
									$option_value = '' !== $row['date'] ? $row['date'] : $row['label'];
									$option_text  = '' !== $row['label'] ? $row['label'] : $option_value;
									if ( '' === $option_value ) {
										continue;
									}
								?>
								<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $option_value, $config['question_default'] ); ?>><?php echo esc_html( $option_text ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Which option starts pre-selected at checkout. The list comes from the saved options above, so add options and press Update first. Leave this on First option when Require an Answer is on.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="mcwccn_question_admin_label"><?php esc_html_e( 'Admin/Email Label', 'mad-cow-checkout-notices-for-woocommerce' ); ?></label></th>
					<td>
						<input type="text" id="mcwccn_question_admin_label" name="mcwccn_question_admin_label" value="<?php echo esc_attr( $config['question_admin_label'] ); ?>" class="regular-text" placeholder="e.g. Delivery Date">
						<p class="description"><?php esc_html_e( 'Label used when displaying the saved answer on the admin order edit screen and inside order emails.', 'mad-cow-checkout-notices-for-woocommerce' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Save handler for the notice CPT.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['mcwccn_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcwccn_nonce'] ) ), 'mcwccn_save_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( MCWCCN_POST_TYPE !== $post->post_type ) {
			return;
		}

		/* ── Display block ─────────────────────────────────────────────── */

		update_post_meta( $post_id, '_mcwccn_enabled', ! empty( $_POST['mcwccn_enabled'] ) ? 1 : 0 );
		update_post_meta(
			$post_id,
			'_mcwccn_heading',
			isset( $_POST['mcwccn_heading'] ) ? sanitize_text_field( wp_unslash( $_POST['mcwccn_heading'] ) ) : ''
		);
		update_post_meta(
			$post_id,
			'_mcwccn_message',
			isset( $_POST['mcwccn_message'] ) ? wp_kses_post( wp_unslash( $_POST['mcwccn_message'] ) ) : ''
		);
		update_post_meta( $post_id, '_mcwccn_show_icon', ! empty( $_POST['mcwccn_show_icon'] ) ? 1 : 0 );

		/* ── Targeting block ───────────────────────────────────────────── */

		update_post_meta(
			$post_id,
			'_mcwccn_start_date',
			mcwccn_sanitize_date( isset( $_POST['mcwccn_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['mcwccn_start_date'] ) ) : '' )
		);
		update_post_meta(
			$post_id,
			'_mcwccn_end_date',
			mcwccn_sanitize_date( isset( $_POST['mcwccn_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['mcwccn_end_date'] ) ) : '' )
		);

		$cats = isset( $_POST['mcwccn_include_cats'] )
			? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['mcwccn_include_cats'] ) )
			: array();
		$cats = array_values( array_unique( array_filter( $cats ) ) );
		update_post_meta( $post_id, '_mcwccn_include_cats', $cats );

		$ids = isset( $_POST['mcwccn_include_ids'] )
			? mcwccn_parse_ids( sanitize_text_field( wp_unslash( $_POST['mcwccn_include_ids'] ) ) )
			: array();
		update_post_meta( $post_id, '_mcwccn_include_ids', $ids );

		/* ── Appearance block ──────────────────────────────────────────── */

		$defaults = mcwccn_appearance_defaults();

		update_post_meta( $post_id, '_mcwccn_heading_inherit', empty( $_POST['mcwccn_heading_inherit'] ) ? 0 : 1 );
		update_post_meta( $post_id, '_mcwccn_qlabel_inherit', empty( $_POST['mcwccn_qlabel_inherit'] ) ? 0 : 1 );

		$qlabel_weight = isset( $_POST['mcwccn_qlabel_weight'] )
			? sanitize_key( wp_unslash( $_POST['mcwccn_qlabel_weight'] ) )
			: $defaults['qlabel_weight'];
		if ( ! in_array( $qlabel_weight, mcwccn_heading_weights(), true ) ) {
			$qlabel_weight = $defaults['qlabel_weight'];
		}
		update_post_meta( $post_id, '_mcwccn_qlabel_weight', $qlabel_weight );

		$heading_weight = isset( $_POST['mcwccn_heading_weight'] )
			? sanitize_key( wp_unslash( $_POST['mcwccn_heading_weight'] ) )
			: $defaults['heading_weight'];
		if ( ! in_array( $heading_weight, mcwccn_heading_weights(), true ) ) {
			$heading_weight = $defaults['heading_weight'];
		}
		update_post_meta( $post_id, '_mcwccn_heading_weight', $heading_weight );

		update_post_meta( $post_id, '_mcwccn_bg_transparent', empty( $_POST['mcwccn_bg_transparent'] ) ? 0 : 1 );

		foreach ( array(
			'heading_color' => '_mcwccn_heading_color',
			'qlabel_color'  => '_mcwccn_qlabel_color',
			'bg_color'      => '_mcwccn_bg_color',
			'border_color'  => '_mcwccn_border_color',
		) as $field => $meta_key ) {
			$color = isset( $_POST[ 'mcwccn_' . $field ] )
				? sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST[ 'mcwccn_' . $field ] ) ) )
				: '';
			update_post_meta( $post_id, $meta_key, $color ? $color : $defaults[ $field ] );
		}

		$border_style = isset( $_POST['mcwccn_border_style'] )
			? sanitize_key( wp_unslash( $_POST['mcwccn_border_style'] ) )
			: $defaults['border_style'];
		if ( ! in_array( $border_style, mcwccn_border_styles(), true ) ) {
			$border_style = $defaults['border_style'];
		}
		update_post_meta( $post_id, '_mcwccn_border_style', $border_style );

		/*
		 * Every remaining appearance value is a pixel length. absint() plus a
		 * hard ceiling keeps them numeric, so the inline style built on the
		 * front end can never carry anything but digits.
		 */
		$pixel_fields = array(
			'heading_size'   => 72,
			'qlabel_size'    => 72,
			'border_width'   => 40,
			'accent_width'   => 40,
			'border_radius'  => 100,
			'padding_top'    => 200,
			'padding_right'  => 200,
			'padding_bottom' => 200,
			'padding_left'   => 200,
			'margin_top'     => 200,
			'margin_right'   => 200,
			'margin_bottom'  => 200,
			'margin_left'    => 200,
		);

		foreach ( $pixel_fields as $field => $max ) {
			$value = isset( $_POST[ 'mcwccn_' . $field ] )
				? absint( wp_unslash( $_POST[ 'mcwccn_' . $field ] ) )
				: (int) $defaults[ $field ];
			update_post_meta( $post_id, '_mcwccn_' . $field, min( $value, $max ) );
		}

		/* ── Question block ────────────────────────────────────────────── */

		update_post_meta( $post_id, '_mcwccn_question_enabled', ! empty( $_POST['mcwccn_question_enabled'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_mcwccn_question_required', ! empty( $_POST['mcwccn_question_required'] ) ? 1 : 0 );

		$type = isset( $_POST['mcwccn_question_type'] ) ? sanitize_key( wp_unslash( $_POST['mcwccn_question_type'] ) ) : 'dropdown';
		if ( ! in_array( $type, array( 'radio', 'dropdown' ), true ) ) {
			$type = 'dropdown';
		}
		update_post_meta( $post_id, '_mcwccn_question_type', $type );

		update_post_meta(
			$post_id,
			'_mcwccn_question_label',
			isset( $_POST['mcwccn_question_label'] ) ? sanitize_text_field( wp_unslash( $_POST['mcwccn_question_label'] ) ) : ''
		);

		$options = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified at the top of this method; each field is sanitized individually below.
		$raw_options = isset( $_POST['mcwccn_question_options'] ) && is_array( $_POST['mcwccn_question_options'] )
			? wp_unslash( $_POST['mcwccn_question_options'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field in the loop below.
			: array();

		if ( ! empty( $raw_options ) ) {
			foreach ( $raw_options as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
				$date  = isset( $row['date'] ) ? mcwccn_sanitize_date( (string) $row['date'] ) : '';

				// Drop rows with nothing in them.
				if ( '' === $label && '' === $date ) {
					continue;
				}

				/*
				 * The stored value is always derived, never carried over from what was
				 * saved before. A dated option stores its Y-m-d date, which keeps order
				 * meta sortable and unaffected by rewording; anything else stores the
				 * label.
				 *
				 * Deriving unconditionally is the point: preserving a previous value
				 * meant editing a row could never change what landed on the order, so
				 * switching an option over to a date went on saving the old text.
				 * Orders already placed are unaffected either way - the order screen
				 * and emails read the value stored on the order, not this list.
				 */
				$value = '' !== $date ? $date : $label;
				// If label is empty and date is set, auto-format for storage-time preview.
				if ( '' === $label && '' !== $date ) {
					$label = mcwccn_format_date_label( $date );
				}

				$options[] = array(
					'value' => $value,
					'label' => $label,
					'date'  => $date,
				);
			}
		}
		update_post_meta( $post_id, '_mcwccn_question_options', $options );

		$default = isset( $_POST['mcwccn_question_default'] )
			? sanitize_text_field( wp_unslash( $_POST['mcwccn_question_default'] ) )
			: '';

		// Drop a default that no longer matches any option, e.g. after a row was removed.
		$option_values = wp_list_pluck( $options, 'value' );
		if ( '' !== $default && ! in_array( $default, array_map( 'strval', $option_values ), true ) ) {
			$default = '';
		}
		update_post_meta( $post_id, '_mcwccn_question_default', $default );

		/*
		 * The meta key is no longer editable in the admin. Whatever a notice is
		 * already using is kept untouched - rewriting it would orphan every
		 * answer stored on existing orders - and a notice that has none gets a
		 * generated one exactly once.
		 */
		$existing_key = (string) get_post_meta( $post_id, '_mcwccn_question_meta_key', true );
		if ( '' === $existing_key ) {
			update_post_meta( $post_id, '_mcwccn_question_meta_key', mcwccn_sanitize_meta_key( '', $post_id ) );
		}

		update_post_meta(
			$post_id,
			'_mcwccn_question_admin_label',
			isset( $_POST['mcwccn_question_admin_label'] ) ? sanitize_text_field( wp_unslash( $_POST['mcwccn_question_admin_label'] ) ) : ''
		);
	}
}
