<?php
/**
 * License Management Page
 *
 * @package Doken_Ox_Pro
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$summary = Doken_Ox_License_Handler::get_status_summary();
$action  = isset($_POST['dox_license_action']) ? sanitize_text_field($_POST['dox_license_action']) : '';
$message = null;
$msg_type = 'info';

if ( $action && check_admin_referer('dox_license_action') ) {
    if ( $action === 'activate' ) {
        $key    = sanitize_text_field($_POST['dox_license_key'] ?? '');
        $result = Doken_Ox_License_Handler::activate($key);
        $message  = $result['message'];
        $msg_type = $result['success'] ? 'success' : 'danger';
        if ($result['success']) $summary = Doken_Ox_License_Handler::get_status_summary();
    } elseif ( $action === 'deactivate' ) {
        $result = Doken_Ox_License_Handler::deactivate();
        $message  = $result['message'];
        $msg_type = 'warning';
        $summary  = Doken_Ox_License_Handler::get_status_summary();
    } elseif ( $action === 'check' ) {
        Doken_Ox_License_Handler::check_status();
        $summary  = Doken_Ox_License_Handler::get_status_summary();
        $message  = __('License status refreshed.', 'doken-ox-pro');
        $msg_type = 'info';
    }
}

$status_icon = ( $summary['status'] === 'active' ) ? 'dashicons-yes-alt' : 'dashicons-warning';
?>
<div class="dox-page">

    <!-- Top SpaceRemit Header -->
    <div class="dox-header">
        <div style="font-size:22px;font-weight:800;color:var(--dox-text-primary);letter-spacing:-0.5px;">
            <?php _e('License & Activation', 'doken-ox-pro'); ?>
        </div>

        <div class="dox-header-actions">
            <span class="dox-badge <?php echo $summary['is_valid'] ? 'dox-badge-success' : 'dox-badge-warning'; ?>">
                <span class="dashicons <?php echo esc_attr($status_icon); ?>" style="font-size:13px;width:13px;height:13px;"></span>
                <?php echo esc_html(ucfirst($summary['status'])); ?>
            </span>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="dox-layout">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>
        <main class="dox-main">

            <p style="font-size:13.5px;color:var(--dox-text-muted);margin:0 0 24px;">
                <?php _e('Manage your commercial Doken Ox Pro license key and domain activation.', 'doken-ox-pro'); ?>
            </p>

            <?php if ($message) : ?>
            <div class="dox-alert dox-alert-<?php echo esc_attr($msg_type); ?>"><?php echo esc_html($message); ?></div>
            <?php endif; ?>

            <?php if ($summary['is_local']) : ?>
            <div class="dox-card" style="padding:14px 18px;border-left:4px solid var(--dox-info);margin-bottom:20px;font-size:13px;color:var(--dox-text-primary);display:flex;align-items:center;gap:10px;">
                <span class="dashicons dashicons-admin-site-alt3" style="color:var(--dox-info);"></span>
                <span><?php _e('Development environment detected. License validation is bypassed on localhost/local domains.', 'doken-ox-pro'); ?></span>
            </div>
            <?php endif; ?>

            <div style="max-width:640px;">

                <!-- Status Card -->
                <div class="dox-card" style="margin-bottom:20px;">
                    <div class="dox-license-status-bar <?php echo esc_attr($summary['status']); ?>">
                        <div style="font-size:40px;"><?php echo $status_icon; ?></div>
                        <div>
                            <h3 style="margin:0 0 4px;color:var(--dox-text-primary);">
                                <?php
                                $labels = [
                                    'active'   => __('License Active', 'doken-ox-pro'),
                                    'inactive' => __('License Inactive', 'doken-ox-pro'),
                                    'expired'  => __('License Expired', 'doken-ox-pro'),
                                    'invalid'  => __('Invalid License', 'doken-ox-pro'),
                                ];
                                echo esc_html($labels[$summary['status']] ?? __('Unknown Status', 'doken-ox-pro'));
                                ?>
                            </h3>
                            <p style="margin:0;color:var(--dox-text-secondary);font-size:13px;">
                                <?php if (!empty($summary['plan'])) : ?>
                                    <?php printf( esc_html__('Plan: %s', 'doken-ox-pro'), esc_html($summary['plan'])); ?>
                                    <?php if (!empty($summary['expires_at'])) : ?> •
                                        <?php printf( esc_html__('Expires: %s', 'doken-ox-pro'), esc_html($summary['expires_at'])); ?>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <?php _e('Enter your license key below to activate.', 'doken-ox-pro'); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Key Details -->
                <?php if (!empty($summary['key'])) : ?>
                <div class="dox-card" style="margin-bottom:20px;">
                    <div class="dox-card-header">
                        <h3 class="dox-card-title"><?php _e('License Details', 'doken-ox-pro'); ?></h3>
                    </div>
                    <table class="dox-table">
                        <tr><td><?php _e('License Key', 'doken-ox-pro'); ?></td><td class="dox-mono" style="color:var(--dox-text-primary);"><?php echo esc_html($summary['key']); ?></td></tr>
                        <tr><td><?php _e('Status', 'doken-ox-pro'); ?></td><td><span class="dox-badge dox-badge-<?php echo esc_attr(Doken_Ox_License_Handler::get_status_color()); ?>"><?php echo esc_html(ucfirst($summary['status'])); ?></span></td></tr>
                        <tr><td><?php _e('Last Checked', 'doken-ox-pro'); ?></td><td><?php echo esc_html($summary['last_check']); ?></td></tr>
                        <?php if (!empty($summary['activations'])) : ?>
                        <tr><td><?php _e('Activations', 'doken-ox-pro'); ?></td><td><?php echo esc_html($summary['activations']['used'] . ' / ' . $summary['activations']['max']); ?></td></tr>
                        <?php endif; ?>
                    </table>

                    <div style="display:flex;gap:10px;margin-top:16px;">
                        <form method="post">
                            <?php wp_nonce_field('dox_license_action'); ?>
                            <input type="hidden" name="dox_license_action" value="check">
                            <button type="submit" class="dox-btn dox-btn-secondary dox-btn-sm" style="display:inline-flex;align-items:center;gap:4px;">
                                <span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;"></span> <?php _e('Refresh Status', 'doken-ox-pro'); ?>
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirm('<?php esc_attr_e('Deactivate license on this site?', 'doken-ox-pro'); ?>');">
                            <?php wp_nonce_field('dox_license_action'); ?>
                            <input type="hidden" name="dox_license_action" value="deactivate">
                            <button type="submit" class="dox-btn dox-btn-danger dox-btn-sm" style="display:inline-flex;align-items:center;gap:4px;">
                                <span class="dashicons dashicons-lock" style="font-size:14px;width:14px;height:14px;"></span> <?php _e('Deactivate', 'doken-ox-pro'); ?>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Activation Form -->
                <?php if ($summary['status'] !== 'active') : ?>
                <div class="dox-card">
                    <div class="dox-card-header">
                        <h3 class="dox-card-title"><?php _e('Activate License', 'doken-ox-pro'); ?></h3>
                    </div>
                    <form method="post">
                        <?php wp_nonce_field('dox_license_action'); ?>
                        <input type="hidden" name="dox_license_action" value="activate">
                        <div class="dox-form-group">
                            <label class="dox-label"><?php _e('License Key', 'doken-ox-pro'); ?></label>
                            <input type="text" name="dox_license_key" class="dox-input dox-mono"
                                   placeholder="XXXX-XXXX-XXXX-XXXX" required autocomplete="off">
                        </div>
                        <p style="font-size:12px;color:var(--dox-text-muted);margin-bottom:16px;">
                            <?php _e('Purchase a license at', 'doken-ox-pro'); ?>
                            <a href="https://oxtech.uk/doken-ox-pro" target="_blank" style="color:var(--dox-primary-light);">oxtech.uk/doken-ox-pro</a>
                        </p>
                        <button type="submit" class="dox-btn dox-btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                            <span class="dashicons dashicons-key" style="font-size:14px;width:14px;height:14px;"></span> <?php _e('Activate License', 'doken-ox-pro'); ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
