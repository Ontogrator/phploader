<?php

include_once( 'config.php' );

include_once( 'common.php' );

require_once 'MDB2.php';


function escape_quotes( $input )
{
  //return str_replace( '"', '\\"', $input );

  return str_replace( '"', '\\"', preg_replace( "/\\xB0/", "&deg;", $input ) );
}



$database_connection =& MDB2::connect( DB_SYSTEM_DSN . "/" . DATABASE_NAME );

if ( PEAR::isError( $database_connection ) )  
  {
    die( $database_connection->getMessage() );
  }

if( isset( $_REQUEST[ 'ds' ] ) === false )
  {
    die( 'Data source not specified' );
  }

$data_source = strtoupper( $_REQUEST[ 'ds' ] );

$columns = null;


foreach( $data_source_infos as $key => $info )
  {
    if( $data_source == $key )
      {
	$columns = $info[ 'columns' ];
      }
  }

if( $columns == null )
  {
    die( 'Unrecognised data source "' . $data_source . '"' );
  }


//
// for big datasets (e.g. SILVA) we dont want to have to pull over all of the results
// to do the counting - so we do the count first, then use "LIMIT offset,count" to 
// select just the required subset of rows
//


// the 'count' mode just returns the number of hits, rather than the hits themselves
$count_only = isset( $_REQUEST[ 'count' ] );

$ontology_terms = get_ontology_terms();

$has_tax  = isset( $ontology_terms[ 'TAX'  ] );
$has_envo = isset( $ontology_terms[ 'ENVO' ] );
$has_gaz  = isset( $ontology_terms[ 'GAZ'  ] );
$has_mat  = isset( $ontology_terms[ 'MAT'  ] );


$page_size = isset( $_REQUEST[ 'limit' ] ) ? $_REQUEST[ 'limit' ] : 10;

$start_row = isset( $_REQUEST[ 'start' ] ) ? ( $_REQUEST[ 'start' ] + 1 ) : 1;       // extJS uses 0 based counting


// from FROM and WHERE clauses are common to both queries, so we'll
// build that bit first

// ::TODO:: generic'ise this

$shared_sql = ' FROM ' . $data_source . '_entry';

if( $has_envo )
  {
    $shared_sql .= ',' . $data_source . '_ENVO_hit';
  }
if( $has_tax )
  {
    $shared_sql .= ',' . $data_source . '_TAX_hit';
  }
if( $has_gaz )
  {
    $shared_sql .= ',' . $data_source . '_GAZ_hit';
  }
if( $has_mat )
  {
    $shared_sql .= ',' . $data_source . '_MAT_hit';
  }

if( $has_envo || $has_tax || $has_gaz || $has_mat )
  {
    $shared_sql .= ' WHERE ';
  }

$needsAnd = false;

//::TODO:: shoould be genericified

if( $has_envo )
  {
    $needsAnd = true;
    //    $shared_sql .= '' . $data_source . '_entry.id=' . $data_source . '_ENVO_hit.id AND ' . $data_source . '_ENVO_hit.name=' . $database_connection->quote( $_REQUEST['ENVO'], 'text' );
    $shared_sql .= '' . $data_source . '_entry.id=' . $data_source . '_ENVO_hit.id AND ' . make_OR_sequence( $database_connection, $data_source . '_ENVO_hit.name', $ontology_terms[ 'ENVO' ] );
  }
if( $has_tax )
  {
   if( $needsAnd )
      {
	$shared_sql .= ' AND ';
      }
   //$shared_sql .= '' . $data_source . '_entry.id=' . $data_source . '_TAX_hit.id AND ' . $data_source . '_TAX_hit.name=' . $database_connection->quote( $_REQUEST['TAX'], 'text' );
   $shared_sql .= '' . $data_source . '_entry.id=' . $data_source . '_TAX_hit.id AND ' . make_OR_sequence( $database_connection, $data_source . '_TAX_hit.name', $ontology_terms[ 'TAX' ] );
    $needsAnd = true;
   }
if( $has_gaz )
  {
    if( $needsAnd )
      {
	$shared_sql .= ' AND ';
      }
    //$shared_sql .= '' . $data_source . '_entry.id=' . $data_source . '_GAZ_hit.id AND ' . $data_source . '_GAZ_hit.name=' . $database_connection->quote( $_REQUEST['GAZ'], 'text' );
    $shared_sql .= '' . $data_source . '_entry.id=' . $data_source . '_GAZ_hit.id AND ' . make_OR_sequence( $database_connection, $data_source . '_GAZ_hit.name', $ontology_terms[ 'GAZ' ] );
    $needsAnd = true;
  }
if( $has_mat )
  {
    if( $needsAnd )
      {
	$shared_sql .= ' AND ';
      }
    //$shared_sql .= '' . $data_source . '_entry.id=' . $data_source . '_MAT_hit.id AND ' . $data_source . '_MAT_hit.name=' . $database_connection->quote( $_REQUEST['MAT'], 'text' );
    $shared_sql .= '' . $data_source . '_entry.id=' . $data_source . '_MAT_hit.id AND ' . make_OR_sequence( $database_connection, $data_source . '_MAT_hit.name', $ontology_terms[ 'MAT' ] );
    $needsAnd = true;
  }



//echo "<P>" . DB_SYSTEM_DSN . "/" . DATABASE_NAME . "</P>";
//
//echo "<P>" . $sql . "</P>";


// 1. firstly, do the count...

$sql = 'SELECT COUNT( DISTINCT( ' . $data_source . '_entry.id) ) ' . $shared_sql;


//echo "<P>1. " . $sql . "</P>";


$result = $database_connection->query( $sql );

if ( PEAR::isError( $result ) )  
  {
    die( "!! - " . $result->getMessage() );
  }

while ( ( $row = $result->fetchRow() ) )  
  {
    $total_rows = $row[ 0 ];

    if( $count_only )
      {
	echo $total_rows;   // should just be a single row containing a single number
      }
  }

$result->free();
    
if( $count_only )
  {
    $database_connection->disconnect();

    exit( 1 );
  }

// now we do the query again, this time with the LIMIT set accordingly

// ::TODO::: fails when we try it without DISTINCT..., resolve why this is required, it involves significant extra database cost

$sql = 'SELECT DISTINCT( ' . $data_source . '_entry.id), ' . $data_source . '_entry.url';
    
foreach( $columns as $name => $width )
  {
    $sql .= ',' . $data_source . '_entry.';
    $sql .= $name;
  }

$sql .= ' ' . $shared_sql . ' LIMIT ' . ($start_row - 1) . ',' . $page_size;

//echo "<P>2. " . $sql . "</P>";

echo '{ rows: [ ';
echo "\n";

$result = $database_connection->query( $sql );

$count = 0;

//echo "<P>NumRows:" . $result->numRows() . "</P>";

while ( ( $row = $result->fetchRow() ) )  
  {
    if( $count > 0 ) 
      { 
	echo ",\n";
      }
    
    $pos = 0;
    
    echo "{\nid: \"" . $row[ $pos++ ] . "\",\nurl: \"" . escape_quotes( $row[ $pos++ ] ) . '"';
    
    foreach( $columns as $name => $width )
      {
	echo ",\n" . $name . ': "' . escape_quotes( $row[ $pos++ ] ) . '"';
      }
    
    echo "\n}";
    
    $count++;
  }

    
echo " ],\ntotalResults:" . ( $total_rows ). ",\nresultsReturned:" . $count . ",\nfirstResult:" . $start_row . "\n}";

//echo "<DIV>";
//var_dump( $result );
//echo "</DIV>";

$result->free();

$database_connection->disconnect();

?>