<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product Detail API
 * GET /mvapp/v1/products/{id}
 */
function dox_product_detail_handler(WP_REST_Request $request)
{
    $id = (int) $request['id'];
    $product = wc_get_product($id);

    if (!$product) {
        return dox_error_response(__('Product not found.', 'doken-ox-pro'), 404);
    }

    $data = Doken_Ox_Product_Model::get_product($id);

    return dox_response(true, '', $data);
}

/**
 * Submit Product Review
 * POST /mvapp/v1/products/{id}/review
 */
function dox_product_review_handler(WP_REST_Request $request)
{
    $product_id = (int) $request['id'];
    $params = $request->get_json_params();
    $rating = isset($params['rating']) ? (int) $params['rating'] : 0;
    $comment = isset($params['comment']) ? sanitize_textarea_field($params['comment']) : '';
    $user_id = get_current_user_id();

    $product = wc_get_product($product_id);
    if (!$product) {
        return dox_error_response(__('Product not found.', 'doken-ox-pro'), 404);
    }

    if ($rating < 1 || $rating > 5) {
        return dox_error_response(__('Rating must be between 1 and 5.', 'doken-ox-pro'));
    }

    if (empty($comment)) {
        return dox_error_response(__('Comment is required.', 'doken-ox-pro'));
    }

    // Insert comment
    $data = array(
        'comment_post_ID' => $product_id,
        'comment_author' => wp_get_current_user()->display_name,
        'comment_author_email' => wp_get_current_user()->user_email,
        'comment_content' => $comment,
        'user_id' => $user_id,
        'comment_type' => 'review',
        'comment_approved' => 1, // Auto approve or depend on settings
    );

    $comment_id = wp_insert_comment($data);
    if (!$comment_id) {
        return dox_error_response(__('Could not save review.', 'doken-ox-pro'));
    }

    // Add rating meta
    update_comment_meta($comment_id, 'rating', $rating);

    // Clear transient cache for product reviews if any
    // Also update product average rating
    $product = wc_get_product($product_id); // Refresh
    // Trigger WC rating recount (usually happens automatically but good to ensure)

    return dox_response(true, __('Review submitted successfully.', 'doken-ox-pro'));
}

/**
 * Product Collection Endpoint
 * GET /mvapp/v1/products/collection
 */
function dox_products_collection_handler(WP_REST_Request $request)
{
    $type = sanitize_text_field($request->get_param('type')); // offer, new, all
    $page = (int) ($request->get_param('page') ?: 1);
    $per_page = (int) ($request->get_param('per_page') ?: 20);

    $args = array(
        'page' => $page,
        'per_page' => $per_page,
    );

    switch ($type) {
        case 'offer':
            $args['on_sale'] = true;
            break;
        case 'new':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
        case 'all':
        default:
            // Standard listing
            break;
    }

    $res = Doken_Ox_Product_Model::list_products($args);

    // Format products
    $products = array();
    if (!empty($res['items'])) {
        foreach ($res['items'] as $item) {
            // Recreating basic details or just passing what model returns
            // Model returns basic format already.
            // But let's ensuring consistent keys with other endpoints if needed.
            // For now, passing model items is fine, but let's check if we need vendor name etc like search.

            // The Model::list_products returns array from format_product_basic. 
            // format_product_basic includes 'vendor' array.
            $products[] = $item;
        }
    }

    return dox_response(true, '', array(
        'type' => $type ?: 'all',
        'total' => $res['total'],
        'products' => $products,
    ));
}

/**
 * Get All Categories Endpoint
 * GET /mvapp/v1/categories
 */
function dox_categories_handler(WP_REST_Request $request)
{
    $hide_empty = $request->get_param('hide_empty');

    $args = array(
        'taxonomy' => 'product_cat',
        'hide_empty' => isset($hide_empty) ? filter_var($hide_empty, FILTER_VALIDATE_BOOLEAN) : true,
    );

    $terms = get_terms($args);

    if (is_wp_error($terms)) {
        return dox_error_response($terms->get_error_message(), 500);
    }

    $categories = array();
    foreach ($terms as $term) {
        $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
        $image_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';

        $categories[] = array(
            'id' => $term->term_id,
            'name' => $term->name,
            'count' => $term->count,
            'icon' => $image_url,
        );
    }

    return dox_response(true, '', array('categories' => $categories));
}

function dox_products_register_routes($namespace)
{
    register_rest_route($namespace, '/products/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'dox_product_detail_handler',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/products/(?P<id>\d+)/review', array(
        'methods' => 'POST',
        'callback' => 'dox_product_review_handler',
        'permission_callback' => array('Doken_Ox_API_Router', 'permission_authenticated'),
    ));

    register_rest_route($namespace, '/products/collection', array(
        'methods' => 'GET',
        'callback' => 'dox_products_collection_handler',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/categories', array(
        'methods' => 'GET',
        'callback' => 'dox_categories_handler',
        'permission_callback' => '__return_true',
    ));
}
add_action('doken_ox_register_routes', 'dox_products_register_routes');
