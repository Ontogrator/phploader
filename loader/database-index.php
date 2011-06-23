<?php

include_once 'config.php';

require_once 'MDB2.php';

if( $argc < 2 )
  {
    die( 'usage: ' . $argv[ 0 ] . " action\n" );
  }

$action = $argv[ 1 ];

$do_create = $do_analyse = $do_delete = false;

if( $action == 'create' )
  {
    $do_create = true;
    $prefix = 'CREATE INDEX ';
  }
else
  {
    if( $action == 'delete' )
      {
	$do_delete = true;
	$prefix = 'DROP INDEX ';
     }
    else
      {
	if( $action == 'delete' )
	  {
	    $do_delete = true;
	    $prefix = 'DROP INDEX ';
	  }
	else
	  {
	    die( "Unrecognised action\n" );
	  }
      }
  }


$database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . DATABASE_NAME );

if ( PEAR::isError( $database_connection ) ) { die( $database_connection->getMessage() ); }

foreach( $ontology_infos as $short_name => $ontology_info )
  {
    $sql = $prefix . $short_name . '_I1 ON ' . $short_name . '_term';
    
    if ( $do_create ) 
      { 
	$sql .= ' ( name, parent_count )';
	}

    $res =& $database_connection->query( $sql );
    
    if ( PEAR::isError( $res ) ) 
      { 
	echo( $short_name . '_I1: ' . $res->getMessage() . "\n" ); 
      }
    else
      {
	echo( $short_name . '_I1: ' . "OK\n" );
      }

    $sql = $prefix . $short_name . '_I2 ON ' . $short_name . '_term';

      if ( $do_create ) 
	{ 
	  $sql .= ' ( name, child_count )';
	}

    $res =& $database_connection->query( $sql );
    
    if ( PEAR::isError( $res ) ) 
      {
	echo( $short_name . '_I2: ' . $res->getMessage() . "\n" ); 
      }
    else
      {
	echo( $short_name . '_I2: ' . "OK\n" );
      }
   
    $sql = $prefix . $short_name . '_I3 ON ' . $short_name . '_parent';

      if ( $do_create ) 
	{ 
	  $sql .= '  ( name, parent )';
	}

    $res =& $database_connection->query( $sql );
    
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
    $sql = $prefix . $short_name . '_I1 ON ' . $short_name . '_entry';

      if ( $do_create ) 
	{ 
	  $sql .= '  ( id )';
	}

    $res =& $database_connection->query( $sql  );
    
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
	$sql = $prefix . $short_name . '_' . $ontology_short_name . '_I1 ON ' . $short_name . '_' . $ontology_short_name . '_hit';

	if ( $do_create ) 
	  { 
	    $sql .= '  ( id, name )';
	  }
	
	$res =& $database_connection->query( $sql );
	
	if ( PEAR::isError( $res ) ) 
	  {
	    echo( $short_name . '_' . $ontology_short_name . '_I1: ' . $res->getMessage() . "\n" ); 
	  }
	else
	  {
	    echo( $short_name . '_' . $ontology_short_name . '_I1: ' . "OK\n" );
	  }
	
	$sql = $prefix . $short_name . '_' . $ontology_short_name . '_I2 ON ' . $short_name . '_' . $ontology_short_name . '_hit';

	if ( $do_create ) 
	  { 
	    $sql .= '  ( name, id )';
	  }

	$res =& $database_connection->query( $sql  );
	
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

if( $do_create || $do_analyse )
  {
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
  }

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
