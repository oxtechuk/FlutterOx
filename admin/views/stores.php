<?php
/**
 * Doken Ox Pro — Stores & Vendors Management View
 * Dokan Multi-Vendor marketplace hub in SpaceRemit dark styling.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_dokan = class_exists( 'Doken_Ox_Plugin_Mode' ) && Doken_Ox_Plugin_Mode::is_dokan_mode();

// Fetch vendors
$vendors = array();
if ( $is_dokan ) {
    $vendor_users = get_users( array(
        'role__in' => array( 'seller', 'vendor' ),
        'orderby'  => 'registered',
        'order'    => 'DESC',
        'number'   => 50,
    ) );

    foreach ( $vendor_users as $vu ) {
        $store_info = function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $vu->ID ) : array();
        $store_name = $store_info['store_name'] ?? ( $vu->display_name . "'s Store" );
        $phone      = $store_info['phone'] ?? '-';
        $gravatar   = get_avatar_url( $vu->ID, array( 'size' => 64 ) );
        $selling    = get_user_meta( $vu->ID, 'dokan_enable_selling', true );
        $is_active  = ( $selling === 'yes' || empty( $selling ) );

        $vendors[] = array(
            'id'         => $vu->ID,
            'name'       => $vu->display_name,
            'email'      => $vu->user_email,
            'store_name' => $store_name,
            'phone'      => $phone,
            'avatar'     => $gravatar,
            'status'     => $is_active ? 'active' : 'suspended',
            'registered' => date_i18n( 'M j, Y', strtotime( $vu->user_registered ) ),
        );
    }
}
?>

<div class="dox-page">

    <!-- Top SpaceRemit Header -->
    <div class="dox-header">
        <div style="font-size:22px;font-weight:800;color:var(--dox-text-primary);letter-spacing:-0.5px;">
            <?php _e( 'Stores & Vendors', 'doken-ox-pro' ); ?>
        </div>

        <div class="dox-header-actions">
            <?php if ( $is_dokan && function_exists( 'dokan_get_seller_url' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dokan' ) ); ?>" class="dox-btn dox-btn-secondary" style="padding:6px 12px;font-size:12px;">
                    <span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;"></span>
                    <?php _e( 'Dokan Dashboard', 'doken-ox-pro' ); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="dox-layout">

        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="dox-main">

            <p style="font-size:13.5px;color:var(--dox-text-muted);margin:0 0 24px;">
                <?php _e( 'Supervise marketplace sellers, store profiles, and mobile vendor APIs.', 'doken-ox-pro' ); ?>
            </p>

            <?php if ( ! $is_dokan ) : ?>
                <!-- Dokan Deactivated Notice -->
                <div class="dox-card" style="padding:40px;text-align:center;">
                    <div style="width:64px;height:64px;border-radius:16px;background:var(--dox-accent-glow);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:var(--dox-accent);">
                        <span class="dashicons dashicons-cart" style="font-size:32px;"></span>
                    </div>
                    <h3 style="font-size:18px;font-weight:700;color:var(--dox-text-primary);margin-bottom:8px;">
                        <?php _e( 'WooCommerce Only Mode is Active', 'doken-ox-pro' ); ?>
                    </h3>
                    <p style="color:var(--dox-text-secondary);max-width:500px;margin:0 auto 20px;font-size:13px;line-height:1.5;">
                        <?php _e( 'This WordPress site is currently running in single-store mode. Dokan multi-vendor endpoints and store management features are disabled to maximize speed and efficiency.', 'doken-ox-pro' ); ?>
                    </p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=doken-ox-pro-settings' ) ); ?>" class="dox-btn dox-btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                        <?php _e( 'Switch to WooCommerce + Dokan Mode', 'doken-ox-pro' ); ?> <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                    </a>
                </div>
            <?php else : ?>

                <!-- Stats Bar -->
                <div class="dox-stats-grid" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="dox-stat-card">
                        <div class="dox-stat-header">
                            <span class="dox-stat-label"><?php _e( 'Registered Stores', 'doken-ox-pro' ); ?></span>
                            <div class="dox-stat-icon warning"><span class="dashicons dashicons-store"></span></div>
                        </div>
                        <div class="dox-stat-value"><?php echo count( $vendors ); ?></div>
                    </div>

                    <div class="dox-stat-card">
                        <div class="dox-stat-header">
                            <span class="dox-stat-label"><?php _e( 'Mobile Vendor API', 'doken-ox-pro' ); ?></span>
                            <div class="dox-stat-icon success"><span class="dashicons dashicons-rest-api"></span></div>
                        </div>
                        <div class="dox-stat-value" style="font-size:18px;color:var(--dox-success);">
                            /mvapp/v1/vendors
                        </div>
                    </div>
                </div>

                <!-- Stores Table Card -->
                <div class="dox-card">
                    <div class="dox-card-header">
                        <h3 class="dox-card-title">
                            <span class="dashicons dashicons-store"></span>
                            <?php _e( 'Marketplace Vendors', 'doken-ox-pro' ); ?>
                        </h3>
                        <span class="dox-badge dox-badge-primary"><?php echo count( $vendors ); ?> active</span>
                    </div>

                    <?php if ( empty( $vendors ) ) : ?>
                        <div style="text-align:center;padding:48px;color:var(--dox-text-muted);">
                            <span class="dashicons dashicons-store" style="font-size:42px;width:42px;height:42px;opacity:0.3;margin-bottom:12px;"></span>
                            <p><?php _e( 'No vendors found on this marketplace yet.', 'doken-ox-pro' ); ?></p>
                        </div>
                    <?php else : ?>
                        <div style="overflow-x:auto;">
                            <table class="dox-table">
                                <thead>
                                    <tr>
                                        <th><?php _e( 'Store Name', 'doken-ox-pro' ); ?></th>
                                        <th><?php _e( 'Vendor Owner', 'doken-ox-pro' ); ?></th>
                                        <th><?php _e( 'Email', 'doken-ox-pro' ); ?></th>
                                        <th><?php _e( 'Phone', 'doken-ox-pro' ); ?></th>
                                        <th><?php _e( 'Status', 'doken-ox-pro' ); ?></th>
                                        <th><?php _e( 'Joined', 'doken-ox-pro' ); ?></th>
                                        <th style="text-align:right;"><?php _e( 'Actions', 'doken-ox-pro' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $vendors as $v ) :
                                        $user_edit = admin_url( 'user-edit.php?user_id=' . $v['id'] );
                                    ?>
                                        <tr>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:10px;">
                                                    <img src="<?php echo esc_url( $v['avatar'] ); ?>" alt="" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">
                                                    <div>
                                                        <div style="font-weight:700;color:var(--dox-text-primary);"><?php echo esc_html( $v['store_name'] ); ?></div>
                                                        <div style="font-size:11px;color:var(--dox-text-muted);">ID: #<?php echo esc_html( $v['id'] ); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="font-weight:600;color:var(--dox-text-primary);"><?php echo esc_html( $v['name'] ); ?></td>
                                            <td><?php echo esc_html( $v['email'] ); ?></td>
                                            <td><?php echo esc_html( $v['phone'] ); ?></td>
                                            <td>
                                                <span class="dox-badge <?php echo $v['status'] === 'active' ? 'dox-badge-success' : 'dox-badge-danger'; ?>">
                                                    <?php echo esc_html( ucfirst( $v['status'] ) ); ?>
                                                </span>
                                            </td>
                                            <td><span style="font-size:12px;color:var(--dox-text-muted);"><?php echo esc_html( $v['registered'] ); ?></span></td>
                                            <td style="text-align:right;">
                                                <a href="<?php echo esc_url( $user_edit ); ?>" class="dox-btn dox-btn-secondary" style="padding:4px 10px;font-size:11px;">
                                                    <?php _e( 'Edit', 'doken-ox-pro' ); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </main>
    </div>
</div>
