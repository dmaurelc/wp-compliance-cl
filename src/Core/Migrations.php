<?php
namespace WPComplianceCL\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight database migration manager.
 *
 * v0.1.1 intentionally keeps migrations idempotent: dbDelta() is used to
 * repair missing tables/columns and the schema version is advanced only after
 * every required table can be found.
 */
final class Migrations {
	public static function maybe_run(): void {
		$current = (string) get_option( 'ccl_db_version', '0' );
		$health  = Database::health();

		if ( WPCCL_DB_VERSION === $current && empty( $health['missing'] ) ) {
			return;
		}

		self::run();
	}

	public static function run(): void {
		$before = Database::health();
		Database::install();
		$after = Database::health();

		if ( empty( $after['missing'] ) ) {
			update_option( 'ccl_db_version', WPCCL_DB_VERSION, false );
			update_option( 'ccl_version', WPCCL_VERSION, false );

			// Audit only after the audit table itself is available.
			if ( Database::exists( 'audit' ) && ( $before['missing'] || (string) get_option( 'ccl_last_migration_version', '' ) !== WPCCL_DB_VERSION ) ) {
				Audit::log(
					'database_migrated',
					'database',
					WPCCL_DB_VERSION,
					array(
						'missing_before' => array_values( $before['missing'] ),
						'plugin_version' => WPCCL_VERSION,
					)
				);
			}

			update_option( 'ccl_last_migration_version', WPCCL_DB_VERSION, false );
			delete_option( 'ccl_db_migration_error' );
			return;
		}

		// Do not claim a successful migration if the schema remains incomplete.
		update_option( 'ccl_db_migration_error', implode( ',', $after['missing'] ), false );
	}
}
