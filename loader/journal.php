<?php

include_once( "generic-data-source.php" );

/*
  journal format is a clean tab delimited file with the columns:

    pubmed_id 
    journal_title 
    article_date
    article_title
    article_authors
    abstract 
  

  how to make an Entrez URL for searching:

  http://www.ncbi.nlm.nih.gov/sites/entrez?term=12219264

  nice and easy!

 */

class JournalDataSource extends GenericDataSource
{
  public function make_instance()
  {
    return new JournalDataSource();
  }

   public function tokenize_line( $raw_line )
   {
     $bits =  split( "\t", $raw_line );  // nicely tab delimited

     return array( 'id'              => $bits[ 0 ],
		   'journal_title'   => $bits[ 1 ],
		   'article_date'    => $bits[ 2 ],
		   'article_title'   => $bits[ 3 ],
		   'article_authors' => $bits[ 4 ],
		   'abstract'        => $bits[ 5 ] );
   }
   
   public function get_url( $parts )
   {
     return 'http://www.ncbi.nlm.nih.gov/sites/entrez?term=' . $parts[ 'id' ];
   }

   public function get_terminizer_input( $parts )
   {
     return $parts[ 'article_title' ] . ' ' . $parts[ 'abstract' ];
   }

   public function get_taxonomy_input( $parts )
   {
     return $parts[ 'article_title' ] . ' ' . $parts[ 'abstract' ];
   }
}

?>