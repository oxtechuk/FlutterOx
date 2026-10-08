<?php
/**
 * App Theme Builder — Admin View
 * Allows admin to customize Flutter app colors, fonts, images, and features
 * with a live mobile phone preview.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$config   = Doken_Ox_App_Config::get_full_config();
$colors   = $config['colors']     ?? [];
$typo     = $config['typography'] ?? [];
$layout   = $config['layout']     ?? [];
$features = $config['features']   ?? [];
$contact  = $config['contact']    ?? [];
$integr   = $config['integrations'] ?? [];
$links    = $config['store_links'] ?? [];

$rest_url = esc_url_raw( rest_url( 'mvapp/v1/app/config' ) );
$nonce    = wp_create_nonce( 'wp_rest' );

// Active tab
$tab = isset( $_GET['theme_tab'] ) ? sanitize_key( $_GET['theme_tab'] ) : 'colors';

// Color Presets
$presets = [
    'indigo_purple' => [
        'name'      => 'Indigo Night',
        'primary'   => '#6366F1',
        'secondary' => '#A855F7',
        'accent'    => '#06B6D4',
        'bg'        => '#FFFFFF',
    ],
    'coral_sunset' => [
        'name'      => 'Coral Sunset',
        'primary'   => '#F43F5E',
        'secondary' => '#FB923C',
        'accent'    => '#FBBF24',
        'bg'        => '#FFFFFF',
    ],
    'ocean_blue' => [
        'name'      => 'Ocean Blue',
        'primary'   => '#0EA5E9',
        'secondary' => '#6366F1',
        'accent'    => '#10B981',
        'bg'        => '#FFFFFF',
    ],
    'forest_green' => [
        'name'      => 'Forest Green',
        'primary'   => '#10B981',
        'secondary' => '#059669',
        'accent'    => '#3B82F6',
        'bg'        => '#FFFFFF',
    ],
    'midnight_dark' => [
        'name'      => 'Midnight Dark',
        'primary'   => '#818CF8',
        'secondary' => '#C084FC',
        'accent'    => '#22D3EE',
        'bg'        => '#0F172A',
    ],
    'rose_gold' => [
        'name'      => 'Rose Gold',
        'primary'   => '#EC4899',
        'secondary' => '#F43F5E',
        'accent'    => '#FBBF24',
        'bg'        => '#FFFFFF',
    ],
];

// Color field definitions (label, config-key)
$color_fields = [
    'Brand Colors' => [
        ['Primary Color',     'primary'],
        ['Primary Dark',      'primary_dark'],
        ['Secondary Color',   'secondary'],
        ['Accent Color',      'accent'],
    ],
    'Backgrounds' => [
        ['App Background',    'background'],
        ['Surface Color',     'surface'],
        ['Surface Variant',   'surface_variant'],
        ['Card Background',   'card_bg'],
    ],
    'Text Colors' => [
        ['Primary Text',      'text_primary'],
        ['Secondary Text',    'text_secondary'],
        ['Hint Text',         'text_hint'],
        ['Border Color',      'border'],
    ],
    'Navigation' => [
        ['Navbar Background', 'navbar_bg'],
        ['Navbar Text',       'navbar_text'],
        ['Navbar Icon',       'navbar_icon'],
        ['Bottom Bar BG',     'bottom_bar_bg'],
        ['Active Tab Color',  'bottom_bar_active'],
        ['Inactive Tab',      'bottom_bar_inactive'],
    ],
    'State Colors' => [
        ['Success',           'success'],
        ['Warning',           'warning'],
        ['Error',             'error'],
        ['Info',              'info'],
        ['Button Text',       'button_text'],
        ['Badge Background',  'badge_bg'],
    ],
];

$current_user = wp_get_current_user();
$username     = $current_user->display_name ?: 'SPACEREMIT';
?>

<div class="dox-page">

    <!-- Top SpaceRemit Header -->
    <div class="dox-header">
        <div style="font-size:22px;font-weight:800;color:var(--dox-text-primary);letter-spacing:-0.5px;">
            <?php _e( 'App Theme Builder', 'doken-ox-pro' ); ?>
        </div>

        <div class="dox-header-actions">
            <button type="button" class="dox-btn dox-btn-secondary" id="dox-theme-reset" style="padding:7px 14px;font-size:12px;">
                <span class="dashicons dashicons-image-rotate" style="font-size:14px;width:14px;height:14px;"></span>
                <?php _e( 'Reset', 'doken-ox-pro' ); ?>
            </button>
            <button type="button" class="dox-btn dox-btn-primary" id="dox-theme-save" style="padding:7px 16px;font-size:12px;">
                <span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;"></span>
                <?php _e( 'Save Theme', 'doken-ox-pro' ); ?>
            </button>
            <a href="<?php echo esc_url( rest_url( 'mvapp/v1/app/config' ) ); ?>" target="_blank"
               class="dox-header-icon-btn" title="<?php esc_attr_e( 'View Config API', 'doken-ox-pro' ); ?>">
                <span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;"></span>
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

        <!-- Main Content -->
        <main class="dox-main">

            <!-- Alert Area -->
            <div id="dox-theme-alert" style="display:none;margin-bottom:16px;"></div>

            <!-- 3-Column Builder Layout -->
            <div class="dox-theme-builder-wrap">

                <!-- ── LEFT: Settings Panel ── -->
                <div class="dox-theme-settings-panel">

                    <!-- Tab Switcher -->
                    <div class="dox-tabs" style="margin-bottom:20px;">
                        <button class="dox-tab-btn <?php echo $tab==='colors'     ? 'active' : ''; ?>" data-tab="colors"><span class="dashicons dashicons-art" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;"></span><?php _e( 'Colors', 'doken-ox-pro' ); ?></button>
                        <button class="dox-tab-btn <?php echo $tab==='typography' ? 'active' : ''; ?>" data-tab="typography"><span class="dashicons dashicons-editor-textcolor" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;"></span><?php _e( 'Font', 'doken-ox-pro' ); ?></button>
                        <button class="dox-tab-btn <?php echo $tab==='branding'   ? 'active' : ''; ?>" data-tab="branding"><span class="dashicons dashicons-format-image" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;"></span><?php _e( 'Brand', 'doken-ox-pro' ); ?></button>
                        <button class="dox-tab-btn <?php echo $tab==='features'   ? 'active' : ''; ?>" data-tab="features"><span class="dashicons dashicons-admin-plugins" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;"></span><?php _e( 'Features', 'doken-ox-pro' ); ?></button>
                        <button class="dox-tab-btn <?php echo $tab==='advanced'   ? 'active' : ''; ?>" data-tab="advanced"><span class="dashicons dashicons-admin-generic" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;"></span><?php _e( 'Advanced', 'doken-ox-pro' ); ?></button>
                    </div>

                    <!-- ═══ COLORS TAB ═══ -->
                    <div class="dox-tab-content" id="tab-colors" <?php echo $tab !== 'colors' ? 'style="display:none"' : ''; ?>>
                        <?php foreach ( $color_fields as $group_name => $fields ) : ?>
                        <div class="dox-card" style="margin-bottom:16px;">
                            <div class="dox-card-header" style="margin-bottom:0;padding-bottom:12px;">
                                <h3 class="dox-card-title"><?php echo esc_html($group_name); ?></h3>
                            </div>
                            <?php foreach ( $fields as [$label, $key] ) :
                                $val = $colors[$key] ?? '#6366F1';
                            ?>
                            <div class="dox-color-row">
                                <span class="dox-color-label"><?php echo esc_html($label); ?></span>
                                <div class="dox-color-picker-wrap">
                                    <div class="dox-color-swatch" style="background:<?php echo esc_attr($val); ?>;">
                                        <input type="color"
                                               value="<?php echo esc_attr($val); ?>"
                                               data-config-key="colors.<?php echo esc_attr($key); ?>"
                                               data-preview-var="--preview-<?php echo esc_attr(str_replace('_','-',$key)); ?>"
                                               class="dox-color-input"
                                               id="color_<?php echo esc_attr($key); ?>">
                                    </div>
                                    <input type="text"
                                           class="dox-color-hex"
                                           value="<?php echo esc_attr($val); ?>"
                                           data-for="color_<?php echo esc_attr($key); ?>"
                                           maxlength="7"
                                           placeholder="#000000">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ═══ TYPOGRAPHY TAB ═══ -->
                    <div class="dox-tab-content" id="tab-typography" <?php echo $tab !== 'typography' ? 'style="display:none"' : ''; ?>>
                        <div class="dox-card">
                            <div class="dox-card-header">
                                <h3 class="dox-card-title"><span class="dashicons dashicons-editor-textcolor"></span> <?php _e('Typography', 'doken-ox-pro'); ?></h3>
                            </div>

                            <div class="dox-form-group">
                                <label class="dox-label"><?php _e('Font Family', 'doken-ox-pro'); ?></label>
                                <select class="dox-select" data-config-key="typography.font_family" id="typo_font_family">
                                    <?php
                                    $fonts = ['Cairo', 'Tajawal', 'IBM Plex Arabic', 'Noto Kufi Arabic', 'Inter', 'Roboto', 'Poppins', 'Nunito', 'Outfit', 'Plus Jakarta Sans'];
                                    foreach ($fonts as $f) :
                                        $sel = ($typo['font_family'] ?? 'Cairo') === $f ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo esc_attr($f); ?>" <?php echo $sel; ?>><?php echo esc_html($f); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div class="dox-form-group">
                                    <label class="dox-label"><?php _e('Base Size (px)', 'doken-ox-pro'); ?></label>
                                    <input type="number" class="dox-input" min="10" max="20"
                                           data-config-key="typography.base_size"
                                           value="<?php echo esc_attr($typo['base_size'] ?? 14); ?>">
                                </div>
                                <div class="dox-form-group">
                                    <label class="dox-label"><?php _e('Heading Size (px)', 'doken-ox-pro'); ?></label>
                                    <input type="number" class="dox-input" min="16" max="36"
                                           data-config-key="typography.heading_size"
                                           value="<?php echo esc_attr($typo['heading_size'] ?? 24); ?>">
                                </div>
                            </div>

                            <div class="dox-form-group">
                                <label class="dox-label"><?php _e('Custom Font URL (Google Fonts)', 'doken-ox-pro'); ?></label>
                                <input type="url" class="dox-input"
                                       data-config-key="typography.font_url"
                                       placeholder="https://fonts.googleapis.com/css2?family=Cairo..."
                                       value="<?php echo esc_attr($typo['font_url'] ?? ''); ?>">
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                                <div class="dox-form-group">
                                    <label class="dox-label"><?php _e('Regular Weight', 'doken-ox-pro'); ?></label>
                                    <select class="dox-select" data-config-key="typography.font_weight_regular">
                                        <?php foreach (['300','400','500'] as $w) : ?>
                                        <option value="<?php echo $w; ?>" <?php selected($typo['font_weight_regular']??'400', $w); ?>><?php echo $w; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="dox-form-group">
                                    <label class="dox-label"><?php _e('Medium Weight', 'doken-ox-pro'); ?></label>
                                    <select class="dox-select" data-config-key="typography.font_weight_medium">
                                        <?php foreach (['500','600','700'] as $w) : ?>
                                        <option value="<?php echo $w; ?>" <?php selected($typo['font_weight_medium']??'600', $w); ?>><?php echo $w; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="dox-form-group">
                                    <label class="dox-label"><?php _e('Bold Weight', 'doken-ox-pro'); ?></label>
                                    <select class="dox-select" data-config-key="typography.font_weight_bold">
                                        <?php foreach (['600','700','800','900'] as $w) : ?>
                                        <option value="<?php echo $w; ?>" <?php selected($typo['font_weight_bold']??'700', $w); ?>><?php echo $w; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Layout -->
                        <div class="dox-card">
                            <div class="dox-card-header">
                                <h3 class="dox-card-title"><span class="dashicons dashicons-layout"></span> <?php _e('Layout', 'doken-ox-pro'); ?></h3>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div class="dox-form-group">
                                    <label class="dox-label"><?php _e('Border Radius (px)', 'doken-ox-pro'); ?></label>
                                    <input type="range" min="0" max="30" step="2"
                                           data-config-key="layout.border_radius"
                                           value="<?php echo esc_attr($layout['border_radius'] ?? 12); ?>"
                                           class="dox-input" id="layout_radius" style="padding:8px 0;">
                                    <span id="radius_val" style="font-size:12px;color:var(--dox-text-muted);"><?php echo esc_html($layout['border_radius'] ?? 12); ?>px</span>
                                </div>
                                <div class="dox-form-group">
                                    <label class="dox-label"><?php _e('Product Card Style', 'doken-ox-pro'); ?></label>
                                    <select class="dox-select" data-config-key="layout.product_card_style">
                                        <option value="grid" <?php selected($layout['product_card_style']??'grid','grid'); ?>>Grid</option>
                                        <option value="list" <?php selected($layout['product_card_style']??'grid','list'); ?>>List</option>
                                    </select>
                                </div>
                                <div class="dox-form-group">
                                    <label class="dox-label"><?php _e('Home Layout', 'doken-ox-pro'); ?></label>
                                    <select class="dox-select" data-config-key="layout.home_layout">
                                        <option value="modern"  <?php selected($layout['home_layout']??'modern','modern'); ?>>Modern</option>
                                        <option value="classic" <?php selected($layout['home_layout']??'modern','classic'); ?>>Classic</option>
                                        <option value="minimal" <?php selected($layout['home_layout']??'modern','minimal'); ?>>Minimal</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ BRANDING TAB ═══ -->
                    <div class="dox-tab-content" id="tab-branding" <?php echo $tab !== 'branding' ? 'style="display:none"' : ''; ?>>
                        <div class="dox-card">
                            <div class="dox-card-header">
                                <h3 class="dox-card-title"><span class="dashicons dashicons-admin-appearance"></span> <?php _e('Brand Identity', 'doken-ox-pro'); ?></h3>
                            </div>

                            <div class="dox-form-group">
                                <label class="dox-label"><?php _e('App Name', 'doken-ox-pro'); ?></label>
                                <input type="text" class="dox-input"
                                       data-config-key="app_name"
                                       data-preview-target="app_name"
                                       value="<?php echo esc_attr($config['app_name'] ?? ''); ?>"
                                       placeholder="My Store">
                            </div>

                            <div class="dox-form-group">
                                <label class="dox-label"><?php _e('App Tagline', 'doken-ox-pro'); ?></label>
                                <input type="text" class="dox-input"
                                       data-config-key="app_tagline"
                                       value="<?php echo esc_attr($config['app_tagline'] ?? ''); ?>"
                                       placeholder="Shop everything">
                            </div>

                            <!-- Logo Upload -->
                            <div class="dox-form-group">
                                <label class="dox-label"><?php _e('App Logo', 'doken-ox-pro'); ?></label>
                                <div class="dox-upload-box" id="logo-upload-box">
                                    <?php if ( ! empty($config['app_logo_url']) ) : ?>
                                        <img src="<?php echo esc_url($config['app_logo_url']); ?>" class="dox-upload-preview" id="logo-preview-img">
                                        <p style="margin-top:8px;"><a href="#" id="logo-change-btn" style="color:var(--dox-primary-light);">Change Logo</a></p>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-format-image"></span>
                                        <p><?php _e('Click to upload app logo', 'doken-ox-pro'); ?></p>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" id="logo_id" name="logo_id" value="<?php echo esc_attr($config['logo_id'] ?? 0); ?>" data-config-key="logo_id">
                                <input type="hidden" id="logo_url" value="<?php echo esc_attr($config['app_logo_url'] ?? ''); ?>">
                            </div>

                            <!-- Splash Image -->
                            <div class="dox-form-group">
                                <label class="dox-label"><?php _e('Splash Screen Image', 'doken-ox-pro'); ?></label>
                                <div class="dox-upload-box" id="splash-upload-box">
                                    <?php if ( ! empty($config['splash_image_url']) ) : ?>
                                        <img src="<?php echo esc_url($config['splash_image_url']); ?>" class="dox-upload-preview" id="splash-preview-img">
                                        <p style="margin-top:8px;"><a href="#" id="splash-change-btn" style="color:var(--dox-primary-light);">Change Image</a></p>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-camera-alt"></span>
                                        <p><?php _e('Click to upload splash image', 'doken-ox-pro'); ?></p>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" id="splash_logo_id" value="<?php echo esc_attr($config['splash_logo_id'] ?? 0); ?>" data-config-key="splash_logo_id">
                                <input type="hidden" id="splash_url" value="<?php echo esc_attr($config['splash_image_url'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- App Store Links -->
                        <div class="dox-card">
                            <div class="dox-card-header">
                                <h3 class="dox-card-title"><span class="dashicons dashicons-smartphone"></span> <?php _e('App Store Links', 'doken-ox-pro'); ?></h3>
                            </div>
                            <div class="dox-form-group">
                                <label class="dox-label"><?php _e('Google Play Store URL', 'doken-ox-pro'); ?></label>
                                <input type="url" class="dox-input" data-config-key="store_links.play_store"
                                       value="<?php echo esc_attr($links['play_store'] ?? ''); ?>"
                                       placeholder="https://play.google.com/store/apps/details?id=...">
                            </div>
                            <div class="dox-form-group">
                                <label class="dox-label"><?php _e('Apple App Store URL', 'doken-ox-pro'); ?></label>
                                <input type="url" class="dox-input" data-config-key="store_links.app_store"
                                       value="<?php echo esc_attr($links['app_store'] ?? ''); ?>"
                                       placeholder="https://apps.apple.com/app/...">
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div class="dox-form-group">
                                    <label class="dox-label"><?php _e('Current Version', 'doken-ox-pro'); ?></label>
                                    <input type="text" class="dox-input" data-config-key="store_links.app_version"
                                           value="<?php echo esc_attr($links['app_version'] ?? '1.0.0'); ?>" placeholder="1.0.0">
                                </div>
                                <div class="dox-form-group">
                                    <label class="dox-label"><?php _e('Min Required Version', 'doken-ox-pro'); ?></label>
                                    <input type="text" class="dox-input" data-config-key="store_links.min_version"
                                           value="<?php echo esc_attr($links['min_version'] ?? '1.0.0'); ?>" placeholder="1.0.0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ FEATURES TAB ═══ -->
                    <div class="dox-tab-content" id="tab-features" <?php echo $tab !== 'features' ? 'style="display:none"' : ''; ?>>
                        <div class="dox-card">
                            <div class="dox-card-header">
                                <h3 class="dox-card-title"><span class="dashicons dashicons-admin-plugins"></span> <?php _e('App Features', 'doken-ox-pro'); ?></h3>
                            </div>
                            <?php
                            $feature_defs = [
                                'enable_wishlist'      => [__('Wishlist',         'doken-ox-pro'), __('Let users save products to a wishlist.',         'doken-ox-pro')],
                                'enable_cart'          => [__('Shopping Cart',    'doken-ox-pro'), __('Enable the cart and checkout flow.',              'doken-ox-pro')],
                                'enable_compare'       => [__('Compare Products', 'doken-ox-pro'), __('Let users compare multiple products.',            'doken-ox-pro')],
                                'enable_notifications' => [__('Push Notifications','doken-ox-pro'),__('Enable in-app and push notifications.',           'doken-ox-pro')],
                                'enable_reviews'       => [__('Product Reviews',  'doken-ox-pro'), __('Show and allow product reviews.',                 'doken-ox-pro')],
                                'enable_dark_mode'     => [__('Dark Mode Toggle', 'doken-ox-pro'), __('Allow users to switch to dark mode.',             'doken-ox-pro')],
                                'enable_flash_sale'    => [__('Flash Sale Timer', 'doken-ox-pro'), __('Show countdown timers on flash sale products.',   'doken-ox-pro')],
                                'enable_otp_login'     => [__('OTP Login',        'doken-ox-pro'), __('Enable phone OTP authentication.',                'doken-ox-pro')],
                                'enable_social_login'  => [__('Social Login',     'doken-ox-pro'), __('Enable Google/Facebook login buttons.',           'doken-ox-pro')],
                                'enable_biometric_auth'=> [__('Biometric Auth',   'doken-ox-pro'), __('Fingerprint/Face ID for returning users.',        'doken-ox-pro')],
                                'enable_referral'      => [__('Referral System',  'doken-ox-pro'), __('Reward users who refer friends.',                 'doken-ox-pro')],
                                'enable_loyalty_points'=> [__('Loyalty Points',   'doken-ox-pro'), __('Points system for purchases.',                   'doken-ox-pro')],
                                'enable_vendor_chat'   => [__('Vendor Chat',      'doken-ox-pro'), __('Real-time chat between customers and vendors.', 'doken-ox-pro')],
                                'show_vendor_info'     => [__('Show Vendor Info', 'doken-ox-pro'), __('Display vendor name on product cards.',           'doken-ox-pro')],
                                'show_store_page'      => [__('Store Pages',      'doken-ox-pro'), __('Enable individual vendor store pages.',           'doken-ox-pro')],
                            ];
                            foreach ($feature_defs as $feat_key => [$feat_label, $feat_desc]) :
                                $enabled = !empty($features[$feat_key]);
                            ?>
                            <div class="dox-toggle-wrap">
                                <div class="dox-toggle-info">
                                    <div class="dox-toggle-title"><?php echo esc_html($feat_label); ?></div>
                                    <div class="dox-toggle-desc"><?php echo esc_html($feat_desc); ?></div>
                                </div>
                                <label class="dox-toggle">
                                    <input type="checkbox"
                                           data-config-key="features.<?php echo esc_attr($feat_key); ?>"
                                           class="dox-feature-toggle"
                                           <?php checked($enabled); ?>>
                                    <span class="dox-toggle-slider"></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ═══ ADVANCED TAB ═══ -->
                    <div class="dox-tab-content" id="tab-advanced" <?php echo $tab !== 'advanced' ? 'style="display:none"' : ''; ?>>
                        <div class="dox-card">
                            <div class="dox-card-header">
                                <h3 class="dox-card-title"><span class="dashicons dashicons-admin-tools"></span> <?php _e('Integrations', 'doken-ox-pro'); ?></h3>
                            </div>
                            <?php
                            $integ_fields = [
                                'google_client_id'  => __('Google OAuth Client ID', 'doken-ox-pro'),
                                'facebook_app_id'   => __('Facebook App ID', 'doken-ox-pro'),
                                'onesignal_app_id'  => __('OneSignal App ID', 'doken-ox-pro'),
                                'google_maps_key'   => __('Google Maps API Key', 'doken-ox-pro'),
                                'stripe_public_key' => __('Stripe Public Key', 'doken-ox-pro'),
                            ];
                            foreach ($integ_fields as $i_key => $i_label) : ?>
                            <div class="dox-form-group">
                                <label class="dox-label"><?php echo esc_html($i_label); ?></label>
                                <input type="text" class="dox-input dox-mono"
                                       data-config-key="integrations.<?php echo esc_attr($i_key); ?>"
                                       value="<?php echo esc_attr($integr[$i_key] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr('Enter ' . $i_label); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Contact Info -->
                        <div class="dox-card">
                            <div class="dox-card-header">
                                <h3 class="dox-card-title"><span class="dashicons dashicons-email"></span> <?php _e('Contact & Legal', 'doken-ox-pro'); ?></h3>
                            </div>
                            <?php
                            $contact_fields = [
                                'support_email' => [__('Support Email', 'doken-ox-pro'), 'email'],
                                'support_phone' => [__('Support Phone', 'doken-ox-pro'), 'tel'],
                                'whatsapp'      => [__('WhatsApp Number', 'doken-ox-pro'), 'tel'],
                                'privacy_url'   => [__('Privacy Policy URL', 'doken-ox-pro'), 'url'],
                                'terms_url'     => [__('Terms of Service URL', 'doken-ox-pro'), 'url'],
                                'about_us_url'  => [__('About Us URL', 'doken-ox-pro'), 'url'],
                            ];
                            foreach ($contact_fields as $c_key => [$c_label, $c_type]) : ?>
                            <div class="dox-form-group">
                                <label class="dox-label"><?php echo esc_html($c_label); ?></label>
                                <input type="<?php echo esc_attr($c_type); ?>" class="dox-input"
                                       data-config-key="contact.<?php echo esc_attr($c_key); ?>"
                                       value="<?php echo esc_attr($contact[$c_key] ?? ''); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div><!-- /.dox-theme-settings-panel -->

                <!-- ── CENTER: Live Phone Preview ── -->
                <div class="dox-theme-preview-col">
                    <div class="dox-card" style="text-align:center;position:sticky;top:28px;">
                        <div class="dox-card-header" style="justify-content:center;">
                            <h3 class="dox-card-title"><span class="dashicons dashicons-smartphone" style="font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px;"></span><?php _e('Live Preview', 'doken-ox-pro'); ?></h3>
                        </div>

                        <!-- Device Switcher -->
                        <div style="display:flex;justify-content:center;gap:8px;margin-bottom:16px;">
                            <button class="dox-btn dox-btn-secondary dox-btn-sm dox-device-btn active" data-device="ios">
                                <span class="dashicons dashicons-apple" style="font-size:13px;width:13px;height:13px;margin:0;"></span> iOS
                            </button>
                            <button class="dox-btn dox-btn-secondary dox-btn-sm dox-device-btn" data-device="android">
                                <span class="dashicons dashicons-smartphone" style="font-size:13px;width:13px;height:13px;margin:0;"></span> Android
                            </button>
                        </div>

                        <!-- Phone Frame -->
                        <div class="dox-phone-frame" id="dox-preview-phone">
                            <div class="dox-phone-screen" id="dox-preview-screen">

                                <!-- Status Bar -->
                                <div class="dox-phone-status">
                                    <span>9:41</span>
                                    <div style="display:flex;gap:3px;align-items:center;">
                                        <span style="font-size:8px;">▐▐▐</span>
                                        <span class="dashicons dashicons-wifi" style="font-size:10px;width:10px;height:10px;"></span>
                                        <span class="dashicons dashicons-battery" style="font-size:10px;width:10px;height:10px;"></span>
                                    </div>
                                </div>

                                <!-- Navbar -->
                                <div class="dox-phone-navbar">
                                    <span class="dox-phone-logo-text" id="preview-app-name">
                                        <?php echo esc_html($config['app_name'] ?? 'My Store'); ?>
                                    </span>
                                    <div style="display:flex;gap:6px;">
                                        <span class="dashicons dashicons-search" style="font-size:14px;width:14px;height:14px;color:var(--preview-navbar-text,#0F172A);"></span>
                                        <span class="dashicons dashicons-cart" style="font-size:14px;width:14px;height:14px;color:var(--preview-navbar-text,#0F172A);"></span>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="dox-phone-content">
                                    <!-- Slider -->
                                    <div class="dox-phone-slider"><span class="dashicons dashicons-tag" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:2px;"></span> Special Offer</div>

                                    <!-- Categories -->
                                    <div class="dox-phone-categories">
                                        <?php for ($i=0; $i<4; $i++) : ?>
                                        <div class="dox-phone-cat">
                                            <div class="dox-phone-cat-circle"></div>
                                            <span class="dox-phone-cat-label">Cat <?php echo $i+1; ?></span>
                                        </div>
                                        <?php endfor; ?>
                                    </div>

                                    <!-- Products -->
                                    <div class="dox-phone-products">
                                        <?php for ($i=0; $i<2; $i++) : ?>
                                        <div class="dox-phone-product-card">
                                            <div class="dox-phone-product-img"></div>
                                            <div class="dox-phone-product-info">
                                                <div class="dox-phone-product-name">Product Name</div>
                                                <div class="dox-phone-product-price">$24.99</div>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <!-- Bottom Bar -->
                                <div class="dox-phone-bottombar">
                                    <div class="dox-phone-tab active">
                                        <span class="dox-phone-tab-icon"><span class="dashicons dashicons-admin-home" style="font-size:14px;width:14px;height:14px;"></span></span>
                                        <span>Home</span>
                                    </div>
                                    <div class="dox-phone-tab">
                                        <span class="dox-phone-tab-icon"><span class="dashicons dashicons-search" style="font-size:14px;width:14px;height:14px;"></span></span>
                                        <span>Search</span>
                                    </div>
                                    <div class="dox-phone-tab">
                                        <span class="dox-phone-tab-icon"><span class="dashicons dashicons-cart" style="font-size:14px;width:14px;height:14px;"></span></span>
                                        <span>Cart</span>
                                    </div>
                                    <div class="dox-phone-tab">
                                        <span class="dox-phone-tab-icon"><span class="dashicons dashicons-admin-users" style="font-size:14px;width:14px;height:14px;"></span></span>
                                        <span>Profile</span>
                                    </div>
                                </div>

                            </div>
                        </div><!-- /.dox-phone-frame -->

                        <!-- API Config Link -->
                        <div style="margin-top:16px;">
                            <p style="font-size:11px;color:var(--dox-text-muted);margin-bottom:6px;">Flutter API Endpoint:</p>
                            <div class="dox-endpoint" style="justify-content:center;font-size:11px;">
                                <span class="dox-method dox-method-get">GET</span>
                                <span class="dox-endpoint-url">/mvapp/v1/app/config</span>
                                <button class="dox-copy-btn" data-copy="<?php echo esc_attr(rest_url('mvapp/v1/app/config')); ?>">
                                    <span class="dashicons dashicons-clipboard" style="font-size:12px;width:12px;height:12px;"></span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div><!-- /.dox-theme-preview-col -->

                <!-- ── RIGHT: Presets Panel ── -->
                <div class="dox-theme-presets-col">
                    <div class="dox-card" style="position:sticky;top:28px;">
                        <div class="dox-card-header">
                            <h3 class="dox-card-title"><span class="dashicons dashicons-color-picker" style="font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px;"></span><?php _e('Color Presets', 'doken-ox-pro'); ?></h3>
                        </div>

                        <div class="dox-preset-grid">
                            <?php foreach ($presets as $preset_id => $preset) : ?>
                            <div class="dox-preset-card" data-preset="<?php echo esc_attr(wp_json_encode($preset)); ?>">
                                <div class="dox-preset-colors">
                                    <div class="dox-preset-dot" style="background:<?php echo esc_attr($preset['primary']); ?>;"></div>
                                    <div class="dox-preset-dot" style="background:<?php echo esc_attr($preset['secondary']); ?>;"></div>
                                    <div class="dox-preset-dot" style="background:<?php echo esc_attr($preset['accent']); ?>;"></div>
                                    <div class="dox-preset-dot" style="background:<?php echo esc_attr($preset['bg']); ?>;border:1px solid var(--dox-border);"></div>
                                </div>
                                <div class="dox-preset-name"><?php echo esc_html($preset['name']); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <hr style="border-color:var(--dox-border);margin:16px 0;">

                        <div>
                            <p style="font-size:12px;color:var(--dox-text-muted);margin-bottom:10px;"><?php _e('Export current theme config:', 'doken-ox-pro'); ?></p>
                            <button class="dox-btn dox-btn-secondary" style="width:100%;justify-content:center;" id="dox-export-config">
                                <span class="dashicons dashicons-download" style="font-size:14px;width:14px;height:14px;margin:0;"></span>
                                <?php _e('Export JSON', 'doken-ox-pro'); ?>
                            </button>
                        </div>

                        <!-- Mode Info -->
                        <hr style="border-color:var(--dox-border);margin:16px 0;">
                        <div>
                            <p style="font-size:11px;color:var(--dox-text-muted);margin-bottom:6px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Current Mode</p>
                            <div class="dox-mode-badge <?php echo Doken_Ox_Plugin_Mode::is_woo_only_mode() ? 'woo-only' : ''; ?>" style="display:inline-flex;">
                                <?php echo esc_html(Doken_Ox_Plugin_Mode::get_mode_label()); ?>
                            </div>
                            <p style="font-size:11px;color:var(--dox-text-muted);margin-top:6px;">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=doken-ox-pro-settings')); ?>"
                                   style="color:var(--dox-primary-light);"><?php _e('Change Mode', 'doken-ox-pro'); ?></a>
                            </p>
                        </div>
                    </div>
                </div><!-- /.dox-theme-presets-col -->

            </div><!-- /.dox-theme-builder-wrap -->
        </main>
    </div><!-- /.dox-layout -->
</div><!-- /.dox-page -->

<!-- Theme Builder Script -->
<script>
(function($){
    'use strict';

    var configData   = {};   // Staged changes
    var restUrl      = '<?php echo esc_js($rest_url); ?>';
    var nonce        = '<?php echo esc_js($nonce); ?>';
    var saveTimer    = null;
    var mediaUploaders = {};

    // ── Sync CSS Preview Variables ──────────────────────────────────────
    var colorKeyMap = {
        'colors.primary':             '--preview-primary',
        'colors.secondary':           '--preview-secondary',
        'colors.accent':              '--preview-accent',
        'colors.background':          '--preview-bg',
        'colors.surface':             '--preview-surface',
        'colors.surface_variant':     '--preview-surface-variant',
        'colors.card_bg':             '--preview-card',
        'colors.text_primary':        '--preview-text',
        'colors.text_secondary':      '--preview-text-secondary',
        'colors.navbar_bg':           '--preview-navbar-bg',
        'colors.navbar_text':         '--preview-navbar-text',
        'colors.bottom_bar_bg':       '--preview-bottombar-bg',
        'colors.bottom_bar_active':   '--preview-tab-active',
        'colors.bottom_bar_inactive': '--preview-tab-inactive',
    };

    function applyPreviewColor(configKey, value) {
        var cssVar = colorKeyMap[configKey];
        if (cssVar) {
            document.getElementById('dox-preview-screen').style.setProperty(cssVar, value);
        }
    }

    // Initialize preview colors from saved config
    <?php foreach ($colors as $ck => $cv): ?>
    applyPreviewColor('colors.<?php echo esc_js($ck); ?>', '<?php echo esc_js($cv); ?>');
    <?php endforeach; ?>

    // ── Color Inputs ────────────────────────────────────────────────────
    $(document).on('input change', '.dox-color-input', function(){
        var $input = $(this);
        var key    = $input.data('config-key');
        var val    = $input.val();

        // Update swatch background
        $input.closest('.dox-color-swatch').css('background', val);

        // Update hex text input
        $('[data-for="' + $input.attr('id') + '"]').val(val);

        // Live preview
        applyPreviewColor(key, val);

        // Stage change
        setNestedKey(configData, key, val);
        scheduleAutoSave();
    });

    // Hex text input → sync to color picker
    $(document).on('change', '.dox-color-hex', function(){
        var $hex = $(this);
        var val  = $hex.val().trim();
        if (!/^#[0-9A-Fa-f]{6}$/.test(val)) return;

        var targetId = $hex.data('for');
        var $picker  = $('#' + targetId);
        $picker.val(val).trigger('input');
    });

    // ── Tab Switching ───────────────────────────────────────────────────
    $(document).on('click', '.dox-tab-btn', function(){
        var tab = $(this).data('tab');
        $('.dox-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.dox-tab-content').hide();
        $('#tab-' + tab).show();
    });

    // ── Feature Toggles ─────────────────────────────────────────────────
    $(document).on('change', '.dox-feature-toggle', function(){
        var key = $(this).data('config-key');
        var val = $(this).is(':checked');
        setNestedKey(configData, key, val);
        scheduleAutoSave();
    });

    // ── Text / Select / Number Inputs ───────────────────────────────────
    $(document).on('change', '[data-config-key]:not(.dox-color-input):not(.dox-feature-toggle):not([data-for])', function(){
        var key = $(this).data('config-key');
        var val = $(this).val();

        // App name preview
        if (key === 'app_name') {
            $('#preview-app-name').text(val);
        }

        setNestedKey(configData, key, val);
        scheduleAutoSave();
    });

    // Range slider display
    $('#layout_radius').on('input', function(){
        $('#radius_val').text($(this).val() + 'px');
    });

    // ── Color Presets ───────────────────────────────────────────────────
    $(document).on('click', '.dox-preset-card', function(){
        var preset = $(this).data('preset');
        if (typeof preset === 'string') {
            try { preset = JSON.parse(preset); } catch(e){ return; }
        }

        $('.dox-preset-card').removeClass('active');
        $(this).addClass('active');

        // Apply preset colors to inputs + preview
        var map = {
            primary:   'primary',
            secondary: 'secondary',
            accent:    'accent',
            bg:        'background',
        };

        $.each(map, function(presetProp, colorKey){
            if (!preset[presetProp]) return;
            var val = preset[presetProp];
            var $picker = $('#color_' + colorKey);
            if ($picker.length) {
                $picker.val(val).closest('.dox-color-swatch').css('background', val);
                $('[data-for="color_' + colorKey + '"]').val(val);
                applyPreviewColor('colors.' + colorKey, val);
                setNestedKey(configData, 'colors.' + colorKey, val);
            }
        });

        scheduleAutoSave();
    });

    // ── Media Upload ─────────────────────────────────────────────────────
    function setupMediaUpload(boxId, hiddenIdField, hiddenUrlField, previewId, changeBtnId) {
        $('#' + boxId + ', #' + changeBtnId).on('click', function(e){
            e.preventDefault();

            if (!mediaUploaders[boxId]) {
                mediaUploaders[boxId] = wp.media({
                    title: 'Select Image',
                    button: { text: 'Use This Image' },
                    multiple: false,
                    library: { type: 'image' },
                });

                mediaUploaders[boxId].on('select', function(){
                    var attachment = mediaUploaders[boxId].state().get('selection').first().toJSON();
                    $('#' + hiddenIdField).val(attachment.id);
                    $('#' + hiddenUrlField).val(attachment.url);

                    // Update config
                    setNestedKey(configData, $('#' + hiddenIdField).data('config-key') || hiddenIdField, attachment.id);

                    // Preview
                    if ($('#' + previewId).length) {
                        $('#' + previewId).attr('src', attachment.url).show();
                    } else {
                        $('#' + boxId).html('<img src="' + attachment.url + '" class="dox-upload-preview" id="' + previewId + '"><p style="margin-top:8px;"><a href="#" id="' + changeBtnId + '" style="color:var(--dox-primary-light);">Change</a></p>');
                    }

                    scheduleAutoSave();
                });
            }

            mediaUploaders[boxId].open();
        });
    }

    setupMediaUpload('logo-upload-box', 'logo_id', 'logo_url', 'logo-preview-img', 'logo-change-btn');
    setupMediaUpload('splash-upload-box', 'splash_logo_id', 'splash_url', 'splash-preview-img', 'splash-change-btn');

    // ── Save ─────────────────────────────────────────────────────────────
    function scheduleAutoSave() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function(){
            if (Object.keys(configData).length > 0) {
                doSave(true); // silent auto-save
            }
        }, 2000);
    }

    function doSave(silent) {
        if (!silent) {
            var $btn = $('#dox-theme-save');
            $btn.prop('disabled', true).html('<span class="dox-spinner" style="display:inline-block;"></span> Saving...');
        }

        $.ajax({
            url: restUrl,
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-WP-Nonce': nonce },
            data: JSON.stringify(configData),
            success: function(res) {
                configData = {}; // clear staged changes
                if (!silent) {
                    $('#dox-theme-save').html('<span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;margin:0;"></span> Saved!').css('background', 'var(--dox-success)');
                    setTimeout(function(){
                        $('#dox-theme-save').html('<span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;margin:0;"></span> Save Theme').prop('disabled', false).css('background','');
                    }, 2000);
                }
                showAlert('success', 'Theme saved successfully! Flutter app will reflect changes on next load.');
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Save failed.';
                showAlert('danger', msg);
                if (!silent) {
                    $('#dox-theme-save').html('Save Theme').prop('disabled', false);
                }
            }
        });
    }

    $('#dox-theme-save').on('click', function(){ doSave(false); });

    // ── Reset ────────────────────────────────────────────────────────────
    $('#dox-theme-reset').on('click', function(){
        if (!confirm('Reset theme to default settings? This cannot be undone.')) return;
        $.ajax({
            url: restUrl + '/reset',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            success: function(){ location.reload(); },
            error: function(){ showAlert('warning', 'Reset via admin page — manually clear dox_app_config option.'); }
        });
    });

    // ── Export JSON ──────────────────────────────────────────────────────
    $('#dox-export-config').on('click', function(){
        $.getJSON(restUrl, function(res){
            var blob = new Blob([JSON.stringify(res.data, null, 2)], {type:'application/json'});
            var url  = URL.createObjectURL(blob);
            var a    = document.createElement('a');
            a.href   = url;
            a.download = 'dox-app-config.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    });

    // ── Copy Button ──────────────────────────────────────────────────────
    $(document).on('click', '.dox-copy-btn', function(){
        var text = $(this).data('copy');
        navigator.clipboard && navigator.clipboard.writeText(text).then(function(){
            showAlert('info', 'URL copied to clipboard!');
        });
    });

    // ── Helpers ──────────────────────────────────────────────────────────
    function showAlert(type, msg) {
        var $el = $('#dox-theme-alert');
        $el.html('<div class="dox-alert dox-alert-' + type + '">' + msg + '</div>').show();
        clearTimeout($el.data('timer'));
        $el.data('timer', setTimeout(function(){ $el.fadeOut(); }, 5000));
    }

    function setNestedKey(obj, dotKey, value) {
        var parts  = dotKey.split('.');
        var cursor = obj;
        for (var i = 0; i < parts.length - 1; i++) {
            if (!cursor[parts[i]] || typeof cursor[parts[i]] !== 'object') {
                cursor[parts[i]] = {};
            }
            cursor = cursor[parts[i]];
        }
        cursor[parts[parts.length - 1]] = value;
    }

})(jQuery);
</script>
