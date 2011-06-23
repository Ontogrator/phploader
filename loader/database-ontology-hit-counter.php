<?php


/*
  interface:

  
  database-ontology-hit-counter.php?ont=Gaz

   -> the body should be a list of one or more ontology terms from the ontology specified in 'ont=[x]'

   -> the response will the be count of the number of hits against those terms given the 
      filters currently in use, (i.e. those specified in the query params)

      

   e.g. how many hits for TAX='Chordata' given ( ENVO='poultry' OR ENVO='fish' ) ?

   
   SELECT COUNT(DISTINCT(JOURNAL_entry.id)) 
     FROM JOURNAL_entry,JOURNAL_TAX_hit,JOURNAL_ENVO_hit 
    WHERE JOURNAL_TAX_hit.name='Chordata'                                        // variable (values provided in message body)
      AND JOURNAL_entry.id=JOURNAL_TAX_hit.id                                    // [join logic]
      AND JOURNAL_entry.id=JOURNAL_ENVO_hit.id                                   // [join logic]
      AND ( JOURNAL_ENVO_hit.name='poultry' OR  JOURNAL_ENVO_hit.name='fish' )   // fixed (values provided in query string)


   in this case the variable is TAX and the fixed is ENVO

   

   test URL:

   http://localhost/ontogrator/database-ontology-hit-counter.php?ds=JOURNAL&ont=ENVO&GAZ_0=France&GAZ_1=Spain
   
*/

include_once( 'config.php' );

include_once( 'common.php' );

require_once 'MDB2.php';

$database_connection =& MDB2::connect( DB_SYSTEM_DSN . "/" . DATABASE_NAME );

if ( PEAR::isError( $database_connection ) )  
  {
    die( $database_connection->getMessage() );
  }

if( isset( $_GET[ 'ont' ] ) == false )
  {
    die( '!! - no ontology selector' );
  }

$search_ontology = $_GET[ 'ont' ];

if( array_search( $search_ontology, array( 'TAX','ENVO','MAT','GAZ' ) ) === false )
  {
    die( '!! - unrecognised ontology' );
  }

if( isset( $_REQUEST[ 'ds' ] ) === false )
  {
    die( 'Data source not specified' );
  }

$data_source = strtoupper( $_REQUEST[ 'ds' ] );

$data_source_table = null;

foreach( $data_source_infos as $key => $info )
  {
    if( $data_source == $key )
      {
	$data_source_table = $key . '_entry';
      }
  }

if( $data_source_table == null )
  {
    die( 'Unrecognised data source "' . $data_source . '"' );
  }


$active_hit_table = $data_source . '_' . $search_ontology . '_hit';

$ontology_terms = get_ontology_terms();

// when we only have a single term to search on (i.e. no filters are active), we can use a special-case search

$filter_count = 0;

foreach( $ontology_infos as $ontology_short_name => $info )
  {
    if( isset( $ontology_terms[ $ontology_short_name ] ) )
      {
	$filter_count++;
      }
  }

    
if( $filter_count == 0 ) // special case
  {
    $sql = 'SELECT COUNT(DISTINCT( ' . $active_hit_table . '.id )) FROM ' . $active_hit_table . ' WHERE ' . $active_hit_table . '.name=?';
    
  }
else
  {
    // it gets more complicated when one or more filters are in use
    
    $sql = 'SELECT COUNT(DISTINCT( ' . $active_hit_table  . '.id)) FROM ' . $active_hit_table;
    
    foreach( $ontology_infos as $ontology_short_name => $info )
      {
	if( isset( $ontology_terms[ $ontology_short_name ] ) )
	  {
	    if( $ontology_short_name != $search_ontology )   // all except the 'fixed' ontology [why?]
	      {
		$sql .=',' .  $data_source . '_' . $ontology_short_name . '_hit';
	      }
	  }
      }
    
    $sql .= ' WHERE ' . $active_hit_table . '.name=?';      // this is where the variable values will be plugged in
        
    // we need to join all interested _hit tables...
    
    $clauses = 0;

     foreach( $ontology_infos as $ontology_short_name => $info )
      {
	if( isset( $ontology_terms[ $ontology_short_name ] ) )
	  {
	    if( $ontology_short_name != $search_ontology )  // all except the 'fixed' ontology
	      {
		$sql .= ' AND ' . $data_source . '_' . $ontology_short_name . '_hit.id = ' . $active_hit_table . '.id';
	      }
	  }
      }
     
     foreach( $ontology_infos as $ontology_short_name => $info )
      {
	if( isset( $ontology_terms[ $ontology_short_name ] ) )
	  {
	    $sql .= ' AND ' . make_OR_sequence( $database_connection, 
						$data_source . '_' . $ontology_short_name . '_hit.name',
						$ontology_terms[ $ontology_short_name ] );
	  }
      }    
  }


//echo '<P>' . $sql . '</P>';


$prepared_statement = $database_connection->prepare( $sql );
		
if ( PEAR::isError( $prepared_statement ) )  
  {
    die( 'prepare:' . $prepared_statement->getMessage() );
  }


// soap body will magically appear at on stdin

$lines = file( 'php://input' );

// dummy inputs for unit testing:
//
//$lines = array( 'food', 'meat', 'poultry', 'beef' );
//$lines = array( 'France', 'Germany', 'Belgium' );

foreach( $lines as $index => $line )
  {
    $term = chop( $line );

    //echo '<P>processing ' . $term . '</P>';

    if( strlen( $term ) > 0 )
      {
	$inputs = array();

	// plug the iteratee into the prepared statement...

	$inputs[] = $term;

	$result = $prepared_statement->execute( $inputs );

	if ( PEAR::isError( $result ) )  
	  {
	    die( 'result:' . $result->getMessage() );
	  }

	$count = 0;

	while ( ( $row = $result->fetchRow() ) )  
	  {
	    $count = $row[ 0 ];
	  }
	
	$result->free();

	echo $term . "\t" . $count . "\n";
      }
  }



$database_connection->disconnect();

?>