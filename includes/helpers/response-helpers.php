<?php
/**
 * Response helper utilities to normalize REST payloads.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'dox_response' ) ) {
    /**
     * Build a standardized REST response body.
     *
     * @param bool       $success    Operation flag.
     * @param string     $message    Human readable message.
     * @param array|object $data     Payload body.
     * @param array       $pagination Pagination metadata.
     * @param int         $status    HTTP status code.
     *
     * @return WP_REST_Response
     */
    function dox_response( $success, $message = '', $data = array(), $pagination = array(), $status = 200 ) {
        $payload = array(
            'success'    => (bool) $success,
            'message'    => $message,
            'data'       => empty( $data ) ? new stdClass() : $data,
            'pagination' => empty( $pagination ) ? new stdClass() : $pagination,
        );

        $response = rest_ensure_response( $payload );
        $response->set_status( $status );

        return $response;
    }
}

if ( ! function_exists( 'dox_error_response' ) ) {
    /**
     * Shortcut for returning an error payload.
     *
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     * @param array  $data    Additional payload data.
     *
     * @return WP_REST_Response
     */
    function dox_error_response( $message, $status = 400, $data = array() ) {
        return dox_response( false, $message, $data, array(), $status );
    }
}

if ( ! function_exists( 'dox_build_pagination' ) ) {
    /**
     * Helper to build pagination arrays.
     *
     * @param int $current Current page.
     * @param int $per_page Items per page.
     * @param int $total Total items.
     *
     * @return array
     */
    function dox_build_pagination( $current, $per_page, $total ) {
        $current   = max( 1, (int) $current );
        $per_page  = max( 1, (int) $per_page );
        $total     = max( 0, (int) $total );
        $total_pages = $per_page ? (int) ceil( $total / $per_page ) : 1;

        return array(
            'current_page' => $current,
            'per_page'     => $per_page,
            'total'        => $total,
            'total_pages'  => max( 1, $total_pages ),
        );
    }
}

if ( ! function_exists( 'dox_admin_current_user' ) ) {
    /**
     * Helper to get current admin user or return error.
     */
    function dox_admin_current_user() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'dox_unauthorized', __( 'Unauthorized.', 'doken-ox-pro' ), array( 'status' => 401 ) );
        }
        if ( ! user_can( $user_id, 'manage_options' ) && ! user_can( $user_id, 'manage_woocommerce' ) ) {
            return new WP_Error( 'dox_forbidden', __( 'Forbidden.', 'doken-ox-pro' ), array( 'status' => 403 ) );
        }
        return get_user_by( 'id', $user_id );
    }
}
