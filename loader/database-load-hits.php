<?php

require_once 'MDB2.php';

include_once( 'config.php' );

include_once( 'database-utils.php' );

$database_utils = new DatabaseUtils();

if( $argc < 3 )
  {
    die( 'usage: ' . $argv[ 0 ] . " data-source-name  result-name\n" );
  }

$data_source_name = $argv[ 1 ];

if( isset( $data_source_infos[ $data_source_name ] ) == false )
  {
    die( "Unrecognised data-source-name '" . $data_source_name . "'\n" );
  }

$input_file_prefix  = $argv[ 2 ];

$database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . DATABASE_NAME );

if ( PEAR::isError( $database_connection ) ) { die( $database_connection->getMessage() ); }

foreach(  $ontology_infos as $short_name => $ontology_info )
  {
    $input_file_name = $input_file_prefix . '.' . $short_name . '.data';

    // the 'IGNORE' prevents duplicate rows from appearing

    // '\t' is assumed as the delimiter by default

    $sql = 
      'LOAD DATA INFILE \'' . realpath( $input_file_name ) . '\'' . 
      ' IGNORE INTO TABLE ' . $data_source_name . '_' . $short_name . '_hit';

    echo "\n" . $sql . "\n";
    
    
    $result = $database_connection->query( $sql );
    
    if ( PEAR::isError( $result ) )  
      {
	die( $result->getMessage() );
      }
    

  }

?>
