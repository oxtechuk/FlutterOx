<?php
/**
 * Plugin Mode Handler
 *
 * Controls whether Doken Ox Pro runs in:
 *   - 'woocommerce'       → Standard store (no Vendor/Dokan routes)
 *   - 'woocommerce_dokan' → Multivendor marketplace (full Dokan integration)
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Plugin_Mode {

    /**
     * Available modes.
     */
    const MODE_WOO       = 'woocommerce';
    const MODE_WOO_DOKAN = 'woocommerce_dokan';

    /**
     * Option key where the mode is stored.
     */
    const OPTION_KEY = 'dox_plugin_mode';

    /**
     * Get the current operating mode.
     *
     * @return string
     */
    public static function get_mode() {
        return get_option( self::OPTION_KEY, self::MODE_WOO_DOKAN );
    }

    /**
     * Set the operating mode.
     *
     * @param string $mode One of the MODE_* constants.
     * @return bool
     */
    public static function set_mode( $mode ) {
        if ( ! in_array( $mode, [ self::MODE_WOO, self::MODE_WOO_DOKAN ], true ) ) {
            return false;
        }
        return update_option( self::OPTION_KEY, $mode );
    }

    /**
     * Is Dokan/Multivendor mode active?
     *
     * @return bool
     */
    public static function is_dokan_mode() {
        return self::get_mode() === self::MODE_WOO_DOKAN;
    }

    /**
     * Is WooCommerce-only mode active?
     *
     * @return bool
     */
    public static function is_woo_only_mode() {
        return self::get_mode() === self::MODE_WOO;
    }

    /**
     * Should vendor API routes be registered?
     * Requires both Dokan mode AND the Dokan plugin being active.
     *
     * @return bool
     */
    public static function should_load_vendor_routes() {
        if ( ! self::is_dokan_mode() ) {
            return false;
        }
        // Support all known Dokan class names
        return class_exists( 'WeDevs_Dokan' )
            || class_exists( 'Dokan_Pro' )
            || function_exists( 'dokan' );
    }

    /**
     * Get the list of active API capability groups for the current mode.
     *
     * @return string[]
     */
    public static function get_api_capabilities() {
        $base = [
            'auth',
            'home',
            'products',
            'categories',
            'search',
            'cart',
            'orders',
            'profile',
            'notifications',
            'wishlist',
        ];

        if ( self::should_load_vendor_routes() ) {
            $base = array_merge( $base, [
                'vendors',
                'vendor_dashboard',
                'vendor_products',
                'vendor_orders',
                'vendor_earnings',
                'stores',
            ] );
        }

        return apply_filters( 'doken_ox_api_capabilities', $base );
    }

    /**
     * Check whether a specific API capability is active.
     *
     * @param string $capability
     * @return bool
     */
    public static function has_capability( $capability ) {
        return in_array( $capability, self::get_api_capabilities(), true );
    }

    /**
     * Get a human-readable label for the current mode.
     *
     * @return string
     */
    public static function get_mode_label() {
        $labels = [
            self::MODE_WOO       => __( 'WooCommerce Only', 'doken-ox-pro' ),
            self::MODE_WOO_DOKAN => __( 'WooCommerce + Dokan (Multivendor)', 'doken-ox-pro' ),
        ];

        return $labels[ self::get_mode() ] ?? __( 'Unknown', 'doken-ox-pro' );
    }

    /**
     * Get all mode options (for UI dropdowns/radios).
     *
     * @return array[]  [ ['value' => ..., 'label' => ..., 'description' => ...], ... ]
     */
    public static function get_mode_options() {
        return [
            [
                'value'       => self::MODE_WOO,
                'label'       => __( 'WooCommerce Only', 'doken-ox-pro' ),
                'description' => __( 'Standard single-seller store. No vendor dashboard or multi-store features.', 'doken-ox-pro' ),
                'icon'        => 'dashicons-cart',
            ],
            [
                'value'       => self::MODE_WOO_DOKAN,
                'label'       => __( 'WooCommerce + Dokan', 'doken-ox-pro' ),
                'description' => __( 'Full multivendor marketplace with vendor dashboards, earnings, and store management.', 'doken-ox-pro' ),
                'icon'        => 'dashicons-store',
                'requires'    => 'Dokan',
            ],
        ];
    }

    /**
     * Detect whether Dokan plugin is installed & active (regardless of mode).
     *
     * @return bool
     */
    public static function is_dokan_installed() {
        return class_exists( 'WeDevs_Dokan' )
            || class_exists( 'Dokan_Pro' )
            || function_exists( 'dokan' );
    }

    /**
     * REST endpoint data about the active mode (for diagnostics).
     *
     * @return array
     */
    public static function get_status_info() {
        return [
            'mode'              => self::get_mode(),
            'mode_label'        => self::get_mode_label(),
            'dokan_installed'   => self::is_dokan_installed(),
            'vendor_routes'     => self::should_load_vendor_routes(),
            'capabilities'      => self::get_api_capabilities(),
        ];
    }
}
