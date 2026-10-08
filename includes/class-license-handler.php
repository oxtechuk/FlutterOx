<?php
/**
 * License Handler
 *
 * Manages plugin license activation, verification, and status.
 * Communicates with the Ox Tech License API.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_License_Handler {

    /**
     * License server endpoint.
     * Replace with your real license API URL.
     */
    const API_URL = 'https://license.oxtech.uk/api/v1';

    /**
     * WordPress option keys.
     */
    const OPT_KEY    = 'dox_license_key';
    const OPT_STATUS = 'dox_license_status';
    const OPT_DATA   = 'dox_license_data';
    const OPT_LAST   = 'dox_license_last_check';

    /**
     * Status constants.
     */
    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_EXPIRED  = 'expired';
    const STATUS_INVALID  = 'invalid';

    // ------------------------------------------------------------------
    //  Activation / Deactivation
    // ------------------------------------------------------------------

    /**
     * Activate a license key for this site.
     *
     * @param string $key
     * @return array{success:bool, message:string, data?:array}
     */
    public static function activate( $key ) {
        $key = sanitize_text_field( trim( $key ) );

        if ( empty( $key ) ) {
            return [ 'success' => false, 'message' => __( 'License key cannot be empty.', 'doken-ox-pro' ) ];
        }

        $response = self::remote_request( 'activate', [
            'license_key' => $key,
            'site_url'    => home_url(),
            'plugin'      => 'doken-ox-pro',
            'version'     => DOKEN_OX_PRO_VERSION,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }

        if ( ! empty( $response['success'] ) ) {
            update_option( self::OPT_KEY,    $key );
            update_option( self::OPT_STATUS, self::STATUS_ACTIVE );
            update_option( self::OPT_DATA,   $response['data'] ?? [] );
            update_option( self::OPT_LAST,   time() );
            return [ 'success' => true, 'message' => __( 'License activated successfully.', 'doken-ox-pro' ), 'data' => $response['data'] ?? [] ];
        }

        return [ 'success' => false, 'message' => $response['message'] ?? __( 'Activation failed.', 'doken-ox-pro' ) ];
    }

    /**
     * Deactivate the current license for this site.
     *
     * @return array{success:bool, message:string}
     */
    public static function deactivate() {
        $key = self::get_key();

        if ( ! empty( $key ) ) {
            self::remote_request( 'deactivate', [
                'license_key' => $key,
                'site_url'    => home_url(),
            ] );
        }

        update_option( self::OPT_STATUS, self::STATUS_INACTIVE );
        delete_option( self::OPT_DATA );

        return [ 'success' => true, 'message' => __( 'License deactivated.', 'doken-ox-pro' ) ];
    }

    // ------------------------------------------------------------------
    //  Status Check
    // ------------------------------------------------------------------

    /**
     * Verify the stored license against the remote server.
     * Called on a daily schedule.
     *
     * @return string  One of the STATUS_* constants.
     */
    public static function check_status() {
        $key = self::get_key();

        if ( empty( $key ) ) {
            update_option( self::OPT_STATUS, self::STATUS_INACTIVE );
            return self::STATUS_INACTIVE;
        }

        $response = self::remote_request( 'check', [
            'license_key' => $key,
            'site_url'    => home_url(),
        ] );

        if ( is_wp_error( $response ) ) {
            // Network error — keep last known status, just update timestamp
            update_option( self::OPT_LAST, time() );
            return self::get_status();
        }

        $status = $response['status'] ?? self::STATUS_INVALID;
        update_option( self::OPT_STATUS, $status );
        update_option( self::OPT_DATA,   $response['data'] ?? [] );
        update_option( self::OPT_LAST,   time() );

        return $status;
    }

    /**
     * Schedule a daily background check (called on activation).
     */
    public static function schedule_checks() {
        if ( ! wp_next_scheduled( 'dox_license_daily_check' ) ) {
            wp_schedule_event( time(), 'daily', 'dox_license_daily_check' );
        }
        add_action( 'dox_license_daily_check', [ __CLASS__, 'check_status' ] );
    }

    /**
     * Clear the scheduled check (called on deactivation).
     */
    public static function clear_schedule() {
        wp_clear_scheduled_hook( 'dox_license_daily_check' );
    }

    // ------------------------------------------------------------------
    //  Helpers / Getters
    // ------------------------------------------------------------------

    /**
     * Is the license currently valid & active?
     *
     * @return bool
     */
    public static function is_valid() {
        // In development / localhost — always allow
        if ( self::is_local_environment() ) {
            return true;
        }

        $status = self::get_status();
        return $status === self::STATUS_ACTIVE;
    }

    /**
     * Get the stored license key (masked for display).
     *
     * @param bool $mask Whether to mask the key for display.
     * @return string
     */
    public static function get_key( $mask = false ) {
        $key = get_option( self::OPT_KEY, '' );
        if ( $mask && strlen( $key ) > 8 ) {
            return substr( $key, 0, 4 ) . str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -4 );
        }
        return $key;
    }

    /**
     * Get the stored status string.
     *
     * @return string
     */
    public static function get_status() {
        return get_option( self::OPT_STATUS, self::STATUS_INACTIVE );
    }

    /**
     * Get all stored license data.
     *
     * @return array
     */
    public static function get_data() {
        return (array) get_option( self::OPT_DATA, [] );
    }

    /**
     * Get a comprehensive status summary for the admin UI.
     *
     * @return array
     */
    public static function get_status_summary() {
        $data   = self::get_data();
        $status = self::get_status();
        $last   = (int) get_option( self::OPT_LAST, 0 );

        return [
            'key'         => self::get_key( true ),
            'status'      => $status,
            'is_valid'    => self::is_valid(),
            'plan'        => $data['plan']       ?? __( 'Unknown', 'doken-ox-pro' ),
            'expires_at'  => $data['expires_at'] ?? null,
            'activations' => $data['activations'] ?? null,
            'last_check'  => $last ? human_time_diff( $last ) . ' ' . __( 'ago', 'doken-ox-pro' ) : __( 'Never', 'doken-ox-pro' ),
            'is_local'    => self::is_local_environment(),
        ];
    }

    /**
     * Get a CSS color class for the status badge.
     *
     * @return string
     */
    public static function get_status_color() {
        $map = [
            self::STATUS_ACTIVE   => 'success',
            self::STATUS_INACTIVE => 'warning',
            self::STATUS_EXPIRED  => 'danger',
            self::STATUS_INVALID  => 'danger',
        ];
        return $map[ self::get_status() ] ?? 'secondary';
    }

    // ------------------------------------------------------------------
    //  Internal
    // ------------------------------------------------------------------

    /**
     * Make a request to the license API.
     *
     * @param string $action   'activate' | 'deactivate' | 'check'
     * @param array  $body
     * @return array|WP_Error
     */
    private static function remote_request( $action, $body ) {
        $url = trailingslashit( self::API_URL ) . $action;

        $response = wp_remote_post( $url, [
            'timeout' => 4,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $json = json_decode( $raw, true );

        if ( ! is_array( $json ) ) {
            return new WP_Error( 'dox_license_parse_error', __( 'Invalid response from license server.', 'doken-ox-pro' ) );
        }

        return $json;
    }

    /**
     * Detect localhost / development environment.
     * License validation is bypassed on these hosts.
     *
     * @return bool
     */
    private static function is_local_environment() {
        $host = parse_url( home_url(), PHP_URL_HOST );
        if ( empty( $host ) ) {
            return true;
        }
        $locals = [ 'localhost', '127.0.0.1', '::1', '.local', '.test', '.dev' ];

        foreach ( $locals as $local ) {
            if ( $host === $local || substr( $host, -strlen( $local ) ) === $local || strpos( $host, 'localhost' ) !== false ) {
                return true;
            }
        }
        if ( strpos( $host, '192.168.' ) === 0 || strpos( $host, '10.' ) === 0 || strpos( $host, '172.' ) === 0 ) {
            return true;
        }
        return false;
    }
}
