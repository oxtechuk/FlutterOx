<?php

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Home_Model {

    public const CACHE_TTL = 600; // 10 دقائق

    protected static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'dox_home_sections';
    }

    protected static function events_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'dox_home_events';
    }

    /**
     * إرجاع عناصر القسم المطلوب.
     */
    public static function get_section_items( string $section, array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'status' => 1,
        );
        $args = wp_parse_args( $args, $defaults );

        $table = self::table();
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE section = %s" . ( $args['status'] ? $wpdb->prepare( " AND status = %d", $args['status'] ) : '' ) . " ORDER BY sort_order ASC, id DESC",
            $section
        );

        $rows = $wpdb->get_results( $query );
        $items = array();

        foreach ( $rows as $row ) {
            if ( self::is_expired( $row ) ) {
                continue;
            }
            $item = array(
                'id'        => (int) $row->id,
                'title'     => $row->title,
                'subtitle'  => $row->subtitle,
                'media_url' => $row->media_id ? wp_get_attachment_url( (int) $row->media_id ) : '',
                'link_url'  => $row->link_url,
                'data'      => maybe_unserialize( $row->data ),
                'sort_order'=> (int) $row->sort_order,
            );
            $items[] = $item;
        }

        return $items;
    }

    protected static function is_expired( $row ): bool {
        $now = current_time( 'timestamp' );
        if ( $row->starts_at && strtotime( $row->starts_at ) > $now ) {
            return true;
        }
        if ( $row->ends_at && strtotime( $row->ends_at ) < $now ) {
            return true;
        }
        return false;
    }

    public static function insert_section( array $data ): int {
        global $wpdb;
        $table = self::table();

        $wpdb->insert(
            $table,
            array(
                'section'    => sanitize_key( $data['section'] ),
                'title'      => sanitize_text_field( $data['title'] ?? '' ),
                'subtitle'   => sanitize_text_field( $data['subtitle'] ?? '' ),
                'media_id'   => isset( $data['media_id'] ) ? (int) $data['media_id'] : null,
                'link_url'   => esc_url_raw( $data['link_url'] ?? '' ),
                'data'       => maybe_serialize( $data['data'] ?? array() ),
                'sort_order' => (int) ( $data['sort_order'] ?? 0 ),
                'status'     => isset( $data['status'] ) ? (int) $data['status'] : 1,
                'starts_at'  => ! empty( $data['starts_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data['starts_at'] ) ) : null,
                'ends_at'    => ! empty( $data['ends_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data['ends_at'] ) ) : null,
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public static function update_section( int $id, array $data ): bool {
        global $wpdb;
        $table = self::table();

        $update = array();
        $format = array();

        $map = array(
            'section'    => '%s',
            'title'      => '%s',
            'subtitle'   => '%s',
            'media_id'   => '%d',
            'link_url'   => '%s',
            'data'       => '%s',
            'sort_order' => '%d',
            'status'     => '%d',
            'starts_at'  => '%s',
            'ends_at'    => '%s',
        );

        foreach ( $map as $key => $fmt ) {
            if ( isset( $data[ $key ] ) ) {
                $value = $data[ $key ];
                if ( 'data' === $key ) {
                    $value = maybe_serialize( $value );
                } elseif ( 'link_url' === $key ) {
                    $value = esc_url_raw( $value );
                } elseif ( in_array( $key, array( 'starts_at', 'ends_at' ), true ) ) {
                    $value = $value ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $value ) ) : null;
                } elseif ( 'section' === $key ) {
                    $value = sanitize_key( $value );
                } elseif ( in_array( $key, array( 'title', 'subtitle' ), true ) ) {
                    $value = sanitize_text_field( $value );
                }
                $update[ $key ] = $value;
                $format[]       = $fmt;
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        return (bool) $wpdb->update( $table, $update, array( 'id' => $id ), $format, array( '%d' ) );
    }

    public static function delete_section( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    public static function record_event( string $section, int $item_id, string $event_type, array $meta = array() ): void {
        global $wpdb;
        $wpdb->insert(
            self::events_table(),
            array(
                'section'    => sanitize_key( $section ),
                'item_id'    => $item_id,
                'event_type' => sanitize_key( $event_type ),
                'user_id'    => get_current_user_id() ?: null,
                'meta'       => maybe_serialize( $meta ),
            ),
            array( '%s', '%d', '%s', '%d', '%s' )
        );

        // زيادة العدادات
        if ( in_array( $event_type, array( 'click', 'view' ), true ) ) {
            $column = 'click' === $event_type ? 'analytics_clicks' : 'analytics_views';
            global $wpdb;
            $table = self::table();
            $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET {$column} = {$column} + 1 WHERE id = %d", $item_id ) );
        }
    }

    /**
     * يبني الـ payload الكامل للواجهة الأمامية.
     */
    public static function build_home_payload( array $args = array() ): array {
        $lang = $args['lang'] ?? 'en';
        $user_id  = get_current_user_id();
        $user_data = array();
        if ( $user_id ) {
            $u = get_userdata( $user_id );
            $user_data = array(
                'name' => $u->display_name,
                'greeting' => 'Hi, ' . $u->display_name . '!',
            );
        } else {
             $user_data = array(
                'name' => 'Guest',
                'greeting' => 'Hi, Guest!',
            );
        }

        $categories = self::get_top_categories();

        // Sliders
        $sliders = self::get_section_items( 'home_slider' );
        $formatted_sliders = self::format_bilingual_items( $sliders, $lang );

        // Ads
        $ads = self::get_section_items( 'home_ads' );
        $formatted_ads = self::format_bilingual_items( $ads, $lang );

        // Offers (Sale products)
        $offers_query = Doken_Ox_Product_Model::list_products( array( 'per_page' => 5, 'on_sale' => true ) );
        $offers = self::format_products( $offers_query['items'] ?? [] );

        // Featured Sellers
        $featured_stores = Doken_Ox_Vendor_Model::list_vendors( array( 'number' => 5 ) );
        foreach ( $featured_stores as &$store ) {
             $p = Doken_Ox_Product_Model::list_products( array( 'vendor_id' => $store['id'], 'per_page' => 3 ) );
             $store['sample_products'] = self::format_products( $p['items'] ?? [] );
             // Ensure product count is integer
             $store['product_count'] = isset($store['product_count']) ? (int)$store['product_count'] : 0;
        }

        // New Collection
        $new_query = Doken_Ox_Product_Model::list_products( array( 'per_page' => 10, 'orderby' => 'date', 'order' => 'DESC' ) );
        $new_collection = self::format_products( $new_query['items'] ?? [] );

        // All Items
        $all_query = Doken_Ox_Product_Model::list_products( array( 'per_page' => 20 ) );
        $all_items = self::format_products( $all_query['items'] ?? [] );

        return array(
            'sliders'          => $formatted_sliders,
            'ads'              => $formatted_ads,
            'user'             => $user_data,
            'categories'       => $categories,
            'offers'           => $offers,
            'featured_sellers' => $featured_stores,
            'new_collection'   => $new_collection,
            'all_items'        => $all_items,
        );
    }

    protected static function get_top_categories(): array {
        $terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'number'     => 8,
                'orderby'    => 'count',
                'order'      => 'DESC',
                'hide_empty' => true,
            )
        );

        $data = array();
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
                $data[] = array(
                    'id'    => $term->term_id,
                    'name'  => $term->name,
                    'count' => $term->count,
                    'icon'  => $thumb_id ? wp_get_attachment_url( (int) $thumb_id ) : '',
                );
            }
        }
        return $data;
    }

    protected static function format_products( array $items ): array {
        return array_map(
            function ( $product ) {
                $regular = (float) ( $product['regular_price'] ?? $product['price'] );
                $sale    = (float) ( $product['sale_price'] ?? $product['price'] );
                $discount= $regular > 0 ? round( ( ( $regular - $sale ) / $regular ) * 100 ) : 0;
                $product['discount_percent'] = max( 0, $discount );
                return $product;
            },
            $items
        );
    }

    protected static function format_bilingual_items( array $items, string $lang ): array {
        $formatted = array();
        foreach ( $items as $item ) {
            $d = is_array( $item['data'] ) ? $item['data'] : array();
            
            $title = ( $lang === 'ar' && ! empty( $d['title_ar'] ) ) ? $d['title_ar'] : ( $d['title_en'] ?? $item['title'] );
            $subtitle = ( $lang === 'ar' && ! empty( $d['subtitle_ar'] ) ) ? $d['subtitle_ar'] : ( $d['subtitle_en'] ?? $item['subtitle'] );
            $image_url = ( $lang === 'ar' && ! empty( $d['image_ar_url'] ) ) ? $d['image_ar_url'] : ( $d['image_en_url'] ?? $item['media_url'] );

            $formatted[] = array(
                'id'       => $item['id'],
                'title'    => $title,
                'subtitle' => $subtitle,
                'image'    => $image_url,
                'link'     => $item['link_url'],
            );
        }
        return $formatted;
    }
}

