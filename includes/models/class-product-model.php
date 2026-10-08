<?php

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Product_Model {

    /**
     * Fetch paginated products with filters.
     *
     * @param array $args
     *
     * @return array{items: array, total: int}
     */
    public static function list_products( array $args ): array {
        $defaults = array(
            'page'        => 1,
            'per_page'    => 20,
            'status'      => 'publish',
            'category'    => null,
            'on_sale'     => null,
            'min_price'   => null,
            'max_price'   => null,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'vendor_id'   => null,
            'featured'    => null,
            'search'      => null,
            'attributes'  => array(), // array( 'pa_color' => array('red', 'blue') )
        );

        $params = wp_parse_args( $args, $defaults );

        $query_args = array(
            'status' => $params['status'],
            'limit'  => (int) $params['per_page'],
            'page'   => (int) $params['page'],
        );

        if ( ! empty( $params['search'] ) ) {
            $query_args['s'] = sanitize_text_field( $params['search'] );
        }
        if ( $params['category'] ) {
            $query_args['category'] = array( (int) $params['category'] );
        }
        if ( null !== $params['on_sale'] ) {
            $query_args['on_sale'] = (bool) $params['on_sale'];
        }
        if ( null !== $params['min_price'] ) {
            $query_args['min_price'] = (float) $params['min_price'];
        }
        if ( null !== $params['max_price'] ) {
            $query_args['max_price'] = (float) $params['max_price'];
        }
        if ( $params['vendor_id'] ) {
            $query_args['author'] = (int) $params['vendor_id'];
        }
        if ( null !== $params['featured'] ) {
            $query_args['featured'] = (bool) $params['featured'];
        }

        // Attribute filtering (Taxonomy based)
        if ( ! empty( $params['attributes'] ) && is_array( $params['attributes'] ) ) {
            $tax_query = array( 'relation' => 'AND' );
            foreach ( $params['attributes'] as $taxonomy => $terms ) {
                if ( ! empty( $terms ) ) {
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => (array) $terms,
                        'operator' => 'IN',
                    );
                }
            }
            if ( count( $tax_query ) > 1 ) {
                $query_args['tax_query'] = $tax_query;
            }
        }

        $orderby = strtolower( (string) $params['orderby'] );
        if ( 'price' === $orderby ) {
            $query_args['orderby']  = 'meta_value_num';
            $query_args['meta_key'] = '_price';
        } elseif ( 'total_sales' === $orderby || 'popularity' === $orderby ) {
            $query_args['orderby']  = 'meta_value_num';
            $query_args['meta_key'] = 'total_sales';
        } else {
            $query_args['orderby'] = $orderby;
        }
        $query_args['order'] = $params['order'];

        $query    = new WC_Product_Query( $query_args );
        $products = $query->get_products();

        $items = array();
        foreach ( $products as $product ) {
            if ( $product instanceof WC_Product ) {
                $items[] = self::format_product_basic( $product );
            }
        }

        // Count for current filters
        $total = self::count_products( $params );

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    /**
     * Count matching products for pagination.
     */
    protected static function count_products( array $params ): int {
        $q = array(
            'post_type'      => 'product',
            'post_status'    => $params['status'] ?? 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(),
            'meta_query'     => array(),
        );

        if ( ! empty( $params['search'] ) ) {
            $q['s'] = sanitize_text_field( $params['search'] );
        }

        if ( ! empty( $params['category'] ) ) {
            $q['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => (int) $params['category'],
            );
        }

        if ( ! empty( $params['vendor_id'] ) ) {
            $q['author'] = (int) $params['vendor_id'];
        }

        if ( ! empty( $params['attributes'] ) && is_array( $params['attributes'] ) ) {
            foreach ( $params['attributes'] as $taxonomy => $terms ) {
                if ( ! empty( $terms ) ) {
                    $q['tax_query'][] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => (array) $terms,
                    );
                }
            }
        }

        if ( count( $q['tax_query'] ) > 1 ) {
            $q['tax_query']['relation'] = 'AND';
        }

        if ( null !== $params['on_sale'] && true === $params['on_sale'] ) {
            $q['post__in'] = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
        }

        if ( null !== $params['min_price'] || null !== $params['max_price'] ) {
            $meta_query = array( 'relation' => 'AND' );
            if ( null !== $params['min_price'] ) {
                $meta_query[] = array(
                    'key'     => '_price',
                    'value'   => (float) $params['min_price'],
                    'type'    => 'DECIMAL',
                    'compare' => '>=',
                );
            }
            if ( null !== $params['max_price'] ) {
                $meta_query[] = array(
                    'key'     => '_price',
                    'value'   => (float) $params['max_price'],
                    'type'    => 'DECIMAL',
                    'compare' => '<=',
                );
            }
            $q['meta_query'] = $meta_query;
        }

        $query = new WP_Query( $q );
        return (int) $query->found_posts;
    }

    /**
     * Get dynamic filter data for the frontend.
     */
    public static function get_filters_data( $category_id = null ): array {
        global $wpdb;

        // 1. Price Range
        $prices = $wpdb->get_row( "SELECT MIN(CAST(meta_value AS DECIMAL)) as min_price, MAX(CAST(meta_value AS DECIMAL)) as max_price FROM {$wpdb->postmeta} WHERE meta_key = '_price'" );
        
        // 2. Attributes (Global and Custom)
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $attributes = array();
        foreach ( $attribute_taxonomies as $tax ) {
            $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
            $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $options = array();
                foreach ( $terms as $term ) {
                    $options[] = array(
                        'id'   => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    );
                }
                $attributes[] = array(
                    'id'    => $tax->attribute_id,
                    'label' => $tax->attribute_label,
                    'slug'  => $taxonomy,
                    'type'  => $tax->attribute_type,
                    'options' => $options,
                );
            }
        }

        // 3. Categories (if not scoped)
        $categories = array();
        if ( ! $category_id ) {
            $cat_terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0 ) );
            foreach ( $cat_terms as $cat ) {
                $categories[] = array(
                    'id'   => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                );
            }
        }

        return array(
            'price_range' => array(
                'min' => (float) ($prices->min_price ?? 0),
                'max' => (float) ($prices->max_price ?? 0),
            ),
            'attributes' => $attributes,
            'categories' => $categories,
        );
    }

    /**
     * Retrieve a single product array.
     */
    public static function get_product( int $product_id ): ?array {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return null;
        }

        return self::format_product_full( $product );
    }

    /**
     * Search products by term.
     */
    public static function search( string $term, int $limit = 20 ): array {
        $args = array(
            'status' => 'publish',
            'limit'  => $limit,
            'search' => $term,
        );
        $products = wc_get_products( $args );
        $data     = array();
        foreach ( $products as $product ) {
            $data[] = self::format_product_basic( $product );
        }
        return $data;
    }

    /**
     * Get image with sizes.
     */
    protected static function get_image_data( $attachment_id ) {
        if ( ! $attachment_id ) return null;
        return array(
            'src'       => wp_get_attachment_url( $attachment_id ),
            'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
            'medium'    => wp_get_attachment_image_url( $attachment_id, 'medium' ),
            'large'     => wp_get_attachment_image_url( $attachment_id, 'large' ),
            'full'      => wp_get_attachment_image_url( $attachment_id, 'full' ),
        );
    }

    /**
     * Format product for listings.
     */
    public static function format_product_basic( WC_Product $product ): array {
        $img_id = $product->get_image_id();
        return array(
            'id'             => $product->get_id(),
            'name'           => $product->get_name(),
            'slug'           => $product->get_slug(),
            'price'          => wc_format_decimal( $product->get_price(), 2 ),
            'regular_price'  => wc_format_decimal( $product->get_regular_price(), 2 ) ?: "",
            'sale_price'     => wc_format_decimal( $product->get_sale_price(), 2 ) ?: "",
            'on_sale'        => $product->is_on_sale(),
            'stock_status'   => $product->get_stock_status(),
            'stock_quantity' => (int) $product->get_stock_quantity(),
            'image'          => wp_get_attachment_url( $img_id ) ?: "",
            'image_sizes'    => self::get_image_data( $img_id ) ?: new stdClass(),
            'rating'         => (float) $product->get_average_rating(),
            'reviews_count'  => (int) $product->get_rating_count(),
            'categories'     => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'all' ) ),
            'vendor'         => Doken_Ox_Vendor_Model::get_vendor_by_product( $product->get_id() ),
            'in_stock'       => $product->is_in_stock(),
        );
    }

    /**
     * Detailed formatter.
     */
    public static function format_product_full( WC_Product $product ): array {
        $img_id = $product->get_image_id();
        
        // Build Base Data
        $data = array(
            'id'             => (int) $product->get_id(),
            'name'           => $product->get_name(),
            'slug'           => $product->get_slug(),
            'price'          => (string) wc_format_decimal( $product->get_price(), 2 ),
            'regular_price'  => (string) ( wc_format_decimal( $product->get_regular_price(), 2 ) ?: "" ),
            'sale_price'     => (string) ( wc_format_decimal( $product->get_sale_price(), 2 ) ?: "" ),
            'on_sale'        => $product->is_on_sale(),
            'stock_status'   => $product->get_stock_status(),
            'stock_quantity' => (int) $product->get_stock_quantity(),
            'image'          => wp_get_attachment_url( $img_id ) ?: "",
            'image_sizes'    => self::get_image_data( $img_id ) ?: new stdClass(),
            'rating'         => (float) $product->get_average_rating(),
            'reviews_count'  => (int) $product->get_rating_count(),
            'description'    => wp_kses_post( $product->get_description() ),
            'short_description' => wp_kses_post( $product->get_short_description() ),
        );

        // Images Gallery
        $attachment_ids = array_filter( array_merge(
            array( $img_id ),
            $product->get_gallery_image_ids()
        ) );
        $data['images'] = array_values( array_map( 'wp_get_attachment_url', $attachment_ids ) );

        // Variations logic
        $variations_data = array();
        if ( $product->is_type( 'variable' ) ) {
            $variation_attributes = $product->get_variation_attributes();
            $children_ids         = $product->get_children();
            $children_products    = array();
            
            foreach ( $children_ids as $child_id ) {
                $children_products[] = wc_get_product( $child_id );
            }

            $group_id = 1;
            foreach ( $variation_attributes as $taxonomy => $options ) {
                $group = array(
                    'id'      => $group_id++,
                    'type'    => wc_attribute_label( $taxonomy ),
                    'options' => array(),
                );

                $option_id = 1;
                foreach ( $options as $option_slug ) {
                    $rep_price = $product->get_price();
                    $rep_image = $data['image'];
                    
                    foreach ( $children_products as $child ) {
                        if ( ! $child ) continue;
                        $child_attrs = $child->get_attributes();
                        if ( isset( $child_attrs[ $taxonomy ] ) && $child_attrs[ $taxonomy ] === $option_slug ) {
                            $rep_price = $child->get_price();
                            if ( $child->get_image_id() ) {
                                $rep_image = wp_get_attachment_url( $child->get_image_id() );
                            }
                            break;
                        }
                    }

                    $term_name = $option_slug;

                    if ( taxonomy_exists( $taxonomy ) ) {
                        $term = get_term_by( 'slug', $option_slug, $taxonomy );
                        if ( $term ) {
                            $term_name = $term->name;
                        }
                    }

                    $group['options'][] = array(
                        'id'    => $option_id++,
                        'name'  => $term_name,
                        'price' => (float) wc_format_decimal( $rep_price, 2 ),
                        'image' => $rep_image,
                    );
                }
                $variations_data[] = $group;
            }
        }

        $data['variations'] = $variations_data;
        $data['in_stock']   = $product->is_in_stock();

        return $data;
    }

}
