<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Optionally check capability
// if ( ! current_user_can( 'activate_plugins' ) ) { return; }

$prefix = $wpdb->prefix;
$tables = array(
    $prefix . 'dox_addresses',
    $prefix . 'dox_notifications',
    $prefix . 'dox_wishlist',
    $prefix . 'dox_logs',
    $prefix . 'dox_tokens',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Remove options
delete_option( 'doken_ox_pro_db_version' );
delete_option( 'doken_ox_pro_settings' );
