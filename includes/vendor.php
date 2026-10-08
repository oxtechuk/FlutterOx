<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once DOKEN_OX_PRO_PATH . 'includes/helpers/response-helpers.php';
require_once DOKEN_OX_PRO_PATH . 'includes/helpers/validation-helpers.php';
require_once DOKEN_OX_PRO_PATH . 'includes/helpers/formatting-helpers.php';

/**
 * Retrieve current vendor user or WP_Error.
 *
 * @return WP_User|WP_Error
 */
function dox_vendor_current_user() {
    $user = wp_get_current_user();
    if ( ! $user || 0 === $user->ID ) {
        return new WP_Error( 'dox_unauthenticated', __( 'Not authenticated.', 'doken-ox-pro' ), array( 'status' => 401 ) );
    }

    if ( ! in_array( 'seller', (array) $user->roles, true ) && ! in_array( 'vendor', (array) $user->roles, true ) && ! user_can( $user, 'manage_woocommerce' ) ) {
        return new WP_Error( 'dox_forbidden', __( 'Vendor access only.', 'doken-ox-pro' ), array( 'status' => 403 ) );
    }

    return $user;
}

/**
 * Ensure Dokan helpers exist.
 */
function dox_vendor_require_dokan() {
    if ( ! function_exists( 'dokan' ) ) {
        return new WP_Error( 'dox_dokan_missing', __( 'Dokan plugin is required.', 'doken-ox-pro' ), array( 'status' => 500 ) );
    }
    return true;
}

/**
 * Vendor authentication: login.
 */
function dox_vendor_auth_login( $request ) {
    if ( empty( $request->get_param( 'role' ) ) ) {
        $user_type = $request->get_param( 'user_type' );
        $request->set_param( 'role', ! empty( $user_type ) ? $user_type : 'vendor' );
    }

    $response = Doken_Ox_Auth::login( $request );

    if ( $response instanceof WP_REST_Response ) {
        $data = $response->get_data();
        if ( ! empty( $data['success'] ) ) {
            $role_string = $data['data']['user']['role'] ?? '';
            if ( false === strpos( $role_string, 'seller' ) && false === strpos( $role_string, 'vendor' ) && false === strpos( $role_string, 'administrator' ) && false === strpos( $role_string, 'dokan_vendor' ) ) {
                return dox_response( false, __( 'User is not a vendor.', 'doken-ox-pro' ), array(), array(), 403 );
            }
        }
    }

    return $response;
}

/**
 * Vendor authentication: register.
 */
function dox_vendor_auth_register( $request ) {
    $payload = wp_parse_args(
        $request->get_json_params(),
        array(
            'email'      => '',
            'password'   => '',
            'name'       => '',
            'store_name' => '',
            'phone'      => '',
            'address'    => '',
            'role'       => '',
            'user_type'  => '',
        )
    );

    $required = dox_require_params( $payload, array( 'email', 'password', 'name', 'store_name' ) );
    if ( is_wp_error( $required ) ) {
        return dox_error_response( $required->get_error_message(), $required->get_error_data()['status'] ?? 400 );
    }

    $role = sanitize_text_field( $payload['role'] );
    if ( empty( $role ) && ! empty( $payload['user_type'] ) ) {
        $role = sanitize_text_field( $payload['user_type'] );
    }

    if ( ! in_array( $role, [ 'vendor', 'seller' ] ) ) {
        $role = 'vendor';
    }

    // Dokan registers the role as 'seller'. We must map 'vendor' to 'seller' for WP.
    $wp_role = ( $role === 'vendor' ) ? 'seller' : $role;

    if ( email_exists( $payload['email'] ) ) {
        return dox_error_response( __( 'Email already exists.', 'doken-ox-pro' ) );
    }

    $user_id = wp_create_user( $payload['email'], $payload['password'], $payload['email'] );
    if ( is_wp_error( $user_id ) ) {
        return dox_error_response( $user_id->get_error_message() );
    }

    wp_update_user(
        array(
            'ID'           => $user_id,
            'display_name' => sanitize_text_field( $payload['name'] ),
        )
    );

    $user = new WP_User( $user_id );
    $user->set_role( $wp_role );

    update_user_meta( $user_id, 'phone', sanitize_text_field( $payload['phone'] ) );

    if ( function_exists( 'dokan' ) ) {
        dokan()->vendor->create(
            $user_id,
            array(
                'store_name'        => sanitize_text_field( $payload['store_name'] ),
                'store_ppp'         => 24,
                'address'           => sanitize_textarea_field( $payload['address'] ),
                'dokan_store_time'  => '',
                'social'            => array(),
                'phone'             => sanitize_text_field( $payload['phone'] ),
            )
        );
    }

    $tokens = Doken_Ox_JWT_Handler::generate_token( $user_id, $role );

    return dox_response(
        true,
        __( 'Vendor account created successfully.', 'doken-ox-pro' ),
        array(
            'token'         => $tokens['token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in'    => $tokens['expires_in'],
            'user'          => dox_format_user( get_user_by( 'id', $user_id ) ),
            'user_type'     => 'vendor',
        )
    );
}

/**
 * Dashboard data.
 */
function dox_vendor_dashboard( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    if ( ! function_exists( 'dokan_get_seller_earnings' ) ) {
        return dox_error_response( 'Dokan plugin is required for vendor stats.' );
    }

    $seller_id = $user->ID;
    $balance   = dokan_get_seller_earnings( $seller_id );
    
    // Get stats from Dokan
    global $wpdb;
    $orders_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT parent_id) FROM {$wpdb->prefix}dokan_orders WHERE seller_id = %d", $seller_id ) );
    $total_sales  = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(order_total) FROM {$wpdb->prefix}dokan_orders WHERE seller_id = %d", $seller_id ) );

    $data = array(
        'store' => array(
            'id'              => $seller_id,
            'name'            => dokan_get_store_info( $seller_id )['store_name'] ?? $user->display_name,
            'balance'         => wc_format_decimal( $balance, 2 ),
            'total_sales'     => wc_format_decimal( $total_sales, 2 ),
            'orders_count'    => intval( $orders_count ),
            'products_count'  => count_user_posts( $seller_id, 'product' ),
            'rating'          => dokan_get_seller_rating( $seller_id ),
        ),
        'stats_cards' => array(
            array( 'label' => 'Total Earnings', 'value' => wc_format_decimal( $balance, 2 ), 'icon' => 'payments' ),
            array( 'label' => 'Total Orders', 'value' => $orders_count, 'icon' => 'shopping_cart' ),
            array( 'label' => 'Active Products', 'value' => count_user_posts( $seller_id, 'product' ), 'icon' => 'inventory' ),
        ),
    );

    return dox_response( true, '', $data );
}

function dox_vendor_earnings( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $period = sanitize_text_field( $request->get_param( 'period' ) );
    $start_date = sanitize_text_field( $request->get_param( 'start_date' ) );
    $end_date = sanitize_text_field( $request->get_param( 'end_date' ) );

    $data = array(
        'total_earnings'  => 0,
        'pending_earnings'=> 0,
        'withdrawn'       => 0,
        'chart_data'      => array(),
        'filters'         => compact( 'period', 'start_date', 'end_date' ),
    );

    return dox_response( true, '', $data );
}

function dox_vendor_gross_sales( $request ) {
    $data = dox_vendor_earnings( $request );
    return $data;
}

function dox_vendor_stats( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $sales_chart = array();
    $orders = wc_get_orders(
        array(
            'limit'      => 30,
            'meta_key'   => '_dokan_vendor_id',
            'meta_value' => $user->ID,
            'orderby'    => 'date',
            'order'      => 'DESC',
        )
    );

    foreach ( $orders as $order ) {
        $sales_chart[] = array(
            'date'    => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
            'orders'  => 1,
            'revenue' => (float) $order->get_total(),
        );
    }

    $data = array(
        'sales_chart'       => $sales_chart,
        'top_products'      => array(),
        'top_categories'    => array(),
        'customer_demographics' => array(),
        'traffic_sources'       => array(),
    );

    return dox_response( true, '', $data );
}

/**
 * Product helpers.
 */
function dox_vendor_format_product( WC_Product $product ) {
    return array(
        'id'             => $product->get_id(),
        'name'           => $product->get_name(),
        'sku'            => $product->get_sku(),
        'price'          => wc_format_decimal( $product->get_price(), 2 ),
        'stock_quantity' => $product->get_stock_quantity(),
        'stock_status'   => $product->get_stock_status(),
        'status'         => $product->get_status(),
        'image'          => wp_get_attachment_url( $product->get_image_id() ),
        'categories'     => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) ),
        'sales_count'    => (int) $product->get_total_sales(),
        'views'          => (int) get_post_meta( $product->get_id(), 'dox_views', true ),
        'created_at'     => get_post_time( 'Y-m-d', false, $product->get_id() ),
    );
}

function dox_vendor_products_list( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $params = wp_parse_args(
        $request->get_params(),
        array(
            'status'   => 'publish',
            'category' => '',
            'search'   => '',
            'orderby'  => 'date',
            'order'    => 'DESC',
            'page'     => 1,
            'per_page' => 20,
        )
    );

    $result = Doken_Ox_Product_Model::list_products(
        array(
            'page'      => (int) $params['page'],
            'per_page'  => (int) $params['per_page'],
            'status'    => $params['status'],
            'category'  => $params['category'] ? (int) $params['category'] : null,
            'orderby'   => sanitize_text_field( $params['orderby'] ),
            'order'     => strtoupper( $params['order'] ) === 'ASC' ? 'ASC' : 'DESC',
            'vendor_id' => $user->ID,
        )
    );

    $pagination = dox_build_pagination( (int) $params['page'], (int) $params['per_page'], (int) $result['total'] );

    return dox_response( true, '', $result['items'], $pagination );
}

function dox_vendor_products_create( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $params = $request->get_json_params();

    $required = dox_require_params( $params, array( 'name', 'regular_price' ) );
    if ( is_wp_error( $required ) ) {
        return dox_error_response( $required->get_error_message(), $required->get_error_data()['status'] ?? 400 );
    }

    $product = new WC_Product_Simple();
    $product->set_name( sanitize_text_field( $params['name'] ) );
    $product->set_description( wp_kses_post( $params['description'] ?? '' ) );
    $product->set_short_description( wp_kses_post( $params['short_description'] ?? '' ) );
    $product->set_regular_price( wc_format_decimal( $params['regular_price'] ) );
    if ( ! empty( $params['sale_price'] ) ) {
        $product->set_sale_price( wc_format_decimal( $params['sale_price'] ) );
    }
    $product->set_manage_stock( ! empty( $params['manage_stock'] ) );
    $product->set_stock_quantity( intval( $params['stock_quantity'] ?? 0 ) );
    $product->set_status( sanitize_text_field( $params['status'] ?? 'publish' ) );
    $product->set_sku( sanitize_text_field( $params['sku'] ?? '' ) );
    $product->set_weight( sanitize_text_field( $params['weight'] ?? '' ) );

    if ( ! empty( $params['categories'] ) && is_array( $params['categories'] ) ) {
        $product->set_category_ids( array_map( 'intval', $params['categories'] ) );
    }

    // Handle Attributes
    if ( ! empty( $params['attributes'] ) && is_array( $params['attributes'] ) ) {
        $attributes_data = array();
        foreach ( $params['attributes'] as $taxonomy => $options ) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name( $taxonomy );
            $attribute->set_options( (array) $options );
            $attribute->set_visible( true );
            $attribute->set_variation( false ); // Simple product for now
            $attributes_data[] = $attribute;
        }
        $product->set_attributes( $attributes_data );
    }

    $product->save();

    // Ensure Dokan compatibility
    update_post_meta( $product->get_id(), '_dokan_vendor_id', $user->ID );
    wp_update_post(
        array(
            'ID'          => $product->get_id(),
            'post_author' => $user->ID,
        )
    );

    if ( ! empty( $params['images'] ) && is_array( $params['images'] ) ) {
        $image_ids = array();
        foreach ( $params['images'] as $image_base64 ) {
            $attachment_id = dox_vendor_handle_base64_image( $image_base64 );
            if ( $attachment_id ) {
                $image_ids[] = $attachment_id;
            }
        }
        if ( ! empty( $image_ids ) ) {
            $product->set_image_id( array_shift( $image_ids ) );
            $product->set_gallery_image_ids( $image_ids );
            $product->save();
        }
    }

    return dox_response(
        true,
        __( 'Product created successfully.', 'doken-ox-pro' ),
        array(
            'product_id' => $product->get_id(),
        )
    );
}

function dox_vendor_products_get( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $product_id = intval( $request['id'] ?? 0 );
    $product    = wc_get_product( $product_id );
    if ( ! $product || (int) $product->get_meta( '_dokan_vendor_id' ) !== $user->ID && (int) get_post_field( 'post_author', $product_id ) !== $user->ID ) {
        return dox_error_response( __( 'Product not found.', 'doken-ox-pro' ), 404 );
    }

    return dox_response( true, '', dox_vendor_format_product( $product ) );
}

function dox_vendor_products_update( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $product_id = intval( $request['id'] ?? 0 );
    $product    = wc_get_product( $product_id );
    if ( ! $product || (int) get_post_field( 'post_author', $product_id ) !== $user->ID ) {
        return dox_error_response( __( 'Product not found.', 'doken-ox-pro' ), 404 );
    }

    $params = $request->get_json_params();
    if ( isset( $params['name'] ) ) {
        $product->set_name( sanitize_text_field( $params['name'] ) );
    }
    if ( isset( $params['regular_price'] ) ) {
        $product->set_regular_price( wc_format_decimal( $params['regular_price'] ) );
    }
    if ( isset( $params['sale_price'] ) ) {
        $product->set_sale_price( wc_format_decimal( $params['sale_price'] ) );
    }
    if ( isset( $params['stock_quantity'] ) ) {
        $product->set_stock_quantity( intval( $params['stock_quantity'] ) );
    }
    if ( isset( $params['status'] ) ) {
        $product->set_status( sanitize_text_field( $params['status'] ) );
    }

    $product->save();

    return dox_response( true, __( 'Product updated.', 'doken-ox-pro' ), array( 'product_id' => $product->get_id() ) );
}

function dox_vendor_products_delete( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $product_id = intval( $request['id'] ?? 0 );
    $product    = wc_get_product( $product_id );
    if ( ! $product || (int) get_post_field( 'post_author', $product_id ) !== $user->ID ) {
        return dox_error_response( __( 'Product not found.', 'doken-ox-pro' ), 404 );
    }

    wp_trash_post( $product_id );

    return dox_response( true, __( 'Product deleted.', 'doken-ox-pro' ) );
}

function dox_vendor_products_categories( $request ) {
    $terms = get_terms(
        array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        )
    );

    $data = array();
    foreach ( $terms as $term ) {
        $data[] = array(
            'id'   => $term->term_id,
            'name' => $term->name,
        );
    }

    return dox_response( true, '', $data );
}

function dox_vendor_products_upload_images( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $product_id = intval( $request['id'] ?? 0 );
    $product    = wc_get_product( $product_id );
    if ( ! $product || (int) get_post_field( 'post_author', $product_id ) !== $user->ID ) {
        return dox_error_response( __( 'Product not found.', 'doken-ox-pro' ), 404 );
    }

    $images = $request->get_json_params()['images'] ?? array();
    $ids    = array();
    foreach ( (array) $images as $img ) {
        $attachment_id = dox_vendor_handle_base64_image( $img );
        if ( $attachment_id ) {
            $ids[] = $attachment_id;
        }
    }

    if ( ! empty( $ids ) ) {
        $product->set_image_id( array_shift( $ids ) );
        $product->set_gallery_image_ids( $ids );
        $product->save();
    }

    return dox_response( true, __( 'Images uploaded.', 'doken-ox-pro' ) );
}

function dox_vendor_products_update_stock( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $product_id = intval( $request['id'] ?? 0 );
    $product    = wc_get_product( $product_id );
    if ( ! $product || (int) get_post_field( 'post_author', $product_id ) !== $user->ID ) {
        return dox_error_response( __( 'Product not found.', 'doken-ox-pro' ), 404 );
    }

    $params = $request->get_json_params();

    if ( isset( $params['stock_quantity'] ) ) {
        $product->set_stock_quantity( intval( $params['stock_quantity'] ) );
    }
    if ( isset( $params['stock_status'] ) ) {
        $product->set_stock_status( sanitize_text_field( $params['stock_status'] ) );
    }
    $product->save();

    return dox_response( true, __( 'Stock updated.', 'doken-ox-pro' ) );
}

function dox_vendor_products_import( $request ) {
    return dox_response( true, __( 'Import scheduled.', 'doken-ox-pro' ) );
}

function dox_vendor_products_export( $request ) {
    return dox_response( true, '', array( 'export_url' => admin_url( 'edit.php?post_type=product&page=dokan-export' ) ) );
}

/**
 * Orders.
 */
function dox_vendor_orders_list( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $params = wp_parse_args(
        $request->get_params(),
        array(
            'status'   => '',
            'customer' => '',
            'date_from'=> '',
            'date_to'  => '',
            'page'     => 1,
            'per_page' => 20,
        )
    );

    $args = array(
        'limit'      => (int) $params['per_page'],
        'page'       => (int) $params['page'],
        'meta_key'   => '_dokan_vendor_id',
        'meta_value' => $user->ID,
    );
    if ( ! empty( $params['status'] ) ) {
        $args['status'] = $params['status'];
    }

    $orders = wc_get_orders( $args );
    $data   = array();
    foreach ( $orders as $order ) {
        $data[] = array(
            'id'            => $order->get_id(),
            'order_number'  => $order->get_order_number(),
            'customer'      => array(
                'id'    => $order->get_user_id(),
                'name'  => $order->get_formatted_billing_full_name(),
                'email' => $order->get_billing_email(),
            ),
            'status'        => $order->get_status(),
            'date_created'  => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
            'total'         => $order->get_total(),
            'items_count'   => count( $order->get_items() ),
            'payment_method'=> $order->get_payment_method(),
            'shipping_method'=> $order->get_shipping_method(),
        );
    }

    return dox_response( true, '', $data );
}

function dox_vendor_orders_get( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $order = wc_get_order( intval( $request['id'] ?? 0 ) );
    if ( ! $order ) {
        return dox_error_response( __( 'Order not found.', 'doken-ox-pro' ), 404 );
    }

    $items = array();
    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        $items[] = array(
            'product_id' => $product ? $product->get_id() : 0,
            'name'       => $item->get_name(),
            'quantity'   => $item->get_quantity(),
            'total'      => $item->get_total(),
        );
    }

    $data = array(
        'id'              => $order->get_id(),
        'order_number'    => $order->get_order_number(),
        'status'          => $order->get_status(),
        'items'           => $items,
        'customer'        => array(
            'id'    => $order->get_user_id(),
            'name'  => $order->get_formatted_billing_full_name(),
            'email' => $order->get_billing_email(),
        ),
        'billing'         => $order->get_address( 'billing' ),
        'shipping'        => $order->get_address( 'shipping' ),
        'totals'          => array(
            'subtotal' => $order->get_subtotal(),
            'shipping' => $order->get_shipping_total(),
            'tax'      => $order->get_total_tax(),
            'total'    => $order->get_total(),
        ),
        'notes'           => wc_get_order_notes( array( 'order_id' => $order->get_id() ) ),
        'timeline'        => array(),
        'vendor_earnings' => 0,
        'commission'      => 0,
    );

    return dox_response( true, '', $data );
}

function dox_vendor_orders_update_status( $request ) {
    $order = wc_get_order( intval( $request['id'] ?? 0 ) );
    if ( ! $order ) {
        return dox_error_response( __( 'Order not found.', 'doken-ox-pro' ), 404 );
    }
    $params = $request->get_json_params();
    $status = sanitize_text_field( $params['status'] ?? '' );
    $note   = sanitize_textarea_field( $params['note'] ?? '' );
    $notify = ! empty( $params['notify_customer'] );
    if ( empty( $status ) ) {
        return dox_error_response( __( 'Status is required.', 'doken-ox-pro' ) );
    }
    $order->update_status( $status, $note, $notify );
    return dox_response( true, __( 'Order status updated.', 'doken-ox-pro' ) );
}

function dox_vendor_orders_latest( $request ) {
    $request->set_param( 'per_page', $request->get_param( 'limit' ) ?? 5 );
    return dox_vendor_orders_list( $request );
}

function dox_vendor_orders_add_note( $request ) {
    $order = wc_get_order( intval( $request['id'] ?? 0 ) );
    if ( ! $order ) {
        return dox_error_response( __( 'Order not found.', 'doken-ox-pro' ), 404 );
    }

    $params = $request->get_json_params();
    $note   = sanitize_textarea_field( $params['note'] ?? '' );
    $customer_note = ! empty( $params['customer_note'] );

    if ( empty( $note ) ) {
        return dox_error_response( __( 'Note is required.', 'doken-ox-pro' ) );
    }

    $order->add_order_note( $note, $customer_note );

    return dox_response( true, __( 'Note added.', 'doken-ox-pro' ) );
}

function dox_vendor_orders_invoice( $request ) {
    return dox_response( true, '', array( 'invoice_url' => '#' ) );
}

function dox_vendor_orders_export( $request ) {
    return dox_response( true, '', array( 'export_url' => '#' ) );
}

/**
 * Reviews.
 */
function dox_vendor_reviews_list( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $params = wp_parse_args(
        $request->get_params(),
        array(
            'status'     => 'approve',
            'product_id' => 0,
            'rating'     => 0,
            'page'       => 1,
            'per_page'   => 20,
        )
    );

    $args = array(
        'number'  => (int) $params['per_page'],
        'paged'   => (int) $params['page'],
        'status'  => $params['status'],
        'type'    => 'review',
        'post_type' => 'product',
        'meta_query' => array(
            array(
                'key'   => '_dokan_vendor_id',
                'value' => $user->ID,
            ),
        ),
    );

    if ( ! empty( $params['product_id'] ) ) {
        $args['post_id'] = (int) $params['product_id'];
    }

    $comments = get_comments( $args );
    $data = array();
    foreach ( $comments as $comment ) {
        $data[] = array(
            'id'        => $comment->comment_ID,
            'product_id'=> $comment->comment_post_ID,
            'author'    => $comment->comment_author,
            'email'     => $comment->comment_author_email,
            'rating'    => (int) get_comment_meta( $comment->comment_ID, 'rating', true ),
            'title'     => get_comment_meta( $comment->comment_ID, 'title', true ),
            'content'   => $comment->comment_content,
            'status'    => $comment->comment_approved,
            'date'      => $comment->comment_date,
            'verified_purchase' => wc_customer_bought_product( $comment->comment_author_email, $comment->user_id, $comment->comment_post_ID ),
        );
    }

    return dox_response( true, '', $data );
}

/**
 * Notifications for vendor.
 */
function dox_vendor_notifications( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $page = max( 1, (int) $request->get_param( 'page' ) );
    $per_page = max( 1, (int) $request->get_param( 'per_page' ) ?: 20 );

    $result = Doken_Ox_Notification_Handler::get_for_user( $user->ID, $page, $per_page );

    return dox_response( true, '', $result['items'], $result['pagination'] );
}

/**
 * Utility to handle base64 images.
 */
function dox_vendor_handle_base64_image( $base64 ) {
    if ( empty( $base64 ) ) {
        return false;
    }

    if ( strpos( $base64, 'base64,' ) !== false ) {
        $base64 = explode( 'base64,', $base64 )[1];
    }

    $decoded = base64_decode( $base64 );
    if ( false === $decoded ) {
        return false;
    }

    $upload = wp_upload_bits( 'vendor_' . time() . '.png', null, $decoded );
    if ( ! empty( $upload['error'] ) ) {
        return false;
    }

    $file = $upload['file'];
    $wp_filetype = wp_check_filetype( $file, null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name( basename( $file ) ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    );
    $attach_id = wp_insert_attachment( $attachment, $file );
    if ( ! is_wp_error( $attach_id ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        return $attach_id;
    }

    return false;
}

/**
 * Register vendor routes.
 */
function dox_vendor_register_routes( $namespace ) {
    $auth = array( 'Doken_Ox_API_Router', 'permission_authenticated' );

    // Auth.
    register_rest_route(
        $namespace,
        '/vendor/auth/login',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_vendor_auth_login',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/auth/register',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_vendor_auth_register',
            'permission_callback' => '__return_true',
        )
    );

    // Dashboard.
    register_rest_route(
        $namespace,
        '/vendor/dashboard',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_dashboard',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/earnings',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_earnings',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/gross-sales',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_gross_sales',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/stats',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_stats',
            'permission_callback' => $auth,
        )
    );

    // Products.
    register_rest_route(
        $namespace,
        '/vendor/products',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_vendor_products_list',
                'permission_callback' => $auth,
            ),
            array(
                'methods'             => 'POST',
                'callback'            => 'dox_vendor_products_create',
                'permission_callback' => $auth,
            ),
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/products/categories',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_products_categories',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/products/(?P<id>\d+)',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_vendor_products_get',
                'permission_callback' => $auth,
            ),
            array(
                'methods'             => 'PUT',
                'callback'            => 'dox_vendor_products_update',
                'permission_callback' => $auth,
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => 'dox_vendor_products_delete',
                'permission_callback' => $auth,
            ),
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/products/(?P<id>\d+)/images',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_vendor_products_upload_images',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/products/(?P<id>\d+)/stock',
        array(
            'methods'             => 'PUT',
            'callback'            => 'dox_vendor_products_update_stock',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/products/import',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_vendor_products_import',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/products/export',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_products_export',
            'permission_callback' => $auth,
        )
    );

    // Orders.
    register_rest_route(
        $namespace,
        '/vendor/orders',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_orders_list',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/orders/(?P<id>\d+)',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_orders_get',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/orders/(?P<id>\d+)/status',
        array(
            'methods'             => 'PUT',
            'callback'            => 'dox_vendor_orders_update_status',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/orders/latest',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_orders_latest',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/orders/(?P<id>\d+)/notes',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_vendor_orders_add_note',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/orders/(?P<id>\d+)/invoice',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_orders_invoice',
            'permission_callback' => $auth,
        )
    );

    register_rest_route(
        $namespace,
        '/vendor/orders/export',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_orders_export',
            'permission_callback' => $auth,
        )
    );

    // Reviews.
    register_rest_route(
        $namespace,
        '/vendor/reviews',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_reviews_list',
            'permission_callback' => $auth,
        )
    );

    // Notifications.
    register_rest_route(
        $namespace,
        '/vendor/notifications',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_vendor_notifications',
            'permission_callback' => $auth,
        )
    );

    // Withdrawals.
    register_rest_route(
        $namespace,
        '/vendor/withdrawals',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_vendor_get_withdrawals',
                'permission_callback' => $auth,
            ),
            array(
                'methods'             => 'POST',
                'callback'            => 'dox_vendor_request_withdrawal',
                'permission_callback' => $auth,
            ),
        )
    );

    // Settings.
    register_rest_route(
        $namespace,
        '/vendor/settings',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_vendor_get_settings',
                'permission_callback' => $auth,
            ),
            array(
                'methods'             => 'POST',
                'callback'            => 'dox_vendor_update_settings',
                'permission_callback' => $auth,
            ),
        )
    );
}

/* ---------------------------
   Withdrawals Endpoints
   --------------------------- */

function dox_vendor_get_withdrawals( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) return dox_error_response( $user->get_error_message() );

    global $wpdb;
    $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dokan_withdraw WHERE user_id = %d ORDER BY id DESC", $user->ID ) );
    
    return dox_response( true, '', $results );
}

function dox_vendor_request_withdrawal( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) return dox_error_response( $user->get_error_message() );

    $amount = floatval( $request->get_param('amount') );
    $method = sanitize_text_field( $request->get_param('method') );

    if ( $amount <= 0 ) return dox_error_response( 'Invalid amount' );

    // Dokan logic for withdrawal request
    $args = array(
        'user_id' => $user->ID,
        'amount'  => $amount,
        'method'  => $method,
        'notes'   => 'Request from Mobile App',
    );

    $withdraw = dokan()->withdraw->create( $args );
    if ( is_wp_error( $withdraw ) ) return dox_error_response( $withdraw->get_error_message() );

    return dox_response( true, 'Withdrawal request submitted', array() );
}

/* ---------------------------
   Settings Endpoints
   --------------------------- */

function dox_vendor_get_settings( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) return dox_error_response( $user->get_error_message() );

    $info = dokan_get_store_info( $user->ID );
    return dox_response( true, '', $info );
}

function dox_vendor_update_settings( $request ) {
    $user = dox_vendor_current_user();
    if ( is_wp_error( $user ) ) return dox_error_response( $user->get_error_message() );

    $params = $request->get_json_params();
    $info = dokan_get_store_info( $user->ID );

    if ( isset( $params['store_name'] ) ) $info['store_name'] = sanitize_text_field( $params['store_name'] );
    if ( isset( $params['phone'] ) ) $info['phone'] = sanitize_text_field( $params['phone'] );
    
    update_user_meta( $user->ID, 'dokan_profile_settings', $info );

    return dox_response( true, 'Settings updated', $info );
}

add_action( 'doken_ox_register_routes', 'dox_vendor_register_routes' );
