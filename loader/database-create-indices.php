<?php

include_once 'config.php';

require_once 'MDB2.php';

$database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . DATABASE_NAME );

if ( PEAR::isError( $database_connection ) ) { die( $database_connection->getMessage() ); }

foreach( $ontology_infos as $short_name => $ontology_info )
  {
    $res =& $database_connection->query( 'CREATE INDEX ' . $short_name . '_I1 ON ' . $short_name . '_term ( name, parent_count )' );
    
    if ( PEAR::isError( $res ) ) 
      { 
	echo( $short_name . '_I1: ' . $res->getMessage() . "\n" ); 
      }
    else
      {
	echo( $short_name . '_I1: ' . "OK\n" );
      }

    $res =& $database_connection->query( 'CREATE INDEX ' . $short_name . '_I2 ON ' . $short_name . '_term ( name, child_count )' );
    
    if ( PEAR::isError( $res ) ) 
      {
	echo( $short_name . '_I2: ' . $res->getMessage() . "\n" ); 
      }
    else
      {
	echo( $short_name . '_I2: ' . "OK\n" );
      }
   
    $res =& $database_connection->query( 'CREATE INDEX ' . $short_name . '_I3 ON ' . $short_name . '_parent ( name, parent )' );
    
    if ( PEAR::isError( $res ) ) 
      { 
	echo( $short_name . '_I3: ' . $res->getMessage() . "\n" ); 
      }
    else
      {
	echo( $short_name . '_I3: ' . "OK\n" );
      }
  }
  


foreach( $data_source_infos as $short_name => $data_source_info )
  {
    $res =& $database_connection->query( 'CREATE INDEX ' . $short_name . '_I1 ON ' . $short_name . '_entry ( id )' );
    
    if ( PEAR::isError( $res ) ) 
      {
	echo( $short_name . '_I1: ' . $res->getMessage() . "\n" ); 
      }
    else
      {
	echo( $short_name . '_I1: ' . "OK\n" );
      }
    
    foreach( $ontology_infos as $ontology_short_name => $ontology_info )
      {
	$res =& $database_connection->query( 'CREATE INDEX ' . $short_name . '_' . $ontology_short_name . '_I1 ON ' . 
					      $short_name . '_' . $ontology_short_name . '_hit ( id, name )' );
	
	if ( PEAR::isError( $res ) ) 
	  {
	    echo( $short_name . '_' . $ontology_short_name . '_I1: ' . $res->getMessage() . "\n" ); 
	  }
	else
	  {
	    echo( $short_name . '_' . $ontology_short_name . '_I1: ' . "OK\n" );
	  }
	
	$res =& $database_connection->query( 'CREATE INDEX ' . $short_name . '_' . $ontology_short_name . '_I2 ON ' . 
					     $short_name . '_' . $ontology_short_name . '_hit ( name, id )' );
	
	if ( PEAR::isError( $res ) ) 
	  {
	    echo( $short_name . '_' . $ontology_short_name . '_I2: ' . $res->getMessage() . "\n" ); 
	  }
	else
	  {
	    echo( $short_name . '_' . $ontology_short_name . '_I2: ' . "OK\n" );
	  }
      }
  }

echo "...indices created\n";

foreach( $data_source_infos as $short_name => $data_source_info )
  {
    $res =& $database_connection->query( 'ANALYZE TABLE ' . $short_name . '_entry' );
    
    if ( PEAR::isError( $res ) ) 
      { 
	echo( $short_name . ' (analyse): ' . $res->getMessage() . "\n" ); 
      }
    
    foreach( $ontology_infos as $ontology_short_name => $ontology_info )
      {
	$res =& $database_connection->query( 'ANALYZE TABLE ' . $short_name . '_' . $ontology_short_name . '_hit' );
	
	if ( PEAR::isError( $res ) ) 
	  { 
	    echo( $short_name . '_' . $ontology_short_name . ' (analyse): ' . $res->getMessage() . "\n" ); 
	  }
      }
  }

foreach( $ontology_infos as $short_name => $ontology_info )
  {
    $res =& $database_connection->query( 'ANALYZE TABLE ' . $short_name . '_term' );
    
    if ( PEAR::isError( $res ) ) 
      { 
	echo( $short_name . '_parent (analyse): ' . $res->getMessage() . "\n" ); 
      }

    $res =& $database_connection->query( 'ANALYZE TABLE ' . $short_name . '_parent' );
    
    if ( PEAR::isError( $res ) ) 
      { 
	echo( $short_name . '_term (analyse): ' . $res->getMessage() . "\n" ); 
      }
  }

echo "...tables analysed\n";


$database_connection->disconnect();


//DROP INDEX ENVO_term_lookup ON ENVO_term;
//DROP INDEX ENVO_parent_lookup ON ENVO_parent;

//CREATE INDEX ENVO_term_lookup ON ENVO_term ( name );
//CREATE INDEX ENVO_parent_lookup ON ENVO_parent ( parent, name );

//DROP INDEX GAZ_term_lookup ON GAZ_term;
//DROP INDEX GAZ_parent_lookup ON GAZ_parent;

//CREATE INDEX GAZ_term_lookup ON GAZ_term ( name );
//CREATE INDEX GAZ_parent_lookup ON GAZ_parent ( parent, name );

?>
