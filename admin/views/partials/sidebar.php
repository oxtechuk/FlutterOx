<?php
/**
 * Shared Sidebar Navigation Partial (SpaceRemit Style)
 * Jet Black background (#0A0B0E), pill items, crisp white icons.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
$is_dokan     = class_exists( 'Doken_Ox_Plugin_Mode' ) && Doken_Ox_Plugin_Mode::is_dokan_mode();

function dox_nav_item( $page, $label, $icon, $current, $badge = '' ) {
    $active = ( $current === $page ) ? ' active' : '';
    $url    = admin_url( 'admin.php?page=' . $page );
    $badge_html = $badge ? '<span class="dox-badge" style="background:var(--dox-primary);color:white;font-size:9px;padding:2px 6px;">' . esc_html( $badge ) . '</span>' : '';
    echo '<a href="' . esc_url( $url ) . '" class="dox-nav-item' . $active . '">';
    echo '<span class="dashicons ' . esc_attr( $icon ) . '"></span>';
    echo '<span>' . esc_html( $label ) . '</span>';
    echo $badge_html;
    echo '</a>';
}
?>
<nav class="dox-sidebar-nav">

    <!-- Brand Header -->
    <div class="dox-sidebar-logo-wrap">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=doken-ox-pro' ) ); ?>" style="display:flex;align-items:center;text-decoration:none;width:100%;">
            <img src="<?php echo esc_url( DOKEN_OX_PRO_URL . 'assets/images/logo-hor.png' ); ?>" 
                 alt="<?php esc_attr_e( 'SpaceRemit Doken Ox Pro', 'doken-ox-pro' ); ?>" 
                 style="max-height: 38px; width: auto; max-width: 100%; object-fit: contain; display: block;">
        </a>
    </div>

    <div class="dox-sidebar-section">
        <?php dox_nav_item( 'doken-ox-pro', __( 'Dashboard', 'doken-ox-pro' ), 'dashicons-admin-home', $current_page ); ?>
    </div>

    <div class="dox-sidebar-section">
        <div class="dox-sidebar-label"><?php _e( 'Commerce', 'doken-ox-pro' ); ?></div>
        <?php dox_nav_item( 'doken-ox-pro-orders',   __( 'Orders', 'doken-ox-pro' ),   'dashicons-list-view', $current_page ); ?>
        <?php dox_nav_item( 'doken-ox-pro-products', __( 'Products', 'doken-ox-pro' ), 'dashicons-products',  $current_page ); ?>
        <?php if ( $is_dokan ) : ?>
            <?php dox_nav_item( 'doken-ox-pro-stores', __( 'Space Seller', 'doken-ox-pro' ), 'dashicons-store', $current_page ); ?>
        <?php endif; ?>
        <?php dox_nav_item( 'doken-ox-pro-users',    __( 'Profile & Users', 'doken-ox-pro' ), 'dashicons-admin-users', $current_page ); ?>
    </div>

    <div class="dox-sidebar-section">
        <div class="dox-sidebar-label"><?php _e( 'Mobile App', 'doken-ox-pro' ); ?></div>
        <?php dox_nav_item( 'doken-ox-pro-home',  __( 'Page Builder', 'doken-ox-pro' ), 'dashicons-layout', $current_page ); ?>
        <?php dox_nav_item( 'doken-ox-pro-theme', __( 'App Theme', 'doken-ox-pro' ),    'dashicons-art',    $current_page ); ?>
    </div>

    <div class="dox-sidebar-section">
        <div class="dox-sidebar-label"><?php _e( 'System', 'doken-ox-pro' ); ?></div>
        <?php dox_nav_item( 'doken-ox-pro-settings', __( 'Settings', 'doken-ox-pro' ),   'dashicons-admin-settings', $current_page ); ?>
        <?php dox_nav_item( 'doken-ox-pro-license',  __( 'License', 'doken-ox-pro' ),    'dashicons-admin-network',  $current_page ); ?>
        <?php dox_nav_item( 'doken-ox-pro-dev',      __( 'Developers', 'doken-ox-pro' ), 'dashicons-editor-code',    $current_page ); ?>
    </div>

    <!-- Bottom: Mode Badge & Setup Wizard Link -->
    <div style="padding:16px 12px;margin-top:auto;border-top:1px solid #14161E;background:var(--dox-sidebar);">
        <?php if ( class_exists( 'Doken_Ox_Plugin_Mode' ) ) : ?>
            <div style="font-size:9.5px;color:#525866;margin-bottom:6px;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Active Mode</div>
            <div style="font-size:11px;color:#FFFFFF;background:#16171E;padding:6px 10px;border-radius:8px;display:flex;align-items:center;gap:6px;border:1px solid #232530;">
                <span style="color:var(--dox-primary);font-size:12px;">●</span>
                <span style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html( Doken_Ox_Plugin_Mode::get_mode_label() ); ?></span>
            </div>
        <?php endif; ?>

        <a href="<?php echo esc_url( admin_url( 'admin.php?page=doken-ox-pro-wizard' ) ); ?>" style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:10px;padding:7px;background:#16171E;border-radius:8px;color:#9CA3AF;font-size:11px;text-decoration:none;border:1px solid #232530;" onmouseenter="this.style.color='#FFFFFF';this.style.background='#1E202A';" onmouseleave="this.style.color='#9CA3AF';this.style.background='#16171E';">
            <span class="dashicons dashicons-welcome-learn-more" style="font-size:14px;width:14px;height:14px;"></span>
            <?php _e( 'Run Setup Wizard', 'doken-ox-pro' ); ?>
        </a>

        <div style="font-size:10px;color:#525866;margin-top:8px;text-align:center;">v<?php echo esc_html( DOKEN_OX_PRO_VERSION ); ?></div>
    </div>

</nav>
