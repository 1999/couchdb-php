<?php

namespace Core;

define( 'COUCHDB_DOMAIN', 'localhost' );
define( 'COUCHDB_PORT', 5984 );
//define( 'COUCHDB_USER', '' );
//define( 'COUCHDB_PASS', '' );

/**
 * @property Memcache $__memcache
 */
class Couchdb {
	private
		$_baseUrl = null,
		$_meta = array();

	public function  __construct() {
		$this->_baseUrl = 'http://' . COUCHDB_DOMAIN . '/';
	}

	/**
	 * generating unique identifier for new documents
	 *
	 * @return string 
	 */
	public function uniqid() {
		return sha1( uniqid() );
	}
	
	/**
	 * keys encoding
	 */
	public function encode( $key ) {
		return str_replace( array( ' ', '\\ufff0' ), array( '%20', 'ufff0' ), json_encode( $key ) );
	}

	/**
	 * getting data
	 * 
	 * @param string $dbName database name
	 * @param string $uri URL without domain and database name (ex.: "_design/list/_view/by_name" or "documentid")
	 * @return stdClass { status: [int] response code, data: [stdclass] decoded response body, etag: [string] etag }
	 */
	public function get( $dbName, $uri ) {
		$ch = $this->_createCurlHandler();
		
		if ( false === isset( $this->_meta[ $dbName ] ) && false === strpos( $uri, '_design' ) ) {
			$this->_meta[ $dbName ] = $this->_dbMeta( $dbName );
		}
		
		$cacheKey = 'couchdata:' . $dbName . ':' . $uri;
		if ( false === strpos( $uri, '_design' ) ) {
			$cacheKey .= ':' . $this->_meta[ $dbName ];
		}
		
		$cacheData = $this->__memcache->get( $cacheKey );
		if ( false !== $cacheData ) {
			return $cacheData;
		}
		
		curl_setopt( $ch, CURLOPT_URL, $this->_baseUrl . $dbName . '/' . $uri );
		$response = curl_exec( $ch );
		$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		
		if ( $status == '0' ) {
			exit( 'CouchDB server is asleep' );
		}
		
		// can cache
		if ( $status == '200' || $status == '404' ) {
			$cacheData = new \StdClass;
			$cacheData->status = $status;
			$cacheData->data = json_decode( $response );
			$cacheData->etag = $this->_meta[ $dbName ];
			
			$this->__memcache->set( $cacheKey, $cacheData );
			return $cacheData;
		}
		
		// requests errors (400 Bad Request, 500 etc)
		$output = new \StdClass;
		$output->status = $status;
		$output->data = $response;
		return $output;
	}

	/**
	 * inserting new document
	 *
	 * @param string $dbName database name
	 * @param array/stdclass $data document with existing "_id" property or index if array
	 * @return stdClass { status: [int] response code, data: [stdclass] response body }
	 */
	public function insert( $dbName, $data ) {
		$ch = $this->_createCurlHandler();
		$data = json_encode( $data );

		curl_setopt( $ch, CURLOPT_URL, $this->_baseUrl . $dbName );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-type: application/json' ) );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		$returned = curl_exec( $ch );
		$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		
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
	 * update existing document
	 *
	 * @param string $dbName database name
	 * @param string $id document id
	 * @param array/stdclass $data document with existing "_rev" property or index if array
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
		
		// invalidate document cache
		$cacheKey = 'couchdata:' . $dbName . ':' . $id;
		$this->__memcache->delete( $cacheKey )
		
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
	 * @param string @revision document "_rev" field
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
		
		// invalidate document cache
		$cacheKey = 'couchdata:' . $dbName . ':' . $id;
		$this->__memcache->delete( $cacheKey )
		
		$output = new \StdClass;
		$output->status = $status;
		$output->data = json_decode( $returned );
		return $output;
	}
	
	############################################################################
	############################################################################
	
	/**
	 * getting meta info
	 */
	private function _dbMeta( $dbName ) {
		$ch = $this->_createCurlHandler();
		$cacheKey = 'couchinfo:' . $dbName;
		
		curl_setopt( $ch, CURLOPT_URL, $this->_baseUrl . $dbName . '/_all_docs?limit=1' );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		
		if ( false !== ( $cacheData = $this->__memcache->get( $cacheKey ) ) ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'If-None-Match: ' . $cacheData ) );
		}
		
		$response = trim( curl_exec( $ch ) );
		$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		
		if ( $status == '404' ) {
			exit( 'database does not exist: ' . $dbName );
		}
		
		$headers = explode( chr( 10 ), $response );
		foreach ( $headers as $header ) {
			if ( substr( $header, 0, 5 ) == 'Etag:' ) {
				$etag = substr( $header, 6, -1 );
				if ( $cacheData != $etag ) {
					$this->__memcache->set( $cacheKey, $etag );
					return $etag;
				}
				
				return $cacheData;
			}
		}
		
		// empty database
		$this->__memcache->set( $cacheKey, '' );
		return '';
	}
	
	############################################################################
	############################################################################

	public function __get( $key ) {
		if ( $key == '__memcache' ) {
			$this->__memcache = new Memcache;
			return $this->__memcache;
		}

		return null;
	}
	
	############################################################################
	############################################################################
	
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

