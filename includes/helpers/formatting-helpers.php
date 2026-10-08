<?php
/**
 * Formatting helpers shared across modules.
 *
 * @package Doken_Ox_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('dox_format_user')) {
    /**
     * Normalize WP_User to API payload.
     *
     * @param WP_User $user User object.
     *
     * @return array
     */
    function dox_format_user(WP_User $user)
    {
        $custom_avatar = get_user_meta($user->ID, 'dox_avatar', true);
        return array(
            'id'     => (int) $user->ID,
            'email'  => $user->user_email,
            'name'   => $user->display_name,
            'role'   => implode(',', $user->roles),
            'avatar' => $custom_avatar ?: get_avatar_url($user->ID),
            'phone'  => get_user_meta($user->ID, 'phone', true),
        );
    }
}

if (!function_exists('dox_format_vendor')) {
    /**
     * Normalize Dokan vendor/store info.
     *
     * @param int $vendor_id Vendor user ID.
     *
     * @return array
     */
    function dox_format_vendor($vendor_id)
    {
        $vendor_id = (int) $vendor_id;
        $store = function_exists('dokan_get_store_info') ? dokan_get_store_info($vendor_id) : array();

        return array(
            'id' => $vendor_id,
            'name' => $store['store_name'] ?? get_the_author_meta('display_name', $vendor_id),
            'description' => $store['store_description'] ?? '',
            'logo' => !empty($store['store_logo']) ? wp_get_attachment_url($store['store_logo']) : '',
            'icon' => !empty($store['store_logo']) ? wp_get_attachment_url($store['store_logo']) : '',
            'banner' => !empty($store['banner']) ? wp_get_attachment_url($store['banner']) : '',
            'rating' => function_exists('dokan_get_seller_rating') ? (float) dokan_get_seller_rating($vendor_id) : 0.0,
            'phone' => get_user_meta($vendor_id, 'phone', true),
            'email' => get_the_author_meta('user_email', $vendor_id),
        );
    }
}

if (!function_exists('dox_format_product_basic')) {
    /**
     * Lightweight product formatter for list responses.
     *
     * @param WC_Product $product Product object.
     *
     * @return array
     */
    function dox_format_product_basic(WC_Product $product)
    {
        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'price' => wc_format_decimal($product->get_price(), 2),
            'regular_price' => wc_format_decimal($product->get_regular_price(), 2),
            'sale_price' => wc_format_decimal($product->get_sale_price(), 2),
            'on_sale' => $product->is_on_sale(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'image' => wp_get_attachment_url($product->get_image_id()),
        );
    }
}

