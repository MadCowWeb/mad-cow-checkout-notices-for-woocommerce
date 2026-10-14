<?php
/**
 * Renders saved question answers on the admin order edit screen and inside
 * WooCommerce order emails.
 *
 * Uses mcwccn_get_all_notices() so answers on existing orders keep displaying
 * even after the corresponding notice is disabled or trashed.
 *
 * @package Mad_Cow_Checkout_Notices_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MCWCCN_Order_Display
 */
class MCWCCN_Order_Display {

	public function __construct() {
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin' ), 10, 1 );
		add_action( 'woocommerce_email_order_meta', array( $this, 'render_email' ), 10, 4 );
	}

	/**
	 * Admin order edit screen: list every notice's saved answer.
	 *
	 * @param WC_Order $order Order.
	 */
	public function render_admin( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		foreach ( $this->collect( $order ) as $row ) {
			echo '<p class="mcwccn-order-meta"><strong>'
				. esc_html( $row['label'] ) . ':</strong> '
				. esc_html( $row['value'] ) . '</p>';
		}
	}

	/**
	 * Order emails. Output is intentionally unstyled so it inherits whatever
	 * the active email template already uses; themes that want their own
	 * treatment can hook woocommerce_email_order_meta at a later priority.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Whether this is the admin copy.
	 * @param bool     $plain_text    Whether the email is plain text.
	 * @param WC_Email $email         Email object.
	 */
	public function render_email( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$rows = $this->collect( $order );
		if ( empty( $rows ) ) {
			return;
		}

		if ( $plain_text ) {
			foreach ( $rows as $row ) {
				echo esc_html( $row['label'] ) . ': ' . esc_html( $row['value'] ) . "\n";
			}
			return;
		}

		foreach ( $rows as $row ) {
			echo '<p><strong>' . esc_html( $row['label'] ) . ':</strong> '
				. esc_html( $row['value'] ) . '</p>';
		}
	}

	/**
	 * Build the list of {label, value} rows to display for a given order.
	 * Skips notices with no meta key, no admin label, or an empty stored value.
	 *
	 * @param WC_Order $order Order.
	 * @return array<int, array{label:string, value:string}>
	 */
	private function collect( $order ) {
		$out  = array();
		$seen = array();

		foreach ( mcwccn_get_all_notices() as $notice ) {
			$config = mcwccn_get_notice_config( $notice );

			$key   = mcwccn_sanitize_meta_key( $config['question_meta_key'], $config['id'] );
			$label = trim( (string) $config['question_admin_label'] );

			if ( '' === $key || '' === $label ) {
				continue;
			}
			// Prevent duplicate rows when two notices share the same meta key.
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$value = $order->get_meta( $key );
			if ( '' === (string) $value ) {
				continue;
			}

			$out[] = array(
				'label' => $label,
				'value' => $this->display_value( $config, (string) $value ),
			);
		}

		/**
		 * Filter the rows shown on the admin order screen and in order emails.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array{label:string, value:string}> $out   Rows to display.
		 * @param WC_Order                                      $order The order being displayed.
		 */
		return (array) apply_filters( 'mcwccn_order_meta_rows', $out, $order );
	}

	/**
	 * Turn the value stored on the order into something worth reading.
	 *
	 * A dated option stores its Y-m-d date, so the raw value would surface in
	 * emails as "2026-12-18" rather than "Friday, December 18". Looking the
	 * value back up against the notice's options restores the wording the
	 * customer actually chose from.
	 *
	 * The stored value is still the source of truth: when no option matches -
	 * because it was edited or removed after the order was placed - the raw
	 * value is shown rather than a guess.
	 *
	 * @param array  $config Notice config array.
	 * @param string $value  Value stored on the order.
	 * @return string
	 */
	private function display_value( $config, $value ) {
		foreach ( (array) $config['question_options'] as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			if ( (string) ( $option['value'] ?? '' ) !== $value ) {
				continue;
			}

			$label = (string) ( $option['label'] ?? '' );
			$date  = (string) ( $option['date'] ?? '' );

			if ( '' === $label && '' !== $date ) {
				$label = mcwccn_format_date_label( $date );
			}

			return '' !== $label ? $label : $value;
		}

		return $value;
	}
}
