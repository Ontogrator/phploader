<?php

include_once( 'generic-data-source.php' );

class GoldDataSource extends GenericDataSource
{
  
  public function make_instance()
  {
    return new GoldDataSource();
  }
  

  public function tokenize_line( $raw_line )
  {
    $bits = split( "\t", $raw_line );
    
    return array( 'id'         => $bits[ 38 ],
		  'gcat_entry' => $bits[ 41 ],
		  'organism'   => $bits[  8 ],
		  'strain'     => $bits[  9 ],
		  'habitat'    => $bits[ 23 ],
		  'isolation'  => $bits[ 34 ] );
  }
  
  public function get_url( $parts )
  {
    return 'http://genomesonline.org/GOLD_CARDS/' . $parts[ 'id' ] . ".html";   // the GoldStamp is the same as the ID
  }
  
  public function get_terminizer_input( $parts )
  {
    return $parts[ 'organism' ] . ' ' . $parts[ 'strain' ] . ' ' . $parts[ 'habitat' ] . ' ' . $parts[ 'isolation' ];
  }
  
  public function get_taxonomy_input( $parts )
  {
    return $parts[ 'organism' ] . ' ' . $parts[ 'strain' ] . ' ' . $parts[ 'habitat' ] . ' ' . $parts[ 'isolation' ];
  }
}

?>