<?php
/**
 * Doken Ox Pro — Settings View
 * Controls Plugin Mode, JWT Security, Rate Limits, Cache, and Mobile Integrations.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle Form Submission
$message = null;
$msg_type = 'success';

if ( isset( $_POST['dox_save_settings'] ) && check_admin_referer( 'dox_settings_action', 'dox_settings_nonce' ) ) {
    // 1. Plugin Mode
    $new_mode = sanitize_text_field( $_POST['dox_plugin_mode'] ?? 'woocommerce_dokan' );
    if ( class_exists( 'Doken_Ox_Plugin_Mode' ) ) {
        Doken_Ox_Plugin_Mode::set_mode( $new_mode );
    }

    // 2. Core Plugin Settings
    $current_settings = get_option( 'doken_ox_pro_settings', array() );
    $updated_settings = array(
        'rate_limit'      => max( 10, intval( $_POST['dox_rate_limit'] ?? 100 ) ),
        'enable_cache'    => ! empty( $_POST['dox_enable_cache'] ),
        'cache_ttl'       => max( 60, intval( $_POST['dox_cache_ttl'] ?? 900 ) ),
        'jwt_expiry'      => max( 300, intval( $_POST['dox_jwt_expiry'] ?? 86400 ) ),
        'refresh_expiry'  => max( 86400, intval( $_POST['dox_refresh_expiry'] ?? 604800 ) ),
        'log_api_errors'  => ! empty( $_POST['dox_log_api_errors'] ),
        'fcm_server_key'  => sanitize_text_field( $_POST['dox_fcm_server_key'] ?? '' ),
        'cors_origins'    => sanitize_text_field( $_POST['dox_cors_origins'] ?? '*' ),
    );

    update_option( 'doken_ox_pro_settings', $updated_settings );

    // Optional cache flush action
    if ( ! empty( $_POST['dox_purge_cache'] ) ) {
        delete_transient( 'dox_admin_system_stats' );
        if ( class_exists( 'Doken_Ox_Cache_Handler' ) && method_exists( 'Doken_Ox_Cache_Handler', 'flush_all' ) ) {
            Doken_Ox_Cache_Handler::flush_all();
        }
    }

    $message  = __( 'Settings updated successfully.', 'doken-ox-pro' );
    $msg_type = 'success';
}

// Regenerate JWT Secret
if ( isset( $_POST['dox_regenerate_jwt_secret'] ) && check_admin_referer( 'dox_settings_action', 'dox_settings_nonce' ) ) {
    $new_secret = wp_generate_password( 64, true, true );
    update_option( 'doken_ox_pro_jwt_secret', $new_secret );
    $message  = __( 'New JWT Secret Key generated. All existing user sessions will need to re-login.', 'doken-ox-pro' );
    $msg_type = 'warning';
}

// Current Values
$current_mode = class_exists( 'Doken_Ox_Plugin_Mode' ) ? Doken_Ox_Plugin_Mode::get_mode() : 'woocommerce_dokan';
$dokan_active = class_exists( 'Doken_Ox_Plugin_Mode' ) && Doken_Ox_Plugin_Mode::is_dokan_installed();
$settings     = get_option( 'doken_ox_pro_settings', array(
    'rate_limit'     => 100,
    'enable_cache'   => true,
    'cache_ttl'      => 900,
    'jwt_expiry'     => 86400,
    'refresh_expiry' => 604800,
    'log_api_errors' => true,
    'fcm_server_key' => '',
    'cors_origins'   => '*',
) );

$jwt_secret = get_option( 'doken_ox_pro_jwt_secret', '••••••••••••••••••••••••••••••••' );
$current_user = wp_get_current_user();
$username     = $current_user->display_name ?: 'SPACEREMIT';
?>

<div class="dox-page">

    <!-- Top SpaceRemit Header -->
    <div class="dox-header">
        <div style="font-size:22px;font-weight:800;color:var(--dox-text-primary);letter-spacing:-0.5px;">
            <?php _e( 'Settings', 'doken-ox-pro' ); ?>
        </div>

        <div class="dox-header-actions">
            <?php if ( class_exists( 'Doken_Ox_Plugin_Mode' ) ) : ?>
                <div class="dox-mode-badge <?php echo Doken_Ox_Plugin_Mode::is_woo_only_mode() ? 'woo-only' : ''; ?>">
                    <?php echo esc_html( Doken_Ox_Plugin_Mode::get_mode_label() ); ?>
                </div>
            <?php endif; ?>

            <a href="<?php echo esc_url( admin_url( 'admin.php?page=doken-ox-pro-wizard' ) ); ?>" class="dox-btn dox-btn-secondary" style="padding:6px 12px;font-size:12px;">
                <span class="dashicons dashicons-welcome-learn-more" style="font-size:14px;width:14px;height:14px;"></span>
                <?php _e( 'Setup Wizard', 'doken-ox-pro' ); ?>
            </a>

            <div class="dox-user-pill">
                <div class="dox-user-avatar">
                    <span class="dashicons dashicons-admin-users" style="font-size:16px;"></span>
                </div>
                <div class="dox-user-meta">
                    <div class="dox-user-welcome"><?php _e( 'Welcome!', 'doken-ox-pro' ); ?></div>
                    <div class="dox-user-name"><?php echo esc_html( $username ); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="dox-layout">

        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="dox-main">
            <?php if ( $message ) : ?>
                <div class="dox-card" style="padding:14px 20px;border-left:4px solid var(--dox-<?php echo esc_attr( $msg_type ); ?>);margin-bottom:24px;display:flex;align-items:center;gap:12px;">
                    <span class="dashicons <?php echo $msg_type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" style="color:var(--dox-<?php echo esc_attr( $msg_type ); ?>);font-size:20px;"></span>
                    <span style="font-size:13px;font-weight:600;color:var(--dox-text-primary);"><?php echo esc_html( $message ); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'dox_settings_action', 'dox_settings_nonce' ); ?>

                <!-- SECTION 1: Architecture Mode -->
                <div class="dox-card">
                    <div class="dox-card-header">
                        <h3 class="dox-card-title">
                            <span class="dashicons dashicons-networking"></span>
                            <?php _e( 'Architecture & Store Mode', 'doken-ox-pro' ); ?>
                        </h3>
                        <span class="dox-badge dox-badge-primary"><?php _e( 'Core Engine', 'doken-ox-pro' ); ?></span>
                    </div>

                    <p style="color:var(--dox-text-secondary);font-size:13px;margin-bottom:20px;">
                        <?php _e( 'Select how Doken Ox Pro operates on this WordPress instance. This dynamically adjusts REST API endpoints, security layers, and mobile app capabilities.', 'doken-ox-pro' ); ?>
                    </p>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;margin-bottom:20px;">

                        <!-- Mode 1: Dokan Multivendor -->
                        <label style="display:block;cursor:pointer;">
                            <input type="radio" name="dox_plugin_mode" value="woocommerce_dokan" <?php checked( $current_mode, 'woocommerce_dokan' ); ?> style="display:none;" class="dox-mode-radio">
                            <div class="dox-mode-card" style="border:2px solid <?php echo $current_mode === 'woocommerce_dokan' ? 'var(--dox-primary)' : 'var(--dox-border)'; ?>;border-radius:var(--dox-radius-lg);padding:20px;background:var(--dox-card);height:100%;transition:all .2s ease;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                                    <div style="width:40px;height:40px;border-radius:10px;background:var(--dox-primary-glow);display:flex;align-items:center;justify-content:center;color:var(--dox-primary-light);">
                                        <span class="dashicons dashicons-store" style="font-size:22px;"></span>
                                    </div>
                                    <span class="dox-badge dox-badge-primary">Recommended</span>
                                </div>
                                <h4 style="margin:0 0 6px;color:var(--dox-text-primary);font-size:15px;font-weight:700;">
                                    <?php _e( 'WooCommerce + Dokan', 'doken-ox-pro' ); ?>
                                </h4>
                                <p style="margin:0;font-size:12.5px;color:var(--dox-text-secondary);line-height:1.5;">
                                    <?php _e( 'Complete multi-vendor marketplace with vendor store profiles, vendor earnings, product management, and Dokan vendor dashboards for mobile.', 'doken-ox-pro' ); ?>
                                </p>
                                <div style="margin-top:14px;font-size:11.5px;color:var(--dox-text-muted);display:flex;align-items:center;gap:6px;">
                                    <span><?php _e( 'Dokan Plugin Status:', 'doken-ox-pro' ); ?></span>
                                    <?php if ( $dokan_active ) : ?>
                                        <span class="dox-badge dox-badge-success" style="font-size:9px;padding:1px 6px;">Active</span>
                                    <?php else : ?>
                                        <span class="dox-badge dox-badge-warning" style="font-size:9px;padding:1px 6px;">Not Detected</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>

                        <!-- Mode 2: WooCommerce Only -->
                        <label style="display:block;cursor:pointer;">
                            <input type="radio" name="dox_plugin_mode" value="woocommerce" <?php checked( $current_mode, 'woocommerce' ); ?> style="display:none;" class="dox-mode-radio">
                            <div class="dox-mode-card" style="border:2px solid <?php echo $current_mode === 'woocommerce' ? 'var(--dox-primary)' : 'var(--dox-border)'; ?>;border-radius:var(--dox-radius-lg);padding:20px;background:var(--dox-card);height:100%;transition:all .2s ease;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                                    <div style="width:40px;height:40px;border-radius:10px;background:var(--dox-accent-glow);display:flex;align-items:center;justify-content:center;color:var(--dox-accent);">
                                        <span class="dashicons dashicons-cart" style="font-size:22px;"></span>
                                    </div>
                                    <span class="dox-badge dox-badge-info">Single Store</span>
                                </div>
                                <h4 style="margin:0 0 6px;color:var(--dox-text-primary);font-size:15px;font-weight:700;">
                                    <?php _e( 'WooCommerce Only', 'doken-ox-pro' ); ?>
                                </h4>
                                <p style="margin:0;font-size:12.5px;color:var(--dox-text-secondary);line-height:1.5;">
                                    <?php _e( 'Standard single-seller e-commerce store. Vendor routes and vendor tabs are deactivated, streamlining mobile API responses for maximum speed.', 'doken-ox-pro' ); ?>
                                </p>
                                <div style="margin-top:14px;font-size:11.5px;color:var(--dox-text-muted);display:flex;align-items:center;gap:4px;">
                                    <span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px;color:var(--dox-success);"></span> <?php _e( 'Optimized for single-merchant mobile apps', 'doken-ox-pro' ); ?>
                                </div>
                            </div>
                        </label>

                    </div>
                </div>

                <!-- SECTION 2: API & JWT Security -->
                <div class="dox-card">
                    <div class="dox-card-header">
                        <h3 class="dox-card-title">
                            <span class="dashicons dashicons-shield"></span>
                            <?php _e( 'Authentication & JWT Security', 'doken-ox-pro' ); ?>
                        </h3>
                        <span class="dox-badge dox-badge-info">HS256 Standard</span>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:20px;margin-bottom:20px;">
                        <div>
                            <label class="dox-form-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--dox-text-primary);">
                                <?php _e( 'Access Token Expiry (Seconds)', 'doken-ox-pro' ); ?>
                            </label>
                            <input type="number" name="dox_jwt_expiry" value="<?php echo esc_attr( $settings['jwt_expiry'] ); ?>" class="dox-input" style="width:100%;padding:10px 14px;background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:var(--dox-radius);color:var(--dox-text-primary);" min="300" step="300">
                            <p style="font-size:11px;color:var(--dox-text-muted);margin:4px 0 0;">
                                <?php _e( 'Default: 86400 (24 hours). Used for mobile app user sessions.', 'doken-ox-pro' ); ?>
                            </p>
                        </div>

                        <div>
                            <label class="dox-form-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--dox-text-primary);">
                                <?php _e( 'Refresh Token Expiry (Seconds)', 'doken-ox-pro' ); ?>
                            </label>
                            <input type="number" name="dox_refresh_expiry" value="<?php echo esc_attr( $settings['refresh_expiry'] ); ?>" class="dox-input" style="width:100%;padding:10px 14px;background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:var(--dox-radius);color:var(--dox-text-primary);" min="86400" step="86400">
                            <p style="font-size:11px;color:var(--dox-text-muted);margin:4px 0 0;">
                                <?php _e( 'Default: 604800 (7 days). Allows silent session refresh in Flutter app.', 'doken-ox-pro' ); ?>
                            </p>
                        </div>

                        <div>
                            <label class="dox-form-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--dox-text-primary);">
                                <?php _e( 'CORS Allowed Origins', 'doken-ox-pro' ); ?>
                            </label>
                            <input type="text" name="dox_cors_origins" value="<?php echo esc_attr( $settings['cors_origins'] ?? '*' ); ?>" class="dox-input" style="width:100%;padding:10px 14px;background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:var(--dox-radius);color:var(--dox-text-primary);">
                            <p style="font-size:11px;color:var(--dox-text-muted);margin:4px 0 0;">
                                <?php _e( 'Default: * (allows web & mobile apps). Or comma-separated domains.', 'doken-ox-pro' ); ?>
                            </p>
                        </div>
                    </div>

                    <div style="background:var(--dox-bg-secondary);border:1px solid var(--dox-border);border-radius:var(--dox-radius);padding:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;">
                        <div>
                            <div style="font-size:12.5px;font-weight:700;color:var(--dox-text-primary);margin-bottom:4px;">
                                <?php _e( 'JWT Secret Key', 'doken-ox-pro' ); ?>
                            </div>
                            <div style="font-family:var(--dox-font-mono);font-size:11px;color:var(--dox-text-muted);">
                                <?php echo esc_html( substr( $jwt_secret, 0, 16 ) . '••••••••••••••••••••••••' ); ?>
                            </div>
                        </div>
                        <button type="submit" name="dox_regenerate_jwt_secret" value="1" class="dox-btn dox-btn-danger" style="padding:7px 14px;font-size:11.5px;" onclick="return confirm('<?php echo esc_js( __( 'Are you sure? This invalidates all active mobile sessions.', 'doken-ox-pro' ) ); ?>');">
                            <?php _e( 'Regenerate Secret', 'doken-ox-pro' ); ?>
                        </button>
                    </div>
                </div>

                <!-- SECTION 3: Rate Limiting & Performance -->
                <div class="dox-card">
                    <div class="dox-card-header">
                        <h3 class="dox-card-title">
                            <span class="dashicons dashicons-performance"></span>
                            <?php _e( 'Rate Limiting & Caching', 'doken-ox-pro' ); ?>
                        </h3>
                        <span class="dox-badge dox-badge-success"><?php _e( 'DDoS Protection', 'doken-ox-pro' ); ?></span>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:20px;margin-bottom:20px;">
                        <div>
                            <label class="dox-form-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--dox-text-primary);">
                                <?php _e( 'Global Rate Limit (Req / Minute)', 'doken-ox-pro' ); ?>
                            </label>
                            <input type="number" name="dox_rate_limit" value="<?php echo esc_attr( $settings['rate_limit'] ); ?>" class="dox-input" style="width:100%;padding:10px 14px;background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:var(--dox-radius);color:var(--dox-text-primary);" min="10" max="5000">
                            <p style="font-size:11px;color:var(--dox-text-muted);margin:4px 0 0;">
                                <?php _e( 'Limits per client IP address. Admins automatically bypass.', 'doken-ox-pro' ); ?>
                            </p>
                        </div>

                        <div>
                            <label class="dox-form-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--dox-text-primary);">
                                <?php _e( 'Cache Transient TTL (Seconds)', 'doken-ox-pro' ); ?>
                            </label>
                            <input type="number" name="dox_cache_ttl" value="<?php echo esc_attr( $settings['cache_ttl'] ?? 900 ); ?>" class="dox-input" style="width:100%;padding:10px 14px;background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:var(--dox-radius);color:var(--dox-text-primary);" min="60" step="60">
                            <p style="font-size:11px;color:var(--dox-text-muted);margin:4px 0 0;">
                                <?php _e( 'Default: 900 (15 min). Caches heavy responses (home layout, categories).', 'doken-ox-pro' ); ?>
                            </p>
                        </div>
                    </div>

                    <div style="display:flex;flex-wrap:wrap;gap:20px;padding-top:10px;border-top:1px solid var(--dox-border-subtle);">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                            <input type="checkbox" name="dox_enable_cache" value="1" <?php checked( ! empty( $settings['enable_cache'] ) ); ?> style="accent-color:var(--dox-primary);">
                            <span style="font-size:13px;font-weight:600;color:var(--dox-text-primary);"><?php _e( 'Enable Response Object Caching', 'doken-ox-pro' ); ?></span>
                        </label>

                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                            <input type="checkbox" name="dox_log_api_errors" value="1" <?php checked( ! empty( $settings['log_api_errors'] ) ); ?> style="accent-color:var(--dox-primary);">
                            <span style="font-size:13px;font-weight:600;color:var(--dox-text-primary);"><?php _e( 'Log API Exceptions in Database', 'doken-ox-pro' ); ?></span>
                        </label>

                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                            <input type="checkbox" name="dox_purge_cache" value="1" style="accent-color:var(--dox-warning);">
                            <span style="font-size:13px;font-weight:600;color:var(--dox-warning);"><?php _e( 'Purge All Transients Now', 'doken-ox-pro' ); ?></span>
                        </label>
                    </div>
                </div>

                <!-- SECTION 4: Firebase Cloud Messaging (Push Notifications) -->
                <div class="dox-card">
                    <div class="dox-card-header">
                        <h3 class="dox-card-title">
                            <span class="dashicons dashicons-bell"></span>
                            <?php _e( 'Firebase Cloud Messaging (FCM)', 'doken-ox-pro' ); ?>
                        </h3>
                        <span class="dox-badge dox-badge-muted">Flutter Push</span>
                    </div>

                    <div>
                        <label class="dox-form-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--dox-text-primary);">
                            <?php _e( 'FCM Server Key / OAuth2 Token', 'doken-ox-pro' ); ?>
                        </label>
                        <input type="password" name="dox_fcm_server_key" value="<?php echo esc_attr( $settings['fcm_server_key'] ?? '' ); ?>" class="dox-input" style="width:100%;padding:10px 14px;background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:var(--dox-radius);color:var(--dox-text-primary);" placeholder="AAAAxxxxxxxx:APA91b...">
                        <p style="font-size:11px;color:var(--dox-text-muted);margin:4px 0 0;">
                            <?php _e( 'Used to send push notifications to Flutter mobile devices on new orders, status changes, and promos.', 'doken-ox-pro' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Submit Bar -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:28px;">
                    <button type="submit" name="dox_save_settings" value="1" class="dox-btn dox-btn-primary" style="padding:12px 28px;font-size:14px;">
                        <span class="dashicons dashicons-saved" style="font-size:16px;"></span>
                        <?php _e( 'Save All Settings', 'doken-ox-pro' ); ?>
                    </button>
                    <span style="font-size:12px;color:var(--dox-text-muted);">
                        <?php _e( 'Changes take effect immediately across all REST endpoints.', 'doken-ox-pro' ); ?>
                    </span>
                </div>

            </form>

        </main>
    </div>
</div>

<script>
// Visual highlight for mode selection cards
document.querySelectorAll('.dox-mode-radio').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.dox-mode-card').forEach(function(card) {
            card.style.borderColor = 'var(--dox-border)';
        });
        if (this.checked) {
            this.closest('label').querySelector('.dox-mode-card').style.borderColor = 'var(--dox-primary)';
        }
    });
});
</script>
