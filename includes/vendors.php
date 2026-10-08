<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Vendors_Handler {

    /**
     * List Vendors
     * GET /mvapp/v1/vendors
     */
    public static function get_vendors( $request ) {
        $page = (int) ($request->get_param('page') ?: 1);
        $per_page = (int) ($request->get_param('per_page') ?: 10);
        $search = sanitize_text_field( $request->get_param('search') );

        $args = array(
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'search' => $search,
        );

        $vendors = Doken_Ox_Vendor_Model::list_vendors( $args );
        
        return dox_response( true, '', $vendors );
    }

    /**
     * Get Vendor Details
     * GET /mvapp/v1/vendors/{id}
     */
    public static function get_vendor( $request ) {
        $id = (int) $request['id'];
        $user = get_user_by( 'id', $id );
        
        if ( ! $user ) {
             return dox_error_response( __( 'Vendor not found.', 'doken-ox-pro' ), 404 );
        }
        
        // Check if role is vendor
        if ( ! in_array( 'seller', (array) $user->roles ) && ! in_array( 'vendor', (array) $user->roles ) ) {
             return dox_error_response( __( 'User is not a vendor.', 'doken-ox-pro' ), 404 );
        }

        $data = Doken_Ox_Vendor_Model::format_vendor( $id );
        
        return dox_response( true, '', $data );
    }
    
    /**
     * Get Vendor Products
     * GET /mvapp/v1/vendors/{id}/products
     */
    public static function get_vendor_products( $request ) {
        $id = (int) $request['id'];
        $page = (int) ($request->get_param('page') ?: 1);
        $per_page = (int) ($request->get_param('per_page') ?: 10);
        
        $args = array(
            'vendor_id' => $id,
            'page' => $page,
            'per_page' => $per_page,
        );
        
        $result = Doken_Ox_Product_Model::list_products( $args );
        
        return dox_response( true, '', $result['items'] );
    }
}

function dox_vendors_register_routes( $namespace ) {
    $auth = array( 'Doken_Ox_API_Router', 'permission_authenticated' );

    register_rest_route( $namespace, '/vendors', array(
        'methods' => 'GET',
        'callback' => array( 'Doken_Ox_Vendors_Handler', 'get_vendors' ),
        'permission_callback' => '__return_true', // Public
    ));

    register_rest_route( $namespace, '/vendors/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => array( 'Doken_Ox_Vendors_Handler', 'get_vendor' ),
        'permission_callback' => '__return_true', // Public
    ));
    
    register_rest_route( $namespace, '/vendors/(?P<id>\d+)/products', array(
        'methods' => 'GET',
        'callback' => array( 'Doken_Ox_Vendors_Handler', 'get_vendor_products' ),
        'permission_callback' => '__return_true', // Public
    ));
}
add_action( 'doken_ox_register_routes', 'dox_vendors_register_routes' );
