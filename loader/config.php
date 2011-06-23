<?php

// ============ data sources and ontologies ========================
	  
//   small = <10K   
//  medium = >10K <250K 
//   large = >250K

$data_source_infos = array ( "GOLD"       => array( "description" => "Genomes Online",  
						    "skip_header_lines" => 1, 
						    "identifier_length" => 10,
						    "data_size" => "small",
						    "columns" => array( "gcat_entry" => 16,
									"organism"=>32,
									"strain" => 32,
									"habitat" => 128,
									"isolation" => 128 ),
						    "data_source_wrapper_class" => "GoldDataSource" ),

			     "STRAININFO" => array( "description" => "StrainInfo.net : cultured micro-organisms", 
						    "skip_header_lines" => 1, 
						    "identifier_length" => 8,
						    "data_size" => "medium",
						    "columns" => array ( "culture_id" => 16,
									 "strain_number" => 16,
									 "species_name" => 60,
									 "habitat" => 100,
									 "country" => 48 ),
						    "data_source_wrapper_class" => "StrainInfoDataSource" ),
			     
			     "CAMERA"     => array( "description" => "CAMERA : marine metagenomics", 
						    "skip_header_lines" => 1, 
						    "identifier_length" => 128,
						    "data_size" => "small",
						    "columns" => array ( "project" => 32,
									 "sample_dataset" => 32,
									 "habitat_type" => 128,
									 "geographic_location" => 128,
									 "sample_location" => 128,
									 "country" => 128,
									 "latitude" => 16,
									 "longitude" => 16,
									 "depth" => 16 ),
						    "data_source_wrapper_class" => "CameraDataSource" ),
			     
			     "SILVA"      => array( "description" => "SILVA : ribosomal RNA sequences", 
						    "skip_header_lines" => 0, 
						    "identifier_length" => 16,
						    "data_size" => "large",
						    "columns" => array ( "title" => 64,
									 "country" => 64,
									 "isolation_source" => 128,
									 "host" => 128,
									 "strain" => 128,
									 "publications" => 128 ),
						    "data_source_wrapper_class" => "SilvaDataSource" ),
			     
			     "JOURNAL"    => array( "description" => "abstracts from recent environmental journals", 
						    "skip_header_lines" => 0, 
						    "identifier_length" => 32,
						    "data_size" => "small",
						    "columns" => array ( "journal_title" => 128,
									 "article_date" => 32,
									 "article_title" => 128,
									 "article_authors" => 128,
									 "abstract" => 256 ),
						    "data_source_wrapper_class" => "JournalDataSource" ) 
			     );

$ontology_infos = array( "GAZ"  => array( "lookup" => "terminizer" ), 
			 "ENVO" => array( "lookup" => "terminizer" ),
			 "MAT"  => array( "lookup" => "terminizer" ),
			 "TAX"  => array( "lookup" => "ubio_taxon_finder" ) );


// ============ common stuff ========================


define( "DATABASE_NAME", "ontogrator" );

define( "OMIXED_RESOURCE",  "terminizer" );
define( "OMIXED_HOST_NAME", "guest" );
define( "OMIXED_PASSWORD",  "guest" );


// ============ server specific stuff ========================

$good = false;

$serverName = getenv('HOSTNAME');


//echo "system: $serverName\n";


    define( "DB_SYSTEM_DSN", "mysql://dave@localhost" );
    //define( "DB_SYSTEM_DSN", "postgres://dave@localhost" );
    
   
    //define( "OMIXED_PHP_LIB_HOME", "C:/Users/dave/bio/omixed/php_library/" );
    define( "OMIXED_PHP_LIB_HOME", "/home/dave/bio/omixed/php_library/" );
    
    define( "OMIXED_HOST",      "localhost" );
   
    define( "TERMINIZER_BASE_URL",  "http://terminizer.org/terminizerBackEnd/service" );


?>
