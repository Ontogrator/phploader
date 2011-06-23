<?php

include_once( "generic-data-source.php" );

class CameraDataSource extends GenericDataSource
{
  
  public function make_instance()
  {
    return new CameraDataSource();
  }
  
  public function tokenize_line( $raw_line )
  {
    $bits = split( "\",\"", $raw_line );
    
    $id = hash( 'md5', $raw_line );

   $project        = preg_replace( "/^\\\"/", "", preg_replace( "/\\&nbsp\\;/", "", $bits[  0 ] ) );
    $sample_dataset = preg_replace( "/\\&nbsp\\;/", "", $bits[  1 ] );

    return array( 'id'                  => $id, 
		  'project'             => $project,
		  'sample_dataset'      => $sample_dataset,
		  'habitat_type'        => preg_replace( "/\\&nbsp\\;/", "", $bits[  3 ] ),
		  'geographic_location' => preg_replace( "/\\&nbsp\\;/", "", $bits[  4 ] ),
		  'sample_location'     => preg_replace( "/\\&nbsp\\;/", "", $bits[  5 ] ),
		  'country'             => preg_replace( "/\\&nbsp\\;/", "", $bits[  6 ] ),
		  'latitude'            => preg_replace( "/\\&nbsp\\;/", "", $bits[  8 ] ),
		  'longitude'           => preg_replace( "/\\&nbsp\\;/", "", $bits[  9 ] ),
		  'depth'               => preg_replace( "/\\&nbsp\\;/", "", $bits[ 10 ] ) );
  }
  
  public function get_url( $parts )
  {
    return 'http://web.camera.calit2.net/cameraweb/gwt/org.jcvi.camera.web.gwt.download.ProjectSamplesPage/ProjectSamplesPage.oa';
  }
  
  public function get_terminizer_input( $parts )
  {
    return $parts[ 'habitat_type' ] . ' ' . $parts[ 'geographic_location' ] . ' ' . $parts[ 'sample_location ' ] . ' ' . $parts[ 'country' ];
  }
  
  public function get_taxonomy_input( $parts )
  {
    return $parts[ 'project' ] . ' ' . $parts[ 'sample_dataaset' ] . ' ' . $parts[ 'habitat_type' ] . ' ' . $parts[ 'sample_location ' ];
  }
}

?>