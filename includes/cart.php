<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Cart_Handler {

    /**
     * Initialize WC Cart for REST request
     */
    public static function load_cart() {
        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        // Ensure frontend includes are loaded
        include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
        include_once WC_ABSPATH . 'includes/wc-notice-functions.php';

        if ( is_null( WC()->cart ) ) {
            wc_load_cart();
        }
        
        // Ensure session is running
        if ( is_null( WC()->session ) ) {
            $session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
            WC()->session  = new $session_class();
            WC()->session->init();
        }

        // If user is logged in, ensure we have their persistent cart
        if ( is_user_logged_in() ) {
            // Check if we are already using this user's session
            $user_id = get_current_user_id();
            // This forces WC to look for the persistent cart for this user
            if ( ! WC()->session->has_session() || WC()->session->get_customer_id() !== $user_id ) {
                 WC()->session->set_customer_session_cookie( true );
                 WC()->customer = new WC_Customer( $user_id, true );
                 WC()->cart->get_cart_from_session();
            }
        }
    }

    public static function get_cart( $request ) {
        self::load_cart();
        $cart = WC()->cart;
        
        $items = array();
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            // Safe check
            if ( ! $product ) continue;

            $items[] = array(
                'key'          => $cart_item_key,
                'product_id'   => $cart_item['product_id'],
                'variation_id' => $cart_item['variation_id'],
                'name'         => $product->get_name(),
                'quantity'     => $cart_item['quantity'],
                'price'        => wc_format_decimal( $product->get_price(), 2 ),
                'regular_price'=> wc_format_decimal( $product->get_regular_price(), 2 ),
                'line_total'   => wc_format_decimal( $cart_item['line_total'], 2 ),
                'image'        => wp_get_attachment_url( $product->get_image_id() ),
                'attributes'   => $cart_item['variation'] ?? array(),
            );
        }

        $totals = $cart->get_totals();
        
        return dox_response( true, '', array(
            'items'     => $items,
            'subtotal'  => $cart->get_cart_subtotal(),
            'total'     => $cart->get_total(),
            'count'     => $cart->get_cart_contents_count(),
            'taxes'     => $cart->get_taxes(),
            'shipping'  => $cart->get_shipping_total(),
        ));
    }

    public static function add_to_cart( $request ) {
        self::load_cart();
        
        $product_id    = (int) $request->get_param('product_id');
        $quantity      = (int) $request->get_param('quantity') ?: 1;
        $variations_input = $request->get_param('variations');

        if ( ! $product_id ) {
             return dox_error_response( __( 'Product ID is required.', 'doken-ox-pro' ) );
        }

        $wc_variation_id = 0;
        $wc_variations_args = array();

        if ( ! empty( $variations_input ) && is_array( $variations_input ) ) {
            // Get product to check if it's variable
            $product = wc_get_product( $product_id );

            if ( $product && $product->is_type( 'variable' ) ) {
                $sent_attrs = array();
                $variation_attributes = $product->get_variation_attributes();
                $attr_keys = array_keys( $variation_attributes );
                $real_attr_keys = array_keys( $product->get_attributes() );

                foreach ( $variations_input as $v ) {
                    if ( isset( $v['variation_id'] ) && isset( $v['option_id'] ) ) {
                        $v_group_idx = (int)$v['variation_id'] - 1;
                        $v_opt_idx   = (int)$v['option_id'] - 1;

                        if ( isset( $attr_keys[$v_group_idx] ) && isset( $real_attr_keys[$v_group_idx] ) ) {
                            $taxonomy_label = $attr_keys[$v_group_idx];
                            $taxonomy_slug  = $real_attr_keys[$v_group_idx];
                            $options_array  = array_values( $variation_attributes[$taxonomy_label] );

                            if ( isset( $options_array[$v_opt_idx] ) ) {
                                $sent_attrs[ $taxonomy_slug ] = $options_array[$v_opt_idx];
                            }
                        }
                    } else {
                        // Fallback: standard key-value
                        foreach( $variations_input as $key => $val ) {
                            $sent_attrs[ $key ] = $val;
                        }
                        break;
                    }
                }

                // Iterate product children and find best match by attribute score
                $best_variation_id = 0;
                foreach ( $product->get_children() as $child_id ) {
                    $child = wc_get_product( $child_id );
                    if ( ! $child ) continue;

                    $child_attrs = $child->get_attributes(); // e.g. ['skin-type' => 'Oil', 'net-quantity' => '50g']
                    $score = 0;
                    foreach ( $sent_attrs as $slug => $value ) {
                        if ( isset( $child_attrs[$slug] ) && $child_attrs[$slug] === $value ) {
                            $score++;
                        }
                    }
                    // All sent attributes matched
                    if ( $score === count( $sent_attrs ) ) {
                        $best_variation_id = $child_id;
                        break;
                    }
                }

                if ( $best_variation_id ) {
                    $wc_variation_id = $best_variation_id;
                    // Build variation args from child actual attributes
                    $child_full_attrs = wc_get_product( $best_variation_id )->get_attributes();
                    foreach ( $child_full_attrs as $slug => $value ) {
                        $wc_variations_args[ 'attribute_' . $slug ] = $value;
                    }
                }
            }
        } else {
             $wc_variation_id = (int) $request->get_param('variation_id');
             $wc_variations_args = $request->get_param('variations') ?: array();
        }

        try {
            $key = WC()->cart->add_to_cart( $product_id, $quantity, $wc_variation_id, $wc_variations_args );
            
            if ( ! $key ) {
                 return dox_error_response( __( 'Could not add to cart. Check stock or product availability.', 'doken-ox-pro' ) );
            }
            
            // Calculate totals so response is fresh
            WC()->cart->calculate_totals();

            return self::get_cart( $request );

        } catch ( Exception $e ) {
            return dox_error_response( $e->getMessage() );
        }
    }
    
    public static function update_item( $request ) {
        self::load_cart();
        $key = sanitize_text_field( $request->get_param('key') );
        $quantity = (int) $request->get_param('quantity');
        
        if ( $quantity <= 0 ) {
            WC()->cart->remove_cart_item( $key );
        } else {
            WC()->cart->set_quantity( $key, $quantity );
        }
        WC()->cart->calculate_totals();
        
        return self::get_cart( $request );
    }

    public static function remove_item( $request ) {
        self::load_cart();
        $key = sanitize_text_field( $request->get_param('key') );
        WC()->cart->remove_cart_item( $key );
        WC()->cart->calculate_totals();
        
        return self::get_cart( $request );
    }
}

function dox_cart_register_routes( $namespace ) {
    $auth = array( 'Doken_Ox_API_Router', 'permission_authenticated' );

    register_rest_route( $namespace, '/cart', array(
        array(
            'methods' => 'GET',
            'callback' => array( 'Doken_Ox_Cart_Handler', 'get_cart' ),
            'permission_callback' => $auth,
        ),
    ));

    register_rest_route( $namespace, '/cart/add', array(
        'methods' => 'POST',
        'callback' => array( 'Doken_Ox_Cart_Handler', 'add_to_cart' ),
        'permission_callback' => $auth,
    ));
    
    register_rest_route( $namespace, '/cart/update', array(
        'methods' => 'POST',
        'callback' => array( 'Doken_Ox_Cart_Handler', 'update_item' ),
        'permission_callback' => $auth,
    ));
    
    register_rest_route( $namespace, '/cart/remove', array(
        'methods' => 'POST',
        'callback' => array( 'Doken_Ox_Cart_Handler', 'remove_item' ),
        'permission_callback' => $auth,
    ));
}
add_action( 'doken_ox_register_routes', 'dox_cart_register_routes' );
