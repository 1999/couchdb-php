<?php

// create sample database "testing" in Futon and run this script
$dbName = 'testing';

require_once dirname( __FILE__ ) . '/couchdb.php';

// create couchdb object instance
$couchdbObj = new Couchdb;
echo "Start executing example script...\n";

// create some view
// $doc variable can also be StdClass object
$doc = array(
	'_id' => '_design/list',
	'language' => 'javascript',
	'views' => array(
		'by_name' => array(
			'map' => 'function(doc) { emit(doc.name, doc); }'
		)
	)
);

echo "\n1. Insert sample design document...\n";
$res = $couchdbObj->insert( $dbName, $doc );
if ( $res->status == '201' ) {
	echo 'Design document inserted with status ' . $res->status . "\n";
} else {
	echo 'Could not insert the new design document. CouchDB server response status was ' . $res->status . "\n";
}

// fill database with some data
$rockstars = array(
	'Fred Durst' => 'Limp Bizkit',
	'James Hetfield' => 'Metallica',
	'Dave Grohl' => 'Nirvana',
	'Mike Shinoda' => 'Linkin Park',
	'Freddie Mercury' => 'Queen',
	'Chino Moreno' => 'Deftones'
);

echo "\n2. Fill database with some sample data...\n";

foreach ( $rockstars as $rockstar => $band ) {
	$doc = new StdClass;
	$doc->_id = $couchdbObj->uniqid();
	$doc->name = $rockstar;
	$doc->bands = array( $band );
	
	$res = $couchdbObj->insert( $dbName, $doc );
	echo 'document #' . $doc->_id . ' inserted with status ' . $res->status . "\n";
	
	switch ( $rockstar ) {
		case 'Dave Grohl' : $updateDoc = $doc; $updateDoc->_rev = $res->data->rev; break; // code follows further
		case 'Freddie Mercury' : $deleteId = $doc->_id; $deleteRev = $res->data->rev; break; // code follows further
	}
}

// fetch rockstars starting from "A" to "G" (including "G")
echo "\n3. Fetch rockstars from \"A\" to \"G\"...\n";
$startKey = 'A';
$endKey = 'G\ufff0';
$designDocUrl = '_design/list/_view/by_name?startkey=' . $couchdbObj->encode( $startKey ) . '&endkey=' . $couchdbObj->encode( $endKey );
$result = $couchdbObj->get( $dbName, $designDocUrl );
foreach ( $result->data->rows as $i => $row ) {
	echo 'Document found #' . $row->id . "\n";
	print_r( $row->value );
}

// delete some doc
echo "\n4. Delete some document...\n";
$res = $couchdbObj->delete( $dbName, $deleteId, $deleteRev );
echo 'Document #' . $deleteId . ' was deleted with status ' . $res->status . "\n";

// update some doc
echo "\n4. Update some document...\n";
$updateDoc->bands[] = 'Foo Fighters';
print_r( $updateDoc );
$res = $couchdbObj->update( $dbName, $updateDoc->_id, $updateDoc );
echo 'Document #' . $updateDoc->_id . ' was updated with status ' . $res->status . "\n";
