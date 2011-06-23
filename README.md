The original Ontogrator used a single codebase for loading and viewing.

This is the orignal loader but it also contains some viewing code which has not
been stripped out.

To run it (briefly):

Configure local connection parameters.

Use database-initialise.php to set up the database.

Grab the ontologies in the right format and use database-load-ontologies to get the simplified ontology DAG into the DB.

Get your data source into a tabular format, then make a wrapper class to load it.  See silva.php.

Run database-load-data.php which will run all the annotation and load the database.

Check that the web interface can connect and query (again, this repo does not contain the full web interface).

Done!
