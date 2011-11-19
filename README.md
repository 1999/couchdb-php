Introduction
============

[CouchDB](http://couchdb.apache.org/) is amazing and easy-to-use NoSQL document-oriented database. Due to the origin of HTTP and the fact that CouchDB uses REST API, **the best way to interact with CouchDB is events-using webserver, such as [node.js](http://nodejs.org) with [Cradle](https://github.com/cloudhead/cradle)** module installed. Node.js can save the data between the requests of different users and that's why it doesn't need the cache layer.

However CouchDB REST API allows everyone to interact with CouchDB. In case of php we do not have such an opportunity as node.js/Cradle can afford, so **Memcached server here is a cache layer between CouchDB and your php-written app**. CouchDB documents and views have ETags which uniquely represent the documents. If the document changes, its ETag changes too. So Memcached stores documents' ETags and lets us make few requests to CouchDB server if we have fetched the valid documents before the request.

But **the most interesting thing is that using this package can really speed up your app if you perform 50 or more requests at one time**. The fact is that fetching documents with "If-None-Match" header and getting 304 response status is really faster than fetching documents without it, but if requests' number increases the performance becomes terrible. And here comes tha fact that the databases in CouchDB are "solid". This means that the database has its own ETag which changes if ANY of the database's documents change or get deleted. Using this fact we can cache the documents and look for database's ETag, thus performing less requests to CouchDB server. In fact if your app works with 20 databases and makes 100 requests to CouchDB, couchdb-php will perform only 20 requests and you'll get the needed data really faster.

Dependencies
============

* **php5** or higher
* **php_curl** module to interact with CouchDB server via REST API
* **Memcached** server and **php_memcache** module to interact with it
* **json_(decode|encode)** functions to work with data

Synopsis
========

Fetching document by its id or view by its url

``` php
$Couchdb = new Couchdb;
$url = 'some_document_id';
$Couchdb->get( $databasename, $url );
```

Inserting document

``` php
$Couchdb = new Couchdb;
$document = array(
	'_id' => 'document_id',
	'field' => 'data'
);
$Couchdb->insert( $databasename, $document );
```

Updating document

``` php
$Couchdb = new Couchdb;
$Couchdb->update( $databasename, $id, $document ); // note that $document must have "_rev" field
```

Deleting document

``` php
$Couchdb = new Couchdb;
$Couchdb->delete( $databasename, $id, $revision );
```

Generating unique identifier

``` php
$Couchdb = new Couchdb;
$document = new StdClass;
$document->_id = $Couchdb->uniqid();
$document->field = 'data';
```

Encoding url parameters

``` php
$Couchdb = new Couchdb;
$startKey = array( 'Ann' );
$endKey = array( 'George' )'
$viewUrl = '_design/list/_views/by_firstname?startkey=' . $Couchdb->encode( $startKey ) . '&endkey=' . $Couchdb->encode( $endKey );
$res = $Couchdb->get( $databasename, $viewUrl );
```

Live example
=============

Create database "testing" in Futon and run example.php

Package author
==============

[Dmitry Sorin](http://www.staypositive.ru) @ [allcafe.ru](http://allcafe.ru)