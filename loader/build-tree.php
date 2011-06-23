<?php

  /*

    generates a file which contains the ontology hierarchy in JSON notation
    
    only terms which have at least one hit in the X_hit table will be included.

    the model is represented as a twisty passage of nested objects and arrays:

    note: field names are abbreviated to 'n' (name),'h' (hits) & 'c'
    (childs) and all CRs and whitespace are stripped.
    
    var data = 
    [
      { name: root1,
        hits: 15,
	childs: [ { name: leaf1,
	            hits: 5,
		    childs: [ { name: leaf1A,
		                hits: 3,
			      },
			      { name: leaf1B,
		                hits: 2,
			      }
			    ]
                  },
		  { name: leaf2,
		    count 4,
		    childs: [ { name: leaf2A,
		                hits: 1,
                              },
                              { name: leaf2B,
		                hits: 3,
                              },
			    ]                    // end of childs for leaf2
                  }                              // end of leaf2
                ]                                // end of childs for root1
        }                                        // end of root1
    ];	                                         // end of data	   
			      

   */



/* 
   ontogrator version: now all datasets are unified into a single tree...

   

*/


include_once( 'config.php' );

require_once 'MDB2.php';

$human_readable = false;


// ===================================================================================

function get_hit_count_for_term( $term )
{
  global $statement_cache;

  $result = $statement_cache[ 'count_hits' ]->execute( array( $term ) );

  $hits = 0;
  
  while ( ( $row = $result->fetchRow() ) )  
    {
      $hits = $row[ 0 ];
    }

  $result->free();

  return $hits;
}


function traverse_term( $term, $has_preceeding_sibling )
{
  global $statement_cache, $human_readable;

  // does it have at least one hit?
  // (because we've expanded all the ancestors of matches out during the hit detection,
  //  we don't need to explcitly check the children looking for hits)
  //
  // if this term has no hits, then we can be sure than non of its
  // children have hits either

  $hits = get_hit_count_for_term( $term );

  if( $hits == 0 )
    {
      //echo '/* ignored ' . $term . " */\n";

      return false;
    }
  else
    {
      // it would be better if this query could generate the hit counts as well
      // (see the LEFT JOIN query below) then we wouldn't have to run
      // the get_hit_count_for_term() each time
      
      $result = $statement_cache[ 'find_children' ]->execute( array( $term ) );
      
      $child_terms = array();
      
      while ( ( $row = $result->fetchRow() ) )  
	{
	  $child_terms[] = $row[ 0 ];
	}

      $result->free();

      if( $has_preceeding_sibling )
	{
	  echo ',';

	  if( $human_readable )
	    {
	      echo "\n";
	    }

	}

      if( $human_readable )
	{
	  echo '{n:"' . $term . "\",\nh:" . $hits . ",\nc:[";
	}
      else
	{
	  echo '{n:"' . $term . "\",h:" . $hits . ",c:[";
	}
      
      if( count( $child_terms ) > 0 )
	{
	  // echo "\n";
	}

      $child_has_preceeding_sibling = false;

      foreach( $child_terms as $child_term )
	{
	  if( traverse_term( $child_term, $child_has_preceeding_sibling ) == true )
	    {
	      $child_has_preceeding_sibling = true;
	    }
	}
      
      //      echo " ] /* end of childs for " . $term . " */\n } /* end of " . $term . " */\n";

       if( $human_readable )
	{
	  echo "]\n}\n";
	}
       else
	 {
	  echo "]}";
	 }
	
      return true;
    }

}

/*
function process_top_level_term( $top_level_term )
{
  
}
*/


// ===================================================================================

if( $argc < 3 )
  {
    die( 'usage: ' . $argv[ 0 ] . ' database-name ontology-name' . "\n" );
  }

$database_name = $argv[ 1 ];

$ontology  = $argv[ 2 ];

$database_connection = null;

if( $database_name == 'gold' )
  {
    $database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . GOLD_DB_DATABASE_NAME );
    
    $identifer = 'goldstamp';
  }

if( $database_name == 'straininfo' )
  {
    $database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . STRAININFO_DB_DATABASE_NAME );

    $identifer = 'culture_id';
  }

if( $database_name == 'camera' )
  {
    $database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . CAMERA_DB_DATABASE_NAME );

    $identifer = 'sample_dataset';
  }


if( $database_connection == null )
  {
    die( 'unrecognised database-name' . "\n" );
  }

if ( PEAR::isError( $database_connection ) )  
  {
    die( $database_connection->getMessage() );
  }


$statement_cache = array();

$statement_cache[ 'find_children' ] = $database_connection->prepare( 'SELECT name FROM ' . $ontology . '_parent WHERE parent = ?' );

$statement_cache[ 'count_hits' ] = $database_connection->prepare( 'SELECT COUNT( ' . $identifer . ' ) FROM ' . $ontology . '_hit where name= ? ' );


// find all of the top-level parents, and work downwards from them
// make sure to only include nodes which have at least one hit

// eg.:
// SELECT GAZ_term.name, GAZ_parent.parent FROM GAZ_term LEFT JOIN ( GAZ_parent ) ON ( GAZ_term.name = GAZ_parent.name ) WHERE GAZ_parent.parent IS NULL;
// SELECT ENVO_term.name, ENVO_parent.parent FROM ENVO_term LEFT JOIN ( ENVO_parent ) ON ( ENVO_term.name = ENVO_parent.name ) WHERE ENVO_parent.parent IS NULL;



// new system:
//
// 1. set all counts to zero
//
// UPDATE ENVO_term SET parent_count=0;
//
// 2. figure out counts
//
// UPDATE ENVO_term SET parent_count=( SELECT COUNT( parent ) from ENVO_parent where ENVO_parent.name=ENVO_term.name );
// UPDATE GAZ_term SET parent_count=( SELECT COUNT( parent ) from GAZ_parent where GAZ_parent.name=GAZ_term.name );
//
//   step 2 still takes an infinite amount of time
//
// 3. woof!
//
// SELECT name,parent_count FROM ENVO_term WHERE parent_count='0';
//

// doing the parent_count in the database is proving to be very expensive,
// so we've moved it back into the database-populate script
//
// so now we can just use the parent_count here:

$top_level_terms = array();

// to find out which terms have no parents, we LEFT JOIN against the X_parent table and look for NULLs
 
//$result = &$database_connection->query( 'SELECT ' . $ontology . '_term.name, ' . $ontology . '_parent.parent ' . 
//					'FROM ' . $ontology . '_term LEFT JOIN ( ' . $ontology . '_parent ) ' . 
//					'ON ( ' . $ontology . '_term.name = ' . $ontology . '_parent.name ) ' . 
//					'WHERE ' . $ontology . '_parent.parent IS NULL;' );

$result = &$database_connection->query( 'SELECT name FROM ' . $ontology . '_term WHERE parent_count=0' );

//::TODO:: we could do something similar to generate the hit counts in a single go too..

while ( ( $row = $result->fetchRow() ) )  
  {
    $top_level_terms[] = $row[ 0 ];
  }

$result->free();

echo 'var ' . $ontology . '_data=[';

$has_preceeding_sibling = false;

foreach( $top_level_terms as $top_level_term )
  {
    if( traverse_term( $top_level_term, $has_preceeding_sibling ) == true )
      {
	$has_preceeding_sibling = true;
      }
  }



foreach( $statement_cache as $name => $prepared_statement )
  {
    $prepared_statement->free();
  }

echo '];';

?>
