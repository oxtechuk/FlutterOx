<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once DOKEN_OX_PRO_PATH . 'includes/helpers/response-helpers.php';


/**
 * System stats endpoint.
 */
function dox_admin_system_stats( $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $cached_stats = get_transient( 'dox_admin_system_stats' );
    if ( false !== $cached_stats ) {
        return dox_response( true, '', $cached_stats );
    }

    $products_count = wp_count_posts( 'product' )->publish ?? 0;
    $users_count    = count_users();
    $orders_count   = 0;
    if ( function_exists( 'wc_count_orders' ) ) {
        $c = (array) wc_count_orders();
        foreach ( array( 'processing', 'completed', 'pending', 'on-hold', 'cancelled', 'refunded', 'failed' ) as $st ) {
            $orders_count += (int) ( $c[ $st ] ?? 0 );
        }
    }
    $revenue        = 0;

    if ( class_exists( 'WC_Report_Sales_By_Date' ) ) {
        $report = new WC_Report_Sales_By_Date();
        $report->calculate_current_range( 'month' );
        $data = $report->get_report_data();
        $revenue = $data->total_sales ?? 0;
    }

    $payload = array(
        'products_count' => (int) $products_count,
        'users_count'    => (int) $users_count['total_users'],
        'orders_count'   => (int) $orders_count,
        'revenue_month'  => (float) $revenue,
        'vendors_count'  => count_users()['avail_roles']['seller'] ?? 0,
    );

    set_transient( 'dox_admin_system_stats', $payload, 15 * MINUTE_IN_SECONDS );

    return dox_response( true, '', $payload );
}

/**
 * List vendors endpoint.
 */
function dox_admin_list_vendors( $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $limit  = $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 10;
    $page   = $request->get_param( 'page' ) ? intval( $request->get_param( 'page' ) ) : 1;
    $search = $request->get_param( 'search' );
    $status = $request->get_param( 'status' ); // active, suspended
    $offset = ( $page - 1 ) * $limit;

    $args = array(
        'role__in' => array( 'seller', 'vendor' ),
        'fields'   => array( 'ID', 'user_email', 'display_name', 'user_registered' ),
        'number'   => $limit,
        'offset'   => $offset,
        'orderby'  => 'registered',
        'order'    => 'DESC',
    );

    if ( ! empty( $search ) ) {
        $args['search'] = '*' . $search . '*';
    }

    // Filter by status (using Dokan's enabling selling capability or meta)
    // Note: Dokan uses 'dokan_enable_selling' capability 'yes'/'no'.
    // For simplicity, we will check our own meta 'dox_suspended' or similar if implemented, 
    // but here we might just filter results manually or assume status is property of vendor.

    $vendors = get_users( $args );

    $data = array_map(
        function ( $vendor ) {
            $store_info = array();
            if ( function_exists( 'dokan_get_store_info' ) ) {
                 $store_info = dokan_get_store_info( $vendor->ID );
            }
            
            $is_selling_enabled = get_user_meta( $vendor->ID, 'dokan_enable_selling', true );
            $status = ( $is_selling_enabled === 'yes' ) ? 'active' : 'suspended';

            return array(
                'id'           => $vendor->ID,
                'email'        => $vendor->user_email,
                'name'         => $vendor->display_name,
                'registered'   => $vendor->user_registered,
                'store'        => $store_info,
                'status'       => $status,
                'orders_count' => wc_get_customer_order_count( $vendor->ID ), // As a customer
            );
        },
        $vendors
    );

    // If status filter is applied, we might need to filter post-query since meta query on large user base can be slow,
    // but for now, we return all and let frontend filter or rely on simple pagination. 
    // Ideally, add meta_query to $args.
    if ( ! empty( $status ) ) {
        $data = array_filter( $data, function($v) use ($status) {
            return $v['status'] === $status;
        });
        // Re-index
        $data = array_values( $data );
    }

    return dox_response( true, '', $data );
}

/**
 * List customers endpoint.
 */
function dox_admin_list_customers( $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $limit  = $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 10;
    $page   = $request->get_param( 'page' ) ? intval( $request->get_param( 'page' ) ) : 1;
    $search = $request->get_param( 'search' );
    $status = $request->get_param( 'status' );
    $offset = ( $page - 1 ) * $limit;

    $args = array(
        'role'    => 'customer',
        'fields'  => array( 'ID', 'user_email', 'display_name', 'user_registered' ),
        'number'  => $limit,
        'offset'  => $offset,
        'orderby' => 'registered',
        'order'   => 'DESC',
    );

    if ( ! empty( $search ) ) {
        $args['search'] = '*' . $search . '*';
    }
    
    // Status filtering via meta query
    if ( ! empty( $status ) && in_array( $status, array( 'active', 'suspended' ) ) ) {
        if ( $status === 'suspended' ) {
            $args['meta_key']   = 'dox_suspended';
            $args['meta_value'] = 'yes';
        } else {
             // Active means NOT suspended (key not exists or not 'yes')
             // Meta query for "NOT EXISTS" or "NOT LIKE" is heavier, 
             // but for simplicity let's assume if we filter by active we might need a complex query 
             // or just filter after fetching if the list is small, OR better:
             // standard way: 
             $args['meta_query'] = array(
                 'relation' => 'OR',
                 array(
                     'key'     => 'dox_suspended',
                     'compare' => 'NOT EXISTS',
                 ),
                 array(
                     'key'     => 'dox_suspended',
                     'value'   => 'yes',
                     'compare' => '!=',
                 ),
             );
        }
    }

    $customers = get_users( $args );

    $data = array_map(
        function ( $customer ) {
            $suspended = get_user_meta( $customer->ID, 'dox_suspended', true );
            return array(
                'id'        => $customer->ID,
                'email'     => $customer->user_email,
                'name'      => $customer->display_name,
                'registered'=> $customer->user_registered,
                'orders'    => wc_get_customer_order_count( $customer->ID ),
                'status'    => $suspended === 'yes' ? 'suspended' : 'active',
            );
        },
        $customers
    );

    return dox_response( true, '', $data );
}

/**
 * List Orders Endpoint
 */
function dox_admin_list_orders( $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $limit  = $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 10;
    $page   = $request->get_param( 'page' ) ? intval( $request->get_param( 'page' ) ) : 1;
    $status = $request->get_param( 'status' );
    $search = $request->get_param( 'search' );

    $args = array(
        'limit'    => $limit,
        'page'     => $page,
        'orderby'  => 'date',
        'order'    => 'DESC',
        'return'   => 'ids',
    );

    if ( ! empty( $status ) && $status !== 'all' ) {
        $args['status'] = $status;
    }

    if ( ! empty( $search ) ) {
        // WC search is tricky via args, but we can try basic search
        // or search by ID if numeric
        if ( is_numeric( $search ) ) {
            $args['post__in'] = array( $search );
        } else {
             // For simplicity, search functionality in WC orders via this simple API might be limited
             // We'll rely on WC's internal search logic if available or just skip for now.
             // Actually, 's' parameter works for posts query
             // $args['s'] = $search; // This might not work with wc_get_orders directly as it uses data store
        }
    }

    $order_ids = wc_get_orders( $args );
    $data = array();

    foreach ( $order_ids as $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) continue;

        $data[] = array(
            'id'           => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status'       => $order->get_status(),
            'total'        => $order->get_formatted_order_total(),
            'customer'     => $order->get_formatted_billing_full_name(),
            'date'         => $order->get_date_created()->date( 'Y-m-d H:i' ),
            'item_count'   => $order->get_item_count(),
        );
    }

    return dox_response( true, '', $data );
}

/**
 * List Products Endpoint
 */
function dox_admin_list_products( $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $limit  = $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 10;
    $page   = $request->get_param( 'page' ) ? intval( $request->get_param( 'page' ) ) : 1;
    $status = $request->get_param( 'status' );
    $search = $request->get_param( 'search' );

    $args = array(
        'limit'    => $limit,
        'page'     => $page,
        'orderby'  => 'date',
        'order'    => 'DESC',
        'return'   => 'ids',
    );

    if ( ! empty( $status ) && $status !== 'all' ) {
        $args['status'] = $status;
    }

    if ( ! empty( $search ) ) {
        $args['s'] = $search;
    }

    $product_ids = wc_get_products( $args );
    $data = array();

    foreach ( $product_ids as $pid ) {
        $product = wc_get_product( $pid );
        if ( ! $product ) continue;

        $data[] = array(
            'id'      => $product->get_id(),
            'name'    => $product->get_name(),
            'status'  => $product->get_status(),
            'price'   => $product->get_price_html(),
            'stock'   => $product->get_stock_quantity(),
            'image'   => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
            'sku'     => $product->get_sku(),
        );
    }

    return dox_response( true, '', $data );
}

/**
 * Handle Actions (Delete, Status Change)
 */
function dox_admin_handle_actions( $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $type   = $request->get_param( 'type' ); // user, order, product
    $id     = $request->get_param( 'id' );
    $action = $request->get_param( 'action' ); // delete, update_status
    $params = $request->get_json_params();

    if ( ! $id || ! $type || ! $action ) {
        return dox_error_response( 'Missing parameters.' );
    }

    switch ( $type ) {
        case 'user':
            if ( $action === 'delete' ) {
                require_once( ABSPATH . 'wp-admin/includes/user.php' );
                if ( wp_delete_user( $id ) ) {
                    return dox_response( true, 'User deleted.' );
                }
                return dox_error_response( 'Failed to delete user.' );
            }
            if ( $action === 'update_status' ) {
                $new_status = $params['status'] ?? 'active';
                if ( $new_status === 'suspended' ) {
                    update_user_meta( $id, 'dox_suspended', 'yes' );
                    // Also for vendors
                    update_user_meta( $id, 'dokan_enable_selling', 'no' );
                } else {
                    update_user_meta( $id, 'dox_suspended', 'no' );
                    update_user_meta( $id, 'dokan_enable_selling', 'yes' );
                }
                return dox_response( true, 'User status updated.' );
            }
            break;

        case 'order':
            $order = wc_get_order( $id );
            if ( ! $order ) return dox_error_response( 'Order not found.' );

            if ( $action === 'delete' ) {
                if ( $order->delete( true ) ) {
                    return dox_response( true, 'Order deleted.' );
                }
                return dox_error_response( 'Failed to delete order.' );
            }
            if ( $action === 'update_status' ) {
                $status = $params['status'] ?? 'pending';
                $order->update_status( $status );
                return dox_response( true, 'Order status updated.' );
            }
            break;

        case 'product':
            $product = wc_get_product( $id );
            if ( ! $product ) return dox_error_response( 'Product not found.' );

            if ( $action === 'delete' ) {
                if ( $product->delete( true ) ) {
                    return dox_response( true, 'Product deleted.' );
                }
                return dox_error_response( 'Failed to delete product.' );
            }
            if ( $action === 'update_status' ) {
                $status = $params['status'] ?? 'publish';
                $product->set_status( $status );
                $product->save();
                return dox_response( true, 'Product status updated.' );
            }
            break;
    }

    return dox_error_response( 'Invalid action.' );
}

/**
 * Settings endpoint.
 */
function dox_admin_settings( $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    if ( 'GET' === $request->get_method() ) {
        $settings = get_option( 'doken_ox_pro_settings', array() );
        return dox_response( true, '', $settings );
    }

    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        return dox_error_response( __( 'Invalid settings payload.', 'doken-ox-pro' ) );
    }

    update_option( 'doken_ox_pro_settings', $params );

    return dox_response( true, __( 'Settings updated.', 'doken-ox-pro' ), $params );
}

/**
 * Banners endpoint.
 */
function dox_admin_banners( $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    if ( 'GET' === $request->get_method() ) {
        $banners = get_option( 'doken_ox_banners', array() );
        return dox_response( true, '', $banners );
    }

    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        return dox_error_response( __( 'Invalid banners payload.', 'doken-ox-pro' ) );
    }

    update_option( 'doken_ox_banners', $params );

    return dox_response( true, __( 'Banners updated.', 'doken-ox-pro' ), $params );
}

/**
 * Register admin routes via shared hook.
 */
function dox_admin_register_routes( $namespace ) {
    $auth = array( 'Doken_Ox_API_Router', 'permission_authenticated' );

    register_rest_route( $namespace, '/admin/banners', array(
        array( 'methods' => 'GET', 'callback' => 'dox_admin_banners', 'permission_callback' => $auth ),
        array( 'methods' => 'POST', 'callback' => 'dox_admin_banners', 'permission_callback' => $auth ),
    ));

    register_rest_route( $namespace, '/admin/system/stats', array(
        'methods' => 'GET', 'callback' => 'dox_admin_system_stats', 'permission_callback' => $auth
    ));

    register_rest_route( $namespace, '/admin/vendors', array(
        'methods' => 'GET', 'callback' => 'dox_admin_list_vendors', 'permission_callback' => $auth
    ));

    register_rest_route( $namespace, '/admin/customers', array(
        'methods' => 'GET', 'callback' => 'dox_admin_list_customers', 'permission_callback' => $auth
    ));

    register_rest_route( $namespace, '/admin/orders', array(
        'methods' => 'GET', 'callback' => 'dox_admin_list_orders', 'permission_callback' => $auth
    ));

    register_rest_route( $namespace, '/admin/products', array(
        'methods' => 'GET', 'callback' => 'dox_admin_list_products', 'permission_callback' => $auth
    ));

    // Unified Action Endpoint
    register_rest_route( $namespace, '/admin/actions/(?P<type>[a-zA-Z0-9-]+)/(?P<id>[\d]+)/(?P<action>[a-zA-Z0-9-_]+)', array(
        'methods' => 'POST', 'callback' => 'dox_admin_handle_actions', 'permission_callback' => $auth
    ));

    register_rest_route( $namespace, '/admin/settings', array(
        array( 'methods' => 'GET', 'callback' => 'dox_admin_settings', 'permission_callback' => $auth ),
        array( 'methods' => 'PUT', 'callback' => 'dox_admin_settings', 'permission_callback' => $auth ),
    ));

    register_rest_route( $namespace, '/admin/settings/verify-license', array(
        'methods' => 'POST', 'callback' => 'dox_admin_verify_license', 'permission_callback' => $auth
    ));
}

/**
 * Verify License Endpoint.
 */
function dox_admin_verify_license( $request ) {
    $user = dox_admin_current_user();
    if ( is_wp_error( $user ) ) {
        return dox_error_response( $user->get_error_message(), $user->get_error_data()['status'] ?? 400 );
    }

    $params = $request->get_json_params();
    $license_key = isset( $params['license_key'] ) ? trim( $params['license_key'] ) : '';

    $valid_keys = array(
        'DOX-PRO-1001-TEMP', 'DOX-PRO-1002-TEMP', 'DOX-PRO-1003-TEMP', 'DOX-PRO-1004-TEMP',
        'DOX-PRO-1005-TEMP', 'DOX-PRO-1006-TEMP', 'DOX-PRO-1007-TEMP', 'DOX-PRO-1008-TEMP',
        'DOX-PRO-1009-TEMP', 'DOX-PRO-1010-TEMP',
    );

    if ( in_array( $license_key, $valid_keys, true ) ) {
        update_option( 'doken_ox_pro_license_status', 'valid' );
        update_option( 'doken_ox_pro_license_key', $license_key );
        return dox_response( true, __( 'License verified successfully.', 'doken-ox-pro' ), array( 'status' => 'valid' ) );
    }

    return dox_error_response( __( 'Invalid license key.', 'doken-ox-pro' ) );
}
add_action( 'doken_ox_register_routes', 'dox_admin_register_routes' );
