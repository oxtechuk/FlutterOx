<?php
/**
 * Manage in-app notifications stored in custom table.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Notification_Handler {

    /**
     * Table name helper.
     *
     * @global wpdb $wpdb
     *
     * @return string
     */
    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dox_notifications';
    }

    /**
     * Store notification row.
     *
     * @param int    $user_id Recipient user ID.
     * @param string $title   Notification title.
     * @param string $message Notification body.
     * @param array  $meta    Optional meta.
     *
     * @return void
     */
    public static function create( $user_id, $title, $message, $meta = array() ) {
        global $wpdb;

        $wpdb->insert(
            self::table(),
            array(
                'user_id' => (int) $user_id,
                'title'   => sanitize_text_field( $title ),
                'message' => wp_kses_post( $message ),
                'meta'    => maybe_serialize( $meta ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Fetch notifications for a user.
     *
     * @param int $user_id User ID.
     * @param int $page    Page number.
     * @param int $per_page Items per page.
     *
     * @return array
     */
    public static function get_for_user( $user_id, $page = 1, $per_page = 20 ) {
        global $wpdb;

        $page     = max( 1, (int) $page );
        $per_page = max( 1, (int) $per_page );
        $offset   = ( $page - 1 ) * $per_page;

        $table = self::table();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $user_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        $total = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(id) FROM {$table} WHERE user_id = %d", $user_id )
        );

        return array(
            'items'      => array_map(
                function ( $row ) {
                    $row['meta'] = maybe_unserialize( $row['meta'] );
                    $row['is_read'] = (bool) $row['is_read'];
                    return $row;
                },
                $rows
            ),
            'pagination' => dox_build_pagination( $page, $per_page, $total ),
        );
    }

    /**
     * Mark notification as read.
     *
     * @param int $user_id User id.
     * @param int $notification_id Row id.
     *
     * @return void
     */
    public static function mark_read( $user_id, $notification_id ) {
        global $wpdb;

        $wpdb->update(
            self::table(),
            array( 'is_read' => 1 ),
            array(
                'id'      => (int) $notification_id,
                'user_id' => (int) $user_id,
            ),
            array( '%d' ),
            array( '%d', '%d' )
        );
    }
}
