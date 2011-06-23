<?php

require_once ( 'terminize.php' );
require_once ( 'database-utils.php' );

/*

  ****  interesting journals

    --------------------------------------------------------
    JrId: 698
    JournalTitle: Applied and environmental microbiology
    MedAbbr: Appl Environ Microbiol
    ISSN: 0099-2240
    ESSN: 1098-5336
    IsoAbbr: Appl. Environ. Microbiol.
    NlmId: 7605801
    --------------------------------------------------------
    JrId: 21501
    JournalTitle: Environmental microbiology
    MedAbbr: Environ Microbiol
    ISSN: 1462-2912
    ESSN: 1462-2920
    IsoAbbr: Environ. Microbiol.
    NlmId: 100883692
    --------------------------------------------------------
    JrId: 8661
    JournalTitle: Microbial ecology
    MedAbbr: Microb Ecol
    ISSN: 0095-3628
    ESSN: 1432-184X
    IsoAbbr: Microb. Ecol.
    NlmId: 7500663
    --------------------------------------------------------
    JrId: 21505
    JournalTitle: International journal of systematic and evolutionary microbiology
    MedAbbr: Int J Syst Evol Microbiol
    ISSN: 1466-5026
    ESSN: 1466-5034
    IsoAbbr: Int. J. Syst. Evol. Microbiol.
    NlmId: 100899600
    --------------------------------------------------------
    ISME Journal: Multidisciplinary Journal of Microbial Ecology 
    +NATURE Pub Grp+



  **** docs for the entrez web service

  http://eutils.ncbi.nlm.nih.gov/corehtml/query/static/eutils_help.html


  term={ISSN}[ta]

   eg for Environ Microbiol, the ISSN is 1462-2912

  http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=1462-2912[ta]

  need to specify some dates or 'retmax=N' otherwise we get the default value of 20 hits


 */

// ================================================================

function remove_quotes_and_tabs_and_CRLFs( $input_string )
{
  return preg_replace( "/[\t|\n|\r|\"]/", "", $input_string );
}


function text_content( $dom_node_list )
{
  $result = '';
  
  if( isset( $dom_node_list ) )
    {
      for( $i =0; $i < $dom_node_list->length; $i++ )
	{
	  $result .= $dom_node_list->item( $i )->textContent;
	}
    }
  
  return $result;
}

function extract_date( $dom_document )
{
  //echo $dom_document->saveXML();

  $dom_xp = new DOMXPath( $dom_document );
  
  // the "//PubDate" means start the search anywhere down the DOM tree...

  $combi_date = null;

  $year = text_content( $dom_xp->evaluate( "//PubDate/Year/text()" ) );
  $month = text_content( $dom_xp->evaluate( "//PubDate/Month/text()" ) );
  $date  = text_content( $dom_xp->evaluate( "//PubDate/Day/text()" ) );
  
  if( strlen( $year ) == 0 )
    {
      $combi_date  = text_content( $dom_xp->evaluate( "//PubDate/MedlineDate/text()" ) );

      if( strlen( $combi_date ) == 0 )
	{
	  $year = text_content( $dom_xp->evaluate( "//Year[0]/text()" ) );
	  $month = text_content( $dom_xp->evaluate( "//Month[0]/text()" ) );
	  $date  = text_content( $dom_xp->evaluate( "//Day[0]/text()" ) );
	}

      if( strlen( $year ) == 0 )
	{
	  $combi_date = '[unknown]'; 
	}
    }


  if( $combi_date != null )
    {
      return $combi_date;
    }
  else
    {
      if( strlen( $date ) > 0 )
	{
	  return $year . "-" . $month . "-" . $date;
	}
      else
	{
	  return $year . "-" . $month;
	}
    }
}


// ================================================================


if( $argc < 2 )
  {
    die( 'usage: ' . $argv[ 0 ] . ' PUBMED-ISSN  [MAX-RESULTS]' . "\n" );
  }

$interesting_issn = $argv[ 1 ];    // e.g. '1462-2912';

$results_desired = ( $argc > 2 ) ? $argv[ 2 ] : 1024;

$max_entries = $results_desired;  // how many results to process

// build the query URL
//
$keyword_search_url = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?";

$keyword_search_url .= "dbname=" . urlencode( "pubmed" );                 // which database to query
$keyword_search_url .= "&";
$keyword_search_url .= "term=" . urlencode( $interesting_issn . '[ta]' ); // search term is "journal issn code"
$keyword_search_url .= "&";
$keyword_search_url .= "retmax=" . urlencode( $results_desired );         // at most N hits

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
	
	$get_abstract_url .= "&";
	$get_abstract_url .= "rettype=abstract";


	//echo $get_abstract_url . "\n";
	

	$dom = new DOMDocument('1.0');
	$dom->load( $get_abstract_url );


	$article_title = "";
	$article_authors = "";
	$abstract = "";
	$journal_title = "";

	$nodes = $dom->getElementsByTagName( "ArticleTitle" ); 

	foreach ( $nodes as $node )
	  {
	    $article_title .= $node->textContent;
	  }

	$article_title = remove_quotes_and_tabs_and_CRLFs( trim( $article_title ) );

	$nodes = $dom->getElementsByTagName( "Title" ); 

	foreach ( $nodes as $node )
	  {
	    $journal_title .= $node->textContent;
	  }

	$journal_title = remove_quotes_and_tabs_and_CRLFs( trim( $journal_title ) );

	$nodes = $dom->getElementsByTagName( "LastName" ); 
	
	foreach ( $nodes as $node )
	  {
	    if( strlen( $article_authors ) > 0 )
	      {
		$article_authors .= ",";
	      }
	    
	    $article_authors .=  $node->textContent;
	  }
	
	$article_date = remove_quotes_and_tabs_and_CRLFs( trim( extract_date( $dom ) ) );

	$article_authors = remove_quotes_and_tabs_and_CRLFs( trim( $article_authors ) );
	
	$abstract = remove_quotes_and_tabs_and_CRLFs( trim( $dom->getElementsByTagName( "Abstract" )->item( 0 )->textContent ) );
	
	
	//echo "-----------------------------------------------------------------\n";
	//echo $journal_title . "\n";
	//echo "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
	//echo $article_title . "\n";
	//echo "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
	//echo $article_authors . "\n";
	//echo "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
	//echo $abstract . "\n";
	//echo "-----------------------------------------------------------------\n";

	echo $primary_key . "\t" . $journal_title . "\t" . $article_date . "\t" . $article_title . "\t" . $article_authors . "\t" . $abstract . "\n";

	//echo $primary_key . "\t" . $article_date . "\n";
      }

  }


?>
