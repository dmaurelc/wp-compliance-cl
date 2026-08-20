<?php
namespace WPComplianceCL\AI\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security boundary reserved for BYOK in v0.2.
 * Implementations MUST encrypt at rest and MUST NOT expose a read-secret API
 * to browser-facing code.
 */
interface SecretStoreInterface {
	public function available(): bool;
	public function store( string $name, string $secret ): bool;
	public function has( string $name ): bool;
	public function delete( string $name ): bool;
}
