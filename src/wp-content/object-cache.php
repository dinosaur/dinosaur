<?php
/*
Plugin Name: Memcached
Description: Memcached backend for the WP Object Cache.
Version: 2.0.2
Plugin URI: http://wordpress.org/extend/plugins/memcached/
Author: Ryan Boren, Denis de Bernardy, Matt Martz

Install this file to wp-content/object-cache.php
*/
use Dinosaur\Cache\ObjectCache;

// This is garbage. Also, necessary.
if ( ! DINOSAUR_UNIT_TESTS ):

if ( ! class_exists( 'Memcache' ) ) {
	throw new \ErrorException( 'Memcache is required to scale WordPress.' );
}

function _wp_object_cache() {
	return ObjectCache::instance();
}

function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	return _wp_object_cache()->add( $key, $data, $group, $expire );
}

function wp_cache_incr( $key, $n = 1, $group = '' ) {
	return _wp_object_cache()->incr( $key, $n, $group );
}

function wp_cache_decr( $key, $n = 1, $group = '' ) {
	return _wp_object_cache()->decr( $key, $n, $group );
}

function wp_cache_close() {
	return _wp_object_cache()->close();
}

function wp_cache_delete( $key, $group = '' ) {
	return _wp_object_cache()->delete( $key, $group );
}

function wp_cache_flush() {
	return _wp_object_cache()->flush();
}

function wp_cache_get( $key, $group = '', $force = false ) {
	return _wp_object_cache()->get( $key, $group, $force );
}

function wp_cache_init() {
	global $wp_object_cache;
	$wp_object_cache = _wp_object_cache();
}

function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	return _wp_object_cache()->replace( $key, $data, $group, $expire );
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	if ( ! defined( 'WP_INSTALLING' ) ) {
		return _wp_object_cache()->set( $key, $data, $group, $expire );
	}
	return _wp_object_cache()->delete( $key, $group );
}

function wp_cache_switch_to_blog( $blog_id ) {
	return _wp_object_cache()->switch_to_blog( $blog_id );
}

function wp_cache_add_global_groups( $groups ) {
	_wp_object_cache()->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {
	_wp_object_cache()->add_non_persistent_groups( $groups );
}

endif;
