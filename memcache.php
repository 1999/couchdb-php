<?php

namespace Core;

define( MEMCACHED_HOST, 'localhost' );
define( MEMCACHED_PORT, 11211 );

class Memcache extends \Memcache {
	public function __construct() {
		if ( false === @$this->connect( MEMCACHED_HOST, MEMCACHED_PORT ) ) {
			exit( 'could not connect to memcached server' );
		}
	}

	/**
	 * setting cache data
	 *
	 * @param string $key cache key
	 * @param string $value cache value
	 * @param integer $expires expires value
	 */
	public function set( $key, $value, $expires = 0 ) {
		$key = $this->_generateKey( $key );
		return parent::set( $key, $value, 0, $expires );
	}

	/**
	 * getting cache data
	 *
	 * @param string $key cache key
	 */
	public function get( $key ) {
		$key = $this->_generateKey( $key );
		return parent::get( $key );
	}

	/**
	 * cache data purging
	 *
	 * @param string $key cache key
	 * @param integer $timeout cache timeout
	 */
	public function delete( $key, $timeout = 0 ) {
		$key = $this->_generateKey( $key );
		return parent::delete( $key, $timeout );
	}
	
	############################################################################
	############################################################################

	private function _generateKey( $key ) {
		return ( strlen( $key ) > 250 )
			? md5( serialize( $key ) )
			: $key;
	}
}

