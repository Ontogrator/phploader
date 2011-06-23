<?php

/*
  interface:

  
  lookup.php?ont=Gaz

     -> returns all root nodes for Gaz

  lookup.php?ont=Gaz&term=[x]

     -> returns all children of node [x] for Gaz


  in each case, the result is one term per line,
  terms which are known to have children will be prefixed with "+", terms without children will be prefixed with "="
 */



function cleanUp( $input )
{
   return "[" . $input . "]";

   //return $input;

}



include_once( 'config.php' );

require_once 'MDB2.php';

$database_connection =& MDB2::connect( DB_SYSTEM_DSN . "/" . DATABASE_NAME );

if ( PEAR::isError( $database_connection ) )  
  {
    die( $database_connection->getMessage() );
  }


$table = null;

if( isset( $_GET[ 'ont' ] ) == false )
  {
    die( '!! - no ontology selector' );
  }

$ontology_short_name = $_GET[ 'ont' ];

if( array_search( $ontology_short_name, array( 'TAX','ENVO','MAT','GAZ' ) ) === false )
  {
    die( '!! - unrecognised ontology' );
  }

/*
  before we have the child_counts available, we did this:

   SELECT EP1.name, count(EP2.name) 
          FROM ENVO_parent AS EP1 
          LEFT JOIN ENVO_parent AS EP2 
          ON EP2.parent=EP1.name 
          WHERE EP1.parent='fruit' 
          GROUP BY EP1.name;

   now we don't need to...
*/


if( isset( $_GET[ 'term' ] ) )
  {
    $sql = 
      'SELECT ' . $ontology_short_name . '_term.name, ' . $ontology_short_name . '_term.child_count ' . 
      ' FROM ' . $ontology_short_name . '_term, ' . $ontology_short_name . '_parent ' .
      ' WHERE parent=? AND ' . $ontology_short_name . '_term.name = ' . $ontology_short_name . '_parent.name';

    // echo $sql;

    $prepared_statement =  $database_connection->prepare( $sql );

    if ( PEAR::isError( $prepared_statement ) )  
      {
	die( "!! - " . $prepared_statement->getMessage() );
      }

    $result = $prepared_statement->execute( array( $_GET[ 'term' ] ) );
  }
else
  {
    // find all nodes which don't have a parent

    if( $ontology_short_name == 'GAZ' )
      {
	// special handling for GAZ...

	$result = $database_connection->query( 'SELECT name,1 FROM GAZ_parent WHERE parent=' .
					       $database_connection->quote( 'geographic region' ) );

      }
    else
      {
	if( $ontology_short_name == 'MAT' )
	  {
	    // special handling for MAT...
	    
	    $result = $database_connection->query( 'SELECT name,1 FROM MAT_parent WHERE parent=' .
						   $database_connection->quote( 'Minimal Anatomical Terminology' ) );
	  }
	else
	  {
	    $result = $database_connection->query( 'SELECT name,1 FROM ' . $ontology_short_name . '_term WHERE parent_count=0' );
	  }
      }
  }

if ( PEAR::isError( $result ) )  
  {
    die( "!! - " . $result->getMessage() );
  }

while ( ( $row = $result->fetchRow() ) )  
  {
    $term = $row[ 0 ];
    $childCount = $row[ 1 ];

    echo ( $childCount > 0 ) ? '+' : '=';
    
    echo cleanUp( $term ) . "\n";
  }

$result->free();

$database_connection->disconnect();

?>
