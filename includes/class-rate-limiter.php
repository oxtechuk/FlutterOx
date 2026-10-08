<?php
/**
 * Advanced Rate Limiter
 *
 * Per-route configurable rate limiting using WordPress transients.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Rate_Limiter {

    /**
     * Default rate limit rules.
     * Each entry: [ 'requests' => int, 'window' => int (seconds) ]
     */
    const LIMITS = [
        'auth/login'           => [ 'requests' => 5,   'window' => 60  ],
        'auth/register'        => [ 'requests' => 3,   'window' => 60  ],
        'auth/send-otp'        => [ 'requests' => 3,   'window' => 300 ],
        'auth/verify-otp'      => [ 'requests' => 5,   'window' => 300 ],
        'auth/forgot-password' => [ 'requests' => 3,   'window' => 300 ],
        'products'             => [ 'requests' => 300, 'window' => 60  ],
        'search'               => [ 'requests' => 60,  'window' => 60  ],
        'cart'                 => [ 'requests' => 60,  'window' => 60  ],
        'orders'               => [ 'requests' => 30,  'window' => 60  ],
        'home'                 => [ 'requests' => 200, 'window' => 60  ],
        'default'              => [ 'requests' => 100, 'window' => 60  ],
    ];

    /**
     * Check rate limit for a given route and identifier (IP or user ID).
     *
     * @param string $route       Route slug, e.g. 'auth/login'
     * @param string $identifier  Client IP or user ID
     * @return true|WP_Error
     */
    public static function check( $route, $identifier = '' ) {
        // Allow admins to bypass rate limiting
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        if ( empty( $identifier ) ) {
            $identifier = self::get_client_ip();
        }

        $rule    = self::get_rule( $route );
        $limit   = (int) $rule['requests'];
        $window  = (int) $rule['window'];
        $key     = 'dox_rl_' . md5( $route . '|' . $identifier );

        $data = get_transient( $key );

        if ( false === $data ) {
            // First request in this window
            set_transient( $key, [ 'count' => 1, 'reset' => time() + $window ], $window );
            return true;
        }

        $data['count']++;
        set_transient( $key, $data, max( 0, $data['reset'] - time() ) );

        if ( $data['count'] > $limit ) {
            $retry_after = max( 0, $data['reset'] - time() );
            return new WP_Error(
                'dox_rate_limited',
                sprintf(
                    /* translators: 1: limit, 2: window seconds */
                    __( 'Rate limit exceeded. Max %1$d requests per %2$d seconds.', 'doken-ox-pro' ),
                    $limit,
                    $window
                ),
                [
                    'status'       => 429,
                    'retry_after'  => $retry_after,
                ]
            );
        }

        return true;
    }

    /**
     * Get the rule for a route (falls back to 'default').
     *
     * Supports partial prefix matching, e.g. 'auth/login' matches route 'auth/login'.
     *
     * @param string $route
     * @return array
     */
    public static function get_rule( $route ) {
        // Get custom rules from settings
        $settings     = (array) get_option( 'doken_ox_pro_settings', [] );
        $custom_rules = $settings['rate_limits'] ?? [];

        $all_rules = array_merge( self::LIMITS, $custom_rules );

        // Exact match first
        if ( isset( $all_rules[ $route ] ) ) {
            return $all_rules[ $route ];
        }

        // Prefix match
        foreach ( $all_rules as $pattern => $rule ) {
            if ( $pattern !== 'default' && strpos( $route, $pattern ) === 0 ) {
                return $rule;
            }
        }

        return $all_rules['default'];
    }

    /**
     * Get client IP address (respects proxies).
     *
     * @return string
     */
    private static function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( $_SERVER[ $header ] );
                // X-Forwarded-For can be a comma-separated list
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }
}
