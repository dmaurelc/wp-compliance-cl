<?php
namespace WPComplianceCL\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	public static function activate(): void {
		Migrations::run();

		if ( false === get_option( 'ccl_settings', false ) ) {
			add_option(
				'ccl_settings',
				array(
					'organisation_name'   => get_bloginfo( 'name' ),
					'rut'                 => '',
					'address'             => '',
					'representative'      => '',
					'privacy_email'       => get_option( 'admin_email' ),
					'dpo_name'            => '',
					'dpo_email'           => '',
					'consent_enabled'     => 1,
					'consent_version'     => '1.0',
					'accent_color'        => '#1f6f5c',
					'script_rules'        => '',
					'security_measures'   => '',
					'breach_procedure_ready' => 0,
					'policy_page_id'      => 0,
					'cookie_page_id'      => 0,
					'rights_page_id'      => 0,
				),
				'',
				false
			);
		}

		update_option( 'ccl_version', WPCCL_VERSION, false );
		update_option( 'ccl_db_version', WPCCL_DB_VERSION, false );
		update_option( 'ccl_law_pack_version', WPCCL_LAW_PACK_VERSION, false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
