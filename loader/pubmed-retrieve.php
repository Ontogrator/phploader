<?php

require_once ( 'terminize.php' );
require_once ( 'database-utils.php' );

$results_desired = 1024;

$max_entries = $results_desired;  // how many results to process

$interesting_ontologies = array( "GAZ", "ENVO", "MAT" );

$terminizer = new Terminizer();

$database_utils = new DatabaseUtils();

$database_connection =& MDB2::connect( DB_SYSTEM_DSN . '/' . DATABASE_NAME );


if ( PEAR::isError( $database_connection ) ) { die( $database_connection->getMessage() ); }


// wipe out any existing hits
//
$database_utils->remove_hits( $database_connection, "PUBMED", $interesting_ontologies );

$database_utils->empty_table( $database_connection, "PUBMED_entry" );


// build the query URL
//
$keyword_search_url = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?";

$keyword_search_url .= "dbname=" . urlencode( "pubmed" );           // which database to query
$keyword_search_url .= "&";
$keyword_search_url .= "term=" . urlencode( "" );
$keyword_search_url .= "&";
$keyword_search_url .= "reldate=" . urlencode( "7" );               // last 7 days
$keyword_search_url .= "&";
$keyword_search_url .= "retmax=" . urlencode( $results_desired );   // at most N hits

// do the query
//
$dom = new DOMDocument('1.0');
$dom->load( $keyword_search_url );

$nodes = $dom->getElementsByTagName( "Id" ); 

// we get N primary keys for articles, one per <Id> element 

$primary_keys = array();

foreach ( $nodes as $node )
  {
    $id = $node->textContent;
    
    $primary_keys[] = $id;

    //echo $id . "\n";
  }

foreach( $primary_keys as $primary_key )
  {
    if( $max_entries-- >= 0 )
      {
	$get_abstract_url = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?";
	
	$get_abstract_url .= "db=" . urlencode( "pubmed" );   // PubMed Central
	$get_abstract_url .= "&";

	$get_abstract_url .= "id=" . urlencode(  $primary_key );   // PubMed Central
	$get_abstract_url .= "&";
	
	$get_abstract_url .= "retmode=xml";
	
	//$get_abstract_url .= "&";
	//$get_abstract_url .= "rettype=abstract";


	//echo "=================================================================\n";
	echo $get_abstract_url . "\n";
	

	$dom = new DOMDocument('1.0');
	$dom->load( $get_abstract_url );

	//echo $dom->saveXML();

	$article_title = "";
	$article_authors = "";
	$abstract = "";
	$journal_title = "";

	$nodes = $dom->getElementsByTagName( "ArticleTitle" ); 

	foreach ( $nodes as $node )
	  {
	    $article_title .= $node->textContent;
	  }

	$nodes = $dom->getElementsByTagName( "Title" ); 

	foreach ( $nodes as $node )
	  {
	    $journal_title .= $node->textContent;
	  }

	$nodes = $dom->getElementsByTagName( "LastName" ); 
	
	//echo $dom->saveXML();
	
	foreach ( $nodes as $node )
	  {
	    if( strlen( $article_authors ) > 0 )
	      {
		$article_authors .= ",";
	      }

	    $article_authors .=  $node->textContent;
	  }
	

	$abstract = $dom->getElementsByTagName( "Abstract" )->item( 0 )->textContent;

	// $abstract = "a river of blood in Moscow";

	echo "-----------------------------------------------------------------\n";
	echo $journal_title . "\n";
	echo "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
	echo $article_title . "\n";
	echo "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
	echo $article_authors . "\n";
	echo "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
	echo $abstract;
	echo '(' . strlen( $abstract ) . ' chars)';
	echo "\n";
	echo "-----------------------------------------------------------------\n";

	$hits = $terminizer->lookup( $database_connection, $interesting_ontologies, $abstract );
	
	
	// count (and optionally list) the hits..

	$hit_count = 0;

	foreach( $interesting_ontologies as $interesting_ontology )
	  {
	    if( array_key_exists( $interesting_ontology, $hits ) )
	      {
		foreach( $hits[ $interesting_ontology ] as $hit )
		  {
		    $hit_count++;

		    echo "   -> " . $interesting_ontology . " " . $hit . "\n";
		  }
	      }
	  }
	
	if( $hit_count > 0 )
	  {
	    // store the entry in the database

	    $database_utils->store_entry( $database_connection, "PUBMED", 
					  array( "pubmed_id", "publication_name", "article_title", "article_authors" ),
					  array( $primary_key, $journal_title, $article_title, $article_authors ) );
	    

	    // then store the hits in the database
	    $database_utils->store_hits( $database_connection, "PUBMED", "pubmed_id", $primary_key, $hits, $interesting_ontologies );
	  }

      }

  }


?>
