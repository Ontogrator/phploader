<?php

/*
  given an input source, for each line:

   1. normalise it

         - convert whatever CSV format into "id", 
           "terminizer-input", "taxonomy-input" and 
           "data" sections )

   2. terminizer it

   3. taxonomize it

   4. store data and associated ontological terms
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

$do_data_load       = false;

$do_termininization = false;
$do_taxonimization  = false;

$wipe_hits = false;
$wipe_entries = false;

$quit_after_wipe = true;

if( $argc < 4 )
  {
    die( 'usage: ' . $argv[ 0 ] . "  action  data-source-name  file-name\n\n" .
	 "   actions: load-only, lookup-then-load, wipe-hits,\n" . 
	 "            wipe-entries, wipe-hits-and-entries\n\n" );
  }

$mode = $argv[ 1 ];

if( $mode == 'load-only' )
  {
    $do_termininization = false;
    $do_taxonimization = false;
    $do_data_load = true;
  }
else
  {
    if( $mode == 'wipe-hits' )
      {
	$wipe_hits = true;
	$quit_after_wipe = true;
      }
    else
      {
	if( $mode == 'lookup-then-load' )
	  {
	    $wipe_hits = true;
	    $wipe_entries = true;
	    $quit_after_wipe = false;
	    $do_termininization = true;
	    $do_taxonimization = true;
	    $do_data_load = true;
	  }
	else
	  {
	    if( $mode == 'wipe-entries' )
	      {
		$wipe_entries = true;
		$quit_after_wipe = true;
	      }
	    else
	      {
		if( $mode == 'wipe-hits-and-entries' )
		  {
		    $wipe_hits = true;
		    $wipe_entries = true;
		    $quit_after_wipe = true;
		  }
		else
		  {
		    die( "Unrecognised action '" . $argv[ 1 ] . "'\n" );
		  }
	      }
	  }
      }
  }

$data_source_name = $argv[ 2 ];

if( isset( $data_source_infos[ $data_source_name ] ) == false )
  {
    die( "Unrecognised data-source-name '" . $data_source_name . "'\n" );
  }

$file_name = $argv[ 3 ];

$handle = fopen( $file_name, "r" );

if( $handle == null )
  {
    die( "Unable to open file '" . $file_name . "'\n" );
  }

$data_source_wrapper = instantiate_data_source_from_name( $data_source_name );

if( $data_source_wrapper == null )
  {
   die( "Unable to build wrapper for '" . $data_source_name . "'\n" );
  }

$terminizer  = new Terminizer();
$taxonomizer = new Taxonomizer();

$database_utils = new DatabaseUtils();

$database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . DATABASE_NAME );

if ( PEAR::isError( $database_connection ) ) { die( $database_connection->getMessage() ); }

// wipe out any existing terminizer hits
//
if( $wipe_hits )
  {
    $database_utils->remove_terminizer_hits( $database_connection, $data_source_name );

    $database_utils->remove_taxonomizer_hits( $database_connection, $data_source_name );
  }


// and remove the actual data
//
if( $wipe_entries )
  {
    $database_utils->empty_table( $database_connection, $data_source_name . "_entry" );
  }

// and possibly stop here
//
if( $wipe_hits || $wipe_entries )
  {
    if( $quit_after_wipe )
      {
	exit( 1 );
      }
  }

$skip_lines = $data_source_infos[ $data_source_name ][ "skip_header_lines" ];

while( ! feof( $handle ) ) 
  {
    $line = chop( fgets( $handle, 65535 ) );
    
    if( $skip_lines > 0 )
      {
	$skip_lines--;
      }
    else
      {
	if( strlen( trim( $line ) ) > 0 )
	  {
	    // split the line using the custom tokenizer
	    //
	    $parts = $data_source_wrapper->tokenize_line( $line );
	    
	    // invoke the terminizer
	    //
	    $terminizer_hit_count = 0;

	    if( $do_termininization )
	      {
		$terminizer_hits = $terminizer->terminize( $database_connection, $data_source_wrapper->get_terminizer_input( $parts ) );
		
		
		// count (and optionally list) the relevant terminizer hits..
		//
		
		
		foreach( $ontology_infos as $short_name => $ontology_info )
		  {
		    if( isset( $terminizer_hits[ $short_name ] ) )
		      {
			foreach( $terminizer_hits[ $short_name ] as $hit )
			  {
			    $terminizer_hit_count++;
			    
			    //echo "   -> " . $short_name . " " . $hit . "\n";
			  }
		      }
		  }
	      }
	    
	    $taxonomy_hit_count = 0;

	    // taxonomize if req'd....

	    if( $do_taxonimization )
	      {
		$taxonomizer_hits = $taxonomizer->taxonomize( $data_source_wrapper->get_taxonomy_input( $parts ) );
		
		foreach( $taxonomizer_hits as $taxonomy_chain )
		  {
		    $taxonomy_hit_count++;
		    
		    if( $verbosity > 2 )
		      {
			echo "TAX:";
			
			$is_first = true;
			
			foreach( $taxonomy_chain as $taxonomy_element )
			  {
			    if( $is_first )
			      {
				$is_first = false;
			      }
			    else
			      {
				echo '->';
			      }
			    echo $taxonomy_element;
			  }
			
			echo "\n";
		      }
		  }
	      }

	    // and store it all away for future generations to enjoy
	    
	    // store the entry in the database
	    //
	    if( $do_data_load )
	      {
		$database_utils->store_entry( $database_connection, 
					      $data_source_name, 
					      $data_source_wrapper, 
					      $parts );
	      }
	    
	    if( $taxonomy_hit_count > 0 )
	      {
		// store the taxonomizer hits in the database
		//
		$database_utils->store_taxonomizer_hits( $database_connection, 
							 $data_source_name, 
							 $parts[ "id" ], 
							 $taxonomizer_hits );
	      }
	    
	    if( $terminizer_hit_count > 0 )
	      {
		// then store the terminizer hits in the database
		//
		$database_utils->store_terminizer_hits( $database_connection, 
							$data_source_name, 
							$parts[ "id" ],
							$terminizer_hits );
	      }
	    
	    echo $parts[ 'id' ] . ' - terms:' . $terminizer_hit_count . ' (' . $terminizer->get_cache_stats() . ') taxa:' . $taxonomy_hit_count . ' (' . $taxonomizer->get_cache_stats() . ")\n";
	  }
      }
  }

fclose( $handle );
$database_connection->disconnect();

?>
