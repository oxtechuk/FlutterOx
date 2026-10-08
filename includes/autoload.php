<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple PSR-4 like autoloader for includes folder
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'Doken_Ox_';
    if ( 0 !== strpos( $class, $prefix ) ) {
        return;
    }

    $relative_class = substr( $class, strlen( $prefix ) );
    $file = DOKEN_OX_PRO_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );
