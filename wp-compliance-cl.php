<?php
/**
 * Plugin Name:       WP Compliance CL
 * Plugin URI:        https://github.com/dmaurelc/wp-compliance-cl
 * Description:       Herramientas técnicas y organizativas para apoyar el cumplimiento de la Ley 21.719 de protección de datos personales en Chile.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            WP Compliance CL
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-compliance-cl
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPCCL_VERSION', '0.1.1' );
define( 'WPCCL_DB_VERSION', '2' );
define( 'WPCCL_LAW_PACK_VERSION', '2026-08-20' );
define( 'WPCCL_FILE', __FILE__ );
define( 'WPCCL_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCCL_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WPComplianceCL\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = WPCCL_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'WPComplianceCL\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPComplianceCL\\Core\\Activator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		WPComplianceCL\Core\Plugin::instance()->boot();
	}
);
