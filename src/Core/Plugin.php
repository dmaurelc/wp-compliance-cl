<?php
namespace WPComplianceCL\Core;

use WPComplianceCL\Admin\Admin;
use WPComplianceCL\Frontend\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		load_plugin_textdomain( 'wp-compliance-cl', false, dirname( plugin_basename( WPCCL_FILE ) ) . '/languages' );

		// Self-heal schema changes on normal plugin load; activation alone is not enough for upgrades.
		Migrations::maybe_run();

		new Consent();
		new Rights();
		new Documents();

		if ( is_admin() ) {
			new Admin();
		}

		new Frontend();
	}
}
