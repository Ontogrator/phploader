<?php

include_once 'config.php' ;


include_once( "gold.php" );
include_once( "silva.php" );
include_once( "journal.php" );


require_once 'MDB2.php';

// ===================================================================================
//
// takes a ontology represented in the omixed-script-processor's input format
// and converts it to a pair of tabular files (one listing the terms
// and their defintions and other other containing the links between
// terms) which are then fed into MySQL via the bulk upload facility (LOAD DATA INFILE)
//
// we do the data collection and parent counting in memory first, then generate the 
// loading files afterwards as MySQL takes forever to run any joins across the 
// GAZ_term and GAZ_parent table
//
// ===================================================================================

function start_element_handler( $parser, $name, $attribs )
{
  global $parse_state; // i know... but there isn't an easy way to pass state into the handler

  //echo 'start: ' . $name . "\n";
  
  if( $name == 'Create' )
    {
 
      $parse_state[ 'item_name' ] = extract_item_name_from_item_id( $attribs[ 'itemID' ] );

      $parse_state[ 'is_not_synonym' ] = ( strpos( $attribs[ 'itemID' ], 'synonymItemType' ) === FALSE );

      // reset all of the attribute and link stores:

      $parse_state[ 'ID' ] = ''; 
      $parse_state[ 'label' ]  = ''; 
      $parse_state[ 'definition' ]  = ''; 

      foreach( $parse_state[ 'interesting_links' ] as $interesting_link )
	{
	  $parse_state[ $interesting_link ] = array();
	}

    } 

  if( $name == 'Attribute' )
    {
      $parse_state[ 'current_attribute' ] = $attribs[ 'name' ];

      //echo 'current_attribute:' . $parse_state[ 'current_attribute' ] . "\n";

    }

  if( $name == 'Link' )
    {
      $link_name   = $attribs[ 'name' ];
      $link_target = extract_item_name_from_item_id( $attribs[ 'itemID' ] );

      $parse_state[ $link_name ][] = $link_target;
    }
}


// we only care about </Create> at which point we have all the stuff we
// need to create the item and its links
//
function end_element_handler( $parser, $name )
{
  global $parse_state;

  if( $name == 'Create' )
    {

      if( $parse_state[ 'is_not_synonym' ] )
	{
	  // we have all of the info in place to create the entry

	  create_database_entries();
	}
      else
	{
	  //echo 'ignored synonym ' . $parse_state[ 'item_name' ] . "\n";
	}
    }
}

// store the text into the $parse_state dumping ground. the key will
// be the value of 'current_attribute'
//
function text_chunk_handler( $parser, $text )
{
  global $parse_state;
  
  if( array_key_exists( 'current_attribute', $parse_state ) )
    {
      $current_attribute = $parse_state[ 'current_attribute' ];
    
      $parse_state[ $current_attribute ] .= $text;
    }
}


// ===================================================================================

// takes all the information gathered about an item and outputs
// relevant rows to the bulk-load file

function create_database_entries()
{
  global $term_list, $parent_count, $child_count, $file_handle_for_parent, $parse_state, $last_microtime, $progress_counter;

  $name =  $parse_state[ 'item_name' ];
  $id = trim( $parse_state[ 'ID' ] );
  $definition = trim( $parse_state[ 'definition' ] );

  if( strlen( $name ) > 255 )
    {
      die( 'Name "' . $name . '" too long' . "\n" );
    }

  if( strlen( $definition ) > 1023 )
    {
      $definition = substr( $definition, 0, 1023 );
    }


  // the row for the term in X_term

  $term_list[] = array( $name, $definition );

  // echo $name . "\n  id:" . $id . "\n";
 
  // one row in X_parent for each of the outgoing links
  // ( and increment the parent counts too )

  foreach( $parse_state[ 'interesting_links' ] as $link )
    {
      foreach( $parse_state[ $link ] as $target )
	{
	  //echo $name . ' ' . $link . ' ' . $target . "\n";

	  // we can write the parent information out on-the-fly rather than storing it...

	  fwrite( $file_handle_for_parent, '"' . escape_quotes( $name ) . '","' . escape_quotes( $link ) . '","' . escape_quotes( $target  ) . '"' . "\n" );

	  // and increment the parent count for when we come to write the term information

	  $parent_count[ $name ] = 1;

	  $child_count[ $target ] = 1;

	  /*

	  if( array_key_exists( $name, $parent_count ) )
	    {
	      $parent_count[ $name ]++;
	    }
	  else
	    {
	      $parent_count[ $name ] = 1;
	    }
	  
	  */


	}
    }
  
  if( (++$progress_counter % 250 ) == 0 )
    {
      $now_microtime = microtime( true );

      $elapsed_seconds = $now_microtime - $last_microtime;
      
      // we've done 250 terms in that time, how many per second?

      $terms_per_second = 250 / $elapsed_seconds;

      echo date('H:i:s') . ' done ' . $progress_counter . ' @ ' . $terms_per_second . " t/s\n";

      $last_microtime = $now_microtime;
    }
}



function write_terms( $term_list, $parent_count, $ontology )
{
  global $parent_count, $child_count;

  $file_handle_for_term   = @fopen( $ontology . '-term.dat', 'wb');
  
  foreach( $term_list as $term_info )
    {
      $term_name = $term_info[ 0 ];
      
      $has_parent = isset( $parent_count[ $term_name ] ) ? 1 : 0;
      $has_child  = isset( $child_count[ $term_name ] ) ? 1 : 0;

      fwrite( $file_handle_for_term, '"' . escape_quotes( $term_name ) . '","' . $has_parent .'","' . $has_child .'","' . escape_quotes( $term_info[ 1 ] ) . '"' . "\n" );

    }
  
  fclose( $file_handle_for_term );

}


// ===================================================================================

function escape_quotes( $input )
{
  return str_replace( '"', '\\"', $input );
}

function extract_item_name_from_item_id( $item_id )
{
  // grab the itemID 

  // note that as we are using the files designed for loading into
  // terminizer, the domain,itemType etc will be parameterised but we
  // dont really care as we only want the itemName component
  
  $id_parts = split( "/", $item_id );
  
  $n_parts = count( $id_parts );
  
  return(  $n_parts == 5 ) ? $id_parts[ 4 ] : implode( "/", array_slice( $id_parts, 4 ) );
}


// ===================================================================================


if( $argc < 3 )
  {
    die( 'usage: ' . $argv[ 0 ] . " ontology-name file-name\n" );
  }

$ontology      = $argv[ 1 ];
$file_name     = $argv[ 2 ];

$progress_counter = 0;

$database_connection = null;

$database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . DATABASE_NAME );

$term_list = array();
$parent_count = array();
$child_count = array();
$parent_list = array();

$parse_state = array();

$parse_state[ 'interesting_links' ] = array( 'is_a', 'part_of', 'located_in', 'related_to' );

if (!( $file_pointer = @fopen( $file_name, 'rb') ) ) 
  {
    die( 'Cannot open ' . $file_name . "\n" );
  }


if ( PEAR::isError( $database_connection ) )  
  {
    die( $database_connection->getMessage() . "\n" );
  }

// wipe existing entries

$result = $database_connection->query( 'DELETE FROM ' . $ontology . '_term' );

if ( PEAR::isError( $result ) )  
  {
    die( $result->getMessage() . "\n");
  }

$result = $database_connection->query( 'DELETE FROM ' . $ontology . '_parent' );

if ( PEAR::isError( $result ) )  
  {
    die( $result->getMessage() . "\n");
  }


// setup the parser and the event handlers

$parser = xml_parser_create();

xml_set_element_handler( $parser, 'start_element_handler', 'end_element_handler' );

xml_set_character_data_handler( $parser, 'text_chunk_handler' );

xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, 0 );


// we are writing the parent links on the fly, so open the file now

$file_handle_for_parent = @fopen( $ontology . '-parent.dat', 'wb');

// and feed the input file to the parser one chunk at a time

$last_microtime = microtime( true );

while ( ( $data = fread( $file_pointer, 8192 ) ) ) 
  {
    if ( ! xml_parse( $parser, $data, feof( $file_pointer ) ) ) 
      {
	die( sprintf( 'XML error at line %d column %d',
		      xml_get_current_line_number( $parser ),
		      xml_get_current_column_number( $parser ) ) );
      }
  }

fclose( $file_pointer );

fclose( $file_handle_for_parent );

// now we have the parent counts, we can write the term list

write_terms( $term_list, $parent_count, $ontology );


// execute the LOAD INFILE commands to read from the tabular files

// note that we need the full path to the file and that in Windows, the \ delimiters need to be escaped

echo 'loading from: ' . realpath( $ontology . '-term.dat' ) . "\n";

$sql = 
  'LOAD DATA INFILE \'' . str_replace( '\\', '\\\\', realpath( $ontology . '-term.dat' ) ) . '\'' . 
  ' INTO TABLE ' . $ontology . '_term FIELDS TERMINATED BY \',\' ENCLOSED BY \'"\'';

echo "\n" . $sql . "\n";

$result = $database_connection->query( $sql );

if ( PEAR::isError( $result ) )  
  {
    die( $result->getMessage() );
  }

echo 'loading from: ' . realpath( $ontology . '-parent.dat' ) . "\n";

$sql = 
  'LOAD DATA INFILE \'' . str_replace( '\\', '\\\\', realpath( $ontology . '-parent.dat' ) ) . '\'' . 
  ' INTO TABLE ' . $ontology . '_parent FIELDS TERMINATED BY \',\' ENCLOSED BY \'"\'';

echo "\n" . $sql . "\n";

$result = $database_connection->query( $sql );

if ( PEAR::isError( $result ) )  
  {
    die( $result->getMessage() );
  }


// tidy up

unlink(  $ontology . '-term.dat' );
unlink(  $ontology . '-parent.dat' );


$database_connection->disconnect();

