<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Orders_Handler {

    /**
     * Checkout - Create Order
     * POST /mvapp/v1/checkout
     */
    public static function create_order( $request ) {
        // Ensure cart is loaded
        Doken_Ox_Cart_Handler::load_cart();

        if ( WC()->cart->is_empty() ) {
            return dox_error_response( __( 'Cart is empty.', 'doken-ox-pro' ) );
        }

        $params = $request->get_json_params();
        $billing = $params['billing'] ?? array();
        $shipping = $params['shipping'] ?? array();
        $payment_method = $params['payment_method'] ?? 'cod';

        // Validation
        if ( empty( $billing['email'] ) || empty( $billing['first_name'] ) ) {
             return dox_error_response( __( 'Billing information required.', 'doken-ox-pro' ) );
        }

        try {
            $checkout = WC()->checkout();
            $order_id = $checkout->create_order( array(
                'billing_first_name' => $billing['first_name'],
                'billing_last_name'  => $billing['last_name'] ?? '',
                'billing_email'      => $billing['email'],
                'billing_phone'      => $billing['phone'] ?? '',
                'billing_address_1'  => $billing['address_1'] ?? '',
                'billing_city'       => $billing['city'] ?? '',
                'billing_postcode'   => $billing['postcode'] ?? '',
                'billing_country'    => $billing['country'] ?? '',
                'shipping_first_name'=> $shipping['first_name'] ?? $billing['first_name'],
                'shipping_address_1' => $shipping['address_1'] ?? $billing['address_1'],
                'payment_method'     => $payment_method,
            ) );

            if ( is_wp_error( $order_id ) ) {
                return dox_error_response( $order_id->get_error_message() );
            }

            // Process payment (mock for now, or handle COD)
            $order = wc_get_order( $order_id );
            
            // Empty cart
            WC()->cart->empty_cart();

            return dox_response( true, __( 'Order created successfully.', 'doken-ox-pro' ), array(
                'order_id' => $order_id,
                'order_key' => $order->get_order_key(),
                'total' => $order->get_total(),
                'status' => $order->get_status(),
            ));

        } catch ( Exception $e ) {
            return dox_error_response( $e->getMessage() );
        }
    }

    /**
     * Get User Orders
     * GET /mvapp/v1/orders
     */
    public static function get_orders( $request ) {
        $user_id = get_current_user_id();
        $status = $request->get_param('status') ?: 'any';
        $page = $request->get_param('page') ?: 1;

        $args = array(
            'customer_id' => $user_id,
            'status' => $status,
            'limit' => 10,
            'page' => $page,
        );

        $orders = wc_get_orders( $args );
        $data = array();

        foreach ( $orders as $order ) {
            $data[] = self::format_order( $order );
        }

        return dox_response( true, '', $data );
    }

    /**
     * Get Single Order
     * GET /mvapp/v1/orders/{id}
     */
    public static function get_order( $request ) {
        $order_id = (int) $request['id'];
        $order = wc_get_order( $order_id );

        if ( ! $order || $order->get_customer_id() !== get_current_user_id() ) {
            return dox_error_response( __( 'Order not found.', 'doken-ox-pro' ), 404 );
        }

        return dox_response( true, '', self::format_order( $order ) );
    }

    private static function format_order( $order ) {
        $items = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $items[] = array(
                'id' => $item->get_id(),
                'product_name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => wc_format_decimal( $item->get_total(), 2 ),
                'image' => $product ? wp_get_attachment_url( $product->get_image_id() ) : '',
            );
        }

        return array(
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'date' => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
            'items' => $items,
            'tracking_number' => $order->get_meta('_tracking_number', true) ?: '',
        );
    }
}

function dox_orders_register_routes( $namespace ) {
    $auth = array( 'Doken_Ox_API_Router', 'permission_authenticated' );

    register_rest_route( $namespace, '/checkout', array(
        'methods' => 'POST',
        'callback' => array( 'Doken_Ox_Orders_Handler', 'create_order' ),
        'permission_callback' => $auth,
    ));

    register_rest_route( $namespace, '/orders', array(
        'methods' => 'GET',
        'callback' => array( 'Doken_Ox_Orders_Handler', 'get_orders' ),
        'permission_callback' => $auth,
    ));

    register_rest_route( $namespace, '/orders/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => array( 'Doken_Ox_Orders_Handler', 'get_order' ),
        'permission_callback' => $auth,
    ));
}
add_action( 'doken_ox_register_routes', 'dox_orders_register_routes' );
