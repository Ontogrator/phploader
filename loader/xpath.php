
<?php

function dump_dom_node_list( $dom_node_list )
{
  if( $dom_node_list->length == 0 )
    {
      echo "[empty DomNodeList]\n";
    }
  else
    {
      
      for( $i =0; $i < $dom_node_list->length; $i++ )
	{
	  echo $dom_node_list->item( $i )->C14N();
	  echo "\n";
	}
      
    }
}



$dom = new DOMDocument('1.0');

// '<outer> <middle> <inner>one</inner> <inner>two</inner> <inner>three</inner> </middle> </outer>'

$sample_xml = 
  '<PubmedArticle>' .
  '<MedlineCitation Owner="NLM" Status="Publisher">' .
  '<PMID>19601964</PMID>' .
  '<DateCreated>' .
  '<Year>2009</Year>' .
  '<Month>7</Month>' .
  '<Day>15</Day>' .
  '</DateCreated>' .
  '<Article PubModel="Print-Electronic">' .
  '<Journal>' .
  '<ISSN IssnType="Electronic">1462-2920</ISSN>' .
  '<JournalIssue CitedMedium="Internet">' .
  '<PubDate>' .
  '<Year>2009</Year>' .
  '<Month>Jul</Month>' .
  '<Day>10</Day>' .
  '</PubDate>' .
  '</JournalIssue>' .
  '<Title>Environmental microbiology</Title>' .
  '<ISOAbbreviation>Environ. Microbiol.</ISOAbbreviation>' .
  '</Journal>' .
  '<ArticleTitle>Diversity and evolution of repABC type plasmids in Rhodobacterales.</ArticleTitle>' .
  '</Article>' .
  '</MedlineCitation>' .
  '</PubmedArticle>';

$dom->loadXML( $sample_xml );

$dom_xp = new DOMXPath( $dom );
 
// how many 'inner' nodes are there?
//
//var_dump( $dom_xp->evaluate( "count(//outer/middle/inner)" ) );
	      
// get the text contents of the second inner node

//dump_dom_node_list( $dom_xp->evaluate( "//middle/inner[2]/text()" ) );

// get the text contents of any inner node

//dump_dom_node_list( $dom_xp->evaluate( "*/inner[3]/text()" ) );

// this works fine:
echo "FOO:" . $dom_xp->evaluate( "*/DateCreated/Year/text()" )->item( 0 )->C14N() . "\n";

// this works fine:
echo "BOO:" . $dom_xp->evaluate( "//PubDate/Year/text()" )->item( 0 )->C14N() . "\n";


?>