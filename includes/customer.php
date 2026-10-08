<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
require_once DOKEN_OX_PRO_PATH . 'includes/helpers/response-helpers.php';

/**
 * Doken Ox Pro - Customer APIs (complete)
 *
 * Endpoints covered:
 * - Products: list, detail, search, featured, latest, filters, pagination
 * - Categories: list, category products
 * - Stores: list, store details, store products, search stores
 * - Cart: get, add, update, remove, clear, apply coupon
 * - Checkout: save address, select shipping, review, payment, place order
 * - Orders: create, list, details, cancel, track
 * - Wishlist: CRUD using dox_wishlist table
 * - Reviews: add review (with images base64 -> media)
 * - Addresses: CRUD using dox_addresses table
 * - Settings: get / update (user settings)
 *
 * Notes:
 * - Protected routes must check authentication via wp_get_current_user()
 * - Uses dox_response(...) helper for consistent JSON
 * - Uses dox_* helper functions for DB interactions where needed
 */

/* ---------------------------
   Helpers & Utilities
   --------------------------- */

function dox_customer_get_params( $request ) {
    return wp_parse_args( $request->get_json_params(), $request->get_query_params() );
}

function dox_current_user_or_error() {
    $user = wp_get_current_user();
    if ( ! $user || 0 === $user->ID ) {
        return new WP_Error( 'dox_unauth', 'Not authenticated', array( 'status' => 401 ) );
    }
    return $user;
}

function dox_table_name( $key ) {
    global $wpdb;
    $tables = array(
        'addresses' => $wpdb->prefix . 'dox_addresses',
        'wishlist'  => $wpdb->prefix . 'dox_wishlist',
        'notifications' => $wpdb->prefix . 'dox_notifications',
        'tokens' => $wpdb->prefix . 'dox_tokens',
        'logs' => $wpdb->prefix . 'dox_logs',
    );
    return isset( $tables[ $key ] ) ? $tables[ $key ] : false;
}

/* ---------------------------
   Products & Categories
   --------------------------- */

function dox_customer_get_products( $request ) {
    $params   = dox_customer_get_params( $request );
    $page     = max( 1, intval( $params['page'] ?? 1 ) );
    $per_page = max( 1, min( 100, intval( $params['per_page'] ?? 20 ) ) );

    $result = Doken_Ox_Product_Model::list_products(
        array(
            'page'       => $page,
            'per_page'   => $per_page,
            'category'   => isset( $params['category'] ) ? intval( $params['category'] ) : null,
            'on_sale'    => isset( $params['on_sale'] ) ? filter_var( $params['on_sale'], FILTER_VALIDATE_BOOLEAN ) : null,
            'min_price'  => $params['min_price'] ?? null,
            'max_price'  => $params['max_price'] ?? null,
            'orderby'    => sanitize_text_field( $params['orderby'] ?? 'date' ),
            'order'      => ( ! empty( $params['order'] ) && strtolower( $params['order'] ) === 'asc' ) ? 'ASC' : 'DESC',
            'search'     => $params['search'] ?? $params['q'] ?? null,
            'attributes' => $params['attributes'] ?? array(),
        )
    );

    $pagination = dox_build_pagination( $page, $per_page, (int) $result['total'] );

    return dox_response( true, '', $result['items'], $pagination );
}

function dox_customer_get_product( $request ) {
    $id = intval( $request['id'] ?? 0 );
    if ( $id <= 0 ) {
        return dox_response( false, 'Invalid product id', array() );
    }

    $product = Doken_Ox_Product_Model::get_product( $id );
    if ( ! $product ) {
        return dox_response( false, 'Product not found', array() );
    }
    $product['reviews']          = dox_customer_get_recent_reviews( $id, 5 );
    $product['related_products'] = array_map( 'intval', wc_get_related_products( $id, 8 ) );
    return dox_response( true, '', $product );
}

function dox_customer_search_products( $request ) {
    $q = sanitize_text_field( $request->get_param( 'q' ) );
    if ( empty( $q ) ) {
        return dox_response( true, '', array() );
    }

    $data = Doken_Ox_Product_Model::search( $q );
    return dox_response( true, '', $data );
}

function dox_customer_featured_products( $request ) {
    $result = Doken_Ox_Product_Model::list_products(
        array(
            'per_page' => 20,
            'featured' => true,
        )
    );
    return dox_response( true, '', $result['items'] );
}

function dox_customer_latest_collections( $request ) {
    $result = Doken_Ox_Product_Model::list_products(
        array(
            'per_page' => 20,
            'orderby'  => 'date',
            'order'    => 'DESC',
        )
    );
    return dox_response( true, '', $result['items'] );
}

/* categories */

function dox_customer_get_categories( $request ) {
    $cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
    $data = array();
    foreach ( $cats as $c ) {
        $thumb_id = get_term_meta( $c->term_id, 'thumbnail_id', true );
        $children = array();
        // optionally add children
        $child_terms = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $c->term_id, 'hide_empty' => false ) );
        foreach ( $child_terms as $ch ) {
            $children[] = array( 'id' => $ch->term_id, 'name' => $ch->name, 'count' => $ch->count );
        }
        $data[] = array(
            'id' => $c->term_id,
            'name' => $c->name,
            'slug' => $c->slug,
            'count' => $c->count,
            'image' => $thumb_id ? wp_get_attachment_url( $thumb_id ) : '',
            'parent' => $c->parent,
            'children' => $children,
        );
    }
    return dox_response( true, '', $data );
}

function dox_customer_get_category_products( $request ) {
    $cat_id = intval( $request['id'] ?? 0 );
    if ( $cat_id <= 0 ) return dox_response( false, 'Invalid category', array() );

    $page = max( 1, intval( $request->get_param( 'page' ) ?? 1 ) );
    $per_page = max( 1, min( 100, intval( $request->get_param( 'per_page' ) ?? 20 ) ) );

    $wc_args = array(
        'limit' => $per_page,
        'page' => $page,
        'status' => 'publish',
        'category' => array( $cat_id ),
    );

    $query = new WC_Product_Query( $wc_args );
    $products = $query->get_products();

    $count_q = new WP_Query( array( 'post_type' => 'product', 'tax_query' => array( array( 'taxonomy'=>'product_cat', 'terms'=> $cat_id, 'field'=>'term_id' ) ), 'fields'=>'ids', 'posts_per_page'=>-1 ) );
    $total = is_array( $count_q->posts ) ? count( $count_q->posts ) : 0;

    $data = array();
    foreach ( $products as $p ) {
        $data[] = array(
            'id' => $p->get_id(),
            'name' => $p->get_name(),
            'price' => wc_format_decimal( $p->get_price(), 2 ),
            'image' => wp_get_attachment_url( $p->get_image_id() ),
        );
    }

    $pagination = array( 'current_page' => $page, 'per_page' => $per_page, 'total'=> $total, 'total_pages' => $per_page ? intval( ceil( $total / $per_page ) ) : 1 );
    return dox_response( true, '', $data, $pagination );
}

/* ---------------------------
   Stores / Vendors
   --------------------------- */

function dox_customer_get_stores( $request ) {
    $params = dox_customer_get_params( $request );
    $stores = Doken_Ox_Vendor_Model::list_vendors(
        array(
            'number' => ! empty( $params['per_page'] ) ? (int) $params['per_page'] : 50,
            'search' => $params['search'] ?? '',
        )
    );
    return dox_response( true, '', $stores );
}

function dox_customer_get_store( $request ) {
    $id = intval( $request['id'] ?? 0 );
    if ( $id <= 0 ) return dox_response( false, 'Invalid store id', array() );

    $data = Doken_Ox_Vendor_Model::format_vendor( $id );
    return dox_response( true, '', $data );
}

function dox_customer_get_store_products( $request ) {
    $id = intval( $request['id'] ?? 0 );
    if ( $id <= 0 ) return dox_response( false, 'Invalid store id', array() );

    $result = Doken_Ox_Product_Model::list_products(
        array(
            'vendor_id' => $id,
            'per_page'  => 50,
        )
    );
    return dox_response( true, '', $result['items'] );
}

/* ---------------------------
   Cart (user-meta backed, improved)
   --------------------------- */

function dox_customer_get_cart( $request ) {
    return dox_response( true, '', dox_customer_calculate_cart_totals( $user->ID ) );
}

/**
 * Advanced Calculation using WooCommerce Core
 */
function dox_customer_calculate_cart_totals( $user_id ) {
    $cart_items = dox_get_user_cart( $user_id );
    $coupon_code = get_user_meta( $user_id, 'dox_cart_coupon', true );
    $selected_shipping = get_user_meta( $user_id, 'dox_selected_shipping', true );
    
    // Get user address
    $billing = get_user_meta( $user_id, 'dox_billing_address', true ) ?: array();
    $shipping_addr = get_user_meta( $user_id, 'dox_shipping_address', true ) ?: $billing;

    // Load WC Cart environment
    if ( ! function_exists( 'wc_load_cart' ) ) {
        include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
    }
    wc_load_cart();
    $cart = WC()->cart;
    $cart->empty_cart();

    // 1. Add items to real WC Cart
    foreach ( $cart_items as $item ) {
        $cart->add_to_cart( $item['product_id'], $item['quantity'], $item['variation_id'] ?? 0 );
    }

    // 2. Set Customer Location for Taxes & Shipping
    if ( ! empty( $shipping_addr ) ) {
        WC()->customer->set_props( array(
            'shipping_country'  => $shipping_addr['country'] ?? '',
            'shipping_state'    => $shipping_addr['state'] ?? '',
            'shipping_postcode' => $shipping_addr['postcode'] ?? '',
            'shipping_city'     => $shipping_addr['city'] ?? '',
            'billing_country'   => $billing['country'] ?? '',
            'billing_state'     => $billing['state'] ?? '',
        ) );
        WC()->customer->save();
    }

    // 3. Apply Coupons
    if ( $coupon_code ) {
        $cart->apply_coupon( $coupon_code );
    }

    // 4. Handle Shipping
    if ( $selected_shipping ) {
        WC()->session->set( 'chosen_shipping_methods', array( $selected_shipping ) );
    }

    $cart->calculate_totals();

    // Format items
    $items = array();
    foreach ( $cart->get_cart() as $key => $cart_item ) {
        $product = $cart_item['data'];
        $items[] = array(
            'key'            => $key,
            'product_id'     => $cart_item['product_id'],
            'variation_id'   => $cart_item['variation_id'],
            'name'           => $product->get_name(),
            'quantity'       => $cart_item['quantity'],
            'price'          => wc_format_decimal( $product->get_price(), 2 ),
            'image'          => wp_get_attachment_url( $product->get_image_id() ),
            'subtotal'       => wc_format_decimal( $cart_item['line_total'], 2 ),
            'total'          => wc_format_decimal( $cart_item['line_total'] + $cart_item['line_tax'], 2 ),
            'vendor'         => dox_customer_get_product_vendor_info( $cart_item['product_id'] ),
        );
    }

    return array(
        'items' => $items,
        'totals' => array(
            'subtotal'  => wc_format_decimal( $cart->get_subtotal(), 2 ),
            'shipping'  => wc_format_decimal( $cart->get_shipping_total(), 2 ),
            'tax'       => wc_format_decimal( $cart->get_total_tax(), 2 ),
            'discount'  => wc_format_decimal( $cart->get_discount_total(), 2 ),
            'total'     => wc_format_decimal( $cart->get_total(), 2 ),
            'currency'  => get_woocommerce_currency(),
        ),
        'coupons' => $cart->get_applied_coupons(),
        'shipping_methods' => dox_customer_get_shipping_methods_data(),
    );
}

function dox_customer_get_shipping_methods_data() {
    $packages = WC()->shipping()->get_packages();
    $methods = array();
    foreach ( $packages as $i => $package ) {
        foreach ( $package['rates'] as $rate_id => $rate ) {
            $methods[] = array(
                'id'    => $rate_id,
                'label' => $rate->label,
                'cost'  => wc_format_decimal( $rate->cost, 2 ),
                'tax'   => wc_format_decimal( $rate->taxes ? array_sum( $rate->taxes ) : 0, 2 ),
            );
        }
    }
    return $methods;
}

function dox_customer_get_shipping_methods( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $data = dox_customer_calculate_cart_totals( $user->ID );
    return dox_response( true, '', $data['shipping_methods'] );
}

function dox_customer_add_to_cart( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $params       = $request->get_json_params();
    $product_id   = intval( $params['product_id'] ?? 0 );
    $variation_id = intval( $params['variation_id'] ?? 0 );
    $quantity     = max( 1, intval( $params['quantity'] ?? 1 ) );
    $variations_input = $params['variations'] ?? array();

    $p = wc_get_product( $product_id );
    if ( ! $p ) {
        return dox_response( false, 'Product not found', array() );
    }

    // Resolve sequential combinations to true variation_id
    if ( ! empty( $variations_input ) && is_array( $variations_input ) && $p->is_type( 'variable' ) ) {
        $sent_attrs           = array();
        $variation_attributes = $p->get_variation_attributes();
        $attr_keys            = array_keys( $variation_attributes );
        $real_attr_keys       = array_keys( $p->get_attributes() );

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
                foreach( $variations_input as $key => $val ) {
                    $sent_attrs[ $key ] = $val;
                }
                break;
            }
        }

        // Iterate children and find best match by attribute score
        foreach ( $p->get_children() as $child_id ) {
            $child = wc_get_product( $child_id );
            if ( ! $child ) continue;

            $child_attrs = $child->get_attributes();
            $score = 0;
            foreach ( $sent_attrs as $slug => $value ) {
                if ( isset( $child_attrs[$slug] ) && $child_attrs[$slug] === $value ) {
                    $score++;
                }
            }
            if ( $score === count( $sent_attrs ) ) {
                $variation_id = $child_id;
                break;
            }
        }
    }

    $product_to_add = wc_get_product( $variation_id ?: $product_id );

    // handle stock check
    if ( $product_to_add && $product_to_add->managing_stock() && $product_to_add->get_stock_quantity() < $quantity ) {
        return dox_response( false, 'Not enough stock', array( 'available' => $product_to_add->get_stock_quantity() ) );
    }

    $cart = dox_get_user_cart( $user->ID );
    // Use a unique key based on product and variation
    $key = 'ci_' . md5( $product_id . '_' . $variation_id );
    
    if ( isset( $cart[ $key ] ) ) {
        $cart[ $key ]['quantity'] += $quantity;
    } else {
        $cart[ $key ] = array(
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'quantity'     => $quantity,
            'added_at'     => current_time( 'mysql' ),
        );
    }
    
    dox_save_user_cart( $user->ID, $cart );

    return dox_response( true, 'Added to cart', array( 'key' => $key, 'cart_count' => count( $cart ) ) );
}

function dox_customer_update_cart_item( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $key = sanitize_text_field( $request['key'] );
    $params = $request->get_json_params();
    $quantity = max( 0, intval( $params['quantity'] ?? 1 ) );

    $cart = dox_get_user_cart( $user->ID );
    if ( ! isset( $cart[ $key ] ) ) return dox_response( false, 'Item not found', array() );

    if ( $quantity <= 0 ) {
        unset( $cart[ $key ] );
    } else {
        $product = wc_get_product( $cart[ $key ]['product_id'] );
        if ( $product && $product->managing_stock() && $product->get_stock_quantity() < $quantity ) {
            return dox_response( false, 'Not enough stock', array() );
        }
        $cart[ $key ]['quantity'] = $quantity;
    }
    dox_save_user_cart( $user->ID, $cart );
    return dox_response( true, 'Cart updated', array() );
}

function dox_customer_remove_cart_item( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $key = sanitize_text_field( $request['key'] );
    $cart = dox_get_user_cart( $user->ID );
    if ( isset( $cart[ $key ] ) ) {
        unset( $cart[ $key ] );
        dox_save_user_cart( $user->ID, $cart );
    }
    return dox_response( true, 'Item removed', array() );
}

function dox_customer_clear_cart( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );
    dox_save_user_cart( $user->ID, array() );
    delete_user_meta( $user->ID, 'dox_cart_coupon' );
    return dox_response( true, 'Cart cleared', array() );
}

function dox_customer_apply_coupon( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $params = $request->get_json_params();
    $code = sanitize_text_field( $params['coupon_code'] ?? '' );
    if ( empty( $code ) ) return dox_response( false, 'coupon_code is required', array() );

    try {
        $coupon = new WC_Coupon( $code );
        if ( ! $coupon || ! $coupon->get_id() ) {
            return dox_response( false, 'Invalid coupon', array() );
        }
    } catch ( Exception $e ) {
        return dox_response( false, 'Invalid coupon', array() );
    }

    update_user_meta( $user->ID, 'dox_cart_coupon', $code );
    return dox_response( true, 'Coupon applied', array( 'coupon_code' => $code ) );
}

/* ---------------------------
   Checkout & Orders
   --------------------------- */

function dox_customer_save_address( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $params = $request->get_json_params();
    $billing = isset( $params['billing'] ) ? (array) $params['billing'] : array();
    $shipping = isset( $params['shipping'] ) ? (array) $params['shipping'] : array();
    $ship_to_different = isset( $params['ship_to_different_address'] ) ? boolval( $params['ship_to_different_address'] ) : false;

    update_user_meta( $user->ID, 'dox_billing_address', $billing );
    update_user_meta( $user->ID, 'dox_shipping_address', $shipping );
    update_user_meta( $user->ID, 'dox_ship_to_different', $ship_to_different );

    return dox_response( true, 'Address saved', array( 'billing' => $billing, 'shipping' => $shipping ) );
}

function dox_customer_checkout_shipping( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $params = $request->get_json_params();
    $method = sanitize_text_field( $params['shipping_method'] ?? '' );
    if ( empty( $method ) ) return dox_response( false, 'shipping_method is required', array() );

    // store chosen shipping method on user meta for the checkout session
    update_user_meta( $user->ID, 'dox_selected_shipping', $method );
    return dox_response( true, 'Shipping method selected', array( 'shipping_method' => $method ) );
}

function dox_customer_checkout_review( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    // assemble order summary from cart + addresses + shipping + coupon
    $cart = dox_get_user_cart( $user->ID );
    if ( empty( $cart ) ) return dox_response( false, 'Cart is empty', array() );

    $items = array(); $subtotal = 0;
    foreach ( $cart as $item ) {
        $prod = wc_get_product( $item['product_id'] );
        if ( ! $prod ) continue;
        $qty = intval( $item['quantity'] );
        $price = floatval( $prod->get_price() );
        $subtotal += $price * $qty;
        $items[] = array( 'product_id' => $prod->get_id(), 'name' => $prod->get_name(), 'quantity' => $qty, 'price' => number_format( $price, 2, '.', '' ) );
    }

    $billing = get_user_meta( $user->ID, 'dox_billing_address', true );
    $shipping = get_user_meta( $user->ID, 'dox_shipping_address', true );
    $shipping_method = get_user_meta( $user->ID, 'dox_selected_shipping', true );
    $coupon = get_user_meta( $user->ID, 'dox_cart_coupon', true );

    $totals = array( 'subtotal' => number_format( $subtotal, 2, '.', '' ), 'shipping' => '0.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => number_format( $subtotal, 2, '.', '' ) );

    return dox_response( true, '', array( 'items' => $items, 'billing' => $billing, 'shipping' => $shipping, 'shipping_method' => $shipping_method, 'coupon' => $coupon, 'totals' => $totals ) );
}

/**
 * POST /customer/checkout/payment
 * Body: { payment_method, payment_details }
 */
function dox_customer_checkout_payment( $request ) {
    // This endpoint only stores payment choice before placing order
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $params = $request->get_json_params();
    $payment_method = sanitize_text_field( $params['payment_method'] ?? '' );
    if ( empty( $payment_method ) ) return dox_response( false, 'payment_method is required', array() );

    update_user_meta( $user->ID, 'dox_selected_payment', $payment_method );
    update_user_meta( $user->ID, 'dox_payment_details', maybe_serialize( $params['payment_details'] ?? array() ) );

    return dox_response( true, 'Payment method selected', array( 'payment_method' => $payment_method ) );
}

/**
 * POST /customer/orders (place order)
 */
function dox_customer_create_order( $request ) {
    if ( ! class_exists( 'WC_Order' ) ) {
        return dox_response( false, 'WooCommerce not active', array() );
    }
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $params = $request->get_json_params();
    $payment_method = sanitize_text_field( $params['payment_method'] ?? get_user_meta( $user->ID, 'dox_selected_payment', true ) );
    $billing = isset( $params['billing'] ) ? (array) $params['billing'] : get_user_meta( $user->ID, 'dox_billing_address', true );
    $shipping = isset( $params['shipping'] ) ? (array) $params['shipping'] : get_user_meta( $user->ID, 'dox_shipping_address', true );
    $customer_note = sanitize_text_field( $params['customer_note'] ?? '' );

    $cart = dox_get_user_cart( $user->ID );
    if ( empty( $cart ) ) return dox_response( false, 'Cart is empty', array() );

    // create order
    $order = wc_create_order( array( 'customer_id' => $user->ID ) );
    foreach ( $cart as $item ) {
        $product = wc_get_product( $item['product_id'] );
        if ( ! $product ) continue;
        $order->add_product( $product, intval( $item['quantity'] ) );
    }

    // addresses
    if ( ! empty( $billing ) && is_array( $billing ) ) $order->set_address( $billing, 'billing' );
    if ( ! empty( $shipping ) && is_array( $shipping ) ) $order->set_address( $shipping, 'shipping' );

    $order->set_payment_method( $payment_method );
    if ( $customer_note ) $order->set_customer_note( $customer_note );

    $order->calculate_totals();
    $order->save();

    // set status depending on payment method
    if ( in_array( $payment_method, array( 'cod', 'cash_on_delivery' ), true ) ) {
        $order->update_status( 'processing' );
    } else {
        $order->update_status( 'pending' );
    }

    // optionally: reduce stock, create vendor split (Dokan handles it on order)
    // clear cart & checkout meta
    dox_save_user_cart( $user->ID, array() );
    delete_user_meta( $user->ID, 'dox_cart_coupon' );
    delete_user_meta( $user->ID, 'dox_selected_shipping' );
    delete_user_meta( $user->ID, 'dox_selected_payment' );
    delete_user_meta( $user->ID, 'dox_payment_details' );

    $data = array(
        'order_id' => $order->get_id(),
        'order_number' => $order->get_order_number(),
        'status' => $order->get_status(),
        'total' => $order->get_total(),
        'payment_url' => '', // for online gateways we may generate URL
    );

    return dox_response( true, 'Order created', $data );
}

function dox_customer_get_orders( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $params = dox_customer_get_params( $request );
    $status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '';
    $page = max( 1, intval( $params['page'] ?? 1 ) );
    $per_page = max( 1, min( 50, intval( $params['per_page'] ?? 20 ) ) );

    $args = array(
        'customer_id' => $user->ID,
        'limit'       => $per_page,
        'page'        => $page,
    );
    if ( $status ) {
        $args['status'] = $status;
    }

    $orders = Doken_Ox_Order_Model::query_orders( $args );
    return dox_response( true, '', $orders );
}

function dox_customer_get_order( $request ) {
    $id = intval( $request['id'] ?? 0 );
    $order = wc_get_order( $id );
    if ( ! $order ) return dox_response( false, 'Order not found', array() );

    // ensure the order belongs to current user or the user is admin
    $user = wp_get_current_user();
    if ( $order->get_user_id() !== $user->ID && ! user_can( $user, 'manage_woocommerce' ) ) {
        return dox_response( false, 'Not authorized', array() );
    }

    $data = Doken_Ox_Order_Model::format_order_detail( $order );
    $data['tracking_number'] = get_post_meta( $order->get_id(), '_dox_tracking_number', true );
    return dox_response( true, '', $data );
}

function dox_customer_cancel_order( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $id = intval( $request['id'] ?? 0 );
    if ( $id <= 0 ) return dox_response( false, 'Invalid order id', array() );

    $order = wc_get_order( $id );
    if ( ! $order ) return dox_response( false, 'Order not found', array() );
    if ( $order->get_user_id() !== $user->ID ) return dox_response( false, 'Not authorized', array() );

    $reason = sanitize_text_field( $request->get_json_params()['reason'] ?? '' );
    $order->update_status( 'cancelled', $reason );

    return dox_response( true, 'Order cancelled', array() );
}

function dox_customer_track_order( $request ) {
    $id = intval( $request['id'] ?? 0 );
    if ( $id <= 0 ) return dox_response( false, 'Invalid order id', array() );

    $tracking = get_post_meta( $id, '_dox_tracking_number', true );
    return dox_response( true, '', array( 'tracking_number' => $tracking ) );
}

/* ---------------------------
   Wishlist (DB-backed)
   --------------------------- */

function dox_customer_get_wishlist( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    global $wpdb;
    $table = dox_table_name( 'wishlist' );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, added_at FROM {$table} WHERE user_id = %d ORDER BY added_at DESC", $user->ID ) );
    $data = array();
    foreach ( $rows as $r ) {
        $p = wc_get_product( $r->product_id );
        if ( ! $p ) continue;
        $data[] = array( 'product_id' => intval( $r->product_id ), 'added_at' => $r->added_at, 'name' => $p->get_name(), 'price' => wc_format_decimal( $p->get_price(), 2 ), 'image' => wp_get_attachment_url( $p->get_image_id() ) );
    }
    return dox_response( true, '', $data );
}

function dox_customer_add_wishlist( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $product_id = intval( $request['product_id'] ?? 0 );
    if ( $product_id <= 0 ) return dox_response( false, 'Invalid product', array() );
    if ( ! wc_get_product( $product_id ) ) return dox_response( false, 'Product not found', array() );

    global $wpdb;
    $table = dox_table_name( 'wishlist' );
    // insert unique
    $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (user_id, product_id, added_at) VALUES (%d, %d, %s)", $user->ID, $product_id, current_time('mysql') ) );

    return dox_response( true, 'Added to wishlist', array( 'product_id' => $product_id ) );
}

function dox_customer_remove_wishlist( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $product_id = intval( $request['product_id'] ?? 0 );
    if ( $product_id <= 0 ) return dox_response( false, 'Invalid product', array() );

    global $wpdb;
    $table = dox_table_name( 'wishlist' );
    $wpdb->delete( $table, array( 'user_id' => $user->ID, 'product_id' => $product_id ), array( '%d', '%d' ) );

    return dox_response( true, 'Removed from wishlist', array( 'product_id' => $product_id ) );
}

function dox_customer_clear_wishlist( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    global $wpdb;
    $table = dox_table_name( 'wishlist' );
    $wpdb->delete( $table, array( 'user_id' => $user->ID ), array( '%d' ) );

    return dox_response( true, 'Wishlist cleared', array() );
}

/* ---------------------------
   Reviews & Ratings
   --------------------------- */

function dox_customer_add_review( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $params = $request->get_json_params();
    $product_id = intval( $params['product_id'] ?? 0 );
    $rating = intval( $params['rating'] ?? 0 );
    $title = sanitize_text_field( $params['title'] ?? '' );
    $content = sanitize_textarea_field( $params['content'] ?? '' );
    $images = isset( $params['images'] ) && is_array( $params['images'] ) ? $params['images'] : array();

    if ( $product_id <= 0 || $rating < 1 || $rating > 5 ) {
        return dox_response( false, 'Invalid review data', array() );
    }

    // use WP_Comment to insert review as comment type 'review' or 'comment' (WooCommerce expects 'comment_type' = 'review')
    $commentdata = array(
        'comment_post_ID' => $product_id,
        'comment_author' => $user->display_name,
        'comment_author_email' => $user->user_email,
        'comment_content' => $content,
        'comment_type' => 'review',
        'user_id' => $user->ID,
        'comment_approved' => 0, // pending moderation by default
    );

    $comment_id = wp_insert_comment( $commentdata );
    if ( $comment_id ) {
        add_comment_meta( $comment_id, 'rating', $rating );
        if ( $title ) add_comment_meta( $comment_id, 'title', $title );
        // handle images (base64) -> upload media
        if ( ! empty( $images ) ) {
            $uploaded = array();
            foreach ( $images as $img_b64 ) {
                $att_id = dox_customer_handle_base64_image( $img_b64, 'review_' . $comment_id );
                if ( $att_id ) $uploaded[] = $att_id;
            }
            if ( ! empty( $uploaded ) ) {
                add_comment_meta( $comment_id, 'images', maybe_serialize( $uploaded ) );
            }
        }
    }

    return dox_response( true, 'Review submitted (pending approval)', array( 'comment_id' => $comment_id ) );
}

function dox_customer_get_product_reviews( $request ) {
    $product_id = intval( $request['id'] ?? 0 );
    if ( $product_id <= 0 ) return dox_response( false, 'Invalid product id', array() );

    $comments = get_comments( array( 'post_id' => $product_id, 'status' => 'approve', 'type' => 'review' ) );
    $data = array();
    foreach ( $comments as $c ) {
        $rating = intval( get_comment_meta( $c->comment_ID, 'rating', true ) );
        $images = maybe_unserialize( get_comment_meta( $c->comment_ID, 'images', true ) );
        if ( ! is_array( $images ) ) $images = array();
        $data[] = array( 'id' => $c->comment_ID, 'author' => $c->comment_author, 'rating' => $rating, 'title' => get_comment_meta( $c->comment_ID, 'title', true ), 'content' => $c->comment_content, 'images' => $images, 'date' => $c->comment_date );
    }
    return dox_response( true, '', $data );
}

function dox_customer_handle_base64_image( $base64, $prefix = 'dox' ) {
    if ( empty( $base64 ) ) return false;
    if ( strpos( $base64, 'base64,' ) !== false ) $base64 = explode( 'base64,', $base64 )[1];
    $decoded = base64_decode( $base64 );
    if ( $decoded === false ) return false;

    $upload = wp_upload_bits( $prefix . '_' . time() . '.jpg', null, $decoded );
    if ( ! empty( $upload['error'] ) ) return false;

    $file = $upload['file'];
    $wp_filetype = wp_check_filetype( $file, null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( basename( $file ) ),
        'post_content' => '',
        'post_status' => 'inherit'
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

/* ---------------------------
   Addresses (DB table dox_addresses)
   --------------------------- */

function dox_customer_get_addresses( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    global $wpdb;
    $table = dox_table_name( 'addresses' );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", $user->ID ) );
    $data = array();
    foreach ( $rows as $r ) {
        $data[] = array(
            'id' => intval( $r->id ),
            'type' => $r->type,
            'first_name' => $r->first_name,
            'last_name' => $r->last_name,
            'address_1' => $r->address_1,
            'address_2' => $r->address_2,
            'city' => $r->city,
            'state' => $r->state,
            'postcode' => $r->postcode,
            'country' => $r->country,
            'phone' => $r->phone,
            'email' => $r->email,
            'is_default' => boolval( $r->is_default ),
            'created_at' => $r->created_at,
        );
    }
    return dox_response( true, '', $data );
}

function dox_customer_add_address( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $params = $request->get_json_params();
    $type = in_array( $params['type'] ?? '', array( 'billing', 'shipping' ), true ) ? $params['type'] : 'shipping';
    $data = array(
        'user_id' => $user->ID,
        'type' => sanitize_text_field( $type ),
        'first_name' => sanitize_text_field( $params['first_name'] ?? '' ),
        'last_name' => sanitize_text_field( $params['last_name'] ?? '' ),
        'address_1' => sanitize_text_field( $params['address_1'] ?? '' ),
        'address_2' => sanitize_text_field( $params['address_2'] ?? '' ),
        'city' => sanitize_text_field( $params['city'] ?? '' ),
        'state' => sanitize_text_field( $params['state'] ?? '' ),
        'postcode' => sanitize_text_field( $params['postcode'] ?? '' ),
        'country' => sanitize_text_field( $params['country'] ?? '' ),
        'phone' => sanitize_text_field( $params['phone'] ?? '' ),
        'email' => sanitize_email( $params['email'] ?? '' ),
        'is_default' => intval( $params['is_default'] ?? 0 ),
        'created_at' => current_time( 'mysql' ),
    );

    global $wpdb;
    $table = dox_table_name( 'addresses' );
    $wpdb->insert( $table, $data, array_fill( 0, count( $data ), '%s' ) );
    $id = $wpdb->insert_id;
    if ( $data['is_default'] ) {
        // unset other defaults of same type
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_default = 0 WHERE user_id = %d AND id != %d AND type = %s", $user->ID, $id, $data['type'] ) );
        
        // Sync with WooCommerce standard addresses
        if ( 'billing' === $data['type'] ) {
            update_user_meta( $user->ID, 'billing_first_name', $data['first_name'] );
            update_user_meta( $user->ID, 'billing_last_name', $data['last_name'] );
            update_user_meta( $user->ID, 'billing_address_1', $data['address_1'] );
            update_user_meta( $user->ID, 'billing_city', $data['city'] );
            update_user_meta( $user->ID, 'billing_country', $data['country'] );
            update_user_meta( $user->ID, 'billing_phone', $data['phone'] );
            update_user_meta( $user->ID, 'billing_email', $data['email'] );
        } else {
            update_user_meta( $user->ID, 'shipping_first_name', $data['first_name'] );
            update_user_meta( $user->ID, 'shipping_last_name', $data['last_name'] );
            update_user_meta( $user->ID, 'shipping_address_1', $data['address_1'] );
            update_user_meta( $user->ID, 'shipping_city', $data['city'] );
            update_user_meta( $user->ID, 'shipping_country', $data['country'] );
        }
    }

    return dox_response( true, 'Address added', array( 'id' => intval( $id ) ) );
}

function dox_customer_update_address( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $id = intval( $request['id'] ?? 0 );
    if ( $id <= 0 ) return dox_response( false, 'Invalid address id', array() );

    $params = $request->get_json_params();
    $update = array();
    $fields = array( 'type','first_name','last_name','address_1','address_2','city','state','postcode','country','phone','email','is_default' );
    foreach ( $fields as $f ) {
        if ( isset( $params[ $f ] ) ) {
            $update[ $f ] = in_array( $f, array( 'is_default' ), true ) ? intval( $params[ $f ] ) : sanitize_text_field( $params[ $f ] );
        }
    }

    global $wpdb;
    $table = dox_table_name( 'addresses' );
    $where = array( 'id' => $id, 'user_id' => $user->ID );
    $wpdb->update( $table, $update, $where, array_fill( 0, count( $update ), '%s' ), array( '%d', '%d' ) );

    if ( isset( $update['is_default'] ) && $update['is_default'] ) {
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_default = 0 WHERE user_id = %d AND id != %d AND type = %s", $user->ID, $id, $update['type'] ?? 'shipping' ) );
    }

    return dox_response( true, 'Address updated', array( 'id' => $id ) );
}

function dox_customer_delete_address( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $id = intval( $request['id'] ?? 0 );
    if ( $id <= 0 ) return dox_response( false, 'Invalid address id', array() );

    global $wpdb;
    $table = dox_table_name( 'addresses' );
    $wpdb->delete( $table, array( 'id' => $id, 'user_id' => $user->ID ), array( '%d', '%d' ) );

    return dox_response( true, 'Address deleted', array( 'id' => $id ) );
}

/* ---------------------------
   Settings & Preferences
   --------------------------- */

function dox_customer_get_settings( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $settings = array(
        'notifications' => boolval( get_user_meta( $user->ID, 'dox_notifications', true ) ),
        'email_notifications' => boolval( get_user_meta( $user->ID, 'dox_email_notifications', true ) ),
        'sms_notifications' => boolval( get_user_meta( $user->ID, 'dox_sms_notifications', true ) ),
        'order_updates' => boolval( get_user_meta( $user->ID, 'dox_order_updates', true ) ),
        'promotional_emails' => boolval( get_user_meta( $user->ID, 'dox_promotional_emails', true ) ),
        'language' => get_user_meta( $user->ID, 'dox_language', true ) ?: 'ar',
        'currency' => get_user_meta( $user->ID, 'dox_currency', true ) ?: get_woocommerce_currency(),
    );

    return dox_response( true, '', $settings );
}

function dox_customer_update_settings( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $params = $request->get_json_params();
    $map = array( 'dox_notifications' => 'notifications', 'dox_email_notifications' => 'email_notifications', 'dox_sms_notifications' => 'sms_notifications', 'dox_order_updates' => 'order_updates', 'dox_promotional_emails' => 'promotional_emails', 'dox_language' => 'language', 'dox_currency' => 'currency' );

    foreach ( $map as $meta_key => $input_key ) {
        if ( isset( $params[ $input_key ] ) ) {
            update_user_meta( $user->ID, $meta_key, is_bool( $params[ $input_key ] ) ? $params[ $input_key ] : sanitize_text_field( $params[ $input_key ] ) );
        }
    }

    return dox_response( true, 'Settings updated', array() );
}

/* ---------------------------
   Small Utilities (vendor info & reviews helper)
   --------------------------- */

function dox_customer_get_product_vendor_info( $product_id ) {
    return Doken_Ox_Vendor_Model::get_vendor_by_product( $product_id );
}

function dox_customer_get_recent_reviews( $product_id, $limit = 5 ) {
    $comments = get_comments( array( 'post_id' => $product_id, 'status' => 'approve', 'type' => 'review', 'number' => $limit ) );
    $data = array();
    foreach ( $comments as $c ) {
        $data[] = array( 'id' => $c->comment_ID, 'author' => $c->comment_author, 'rating' => intval( get_comment_meta( $c->comment_ID, 'rating', true ) ), 'content' => $c->comment_content, 'date' => $c->comment_date );
    }
    return $data;
}

function dox_customer_search_stores( $request ) {
    $query = sanitize_text_field( $request->get_param( 'q' ) );
    if ( empty( $query ) ) {
        return dox_response( true, '', array() );
    }

    $users = get_users(
        array(
            'role__in'       => array( 'seller', 'vendor' ),
            'search'         => '*' . $query . '*',
            'search_columns' => array( 'user_login', 'user_nicename', 'display_name', 'user_email' ),
            'number'         => 20,
        )
    );
    $data  = array();
    foreach ( $users as $user ) {
        $data[] = dox_format_vendor( $user->ID );
    }

    return dox_response( true, '', $data );
}

function dox_get_user_cart( $user_id ) {
    $cart = get_user_meta( $user_id, 'dox_cart_items', true );
    return is_array( $cart ) ? $cart : array();
}

function dox_save_user_cart( $user_id, $cart ) {
    update_user_meta( $user_id, 'dox_cart_items', is_array( $cart ) ? $cart : array() );
}

/**
 * Register Customer routes hooked into doken_ox_register_routes.
 */
function dox_customer_register_routes( $namespace ) {
    $auth_callback = array( 'Doken_Ox_API_Router', 'permission_authenticated' );

    // Products.
    register_rest_route(
        $namespace,
        '/customer/products',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_customer_get_products',
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'POST',
                'callback'            => 'dox_customer_get_products',
                'permission_callback' => '__return_true',
            ),
        )
    );

    register_rest_route(
        $namespace,
        '/customer/products/(?P<id>\d+)',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_get_product',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        $namespace,
        '/customer/products/search',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_search_products',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        $namespace,
        '/customer/featured-products',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_featured_products',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        $namespace,
        '/customer/latest-collections',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_latest_collections',
            'permission_callback' => '__return_true',
        )
    );

    // Categories.
    register_rest_route(
        $namespace,
        '/customer/categories',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_get_categories',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        $namespace,
        '/customer/categories/(?P<id>\d+)/products',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_get_category_products',
            'permission_callback' => '__return_true',
        )
    );

    // Stores.
    register_rest_route(
        $namespace,
        '/customer/stores',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_get_stores',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        $namespace,
        '/customer/stores/(?P<id>\d+)',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_get_store',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        $namespace,
        '/customer/stores/(?P<id>\d+)/products',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_get_store_products',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        $namespace,
        '/customer/stores/search',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_search_stores',
            'permission_callback' => '__return_true',
        )
    );

    // Cart & Checkout.
    register_rest_route(
        $namespace,
        '/customer/cart',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_customer_get_cart',
                'permission_callback' => $auth_callback,
            ),
            array(
                'methods'             => 'POST',
                'callback'            => 'dox_customer_add_to_cart',
                'permission_callback' => $auth_callback,
            ),
        )
    );

    register_rest_route(
        $namespace,
        '/customer/cart/(?P<key>[A-Za-z0-9_-]+)',
        array(
            array(
                'methods'             => 'PUT',
                'callback'            => 'dox_customer_update_cart_item',
                'permission_callback' => $auth_callback,
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => 'dox_customer_remove_cart_item',
                'permission_callback' => $auth_callback,
            ),
        )
    );

    register_rest_route(
        $namespace,
        '/customer/cart/clear',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_customer_clear_cart',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/cart/apply-coupon',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_customer_apply_coupon',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/cart/coupon',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_customer_apply_coupon',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/checkout/address',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_customer_save_address',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/checkout/shipping-methods',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_get_shipping_methods',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/checkout/shipping',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_customer_checkout_shipping',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/checkout/review',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_checkout_review',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/checkout/payment',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_customer_checkout_payment',
            'permission_callback' => $auth_callback,
        )
    );

    // Orders.
    register_rest_route(
        $namespace,
        '/customer/orders',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_customer_get_orders',
                'permission_callback' => $auth_callback,
            ),
            array(
                'methods'             => 'POST',
                'callback'            => 'dox_customer_create_order',
                'permission_callback' => $auth_callback,
            ),
        )
    );

    register_rest_route(
        $namespace,
        '/customer/orders/(?P<id>\d+)',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_get_order',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/orders/(?P<id>\d+)/cancel',
        array(
            'methods'             => 'PUT',
            'callback'            => 'dox_customer_cancel_order',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/orders/(?P<id>\d+)/track',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_track_order',
            'permission_callback' => $auth_callback,
        )
    );

    // Wishlist.
    register_rest_route(
        $namespace,
        '/customer/wishlist',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_get_wishlist',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/wishlist/(?P<product_id>\d+)',
        array(
            array(
                'methods'             => 'POST',
                'callback'            => 'dox_customer_add_wishlist',
                'permission_callback' => $auth_callback,
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => 'dox_customer_remove_wishlist',
                'permission_callback' => $auth_callback,
            ),
        )
    );

    register_rest_route(
        $namespace,
        '/customer/wishlist/clear',
        array(
            'methods'             => 'DELETE',
            'callback'            => 'dox_customer_clear_wishlist',
            'permission_callback' => $auth_callback,
        )
    );

    // Reviews.
    register_rest_route(
        $namespace,
        '/customer/reviews',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_customer_add_review',
            'permission_callback' => $auth_callback,
        )
    );

    register_rest_route(
        $namespace,
        '/customer/products/(?P<id>\d+)/reviews',
        array(
            'methods'             => 'GET',
            'callback'            => 'dox_customer_get_product_reviews',
            'permission_callback' => '__return_true',
        )
    );

    // Addresses.
    register_rest_route(
        $namespace,
        '/customer/addresses',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_customer_get_addresses',
                'permission_callback' => $auth_callback,
            ),
            array(
                'methods'             => 'POST',
                'callback'            => 'dox_customer_add_address',
                'permission_callback' => $auth_callback,
            ),
        )
    );

    register_rest_route(
        $namespace,
        '/customer/addresses/(?P<id>\d+)',
        array(
            array(
                'methods'             => 'PUT',
                'callback'            => 'dox_customer_update_address',
                'permission_callback' => $auth_callback,
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => 'dox_customer_delete_address',
                'permission_callback' => $auth_callback,
            ),
        )
    );

    // Settings.
    register_rest_route(
        $namespace,
        '/customer/settings',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_customer_get_settings',
                'permission_callback' => $auth_callback,
            ),
            array(
                'methods'             => 'PUT',
                'callback'            => 'dox_customer_update_settings',
                'permission_callback' => $auth_callback,
            ),
        )
    );

    // Notifications.
    register_rest_route(
        $namespace,
        '/customer/notifications',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'dox_customer_get_notifications',
                'permission_callback' => $auth_callback,
            ),
        )
    );

    register_rest_route(
        $namespace,
        '/customer/notifications/(?P<id>\d+)/read',
        array(
            'methods'             => 'POST',
            'callback'            => 'dox_customer_mark_notification_read',
            'permission_callback' => $auth_callback,
        )
    );
}

/* ---------------------------
   Notifications Endpoints
   --------------------------- */

function dox_customer_get_notifications( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    global $wpdb;
    $table = dox_table_name( 'notifications' );
    $rows = $wpdb->get_results( $wpdb->prepare( 
        "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 50", 
        $user->ID 
    ) );

    $data = array();
    foreach ( $rows as $r ) {
        $data[] = array(
            'id'         => intval( $r->id ),
            'title'      => $r->title,
            'message'    => $r->message,
            'is_read'    => boolval( $r->is_read ),
            'created_at' => $r->created_at,
            'meta'       => maybe_unserialize( $r->meta ),
        );
    }

    return dox_response( true, '', $data );
}

function dox_customer_mark_notification_read( $request ) {
    $user = dox_current_user_or_error();
    if ( is_wp_error( $user ) ) return dox_response( false, $user->get_error_message(), array() );

    $id = intval( $request['id'] );
    global $wpdb;
    $table = dox_table_name( 'notifications' );
    
    $wpdb->update( $table, array( 'is_read' => 1 ), array( 'id' => $id, 'user_id' => $user->ID ) );

    return dox_response( true, 'Notification marked as read', array() );
}

add_action( 'doken_ox_register_routes', 'dox_customer_register_routes' );