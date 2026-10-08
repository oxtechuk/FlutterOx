<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Profile_Handler {

    /**
     * Change Password
     * PUT /mvapp/v1/profile/password
     */
    public static function change_password( $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $current_password = $params['current_password'] ?? '';
        $new_password = $params['new_password'] ?? '';

        if ( empty( $current_password ) || empty( $new_password ) ) {
            return dox_error_response( __( 'Current and new password are required.', 'doken-ox-pro' ) );
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user || ! wp_check_password( $current_password, $user->data->user_pass, $user_id ) ) {
             return dox_error_response( __( 'Incorrect current password.', 'doken-ox-pro' ) );
        }

        wp_set_password( $new_password, $user_id );

        return dox_response( true, __( 'Password changed successfully.', 'doken-ox-pro' ) );
    }

    /**
     * Get Addresses
     * GET /mvapp/v1/profile/addresses
     */
    public static function get_addresses( $request ) {
        $user_id = get_current_user_id();
        $customer = new WC_Customer( $user_id );

        return dox_response( true, '', array(
            'billing' => $customer->get_billing(),
            'shipping' => $customer->get_shipping(),
        ));
    }

    /**
     * Update Addresses
     * POST /mvapp/v1/profile/addresses
     */
    public static function update_addresses( $request ) {
        $user_id = get_current_user_id();
        $customer = new WC_Customer( $user_id );
        $params = $request->get_json_params();

        if ( isset( $params['billing'] ) ) {
            foreach ( $params['billing'] as $key => $value ) {
                $method = 'set_billing_' . $key;
                if ( is_callable( array( $customer, $method ) ) ) {
                    $customer->$method( sanitize_text_field( $value ) );
                }
            }
        }

        if ( isset( $params['shipping'] ) ) {
            foreach ( $params['shipping'] as $key => $value ) {
                $method = 'set_shipping_' . $key;
                if ( is_callable( array( $customer, $method ) ) ) {
                    $customer->$method( sanitize_text_field( $value ) );
                }
            }
        }

        $customer->save();

        return dox_response( true, __( 'Addresses updated.', 'doken-ox-pro' ), array(
            'billing' => $customer->get_billing(),
            'shipping' => $customer->get_shipping(),
        ));
    }

    /**
     * Get Settings (Language, etc)
     * GET /mvapp/v1/settings
     */
    public static function get_settings( $request ) {
        // Return default settings or user preferences
        return dox_response( true, '', array(
            'languages' => array(
                array( 'code' => 'en', 'name' => 'English', 'default' => true ),
                array( 'code' => 'ar', 'name' => 'Arabic', 'default' => false ),
            ),
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
        ));
    }
    
    /**
     * Help Page / Static Pages
     * GET /mvapp/v1/pages/{slug}
     */
    public static function get_page( $request ) {
        $slug = sanitize_text_field( $request['slug'] );
        $page = get_page_by_path( $slug );
        
        if ( ! $page ) {
            return dox_error_response( __( 'Page not found.', 'doken-ox-pro' ), 404 );
        }
        
        return dox_response( true, '', array(
            'title' => $page->post_title,
            'content' => apply_filters( 'the_content', $page->post_content ),
        ));
    }
}

function dox_profile_register_routes( $namespace ) {
    $auth = array( 'Doken_Ox_API_Router', 'permission_authenticated' );

    register_rest_route( $namespace, '/profile/password', array(
        'methods' => 'PUT',
        'callback' => array( 'Doken_Ox_Profile_Handler', 'change_password' ),
        'permission_callback' => $auth,
    ));

    register_rest_route( $namespace, '/profile/addresses', array(
        array(
            'methods' => 'GET',
            'callback' => array( 'Doken_Ox_Profile_Handler', 'get_addresses' ),
            'permission_callback' => $auth,
        ),
        array(
            'methods' => 'POST',
            'callback' => array( 'Doken_Ox_Profile_Handler', 'update_addresses' ),
            'permission_callback' => $auth,
        ),
    ));

    register_rest_route( $namespace, '/settings', array(
        'methods' => 'GET',
        'callback' => array( 'Doken_Ox_Profile_Handler', 'get_settings' ),
        'permission_callback' => '__return_true', // Public settings
    ));
    
    register_rest_route( $namespace, '/pages/(?P<slug>[a-zA-Z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => array( 'Doken_Ox_Profile_Handler', 'get_page' ),
        'permission_callback' => '__return_true',
    ));
}
add_action( 'doken_ox_register_routes', 'dox_profile_register_routes' );
