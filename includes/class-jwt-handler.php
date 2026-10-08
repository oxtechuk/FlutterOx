<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Doken_Ox_JWT_Handler
 * Lightweight JWT generator/validator (HS256) without external libs.
 */
class Doken_Ox_JWT_Handler {

    // default expiry seconds (from installer defaults)
    public static function get_settings() {
        $opts = get_option( 'doken_ox_pro_settings', array() );
        return wp_parse_args( $opts, array(
            'jwt_expiry'     => 24 * 60 * 60,
            'refresh_expiry' => 7 * 24 * 60 * 60,
        ) );
    }

    public static function secret() {
        // Prefer JWT_AUTH_SECRET_KEY if defined (compatibility with other JWT plugins)
        if ( defined( 'JWT_AUTH_SECRET_KEY' ) && JWT_AUTH_SECRET_KEY ) {
            return JWT_AUTH_SECRET_KEY;
        }

        // use a saved secret or fall back to AUTH_KEY constant
        $s = get_option( 'doken_ox_pro_jwt_secret' );
        if ( ! $s ) {
            if ( defined( 'AUTH_KEY' ) && AUTH_KEY ) {
                $s = AUTH_KEY;
            } else {
                // fallback generate and store (first run)
                $s = wp_generate_password( 64, true, true );
            }
            update_option( 'doken_ox_pro_jwt_secret', $s );
        }
        return $s;
    }

    private static function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    private static function base64url_decode( $data ) {
        $remainder = strlen( $data ) % 4;
        if ( $remainder ) {
            $padlen = 4 - $remainder;
            $data .= str_repeat( '=', $padlen );
        }
        return base64_decode( strtr( $data, '-_', '+/' ) );
    }

    public static function generate_token( $user_id, $role = 'customer' ) {
        $settings = self::get_settings();
        $now = time();
        $exp = $now + intval( $settings['jwt_expiry'] );

        $payload = array(
            'iss' => get_bloginfo( 'url' ),
            'iat' => $now,
            'exp' => $exp,
            'sub' => intval( $user_id ),
            'role'=> $role,
            'data' => array(
                'user' => array(
                    'id' => intval( $user_id ),
                ),
            ),
        );

        $header = array( 'alg' => 'HS256', 'typ' => 'JWT' );

        $segments = array();
        $segments[] = self::base64url_encode( wp_json_encode( $header ) );
        $segments[] = self::base64url_encode( wp_json_encode( $payload ) );

        $signing_input = implode( '.', $segments );
        $signature = hash_hmac( 'sha256', $signing_input, self::secret(), true );
        $segments[] = self::base64url_encode( $signature );

        $jwt = implode( '.', $segments );

        // create refresh token and store in DB
        $refresh = wp_generate_password( 64, true, true );
        self::store_refresh_token( $user_id, $jwt, $refresh, $exp + intval( self::get_settings()['refresh_expiry'] ) );

        return array(
            'token'         => $jwt,
            'refresh_token' => $refresh,
            'expires_in'    => $exp,
            'token_type'    => 'Bearer',
        );
    }

    private static function store_refresh_token( $user_id, $jwt, $refresh_token, $expires_at ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dox_tokens';
        // delete previous tokens for this refresh to avoid duplicates (optional)
        $wpdb->insert( $table, array(
            'user_id' => $user_id,
            'jwt_token' => $jwt,
            'refresh_token' => $refresh_token,
            'expires_at' => gmdate( 'Y-m-d H:i:s', $expires_at ),
            'created_at' => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s', '%s' ) );
    }

    public static function validate_token( $jwt ) {
        if ( empty( $jwt ) ) {
            return new WP_Error( 'dox_jwt_missing', 'Missing token', array( 'status' => 401 ) );
        }

        $parts = explode( '.', $jwt );
        if ( count( $parts ) !== 3 ) {
            return new WP_Error( 'dox_jwt_invalid', 'Invalid token format', array( 'status' => 401 ) );
        }

        list( $bh, $bp, $bs ) = $parts;
        $header = json_decode( self::base64url_decode( $bh ), true );
        $payload = json_decode( self::base64url_decode( $bp ), true );
        $signature = self::base64url_decode( $bs );

        if ( ! is_array( $payload ) ) {
            return new WP_Error( 'dox_jwt_invalid_payload', 'Invalid payload', array( 'status' => 401 ) );
        }

        $signing_input = $bh . '.' . $bp;
        $expected = hash_hmac( 'sha256', $signing_input, self::secret(), true );

        if ( ! hash_equals( $expected, $signature ) ) {
            return new WP_Error( 'dox_jwt_signature', 'Invalid signature', array( 'status' => 401 ) );
        }

        if ( isset( $payload['exp'] ) && time() > intval( $payload['exp'] ) ) {
            return new WP_Error( 'dox_jwt_expired', 'Token expired', array( 'status' => 401 ) );
        }

        // return payload on success
        return $payload;
    }

    public static function refresh_access_token( $refresh_token ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dox_tokens';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE refresh_token = %s LIMIT 1", $refresh_token ) );

        if ( ! $row ) {
            return new WP_Error( 'dox_refresh_invalid', 'Invalid refresh token', array( 'status' => 401 ) );
        }

        // check expiry
        if ( isset( $row->expires_at ) && strtotime( $row->expires_at ) < time() ) {
            return new WP_Error( 'dox_refresh_expired', 'Refresh token expired', array( 'status' => 401 ) );
        }

        $user_id = intval( $row->user_id );
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new WP_Error( 'dox_user_not_found', 'User not found', array( 'status' => 404 ) );
        }

        // create new token
        $role = 'customer';
        $roles = $user->roles;
        if ( ! empty( $roles ) ) {
            $role = $roles[0];
        }

        return self::generate_token( $user_id, $role );
    }

    public static function revoke_tokens_for_user( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dox_tokens';
        $wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
    }

    /**
     * Invalidate a specific JWT token.
     *
     * @param string $jwt Token string.
     *
     * @return void
     */
    public static function invalidate_token( $jwt ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dox_tokens';
        $wpdb->delete( $table, array( 'jwt_token' => $jwt ), array( '%s' ) );
    }

    /**
     * Extract bearer token from server headers.
     *
     * @return string|false
     */
    public static function get_token_from_headers() {
        $auth_header = null;

        if ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
            if ( $headers ) {
                foreach ( $headers as $k => $v ) {
                    if ( strtolower( $k ) === 'authorization' ) {
                        $auth_header = $v;
                        break;
                    }
                }
            }
        }

        if ( ! $auth_header ) {
            if ( isset( $_SERVER['Authorization'] ) ) {
                $auth_header = $_SERVER['Authorization'];
            } elseif ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
                $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
                $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
        }

        if ( empty( $auth_header ) ) {
            return false;
        }

        if ( preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
            return trim( $matches[1] );
        }

        return false;
    }

    /**
     * Resolve user id from Authorization header.
     *
     * @return int|WP_Error
     */
    public static function get_user_id_from_request() {
        $token = self::get_token_from_headers();

        if ( ! $token ) {
            return new WP_Error( 'dox_auth_missing', __( 'Authorization header missing.', 'doken-ox-pro' ), array( 'status' => 401 ) );
        }

        $payload = self::validate_token( $token );

        if ( is_wp_error( $payload ) ) {
            return $payload;
        }

        return isset( $payload['sub'] ) ? (int) $payload['sub'] : 0;
    }
}
