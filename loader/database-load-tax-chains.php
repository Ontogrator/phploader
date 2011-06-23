<?php

require_once 'MDB2.php';

include_once( 'config.php' );

include_once( 'database-utils.php' );

$database_utils = new DatabaseUtils();

if( $argc < 2 )
  {
    die( 'usage: ' . $argv[ 0 ] . " file-name\n" );
  }

$file_name = $argv[ 1 ];

$handle = fopen( $file_name, "r" );

if( $handle == null )
  {
    die( "Unable to open file '" . $file_name . "'\n" );
  }

$database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . DATABASE_NAME );

if ( PEAR::isError( $database_connection ) ) { die( $database_connection->getMessage() ); }

$count = 0;

while( ! feof( $handle ) ) 
  {
    $line = chop( fgets( $handle, 65535 ) );

    $bits = split( "\t", $line );

    $term = $bits[ 0 ];
    $parent = $bits [ 1 ];

    if( strlen( trim( $term ) ) > 0 )
      {
	// check whether the term and its parent already exist...
	
	$database_utils->possibly_insert_tax_term( $database_connection, $term, $parent  );
	
	echo ++$count . ': ' . $term . ' => ' . $parent . "\n";

	if( strlen( trim( $parent ) ) == 0 )
	  {
	    echo '** ' . $term . "\n";
	  }
      }
  }

?>
