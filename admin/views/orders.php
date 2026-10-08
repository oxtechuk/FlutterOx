<?php
/**
 * Doken Ox Pro — Orders Control View
 * SpaceRemit SaaS Layout (Black Sidebar + White Cards + Coral Tabs)
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$status_filter = isset( $_GET['order_status'] ) ? sanitize_key( $_GET['order_status'] ) : '';
$paged         = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page      = 15;

$order_args = array(
    'limit'    => $per_page,
    'page'     => $paged,
    'orderby'  => 'date',
    'order'    => 'DESC',
    'paginate' => true,
);

if ( ! empty( $status_filter ) ) {
    $order_args['status'] = $status_filter;
}

$orders_results = array();
$total_orders   = 0;
$max_num_pages  = 1;

if ( function_exists( 'wc_get_orders' ) ) {
    $results = wc_get_orders( $order_args );
    if ( is_object( $results ) && isset( $results->orders ) ) {
        $orders_results = $results->orders;
        $total_orders   = $results->total;
        $max_num_pages  = $results->max_num_pages;
    } elseif ( is_array( $results ) ) {
        $orders_results = $results;
        $total_orders   = count( $results );
    }
}

// Safely calculate counts without undefined array key warnings
$wc_counts = function_exists( 'wc_count_orders' ) ? (array) wc_count_orders() : array();
$total_all_orders = 0;
foreach ( array( 'processing', 'completed', 'pending', 'on-hold', 'cancelled', 'refunded', 'failed' ) as $st_key ) {
    $total_all_orders += (int) ( $wc_counts[ $st_key ] ?? 0 );
}

$counts = array(
    'all'        => $total_all_orders,
    'processing' => (int) ( $wc_counts['processing'] ?? 0 ),
    'completed'  => (int) ( $wc_counts['completed'] ?? 0 ),
    'pending'    => (int) ( $wc_counts['pending'] ?? 0 ),
    'cancelled'  => (int) ( $wc_counts['cancelled'] ?? 0 ),
);

$current_user = wp_get_current_user();
$username     = $current_user->display_name ?: 'SPACEREMIT';
?>

<div class="dox-page">

    <!-- Top SpaceRemit Header -->
    <div class="dox-header">
        <div style="font-size:22px;font-weight:800;color:var(--dox-text-primary);letter-spacing:-0.5px;">
            <?php _e( 'Orders', 'doken-ox-pro' ); ?>
        </div>

        <div class="dox-header-actions">
            <?php if ( function_exists( 'wc_get_endpoint_url' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>" class="dox-btn dox-btn-secondary" style="padding:6px 12px;font-size:12px;">
                    <span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;"></span>
                    <?php _e( 'Native WC Orders', 'doken-ox-pro' ); ?>
                </a>
            <?php endif; ?>

            <a href="<?php echo esc_url( admin_url( 'admin.php?page=doken-ox-pro-orders' ) ); ?>" class="dox-notif-btn" title="<?php esc_attr_e( 'Orders & Notifications', 'doken-ox-pro' ); ?>">
                <span class="dashicons dashicons-bell" style="font-size:18px;"></span>
                <span class="dox-notif-badge-pill"><?php echo max( 1, $counts['processing'] % 99 ); ?></span>
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

            <!-- Subtitle -->
            <p style="font-size:13.5px;color:var(--dox-text-muted);margin:0 0 24px;">
                <?php _e( 'Monitor customer purchases across web and Flutter mobile app.', 'doken-ox-pro' ); ?>
            </p>

            <!-- Stats Bar -->
            <div class="dox-stats-grid">
                <div class="dox-stat-card">
                    <div class="dox-stat-header">
                        <span class="dox-stat-label"><?php _e( 'Total Orders', 'doken-ox-pro' ); ?></span>
                        <div class="dox-stat-icon primary"><span class="dashicons dashicons-cart"></span></div>
                    </div>
                    <div class="dox-stat-value"><?php echo number_format_i18n( $counts['all'] ); ?></div>
                </div>

                <div class="dox-stat-card">
                    <div class="dox-stat-header">
                        <span class="dox-stat-label"><?php _e( 'Processing', 'doken-ox-pro' ); ?></span>
                        <div class="dox-stat-icon warning"><span class="dashicons dashicons-update"></span></div>
                    </div>
                    <div class="dox-stat-value"><?php echo number_format_i18n( $counts['processing'] ); ?></div>
                </div>

                <div class="dox-stat-card">
                    <div class="dox-stat-header">
                        <span class="dox-stat-label"><?php _e( 'Completed', 'doken-ox-pro' ); ?></span>
                        <div class="dox-stat-icon success"><span class="dashicons dashicons-yes-alt"></span></div>
                    </div>
                    <div class="dox-stat-value"><?php echo number_format_i18n( $counts['completed'] ); ?></div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="dox-card" style="padding:14px 20px;margin-bottom:20px;">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <?php
                    $tabs = array(
                        ''           => __( 'All', 'doken-ox-pro' ),
                        'processing' => __( 'Processing', 'doken-ox-pro' ),
                        'completed'  => __( 'Completed', 'doken-ox-pro' ),
                        'pending'    => __( 'Pending Payment', 'doken-ox-pro' ),
                        'cancelled'  => __( 'Cancelled', 'doken-ox-pro' ),
                    );
                    foreach ( $tabs as $k => $label ) :
                        $is_active = ( $status_filter === $k );
                        $url = admin_url( 'admin.php?page=doken-ox-pro-orders' . ( $k ? '&order_status=' . $k : '' ) );
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="dox-btn <?php echo $is_active ? 'dox-btn-primary' : 'dox-btn-secondary'; ?> dox-btn-pill" style="padding:7px 18px;font-size:12px;">
                            <?php echo esc_html( $label ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Orders Table Card -->
            <div class="dox-card" style="padding:24px;">
                <?php if ( empty( $orders_results ) ) : ?>
                    <div style="text-align:center;padding:48px;color:var(--dox-text-muted);">
                        <span class="dashicons dashicons-cart" style="font-size:42px;width:42px;height:42px;opacity:0.3;margin-bottom:12px;"></span>
                        <p><?php _e( 'No orders found matching this filter.', 'doken-ox-pro' ); ?></p>
                    </div>
                <?php else : ?>
                    <div style="overflow-x:auto;">
                        <table class="dox-table">
                            <thead>
                                <tr>
                                    <th><?php _e( 'Order ID', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Customer', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Date', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Items', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Total', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Status', 'doken-ox-pro' ); ?></th>
                                    <th style="text-align:right;"><?php _e( 'Actions', 'doken-ox-pro' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $orders_results as $order ) :
                                    $oid = $order->get_id();
                                    $st  = $order->get_status();
                                    $badge = 'dox-badge-muted';
                                    if ( in_array( $st, array( 'completed', 'processing' ), true ) ) $badge = 'dox-badge-success';
                                    if ( in_array( $st, array( 'pending', 'on-hold' ), true ) )    $badge = 'dox-badge-warning';
                                    if ( in_array( $st, array( 'cancelled', 'failed' ), true ) )   $badge = 'dox-badge-danger';

                                    $cust_name = $order->get_billing_first_name() ? ( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) : __( 'Guest', 'doken-ox-pro' );
                                    $date_str  = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'M j, Y H:i' ) : '-';
                                    $edit_url  = admin_url( 'post.php?post=' . $oid . '&action=edit' );
                                ?>
                                    <tr>
                                        <td class="dox-td-primary">
                                            <a href="<?php echo esc_url( $edit_url ); ?>" style="color:var(--dox-primary);text-decoration:none;font-weight:700;">
                                                #<?php echo esc_html( $oid ); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;color:var(--dox-text-primary);"><?php echo esc_html( $cust_name ); ?></div>
                                            <div style="font-size:11px;color:var(--dox-text-muted);"><?php echo esc_html( $order->get_billing_email() ); ?></div>
                                        </td>
                                        <td><span style="font-size:12px;color:var(--dox-text-muted);"><?php echo esc_html( $date_str ); ?></span></td>
                                        <td><?php echo esc_html( $order->get_item_count() ); ?> items</td>
                                        <td style="font-weight:700;color:var(--dox-text-primary);">
                                            <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
                                        </td>
                                        <td>
                                            <span class="dox-badge <?php echo esc_attr( $badge ); ?>">
                                                <?php echo esc_html( ucfirst( $st ) ); ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="<?php echo esc_url( $edit_url ); ?>" class="dox-btn dox-btn-secondary" style="padding:4px 10px;font-size:11px;display:inline-flex;align-items:center;gap:2px;">
                                                <?php _e( 'Details', 'doken-ox-pro' ); ?> <span class="dashicons dashicons-arrow-right-alt2" style="font-size:12px;width:12px;height:12px;"></span>
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
