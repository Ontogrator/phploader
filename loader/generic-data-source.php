<?php

abstract class GenericDataSource
{
  abstract public function tokenize_line( $raw_line );
  abstract public function get_url( $parts );
  abstract public function get_terminizer_input( $parts );
  abstract public function get_taxonomy_input( $parts );

  abstract public function make_instance();
}

function instantiate_data_source_from_name( $data_source_name )
{
  global $data_source_infos;

  $class_name = $data_source_infos[ $data_source_name ][ "data_source_wrapper_class" ];

  return call_user_func( array( $class_name, 'make_instance' ) );  // 
}

?>