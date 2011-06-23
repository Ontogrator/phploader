<?php

include_once( 'config.php' );

require_once 'MDB2.php';

require_once ( OMIXED_PHP_LIB_HOME . 'omixed.php' );

class Terminizer
{
  private $debug_level = 0;

  private $statement_cache;
  
  private $text_lookup_cache;

  private $parent_expand_cache;

  private $text_lookup_cache_hits;
  private $text_lookup_cache_misses;

  private $parent_expand_cache_hits;
  private $parent_expand_cache_misses;

  // ::TODO:: add an expansion cache

  function __construct()
  {
    $this->text_lookup_cache_hits = 0;
    $this->text_lookup_cache_misses = 0;
 
    $this->parent_expand_cache_hits = 0;
    $this->parent_expand_cache_misses = 0;

    $this->statement_cache = array();
    $this->text_lookup_cache = array();
    $this->parent_expand_cache = array();
  }

  // ===================================================================================

  function get_cache_stats()
  {
    $text_cache_hit_rate = ( $this->text_lookup_cache_misses == 0 ) ? 0 : $this->text_lookup_cache_hits / ( $this->text_lookup_cache_hits + $this->text_lookup_cache_misses );

    $parent_cache_hit_rate = ( $this->parent_expand_cache_misses == 0 ) ? 0 : $this->parent_expand_cache_hits / ( $this->parent_expand_cache_hits + $this->parent_expand_cache_misses );

    return sprintf( "t:%.0f%%,p:%.0f%%", ( $text_cache_hit_rate * 100.0 ), ( $parent_cache_hit_rate * 100.0 ) );
  }

  // ===================================================================================
  
  private function invoke_service( $text )
  {
    global $ontology_infos;

    if( $this->debug_level > 0 )
      {
	echo "  TA <<" . $text . ">>\n";
      }

    $matched_terms = array();
    
    if( strlen( trim( $text ) ) > 0 )
      {
	
	$query_url = TERMINIZER_BASE_URL . "?sourceText=" . urlencode( $text );
	
	// print( "\neeeeeeeee\n\n" . $query_url . "\n\neeeeeeeeeeeee\n" );
	
	$dom = new DOMDocument('1.0');
	
	$dom->load( $query_url );
	
	// print( "\nwwwwwwww\n\n" . $dom->saveXML()  . "\n\nwwwwwwwwwwwwwwww\n" );
	
	$nodes = $dom->getElementsByTagName( "MatchedTerm" ); 
	
	foreach ( $nodes as $node )
	  {
	    $item_id = $node->getElementsByTagName( "OmixedItemID" )->item( 0 )->textContent;
	    
	    //echo "  *[" . $item_id . "]*\n";
	    
	    $item_id_bits = OmixedItem::parse_item_id( $item_id );
	    
	    $item_type_name_bits = split( ' ', $item_id_bits['ItemTypeName'] );
	    
	    $ontology_name = $item_type_name_bits[ 0 ];
	    
	    if( isset( $ontology_infos[ $ontology_name ] ) )
	      {
		$term_name =  $item_id_bits['ItemName'];
		
		// prevent duplicate matches with the same term
		
		if( isset( $matched_terms[ $ontology_name ] ) === false )
		  {
		    $matched_terms[ $ontology_name ] = array( $term_name );
		  }
		else
		  {
		    if( array_search( $term_name, $matched_terms[ $ontology_name ] ) === false )
		      {
			//echo "  *{{{" . $ontology_name . ':' . $term_name . "}}}*\n";
			
			$matched_terms[ $ontology_name ][] = $term_name;
		      }  
		  }
	      }
	  }
      }
    
    return $matched_terms;
  }
  
  
  // ===================================================================================
  
  
  private function find_all_parents( $database_connection, $term, $prepared_statement  )
  {
    $continue = true;
    
    $parents = $prepared_statement->execute( array( $term ) );
    
    $parent_list = Array();
    
    
    while ( ( $row = $parents->fetchRow() ) )  
      {
	//echo '   ' . $term . ' has_parent ' . $row[ 0 ] . "\n";
	
	$parent_list[] = $row[ 0 ];
      }
    
    $parents->free();
    
    // now recurse...
    
    $expanded_parent_list = Array();
    
    $expanded_parent_list[] = $term;
    
    // surely there is a cleaner way to do this?
    
    foreach( $parent_list as $parent )
      {
	$expanded_parent_list[] = $parent;
	
	$parent_parents = $this->find_all_parents( $database_connection, $parent, $prepared_statement );
	
	foreach( $parent_parents as $parent_parent )
	  {
	    $expanded_parent_list[] = $parent_parent;
	  }
	
      }
    
    return $expanded_parent_list;
  }
  

  private function expand_to_parents( $database_connection, $hits )
  {
    global $ontology_infos;

    // given an input set of terms, expand it so that *all* of the antecedants of each term are included
    
    $expanded_list = Array();
    
    foreach( $ontology_infos as $short_name => $ontology_info )
      {
	if( array_key_exists( $short_name, $hits ) )
	  {
	    //echo 'checking: ' . $interesting_ontology . "\n";
	    
	    $key = 'par:' . $short_name;
	    
	    if( isset( $this->statement_cache[ $key ] ) )
	      {
		$prepared_statement = $this->statement_cache[ $key ];
		
	      }
	    else
	      {
		$prepared_statement = $database_connection->prepare( 'SELECT parent FROM ' . $short_name . '_parent WHERE name = ?');
		
		$this->statement_cache[ $key ] = $prepared_statement ;
	      }
	    
	    $expanded_list[ $short_name ] = Array();
	    
	    foreach( $hits[ $short_name ] as $hit )
	      {
		if( $this->debug_level > 0 )
		  {
		    echo '  base: ' . $hit . "\n";
		  }

		// add the base term
		
		$expanded_list[] = $hit;
		
		//echo $interesting_ontology . ':' . $hit . "\n";
		
		// find all parent terms (via the cache if possible)
		
		if( isset( $this->parent_expand_cache[ $hit ] ) )
		  {
		    $parent_list = $this->parent_expand_cache[ $hit ];
		    $this->parent_expand_cache_hits++;
		  }
		else
		  {
		    // not in cache, do the database lookup

		    $parent_list = $this->find_all_parents( $database_connection, $hit, $prepared_statement );
		    
		    $this->parent_expand_cache[ $hit ] = $parent_list;
		    $this->parent_expand_cache_misses++;
		  }
 
		//echo '  found: ' . count( $parent_list ) . " parents\n";
		
		// and add to total list for this entry
		
		foreach( $parent_list as $parent )
		  {
		    $expanded_list[ $short_name ][] = $parent;
		  }
	      }
	    
	    // now we need to uniqify the $expanded_parent_list (duplicates might have arisen as the hits can share common ancestors)
	    
	    $cleaned_list = Array();
	    
	    foreach( $expanded_list[ $short_name ] as $term )
	      {
		if( array_search( $term, $cleaned_list ) === FALSE )
		  {
		    $cleaned_list[] = $term;
		  }
	      }
	    
	    if( $this->debug_level > 0 )
	      {
		echo '  cleaned to: ' . count( $cleaned_list ) . " parents\n";
	      }

	    $expanded_list[ $short_name ] = $cleaned_list;
	  }
	
      }
    
    return $expanded_list;
  }
  
    
  // ===================================================================================
  
  public function terminize( $database_connection, $text )
  {
    global $ontology_infos;

    $clean_terminizer_input = preg_replace( "/[\\x00-\\x1F]/", "", preg_replace( "/[\\x7F-\\xFF]/", "", trim( $text ) ) );
    
    if( isset( $this->text_lookup_cache[ $clean_terminizer_input ] ) )
      {
	$this->text_lookup_cache_hits++;
	$hits = $this->text_lookup_cache[ $clean_terminizer_input ];
      }
    else
      {
	$hits = $this->invoke_service( $clean_terminizer_input );

	$this->text_lookup_cache[ $clean_terminizer_input ] = $hits;
	$this->text_lookup_cache_misses++;
      }
    
    $hits = $this->expand_to_parents( $database_connection, $hits );
    
    // list the expanded hits:
    
    if( $this->debug_level > 1 )
      {
	foreach( $ontology_infos as $short_name => $ontology_info )
	  {
	    if( array_key_exists( $short_name, $hits ) )
	      {
		foreach( $hits[ $short_name ] as $hit )
		  {
		    echo "   -> " . $short_name . " " . $hit . "\n";
		  }
	      }
	  }
      }
    
    return $hits;
  }
}

?>
