<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

// Privacy-first default: preserve compliance records on uninstall.
// Define WPCCL_PURGE_ON_UNINSTALL as true in wp-config.php before uninstalling to remove all plugin data.
// CCL_PURGE_ON_UNINSTALL is kept as a v0.1.0 compatibility alias.
$purge = ( defined( 'WPCCL_PURGE_ON_UNINSTALL' ) && true === WPCCL_PURGE_ON_UNINSTALL ) || ( defined( 'CCL_PURGE_ON_UNINSTALL' ) && true === CCL_PURGE_ON_UNINSTALL );
if ( ! $purge ) { return; }

global $wpdb;
foreach ( array( 'treatments', 'providers', 'rights', 'right_events', 'consents', 'breaches', 'audit' ) as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'ccl_' . $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
delete_option( 'ccl_settings' );
delete_option( 'ccl_version' );
delete_option( 'ccl_law_pack_version' );
delete_option( 'ccl_db_version' );
delete_option( 'ccl_last_migration_version' );
delete_option( 'ccl_db_migration_error' );
