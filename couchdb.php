<?php

define( 'COUCHDB_DOMAIN', 'localhost' );
define( 'COUCHDB_PORT', 5984 );
// uncomment these constants to use them in requests to CouchDB server
//define( 'COUCHDB_USER', 'couchuser' );
//define( 'COUCHDB_PASS', 'couchpassword' );

define( 'MEMCACHED_HOST', 'localhost' );
define( 'MEMCACHED_PORT', 11211 );
define( 'MEMCACHED_KEYPREFIX', 'couchdb_' );

/**
 * @property Memcache $_memcache
 */
class Couchdb {
	private
		$_baseUrl = null,
		$_memcache = null,
		$_meta = array();

	public function  __construct() {
		$this->_baseUrl = 'http://' . COUCHDB_DOMAIN . '/';
		
		$this->_memcache = new Memcache;
		$this->_memcache->connect( MEMCACHED_HOST, MEMCACHED_PORT );
	}

	/**
	 * get unique identifier for new document
	 *
	 * @return string(40) new identifier
	 */
	public function uniqid() {
		return sha1( uniqid() );
	}
	
	/**
	 * key encoding
	 *
	 * @return string encoded key
	 */
	public function encode( $key ) {
		return rawurlencode( json_encode( $key ) );
	}

	/**
	 * getting data
	 * 
	 * @param string $dbName database name
	 * @param string $uri URL without domain and database name (ex.: "_design/list/_view/by_name" or "documentId")
	 * @return stdClass { status: [int] response code, data: [stdclass] decoded response body, meta: [string, optional] database current etag }
	 */
	public function get( $dbName, $uri ) {
		$ch = $this->_createCurlHandler();
		
		if ( false === isset( $this->_meta[ $dbName ] ) ) {
			$this->_meta[ $dbName ] = $this->_dbMeta( $dbName );
		}
		
		$cacheKey = $this->_encodeMemcacheKey( 'couchdata:' . $dbName . ':' . $uri . ':' . $this->_meta[ $dbName ] );
		$cacheData = $this->_memcache->get( $cacheKey );
		if ( false !== $cacheData ) {
			return $cacheData;
		}
		
		curl_setopt( $ch, CURLOPT_URL, $this->_baseUrl . $dbName . '/' . $uri );
		$response = curl_exec( $ch );
		$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		
		if ( $status == '0' ) {
			exit( 'CouchDB server is asleep' );
		}
		
		// cache response
		if ( $status == '200' ) {
			$cacheData = new StdClass;
			$cacheData->status = $status;
			$cacheData->data = json_decode( $response );
			$cacheData->meta = $this->_meta[ $dbName ];
			
			$this->_memcache->set( $cacheKey, $cacheData );
			return $cacheData;
		}
		
		// requests errors (404 Not Found, 500 etc)
		$output = new StdClass;
		$output->status = $status;
		$output->data = json_decode( $response );
		return $output;
	}

	/**
	 * inserting new document
	 *
	 * @param string $dbName database name
	 * @param array|stdclass $data document with existing "_id" property (index if array)
	 * @return stdClass { status: [int] response code, data: [stdclass] response body }
	 */
	public function insert( $dbName, $data ) {
		$ch = $this->_createCurlHandler();
		$data = json_encode( $data );

		curl_setopt( $ch, CURLOPT_URL, $this->_baseUrl . $dbName );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-type: application/json' ) );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		$returned = curl_exec( $ch );
		$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		
		// invalidate cache
		if ( true === isset( $this->_meta[ $dbName ] ) ) {
			unset( $this->_meta[ $dbName ] );
		}

		$output = new StdClass;
		$output->status = $status;
		$output->data = json_decode( $returned );
		return $output;
	}

	/**
	 * update existing document
	 *
	 * @param string $dbName database name
	 * @param string $id document id
	 * @param array|stdclass $data document with existing "_rev" property (index if array)
	 * @return stdClass { status: [int] response code, data: [stdclass] response body }
	 */
	public function update( $dbName, $id, $data ) {
		$ch = $this->_createCurlHandler();
		$data = json_encode( $data );

		$putData = tmpfile();
		fwrite( $putData, $data );
		fseek( $putData, 0 );
		
		curl_setopt( $ch, CURLOPT_URL, $this->_baseUrl . $dbName . '/' . $id );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-type: application/json' ) );
		curl_setopt( $ch, CURLOPT_PUT, true );
		curl_setopt( $ch, CURLOPT_INFILE, $putData );
		curl_setopt( $ch, CURLOPT_INFILESIZE, mb_strlen( $data ) );
		$returned = curl_exec( $ch );
		$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		
		fclose( $putData );
		
		// invalidate cache
		if ( true === isset( $this->_meta[ $dbName ] ) ) {
			unset( $this->_meta[ $dbName ] );
		}
		
		$output = new \StdClass;
		$output->status = $status;
		$output->data = json_decode( $returned );
		return $output;
	}

	/**
	 * purging data
	 *
	 * @param string $dbName database name
	 * @param string $id document id
	 * @param string $revision document "_rev" field
	 * @return stdClass { status: [int] response code, data: [stdclass] response body }
	 */
	public function delete( $dbName, $id, $revision ) {
		$ch = $this->_createCurlHandler();
		
		curl_setopt( $ch, CURLOPT_URL, $this->_baseUrl . $dbName . '/' . $id . '?rev=' . $revision );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-type: application/json' ) );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );
		$returned = curl_exec( $ch );
		$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		
		// invalidate cache
		if ( true === isset( $this->_meta[ $dbName ] ) ) {
			unset( $this->_meta[ $dbName ] );
		}
		
		$output = new StdClass;
		$output->status = $status;
		$output->data = json_decode( $returned );
		return $output;
	}
	
	
	/* PRIVATE METHODS HERE */
	
	/**
	 * encoding memcached cache key
	 */
	private function _encodeMemcacheKey( $cacheKey ) {
		return md5( serialize( MEMCACHED_KEYPREFIX . $cacheKey ) );
	}
	
	/**
	 * getting database meta info
	 */
	private function _dbMeta( $dbName ) {
		$ch = $this->_createCurlHandler();
		$cacheKey = $this->_encodeMemcacheKey( 'couchinfo:' . $dbName );
		
		curl_setopt( $ch, CURLOPT_URL, $this->_baseUrl . $dbName . '/_all_docs?limit=1' );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		
		if ( false !== ( $cacheData = $this->_memcache->get( $cacheKey ) ) ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'If-None-Match: ' . $cacheData ) );
		}
		
		$response = trim( curl_exec( $ch ) );
		$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		
		if ( $status == '0' ) {
			exit( 'CouchDB server is asleep' );
		}
		
		if ( $status == '404' ) {
			exit( 'database does not exist: ' . $dbName );
		}
		
		// empty database
		if ( preg_match( '#etag:\s"(.*?)"#im', $response, $matches ) == 0 ) {
			if ( false !== $cacheData ) {
				$this->_memcache->delete( $cacheKey, 0 );
			}
			
			return '';
		}
		
		// database content has changed / first database request
		if ( $cacheData != $matches[1] ) {
			$this->_memcache->set( $cacheKey, $matches[1] );
			return $matches[1];
		}
			
		return $cacheData;
	}
	
	/**
	 * create initial CURL request object
	 */
	private function _createCurlHandler() {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_PORT, COUCHDB_PORT );
		
		if ( true === defined( 'COUCHDB_USER' ) && true === defined( 'COUCHDB_PASS' ) ) {
			curl_setopt( $ch, CURLOPT_USERPWD, COUCHDB_USER . ':' . COUCHDB_PASS );
		}
		
		return $ch;
	}
}

