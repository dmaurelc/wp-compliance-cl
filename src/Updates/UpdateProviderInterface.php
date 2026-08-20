<?php
namespace WPComplianceCL\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for update providers without coupling the WordPress UI to a source.
 * GitHub Releases is the default provider from v0.1.2 onward.
 */
interface UpdateProviderInterface {
	public function boot(): void;
}
