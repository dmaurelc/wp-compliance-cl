<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/wp-compliance-cl.php' );
$readme = file_get_contents( $root . '/readme.txt' );
$changelog = file_get_contents( $root . '/CHANGELOG.md' );

preg_match( '/^ \* Version:\s+([0-9.]+)/m', $main, $plugin_match );
preg_match( '/^Stable tag:\s+([0-9.]+)/m', $readme, $readme_match );

$plugin_version = $plugin_match[1] ?? '';
$stable_tag = $readme_match[1] ?? '';

if ( ! $plugin_version || $plugin_version !== $stable_tag ) {
	fwrite( STDERR, "Plugin Version and Stable tag do not match.\n" );
	exit( 1 );
}

if ( false === strpos( $main, "define( 'WPCCL_VERSION', '" . $plugin_version . "' );" ) ) {
	fwrite( STDERR, "WPCCL_VERSION does not match plugin header.\n" );
	exit( 1 );
}

if ( false === strpos( $changelog, '## ' . $plugin_version ) ) {
	fwrite( STDERR, "CHANGELOG does not contain the current version.\n" );
	exit( 1 );
}

echo "Release metadata OK: {$plugin_version}\n";
