<?php
/**
 * Frontend rendering + order meta persistence.
 *
 * Loops every enabled mcwccn_notice on the checkout page and renders its
 * heading, message, and optional question. On order submit it walks the same
 * list and saves the customer's answer to the order under each notice's
 * configured meta key.
 *
 * @package Mad_Cow_Checkout_Notices_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MCWCCN_Frontend
 */
class MCWCCN_Frontend {

	public function __construct() {
		add_action( 'woocommerce_after_order_notes', array( $this, 'render_all_notices' ), 5 );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_required_answers' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_all_answers' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		/*
		 * Block checkout. render_block_{name} is a WordPress core filter that
		 * hands back a block's rendered HTML, so notices can be placed around
		 * the Checkout block without any JavaScript, Store API work or build
		 * step. The classic hooks above stay exactly as they are.
		 */
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'inject_block_notices' ), 10, 2 );
	}

	/**
	 * Enqueue the notice stylesheet on the checkout page only.
	 */
	public function enqueue_styles() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		$relative = 'assets/notices.css';
		$path     = MCWCCN_DIR . $relative;
		$version  = file_exists( $path ) ? filemtime( $path ) : MCWCCN_VERSION;

		wp_enqueue_style(
			'mcwccn-notices',
			MCWCCN_URL . $relative,
			array(),
			$version
		);
	}

	/**
	 * Render every eligible notice at checkout.
	 *
	 * @param WC_Checkout $checkout Checkout object.
	 */
	public function render_all_notices( $checkout ) {
		$notices = mcwccn_get_active_notices();
		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$config = mcwccn_get_notice_config( $notice );

			if ( ! $this->should_render( $config ) ) {
				continue;
			}

			$this->render_single_notice( $config, $checkout );
		}
	}

	/**
	 * Place notices around the Checkout block.
	 *
	 * The block checkout never fires woocommerce_after_order_notes, so the
	 * classic hook renders nothing there. render_block_woocommerce/checkout is
	 * a core WordPress filter over the block's rendered HTML, which lets the
	 * same notices - same targeting, same dates, same per-notice styling - be
	 * placed above the block with no JavaScript involved.
	 *
	 * Notices carrying a question are deliberately skipped. The block checkout
	 * submits through the Store API as JSON built by React, so an input placed
	 * in this HTML sits outside that entirely: it would render, the customer
	 * would answer it, and the answer would be silently discarded. Showing
	 * nothing is honest; showing a field that quietly loses data is not.
	 *
	 * @param string $block_content The block's rendered HTML.
	 * @param array  $block         The parsed block.
	 * @return string
	 */
	public function inject_block_notices( $block_content, $block ) {
		if ( empty( $block['blockName'] ) || 'woocommerce/checkout' !== $block['blockName'] ) {
			return $block_content;
		}

		$notices = mcwccn_get_active_notices();
		if ( empty( $notices ) ) {
			return $block_content;
		}

		$before = '';

		foreach ( $notices as $notice ) {
			$config = mcwccn_get_notice_config( $notice );

			if ( ! empty( $config['question_enabled'] ) ) {
				continue;
			}
			if ( ! $this->should_render( $config ) ) {
				continue;
			}

			ob_start();
			$this->render_single_notice( $config );
			$before .= ob_get_clean();
		}

		return $before . $block_content;
	}

	/**
	 * Decide whether a single notice should render.
	 *
	 * @param array $config Notice config array.
	 * @return bool
	 */
	private function should_render( $config ) {
		if ( empty( $config['enabled'] ) ) {
			return false;
		}
		if ( ! mcwccn_in_date_window( $config ) ) {
			return false;
		}
		if ( ! empty( $config['question_enabled'] )
			&& mcwccn_all_dates_expired( (array) $config['question_options'] ) ) {
			return false;
		}
		if ( ! mcwccn_cart_matches( $config ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Render one notice's heading, message, and optional question.
	 *
	 * @param array       $config   Notice config array.
	 * @param WC_Checkout $checkout Checkout object.
	 */
	private function render_single_notice( $config, $checkout = null ) {
		$style = $this->box_style( $config );

		printf(
			'<div class="mcwccn-notice mcwccn-notice-%d" style="%s">',
			(int) $config['id'],
			esc_attr( $style )
		);

		if ( ! empty( $config['heading'] ) ) {
			printf(
				'<h2 class="mcwccn-heading" style="%s">%s</h2>',
				esc_attr( $this->heading_style( $config ) ),
				esc_html( $config['heading'] )
			);
		}

		if ( ! empty( $config['message'] ) ) {
			// A div, not a p: the editor stores block-level HTML that cannot nest inside a paragraph.
			echo '<div class="mcwccn-message">';

			if ( ! empty( $config['show_icon'] ) ) {
				echo wp_kses( $this->icon_svg(), $this->allowed_svg_tags() );
			}

			echo '<div class="mcwccn-message-text">'
				. wp_kses_post( wpautop( $config['message'] ) )
				. '</div>';
			echo '</div>';
		}

		// $checkout is absent on the block path, where a question cannot work.
		if ( ! empty( $config['question_enabled'] ) && $checkout instanceof WC_Checkout ) {
			$this->render_question( $config, $checkout );
		}

		echo '</div>';
	}

	/**
	 * Build the heading's inline style. Same escaping contract as box_style():
	 * parts are assembled unescaped and the caller escapes the result once.
	 * The size is forced through %d and the weight is checked against a list,
	 * so neither can carry anything but the value it claims to be.
	 *
	 * @param array $config Notice config array.
	 * @return string
	 */
	private function heading_style( $config ) {
		$parts = array(
			sprintf( 'font-size:%dpx', (int) $config['heading_size'] ),
			sprintf( 'font-weight:%s', $config['heading_weight'] ),
		);

		if ( empty( $config['heading_inherit'] ) ) {
			$parts[] = sprintf( 'color:%s', $config['heading_color'] );
		}

		return implode( ';', $parts );
	}

	/**
	 * Build the question label's inline style. Same escaping contract as
	 * box_style() and heading_style(): assembled unescaped, escaped once by the
	 * caller, with the size forced through %d and the weight whitelisted.
	 *
	 * @param array $config Notice config array.
	 * @return string
	 */
	private function question_label_style( $config ) {
		$parts = array(
			sprintf( 'font-size:%dpx', (int) $config['qlabel_size'] ),
			sprintf( 'font-weight:%s', $config['qlabel_weight'] ),
		);

		if ( empty( $config['qlabel_inherit'] ) ) {
			$parts[] = sprintf( 'color:%s', $config['qlabel_color'] );
		}

		return implode( ';', $parts );
	}

	/**
	 * Build the notice box's inline style from its appearance settings.
	 *
	 * Parts are assembled UNESCAPED and the finished string is escaped exactly
	 * once by the caller. That split matters both ways:
	 *
	 *   - esc_attr() does not neutralise ':' or ';', so escaping a fragment buys
	 *     nothing and would not stop a declaration being injected. Safety comes
	 *     from every numeric going through %d and every colour through
	 *     sanitize_hex_color(), with the border style checked against a list.
	 *   - Escaping here as well as at output would double-encode, turning a
	 *     quote into &amp;quot;, which is not valid CSS.
	 *
	 * @param array $config Notice config array.
	 * @return string
	 */
	private function box_style( $config ) {
		$parts = array();

		if ( empty( $config['bg_transparent'] ) ) {
			$parts[] = sprintf( 'background-color:%s', $config['bg_color'] );
		}

		$border_width = (int) $config['border_width'];
		$border_style = $config['border_style'];

		if ( $border_width > 0 && 'none' !== $border_style ) {
			$parts[] = sprintf(
				'border:%dpx %s %s',
				$border_width,
				$border_style,
				$config['border_color']
			);

			$accent = (int) $config['accent_width'];
			if ( $accent > 0 ) {
				$parts[] = sprintf( 'border-left-width:%dpx', $accent );
			}
		}

		$parts[] = sprintf( 'border-radius:%dpx', (int) $config['border_radius'] );

		$parts[] = sprintf(
			'padding:%dpx %dpx %dpx %dpx',
			(int) $config['padding_top'],
			(int) $config['padding_right'],
			(int) $config['padding_bottom'],
			(int) $config['padding_left']
		);

		$parts[] = sprintf(
			'margin:%dpx %dpx %dpx %dpx',
			(int) $config['margin_top'],
			(int) $config['margin_right'],
			(int) $config['margin_bottom'],
			(int) $config['margin_left']
		);

		return implode( ';', $parts );
	}

	/**
	 * Inline arrow icon. Inlined rather than loaded from an icon font so the
	 * plugin carries no external dependency and renders identically on any
	 * theme. currentColor means it inherits the surrounding text colour.
	 *
	 * @return string
	 */
	private function icon_svg() {
		return '<svg class="mcwccn-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false">'
			. '<path d="M10 0a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm.7-11.7a1 1 0 0 0-1.4 1.4L10.6 9H6a1 1 0 1 0 0 2h4.6l-1.3 1.3a1 1 0 1 0 1.4 1.4l3-3a1 1 0 0 0 0-1.4l-3-3Z"/>'
			. '</svg>';
	}

	/**
	 * Tag whitelist for wp_kses() when echoing the inline icon.
	 *
	 * @return array
	 */
	private function allowed_svg_tags() {
		return array(
			'svg'  => array(
				'class'       => true,
				'width'       => true,
				'height'      => true,
				'viewbox'     => true,
				'fill'        => true,
				'aria-hidden' => true,
				'focusable'   => true,
			),
			'path' => array(
				'd'    => true,
				'fill' => true,
			),
		);
	}

	/**
	 * Render the notice's question (radio or dropdown) using WooCommerce's
	 * form-field helper so it inherits checkout styling.
	 *
	 * @param array       $config   Notice config array.
	 * @param WC_Checkout $checkout Checkout object.
	 */
	private function render_question( $config, $checkout ) {
		$options = $this->build_options( $config );
		if ( empty( $options ) ) {
			return;
		}

		$field_key = $this->field_key( $config );
		$is_radio  = 'radio' === $config['question_type'];
		$required  = ! empty( $config['question_required'] );

		/*
		 * A required question must start with nothing chosen, otherwise the
		 * requirement is already satisfied before the customer touches it. An
		 * explicit Default Selection still wins - the admin asked for it.
		 */
		$default = $required && '' === (string) $config['question_default']
			? ''
			: $this->determine_default( $config, $options );

		/*
		 * The label is rendered here rather than handed to woocommerce_form_field()
		 * for two reasons: that function has no hook for putting an inline style on
		 * it, and for radio fields it reuses label_class for BOTH the group label
		 * and each option label, so styling one would style the other. Rendering it
		 * separately also keeps it outside .form-row, where WooCommerce's own
		 * "label { display: block }" rule would otherwise apply.
		 */
		if ( '' !== (string) $config['question_label'] ) {
			$option_keys = array_keys( $options );
			$label_for   = $is_radio
				? $field_key . '_' . (string) reset( $option_keys )
				: $field_key;

			printf(
				'<label class="mcwccn-question-label" for="%s" style="%s">%s%s</label>',
				esc_attr( $label_for ),
				esc_attr( $this->question_label_style( $config ) ),
				esc_html( $config['question_label'] ),
				// Decorative only: woocommerce_form_field() puts aria-required on the input itself.
				$required ? ' <span class="required" aria-hidden="true">*</span>' : ''
			);
		}

		$field_options = $options;

		/*
		 * A required dropdown needs an empty first entry, or the browser shows
		 * the first real option and the customer can submit without choosing.
		 * Added here rather than in build_options() so it can never count as a
		 * valid answer during validation or saving.
		 */
		if ( $required && ! $is_radio && '' === (string) $default ) {
			$field_options = array( '' => __( 'Please choose&hellip;', 'mad-cow-checkout-notices-for-woocommerce' ) ) + $field_options;
		}

		$args = array(
			'type'     => $is_radio ? 'radio' : 'select',
			'class'    => array( 'mcwccn-field', 'mcwccn-field-' . sanitize_html_class( $config['question_type'] ) ),
			'label'    => '',
			'required' => $required,
			'options'  => $field_options,
			'default'  => $default,
		);

		if ( $is_radio ) {
			$args['input_class'] = array( 'mcwccn-radio-input' );
			$args['label_class'] = array( 'mcwccn-radio-label' );
			$args['clear']       = true;
		}

		ob_start();
		woocommerce_form_field( $field_key, $args, $checkout->get_value( $field_key ) );
		$field_html = ob_get_clean();

		/*
		 * WooCommerce tags every option label with the field's optional/required
		 * marker - "(optional)" when the field is not required, an asterisk when
		 * it is. On a radio group that reads as though each individual choice
		 * carried the status, rather than the question as a whole, so both are
		 * stripped here; the group label above already shows the asterisk once.
		 * Matching the markup rather than the words keeps this working on
		 * translated sites.
		 */
		$field_html = preg_replace(
			'#(?:&nbsp;|\s)*<span class="(?:optional|required)"[^>]*>.*?</span>#s',
			'',
			$field_html
		);

		/*
		 * Escaped late. wp_kses_post() cannot be used here: form controls were
		 * removed from $allowedposttags in WordPress 5.0.1, so it would strip
		 * the select, its options and the radio inputs. The allowlist below
		 * covers exactly what woocommerce_form_field() emits for a select or a
		 * radio group, and routes every style attribute through
		 * safecss_filter_attr().
		 */
		echo wp_kses( $field_html, self::form_field_allowed_html() );
	}

	/**
	 * Allowed HTML for the markup woocommerce_form_field() produces.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function form_field_allowed_html() {
		$global = array(
			'id'    => true,
			'class' => true,
			'style' => true,
			'title' => true,
		);

		return array(
			'p'      => $global + array( 'data-priority' => true ),
			'span'   => $global,
			'div'    => $global,
			'br'     => array( 'class' => true ),
			'abbr'   => $global + array( 'title' => true ),
			'label'  => $global + array( 'for' => true ),
			'select' => $global + array(
				'name'             => true,
				'autocomplete'     => true,
				'data-placeholder' => true,
				'data-allow_clear' => true,
				'required'         => true,
				'aria-required'    => true,
				'aria-describedby' => true,
				'aria-label'       => true,
				'multiple'         => true,
			),
			'option' => array(
				'value'    => true,
				'selected' => true,
				'disabled' => true,
				'class'    => true,
			),
			'input'  => $global + array(
				'type'             => true,
				'name'             => true,
				'value'            => true,
				'checked'          => true,
				'required'         => true,
				'aria-required'    => true,
				'aria-describedby' => true,
				'aria-label'       => true,
				'autocomplete'     => true,
				'placeholder'      => true,
			),
		);
	}

	/**
	 * Build the WooCommerce-form-field-compatible options array from the
	 * notice's stored question_options. Applies the "hide past dates" rule
	 * and the "auto-format label from date" fallback.
	 *
	 * @param array $config Notice config array.
	 * @return array<string, string> value => label
	 */
	private function build_options( $config ) {
		$out  = array();
		$rows = mcwccn_future_options( (array) $config['question_options'] );

		foreach ( $rows as $opt ) {
			$value = isset( $opt['value'] ) ? (string) $opt['value'] : '';
			$label = isset( $opt['label'] ) ? (string) $opt['label'] : '';
			$date  = isset( $opt['date'] ) ? (string) $opt['date'] : '';

			if ( '' === $label && '' !== $date ) {
				$label = mcwccn_format_date_label( $date );
			}
			if ( '' === $value ) {
				$value = $label;
			}
			if ( '' === $value ) {
				continue;
			}

			$out[ $value ] = '' !== $label ? $label : $value;
		}

		return $out;
	}

	/**
	 * Determine which option value should be preselected.
	 *
	 * @param array                 $config  Notice config array.
	 * @param array<string, string> $options Rendered options.
	 * @return string
	 */
	private function determine_default( $config, $options ) {
		$default = (string) $config['question_default'];

		if ( '' !== $default && array_key_exists( $default, $options ) ) {
			return $default;
		}

		$keys = array_keys( $options );
		return $keys ? (string) reset( $keys ) : '';
	}

	/**
	 * Return the checkout form field name for a notice. Reuses the notice's
	 * meta key so the field posts back under the exact same name the meta
	 * gets stored under.
	 *
	 * @param array $config Notice config array.
	 * @return string
	 */
	private function field_key( $config ) {
		return mcwccn_sanitize_meta_key( $config['question_meta_key'], $config['id'] );
	}

	/**
	 * Block checkout when a notice's question is required and no valid option
	 * was chosen.
	 *
	 * woocommerce_checkout_process is WooCommerce's own validation hook, so the
	 * error appears where shoppers already expect checkout errors and works on
	 * any theme. Passing required to woocommerce_form_field() adds the
	 * client-side validate-required styling, but this is the check that
	 * actually stops the order.
	 *
	 * Only notices that are currently rendering are checked - demanding an
	 * answer to a question the customer was never shown would be a dead end.
	 *
	 * Nonce verification: WooCommerce verifies the checkout nonce inside
	 * WC_Checkout::process_checkout() before firing this hook.
	 */
	public function validate_required_answers() {
		foreach ( mcwccn_get_active_notices() as $notice ) {
			$config = mcwccn_get_notice_config( $notice );

			if ( empty( $config['question_enabled'] ) || empty( $config['question_required'] ) ) {
				continue;
			}
			if ( ! $this->should_render( $config ) ) {
				continue;
			}

			$options = $this->build_options( $config );
			if ( empty( $options ) ) {
				continue;
			}

			$field_key = $this->field_key( $config );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this hook fires; see method docblock.
			$raw = isset( $_POST[ $field_key ] ) ? wp_unslash( $_POST[ $field_key ] ) : '';
			$value = sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' );

			if ( '' !== $value && in_array( $value, array_map( 'strval', array_keys( $options ) ), true ) ) {
				continue;
			}

			$label = '' !== (string) $config['question_label']
				? $config['question_label']
				: $config['question_admin_label'];

			if ( '' === (string) $label ) {
				$label = __( 'this question', 'mad-cow-checkout-notices-for-woocommerce' );
			}

			wc_add_notice(
				sprintf(
					/* translators: %s: the question's label, for example "Choose a delivery date". */
					__( 'Please choose an option for &ldquo;%s&rdquo; before placing your order.', 'mad-cow-checkout-notices-for-woocommerce' ),
					$label
				),
				'error'
			);
		}
	}

	/**
	 * Save every submitted notice answer to its order.
	 *
	 * Nonce verification: this runs on woocommerce_checkout_update_order_meta,
	 * which WooCommerce fires from WC_Checkout::process_checkout() only after
	 * that method has already verified the checkout nonce. Re-checking here is
	 * not possible - the nonce field belongs to WooCommerce, not this plugin -
	 * so the PHPCS sniff is suppressed at each read with this justification.
	 *
	 * Every submitted value is additionally checked against the option list the
	 * notice actually offered, so a crafted request cannot write arbitrary text
	 * into order meta.
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_all_answers( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$notices = mcwccn_get_active_notices();
		if ( empty( $notices ) ) {
			return;
		}

		$dirty = false;
		foreach ( $notices as $notice ) {
			$config = mcwccn_get_notice_config( $notice );

			if ( empty( $config['question_enabled'] ) ) {
				continue;
			}

			$field_key = $this->field_key( $config );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this hook fires; see method docblock.
			if ( ! isset( $_POST[ $field_key ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
			$value = sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) );
			if ( '' === $value ) {
				continue;
			}

			// Only store a value the notice actually offered.
			$allowed = array_keys( $this->build_options( $config ) );
			if ( ! in_array( $value, array_map( 'strval', $allowed ), true ) ) {
				continue;
			}

			$order->update_meta_data( $field_key, $value );
			$dirty = true;
		}

		if ( $dirty ) {
			$order->save();
		}
	}
}
