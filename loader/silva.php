<?php

include_once( "generic-data-source.php" );


class SilvaDataSource extends GenericDataSource
{
  public function make_instance()
  {
    return new SilvaDataSource();
  }

  public function tokenize_line( $raw_line )
  {
    $bits = split( "\t", $raw_line );

    return array( 'id'               => $bits[ 0 ] . "." . $bits[ 1 ] . "." . $bits[ 2 ],
                  'title'            => $bits[ 3 ],
                  'country'          => $bits[ 4 ],
                  'isolation_source' => $bits[ 5 ],
                  'host'             => $bits[ 6 ],
                  'strain'           => $bits[ 7 ],
                  'publication_id'   => $bits[ 8 ],
                  'pubmed_id'        => $bits[ 9 ],
                  '*url_end'         => $bits[ 10 ] . '/' . $bits[ 0 ],
                  );

  }
  
  public function get_url( $parts )
  {
    return 'http://www.arb-silva.de/browser/'  . $parts[ '*url_end' ];
  }

  public function get_terminizer_input( $parts )
  {
    return $parts[ 'title' ] . ' ' . $parts[ 'country' ] . ' ' . $parts[ 'isolation_source' ] . ' ' . $parts[ 'host' ];
  }
  
   public function get_taxonomy_input( $parts )
   {
     return $parts[ 'title' ] . ' ' . $parts[ 'isolation_source' ] . ' ' . $parts[ 'host' ] . ' ' . $parts[ 'strain' ];
   }
}

?>