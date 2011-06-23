<?php

/*
  given an input source, for each line:

   1. normalise it

         - convert whatever CSV format into "id", 
           "terminizer-input", "taxonomy-input" and 
           "data" sections )

   2. terminizer it

   3. taxonomize it

   4. output the results to a set of text files
*/

include_once( 'config.php' );

include_once( "gold.php" );
include_once( "silva.php" );
include_once( "journal.php" );
include_once( "camera.php" );
include_once( "straininfo.php" );

require_once ( 'terminize.php' );

require_once ( 'taxonomize.php' );

require_once ( 'database-utils.php' );

$verbosity = 0;

$do_termininization = true;
$do_taxonimization  = true;
$do_data_load       = true;


if( $argc < 4 )
  {
    die( 'usage: ' . $argv[ 0 ] . " data-source-name  file-name  result-name\n" );
  }

$data_source_name = $argv[ 1 ];

if( isset( $data_source_infos[ $data_source_name ] ) == false )
  {
    die( "Unrecognised data-source-name '" . $data_source_name . "'\n" );
  }

$input_file_name  = $argv[ 2 ];

$input_handle = fopen( $input_file_name, "r" );

if( $input_handle == null )
  {
    die( "Unable to open file '" . $input_file_name . "'\n" );
  }

$data_source_wrapper = instantiate_data_source_from_name( $data_source_name );

if( $data_source_wrapper == null )
  {
   die( "Unable to build wrapper for '" . $data_source_name . "'\n" );
  }

$output_handles = array();

$output_file_prefix  = $argv[ 3 ];

if( strlen( trim( $output_file_prefix ) ) == 0 )
  {
    die( "No result name specified" );
  }

foreach( $ontology_infos as $short_name => $ontology_info )
  {
    $output_file_name = $output_file_prefix . '.' . $short_name . '.data';

    $output_handle = fopen( $output_file_name, 'w' );
    
    if( ! $output_handle )
      {
	die( "Unable to open output file '" . $output_file_name . "'" );
      }

    $output_handles[ $short_name ] = $output_handle ;
  }



$terminizer  = new Terminizer();
$taxonomizer = new Taxonomizer();

// configure the taxonomizer to output the chains to a file (as we aren't capturing them to a database)

$tax_chain_output_handle = fopen( $output_file_prefix . '.TAX_CHAIN.data', 'w' );

if( ! $tax_chain_output_handle )
  {
    die( "Unable to open output file '" . $output_file_prefix . '.TAX_CHAIN.data' . "'" );
  }

$taxonomizer->set_report_handle( $tax_chain_output_handle );


$database_utils = new DatabaseUtils();

$database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . DATABASE_NAME );

if ( PEAR::isError( $database_connection ) ) { die( $database_connection->getMessage() ); }


$skip_lines = $data_source_infos[ $data_source_name ][ "skip_header_lines" ];

$count = 1;

while( ! feof( $input_handle ) ) 
  {
    $line = chop( fgets( $input_handle, 65535 ) );
    
    if( $skip_lines > 0 )
      {
	$skip_lines--;
      }
    else
      {
	if( strlen( trim( $line ) ) > 0 )
	  {
	    // parse the line using the tokenizer for this data source
	    //
	    $parts = $data_source_wrapper->tokenize_line( $line );
	    

	    if( $verbosity > 0 )
	      {
		foreach( $parts as $key => $value )
		  {
		    echo $key . " : " . $value . "\n";
		    
		  }

		echo "Terminizer: " . $data_source_wrapper->get_terminizer_input( $parts ) . "\n";
		echo "Taxonomizer: " . $data_source_wrapper->get_taxonomy_input( $parts ) . "\n";
	      }


	    // invoke the terminizer
	    //
	    $terminizer_hits = $terminizer->terminize( $database_connection, $data_source_wrapper->get_terminizer_input( $parts ) );
	    
	    // and write results to file
	    //
	    $id = $parts[ 'id' ];

	    $terminizer_hit_count = 0;

	    foreach( $ontology_infos as $short_name => $ontology_info )
	      {
		if( isset( $terminizer_hits[ $short_name ] ) )
		  {
		    $handle = $output_handles[ $short_name ];
			
		    foreach( $terminizer_hits[ $short_name ] as $hit )
		      {
			$terminizer_hit_count++;
			fwrite( $handle, $id . "\t" . $hit . "\n" );
		      }
		  }
	      }

	    // invoke the taxonomizer
	    //
	    $taxonomizer_hits = $taxonomizer->taxonomize( $data_source_wrapper->get_taxonomy_input( $parts ) );
	    
	    // and write results to file
	    //
	    $taxonomy_hit_count = 0;

	    if( $taxonomizer_hits != null )
	      {
		$handle = $output_handles[ 'TAX' ];
		
		foreach( $taxonomizer_hits as $taxonomy_chain )
		  {
		    foreach( $taxonomy_chain as $taxonomy_element )
		      {
			$taxonomy_hit_count++;
			fwrite( $handle, $id . "\t" . $taxonomy_element . "\n" );
		      }
		  }
	      }
	    
	    echo $count . ' : ' . $parts[ 'id' ] . ' - terms:' . $terminizer_hit_count . '(' . $terminizer->get_cache_stats() . ') taxa:' . $taxonomy_hit_count . ' (' . $taxonomizer->get_cache_stats() . ")\n";
 	  }
      }

    $count++;
  }


// shut everything down cleanly.

fclose( $input_handle );

foreach( $output_handles as $key => $handle )
  {
    fclose( $handle );
  }

$database_connection->disconnect();

?>
