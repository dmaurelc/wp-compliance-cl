<?php
namespace WPComplianceCL\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Audit {
	public static function log( string $action, string $object_type = '', string $object_id = '', array $context = array() ): void {
		global $wpdb;
		$created = current_time( 'mysql', true );
		$actor   = get_current_user_id();
		$payload = wp_json_encode(
			array(
				'action'      => $action,
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'context'     => $context,
				'created_at'  => $created,
				'actor_id'    => $actor,
			)
		);
		$hash = hash_hmac( 'sha256', (string) $payload, wp_salt( 'auth' ) );

		$wpdb->insert(
			Database::table( 'audit' ),
			array(
				'actor_id'      => $actor,
				'action'        => $action,
				'object_type'   => $object_type,
				'object_id'     => $object_id,
				'context'       => $payload,
				'integrity_hash'=> $hash,
				'created_at'    => $created,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
