=== Doken Ox Pro — Mobile App REST API Backend for WooCommerce & Dokan ===
Contributors: oxtech, ox-tech
Donate link: https://oxtech.uk
Tags: woocommerce, dokan, flutter, mobile app, rest api
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-performance REST API backend for Flutter & mobile apps. Supports WooCommerce & Dokan with visual theme builder and fast caching.

== Description ==

**Doken Ox Pro** transforms your WordPress website into a production-grade backend engine for Flutter, iOS, and Android eCommerce applications.

Built with performance, security, and developer experience in mind, it provides unified REST API endpoints under `/wp-json/mvapp/v1/`, complete JWT authentication, intelligent transient caching, per-IP rate limiting, and an interactive **SpaceRemit-styled Admin Dashboard**.

Whether you are running a single-merchant store or a multi-vendor Dokan marketplace, Doken Ox Pro allows you to switch operating architectures with a single click — deactivating unused vendor routes to maximize response times for single-store apps.

### Key Features

* **Dual Operating Architecture**: One-click toggle between **WooCommerce Only** (lean single-seller mobile backend) and **WooCommerce + Dokan** (full marketplace multi-vendor backend).
* **Visual App Theme Builder**: Live real-time smartphone mockup preview. Customize brand palettes, typography (Cairo, Tajawal, Inter, Roboto), features (Wishlist, Dark Mode, Reviews), and inspect output instantly.
* **Mobile Home Page Builder**: Visual drag-and-drop home layout editor supporting Hero Carousels, Category Circles, Product Grids, Flash Sale Countdowns, and Top Store Badges.
* **Enterprise Security**: HS256 JWT tokens, automated refresh tokens, per-IP rate limiting, CORS configuration, and passwordless OTP verification flows.
* **SpaceRemit Admin Experience**: Clean SaaS user interface with real-time financial stats, smooth revenue graphs, and orders control center with zero emojis.
* **High-Performance Caching**: Multi-level transient response caching with automated cache invalidation when catalog items or layouts change.
* **Flutter-Ready REST Endpoints**: Over 25 documented endpoints mirroring mobile application standards for catalog, cart, checkout, vendor stores, and user profiles.

== Installation ==

1. Upload the `doken-ox-pro` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. The interactive **Setup Wizard** will launch to guide you through:
   * Selecting your Operating Mode (WooCommerce Only vs WooCommerce + Dokan).
   * Checking dependencies and toggling optional modules (Security, Caching, Push Notifications).
   * Configuring your mobile app identity and color palettes.
4. Access the unified dashboard under **Doken Ox Pro** in your WordPress admin sidebar.
5. Point your Flutter mobile app to `https://your-domain.com/wp-json/mvapp/v1/`.

== Frequently Asked Questions ==

= Does this plugin require Dokan to work? =
No! Dokan is completely optional. If you run a standard single-merchant store, select **WooCommerce Only** mode in Settings or during the Setup Wizard. All vendor-specific overhead will be deactivated to keep mobile responses fast and lean.

= How do I connect my Flutter app? =
Your Flutter application communicates directly with the `/wp-json/mvapp/v1/` REST routes. You can inspect the live theme configuration at `/wp-json/mvapp/v1/app/config` and the home screen layout at `/wp-json/mvapp/v1/home`.

= How does JWT authentication work? =
Clients send credentials to `/wp-json/mvapp/v1/auth/login` and receive a signed JWT access token and a refresh token. Protected endpoints accept this via the `Authorization: Bearer <token>` header.

= Are mobile push notifications supported? =
Yes. You can input your Firebase Cloud Messaging (FCM) server key in **Settings** to enable order status change notifications.

= Can I customize the mobile theme without coding? =
Yes! Navigate to **Doken Ox Pro → App Theme** to customize primary colors, fonts, feature toggles, and logo branding with real-time preview inside an interactive smartphone mockup.

== Screenshots ==

1. SpaceRemit Admin Dashboard featuring financial metrics, income curve, and quick actions.
2. Visual App Theme Builder with interactive smartphone mockup preview and color palette presets.
3. Orders Control Center with order status badges and rapid order fulfillment.
4. Mobile Home Page Visual Builder with drag-and-drop layout ordering.
5. Interactive 4-step Setup Wizard for architecture mode selection and initial setup.
6. REST API Developer Explorer & Diagnostics console.

== Changelog ==

= 1.0.0 =
* Initial public release on WordPress.org.
* SpaceRemit sleek SaaS design system (pure black sidebar, white cards, capsule buttons).
* Visual App Theme Builder with live smartphone frame preview.
* Drag-and-drop Mobile Home Layout Builder.
* Single-Store (WooCommerce Only) and Marketplace (Dokan) architecture modes.
* JWT HS256 authentication and refresh token rotation.
* High-performance transient query caching for sales and dashboard statistics.
* 4-step interactive Setup Wizard.
* 100% Dashicons UI compliance with complete removal of emojis.

== Upgrade Notice ==

= 1.0.0 =
Initial production release. Fully compatible with WordPress 6.0+ and WooCommerce 7.0+.
