<?php
namespace WPComplianceCL\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract reserved for v0.2 update providers (GitHub Releases first,
 * self-hosted update service later) without coupling WordPress UI to a source.
 */
interface UpdateProviderInterface {
	public function boot(): void;
}
