<?php
namespace Dinosaur\Cache;

use Dinosaur\Logger;

class ObjectCache {
	const DEFAULT_EXPIRATION = 0;

	protected $globalPrefix;
	protected $sitePrefix;

	protected $global_groups = [];
	protected $no_mc_groups = [];
	protected $cache = [];
	/**
	 * @var \Memcache
	 */
	protected $mc;
	/**
	 * @var \NYT\Cache\ObjectCache
	 */
	protected static $instance;

	public static function instance() {
		if ( ! static::$instance ) {
			static::$instance = new static;
		}
		return static::$instance;
	}

	public function __construct() {
		global $blog_id, $table_prefix;
		$servers = [
			getenv( 'MEMCACHED_HOST' ) . ':11211',
		];

		$this->mc = new \Memcache();
		foreach ( $servers as $server  ) {
			list( $node, $port ) = explode( ':', $server );
			$this->mc->addServer(
				// host
				$node,
				// port
				$port,
				// persistent
				true,
				// bucket weight
				1,
				// connection timeout
				1,
				// retry interval, how often a failed server is retried
				3,
				// status, server is considered online
				true,
				// failure callback
				[ $this, 'failureCallback' ]
			);
			$this->mc->setCompressThreshold( 20000, 0.2 );
		}

		$this->globalPrefix = is_multisite() ? '' : $table_prefix;
		$this->sitePrefix = ( is_multisite() ? $blog_id : $table_prefix ) . ':';
	}

	public function add( $id, $data, string $group = 'default', int $expire = 0 ) {
		$key = $this->key( $id, $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		if ( in_array( $group, $this->no_mc_groups ) ) {
			$this->cache[ $key ] = $data;
			return true;
		} elseif ( isset( $this->cache[ $key ] ) && false !== $this->cache[ $key ] ) {
			return false;
		}

		if ( ! $expire ) {
			$expire = static::DEFAULT_EXPIRATION;
		}

		$result = $this->mc->add( $key, $data, false, $expire );

		if ( false !== $result ) {
			$this->cache[ $key ] = $data;
		}

		return $result;
	}

	public function incr( $id, int $n = 1, string $group = 'default' ) {
		$key = $this->key( $id, $group );
		$this->cache[ $key ] = $this->mc->increment( $key, $n );
		return $this->cache[ $key ];
	}

	public function decr( $id, int $n = 1, string $group = 'default' ) {
		$key = $this->key( $id, $group );
		$this->cache[ $key ] = $this->mc->decrement( $key, $n );
		return $this->cache[ $key ];
	}

	public function close() {
		$this->mc->close();
	}

	public function add_global_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}
		$this->global_groups = array_merge( $this->global_groups, $groups );
		$this->global_groups = array_unique( $this->global_groups );
	}

	public function add_non_persistent_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}
		$this->no_mc_groups = array_merge( $this->no_mc_groups, $groups );
		$this->no_mc_groups = array_unique( $this->no_mc_groups );
	}

	public function delete( $id, $group = 'default' ): bool
	{
		$key = $this->key( $id, $group );

		if ( in_array( $group, $this->no_mc_groups ) ) {
			unset( $this->cache[ $key ] );
			return true;
		}

		$result = $this->mc->delete( $key );

		if ( false !== $result ) {
			unset( $this->cache[ $key ] );
		}

		return $result;
	}

	public function flush(): bool
	{
		// Don't flush if multi-blog.
		if ( is_multisite() ) {
			return true;
		}

		return $this->mc->flush();
	}

	public function get( $id, string $group = 'default', bool $force = false ) {
		$key = $this->key( $id, $group );
		$value = false;

		if ( isset( $this->cache[ $key ] ) && ( ! $force || in_array( $group, $this->no_mc_groups ) ) ) {
			if ( is_object( $this->cache[ $key ] ) ) {
 				$value = clone $this->cache[ $key ];
			} else {
				$value = $this->cache[ $key ];
			}
		} elseif ( in_array( $group, $this->no_mc_groups ) ) {
			$this->cache[ $key ] = false;
		} else {
			$value = $this->mc->get( $key );
			if ( is_null( $value ) ) {
				$value = false;
			}
			$this->cache[ $key ] = $value;
		}

		if ( 'checkthedatabaseplease' === $value ) {
			unset( $this->cache[ $key ] );
		}

		return $value;
	}

//	public function get_multi( $groups ) {
//		/*
//		format: $get['group-name'] = array( 'key1', 'key2' );
//		*/
//		$return = [];
//		foreach ( $groups as $group => $ids ) {
//			foreach ( $ids as $id ) {
//				$key = $this->key( $id, $group );
//				if ( isset( $this->cache[ $key ] ) ) {
//					if ( is_object( $this->cache[ $key ] ) )
//						$return[ $key ] = clone $this->cache[ $key ];
//					else
//						$return[ $key ] = $this->cache[ $key ];
//					continue;
//				} elseif ( in_array( $group, $this->no_mc_groups ) ) {
//					$return[ $key ] = false;
//					continue;
//				} else {
//					$return[ $key ] = $this->mc->get( $key );
//				}
//			}
//			if ( $to_get ) {
//				$vals = $this->mc->get_multi( $to_get );
//				$return = array_merge( $return, $vals );
//			}
//		}
//
//		$this->cache = array_merge( $this->cache, $return );
//		return $return;
//	}

	public function key( $key, string $group = 'default' ): string
	{
		if ( false !== array_search( $group, $this->global_groups ) ) {
			$prefix = $this->globalPrefix;
		} else {
			$prefix = $this->sitePrefix;
		}
		return preg_replace( '/\s+/', '', WP_CACHE_KEY_SALT . "$prefix$group:$key" );
	}

	public function replace( $id, $data, string $group = 'default', int $expire = 0 ): bool
	{
		$key = $this->key( $id, $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		if ( ! $expire ) {
			$expire = static::DEFAULT_EXPIRATION;
		}

		$result = $this->mc->replace( $key, $data, 0, $expire );

		if ( false !== $result ) {
			$this->cache[ $key ] = $data;
		}

		return $result;
	}

	public function set( $id, $data, string $group = 'default', int $expire = 0 ): bool
	{
		$key = $this->key( $id, $group );

		if ( isset( $this->cache[ $key ] ) && ( 'checkthedatabaseplease' === $this->cache[ $key ] ) ) {
			return false;
		}

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$this->cache[ $key ] = $data;

		if ( in_array( $group, $this->no_mc_groups ) ) {
			return true;
		}

		if ( ! $expire ) {
			$expire = static::DEFAULT_EXPIRATION;
		}

		return $this->mc->set( $key, $data, false, $expire );
	}

	public function switch_to_blog( int $blog_id ) {
		global $table_prefix;
		$this->sitePrefix = ( is_multisite() ? $blog_id : $table_prefix ) . ':';
	}

	public function failureCallback( string $host, int $port ) {
		$logger = Logger::get( 'Memcached' );
		$logger->warn( "Connection failure for $host:$port\n" );
	}
}