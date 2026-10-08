<?php
/**
 * Doken Ox Pro — Interactive Setup Wizard
 * Fullscreen / Guided Onboarding for Store Architecture, Dependencies, and App Config.
 * Clean SaaS typography with zero emojis.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$step = isset( $_GET['step'] ) ? max( 1, min( 4, intval( $_GET['step'] ) ) ) : 1;
$saved_notice = false;

// Process Form Submissions
if ( isset( $_POST['dox_wizard_submit'] ) && check_admin_referer( 'dox_wizard_step', 'dox_wizard_nonce' ) ) {
    $current_step = intval( $_POST['current_step'] ?? 1 );

    if ( $current_step === 1 ) {
        // Save Mode
        $chosen_mode = sanitize_text_field( $_POST['dox_plugin_mode'] ?? 'woocommerce' );
        if ( class_exists( 'Doken_Ox_Plugin_Mode' ) ) {
            Doken_Ox_Plugin_Mode::set_mode( $chosen_mode );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=doken-ox-pro-wizard&step=2' ) );
        exit;
    } elseif ( $current_step === 2 ) {
        // Save Features & Security toggles
        $current_settings = get_option( 'doken_ox_pro_settings', array() );
        $current_settings['rate_limit']     = max( 10, intval( $_POST['dox_rate_limit'] ?? 100 ) );
        $current_settings['enable_cache']   = ! empty( $_POST['dox_enable_cache'] );
        $current_settings['enable_security']= ! empty( $_POST['dox_enable_security'] );
        $current_settings['log_api_errors'] = ! empty( $_POST['dox_log_api_errors'] );
        update_option( 'doken_ox_pro_settings', $current_settings );

        wp_safe_redirect( admin_url( 'admin.php?page=doken-ox-pro-wizard&step=3' ) );
        exit;
    } elseif ( $current_step === 3 ) {
        // Save App Branding & Theme Color
        $app_name   = sanitize_text_field( $_POST['dox_app_name'] ?? 'My Store App' );
        $app_preset = sanitize_text_field( $_POST['dox_app_preset'] ?? 'spaceremit_coral' );

        $preset_colors = array(
            'spaceremit_coral' => array( 'primary' => '#FF385C', 'secondary' => '#111827', 'accent' => '#06B6D4' ),
            'indigo_night'     => array( 'primary' => '#6366F1', 'secondary' => '#A855F7', 'accent' => '#06B6D4' ),
            'emerald_store'    => array( 'primary' => '#10B981', 'secondary' => '#059669', 'accent' => '#3B82F6' ),
            'ocean_blue'       => array( 'primary' => '#0EA5E9', 'secondary' => '#6366F1', 'accent' => '#10B981' ),
        );

        $selected_colors = $preset_colors[ $app_preset ] ?? $preset_colors['spaceremit_coral'];

        if ( class_exists( 'Doken_Ox_App_Config' ) ) {
            $config = Doken_Ox_App_Config::get_full_config();
            $config['brand']['app_name']   = $app_name;
            $config['colors']['primary']   = $selected_colors['primary'];
            $config['colors']['secondary'] = $selected_colors['secondary'];
            $config['colors']['accent']    = $selected_colors['accent'];
            Doken_Ox_App_Config::update_full_config( $config );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=doken-ox-pro-wizard&step=4' ) );
        exit;
    } elseif ( $current_step === 4 ) {
        // License / Final Launch
        $license_key = sanitize_text_field( $_POST['dox_license_key'] ?? '' );
        if ( ! empty( $license_key ) && class_exists( 'Doken_Ox_License_Handler' ) ) {
            Doken_Ox_License_Handler::activate( $license_key );
        }
        update_option( 'dox_setup_completed', true );
        wp_safe_redirect( admin_url( 'admin.php?page=doken-ox-pro' ) );
        exit;
    }
}

// Current Data
$active_mode   = class_exists( 'Doken_Ox_Plugin_Mode' ) ? Doken_Ox_Plugin_Mode::get_mode() : 'woocommerce';
$dokan_active  = class_exists( 'Doken_Ox_Plugin_Mode' ) && Doken_Ox_Plugin_Mode::is_dokan_installed();
$woo_active    = class_exists( 'WooCommerce' );
$settings      = get_option( 'doken_ox_pro_settings', array( 'enable_cache' => true, 'rate_limit' => 100, 'enable_security' => true ) );
$app_config    = class_exists( 'Doken_Ox_App_Config' ) ? Doken_Ox_App_Config::get_full_config() : array();
$app_name      = $app_config['brand']['app_name'] ?? get_bloginfo( 'name' );
?>

<div class="dox-page" style="min-height:100vh;background:#F5F6F8;padding:40px 20px !important;">

    <!-- Wizard Header Branding -->
    <div style="text-align:center;margin-bottom:32px;">
        <div style="display:inline-flex;align-items:center;gap:10px;background:#0A0B0E;padding:8px 18px;border-radius:14px;box-shadow:0 4px 16px rgba(0,0,0,0.1);">
            <div style="width:24px;height:24px;border-radius:6px;background:var(--dox-primary);display:flex;align-items:center;justify-content:center;color:#FFFFFF;">
                <span class="dashicons dashicons-bolt" style="font-size:16px;width:16px;height:16px;"></span>
            </div>
            <span style="color:#FFFFFF;font-weight:900;font-size:14px;letter-spacing:0.8px;">SPACEREMIT</span>
            <span style="color:#6B7280;font-size:12px;">| Doken Ox Pro Setup Wizard</span>
        </div>
        <h1 style="font-size:26px;font-weight:900;color:#111827;margin:16px 0 6px;letter-spacing:-0.5px;">
            <?php _e( 'Commerce & Mobile App Onboarding', 'doken-ox-pro' ); ?>
        </h1>
        <p style="font-size:14px;color:#6B7280;margin:0;">
            <?php _e( 'Configure your store mode, mobile dependencies, and Flutter API endpoints in 4 simple steps.', 'doken-ox-pro' ); ?>
        </p>
    </div>

    <!-- Wizard Container -->
    <div class="dox-wizard-wrap">

        <!-- Progress Steps Bar -->
        <div class="dox-wizard-steps-bar">
            <div class="dox-wizard-step-item <?php echo $step === 1 ? 'active' : ''; ?>">
                <div class="dox-wizard-step-num">1</div>
                <div>
                    <div><?php _e( 'Store Mode', 'doken-ox-pro' ); ?></div>
                    <div style="font-size:10px;color:#9CA3AF;font-weight:400;"><?php _e( 'Single or Multi-vendor', 'doken-ox-pro' ); ?></div>
                </div>
            </div>
            <div class="dox-wizard-step-item <?php echo $step === 2 ? 'active' : ''; ?>">
                <div class="dox-wizard-step-num">2</div>
                <div>
                    <div><?php _e( 'Features & Security', 'doken-ox-pro' ); ?></div>
                    <div style="font-size:10px;color:#9CA3AF;font-weight:400;"><?php _e( 'Toggles & Modules', 'doken-ox-pro' ); ?></div>
                </div>
            </div>
            <div class="dox-wizard-step-item <?php echo $step === 3 ? 'active' : ''; ?>">
                <div class="dox-wizard-step-num">3</div>
                <div>
                    <div><?php _e( 'App Branding', 'doken-ox-pro' ); ?></div>
                    <div style="font-size:10px;color:#9CA3AF;font-weight:400;"><?php _e( 'Flutter Colors & Name', 'doken-ox-pro' ); ?></div>
                </div>
            </div>
            <div class="dox-wizard-step-item <?php echo $step === 4 ? 'active' : ''; ?>">
                <div class="dox-wizard-step-num">4</div>
                <div>
                    <div><?php _e( 'Ready', 'doken-ox-pro' ); ?></div>
                    <div style="font-size:10px;color:#9CA3AF;font-weight:400;"><?php _e( 'Launch Dashboard', 'doken-ox-pro' ); ?></div>
                </div>
            </div>
        </div>

        <!-- Step Body -->
        <div style="padding:36px 40px;">

            <form method="post" action="">
                <?php wp_nonce_field( 'dox_wizard_step', 'dox_wizard_nonce' ); ?>
                <input type="hidden" name="current_step" value="<?php echo esc_attr( $step ); ?>">
                <input type="hidden" name="dox_wizard_submit" value="1">

                <!-- STEP 1: Store Mode Selection -->
                <?php if ( $step === 1 ) : ?>
                    <h2 style="font-size:19px;font-weight:800;color:#111827;margin:0 0 8px;">
                        <?php _e( 'Step 1: Choose Your Operating Mode', 'doken-ox-pro' ); ?>
                    </h2>
                    <p style="font-size:13.5px;color:#6B7280;margin:0 0 24px;line-height:1.5;">
                        <?php _e( 'Dokan is completely optional! If you operate a regular single-merchant store, choose WooCommerce Only for maximum speed and lightweight APIs.', 'doken-ox-pro' ); ?>
                    </p>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:32px;">
                        <!-- Option A: WooCommerce Only -->
                        <label style="cursor:pointer;display:block;">
                            <input type="radio" name="dox_plugin_mode" value="woocommerce" <?php checked( $active_mode, 'woocommerce' ); ?> style="display:none;" class="dox-wizard-radio">
                            <div class="dox-wizard-mode-card" style="border:2px solid <?php echo $active_mode === 'woocommerce' ? 'var(--dox-primary)' : '#E5E7EB'; ?>;border-radius:16px;padding:24px;background:#FFFFFF;transition:all .2s ease;height:100%;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                                    <div style="width:44px;height:44px;border-radius:12px;background:#F3F4F6;display:flex;align-items:center;justify-content:center;color:#111827;">
                                        <span class="dashicons dashicons-cart" style="font-size:22px;"></span>
                                    </div>
                                    <span class="dox-badge dox-badge-success">Standard & Fast</span>
                                </div>
                                <h3 style="font-size:16px;font-weight:800;color:#111827;margin:0 0 6px;">
                                    <?php _e( 'WooCommerce Only', 'doken-ox-pro' ); ?>
                                </h3>
                                <p style="font-size:12.5px;color:#6B7280;margin:0;line-height:1.5;">
                                    <?php _e( 'Standard single-seller e-commerce store. Multi-vendor routes and seller tabs are deactivated for maximum performance.', 'doken-ox-pro' ); ?>
                                </p>
                                <div style="margin-top:14px;font-size:11.5px;color:#10B981;font-weight:600;display:flex;align-items:center;gap:4px;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size:14px;width:14px;height:14px;"></span>
                                    <span><?php _e( 'Dokan is NOT required', 'doken-ox-pro' ); ?></span>
                                </div>
                            </div>
                        </label>

                        <!-- Option B: WooCommerce + Dokan -->
                        <label style="cursor:pointer;display:block;">
                            <input type="radio" name="dox_plugin_mode" value="woocommerce_dokan" <?php checked( $active_mode, 'woocommerce_dokan' ); ?> style="display:none;" class="dox-wizard-radio">
                            <div class="dox-wizard-mode-card" style="border:2px solid <?php echo $active_mode === 'woocommerce_dokan' ? 'var(--dox-primary)' : '#E5E7EB'; ?>;border-radius:16px;padding:24px;background:#FFFFFF;transition:all .2s ease;height:100%;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                                    <div style="width:44px;height:44px;border-radius:12px;background:#FEE2E2;display:flex;align-items:center;justify-content:center;color:var(--dox-primary);">
                                        <span class="dashicons dashicons-store" style="font-size:22px;"></span>
                                    </div>
                                    <span class="dox-badge dox-badge-primary">Multi-Vendor</span>
                                </div>
                                <h3 style="font-size:16px;font-weight:800;color:#111827;margin:0 0 6px;">
                                    <?php _e( 'WooCommerce + Dokan', 'doken-ox-pro' ); ?>
                                </h3>
                                <p style="font-size:12.5px;color:#6B7280;margin:0;line-height:1.5;">
                                    <?php _e( 'Full marketplace with vendor stores, seller earnings, commissions, and vendor mobile app endpoints.', 'doken-ox-pro' ); ?>
                                </p>
                                <div style="margin-top:14px;font-size:11.5px;color:#6B7280;">
                                    <?php _e( 'Dokan Status:', 'doken-ox-pro' ); ?> <strong><?php echo $dokan_active ? 'Installed' : 'Not installed'; ?></strong>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="dox-btn dox-btn-primary" style="padding:12px 28px;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
                            <span><?php _e( 'Next: Features & Modules', 'doken-ox-pro' ); ?></span>
                            <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                        </button>
                    </div>

                <!-- STEP 2: Dependencies & Optional Features -->
                <?php elseif ( $step === 2 ) : ?>
                    <h2 style="font-size:19px;font-weight:800;color:#111827;margin:0 0 8px;">
                        <?php _e( 'Step 2: Dependencies & Module Toggles', 'doken-ox-pro' ); ?>
                    </h2>
                    <p style="font-size:13.5px;color:#6B7280;margin:0 0 24px;">
                        <?php _e( 'Choose what to activate or deactivate. Everything is modular and configurable.', 'doken-ox-pro' ); ?>
                    </p>

                    <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:32px;">

                        <!-- Core WooCommerce (Mandatory) -->
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:12px;">
                            <div style="display:flex;align-items:center;gap:14px;">
                                <div style="width:36px;height:36px;border-radius:10px;background:#E5E7EB;display:flex;align-items:center;justify-content:center;color:#111827;">
                                    <span class="dashicons dashicons-archive"></span>
                                </div>
                                <div>
                                    <div style="font-weight:700;color:#111827;font-size:14px;">WooCommerce Core</div>
                                    <div style="font-size:12px;color:#6B7280;"><?php _e( 'Essential for store products, cart, checkout, and order management.', 'doken-ox-pro' ); ?></div>
                                </div>
                            </div>
                            <span class="dox-badge dox-badge-success"><?php echo $woo_active ? 'Active (Essential)' : 'Required'; ?></span>
                        </div>

                        <!-- Dokan Integration (Optional) -->
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;">
                            <div style="display:flex;align-items:center;gap:14px;">
                                <div style="width:36px;height:36px;border-radius:10px;background:#F3F4F6;display:flex;align-items:center;justify-content:center;color:#4B5563;">
                                    <span class="dashicons dashicons-store"></span>
                                </div>
                                <div>
                                    <div style="font-weight:700;color:#111827;font-size:14px;">Dokan Multi-Vendor Integration</div>
                                    <div style="font-size:12px;color:#6B7280;"><?php _e( 'Optional. Only needed if you want seller stores and vendor dashboards.', 'doken-ox-pro' ); ?></div>
                                </div>
                            </div>
                            <span class="dox-badge dox-badge-muted"><?php _e( 'Optional', 'doken-ox-pro' ); ?></span>
                        </div>

                        <!-- Security & Rate Limiting Module (Optional internal feature) -->
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;">
                            <div style="display:flex;align-items:center;gap:14px;">
                                <div style="width:36px;height:36px;border-radius:10px;background:#FEF3C7;display:flex;align-items:center;justify-content:center;color:#D97706;">
                                    <span class="dashicons dashicons-shield"></span>
                                </div>
                                <div>
                                    <div style="font-weight:700;color:#111827;font-size:14px;">Internal Security & Rate Limiting (DDoS Guard)</div>
                                    <div style="font-size:12px;color:#6B7280;"><?php _e( 'Optional built-in rate limiter. Protects your mobile endpoints without extra heavy security plugins.', 'doken-ox-pro' ); ?></div>
                                </div>
                            </div>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="dox_enable_security" value="1" <?php checked( ! empty( $settings['enable_security'] ?? true ) ); ?> style="accent-color:var(--dox-primary);width:18px;height:18px;">
                                <span style="font-size:13px;font-weight:600;color:#111827;"><?php _e( 'Enable', 'doken-ox-pro' ); ?></span>
                            </label>
                        </div>

                        <!-- Performance Caching Module (Optional) -->
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;">
                            <div style="display:flex;align-items:center;gap:14px;">
                                <div style="width:36px;height:36px;border-radius:10px;background:#ECFDF5;display:flex;align-items:center;justify-content:center;color:#059669;">
                                    <span class="dashicons dashicons-performance"></span>
                                </div>
                                <div>
                                    <div style="font-weight:700;color:#111827;font-size:14px;">Response Object Caching</div>
                                    <div style="font-size:12px;color:#6B7280;"><?php _e( 'Caches mobile home layout and catalog responses to load Flutter screens in under 150ms.', 'doken-ox-pro' ); ?></div>
                                </div>
                            </div>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="dox_enable_cache" value="1" <?php checked( ! empty( $settings['enable_cache'] ) ); ?> style="accent-color:var(--dox-primary);width:18px;height:18px;">
                                <span style="font-size:13px;font-weight:600;color:#111827;"><?php _e( 'Enable', 'doken-ox-pro' ); ?></span>
                            </label>
                        </div>

                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=doken-ox-pro-wizard&step=1' ) ); ?>" class="dox-btn dox-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;">
                            <span class="dashicons dashicons-arrow-left-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                            <span><?php _e( 'Back', 'doken-ox-pro' ); ?></span>
                        </a>
                        <button type="submit" class="dox-btn dox-btn-primary" style="padding:12px 28px;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
                            <span><?php _e( 'Next: App Branding', 'doken-ox-pro' ); ?></span>
                            <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                        </button>
                    </div>

                <!-- STEP 3: Flutter App Branding & Colors -->
                <?php elseif ( $step === 3 ) : ?>
                    <h2 style="font-size:19px;font-weight:800;color:#111827;margin:0 0 8px;">
                        <?php _e( 'Step 3: Flutter Mobile App Identity & Colors', 'doken-ox-pro' ); ?>
                    </h2>
                    <p style="font-size:13.5px;color:#6B7280;margin:0 0 24px;">
                        <?php _e( 'Set your mobile application title and choose a preset palette that your Flutter app will load on startup.', 'doken-ox-pro' ); ?>
                    </p>

                    <div style="margin-bottom:24px;">
                        <label style="display:block;font-size:13px;font-weight:700;color:#111827;margin-bottom:6px;">
                            <?php _e( 'Mobile Application Name', 'doken-ox-pro' ); ?>
                        </label>
                        <input type="text" name="dox_app_name" value="<?php echo esc_attr( $app_name ); ?>" class="dox-input" style="padding:12px 16px;font-size:15px;max-width:400px;" required>
                    </div>

                    <div style="margin-bottom:32px;">
                        <label style="display:block;font-size:13px;font-weight:700;color:#111827;margin-bottom:12px;">
                            <?php _e( 'Select App Preset Color Palette', 'doken-ox-pro' ); ?>
                        </label>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;">

                            <!-- Preset 1: SpaceRemit Coral -->
                            <label style="cursor:pointer;display:block;">
                                <input type="radio" name="dox_app_preset" value="spaceremit_coral" checked style="display:none;" class="dox-preset-radio">
                                <div class="dox-preset-card" style="border:2px solid var(--dox-primary);border-radius:12px;padding:16px;background:#FFFFFF;transition:all .2s ease;">
                                    <div style="display:flex;gap:6px;margin-bottom:10px;">
                                        <div style="width:24px;height:24px;border-radius:6px;background:#FF385C;"></div>
                                        <div style="width:24px;height:24px;border-radius:6px;background:#111827;"></div>
                                        <div style="width:24px;height:24px;border-radius:6px;background:#06B6D4;"></div>
                                    </div>
                                    <div style="font-weight:800;color:#111827;font-size:13px;">SpaceRemit Coral</div>
                                    <div style="font-size:11px;color:#6B7280;">High-impact Modern</div>
                                </div>
                            </label>

                            <!-- Preset 2: Indigo Night -->
                            <label style="cursor:pointer;display:block;">
                                <input type="radio" name="dox_app_preset" value="indigo_night" style="display:none;" class="dox-preset-radio">
                                <div class="dox-preset-card" style="border:2px solid #E5E7EB;border-radius:12px;padding:16px;background:#FFFFFF;transition:all .2s ease;">
                                    <div style="display:flex;gap:6px;margin-bottom:10px;">
                                        <div style="width:24px;height:24px;border-radius:6px;background:#6366F1;"></div>
                                        <div style="width:24px;height:24px;border-radius:6px;background:#A855F7;"></div>
                                        <div style="width:24px;height:24px;border-radius:6px;background:#06B6D4;"></div>
                                    </div>
                                    <div style="font-weight:800;color:#111827;font-size:13px;">Indigo Night</div>
                                    <div style="font-size:11px;color:#6B7280;">Tech & Luxury</div>
                                </div>
                            </label>

                            <!-- Preset 3: Emerald Store -->
                            <label style="cursor:pointer;display:block;">
                                <input type="radio" name="dox_app_preset" value="emerald_store" style="display:none;" class="dox-preset-radio">
                                <div class="dox-preset-card" style="border:2px solid #E5E7EB;border-radius:12px;padding:16px;background:#FFFFFF;transition:all .2s ease;">
                                    <div style="display:flex;gap:6px;margin-bottom:10px;">
                                        <div style="width:24px;height:24px;border-radius:6px;background:#10B981;"></div>
                                        <div style="width:24px;height:24px;border-radius:6px;background:#059669;"></div>
                                        <div style="width:24px;height:24px;border-radius:6px;background:#3B82F6;"></div>
                                    </div>
                                    <div style="font-weight:800;color:#111827;font-size:13px;">Emerald Green</div>
                                    <div style="font-size:11px;color:#6B7280;">Organic & Fresh</div>
                                </div>
                            </label>

                        </div>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=doken-ox-pro-wizard&step=2' ) ); ?>" class="dox-btn dox-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;">
                            <span class="dashicons dashicons-arrow-left-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                            <span><?php _e( 'Back', 'doken-ox-pro' ); ?></span>
                        </a>
                        <button type="submit" class="dox-btn dox-btn-primary" style="padding:12px 28px;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
                            <span><?php _e( 'Next: License & Launch', 'doken-ox-pro' ); ?></span>
                            <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                        </button>
                    </div>

                <!-- STEP 4: Ready & Launch -->
                <?php elseif ( $step === 4 ) : ?>
                    <div style="text-align:center;padding:20px 0;">
                        <div style="width:72px;height:72px;border-radius:50%;background:#ECFDF5;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px;color:#10B981;">
                            <span class="dashicons dashicons-yes-alt" style="font-size:36px;width:36px;height:36px;"></span>
                        </div>
                        <h2 style="font-size:24px;font-weight:900;color:#111827;margin:0 0 10px;">
                            <?php _e( 'All Set! Your Platform is Ready', 'doken-ox-pro' ); ?>
                        </h2>
                        <p style="font-size:14px;color:#6B7280;max-width:520px;margin:0 auto 28px;line-height:1.6;">
                            <?php _e( 'Your WordPress store is now fully configured as a backend for Flutter applications. Endpoints are active under /wp-json/mvapp/v1/.', 'doken-ox-pro' ); ?>
                        </p>

                        <div style="max-width:440px;margin:0 auto 32px;text-align:left;">
                            <label style="display:block;font-size:12.5px;font-weight:700;color:#111827;margin-bottom:6px;">
                                <?php _e( 'License Key (Optional on Localhost)', 'doken-ox-pro' ); ?>
                            </label>
                            <input type="text" name="dox_license_key" placeholder="DOX-PRO-XXXX-XXXX" class="dox-input" style="padding:10px 14px;font-family:var(--dox-font-mono);">
                            <div style="font-size:11px;color:#9CA3AF;margin-top:4px;">
                                <?php _e( 'You can enter a key or skip to run in full local developer mode.', 'doken-ox-pro' ); ?>
                            </div>
                        </div>

                        <div style="display:flex;justify-content:center;gap:14px;align-items:center;">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=doken-ox-pro-wizard&step=3' ) ); ?>" class="dox-btn dox-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;">
                                <span class="dashicons dashicons-arrow-left-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                                <span><?php _e( 'Back', 'doken-ox-pro' ); ?></span>
                            </a>
                            <button type="submit" class="dox-btn dox-btn-primary" style="padding:14px 36px;font-size:15px;font-weight:800;display:inline-flex;align-items:center;gap:8px;">
                                <span><?php _e( 'Launch Dashboard', 'doken-ox-pro' ); ?></span>
                                <span class="dashicons dashicons-controls-forward" style="font-size:16px;width:16px;height:16px;"></span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

            </form>

        </div>

    </div>

</div>

<script>
// Mode selector cards visual toggle
document.querySelectorAll('.dox-wizard-radio').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.dox-wizard-mode-card').forEach(function(card) {
            card.style.borderColor = '#E5E7EB';
        });
        if (this.checked) {
            this.closest('label').querySelector('.dox-wizard-mode-card').style.borderColor = 'var(--dox-primary)';
        }
    });
});

// Preset palette selector visual toggle
document.querySelectorAll('.dox-preset-radio').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.dox-preset-card').forEach(function(card) {
            card.style.borderColor = '#E5E7EB';
        });
        if (this.checked) {
            this.closest('label').querySelector('.dox-preset-card').style.borderColor = 'var(--dox-primary)';
        }
    });
});
</script>
