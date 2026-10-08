<?php
if (!defined('ABSPATH')) {
    exit;
}

class Doken_Ox_API_Router
{

    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'), 15);
        // add CORS headers for our namespace
        add_filter('rest_post_dispatch', array(__CLASS__, 'add_cors_headers'), 10, 2);
    }

    public static function add_cors_headers($response, $server)
    {
        // only for our namespace
        $route = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
        if (strpos($route, '/mvapp/v1/') !== false) {
            if (is_wp_error($response)) {
                $response = rest_ensure_response($response);
            }
            $response->header('Access-Control-Allow-Origin', '*');
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-WP-Nonce');
        }
        return $response;
    }

    public static function register_routes()
    {
        $namespace = 'mvapp/v1';

        // AUTH
        register_rest_route(
            $namespace,
            '/auth/login',
            array(
                'methods' => 'POST',
                'callback' => array('Doken_Ox_Auth', 'login'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $namespace,
            '/auth/register',
            array(
                'methods' => 'POST',
                'callback' => array('Doken_Ox_Auth', 'register'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $namespace,
            '/auth/refresh',
            array(
                'methods' => 'POST',
                'callback' => array('Doken_Ox_Auth', 'refresh'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $namespace,
            '/auth/me',
            array(
                'methods' => 'GET',
                'callback' => array('Doken_Ox_Auth', 'me'),
                'permission_callback' => array(__CLASS__, 'permission_authenticated'),
            )
        );

        register_rest_route(
            $namespace,
            '/auth/profile',
            array(
                'methods' => 'PUT',
                'callback' => array('Doken_Ox_Auth', 'update_profile'),
                'permission_callback' => array(__CLASS__, 'permission_authenticated'),
            )
        );

        register_rest_route(
            $namespace,
            '/auth/logout',
            array(
                'methods' => 'POST',
                'callback' => array('Doken_Ox_Auth', 'logout'),
                'permission_callback' => array(__CLASS__, 'permission_authenticated'),
            )
        );

        register_rest_route(
            $namespace,
            '/auth/forgot-password',
            array(
                'methods' => 'POST',
                'callback' => array('Doken_Ox_Auth', 'forgot_password'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $namespace,
            '/auth/reset-password',
            array(
                'methods' => 'POST',
                'callback' => array('Doken_Ox_Auth', 'reset_password'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $namespace,
            '/auth/send-otp',
            array(
                'methods' => 'POST',
                'callback' => array('Doken_Ox_Auth', 'send_otp'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $namespace,
            '/auth/verify-otp',
            array(
                'methods' => 'POST',
                'callback' => array('Doken_Ox_Auth', 'verify_otp'),
                'permission_callback' => '__return_true',
            )
        );

        /**
         * Allow feature modules (customer, vendor, admin, etc.) to register their routes.
         * Uses the action hook pattern so modules self-register.
         */
        do_action('doken_ox_register_routes', $namespace);

        // Vendor routes — only registered when Dokan mode is active
        if ( class_exists('Doken_Ox_Plugin_Mode') && Doken_Ox_Plugin_Mode::should_load_vendor_routes() ) {
            do_action('doken_ox_register_vendor_routes', $namespace);
        }
    }

    /**
     * Permission callback to ensure token is present and valid.
     */
    public static function permission_authenticated($request)
    {
        // Validate JWT from Authorization header
        $auth = self::get_bearer_token();
        if ($auth) {
            $payload = Doken_Ox_JWT_Handler::validate_token($auth);
            if (is_wp_error($payload)) {
                return $payload;
            }
            $user = get_user_by('id', intval($payload['sub']));
            if (!$user) {
                return new WP_Error('dox_user_not_found', 'User not found', array('status' => 404));
            }
            wp_set_current_user($user->ID);
        } else {
            // fallback to WP nonce/cookie authentication for dashboard usage
            $nonce = $request instanceof WP_REST_Request ? $request->get_header('X-WP-Nonce') : '';
            if (!($nonce && wp_verify_nonce($nonce, 'wp_rest') && is_user_logged_in())) {
                return new WP_Error('dox_auth_missing', 'Authorization header not present', array('status' => 401));
            }
            wp_set_current_user(get_current_user_id());
        }

        $rl = self::rate_limit_check();
        if (is_wp_error($rl)) {
            return $rl;
        }
        return true;
    }

    public static function get_bearer_token()
    {
        return Doken_Ox_JWT_Handler::get_token_from_headers();
    }

    /**
     * Simple rate limiting by IP using transients.
     */
    /**
     * Advanced rate limiting — delegates to Doken_Ox_Rate_Limiter.
     */
    public static function rate_limit_check( $route = 'default' ) {
        if ( class_exists('Doken_Ox_Rate_Limiter') ) {
            return Doken_Ox_Rate_Limiter::check( $route );
        }

        // Fallback basic limiter
        $ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $key   = 'dox_rl_' . md5($ip);
        $limit = intval( get_option('doken_ox_pro_settings', ['rate_limit' => 100])['rate_limit'] ?? 100 );

        $count = get_transient($key);
        if (false === $count) {
            set_transient($key, 1, 60);
        } else {
            $count = intval($count) + 1;
            set_transient($key, $count, 60);
            if ($count > $limit) {
                return new WP_Error('dox_rate_limited', 'Rate limit exceeded', ['status' => 429]);
            }
        }
        return true;
    }
}
