<?php
namespace WPComplianceCL\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rights {
	public function __construct() {
		add_action( 'admin_post_nopriv_ccl_submit_right', array( $this, 'submit' ) );
		add_action( 'admin_post_ccl_submit_right', array( $this, 'submit' ) );
	}

	public function submit(): void {
		if ( ! isset( $_POST['ccl_right_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ccl_right_nonce'] ) ), 'ccl_submit_right' ) ) {
			wp_die( esc_html__( 'La solicitud no pudo validarse.', 'wp-compliance-cl' ) );
		}

		if ( ! empty( $_POST['company'] ) ) {
			wp_safe_redirect( add_query_arg( 'ccl_right', 'ok', wp_get_referer() ?: home_url( '/' ) ) );
			exit;
		}

		$name    = isset( $_POST['requester_name'] ) ? sanitize_text_field( wp_unslash( $_POST['requester_name'] ) ) : '';
		$email   = isset( $_POST['requester_email'] ) ? sanitize_email( wp_unslash( $_POST['requester_email'] ) ) : '';
		$type    = isset( $_POST['right_type'] ) ? sanitize_key( wp_unslash( $_POST['right_type'] ) ) : '';
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
		$allowed = array( 'access', 'rectification', 'deletion', 'objection', 'portability', 'blocking' );

		if ( ! $name || ! is_email( $email ) || ! in_array( $type, $allowed, true ) ) {
			wp_safe_redirect( add_query_arg( 'ccl_right', 'invalid', wp_get_referer() ?: home_url( '/' ) ) );
			exit;
		}

		$rate_key = 'ccl_right_' . hash_hmac( 'sha256', $email, wp_salt( 'nonce' ) );
		if ( get_transient( $rate_key ) ) {
			wp_safe_redirect( add_query_arg( 'ccl_right', 'rate', wp_get_referer() ?: home_url( '/' ) ) );
			exit;
		}
		set_transient( $rate_key, 1, MINUTE_IN_SECONDS );

		global $wpdb;
		$now       = Util::now_mysql();
		$timestamp = current_time( 'timestamp', true );
		$due       = gmdate( 'Y-m-d H:i:s', $timestamp + ( 30 * DAY_IN_SECONDS ) );
		$reference = 'CL-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
		$proof     = hash_hmac( 'sha256', $reference . '|' . $email . '|' . $now, wp_salt( 'auth' ) );

		$wpdb->insert(
			Database::table( 'rights' ),
			array(
				'reference'         => $reference,
				'right_type'        => $type,
				'requester_name'    => $name,
				'requester_email'   => $email,
				'details'           => $details,
				'status'            => 'received',
				'identity_verified' => 0,
				'received_at'       => $now,
				'due_at'            => $due,
				'proof_hash'        => $proof,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		$id = (int) $wpdb->insert_id;
		$this->event( $id, 'received', 'Solicitud recibida desde el formulario público.' );
		Audit::log( 'right_request_received', 'right', (string) $id, array( 'reference' => $reference, 'type' => $type ) );

		$settings = Util::settings();
		$subject  = sprintf( '[%s] Solicitud de %s recibida', $reference, Util::right_label( $type ) );
		$message  = "Hemos recibido tu solicitud de protección de datos.\n\nReferencia: {$reference}\nTipo: " . Util::right_label( $type ) . "\nFecha límite inicial: {$due} UTC\n\nConserva esta referencia para futuras comunicaciones.";
		wp_mail( $email, $subject, $message );
		wp_mail( sanitize_email( $settings['privacy_email'] ), $subject, "Nueva solicitud recibida.\n\nReferencia: {$reference}\nSolicitante: {$name}\nEmail: {$email}\nTipo: " . Util::right_label( $type ) );

		wp_safe_redirect( add_query_arg( array( 'ccl_right' => 'ok', 'ccl_ref' => $reference ), wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	public function event( int $right_id, string $event_type, string $message = '' ): void {
		global $wpdb;
		$wpdb->insert(
			Database::table( 'right_events' ),
			array(
				'right_id'   => $right_id,
				'event_type' => sanitize_key( $event_type ),
				'message'    => sanitize_textarea_field( $message ),
				'actor_id'   => get_current_user_id(),
				'created_at' => Util::now_mysql(),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);
	}
}
