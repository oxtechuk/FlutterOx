<?php
/**
 * Simple cache wrapper built on WordPress transients.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Cache_Handler {

    /**
     * Prefix cache keys.
     *
     * @param string $key Raw key.
     *
     * @return string
     */
    protected static function build_key( $key ) {
        return 'dox_cache_' . sanitize_key( $key );
    }

    /**
     * Retrieve cached value.
     *
     * @param string $key     Cache key.
     * @param mixed  $default Default if not set.
     *
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $value = get_transient( self::build_key( $key ) );
        return false === $value ? $default : $value;
    }

    /**
     * Store cached value.
     *
     * @param string $key        Cache key.
     * @param mixed  $value      Value to store.
     * @param int    $expiration Expiration in seconds.
     *
     * @return void
     */
    public static function set( $key, $value, $expiration = 300 ) {
        set_transient( self::build_key( $key ), $value, (int) $expiration );
    }

    /**
     * Delete cached value.
     *
     * @param string $key Cache key.
     *
     * @return void
     */
    public static function delete( $key ) {
        delete_transient( self::build_key( $key ) );
    }

    /**
     * Remember helper - cache result of callback.
     *
     * @param string   $key        Cache key.
     * @param int      $expiration Expiration in seconds.
     * @param callable $callback   Callback to resolve value.
     *
     * @return mixed
     */
    public static function remember( $key, $expiration, callable $callback ) {
        $cached = self::get( $key, null );
        if ( null !== $cached ) {
            return $cached;
        }

        $value = call_user_func( $callback );
        self::set( $key, $value, $expiration );

        return $value;
    }
}
