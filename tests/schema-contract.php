<?php
$database = file_get_contents( dirname( __DIR__ ) . '/src/Core/Database.php' );
$required = array( 'treatments', 'providers', 'rights', 'right_events', 'consents', 'breaches', 'audit' );

foreach ( $required as $table ) {
	if ( false === strpos( $database, "self::table( '" . $table . "' )" ) ) {
		fwrite( STDERR, "Missing schema definition/reference for {$table}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $database, 'public static function health()' ) ) {
	fwrite( STDERR, "Database health contract is missing.\n" );
	exit( 1 );
}

echo "Schema contract OK.\n";
