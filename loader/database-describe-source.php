<?php

/*
  interface:

  
  database-describe-source.php?name=[x]

     -> returns the column information for the source [x]

  in each case, the result is one column per line; "name" "\t" "width"

 */

include_once( 'config.php' );

if( isset( $_REQUEST[ 'name' ] ) == false )
  {
    die( '!! - no data source named' );
  }

$data_source_name = $_REQUEST[ 'name' ];

foreach( $data_source_infos as $name => $info )
  {
    if( $data_source_name == $name )
      {
	$id_length = $info[ "identifier_length" ];
	
	echo 'id' . "\t" . $id_length  . "\n";

	echo 'url' . "\t" . '*' . "\n";

	foreach( $info[ "columns" ] as $column => $width )
	  {
	    echo $column . "\t" . $width . "\n";
	  }

	exit;
      }

  }

die( '!! - unrecognised data source' );

?>