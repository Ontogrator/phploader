<?php

include_once 'config.php';

require_once 'MDB2.php';

$mdb2 =& MDB2::connect( DB_SYSTEM_DSN );

if ( PEAR::isError( $mdb2 ) ) { die( $mdb2->getMessage() ); }

$res =& $mdb2->query( 'DROP DATABASE IF EXISTS ' . DATABASE_NAME );

if ( PEAR::isError( $res ) ) { die( $res->getMessage() ); }

echo $res->db->last_query . "\n";

$res =& $mdb2->query( 'CREATE DATABASE ' . DATABASE_NAME );

if ( PEAR::isError( $res ) ) { die( $res->getMessage() ); }

echo $res->db->last_query . "\n";

$mdb2->disconnect();

echo "...database reset\n";

$mdb2 =& MDB2::connect( DB_SYSTEM_DSN . "/" . DATABASE_NAME );

if ( PEAR::isError( $mdb2 ) ) { die( $mdb2->getMessage() ); }

// tables for each ontology (and the taxonomies too)

foreach( $ontology_infos as $short_name => $ontology_info )
  {
    $res =& $mdb2->query( 'CREATE TABLE ' . $short_name . '_term ( name VARCHAR(255), parent_count SMALLINT, child_count SMALLINT, definition VARCHAR(1024) )' );
    
    echo $res->db->last_query . "\n";

    if ( PEAR::isError( $res ) ) { die( $res->getMessage() ); }
    
    $res =& $mdb2->query( 'CREATE TABLE ' . $short_name . '_parent ( name VARCHAR(255), relation VARCHAR(16), parent VARCHAR(255) )' );
    
    if ( PEAR::isError( $res ) ) { die( $res->getMessage() ); }

    echo $res->db->last_query . "\n";
  }

// generic tables for each data source and the ontological hits thereon

foreach( $data_source_infos as $short_name => $data_source_info )
  {
    $columns = $data_source_infos[ $short_name ][ "columns" ];

    $field_defns = '';

    foreach( $columns as $column_name => $column_width )
      {
	$field_defns .= ', ' . $column_name . ' VARCHAR(' . $column_width . ')';
      }
    
    $res =& $mdb2->query( 'CREATE TABLE ' . $short_name . '_entry ( id VARCHAR(' . $data_source_info["identifier_length"] . '), url TEXT' . $field_defns. ' )' );
    
    if ( PEAR::isError( $res ) ) { die( $res->getMessage() ); }

    echo $res->db->last_query . "\n";
    
    foreach( $ontology_infos as $ontology_short_name => $ontology_info )
      {
	$res =& $mdb2->query( 'CREATE TABLE ' . $short_name . '_' . $ontology_short_name . '_hit ( id VARCHAR(' . $data_source_info["identifier_length"] . '), name VARCHAR(255) )' );
	
	if ( PEAR::isError( $res ) ) { die( $res->getMessage() ); }
	
	echo $res->db->last_query . "\n";
      }
  }


echo "...tables created\n";

$mdb2->disconnect();

?>
