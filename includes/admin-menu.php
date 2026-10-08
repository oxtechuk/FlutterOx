<?php
/**
 * Admin menu + placeholder pages for React dashboards.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Admin_Menu {

    /**
     * Boot menu hooks.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    /**
     * Register WP Admin pages.
     */
    public static function register_menu() {
        $capability = 'manage_options';

        add_menu_page(
            __( 'Doken Ox Pro', 'doken-ox-pro' ),
            __( 'Doken Ox Pro', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro',
            array( __CLASS__, 'render_dashboard' ),
            'dashicons-store',
            56
        );

        add_submenu_page(
            'doken-ox-pro',
            __( 'Dashboard', 'doken-ox-pro' ),
            __( 'Dashboard', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro',
            array( __CLASS__, 'render_dashboard' )
        );

        add_submenu_page(
            'doken-ox-pro',
            __( 'Orders', 'doken-ox-pro' ),
            __( 'Orders', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro-orders',
            array( __CLASS__, 'render_orders' )
        );

        add_submenu_page(
            'doken-ox-pro',
            __( 'Mobile Builder', 'doken-ox-pro' ),
            __( 'Mobile Builder', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro-home',
            array( __CLASS__, 'render_home' )
        );

        add_submenu_page(
            'doken-ox-pro',
            __( 'Products', 'doken-ox-pro' ),
            __( 'Products', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro-products',
            array( __CLASS__, 'render_products' )
        );

        if ( ! class_exists( 'Doken_Ox_Plugin_Mode' ) || Doken_Ox_Plugin_Mode::is_dokan_mode() ) {
            add_submenu_page(
                'doken-ox-pro',
                __( 'Stores', 'doken-ox-pro' ),
                __( 'Stores', 'doken-ox-pro' ),
                $capability,
                'doken-ox-pro-stores',
                array( __CLASS__, 'render_stores' )
            );
        }

        add_submenu_page(
            'doken-ox-pro',
            __( 'Users', 'doken-ox-pro' ),
            __( 'Users', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro-users',
            array( __CLASS__, 'render_users' )
        );

        add_submenu_page(
            'doken-ox-pro',
            __( 'App Theme', 'doken-ox-pro' ),
            __( 'App Theme', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro-theme',
            array( __CLASS__, 'render_theme' )
        );

        add_submenu_page(
            'doken-ox-pro',
            __( 'Settings', 'doken-ox-pro' ),
            __( 'Settings', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro-settings',
            array( __CLASS__, 'render_settings' )
        );

        add_submenu_page(
            'doken-ox-pro',
            __( 'License', 'doken-ox-pro' ),
            __( 'License', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro-license',
            array( __CLASS__, 'render_license' )
        );

        add_submenu_page(
            'doken-ox-pro',
            __( 'Setup Wizard', 'doken-ox-pro' ),
            __( 'Setup Wizard', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro-wizard',
            array( __CLASS__, 'render_wizard' )
        );

        add_submenu_page(
            'doken-ox-pro',
            __( 'Development', 'doken-ox-pro' ),
            __( 'Development', 'doken-ox-pro' ),
            $capability,
            'doken-ox-pro-dev',
            array( __CLASS__, 'render_dev' )
        );
    }

    /**
     * Renderers simply include view templates.
     */
    public static function render_wizard() {
        self::render_view( 'wizard' );
    }
    public static function render_dashboard() {
        self::render_view( 'dashboard' );
    }

    public static function render_home() {
        self::render_view( 'home-app' );
    }

    public static function render_theme() {
        self::render_view( 'app-theme' );
    }

    public static function render_license() {
        self::render_view( 'license' );
    }

    public static function render_orders() {
        self::render_view( 'orders' );
    }

    public static function render_products() {
        self::render_view( 'products' );
    }

    public static function render_stores() {
        self::render_view( 'stores' );
    }

    public static function render_users() {
        self::render_view( 'users' );
    }

    public static function render_settings() {
        self::render_view( 'settings' );
    }

    public static function render_dev() {
        self::render_view( 'dev' );
    }

    /**
     * Include a view with guard.
     *
     * @param string $view view slug.
     */
    private static function render_view( $view ) {
        $file = DOKEN_OX_PRO_PATH . 'admin/views/' . $view . '.php';
        if ( file_exists( $file ) ) {
            include $file;
        } else {
            echo '<div class="wrap"><h1>' . esc_html__( 'View not found.', 'doken-ox-pro' ) . '</h1></div>';
        }
    }
}
