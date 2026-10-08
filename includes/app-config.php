<?php
/**
 * App Config — manages all Flutter app appearance & feature settings.
 *
 * Stores every configurable value (colors, typography, images, features)
 * in the {prefix}dox_app_config table and exposes them via REST API.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_App_Config {

    /**
     * WordPress option key for the merged config blob.
     * We also keep a fast wp_options copy for single-request reads.
     */
    const OPTION_KEY   = 'dox_app_config';
    const CACHE_KEY    = 'dox_app_config_v1';
    const CACHE_GROUP  = 'doken_ox';
    const CACHE_TTL    = 3600; // 1 hour

    // ------------------------------------------------------------------
    //  Read
    // ------------------------------------------------------------------

    /**
     * Get the full merged app config (defaults ← stored values ← computed).
     *
     * @return array
     */
    public static function get_full_config() {
        // Try object cache first
        $cached = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $stored  = (array) get_option( self::OPTION_KEY, [] );
        $default = self::default_config();

        // Deep merge: stored values override defaults
        $config = self::deep_merge( $default, $stored );

        // Resolve media IDs to URLs
        $config = self::resolve_media_urls( $config );

        // Add read-only computed fields
        $config['_meta'] = [
            'plugin_version' => DOKEN_OX_PRO_VERSION,
            'mode'           => Doken_Ox_Plugin_Mode::get_mode(),
            'capabilities'   => Doken_Ox_Plugin_Mode::get_api_capabilities(),
            'rest_url'       => esc_url_raw( rest_url( 'mvapp/v1/' ) ),
            'site_url'       => home_url(),
            'currency'       => get_woocommerce_currency(),
            'currency_symbol'=> get_woocommerce_currency_symbol(),
        ];

        wp_cache_set( self::CACHE_KEY, $config, self::CACHE_GROUP, self::CACHE_TTL );

        return $config;
    }

    /**
     * Get a single config key using dot-notation.
     * Example: 'colors.primary'
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $config = self::get_full_config();
        $parts  = explode( '.', $key );
        $cursor = $config;

        foreach ( $parts as $part ) {
            if ( ! is_array( $cursor ) || ! array_key_exists( $part, $cursor ) ) {
                return $default;
            }
            $cursor = $cursor[ $part ];
        }

        return $cursor;
    }

    // ------------------------------------------------------------------
    //  Write
    // ------------------------------------------------------------------

    /**
     * Save a partial config update (deep-merged with current stored config).
     *
     * @param array $data  Associative array of settings (can be nested).
     * @return array  The full config after update.
     */
    public static function update( $data ) {
        $current = (array) get_option( self::OPTION_KEY, [] );
        $updated = self::deep_merge( $current, $data );

        // Remove computed _meta before storing
        unset( $updated['_meta'] );

        update_option( self::OPTION_KEY, $updated );
        self::bust_cache();

        return self::get_full_config();
    }

    /**
     * Reset everything to factory defaults.
     *
     * @return array
     */
    public static function reset() {
        delete_option( self::OPTION_KEY );
        self::bust_cache();
        return self::get_full_config();
    }

    /**
     * Clear the object cache so the next read is fresh.
     */
    public static function bust_cache() {
        wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
    }

    // ------------------------------------------------------------------
    //  REST Route Callbacks
    // ------------------------------------------------------------------

    /**
     * GET /mvapp/v1/app/config
     */
    public static function rest_get_config( $request ) {
        $config = self::get_full_config();
        return rest_ensure_response( [
            'success' => true,
            'data'    => $config,
        ] );
    }

    /**
     * GET /mvapp/v1/app/config/colors
     */
    public static function rest_get_colors( $request ) {
        return rest_ensure_response( [
            'success' => true,
            'data'    => self::get( 'colors', [] ),
        ] );
    }

    /**
     * GET /mvapp/v1/app/config/theme
     */
    public static function rest_get_theme( $request ) {
        $config = self::get_full_config();
        return rest_ensure_response( [
            'success' => true,
            'data'    => [
                'colors'     => $config['colors']     ?? [],
                'typography' => $config['typography'] ?? [],
                'layout'     => $config['layout']     ?? [],
                'app_name'   => $config['app_name']   ?? '',
                'app_logo'   => $config['app_logo_url'] ?? '',
                'splash'     => $config['splash_image_url'] ?? '',
            ],
        ] );
    }

    /**
     * GET /mvapp/v1/app/config/features
     */
    public static function rest_get_features( $request ) {
        return rest_ensure_response( [
            'success' => true,
            'data'    => self::get( 'features', [] ),
        ] );
    }

    /**
     * POST /mvapp/v1/app/config  (Admin only — updates settings)
     */
    public static function rest_update_config( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'dox_forbidden', __( 'Insufficient permissions.', 'doken-ox-pro' ), [ 'status' => 403 ] );
        }

        $body = $request->get_json_params();
        if ( ! is_array( $body ) || empty( $body ) ) {
            return new WP_Error( 'dox_bad_request', __( 'Invalid request body.', 'doken-ox-pro' ), [ 'status' => 400 ] );
        }

        // Sanitize colors
        if ( isset( $body['colors'] ) && is_array( $body['colors'] ) ) {
            foreach ( $body['colors'] as $k => $v ) {
                $body['colors'][ $k ] = sanitize_hex_color( $v ) ?: sanitize_text_field( $v );
            }
        }

        // Sanitize strings
        if ( isset( $body['app_name'] ) ) {
            $body['app_name'] = sanitize_text_field( $body['app_name'] );
        }

        $updated = self::update( $body );

        return rest_ensure_response( [
            'success' => true,
            'message' => __( 'App config updated successfully.', 'doken-ox-pro' ),
            'data'    => $updated,
        ] );
    }

    // ------------------------------------------------------------------
    //  Register REST Routes
    // ------------------------------------------------------------------

    /**
     * Called from doken_ox_register_routes action.
     *
     * @param string $namespace  The REST namespace (mvapp/v1).
     */
    public static function register_routes( $namespace ) {
        // Full config (public — Flutter reads this on boot)
        register_rest_route( $namespace, '/app/config', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'rest_get_config' ],
            'permission_callback' => '__return_true',
        ] );

        // Colors only
        register_rest_route( $namespace, '/app/config/colors', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'rest_get_colors' ],
            'permission_callback' => '__return_true',
        ] );

        // Theme (colors + typography + layout)
        register_rest_route( $namespace, '/app/config/theme', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'rest_get_theme' ],
            'permission_callback' => '__return_true',
        ] );

        // Features toggle map
        register_rest_route( $namespace, '/app/config/features', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'rest_get_features' ],
            'permission_callback' => '__return_true',
        ] );

        // Update (admin only)
        register_rest_route( $namespace, '/app/config', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'rest_update_config' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    // ------------------------------------------------------------------
    //  Default Config
    // ------------------------------------------------------------------

    /**
     * Factory default configuration.
     *
     * @return array
     */
    public static function default_config() {
        return [
            // Branding
            'app_name'            => get_bloginfo( 'name' ),
            'app_tagline'         => get_bloginfo( 'description' ),
            'logo_id'             => 0,
            'app_logo_url'        => '',
            'splash_logo_id'      => 0,
            'splash_image_url'    => '',
            'favicon_id'          => 0,
            'onboarding_slides'   => [],   // [{ image_url, title, subtitle }]

            // Colors
            'colors' => [
                'primary'          => '#6366F1',
                'primary_dark'     => '#4F46E5',
                'secondary'        => '#A855F7',
                'accent'           => '#06B6D4',
                'background'       => '#FFFFFF',
                'surface'          => '#F8FAFC',
                'surface_variant'  => '#F1F5F9',
                'text_primary'     => '#0F172A',
                'text_secondary'   => '#64748B',
                'text_hint'        => '#94A3B8',
                'border'           => '#E2E8F0',
                'success'          => '#10B981',
                'warning'          => '#F59E0B',
                'error'            => '#EF4444',
                'info'             => '#3B82F6',
                'navbar_bg'        => '#FFFFFF',
                'navbar_text'      => '#0F172A',
                'navbar_icon'      => '#6366F1',
                'bottom_bar_bg'    => '#FFFFFF',
                'bottom_bar_active'=> '#6366F1',
                'bottom_bar_inactive'=> '#94A3B8',
                'button_text'      => '#FFFFFF',
                'card_bg'          => '#FFFFFF',
                'badge_bg'         => '#EF4444',
                'badge_text'       => '#FFFFFF',
            ],

            // Typography
            'typography' => [
                'font_family'      => 'Cairo',
                'font_url'         => 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap',
                'base_size'        => 14,
                'heading_size'     => 24,
                'title_size'       => 18,
                'caption_size'     => 12,
                'font_weight_regular' => '400',
                'font_weight_medium'  => '600',
                'font_weight_bold'    => '700',
                'line_height'      => 1.6,
            ],

            // Layout & UI
            'layout' => [
                'border_radius'        => 12,
                'card_border_radius'   => 12,
                'button_border_radius' => 8,
                'card_shadow'          => true,
                'card_elevation'       => 4,
                'rtl_support'          => false,
                'product_card_style'   => 'grid',  // 'grid' | 'list'
                'home_layout'          => 'modern', // 'modern' | 'classic' | 'minimal'
                'tab_bar_style'        => 'icons',  // 'icons' | 'icons_labels' | 'labels'
                'image_quality'        => 80,
            ],

            // Feature Toggles
            'features' => [
                'enable_wishlist'         => true,
                'enable_cart'             => true,
                'enable_compare'          => false,
                'enable_notifications'    => true,
                'enable_reviews'          => true,
                'enable_vendor_chat'      => false,
                'enable_social_login'     => false,
                'enable_google_login'     => false,
                'enable_facebook_login'   => false,
                'enable_apple_login'      => false,
                'enable_biometric_auth'   => false,
                'enable_otp_login'        => false,
                'enable_dark_mode'        => true,
                'enable_multi_language'   => false,
                'enable_referral'         => false,
                'enable_loyalty_points'   => false,
                'enable_flash_sale'       => false,
                'show_vendor_info'        => true,
                'show_store_page'         => true,
            ],

            // Social / Third-party Keys (stored encrypted-ish via options)
            'integrations' => [
                'google_client_id'    => '',
                'facebook_app_id'     => '',
                'onesignal_app_id'    => '',
                'firebase_config'     => '',
                'google_maps_key'     => '',
                'stripe_public_key'   => '',
            ],

            // App Store Links
            'store_links' => [
                'play_store'   => '',
                'app_store'    => '',
                'app_version'  => '1.0.0',
                'min_version'  => '1.0.0',
                'force_update' => false,
            ],

            // Contact & Legal
            'contact' => [
                'support_email'   => get_option( 'admin_email' ),
                'support_phone'   => '',
                'whatsapp'        => '',
                'privacy_url'     => '',
                'terms_url'       => '',
                'about_us_url'    => '',
            ],
        ];
    }

    // ------------------------------------------------------------------
    //  Internals
    // ------------------------------------------------------------------

    /**
     * Deep merge two arrays (right side wins).
     *
     * @param array $base
     * @param array $override
     * @return array
     */
    private static function deep_merge( array $base, array $override ) {
        foreach ( $override as $key => $value ) {
            if ( isset( $base[ $key ] ) && is_array( $base[ $key ] ) && is_array( $value ) ) {
                $base[ $key ] = self::deep_merge( $base[ $key ], $value );
            } else {
                $base[ $key ] = $value;
            }
        }
        return $base;
    }

    /**
     * Resolve media_id → URL fields.
     *
     * @param array $config
     * @return array
     */
    private static function resolve_media_urls( array $config ) {
        $media_fields = [
            'logo_id'        => 'app_logo_url',
            'splash_logo_id' => 'splash_image_url',
        ];

        foreach ( $media_fields as $id_field => $url_field ) {
            $id = intval( $config[ $id_field ] ?? 0 );
            if ( $id > 0 ) {
                $url = wp_get_attachment_image_url( $id, 'full' );
                if ( $url ) {
                    $config[ $url_field ] = $url;
                }
            }
        }

        // Resolve onboarding slides media IDs
        if ( ! empty( $config['onboarding_slides'] ) && is_array( $config['onboarding_slides'] ) ) {
            foreach ( $config['onboarding_slides'] as &$slide ) {
                if ( ! empty( $slide['image_id'] ) ) {
                    $url = wp_get_attachment_image_url( intval( $slide['image_id'] ), 'large' );
                    if ( $url ) {
                        $slide['image_url'] = $url;
                    }
                }
            }
            unset( $slide );
        }

        return $config;
    }
}
