<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once DOKEN_OX_PRO_PATH . 'includes/helpers/response-helpers.php';

/**
 * تزود واجهة العميل ببيانات الصفحة الرئيسية.
 */
function dox_home_get_cache_bump(): int {
    $bump = (int) get_option( 'doken_ox_home_cache_bump', 1 );
    if ( $bump <= 0 ) {
        $bump = 1;
    }
    return $bump;
}

function dox_home_bump_cache(): void {
    $bump = dox_home_get_cache_bump() + 1;
    update_option( 'doken_ox_home_cache_bump', $bump );
}

function dox_home_get_payload( WP_REST_Request $request ) {
    $location = sanitize_text_field( $request->get_param( 'location' ) );
    $lang     = sanitize_text_field( $request->get_param( 'lang' ) );
    if ( empty( $lang ) ) {
        $lang = 'en';
    }

    $user_id  = get_current_user_id();
    $bump     = dox_home_get_cache_bump();
    $cache_key = 'home_payload_' . $bump . '_' . md5( $user_id . '|' . $location . '|' . $lang );

    $payload = Doken_Ox_Cache_Handler::remember(
        $cache_key,
        Doken_Ox_Home_Model::CACHE_TTL,
        function () use ( $location, $lang ) {
            $data = Doken_Ox_Home_Model::build_home_payload(
                array(
                    'location' => $location,
                    'lang'     => $lang,
                )
            );
            return $data;
        }
    );

    Doken_Ox_Home_Model::record_event( 'home', 0, 'view', array( 'location' => $location, 'lang' => $lang ) );

    return dox_response( true, '', $payload );
}

/**
 * تتبع نقرات البنرات.
 */
function dox_home_banner_click( WP_REST_Request $request ) {
    $section_id = (int) $request['section_id'];
    Doken_Ox_Home_Model::record_event( 'banners', $section_id, 'click', array( 'source' => 'app' ) );
    return dox_response( true, __( 'Click recorded.', 'doken-ox-pro' ) );
}

/**
 * Admin: list sections.
 */
function dox_home_admin_list_sections( WP_REST_Request $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $section = sanitize_key( $request->get_param( 'section' ) );
    $items   = Doken_Ox_Home_Model::get_section_items( $section ?: 'banners', array( 'status' => 0 ) );

    return dox_response( true, '', $items );
}

function dox_home_admin_create_section( WP_REST_Request $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $params = $request->get_json_params();
    $id = Doken_Ox_Home_Model::insert_section( $params );

    dox_home_bump_cache();

    return dox_response( true, __( 'Section created.', 'doken-ox-pro' ), array( 'id' => $id ) );
}

function dox_home_admin_update_section( WP_REST_Request $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $id     = (int) $request['id'];
    $params = $request->get_json_params();

    $updated = Doken_Ox_Home_Model::update_section( $id, $params );

    dox_home_bump_cache();

    return dox_response( true, $updated ? __( 'Section updated.', 'doken-ox-pro' ) : __( 'No changes applied.', 'doken-ox-pro' ) );
}

function dox_home_admin_delete_section( WP_REST_Request $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $id = (int) $request['id'];
    $deleted = Doken_Ox_Home_Model::delete_section( $id );
    dox_home_bump_cache();

    return dox_response( true, $deleted ? __( 'Section deleted.', 'doken-ox-pro' ) : __( 'Unable to delete section.', 'doken-ox-pro' ) );
}

/**
 * تسجيل المسارات الجديدة.
 */
function dox_home_register_routes( string $namespace ) {
    // public payload
    register_rest_route(
        $namespace,
        '/home',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_home_get_payload',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        $namespace,
        '/customer/home/banners/(?P<section_id>\d+)/click',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_home_banner_click',
            'permission_callback' => '__return_true',
        )
    );

    $auth = array( 'Doken_Ox_API_Router', 'permission_authenticated' );

    register_rest_route(
        $namespace,
        '/admin/home/sections',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_home_admin_list_sections',
                'permission_callback' => $auth,
            ),
            array(
                'methods'             => 'POST',
                'callback'            => 'dox_home_admin_create_section',
                'permission_callback' => $auth,
            ),
        )
    );

    register_rest_route(
        $namespace,
        '/admin/home/sections/(?P<id>\d+)',
        array(
            array(
                'methods'             => 'PUT',
                'callback'            => 'dox_home_admin_update_section',
                'permission_callback' => $auth,
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => 'dox_home_admin_delete_section',
                'permission_callback' => $auth,
            ),
        )
    );
}
add_action( 'doken_ox_register_routes', 'dox_home_register_routes' );

