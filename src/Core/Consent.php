<?php
namespace WPComplianceCL\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Consent {
	public function __construct() {
		add_action( 'wp_ajax_ccl_save_consent', array( $this, 'save_consent' ) );
		add_action( 'wp_ajax_nopriv_ccl_save_consent', array( $this, 'save_consent' ) );
		add_filter( 'script_loader_tag', array( $this, 'filter_script_tag' ), 10, 3 );
	}

	public function save_consent(): void {
		$nonce_ok = isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ccl_consent' );
		$origin_ok = $this->same_origin_request();
		if ( ! $nonce_ok && ! $origin_ok ) {
			wp_send_json_error( array( 'message' => 'Origen de solicitud no permitido.' ), 403 );
		}

		$rate_source = ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) . '|' . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
		$rate_key = 'ccl_consent_rate_' . substr( hash_hmac( 'sha256', $rate_source, wp_salt( 'nonce' ) ), 0, 32 );
		$rate = (int) get_transient( $rate_key );
		if ( $rate >= 30 ) {
			wp_send_json_error( array( 'message' => 'Límite temporal alcanzado.' ), 429 );
		}
		set_transient( $rate_key, $rate + 1, HOUR_IN_SECONDS );

		$settings   = Util::settings();
		$categories = isset( $_POST['categories'] ) ? (array) wp_unslash( $_POST['categories'] ) : array();
		$allowed    = array( 'necessary', 'functional', 'analytics', 'marketing' );
		$categories = array_values( array_intersect( array_map( 'sanitize_key', $categories ), $allowed ) );

		if ( ! in_array( 'necessary', $categories, true ) ) {
			$categories[] = 'necessary';
		}

		$uuid = isset( $_POST['uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['uuid'] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9\-]{36}$/i', $uuid ) ) {
			$uuid = wp_generate_uuid4();
		}

		$status  = isset( $_POST['status'] ) && 'revoked' === sanitize_key( wp_unslash( $_POST['status'] ) ) ? 'revoked' : 'granted';
		$created = Util::now_mysql();
		$agent   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$finger  = hash_hmac( 'sha256', $agent . '|' . $ip, wp_salt( 'nonce' ) );
		$payload = wp_json_encode( array( 'uuid' => $uuid, 'version' => $settings['consent_version'], 'categories' => $categories, 'status' => $status, 'created_at' => $created ) );
		$proof   = hash_hmac( 'sha256', (string) $payload, wp_salt( 'auth' ) );

		global $wpdb;
		$wpdb->insert(
			Database::table( 'consents' ),
			array(
				'uuid'             => $uuid,
				'consent_version'  => sanitize_text_field( $settings['consent_version'] ),
				'categories'       => wp_json_encode( $categories ),
				'status'           => $status,
				'source'           => 'banner',
				'fingerprint_hash' => $finger,
				'proof_hash'       => $proof,
				'created_at'       => $created,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		Audit::log( 'consent_recorded', 'consent', $uuid, array( 'status' => $status, 'categories' => $categories ) );
		wp_send_json_success( array( 'uuid' => $uuid, 'categories' => $categories, 'status' => $status ) );
	}

	public function filter_script_tag( string $tag, string $handle, string $src ): string {
		if ( is_admin() || empty( $src ) ) {
			return $tag;
		}

		$settings = Util::settings();
		$rules             = $this->parse_rules( (string) $settings['script_rules'] );
		$normalized_handle = sanitize_key( $handle );
		if ( ! isset( $rules[ $normalized_handle ] ) || 'necessary' === $rules[ $normalized_handle ] ) {
			return $tag;
		}

		$category = $rules[ $normalized_handle ];
		if ( $this->has_category( $category ) ) {
			return $tag;
		}

		$replacement = sprintf(
			'<script type="text/plain" data-ccl-blocked="1" data-ccl-category="%1$s" data-ccl-handle="%2$s" data-ccl-src="%3$s"></script>',
			esc_attr( $category ),
			esc_attr( $handle ),
			esc_url( $src )
		);
		return $replacement;
	}

	private function same_origin_request(): bool {
		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$source = '';
		if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
			$source = esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$source = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}
		$source_host = $source ? wp_parse_url( $source, PHP_URL_HOST ) : '';
		return $home_host && $source_host && strtolower( (string) $home_host ) === strtolower( (string) $source_host );
	}

	private function parse_rules( string $raw ): array {
		$rules = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $handle, $category ) = array_map( 'trim', explode( '=', $line, 2 ) );
			$category = sanitize_key( $category );
			if ( $handle && in_array( $category, array( 'necessary', 'functional', 'analytics', 'marketing' ), true ) ) {
				$rules[ sanitize_key( $handle ) ] = $category;
			}
		}
		return $rules;
	}

	private function has_category( string $category ): bool {
		if ( 'necessary' === $category ) {
			return true;
		}
		if ( empty( $_COOKIE['ccl_consent'] ) ) {
			return false;
		}
		$decoded = json_decode( base64_decode( sanitize_text_field( wp_unslash( $_COOKIE['ccl_consent'] ) ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return is_array( $decoded ) && ! empty( $decoded['categories'] ) && in_array( $category, (array) $decoded['categories'], true );
	}
}
