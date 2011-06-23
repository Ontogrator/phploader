<?php

include_once( 'config.php' );

foreach( $data_source_infos as $key => $info )
  {
    echo $key . "\t" . $info[ "description" ] . "\t" . $info[ "columns" ] . "\n";
  }

?>