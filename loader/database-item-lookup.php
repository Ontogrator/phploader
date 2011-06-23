<?php

//
// provides details of the hits for a specific item

//
include_once( 'config.php' );

require_once 'MDB2.php';

$debug = false;

function escape_quotes( $input )
{
  //return str_replace( '"', '\\"', $input );

  return str_replace( '"', '\\"', preg_replace( "/\\xB0/", "&deg;", $input ) );
}

function get_count( &$store, $key )
{
  return isset( $store[ $key ] ) ? $store[ $key ] : 0;
}

function increment( &$store, $key )
{
  $store[ $key ] = get_count( $store, $key ) + 1;
}

function store_parent( &$parent_store, $term_name, $parent_name )
{
  if( isset( $parent_store[ $term_name ] ) )
    {
      $parent_store[ $term_name ][] = $parent_name;
    }
  else
    {
      $parent_store[ $term_name ] = array( $parent_name );
    }

}

function get_parent_count( &$parent_store, $term_name )
{
  if( isset( $parent_store, $term_name ) )
    {
      return count( $parent_store[ $term_name ] );
    }
  else
    {
      return 0;
    }
}

//
// turn a tree into a list of chains
//
//    a-b-c
//     -d-e
//       -f
//
// for safety's sake, we'll handle cyclical paths gracefully
//
function get_paths( &$parent_store, &$ignore_list, $term_name )
{
  if( isset( $parent_store[ $term_name ] ) === false )
    {
      // this term has no parents - so the result is just the term itself
      
      //echo '[' . $term_name . ':no parent]';
      
      return array( array( $term_name ) );
    }
  else
    {
      // this term has some parents, get their paths then prepend this term to each of them

      $ignore_list[] = $term_name;


      // either by accident or design, some TAX paths are cyclic
      $result = array();
      
      $parents = $parent_store[ $term_name ];
      
      foreach( $parents as $parent_name )
	{
	  // if we already seen this parent on any of the paths, then ignore it...

	  if( array_search(  $parent_name, $ignore_list ) === false )
	    {
	      $subpaths = get_paths( $parent_store, $ignore_list, $parent_name );
	      
	      //echo '[' . $term_name . ':' . $parent_name . ':' . count( $subpaths ) . ']';
	      
	      foreach( $subpaths as $subpath )
		{
		  //echo '<P STYLE="padding:8px;color:#842">';
		  //var_dump( $subpath );
		  //echo '</P>';
		  
		  // bung the term_name onto the head of this path
		  
		  array_unshift( $subpath, $term_name );
		  
		  $result[] = $subpath;
		}
	    }
	}
      
      return $result;
    }


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

if( isset( $_REQUEST[ 'id' ] ) === false )
  {
    die( 'ID not specified' );
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



		

// for each ontology, get the hits against this item
// and then arrange those hits into the parentage hierarchy
//
//
// remember some items can have multiple parents,
//
// this hash-of-hashes will be filled with: one set of 'term => [parent1, parent2, .... ]'  for each ontology
//
$parent_store = array();   


foreach( $ontology_infos as $short_name => $ontology_info )
  {
    $parent_store[ $short_name ] = array();   
    
    $parent_lookup_sql = 'SELECT parent FROM ' . $short_name . '_parent WHERE name = ?';

    if( $debug ) { echo '<P STYLE="background:#000;color:#fff">' . $parent_lookup_sql. '</P>'; }
    
    $parent_lookup_statement = $database_connection->prepare( $parent_lookup_sql );
    
    if ( PEAR::isError( $parent_lookup_statement ) )  
      {
	die( "!! - " . $parent_lookup_statement->getMessage() );
      }
    
    $sql = 'SELECT name FROM ' . $data_source . '_' . $short_name . '_hit WHERE ID="' . $_REQUEST[ 'id' ] . '"';

    if( $debug ) { echo '<P STYLE="background:#000;color:#fff">' . $sql. '</P>'; }

    $result = $database_connection->query( $sql );

    if ( PEAR::isError( $result ) )  
      {
	die( "!! - " . $result->getMessage() );
      }
    
    while ( ( $row = $result->fetchRow() ) )  
      {
	$term_name = $row[ 0 ];
	
	//echo '<P>' . $short_name . ' : ' . $term_name . '</P>';
	
	$parent_info_result = $parent_lookup_statement->execute( array( $term_name ) );
	
	if ( PEAR::isError( $parent_info_result ) )  
	  {
	    die( "!! - " . $parent_info_result->getMessage() );
	  }
	
	while ( ( $row = $parent_info_result->fetchRow() ) )  
	  {
	    $parent_name = $row[ 0 ];

	    // echo '<P STYLE="padding-left:32px">' . $parent_name . '</P>';
	    
	    store_parent( $parent_store[ $short_name ], $term_name, $parent_name );
	  }
	
	

	$parent_info_result->free();
      }
    
    $result->free();
  }



$database_connection->disconnect();

// now we can resolve the parentage

foreach( $ontology_infos as $short_name => $ontology_info )
  {
    $child_count = array(); // how many childs does each term have?

    foreach( $parent_store[ $short_name ] as $term_name => $parent_name_list )
      {
	//echo '<P STYLE="color:#933">' . $term_name . ' (' . $short_name . ')</P>';

	$total_parent_count = 0;

	foreach( $parent_name_list as $parent_name )
	  {
	    $parent_count  =  get_parent_count( $parent_store[ $short_name ], $parent_name );
 
	    $total_parent_count += $parent_count;

	    increment( $child_count, $parent_name );

	    //echo '<P STYLE="padding-left:32px; color:#966">' . $parent_name . ' (' . $parent_count . ')</P>';
	  }

	if( $total_parent_count == 0 )
	  {
	    // this is a root

	    //echo '<P STYLE="padding-left:48px; color:#699">ROOT!</P>';
	  }
      }

    // now we know the leaf[s] and root[s], we can generate the path[s]:

    foreach( $parent_store[ $short_name ] as $term_name => $parent_name_list )
      {
	if( get_count( $child_count, $term_name ) == 0 ) // isset( $store[ $key ] ) === false )
	  {
	    //echo '<P STYLE="padding-left:48px; color:#996">LEAF!</P>';

	    $ignore_list = array();

	    $paths = get_paths( $parent_store[ $short_name ], $ignore_list, $term_name );

	    //echo '<P STYLE="padding-left:48px; color:#996">' . count( $paths ) . ' paths</P>';

	    foreach( $paths as $path )
	      {
		$count = 0;

		if( $debug )
		  {
		    echo '<P STYLE="padding-left:64px; color:#962">';
		    
		    foreach( $path as $path_part )
		      {
			echo ( ( $count++ > 0 ) ? ' &gt; ' : '' ) . $path_part;
		      }
		    
		    echo '</P>';
		  }

		echo $short_name;
		foreach( $path as $path_part )
		  {
		    echo "\t";
		    echo $path_part;
		  }
		echo "\n";
	      }
	  }
      }
  }





?>