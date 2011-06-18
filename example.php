<?php

// create sample database "testing" in Futon and run this script
define( 'DBNAME', 'testing' );

require_once dirname( __FILE__ ) . '/couchdb.php';
require_once dirname( __FILE__ ) . '/memcache.php';

// create couchdb object instance
$couchdbObj = new Core\Couchdb;

// create some view
$doc = new \StdClass;
$doc->_id = '_design/list';
$doc->language = 'javascript';
$doc->views = new \StdClass;
$doc->views->by_name = new \StdClass;
$doc->views->by_name->map = 'function(doc) { emit(doc.name, doc); }';
$result = $couchdbObj->insert( DBNAME, $doc );
echo 'View document inserted with status: ' . $result->status . '<br>';

// put some data
$rockstars = array(
	'Fred Durst' => 'Limp Bizkit',
	'James Hetfield' => 'Metallica',
	'Kurt Cobain' => 'Nirvana',
	'Mike Shinoda' => 'Linkin Park',
	'Freddie Mercury' => 'Queen',
	'Chino Moreno' => 'Deftones'
);

// insert documents
$doc = new \StdClass;
foreach ( $rockstars as $rockstar => $band ) {
	$doc->_id = $couchdbObj->uniqid();
	$doc->name = $rockstar;
	$doc->band = $band;
	
	$result = $couchdbObj->insert( DBNAME, $doc );
	echo 'document #' . $doc->_id . ' inserted with status: ' . $result->status . '<br>';
}

// fetch rockstars starting from "J" till "Z"
echo 'fetching rockstars from "A" to "G"...<br>';
$startKey = 'A';
$endKey = 'G\ufff0';
$designDocUrl = '_design/list/_view/by_name?startkey=' . $couchdbObj->encode( $startKey ) . '&endkey=' . $couchdbObj->encode( $endKey );
$result = $couchdbObj->get( DBNAME, $designDocUrl );
foreach ( $result->data->rows as $i => $row ) {
	echo '<pre>';
	print_r( $row->value );
	echo '</pre>';
	
	if ( $i == 0 ) {
		$dropDoc = $row->value;
	}
	
	$updateDoc = $row->value;
}

// delete some doc
$result = $couchdbObj->delete( DBNAME, $dropDoc->_id, $dropDoc->_rev );
echo 'document #' . $dropDoc->_id . ' deleted with status: ' . $result->status . '<br>';

// update some doc
$updateDoc->new_field = array( 'some data' );
$result = $couchdbObj->update( DBNAME, $updateDoc->_id, $updateDoc );
echo 'document #' . $updateDoc->_id . ' updated with status: ' . $result->status;

