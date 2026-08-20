<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/wp-compliance-cl.php' );
$plugin = file_get_contents( $root . '/src/Core/Plugin.php' );
$provider = file_get_contents( $root . '/src/Updates/GitHubReleaseProvider.php' );

$contracts = array(
	'Update URI header'          => 'Update URI:        https://github.com/dmaurelc/wp-compliance-cl',
	'provider implementation'    => 'implements UpdateProviderInterface',
	'native update hook'         => "update_plugins_github.com",
	'plugin information hook'    => "plugins_api",
	'authenticated download hook' => "upgrader_pre_download",
	'latest release endpoint'    => "/releases/latest",
	'checksum asset'             => "'.sha256'",
	'SHA-256 verification'       => "hash_file( 'sha256'",
	'external token constant'    => "WPCCL_GITHUB_TOKEN",
);

foreach ( $contracts as $label => $needle ) {
	$haystack = 'Update URI header' === $label ? $main : $provider;
	if ( false === strpos( $haystack, $needle ) ) {
		fwrite( STDERR, "Missing update provider contract: {$label}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $plugin, 'GitHubReleaseProvider' ) || false === strpos( $plugin, '->boot()' ) ) {
	fwrite( STDERR, "GitHubReleaseProvider is not booted by the plugin.\n" );
	exit( 1 );
}

echo "Update provider contract OK.\n";
