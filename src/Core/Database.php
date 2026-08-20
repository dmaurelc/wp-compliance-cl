<?php
namespace WPComplianceCL\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Database {
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'ccl_' . $name;
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$sql = array();
		$sql[] = 'CREATE TABLE ' . self::table( 'treatments' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			purpose text NOT NULL,
			data_categories text NOT NULL,
			data_subjects text NOT NULL,
			lawful_basis varchar(80) NOT NULL,
			retention varchar(190) NOT NULL DEFAULT '',
			recipients text NULL,
			sensitive tinyint(1) NOT NULL DEFAULT 0,
			large_scale tinyint(1) NOT NULL DEFAULT 0,
			automated_decisions tinyint(1) NOT NULL DEFAULT 0,
			public_monitoring tinyint(1) NOT NULL DEFAULT 0,
			dpia_status varchar(30) NOT NULL DEFAULT 'not_assessed',
			status varchar(30) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lawful_basis (lawful_basis),
			KEY status (status)
		) $charset";

		$sql[] = 'CREATE TABLE ' . self::table( 'providers' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			service varchar(190) NOT NULL DEFAULT '',
			role varchar(40) NOT NULL DEFAULT 'processor',
			data_categories text NULL,
			purpose text NULL,
			country varchar(120) NOT NULL DEFAULT '',
			subprocessors text NULL,
			international_transfer tinyint(1) NOT NULL DEFAULT 0,
			transfer_mechanism varchar(190) NOT NULL DEFAULT '',
			dpa_status varchar(30) NOT NULL DEFAULT 'unknown',
			document_url text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY dpa_status (dpa_status)
		) $charset";

		$sql[] = 'CREATE TABLE ' . self::table( 'rights' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reference varchar(40) NOT NULL,
			right_type varchar(40) NOT NULL,
			requester_name varchar(190) NOT NULL,
			requester_email varchar(190) NOT NULL,
			details longtext NULL,
			status varchar(40) NOT NULL DEFAULT 'received',
			identity_verified tinyint(1) NOT NULL DEFAULT 0,
			received_at datetime NOT NULL,
			due_at datetime NOT NULL,
			extended_at datetime NULL,
			responded_at datetime NULL,
			response longtext NULL,
			proof_hash varchar(64) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY status (status),
			KEY due_at (due_at)
		) $charset";

		$sql[] = 'CREATE TABLE ' . self::table( 'right_events' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			right_id bigint(20) unsigned NOT NULL,
			event_type varchar(60) NOT NULL,
			message text NULL,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY right_id (right_id)
		) $charset";

		$sql[] = 'CREATE TABLE ' . self::table( 'consents' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(36) NOT NULL,
			consent_version varchar(40) NOT NULL,
			categories text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'granted',
			source varchar(60) NOT NULL DEFAULT 'banner',
			fingerprint_hash varchar(64) NOT NULL DEFAULT '',
			proof_hash varchar(64) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY uuid (uuid),
			KEY created_at (created_at)
		) $charset";

		$sql[] = 'CREATE TABLE ' . self::table( 'breaches' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL,
			detected_at datetime NOT NULL,
			nature text NOT NULL,
			data_categories text NULL,
			affected_estimate int(11) unsigned NOT NULL DEFAULT 0,
			effects text NULL,
			risk_level varchar(30) NOT NULL DEFAULT 'pending',
			measures longtext NULL,
			notified_agency tinyint(1) NOT NULL DEFAULT 0,
			notified_subjects tinyint(1) NOT NULL DEFAULT 0,
			evidence text NULL,
			status varchar(30) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY risk_level (risk_level)
		) $charset";

		$sql[] = 'CREATE TABLE ' . self::table( 'audit' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(100) NOT NULL,
			object_type varchar(80) NOT NULL DEFAULT '',
			object_id varchar(80) NOT NULL DEFAULT '',
			context longtext NULL,
			integrity_hash varchar(64) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY action (action),
			KEY created_at (created_at)
		) $charset";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
}
