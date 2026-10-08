<?php
/**
 * Doken Ox Pro — Mobile Page Builder View
 * Drag & Drop visual page builder for Flutter App Home Layout.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'dox_home_sections';

// Process Actions (Add / Edit / Delete / Reorder)
$notice = null;
$notice_type = 'success';

if ( isset( $_POST['dox_action'] ) && check_admin_referer( 'dox_home_action', 'dox_home_nonce' ) ) {
    $action = sanitize_text_field( $_POST['dox_action'] );

    if ( $action === 'add_section' ) {
        $section_type = sanitize_key( $_POST['section_type'] ?? 'slider' );
        $title_en     = sanitize_text_field( $_POST['title_en'] ?? 'New Section' );
        $title_ar     = sanitize_text_field( $_POST['title_ar'] ?? '' );
        $media_id     = ! empty( $_POST['media_id'] ) ? intval( $_POST['media_id'] ) : null;
        $link_url     = esc_url_raw( $_POST['link_url'] ?? '' );

        $extra_data = array(
            'title_en'    => $title_en,
            'title_ar'    => $title_ar,
            'item_limit'  => intval( $_POST['item_limit'] ?? 8 ),
            'layout_type' => sanitize_text_field( $_POST['layout_type'] ?? 'grid' ),
        );

        $wpdb->insert(
            $table,
            array(
                'section'    => $section_type,
                'title'      => $title_en,
                'subtitle'   => $title_ar,
                'media_id'   => $media_id,
                'link_url'   => $link_url,
                'data'       => maybe_serialize( $extra_data ),
                'sort_order' => (int) $wpdb->get_var( "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {$table}" ),
                'status'     => 1,
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d' )
        );

        if ( function_exists( 'dox_home_bump_cache' ) ) {
            dox_home_bump_cache();
        }
        $notice = __( 'New section added to mobile layout.', 'doken-ox-pro' );
    } elseif ( $action === 'delete_section' ) {
        $del_id = intval( $_POST['section_id'] ?? 0 );
        if ( $del_id > 0 ) {
            $wpdb->delete( $table, array( 'id' => $del_id ), array( '%d' ) );
            if ( function_exists( 'dox_home_bump_cache' ) ) {
                dox_home_bump_cache();
            }
            $notice = __( 'Section removed from mobile layout.', 'doken-ox-pro' );
        }
    } elseif ( $action === 'reorder_sections' ) {
        $order = json_decode( stripslashes( $_POST['order_data'] ?? '[]' ), true );
        if ( is_array( $order ) ) {
            foreach ( $order as $idx => $id ) {
                $wpdb->update( $table, array( 'sort_order' => (int) $idx ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
            }
            if ( function_exists( 'dox_home_bump_cache' ) ) {
                dox_home_bump_cache();
            }
            $notice = __( 'Layout order saved.', 'doken-ox-pro' );
        }
    }
}

// Fetch all sections ordered by sort_order
$sections = array();
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
    $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC" );
    foreach ( (array) $rows as $row ) {
        $d = maybe_unserialize( $row->data );
        $sections[] = array(
            'id'         => (int) $row->id,
            'type'       => $row->section,
            'title'      => $row->title ?: ( $d['title_en'] ?? 'Untitled' ),
            'subtitle'   => $row->subtitle ?: ( $d['title_ar'] ?? '' ),
            'media_url'  => $row->media_id ? wp_get_attachment_url( (int) $row->media_id ) : '',
            'media_id'   => $row->media_id,
            'link_url'   => $row->link_url,
            'sort_order' => (int) $row->sort_order,
            'status'     => (int) $row->status,
            'data'       => $d,
        );
    }
}

// Fallback seed if table is empty
if ( empty( $sections ) ) {
    $default_sections = array(
        array( 'type' => 'slider',     'title' => 'Main Hero Banners',    'icon' => 'dashicons-images-alt2' ),
        array( 'type' => 'categories', 'title' => 'Popular Categories',   'icon' => 'dashicons-grid-view' ),
        array( 'type' => 'flash_deal', 'title' => 'Flash Sale (Limited)', 'icon' => 'dashicons-clock' ),
        array( 'type' => 'products',   'title' => 'Trending Products',    'icon' => 'dashicons-cart' ),
        array( 'type' => 'vendors',    'title' => 'Top Rated Stores',     'icon' => 'dashicons-store' ),
    );
    foreach ( $default_sections as $i => $s ) {
        $wpdb->insert(
            $table,
            array(
                'section'    => $s['type'],
                'title'      => $s['title'],
                'subtitle'   => '',
                'sort_order' => $i,
                'status'     => 1,
            ),
            array( '%s', '%s', '%s', '%d', '%d' )
        );
    }
    // Re-fetch
    $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC" );
    foreach ( (array) $rows as $row ) {
        $sections[] = array(
            'id'         => (int) $row->id,
            'type'       => $row->section,
            'title'      => $row->title,
            'subtitle'   => $row->subtitle,
            'media_url'  => '',
            'media_id'   => null,
            'link_url'   => '',
            'sort_order' => (int) $row->sort_order,
            'status'     => (int) $row->status,
            'data'       => maybe_unserialize( $row->data ),
        );
    }
}

$section_types = array(
    'slider'     => array( 'label' => __( 'Hero Carousel / Slider', 'doken-ox-pro' ),   'icon' => 'dashicons-images-alt2', 'desc' => 'High-impact full width banners' ),
    'categories' => array( 'label' => __( 'Category Circles / Grid', 'doken-ox-pro' ),  'icon' => 'dashicons-grid-view',   'desc' => 'Quick taxonomy navigation' ),
    'products'   => array( 'label' => __( 'Product Grid / Carousel', 'doken-ox-pro' ),  'icon' => 'dashicons-cart',        'desc' => 'Featured, latest or discounted items' ),
    'flash_deal' => array( 'label' => __( 'Flash Sale Countdown', 'doken-ox-pro' ),    'icon' => 'dashicons-clock',       'desc' => 'Limited time discounts with timer' ),
    'vendors'    => array( 'label' => __( 'Top Store Vendors', 'doken-ox-pro' ),       'icon' => 'dashicons-store',       'desc' => 'Featured Dokan store badges' ),
    'banner'     => array( 'label' => __( 'Promo Single Banner', 'doken-ox-pro' ),     'icon' => 'dashicons-megaphone',   'desc' => 'Static promotion / coupon callout' ),
);

$app_config = class_exists( 'Doken_Ox_App_Config' ) ? Doken_Ox_App_Config::get_full_config() : array();
$primary_color = $app_config['colors']['primary'] ?? '#6366F1';
$current_user  = wp_get_current_user();
$username      = $current_user->display_name ?: 'SPACEREMIT';
?>

<!-- Load SortableJS for smooth drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<div class="dox-page">

    <!-- Top SpaceRemit Header -->
    <div class="dox-header">
        <div style="font-size:22px;font-weight:800;color:var(--dox-text-primary);letter-spacing:-0.5px;">
            <?php _e( 'Page Builder', 'doken-ox-pro' ); ?>
        </div>

        <div class="dox-header-actions">
            <button type="button" class="dox-btn dox-btn-primary" onclick="openAddSectionModal();" style="padding:7px 16px;font-size:12px;">
                <span class="dashicons dashicons-plus-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                <?php _e( 'Add Section', 'doken-ox-pro' ); ?>
            </button>

            <a href="<?php echo esc_url( rest_url( 'mvapp/v1/home' ) ); ?>" target="_blank" class="dox-header-icon-btn" title="<?php esc_attr_e( 'Inspect Live Home JSON API', 'doken-ox-pro' ); ?>">
                <span class="dashicons dashicons-rest-api" style="font-size:14px;width:14px;height:14px;"></span>
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
            <?php if ( $notice ) : ?>
                <div class="dox-card" style="padding:14px 20px;border-left:4px solid var(--dox-success);margin-bottom:24px;display:flex;align-items:center;gap:12px;">
                    <span class="dashicons dashicons-yes-alt" style="color:var(--dox-success);font-size:20px;"></span>
                    <span style="font-size:13px;font-weight:600;color:var(--dox-text-primary);"><?php echo esc_html( $notice ); ?></span>
                </div>
            <?php endif; ?>

            <!-- 2-Column Builder Grid: Active Layout Stack | Live Phone Preview -->
            <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;">

                <!-- Left Column: Active Layout Stack & Component Shelf -->
                <div>

                    <!-- Component Quick Shelf -->
                    <div class="dox-card" style="margin-bottom:20px;">
                        <div class="dox-card-header">
                            <h3 class="dox-card-title">
                                <span class="dashicons dashicons-screenoptions"></span>
                                <?php _e( 'Available Components', 'doken-ox-pro' ); ?>
                            </h3>
                            <span style="font-size:11px;color:var(--dox-text-muted);"><?php _e( 'Click to insert into layout', 'doken-ox-pro' ); ?></span>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px;">
                            <?php foreach ( $section_types as $st_key => $st_data ) : ?>
                                <div onclick="quickAddSection('<?php echo esc_js( $st_key ); ?>', '<?php echo esc_js( $st_data['label'] ); ?>');" style="padding:12px;background:var(--dox-glass);border:1px solid var(--dox-glass-border);border-radius:var(--dox-radius);cursor:pointer;display:flex;align-items:center;gap:10px;transition:all .2s ease;" onmouseenter="this.style.borderColor='var(--dox-primary)';this.style.background='var(--dox-card-hover)';" onmouseleave="this.style.borderColor='var(--dox-glass-border)';this.style.background='var(--dox-glass)';">
                                    <div style="width:34px;height:34px;border-radius:8px;background:var(--dox-primary-glow);display:flex;align-items:center;justify-content:center;color:var(--dox-primary-light);">
                                        <span class="dashicons <?php echo esc_attr( $st_data['icon'] ); ?>"></span>
                                    </div>
                                    <div style="overflow:hidden;">
                                        <div style="font-size:12.5px;font-weight:600;color:var(--dox-text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                            <?php echo esc_html( $st_data['label'] ); ?>
                                        </div>
                                        <div style="font-size:10.5px;color:var(--dox-text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                            <?php echo esc_html( $st_data['desc'] ); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Active Stack with Drag & Drop Reordering -->
                    <div class="dox-card">
                        <div class="dox-card-header">
                            <div>
                                <h3 class="dox-card-title">
                                    <span class="dashicons dashicons-menu"></span>
                                    <?php _e( 'Active Mobile Screen Layout', 'doken-ox-pro' ); ?>
                                </h3>
                                <p style="font-size:11.5px;color:var(--dox-text-muted);margin:4px 0 0;">
                                    <?php _e( 'Drag handles to re-order. Changes sync with /mvapp/v1/home instantly.', 'doken-ox-pro' ); ?>
                                </p>
                            </div>

                            <form method="post" id="dox-reorder-form">
                                <?php wp_nonce_field( 'dox_home_action', 'dox_home_nonce' ); ?>
                                <input type="hidden" name="dox_action" value="reorder_sections">
                                <input type="hidden" name="order_data" id="dox-order-data">
                                <button type="submit" class="dox-btn dox-btn-primary" style="padding:6px 14px;font-size:12px;">
                                    <span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;"></span>
                                    <?php _e( 'Save Order', 'doken-ox-pro' ); ?>
                                </button>
                            </form>
                        </div>

                        <div id="dox-sections-sortable" style="display:flex;flex-direction:column;gap:12px;">
                            <?php foreach ( $sections as $s ) :
                                $type_info = $section_types[ $s['type'] ] ?? array( 'label' => ucfirst( $s['type'] ), 'icon' => 'dashicons-layout' );
                            ?>
                                <div class="dox-section-item" data-id="<?php echo esc_attr( $s['id'] ); ?>" data-type="<?php echo esc_attr( $s['type'] ); ?>" style="padding:14px 18px;background:var(--dox-card);border:1px solid var(--dox-border);border-radius:var(--dox-radius);display:flex;align-items:center;justify-content:space-between;cursor:grab;transition:all .2s ease;">
                                    <div style="display:flex;align-items:center;gap:14px;">
                                        <div style="color:var(--dox-text-muted);cursor:grab;">
                                            <span class="dashicons dashicons-menu" style="font-size:18px;"></span>
                                        </div>
                                        <div style="width:36px;height:36px;border-radius:8px;background:var(--dox-primary-glow);display:flex;align-items:center;justify-content:center;color:var(--dox-primary-light);">
                                            <span class="dashicons <?php echo esc_attr( $type_info['icon'] ); ?>"></span>
                                        </div>
                                        <div>
                                            <div style="font-size:14px;font-weight:700;color:var(--dox-text-primary);">
                                                <?php echo esc_html( $s['title'] ); ?>
                                            </div>
                                            <div style="font-size:11.5px;color:var(--dox-text-muted);display:flex;gap:10px;align-items:center;margin-top:2px;">
                                                <span class="dox-badge dox-badge-primary" style="font-size:9px;padding:1px 6px;">
                                                    <?php echo esc_html( $type_info['label'] ); ?>
                                                </span>
                                                <?php if ( ! empty( $s['subtitle'] ) ) : ?>
                                                    <span><?php echo esc_html( $s['subtitle'] ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <!-- Delete button -->
                                        <form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this section from mobile app?', 'doken-ox-pro' ) ); ?>');" style="margin:0;">
                                            <?php wp_nonce_field( 'dox_home_action', 'dox_home_nonce' ); ?>
                                            <input type="hidden" name="dox_action" value="delete_section">
                                            <input type="hidden" name="section_id" value="<?php echo esc_attr( $s['id'] ); ?>">
                                            <button type="submit" class="dox-header-icon-btn" style="width:30px;height:30px;color:var(--dox-danger);" title="<?php esc_attr_e( 'Delete Section', 'doken-ox-pro' ); ?>">
                                                <span class="dashicons dashicons-trash" style="font-size:14px;"></span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Live Phone Mockup Preview -->
                <div>
                    <div class="dox-card" style="position:sticky;top:100px;padding:16px;background:var(--dox-sidebar);border-color:var(--dox-border);">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <div style="font-size:13px;font-weight:700;color:var(--dox-text-primary);display:flex;align-items:center;gap:6px;">
                                <span class="dashicons dashicons-smartphone"></span>
                                <?php _e( 'Live App Preview', 'doken-ox-pro' ); ?>
                            </div>
                            <span class="dox-badge dox-badge-success" style="font-size:9px;">Live</span>
                        </div>

                        <!-- Phone Shell -->
                        <div style="width:300px;margin:0 auto;background:#000000;border-radius:44px;padding:12px;box-shadow:0 25px 60px rgba(0,0,0,0.8), 0 0 30px var(--dox-primary-glow);border:4px solid #252840;">
                            <!-- Phone Notch & Screen -->
                            <div style="width:100%;height:580px;background:#0F1120;border-radius:34px;overflow-y:auto;overflow-x:hidden;display:flex;flex-direction:column;position:relative;">

                                <!-- Top Phone Status Bar -->
                                <div style="height:28px;display:flex;justify-content:space-between;align-items:center;padding:0 20px;font-size:10px;font-weight:700;color:#94A3B8;flex-shrink:0;">
                                    <span>9:41</span>
                                    <div style="width:60px;height:12px;background:#000;border-radius:10px;"></div>
                                    <div style="display:flex;gap:4px;align-items:center;">
                                        <span class="dashicons dashicons-performance" style="font-size:10px;width:10px;height:10px;"></span>
                                    </div>
                                </div>

                                <!-- Mobile Header / Brand Bar -->
                                <div style="padding:10px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #1E2035;flex-shrink:0;">
                                    <div style="font-size:14px;font-weight:800;color:#FFFFFF;letter-spacing:-0.3px;">
                                        <?php echo esc_html( $app_config['brand']['app_name'] ?? 'MOSHINA' ); ?>
                                    </div>
                                    <div style="display:flex;gap:8px;align-items:center;color:#94A3B8;">
                                        <span class="dashicons dashicons-search" style="font-size:14px;width:14px;height:14px;"></span>
                                        <span class="dashicons dashicons-cart" style="font-size:14px;width:14px;height:14px;color:<?php echo esc_attr($primary_color); ?>;"></span>
                                    </div>
                                </div>

                                <!-- Dynamic Preview Screen Stack -->
                                <div id="dox-phone-preview-stack" style="padding:12px 14px;display:flex;flex-direction:column;gap:14px;flex:1;">
                                    <?php foreach ( $sections as $s ) : ?>
                                        <?php if ( $s['type'] === 'slider' ) : ?>
                                            <!-- Hero Banner Slider Mock -->
                                            <div style="height:120px;border-radius:14px;background:linear-gradient(135deg, <?php echo esc_attr($primary_color); ?>, #A855F7);padding:14px;display:flex;flex-direction:column;justify-content:flex-end;color:white;box-shadow:0 8px 20px rgba(99,102,241,0.3);">
                                                <div style="font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:0.8;font-weight:700;">Special Deal</div>
                                                <div style="font-size:14px;font-weight:800;line-height:1.2;"><?php echo esc_html( $s['title'] ); ?></div>
                                            </div>
                                        <?php elseif ( $s['type'] === 'categories' ) : ?>
                                            <!-- Categories Circles Mock -->
                                            <div>
                                                <div style="font-size:11px;font-weight:700;color:#F1F5F9;margin-bottom:8px;"><?php echo esc_html( $s['title'] ); ?></div>
                                                <div style="display:flex;gap:8px;overflow-x:auto;">
                                                    <?php for ( $ci = 1; $ci <= 4; $ci++ ) : ?>
                                                        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;flex-shrink:0;">
                                                            <div style="width:44px;height:44px;border-radius:50%;background:#1E2035;border:1px solid #252840;display:flex;align-items:center;justify-content:center;color:#818CF8;">
                                                                <span class="dashicons dashicons-tag" style="font-size:16px;"></span>
                                                            </div>
                                                            <span style="font-size:9px;color:#94A3B8;">Cat <?php echo $ci; ?></span>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        <?php elseif ( $s['type'] === 'products' || $s['type'] === 'flash_deal' ) : ?>
                                            <!-- Products 2-Col Mock -->
                                            <div>
                                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                                    <span style="font-size:11px;font-weight:700;color:#F1F5F9;"><?php echo esc_html( $s['title'] ); ?></span>
                                                    <span style="font-size:9px;color:<?php echo esc_attr($primary_color); ?>;font-weight:600;">See All</span>
                                                </div>
                                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                                    <div style="background:#161929;border:1px solid #252840;border-radius:10px;padding:8px;">
                                                        <div style="height:65px;background:#1E2035;border-radius:6px;margin-bottom:6px;"></div>
                                                        <div style="font-size:10px;font-weight:600;color:#F1F5F9;">Item Alpha</div>
                                                        <div style="font-size:9.5px;color:<?php echo esc_attr($primary_color); ?>;font-weight:700;">$49.00</div>
                                                    </div>
                                                    <div style="background:#161929;border:1px solid #252840;border-radius:10px;padding:8px;">
                                                        <div style="height:65px;background:#1E2035;border-radius:6px;margin-bottom:6px;"></div>
                                                        <div style="font-size:10px;font-weight:600;color:#F1F5F9;">Item Beta</div>
                                                        <div style="font-size:9.5px;color:<?php echo esc_attr($primary_color); ?>;font-weight:700;">$89.00</div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif ( $s['type'] === 'vendors' ) : ?>
                                            <!-- Vendors Mock -->
                                            <div>
                                                <div style="font-size:11px;font-weight:700;color:#F1F5F9;margin-bottom:8px;"><?php echo esc_html( $s['title'] ); ?></div>
                                                <div style="background:#161929;border:1px solid #252840;border-radius:10px;padding:8px;display:flex;align-items:center;gap:8px;">
                                                    <div style="width:30px;height:30px;border-radius:6px;background:var(--dox-warning-bg);display:flex;align-items:center;justify-content:center;color:var(--dox-warning);">
                                                        <span class="dashicons dashicons-store" style="font-size:14px;"></span>
                                                    </div>
                                                    <div>
                                                        <div style="font-size:10px;font-weight:700;color:#FFF;">Apex Motors Store</div>
                                                        <div style="font-size:8.5px;color:#10B981;display:flex;align-items:center;gap:3px;"><span class="dashicons dashicons-star-filled" style="font-size:10px;width:10px;height:10px;"></span> 4.9 (120 reviews)</div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else : ?>
                                            <!-- Generic Banner Mock -->
                                            <div style="height:60px;background:#161929;border:1px dashed #252840;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#94A3B8;">
                                                <?php echo esc_html( $s['title'] ); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Bottom Mobile App Nav Bar -->
                                <div style="height:48px;background:#10121C;border-top:1px solid #1E2035;display:flex;justify-content:space-around;align-items:center;color:#64748B;font-size:9px;flex-shrink:0;">
                                    <div style="color:<?php echo esc_attr($primary_color); ?>;display:flex;flex-direction:column;align-items:center;">
                                        <span class="dashicons dashicons-admin-home" style="font-size:15px;width:15px;height:15px;"></span>
                                        <span>Home</span>
                                    </div>
                                    <div style="display:flex;flex-direction:column;align-items:center;">
                                        <span class="dashicons dashicons-search" style="font-size:15px;width:15px;height:15px;"></span>
                                        <span>Explore</span>
                                    </div>
                                    <div style="display:flex;flex-direction:column;align-items:center;">
                                        <span class="dashicons dashicons-cart" style="font-size:15px;width:15px;height:15px;"></span>
                                        <span>Cart</span>
                                    </div>
                                    <div style="display:flex;flex-direction:column;align-items:center;">
                                        <span class="dashicons dashicons-admin-users" style="font-size:15px;width:15px;height:15px;"></span>
                                        <span>Profile</span>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </main>
    </div>
</div>

<!-- Modal: Add Section -->
<div id="dox-add-section-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(5px);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--dox-card);border:1px solid var(--dox-border);border-radius:var(--dox-radius-xl);width:90%;max-width:540px;padding:28px;box-shadow:var(--dox-shadow-lg);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--dox-border-subtle);">
            <h3 style="margin:0;font-size:17px;font-weight:700;color:var(--dox-text-primary);">
                <?php _e( 'Add Section to Mobile Layout', 'doken-ox-pro' ); ?>
            </h3>
            <button type="button" onclick="closeAddSectionModal();" style="background:none;border:none;color:var(--dox-text-muted);cursor:pointer;padding:4px;display:flex;align-items:center;"><span class="dashicons dashicons-no-alt" style="font-size:18px;width:18px;height:18px;"></span></button>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field( 'dox_home_action', 'dox_home_nonce' ); ?>
            <input type="hidden" name="dox_action" value="add_section">

            <div style="margin-bottom:16px;">
                <label class="dox-form-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--dox-text-primary);">
                    <?php _e( 'Component Type', 'doken-ox-pro' ); ?>
                </label>
                <select name="section_type" id="modal-section-type" class="dox-input" style="width:100%;padding:10px;background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:var(--dox-radius);color:var(--dox-text-primary);">
                    <?php foreach ( $section_types as $k => $st ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $st['label'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:16px;">
                <label class="dox-form-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--dox-text-primary);">
                    <?php _e( 'Title (English)', 'doken-ox-pro' ); ?>
                </label>
                <input type="text" name="title_en" id="modal-title-en" class="dox-input" style="width:100%;padding:10px;background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:var(--dox-radius);color:var(--dox-text-primary);" required>
            </div>

            <div style="margin-bottom:16px;">
                <label class="dox-form-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--dox-text-primary);">
                    <?php _e( 'Title (Arabic)', 'doken-ox-pro' ); ?>
                </label>
                <input type="text" name="title_ar" id="modal-title-ar" class="dox-input" style="width:100%;padding:10px;background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:var(--dox-radius);color:var(--dox-text-primary);" dir="rtl">
            </div>

            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                <button type="button" onclick="closeAddSectionModal();" class="dox-btn dox-btn-secondary">
                    <?php _e( 'Cancel', 'doken-ox-pro' ); ?>
                </button>
                <button type="submit" class="dox-btn dox-btn-primary">
                    <?php _e( 'Create Section', 'doken-ox-pro' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Init SortableJS on active sections list
document.addEventListener('DOMContentLoaded', function () {
    var sortableEl = document.getElementById('dox-sections-sortable');
    if (sortableEl && typeof Sortable !== 'undefined') {
        Sortable.create(sortableEl, {
            animation: 180,
            ghostClass: 'dox-sortable-ghost',
            onEnd: function () {
                var order = [];
                sortableEl.querySelectorAll('.dox-section-item').forEach(function (el) {
                    order.push(el.dataset.id);
                });
                document.getElementById('dox-order-data').value = JSON.stringify(order);
            }
        });
    }
});

function openAddSectionModal() {
    var modal = document.getElementById('dox-add-section-modal');
    modal.style.display = 'flex';
}

function closeAddSectionModal() {
    var modal = document.getElementById('dox-add-section-modal');
    modal.style.display = 'none';
}

function quickAddSection(type, label) {
    document.getElementById('modal-section-type').value = type;
    document.getElementById('modal-title-en').value = label;
    openAddSectionModal();
}
</script>
