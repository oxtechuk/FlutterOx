<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Installer {

    const DB_VERSION = '2.0.0'; // Updated for commercial release

    public static function activate() {
        self::run_install();
    }

    public static function deactivate() {
        // احتياطي: لا نحذف أي شيء أثناء إلغاء التفعيل
    }

    /**
     * تشغيل التثبيت أو التحديث
     */
    public static function run_install() {
        self::create_db_tables();
        self::create_roles_capabilities();
        self::set_default_options();
        self::check_db_version();
        self::log_install_event();
    }

    /**
     * إنشاء الجداول
     */
    public static function create_db_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $sql = [];

        // Addresses table
        $sql[] = "CREATE TABLE {$prefix}dox_addresses (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(20) DEFAULT 'shipping',
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            address_1 VARCHAR(255),
            address_2 VARCHAR(255),
            city VARCHAR(100),
            state VARCHAR(100),
            postcode VARCHAR(20),
            country VARCHAR(20),
            phone VARCHAR(50),
            email VARCHAR(100),
            is_default TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Notifications table
        $sql[] = "CREATE TABLE {$prefix}dox_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255),
            message TEXT,
            meta LONGTEXT,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Wishlist
        $sql[] = "CREATE TABLE {$prefix}dox_wishlist (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_product (user_id, product_id)
        ) $charset_collate;";

        // Logs
        $sql[] = "CREATE TABLE {$prefix}dox_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            route VARCHAR(255),
            method VARCHAR(10),
            ip VARCHAR(45),
            payload LONGTEXT,
            response LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Tokens (optional but recommended)
        $sql[] = "CREATE TABLE {$prefix}dox_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            jwt_token TEXT,
            refresh_token VARCHAR(255),
            expires_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Home page sections
        $sql[] = "CREATE TABLE {$prefix}dox_home_sections (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            section VARCHAR(50) NOT NULL,
            title VARCHAR(255),
            subtitle VARCHAR(255),
            media_id BIGINT UNSIGNED NULL,
            link_url VARCHAR(255),
            data LONGTEXT,
            sort_order INT DEFAULT 0,
            status TINYINT(1) DEFAULT 1,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            analytics_clicks BIGINT UNSIGNED DEFAULT 0,
            analytics_views BIGINT UNSIGNED DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY section (section),
            KEY status (status),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // Home analytics events
        $sql[] = "CREATE TABLE {$prefix}dox_home_events (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            section VARCHAR(50),
            item_id BIGINT UNSIGNED NULL,
            event_type VARCHAR(20),
            user_id BIGINT UNSIGNED NULL,
            meta LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY section (section),
            KEY event_type (event_type)
        ) $charset_collate;";

        // App Config table
        $sql[] = "CREATE TABLE {$prefix}dox_app_config (
            id         BIGINT UNSIGNED AUTO_INCREMENT,
            config_key VARCHAR(100) NOT NULL,
            config_val LONGTEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY config_key (config_key)
        ) $charset_collate;";

        // API Keys table
        $sql[] = "CREATE TABLE {$prefix}dox_api_keys (
            id          BIGINT UNSIGNED AUTO_INCREMENT,
            key_name    VARCHAR(100),
            api_key     VARCHAR(64) NOT NULL,
            permissions VARCHAR(255) DEFAULT 'read',
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used   DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key)
        ) $charset_collate;";

        // License table
        $sql[] = "CREATE TABLE {$prefix}dox_license (
            id           BIGINT UNSIGNED AUTO_INCREMENT,
            license_key  VARCHAR(255) NOT NULL,
            status       VARCHAR(20) DEFAULT 'inactive',
            plan         VARCHAR(50) NULL,
            expires_at   DATETIME NULL,
            site_url     VARCHAR(255),
            activated_at DATETIME NULL,
            last_check   DATETIME NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }

    /**
     * إضافة صلاحيات خاصة للعميل والبائع
     */
    public static function create_roles_capabilities() {

        // Customer
        $customer = get_role('customer');
        if ($customer) {
            $customer->add_cap('dox_read_address');
            $customer->add_cap('dox_manage_address');
            $customer->add_cap('dox_manage_wishlist');
        }

        // Vendor — يجب أن تكون Dokan مثبتة
        $vendor = get_role('seller'); // Dokan default role
        if ($vendor) {
            $vendor->add_cap('dox_vendor_dashboard');
            $vendor->add_cap('dox_manage_products');
            $vendor->add_cap('dox_manage_orders');
            $vendor->add_cap('dox_view_earnings');
            $vendor->add_cap('dox_vendor_api_access');
        }

        // Admin
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('dox_admin_api_access');
            $admin->add_cap('dox_manage_system');
        }
    }

    /**
     * إضافة الإعدادات الافتراضية
     */
    public static function set_default_options() {
        if (! get_option('doken_ox_pro_settings')) {
            add_option('doken_ox_pro_settings', [
                'rate_limit'     => 100,   // 100 requests per minute (global fallback)
                'enable_cache'   => true,
                'jwt_expiry'     => 24 * 60 * 60,       // 1 day
                'refresh_expiry' => 7 * 24 * 60 * 60,   // 7 days
            ]);
        }

        // Plugin mode default
        if (! get_option( Doken_Ox_Plugin_Mode::OPTION_KEY )) {
            add_option( Doken_Ox_Plugin_Mode::OPTION_KEY, Doken_Ox_Plugin_Mode::MODE_WOO_DOKAN );
        }
    }

    /**
     * حفظ نسخة قاعدة البيانات الحالية
     */
    public static function check_db_version() {
        $installed_version = get_option('doken_ox_pro_db_version');

        if ($installed_version !== self::DB_VERSION) {
            update_option('doken_ox_pro_db_version', self::DB_VERSION);
        }
    }

    /**
     * تسجيل عملية التثبيت
     */
    public static function log_install_event() {
        update_option('doken_ox_pro_last_install', current_time('mysql'));
    }
}
