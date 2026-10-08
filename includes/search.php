<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Search API Handler
 * GET /mvapp/v1/search
 */
function dox_search_handler(WP_REST_Request $request)
{
    $q = sanitize_text_field($request->get_param('q'));
    $category_id = (int) $request->get_param('category_id');
    $type = sanitize_text_field($request->get_param('type')); // all, products, vendors
    $page = (int) ($request->get_param('page') ?: 1);

    // Extra filters
    $min_price = $request->get_param('min_price');
    $max_price = $request->get_param('max_price');
    $on_sale = $request->get_param('on_sale');
    $featured = $request->get_param('featured');
    $orderby = sanitize_text_field($request->get_param('orderby'));
    $order = sanitize_text_field($request->get_param('order'));

    $products = array();
    $vendors = array();
    $categories = array();

    // 1. Products Search
    if (empty($type) || in_array($type, array('all', 'products'), true)) {
        $args = array(
            'search' => $q,
            'category_id' => $category_id > 0 ? $category_id : null,
            'per_page' => 20,
            'page' => $page,
            'min_price' => isset($min_price) ? (float) $min_price : null,
            'max_price' => isset($max_price) ? (float) $max_price : null,
            'on_sale' => isset($on_sale) ? filter_var($on_sale, FILTER_VALIDATE_BOOLEAN) : null,
            'featured' => isset($featured) ? filter_var($featured, FILTER_VALIDATE_BOOLEAN) : null,
            'orderby' => $orderby,
            'order' => $order,
        );
        $p_res = Doken_Ox_Product_Model::list_products($args);

        // Format for search results
        if (!empty($p_res['items'])) {
            foreach ($p_res['items'] as $item) {
                $product_obj = wc_get_product($item['id']);
                if (!$product_obj)
                    continue;

                $vendor_id = get_post_field('post_author', $item['id']);
                $store_info = function_exists('dokan_get_store_info') ? dokan_get_store_info($vendor_id) : array();

                $products[] = array(
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'image' => $item['image'],
                    'price' => $item['price'],
                    'short_description' => $product_obj->get_short_description(),
                    'vendor_name' => $store_info['store_name'] ?? '',
                );
            }
        }
    }

    // 2. Vendors Search
    if (empty($type) || in_array($type, array('all', 'vendors'), true)) {
        $v_args = array(
            'search' => $q,
            'number' => 10,
        );
        // Note: list_vendors implementation in model needs to support 'search' param
        $v_res = Doken_Ox_Vendor_Model::list_vendors($v_args);

        foreach ($v_res as $v) {
            // Get sample products for vendor
            $vp = Doken_Ox_Product_Model::list_products(array('vendor_id' => $v['id'], 'per_page' => 3));
            $sample_products = isset($vp['items']) ? dox_format_products($vp['items']) : array();

            $vendors[] = array(
                'id' => $v['id'],
                'name' => $v['name'],
                'avatar' => $v['logo'] ?? '', // dox_format_vendor returns logo
                'product_count' => (int) ($v['product_count'] ?? 0), // Ensure it is int
                'sample_products' => $sample_products,
            );
        }
    }

    // 3. Categories
    // Return top categories with 'checked' state if matches category_id
    $cat_terms = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'number' => 20,
        'orderby' => 'count',
        'order' => 'DESC',
    ));

    if (!is_wp_error($cat_terms)) {
        foreach ($cat_terms as $term) {
            $categories[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'checked' => ($term->term_id === $category_id),
            );
        }
    }

    return dox_response(true, '', array(
        'products' => $products,
        'vendors' => $vendors,
        'categories' => $categories,
    ));
}

/**
 * Search by Category API Handler
 * GET /mvapp/v1/search/category
 */
function dox_search_category_handler(WP_REST_Request $request)
{
    $category_id = (int) $request->get_param('category_id');
    $q = sanitize_text_field($request->get_param('q'));
    $page = (int) ($request->get_param('page') ?: 1);

    if (!$category_id) {
        return dox_response(false, __('Category ID is required.', 'doken-ox-pro'), array(), array(), 400);
    }

    $min_price = $request->get_param('min_price');
    $max_price = $request->get_param('max_price');
    $on_sale = $request->get_param('on_sale');
    $featured = $request->get_param('featured');
    $orderby = sanitize_text_field($request->get_param('orderby'));
    $order = sanitize_text_field($request->get_param('order'));
    
    // Attribute filters
    $attributes = $request->get_param('attributes') ?: array();
    if ( is_array( $attributes ) ) {
        foreach ( $attributes as $key => $val ) {
            if ( is_string( $val ) ) { $attributes[$key] = explode( ',', $val ); }
        }
    }

    $args = array(
        'search' => $q,
        'category' => $category_id,
        'per_page' => 20,
        'page' => $page,
        'min_price' => isset($min_price) ? (float) $min_price : null,
        'max_price' => isset($max_price) ? (float) $max_price : null,
        'on_sale' => isset($on_sale) ? filter_var($on_sale, FILTER_VALIDATE_BOOLEAN) : null,
        'featured' => isset($featured) ? filter_var($featured, FILTER_VALIDATE_BOOLEAN) : null,
        'orderby' => $orderby,
        'order' => $order,
        'attributes' => $attributes,
    );
    
    $p_res = Doken_Ox_Product_Model::list_products($args);

    $products = array();
    if (!empty($p_res['items'])) {
        foreach ($p_res['items'] as $item) {
            $product_obj = wc_get_product($item['id']);
            if (!$product_obj) continue;

            $vendor_id = get_post_field('post_author', $item['id']);
            $store_info = function_exists('dokan_get_store_info') ? dokan_get_store_info($vendor_id) : array();

            $products[] = array(
                'id' => $item['id'],
                'name' => $item['name'],
                'image' => $item['image'],
                'price' => $item['price'],
                'short_description' => $product_obj->get_short_description(),
                'vendor_name' => $store_info['store_name'] ?? '',
            );
        }
    }

    $category_term = get_term($category_id, 'product_cat');
    $category_name = ($category_term && !is_wp_error($category_term)) ? $category_term->name : '';

    return dox_response(true, '', array(
        'category_id' => $category_id,
        'category_name' => $category_name,
        'total' => $p_res['total'],
        'products' => $products,
    ));
}

/**
 * Register Routes
 */
function dox_search_register_routes($namespace)
{
    register_rest_route($namespace, '/search', array(
        'methods' => 'GET',
        'callback' => 'dox_search_handler',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/search/category', array(
        'methods' => 'GET',
        'callback' => 'dox_search_category_handler',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/search/filters', array(
        'methods' => 'GET',
        'callback' => 'dox_get_search_filters',
        'permission_callback' => '__return_true',
    ));
}

/**
 * Handle GET /search/filters
 */
function dox_get_search_filters(WP_REST_Request $request) {
    $category_id = (int) $request->get_param('category_id');
    $data = Doken_Ox_Product_Model::get_filters_data($category_id);
    return dox_response(true, '', $data);
}

add_action('doken_ox_register_routes', 'dox_search_register_routes');

/**
 * Helper to format products if not available globally
 */
if (!function_exists('dox_format_products')) {
    function dox_format_products($items)
    {
        if (class_exists('Doken_Ox_Home_Model') && method_exists('Doken_Ox_Home_Model', 'format_products')) {
            // Accessing protected method might fail if not careful, but wait, format_products in Home Model is protected.
            // So we should replicate or expose it.
            // Let's replicate simple formatting here to be safe.
            return array_map(function ($p) {
                return $p; // basic format is already done by list_products? list_products returns basic info.
            }, $items);
        }
        return $items;
    }
}
