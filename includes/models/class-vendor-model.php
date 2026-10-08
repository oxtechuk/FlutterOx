<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Doken_Ox_Vendor_Model
{

    /**
     * Format vendor info.
     */
    public static function format_vendor(int $vendor_id): array
    {
        $store = function_exists('dokan_get_store_info') ? dokan_get_store_info($vendor_id) : array();

        return array(
            'id' => $vendor_id,
            'name' => $store['store_name'] ?? get_the_author_meta('display_name', $vendor_id),
            'description' => $store['store_description'] ?? '',
            'slug' => isset($store['store_name']) ? sanitize_title($store['store_name']) : sanitize_title(get_the_author_meta('display_name', $vendor_id)),
            'logo' => isset($store['store_logo']) ? wp_get_attachment_url($store['store_logo']) : '',
            'icon' => isset($store['store_logo']) ? wp_get_attachment_url($store['store_logo']) : '',
            'banner' => isset($store['banner']) ? wp_get_attachment_url($store['banner']) : '',
            'rating' => function_exists('dokan_get_seller_rating') ? (float) dokan_get_seller_rating($vendor_id) : 0,
            'reviews_count' => 0,
            'products_count' => count_user_posts($vendor_id, 'product'),
            'total_sales' => 0,
            'featured' => isset($store['featured']) ? (bool) $store['featured'] : false,
            'address' => $store['location'] ?? '',
            'phone' => get_user_meta($vendor_id, 'phone', true),
            'email' => get_the_author_meta('user_email', $vendor_id),
        );
    }

    /**
     * Vendor info by product.
     */
    public static function get_vendor_by_product(int $product_id): array
    {
        if (function_exists('dokan_get_seller_id_by_product')) {
            $seller_id = dokan_get_seller_id_by_product($product_id);
            if ($seller_id) {
                return self::format_vendor($seller_id);
            }
        }

        $author_id = (int) get_post_field('post_author', $product_id);
        if ($author_id) {
            return self::format_vendor($author_id);
        }

        return array();
    }

    /**
     * List vendor users.
     */
    public static function list_vendors(array $args = array()): array
    {
        $defaults = array(
            'number' => 20,
            'search' => '',
        );
        $params = wp_parse_args($args, $defaults);

        $query_args = array(
            'role__in' => array('seller', 'vendor'),
            'number' => (int) $params['number'],
            'search' => $params['search'] ? '*' . $params['search'] . '*' : '',
            'search_columns' => array('user_login', 'user_nicename', 'display_name', 'user_email'),
        );

        $users = get_users($query_args);
        $data = array();
        foreach ($users as $user) {
            $data[] = self::format_vendor($user->ID);
        }

        return $data;
    }
}
