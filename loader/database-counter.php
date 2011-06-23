<?php

/*

include_once( 'config.php' );

require_once 'MDB2.php';

$database_connection =& MDB2::connect( DB_SYSTEM_DSN . "/" . SILVA_DB_DATABASE_NAME );

if ( PEAR::isError( $database_connection ) )  
  {
    die( $database_connection->getMessage() );
  }

$has_tax  = array_key_exists( 'Tax',  $_GET );
$has_envo = array_key_exists( 'EnvO', $_GET );
$has_gaz  = array_key_exists( 'Gaz',  $_GET );
$has_mat  = array_key_exists( 'Mat',  $_GET );

if( $has_tax && $has_envo && $has_gaz && $has_mat )
  {
    die( 'not all sources can be selected at once' );
  }

if( ! $has_tax && ! $has_envo && ! $has_gaz && ! $has_mat )
  {
    die( 'at least one source must be selected' );
  }

$target = array_key_exists( 'count',  $_GET ) ? $_GET['count'] : null;

if( ! $target )
  {
    die( 'missing param "count"' );
  }

$target = strtoupper( $target );

$sql = 'SELECT ' . $target. '_hit.name, COUNT( ' . $target. '_hit.name) from SILVA_entry';

if( $has_envo )
  {
    $sql .= ',ENVO_hit';
  }
if( $has_tax )
  {
    $sql .= ',TAX_hit';
  }
if( $has_gaz )
  {
    $sql .= ',GAZ_hit';
  }
if( $has_mat )
  {
    $sql .= ',MAT_hit';
  }

$sql .= ',' . $target . '_hit';

if( $has_envo || $has_tax || $has_gaz || $has_mat )
  {
    $sql .= ' WHERE ';
  }


$needsAnd = false;

if( $has_envo )
  {
    $needsAnd = true;
    $sql .= 'SILVA_entry.primary_accession=ENVO_hit.primary_accession AND ENVO_hit.name=' . $database_connection->quote( $_GET['EnvO'], 'text' );
  }
if( $has_tax )
  {
   if( $needsAnd )
      {
	$sql .= ' AND ';
      }
    $sql .= 'SILVA_entry.primary_accession=TAX_hit.primary_accession AND TAX_hit.name=' . $database_connection->quote( $_GET['Tax'], 'text' );
    $needsAnd = true;
   }
if( $has_gaz )
  {
    if( $needsAnd )
      {
	$sql .= ' AND ';
      }
    $sql .= 'SILVA_entry.primary_accession=GAZ_hit.primary_accession AND GAZ_hit.name=' . $database_connection->quote( $_GET['Gaz'], 'text' );
    $needsAnd = true;
  }
if( $has_mat )
  {
    if( $needsAnd )
      {
	$sql .= ' AND ';
      }
    $sql .= 'SILVA_entry.primary_accession=MAT_hit.primary_accession AND MAT_hit.name=' . $database_connection->quote( $_GET['Mat'], 'text' );
    $needsAnd = true;
  }


$sql .= ' AND SILVA_entry.primary_accession=' . $target. '_hit.primary_accession GROUP BY ' . $target. '_hit.name';

// echo $sql . "\n\n";

$result = $database_connection->query( $sql );

if ( PEAR::isError( $result ) )  
  {
    die( $result->getMessage() );
  }

while ( ( $row = $result->fetchRow() ) )  
  {
    echo implode( "\t", $row ) . "\n";
  }

$result->free();

$database_connection->disconnect();

*/

?>
