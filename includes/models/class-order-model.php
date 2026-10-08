<?php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Order_Model {

    /**
     * Format order for list views.
     */
    public static function format_order_summary( WC_Order $order ): array {
        return array(
            'id'            => $order->get_id(),
            'order_number'  => $order->get_order_number(),
            'status'        => $order->get_status(),
            'date_created'  => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
            'total'         => $order->get_total(),
            'currency'      => $order->get_currency(),
            'items_count'   => count( $order->get_items() ),
            'payment_method'=> $order->get_payment_method_title(),
        );
    }

    /**
     * Detailed payload.
     */
    public static function format_order_detail( WC_Order $order ): array {
        $items = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $items[] = array(
                'product_id' => $product ? $product->get_id() : 0,
                'name'       => $item->get_name(),
                'quantity'   => $item->get_quantity(),
                'price'      => wc_format_decimal( $item->get_total() / max( 1, $item->get_quantity() ), 2 ),
                'subtotal'   => wc_format_decimal( $item->get_total(), 2 ),
                'image'      => $product ? wp_get_attachment_url( $product->get_image_id() ) : '',
            );
        }

        return array(
            'id'            => $order->get_id(),
            'order_number'  => $order->get_order_number(),
            'status'        => $order->get_status(),
            'date_created'  => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
            'items'         => $items,
            'billing'       => $order->get_address( 'billing' ),
            'shipping'      => $order->get_address( 'shipping' ),
            'totals'        => array(
                'subtotal' => $order->get_subtotal(),
                'shipping' => $order->get_shipping_total(),
                'tax'      => $order->get_total_tax(),
                'total'    => $order->get_total(),
            ),
            'payment_method'=> $order->get_payment_method_title(),
            'notes'         => wc_get_order_notes( array( 'order_id' => $order->get_id() ) ),
        );
    }

    /**
     * Query WooCommerce orders by args.
     *
     * @param array $args
     *
     * @return array
     */
    public static function query_orders( array $args ): array {
        $orders = wc_get_orders( $args );
        $data   = array();
        foreach ( $orders as $order ) {
            $data[] = self::format_order_summary( $order );
        }
        return $data;
    }
}
