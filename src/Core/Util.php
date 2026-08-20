<?php
namespace WPComplianceCL\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Util {
	public static function settings(): array {
		$defaults = array(
			'organisation_name' => get_bloginfo( 'name' ),
			'rut' => '', 'address' => '', 'representative' => '',
			'privacy_email' => get_option( 'admin_email' ),
			'dpo_name' => '', 'dpo_email' => '',
			'consent_enabled' => 1, 'consent_version' => '1.0',
			'accent_color' => '#1f6f5c', 'script_rules' => '', 'security_measures' => '', 'breach_procedure_ready' => 0,
			'policy_page_id' => 0, 'cookie_page_id' => 0, 'rights_page_id' => 0,
		);
		return wp_parse_args( (array) get_option( 'ccl_settings', array() ), $defaults );
	}

	public static function admin_capability(): string {
		return (string) apply_filters( 'ccl_admin_capability', 'manage_options' );
	}

	public static function now_mysql(): string {
		return current_time( 'mysql', true );
	}

	public static function right_label( string $type ): string {
		$labels = array(
			'access' => 'Acceso', 'rectification' => 'Rectificación', 'deletion' => 'Supresión',
			'objection' => 'Oposición', 'portability' => 'Portabilidad', 'blocking' => 'Bloqueo',
		);
		return $labels[ $type ] ?? ucfirst( $type );
	}

	public static function status_label( string $status ): string {
		$labels = array(
			'received' => 'Recibida', 'verified' => 'Identidad verificada', 'in_progress' => 'En proceso',
			'responded' => 'Respondida', 'closed' => 'Cerrada', 'rejected' => 'Rechazada',
		);
		return $labels[ $status ] ?? ucfirst( $status );
	}
}
