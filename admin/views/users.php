<?php
/**
 * Doken Ox Pro — Users & Customers Control View
 * SpaceRemit dark theme user registry.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$role_filter = isset( $_GET['role'] ) ? sanitize_key( $_GET['role'] ) : '';
$paged       = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

$user_args = array(
    'number'  => 50,
    'paged'   => $paged,
    'orderby' => 'registered',
    'order'   => 'DESC',
);

if ( ! empty( $role_filter ) ) {
    $user_args['role'] = $role_filter;
}

$users = get_users( $user_args );
$user_counts = count_users();
$total_all   = (int) ( $user_counts['total_users'] ?? 0 );
$total_cust  = (int) ( $user_counts['avail_roles']['customer'] ?? 0 );
$total_sell  = (int) ( $user_counts['avail_roles']['seller'] ?? 0 );
?>

<div class="dox-page">

    <!-- Top SpaceRemit Header -->
    <div class="dox-header">
        <div style="font-size:22px;font-weight:800;color:var(--dox-text-primary);letter-spacing:-0.5px;">
            <?php _e( 'Users & Customers', 'doken-ox-pro' ); ?>
        </div>

        <div class="dox-header-actions">
            <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="dox-btn dox-btn-primary" style="padding:6px 14px;font-size:12px;">
                <span class="dashicons dashicons-plus-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                <?php _e( 'Add User', 'doken-ox-pro' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="dox-btn dox-btn-secondary" style="padding:6px 12px;font-size:12px;">
                <span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;"></span>
                <?php _e( 'Native WP', 'doken-ox-pro' ); ?>
            </a>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="dox-layout">

        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="dox-main">

            <p style="font-size:13.5px;color:var(--dox-text-muted);margin:0 0 24px;">
                <?php _e( 'View registered accounts across WooCommerce and Flutter mobile authentication.', 'doken-ox-pro' ); ?>
            </p>

            <!-- Stats Bar -->
            <div class="dox-stats-grid" style="grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
                <div class="dox-stat-card">
                    <div class="dox-stat-header">
                        <span class="dox-stat-label"><?php _e( 'Total Users', 'doken-ox-pro' ); ?></span>
                        <div class="dox-stat-icon primary"><span class="dashicons dashicons-groups"></span></div>
                    </div>
                    <div class="dox-stat-value"><?php echo number_format_i18n( $total_all ); ?></div>
                </div>

                <div class="dox-stat-card">
                    <div class="dox-stat-header">
                        <span class="dox-stat-label"><?php _e( 'Customers', 'doken-ox-pro' ); ?></span>
                        <div class="dox-stat-icon success"><span class="dashicons dashicons-cart"></span></div>
                    </div>
                    <div class="dox-stat-value"><?php echo number_format_i18n( $total_cust ); ?></div>
                </div>

                <div class="dox-stat-card">
                    <div class="dox-stat-header">
                        <span class="dox-stat-label"><?php _e( 'Vendors / Sellers', 'doken-ox-pro' ); ?></span>
                        <div class="dox-stat-icon warning"><span class="dashicons dashicons-store"></span></div>
                    </div>
                    <div class="dox-stat-value"><?php echo number_format_i18n( $total_sell ); ?></div>
                </div>
            </div>

            <!-- Role Filter Bar -->
            <div class="dox-card" style="padding:16px;">
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php
                    $role_tabs = array(
                        ''              => __( 'All Accounts', 'doken-ox-pro' ),
                        'customer'      => __( 'Customers Only', 'doken-ox-pro' ),
                        'seller'        => __( 'Vendors Only', 'doken-ox-pro' ),
                        'administrator' => __( 'Administrators', 'doken-ox-pro' ),
                    );
                    foreach ( $role_tabs as $rk => $rlabel ) :
                        $active = ( $role_filter === $rk );
                        $rurl   = admin_url( 'admin.php?page=doken-ox-pro-users' . ( $rk ? '&role=' . $rk : '' ) );
                    ?>
                        <a href="<?php echo esc_url( $rurl ); ?>" class="dox-btn <?php echo $active ? 'dox-btn-primary' : 'dox-btn-secondary'; ?>" style="padding:6px 14px;font-size:12px;">
                            <?php echo esc_html( $rlabel ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Users Table Card -->
            <div class="dox-card">
                <?php if ( empty( $users ) ) : ?>
                    <div style="text-align:center;padding:48px;color:var(--dox-text-muted);">
                        <span class="dashicons dashicons-admin-users" style="font-size:42px;width:42px;height:42px;opacity:0.3;margin-bottom:12px;"></span>
                        <p><?php _e( 'No users found matching this filter.', 'doken-ox-pro' ); ?></p>
                    </div>
                <?php else : ?>
                    <div style="overflow-x:auto;">
                        <table class="dox-table">
                            <thead>
                                <tr>
                                    <th><?php _e( 'User', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Email', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Role', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Orders Placed', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Registered', 'doken-ox-pro' ); ?></th>
                                    <th style="text-align:right;"><?php _e( 'Actions', 'doken-ox-pro' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $users as $u ) :
                                    $uid     = $u->ID;
                                    $roles   = (array) $u->roles;
                                    $primary = reset( $roles ) ?: 'user';
                                    $avatar  = get_avatar_url( $uid, array( 'size' => 64 ) );
                                    $orders_count = function_exists( 'wc_get_customer_order_count' ) ? wc_get_customer_order_count( $uid ) : '-';
                                    $edit_u  = admin_url( 'user-edit.php?user_id=' . $uid );

                                    $role_badge = 'dox-badge-muted';
                                    if ( $primary === 'administrator' ) $role_badge = 'dox-badge-danger';
                                    if ( $primary === 'customer' )      $role_badge = 'dox-badge-success';
                                    if ( $primary === 'seller' )        $role_badge = 'dox-badge-warning';
                                ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <img src="<?php echo esc_url( $avatar ); ?>" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover;">
                                                <div>
                                                    <a href="<?php echo esc_url( $edit_u ); ?>" style="font-weight:700;color:var(--dox-text-primary);text-decoration:none;">
                                                        <?php echo esc_html( $u->display_name ); ?>
                                                    </a>
                                                    <div style="font-size:11px;color:var(--dox-text-muted);">@<?php echo esc_html( $u->user_login ); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html( $u->user_email ); ?></td>
                                        <td>
                                            <span class="dox-badge <?php echo esc_attr( $role_badge ); ?>" style="font-size:10px;">
                                                <?php echo esc_html( ucfirst( $primary ) ); ?>
                                            </span>
                                        </td>
                                        <td style="font-weight:600;color:var(--dox-text-primary);"><?php echo esc_html( $orders_count ); ?></td>
                                        <td><span style="font-size:12px;color:var(--dox-text-muted);"><?php echo date_i18n( 'M j, Y', strtotime( $u->user_registered ) ); ?></span></td>
                                        <td style="text-align:right;">
                                            <a href="<?php echo esc_url( $edit_u ); ?>" class="dox-btn dox-btn-secondary" style="padding:4px 10px;font-size:11px;">
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

        </main>
    </div>
</div>
