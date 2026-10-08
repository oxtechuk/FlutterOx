<?php
/**
 * Plugin Name: Doken Ox Pro — Multivendor Mobile Backend
 * Plugin URI:  https://Oxtech.uk
 * Description: Professional REST API backend for Flutter mobile apps — supports WooCommerce & Dokan Multivendor with a visual App Theme Builder.
 * Version:     1.0.0
 * Author:      Ox Tech
 * Author URI:  https://oxtech.uk
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: doken-ox-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DOKEN_OX_PRO_VERSION', '1.0.0' );
define( 'DOKEN_OX_PRO_FILE', __FILE__ );
define( 'DOKEN_OX_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'DOKEN_OX_PRO_URL', plugin_dir_url( __FILE__ ) );

// Autoload classes that follow the Doken_Ox_* naming convention.
require_once DOKEN_OX_PRO_PATH . 'includes/autoload.php';

// Core commercial classes (must load before everything else)
require_once DOKEN_OX_PRO_PATH . 'includes/class-plugin-mode.php';
require_once DOKEN_OX_PRO_PATH . 'includes/class-license-handler.php';
require_once DOKEN_OX_PRO_PATH . 'includes/class-rate-limiter.php';

// Installer handles activation tasks (DB tables, roles, defaults).
require_once DOKEN_OX_PRO_PATH . 'includes/class-installer.php';

register_activation_hook( __FILE__, array( 'Doken_Ox_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Doken_Ox_Installer', 'deactivate' ) );

/**
 * Main plugin bootstrapper.
 */
final class Doken_Ox_Pro {

    /**
     * Singleton instance.
     *
     * @var Doken_Ox_Pro|null
     */
    private static $instance = null;

    /**
     * Retrieve the singleton instance.
     *
     * @return Doken_Ox_Pro
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Bootstrap plugin internals.
     */
    private function __construct() {
        $this->includes();
        $this->hooks();
    }

    /**
     * Load required files.
     */
    private function includes() {
        // Helpers
        require_once DOKEN_OX_PRO_PATH . 'includes/helpers/response-helpers.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/helpers/validation-helpers.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/helpers/formatting-helpers.php';

        // Core services
        require_once DOKEN_OX_PRO_PATH . 'includes/class-api-router.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/class-cache-handler.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/class-jwt-handler.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/class-email-handler.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/class-notification-handler.php';

        // App Config — Theme & Feature settings
        require_once DOKEN_OX_PRO_PATH . 'includes/app-config.php';

        // Models
        require_once DOKEN_OX_PRO_PATH . 'includes/models/class-product-model.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/models/class-order-model.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/models/class-store-model.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/models/class-customer-model.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/models/class-vendor-model.php';
        require_once DOKEN_OX_PRO_PATH . 'includes/models/class-home-model.php';

        // Admin UI
        require_once DOKEN_OX_PRO_PATH . 'includes/admin-menu.php';

        // Admin class files
        require_once DOKEN_OX_PRO_PATH . 'admin/class-admin-dashboard.php';
        require_once DOKEN_OX_PRO_PATH . 'admin/class-admin-orders.php';
        require_once DOKEN_OX_PRO_PATH . 'admin/class-admin-products.php';
        require_once DOKEN_OX_PRO_PATH . 'admin/class-admin-settings.php';
        require_once DOKEN_OX_PRO_PATH . 'admin/class-admin-stores.php';
        require_once DOKEN_OX_PRO_PATH . 'admin/class-admin-users.php';

        // API modules (logic will be expanded in later stages)
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/auth.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/auth.php';
        }
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/customer.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/customer.php';
        }
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/vendor.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/vendor.php';
        }
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/admin.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/admin.php';
        }
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/home.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/home.php';
        }
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/search.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/search.php';
        }
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/products.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/products.php';
        }
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/cart.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/cart.php';
        }
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/orders.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/orders.php';
        }
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/profile.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/profile.php';
        }
        if ( file_exists( DOKEN_OX_PRO_PATH . 'includes/vendors.php' ) ) {
            require_once DOKEN_OX_PRO_PATH . 'includes/vendors.php';
        }
    }

    /**
     * Register runtime hooks.
     */
    private function hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        add_action( 'init', array( 'Doken_Ox_API_Router', 'init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Register App Config REST routes
        add_action( 'doken_ox_register_routes', array( 'Doken_Ox_App_Config', 'register_routes' ) );

        // License daily checks
        Doken_Ox_License_Handler::schedule_checks();

        // Admin notices
        add_action( 'admin_notices', array( $this, 'maybe_show_license_notice' ) );
    }

    /**
     * Fired after all plugins load.
     */
    public function on_plugins_loaded() {
        load_plugin_textdomain( 'doken-ox-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        if ( class_exists( 'Doken_Ox_Admin_Menu' ) ) {
            Doken_Ox_Admin_Menu::init();
        }
    }

    /**
     * Show a persistent admin notice if the license is not active (on live sites).
     */
    public function maybe_show_license_notice() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( Doken_Ox_License_Handler::is_valid() ) {
            return;
        }
        $url = admin_url( 'admin.php?page=doken-ox-pro-license' );
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Doken Ox Pro</strong> — ';
        /* translators: %s: settings URL */
        printf( esc_html__( 'Your license is not active. %s to activate.', 'doken-ox-pro' ), '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Click here', 'doken-ox-pro' ) . '</a>' );
        echo '</p></div>';
    }

    /**
     * Enqueue admin assets and expose REST config only on Doken Ox Pro pages.
     */
    public function enqueue_admin_assets() {
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( strpos( $page, 'doken-ox-pro' ) === false ) {
            return;
        }

        if ( in_array( $page, array( 'doken-ox-pro-home', 'doken-ox-pro-theme', 'doken-ox-pro-wizard' ), true ) ) {
            wp_enqueue_media();
        }

        wp_enqueue_style(
            'doken-ox-admin',
            DOKEN_OX_PRO_URL . 'assets/css/admin.css',
            array(),
            DOKEN_OX_PRO_VERSION
        );

        // Chart.js only needed on dashboard
        if ( $page === 'doken-ox-pro' ) {
            wp_enqueue_script(
                'doken-ox-chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.5/dist/chart.umd.min.js',
                array(),
                '4.4.5',
                true
            );
        }

        wp_enqueue_script(
            'doken-ox-admin',
            DOKEN_OX_PRO_URL . 'assets/js/admin.js',
            array( 'wp-element', 'wp-i18n' ),
            DOKEN_OX_PRO_VERSION,
            true
        );

        wp_localize_script(
            'doken-ox-admin',
            'DokenOxPro',
            array(
                'restUrl' => esc_url_raw( rest_url( 'mvapp/v1/' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'version' => DOKEN_OX_PRO_VERSION,
                'i18n'    => array(
                    'loading' => __( 'Loading…', 'doken-ox-pro' ),
                ),
            )
        );
    }
}

Doken_Ox_Pro::instance();
