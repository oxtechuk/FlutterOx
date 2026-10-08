<?php
/**
 * Validation helper utilities.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'dox_require_params' ) ) {
    /**
     * Ensure required parameters exist in payload.
     *
     * @param array $payload  Request body/params.
     * @param array $required Keys to validate.
     *
     * @return true|WP_Error
     */
    function dox_require_params( array $payload, array $required ) {
        $missing = array();

        foreach ( $required as $key ) {
            if ( ! isset( $payload[ $key ] ) || '' === $payload[ $key ] ) {
                $missing[] = $key;
            }
        }

        if ( ! empty( $missing ) ) {
            return new WP_Error(
                'dox_missing_params',
                sprintf(
                    /* translators: %s: comma separated parameter keys */
                    __( 'Missing required parameters: %s', 'doken-ox-pro' ),
                    implode( ', ', $missing )
                ),
                array( 'status' => 400 )
            );
        }

        return true;
    }
}

if ( ! function_exists( 'dox_bool' ) ) {
    /**
     * Safely convert truthy values to boolean.
     *
     * @param mixed $value Raw value.
     *
     * @return bool
     */
    function dox_bool( $value ) {
        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_numeric( $value ) ) {
            return (bool) intval( $value );
        }

        $value = strtolower( trim( (string) $value ) );

        return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
    }
}

if ( ! function_exists( 'dox_clean_array' ) ) {
    /**
     * Sanitize an associative array using field map.
     *
     * @param array $payload Raw payload.
     * @param array $map     Map of field => callback.
     *
     * @return array
     */
    function dox_clean_array( array $payload, array $map ) {
        $clean = array();

        foreach ( $map as $field => $callback ) {
            if ( ! array_key_exists( $field, $payload ) ) {
                continue;
            }

            $value = $payload[ $field ];

            if ( is_callable( $callback ) ) {
                $clean[ $field ] = call_user_func( $callback, $value );
            } else {
                $clean[ $field ] = sanitize_text_field( wp_unslash( $value ) );
            }
        }

        return $clean;
    }
}

