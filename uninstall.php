<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

// Privacy-first default: preserve compliance records on uninstall.
// Define WPCCL_PURGE_ON_UNINSTALL as true in wp-config.php before uninstalling to remove all plugin data.
if ( ! defined( 'WPCCL_PURGE_ON_UNINSTALL' ) || true !== WPCCL_PURGE_ON_UNINSTALL ) { return; }

global $wpdb;
foreach ( array( 'treatments', 'providers', 'rights', 'right_events', 'consents', 'breaches', 'audit' ) as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'ccl_' . $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
delete_option( 'ccl_settings' );
delete_option( 'ccl_version' );
delete_option( 'ccl_law_pack_version' );
