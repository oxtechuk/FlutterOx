<?php
/**
 * Doken Ox Pro — Products Control View
 * SpaceRemit dark theme catalog manager.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;
$stock_st = isset( $_GET['stock_status'] ) ? sanitize_key( $_GET['stock_status'] ) : '';

$args = array(
    'limit'    => $per_page,
    'page'     => $paged,
    'orderby'  => 'date',
    'order'    => 'DESC',
    'paginate' => true,
);

if ( ! empty( $stock_st ) ) {
    $args['stock_status'] = $stock_st;
}

$product_results = array();
$total_products  = 0;

if ( function_exists( 'wc_get_products' ) ) {
    $res = wc_get_products( $args );
    if ( is_object( $res ) && isset( $res->products ) ) {
        $product_results = $res->products;
        $total_products  = $res->total;
    } elseif ( is_array( $res ) ) {
        $product_results = $res;
        $total_products  = count( $res );
    }
}

// Counts
$total_published = 0;
if ( post_type_exists( 'product' ) ) {
    $c = wp_count_posts( 'product' );
    $total_published = (int) ( $c->publish ?? 0 );
}
?>

<div class="dox-page">

    <!-- Top SpaceRemit Header -->
    <div class="dox-header">
        <div style="font-size:22px;font-weight:800;color:var(--dox-text-primary);letter-spacing:-0.5px;">
            <?php _e( 'Products', 'doken-ox-pro' ); ?>
        </div>

        <div class="dox-header-actions">
            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="dox-btn dox-btn-primary" style="padding:6px 14px;font-size:12px;">
                <span class="dashicons dashicons-plus-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                <?php _e( 'Add Product', 'doken-ox-pro' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="dox-btn dox-btn-secondary" style="padding:6px 12px;font-size:12px;">
                <span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;"></span>
                <?php _e( 'Native WC', 'doken-ox-pro' ); ?>
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
                <?php _e( 'Manage synchronized inventory between store and mobile Flutter application.', 'doken-ox-pro' ); ?>
            </p>

            <!-- Stats Bar -->
            <div class="dox-stats-grid" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));">
                <div class="dox-stat-card">
                    <div class="dox-stat-header">
                        <span class="dox-stat-label"><?php _e( 'Live Products', 'doken-ox-pro' ); ?></span>
                        <div class="dox-stat-icon primary"><span class="dashicons dashicons-products"></span></div>
                    </div>
                    <div class="dox-stat-value"><?php echo number_format_i18n( $total_published ); ?></div>
                </div>

                <div class="dox-stat-card">
                    <div class="dox-stat-header">
                        <span class="dox-stat-label"><?php _e( 'Mobile Sync State', 'doken-ox-pro' ); ?></span>
                        <div class="dox-stat-icon success"><span class="dashicons dashicons-yes-alt"></span></div>
                    </div>
                    <div class="dox-stat-value" style="font-size:18px;color:var(--dox-success);">
                        <?php _e( 'Real-time via REST', 'doken-ox-pro' ); ?>
                    </div>
                </div>
            </div>

            <!-- Products Table Card -->
            <div class="dox-card">
                <?php if ( empty( $product_results ) ) : ?>
                    <div style="text-align:center;padding:48px;color:var(--dox-text-muted);">
                        <span class="dashicons dashicons-products" style="font-size:42px;width:42px;height:42px;opacity:0.3;margin-bottom:12px;"></span>
                        <p><?php _e( 'No products found.', 'doken-ox-pro' ); ?></p>
                    </div>
                <?php else : ?>
                    <div style="overflow-x:auto;">
                        <table class="dox-table">
                            <thead>
                                <tr>
                                    <th><?php _e( 'Product', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'SKU', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Price', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Stock', 'doken-ox-pro' ); ?></th>
                                    <th><?php _e( 'Type', 'doken-ox-pro' ); ?></th>
                                    <th style="text-align:right;"><?php _e( 'Actions', 'doken-ox-pro' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $product_results as $product ) :
                                    $pid     = $product->get_id();
                                    $img_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
                                    $stock   = $product->get_stock_status();
                                    $badge   = ( $stock === 'instock' ) ? 'dox-badge-success' : 'dox-badge-danger';
                                    $edit_url = admin_url( 'post.php?post=' . $pid . '&action=edit' );
                                ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:12px;">
                                                <?php if ( $img_url ) : ?>
                                                    <img src="<?php echo esc_url( $img_url ); ?>" alt="" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">
                                                <?php else : ?>
                                                    <div style="width:40px;height:40px;border-radius:8px;background:var(--dox-border);display:flex;align-items:center;justify-content:center;color:var(--dox-text-muted);">
                                                        <span class="dashicons dashicons-format-image"></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <a href="<?php echo esc_url( $edit_url ); ?>" style="font-weight:700;color:var(--dox-text-primary);text-decoration:none;">
                                                        <?php echo esc_html( $product->get_name() ); ?>
                                                    </a>
                                                    <div style="font-size:11px;color:var(--dox-text-muted);">ID: #<?php echo esc_html( $pid ); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-family:var(--dox-font-mono);font-size:12px;">
                                                <?php echo esc_html( $product->get_sku() ?: '-' ); ?>
                                            </span>
                                        </td>
                                        <td style="font-weight:700;color:var(--dox-text-primary);">
                                            <?php echo wp_kses_post( $product->get_price_html() ); ?>
                                        </td>
                                        <td>
                                            <span class="dox-badge <?php echo esc_attr( $badge ); ?>">
                                                <?php echo esc_html( ucfirst( $stock ) ); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="dox-badge dox-badge-muted" style="font-size:10px;">
                                                <?php echo esc_html( ucfirst( $product->get_type() ) ); ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="<?php echo esc_url( $edit_url ); ?>" class="dox-btn dox-btn-secondary" style="padding:4px 10px;font-size:11px;">
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
