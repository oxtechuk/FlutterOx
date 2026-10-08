<?php
/**
 * Doken Ox Pro — Development & API Explorer View
 * Interactive REST API documentation, testing console, and system diagnostics.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_dokan = class_exists( 'Doken_Ox_Plugin_Mode' ) && Doken_Ox_Plugin_Mode::is_dokan_mode();
$rest_root = esc_url_raw( rest_url( 'mvapp/v1' ) );
$nonce = wp_create_nonce( 'wp_rest' );

// API Endpoints Catalog
$api_groups = array(
    'App Config & Theming (Public)' => array(
        array( 'method' => 'GET',  'route' => '/app/config',          'desc' => 'Fetch full app theme, colors, typography, features & URLs for Flutter boot', 'auth' => 'Public' ),
        array( 'method' => 'POST', 'route' => '/app/config',          'desc' => 'Update app theme config JSON (Admin only)', 'auth' => 'Admin' ),
        array( 'method' => 'GET',  'route' => '/app/config/presets',  'desc' => 'Get curated palette presets (Indigo, Midnight, Coral, Ocean, etc.)', 'auth' => 'Public' ),
        array( 'method' => 'GET',  'route' => '/app/config/colors',   'desc' => 'Get active brand color palette directly', 'auth' => 'Public' ),
    ),
    'Authentication & User' => array(
        array( 'method' => 'POST', 'route' => '/auth/login',           'desc' => 'Authenticate user via username/email & password. Returns JWT token & role.', 'auth' => 'Public' ),
        array( 'method' => 'POST', 'route' => '/auth/register',        'desc' => 'Register new customer or vendor with validation & auto-login.', 'auth' => 'Public' ),
        array( 'method' => 'POST', 'route' => '/auth/refresh',         'desc' => 'Exchange refresh_token for a fresh access JWT token without prompt.', 'auth' => 'Public' ),
        array( 'method' => 'GET',  'route' => '/auth/me',              'desc' => 'Get current authenticated user profile, avatar, and capabilities.', 'auth' => 'JWT Bearer' ),
        array( 'method' => 'PUT',  'route' => '/auth/profile',         'desc' => 'Update user profile (display name, email, phone, billing address).', 'auth' => 'JWT Bearer' ),
        array( 'method' => 'POST', 'route' => '/auth/send-otp',        'desc' => 'Send one-time SMS / Email verification code for passwordless login.', 'auth' => 'Public' ),
        array( 'method' => 'POST', 'route' => '/auth/verify-otp',      'desc' => 'Verify OTP token and issue JWT session token.', 'auth' => 'Public' ),
    ),
    'Storefront & Catalog' => array(
        array( 'method' => 'GET',  'route' => '/home',                 'desc' => 'Complete home layout payload (banners, categories, flash deals, grids).', 'auth' => 'Public' ),
        array( 'method' => 'GET',  'route' => '/products',             'desc' => 'Paginated product catalog with filtering, sorting, category, and vendor params.', 'auth' => 'Public' ),
        array( 'method' => 'GET',  'route' => '/products/{id}',        'desc' => 'Single product detail with gallery, variations, stock, and vendor metadata.', 'auth' => 'Public' ),
        array( 'method' => 'GET',  'route' => '/search',               'desc' => 'Instant search query matching products, categories, and tags.', 'auth' => 'Public' ),
    ),
    'Cart & Checkout' => array(
        array( 'method' => 'GET',  'route' => '/cart',                 'desc' => 'Retrieve active shopping cart items, tax calculation, coupons, and totals.', 'auth' => 'JWT Bearer' ),
        array( 'method' => 'POST', 'route' => '/cart/add',             'desc' => 'Add product item to cart with quantity and variation attributes.', 'auth' => 'JWT Bearer' ),
        array( 'method' => 'GET',  'route' => '/orders',               'desc' => 'List authenticated user orders with items, status badges, tracking info.', 'auth' => 'JWT Bearer' ),
        array( 'method' => 'POST', 'route' => '/orders',               'desc' => 'Place new order, generate order ID, and initialize payment gateway.', 'auth' => 'JWT Bearer' ),
    ),
);

if ( $is_dokan ) {
    $api_groups['Dokan Multi-Vendor Marketplace'] = array(
        array( 'method' => 'GET',  'route' => '/vendors',              'desc' => 'List registered Dokan vendor stores with ratings, logo, banner, and location.', 'auth' => 'Public' ),
        array( 'method' => 'GET',  'route' => '/vendors/{id}',         'desc' => 'Single vendor store details, reviews, address, and vendor product catalog.', 'auth' => 'Public' ),
        array( 'method' => 'GET',  'route' => '/vendor/dashboard',     'desc' => 'Vendor mobile dashboard stats (sales, orders count, withdrawable balance).', 'auth' => 'Vendor JWT' ),
        array( 'method' => 'GET',  'route' => '/vendor/products',      'desc' => 'Vendor product manager (create, edit, stock levels, delete product).', 'auth' => 'Vendor JWT' ),
        array( 'method' => 'GET',  'route' => '/vendor/orders',        'desc' => 'Vendor orders list with line items and customer details.', 'auth' => 'Vendor JWT' ),
    );
}

// System diagnostics
global $wp_version;
$system_info = array(
    'WordPress Version' => $wp_version,
    'WooCommerce'       => defined( 'WC_VERSION' ) ? WC_VERSION : 'Active',
    'Dokan Engine'      => defined( 'DOKAN_PLUGIN_VERSION' ) ? DOKAN_PLUGIN_VERSION : ( $is_dokan ? 'Enabled' : 'Disabled' ),
    'PHP Version'       => phpversion(),
    'REST Namespace'    => 'mvapp/v1',
    'Plugin Mode'       => class_exists( 'Doken_Ox_Plugin_Mode' ) ? Doken_Ox_Plugin_Mode::get_mode_label() : 'Standard',
    'License Status'    => class_exists( 'Doken_Ox_License_Handler' ) && Doken_Ox_License_Handler::is_valid() ? 'Active (Pro)' : 'Inactive',
    'Server Software'   => sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ?? 'Apache/Nginx' ),
);
$current_user = wp_get_current_user();
$username     = $current_user->display_name ?: 'SPACEREMIT';
?>

<div class="dox-page">

    <!-- Top SpaceRemit Header -->
    <div class="dox-header">
        <div style="font-size:22px;font-weight:800;color:var(--dox-text-primary);letter-spacing:-0.5px;">
            <?php _e( 'Developers', 'doken-ox-pro' ); ?>
        </div>

        <div class="dox-header-actions">
            <span class="dox-badge dox-badge-success" style="font-weight:600;">/mvapp/v1</span>

            <a href="<?php echo esc_url( rest_url( 'mvapp/v1/app/config' ) ); ?>" target="_blank" class="dox-btn dox-btn-secondary" style="padding:6px 12px;font-size:12px;">
                <span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;"></span>
                <?php _e( 'Config JSON', 'doken-ox-pro' ); ?>
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

            <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

                <!-- Left Column: Endpoints Catalog -->
                <div>
                    <?php foreach ( $api_groups as $group_title => $endpoints ) : ?>
                        <div class="dox-card">
                            <div class="dox-card-header">
                                <h3 class="dox-card-title">
                                    <span class="dashicons dashicons-rest-api"></span>
                                    <?php echo esc_html( $group_title ); ?>
                                </h3>
                                <span class="dox-badge dox-badge-muted"><?php echo count( $endpoints ); ?> routes</span>
                            </div>

                            <div style="display:flex;flex-direction:column;gap:10px;">
                                <?php foreach ( $endpoints as $ep ) :
                                    $method_class = 'dox-badge-info';
                                    if ( $ep['method'] === 'POST' ) $method_class = 'dox-badge-success';
                                    if ( $ep['method'] === 'PUT' )  $method_class = 'dox-badge-warning';
                                    if ( $ep['method'] === 'DELETE' ) $method_class = 'dox-badge-danger';

                                    $full_url = rest_url( 'mvapp/v1' . $ep['route'] );
                                ?>
                                    <div style="background:var(--dox-glass);border:1px solid var(--dox-border-subtle);border-radius:var(--dox-radius);padding:14px;transition:all .2s ease;" onmouseenter="this.style.borderColor='var(--dox-border)';" onmouseleave="this.style.borderColor='var(--dox-border-subtle)';">
                                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <span class="dox-badge <?php echo esc_attr( $method_class ); ?>" style="font-family:var(--dox-font-mono);font-size:10px;padding:3px 8px;">
                                                    <?php echo esc_html( $ep['method'] ); ?>
                                                </span>
                                                <span style="font-family:var(--dox-font-mono);font-size:13px;font-weight:700;color:var(--dox-text-primary);">
                                                    /mvapp/v1<?php echo esc_html( $ep['route'] ); ?>
                                                </span>
                                            </div>

                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <span class="dox-badge dox-badge-muted" style="font-size:10px;">
                                                    <?php echo esc_html( $ep['auth'] ); ?>
                                                </span>
                                                <button type="button" class="dox-header-icon-btn" style="width:28px;height:28px;" onclick="copyEndpoint('<?php echo esc_js( $full_url ); ?>');" title="<?php esc_attr_e( 'Copy full URL', 'doken-ox-pro' ); ?>">
                                                    <span class="dashicons dashicons-admin-page" style="font-size:13px;"></span>
                                                </button>
                                                <?php if ( $ep['method'] === 'GET' && strpos( $ep['route'], '{' ) === false ) : ?>
                                                    <a href="<?php echo esc_url( $full_url ); ?>" target="_blank" class="dox-header-icon-btn" style="width:28px;height:28px;" title="<?php esc_attr_e( 'Test in Browser', 'doken-ox-pro' ); ?>">
                                                        <span class="dashicons dashicons-external" style="font-size:13px;"></span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <p style="margin:0;font-size:12px;color:var(--dox-text-secondary);line-height:1.4;">
                                            <?php echo esc_html( $ep['desc'] ); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Right Column: System Diagnostics & Live Tester -->
                <div>
                    <!-- System Diagnostics Card -->
                    <div class="dox-card">
                        <div class="dox-card-header">
                            <h3 class="dox-card-title">
                                <span class="dashicons dashicons-info"></span>
                                <?php _e( 'System Diagnostics', 'doken-ox-pro' ); ?>
                            </h3>
                            <span class="dox-badge dox-badge-success">Healthy</span>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:12px;font-size:12.5px;">
                            <?php foreach ( $system_info as $label => $val ) : ?>
                                <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--dox-border-subtle);padding-bottom:8px;">
                                    <span style="color:var(--dox-text-secondary);"><?php echo esc_html( $label ); ?></span>
                                    <span style="font-weight:600;color:var(--dox-text-primary);font-family:var(--dox-font-mono);font-size:11.5px;">
                                        <?php echo esc_html( $val ); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quick Testing Tool -->
                    <div class="dox-card">
                        <div class="dox-card-header">
                            <h3 class="dox-card-title">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php _e( 'Quick API Ping', 'doken-ox-pro' ); ?>
                            </h3>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label style="font-size:11.5px;color:var(--dox-text-muted);display:block;margin-bottom:4px;">Endpoint</label>
                            <select id="dox-ping-endpoint" class="dox-input" style="width:100%;padding:8px;background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:var(--dox-radius);color:var(--dox-text-primary);font-size:12px;">
                                <option value="/app/config">GET /mvapp/v1/app/config</option>
                                <option value="/app/config/presets">GET /mvapp/v1/app/config/presets</option>
                                <option value="/home">GET /mvapp/v1/home</option>
                                <option value="/products">GET /mvapp/v1/products</option>
                            </select>
                        </div>

                        <button type="button" onclick="runPing();" class="dox-btn dox-btn-primary" style="width:100%;justify-content:center;padding:8px;font-size:12px;">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e( 'Send Test Request', 'doken-ox-pro' ); ?>
                        </button>

                        <div id="dox-ping-output" style="margin-top:14px;display:none;">
                            <div style="font-size:11px;color:var(--dox-text-muted);margin-bottom:4px;">Response (JSON)</div>
                            <pre id="dox-ping-json" style="background:var(--dox-bg);border:1px solid var(--dox-border);border-radius:6px;padding:10px;font-size:10.5px;font-family:var(--dox-font-mono);max-height:180px;overflow-y:auto;color:#818CF8;margin:0;"></pre>
                        </div>
                    </div>
                </div>

            </div>

        </main>
    </div>
</div>

<script>
function copyEndpoint(url) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            alert('Copied URL to clipboard:\n' + url);
        });
    } else {
        prompt('Copy URL:', url);
    }
}

function runPing() {
    var ep = document.getElementById('dox-ping-endpoint').value;
    var outBox = document.getElementById('dox-ping-output');
    var jsonPre = document.getElementById('dox-ping-json');

    outBox.style.display = 'block';
    jsonPre.textContent = 'Pinging...';

    fetch('<?php echo esc_url_raw( rest_url( "mvapp/v1" ) ); ?>' + ep)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            jsonPre.textContent = JSON.stringify(data, null, 2);
        })
        .catch(function(err) {
            jsonPre.textContent = 'Error: ' + err.message;
        });
}
</script>
