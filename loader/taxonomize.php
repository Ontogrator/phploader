<?

include_once 'config.php';

require_once 'MDB2.php';

define( 'KEY_CODE', '426401c1577b645d76e4b3aa0175693212418b83' );

class Taxonomizer
{
  private $debug_level = 0;

  private $dom;

  private $input_result_cache;         // input strings that have been seen before    (store the namebankIDs that resulted)

  private $text_cache_hits;
  private $text_cache_misses;

  private $namebank_id_cache;         // namebankIDs that have been looked up before (store the classificationChains that resulted)
  
  private $namebank_cache_hits;
  private $namebank_cache_misses;

  private $report_handle;

  function __construct()
  {
      $this->dom = new DOMDocument('1.0');

      $this->dom->resolveExternals = false;
      $this->dom->strictErrorChecking  = false;

      $this->input_text_lookup = array();

      $this->namebank_id_cache = array();

      $this->text_cache_hits = 0;
      $this->text_cache_misses = 0;

      $this->namebank_cache_hits = 0;
      $this->namebank_cache_misses = 0;

      $this->report_handle = null;
  }

  // ===================================================================================

  function get_cache_stats()
  {
    $text_cache_hit_rate = ( $this->text_cache_misses == 0 ) ? 0 : $this->text_cache_hits / ( $this->text_cache_hits + $this->text_cache_misses );
    
    $namebank_cache_hit_rate = ( $this->namebank_cache_misses == 0 ) ? 0 : $this->namebank_cache_hits / ( $this->namebank_cache_hits + $this->namebank_cache_misses );

    return sprintf( "t:%.0f%%,n:%.0f%%", ( $text_cache_hit_rate * 100.0 ), ( $namebank_cache_hit_rate * 100.0 ) );
  }

  // ===================================================================================


  function set_report_handle( $report_handle )
  {
    $this->report_handle = $report_handle;
  }

  // ===================================================================================

  private function find_namebank_ids( $input_string )
  {
    // $input_string = "Genome sequence and comparative analysis of the model rodent malaria parasite Plasmodium yoelii yoelii";
    
    $namebank_ids = array();
    
    $service_url = 'http://www.ubio.org/webservices/service.php?function=taxonFinder&freeText=' . urlencode( $input_string ) . '&keyCode=' . KEY_CODE;
    
    if( $this->dom->load( $service_url ) === FALSE )
      {
	echo $service_url . "\n";
	
	print 'WARN: service call FAILED for ' . $input_name . "\n";
      }
    else
      {
	//echo $this->dom->saveXML();
	
	$nodes = $this->dom->getElementsByTagName( "namebankID" );
	
	foreach( $nodes as $node )
	  {
	    $namebank_ids[] = $node->textContent;
	    
	    if( $this->debug_level > 0 )
	      {
		echo "<<" . $node->textContent . ">>\n";
	      }
	  }
      }
    
    return $namebank_ids;		
  }


  // ===================================================================================
  
  private function get_classification_chain( $namebank_id )
  {
    // leaving out the '&classificationTitleID=100' flag gets moar hits
    
    $classification_id_lookup_url = 'http://www.ubio.org/webservices/service.php?function=classificationbank_search&namebankID=' . $namebank_id . '&keyCode=' . KEY_CODE;
    
    //echo "  doing chain lookup using " . $classification_id_lookup_url . "\n";
    
    $classificationbank_id = null;
    
    $classification_chain = array();
    
    if( $this->dom->load( $classification_id_lookup_url ) === FALSE )
      {
	echo $classification_id_lookup_url . "\n";
	
	print 'WARN: Classification ID lookup FAILED for ' . $namebank_id . "\n";
	
	return null;  // null signals that lookup failed
      }
    else
      { 
	//print( "\npppppppp\n\n" . $this->dom->saveXML()  . "\n\npppppppp\n" );
	
	// we get back one or more <classificationBankID> elements
	// (wrapped up in various levels of gubbins)
	
	// it looks like they are ranked somehow, so lets take the
	// first one and hope for the best
	
	$nodes = $this->dom->getElementsByTagName( "classificationBankID" );
	
	if( ( $nodes != null ) && ( $nodes->length > 0 ) )
	  {
	    $classificationbank_id = $this->dom->getElementsByTagName( "classificationBankID" )->item( 0 )->textContent;
	  }
	
	// finally, we can get the ancestors
	
	if( $classificationbank_id == null )
	  {
	    print 'WARN: No classification ID found for ' . $namebank_id . "\n";
	    
	    print 'WARN: URL was ' . $classification_id_lookup_url . "\n";
	    
	    return null;  // null signals that lookup failed
	  }
	else
	  { 
	    $classification_lookup_url = 'http://www.ubio.org/webservices/service.php?function=classificationbank_object&hierarchiesID=' . $classificationbank_id . '&childrenFlag=0&ancestryFlag=1&justificationsFlag=0&synonymsFlag=0&keyCode=' . KEY_CODE;
	    
	    //echo 'doing classification looking using ' . $classification_lookup_url . "\n";
	    
	    if( $this->dom->load( $classification_lookup_url ) === FALSE )
	      {
		print 'WARN: No classification found for ' . $namebank_id . "\n";
		
		return null;  // null signals that lookup failed
	      }
	    else
	      {
		//print( "\nkkkkkkkkkkkk\n\n" . $this->dom->saveXML()  . "\n\nkkkkkkkkkkkk\n" );
		
		// we get a bunch of <rankName><nameString> sets (wrapped in <Value> elements)
		
		$value_nodes = $this->dom->getElementsByTagName( "value" );
		
		$chain = array();
		
		foreach ( $value_nodes as $value_node )
		  {
		    $rank_name          = $value_node->getElementsByTagName( "rankName" )->item( 0 )->textContent; 
		    $base64_name_string = $value_node->getElementsByTagName( "nameString" )->item( 0 )->textContent;
		    
		    $name_string = base64_decode( $base64_name_string );
		    
		    //echo " %%%% " . $rank_name . " : " . $name_string . "\n";
		    
		    array_push( $chain, $name_string );
		  }
		
		// we want to iterate backwards across the array...
		
		$classification_chain = array_reverse( $chain );
		
	      } 
	  } // end parsing of classification_lookup ( convert classificationID to list of names)
      } // end of looking up classificationID
    
    if( $this->debug_level > 0 ) { print '[[chain of ' . count( $classification_chain ) . ' terms for ' . $namebank_id . "]]\n"; }
    
    return $classification_chain;
  }

  // ===================================================================================

  // ===================================================================================
  
  // returns an array of arrays, where each inner array is a "classification chain" of taxa names
  //  from more general to more specific
  //  
  //     (
  //       ( bacteria, protobacteria, cyanoprotobacteria, cyanus flagellus ),
  //       ( animalia, spideria, spiderius hairyus, tarantula tarantula )
  //     )
  // 
  public function taxonomize( $text )
  {
    $results = array();
    
    if( isset( $this->input_text_lookup[ $text ] ) )
      {
	$namebank_ids = $this->input_text_lookup[ $text ];
	
	if( $this->debug_level > 0 ) { echo "  " . count( $namebank_ids ) . " hits from text cache\n"; }

	$this->text_cache_hits++;
      }
    else
      {

	// find terms
	//
	$namebank_ids = $this->find_namebank_ids( $text );
	
	if( $this->debug_level > 0 ) 
	  {
	    echo "  " . count( $namebank_ids ) . " hits found:\n";
	    
	    foreach( $namebank_ids as $namebank_id )
	      {
		echo "     [[ " .  $namebank_id  . "]]\n";
	      }
	  }

	// and store namebankIDs for next time we see the same input string
	//
	$this->input_text_lookup[ $text ] = $namebank_ids;
	$this->text_cache_misses++;
	
      }
    
    // foreach of the 'namebank_ids', we want to convert it to a 'classification_chain'
    
    foreach( $namebank_ids as $namebank_id )
      {
	// outer1
	//  we need a ClassificationBankID for this NamebankID
	
	// which we might have cached...
	
	if( isset( $this->namebank_id_cache[ $namebank_id  ] ) )
	  {
	    $classification_chain = $this->namebank_id_cache[ $namebank_id  ];
	    
	    if( $this->debug_level > 0 ) { echo "chain of length " . count( $classification_chain ) . " pulled from cache\n"; }

	    $this->namebank_cache_hits++;
	  }
	else
	  {
	    // 'classificationTitleID=100' selects only NCBI Tax for searching
	    // and store it in the cache
	    
	    $classification_chain = $this->get_classification_chain( $namebank_id );
	    
	    if( $this->debug_level > 0 ) { echo "chain of length " . count( $classification_chain ) . " found and cached...\n"; }
	    
	    // save the chain in the cache 
	    
	    if( $classification_chain != null )
	      {
		$this->namebank_id_cache[ $namebank_id  ] = $classification_chain;

		// and optionally output the chain to the report file

		if( $this->report_handle != null )
		  {
		    $previous_entry = null;

		    foreach( $classification_chain as $index => $entry )
		      {
			fwrite( $this->report_handle, ( $entry . "\t" ) );

			if( $previous_entry != null )
			  {
			    fwrite( $this->report_handle, $previous_entry );
			  }

			fwrite( $this->report_handle, "\n" );

			$previous_entry = $entry;
		      }
		  }

	      }

	    $this->namebank_cache_misses++;
	  }
	
	$results[] = $classification_chain;
      }

    return $results;
  }
}
?>