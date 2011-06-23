<?php

include_once( 'generic-data-source.php' );

class StrainInfoDataSource extends GenericDataSource
{
  
  public function make_instance()
  {
    return new StrainInfoDataSource();
  }
  

  public function tokenize_line( $raw_line )
  {
    $bits = split( "\t", $raw_line );
    
    return array( 'id'            => $bits[ 0 ],
		  'strain_number' => $bits[ 1 ],
		  'species_name'  => $bits[ 2 ],
		  'habitat'       => $bits[ 3 ],
		  'country'       => trim( $bits[ 4 ] . ' ' . $bits[ 5 ] )
		  );
  }
      
  public function get_url( $parts )
  {
    return 'http://www.straininfo2.ugent.be/strain/' . $parts[ 'id' ];
  }
  
  public function get_terminizer_input( $parts )
  {
    return $parts[ 'habitat' ] . ' ' . $parts[ 'country' ];
  }
  
  public function get_taxonomy_input( $parts )
  {
    return $parts[ 'species_name' ];
  }
}

?>