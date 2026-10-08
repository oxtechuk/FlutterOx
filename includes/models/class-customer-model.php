<?php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Customer_Model {

    public static function format_customer( WP_User $user ): array {
        return array(
            'id'      => $user->ID,
            'email'   => $user->user_email,
            'name'    => $user->display_name,
            'phone'   => get_user_meta( $user->ID, 'phone', true ),
            'avatar'  => get_avatar_url( $user->ID ),
            'orders'  => wc_get_customer_order_count( $user->ID ),
            'lifetime_value' => wc_get_customer_total_spent( $user->ID ),
        );
    }

    public static function list_customers( array $args = array() ): array {
        $defaults = array(
            'number' => 20,
            'search' => '',
        );
        $params = wp_parse_args( $args, $defaults );

        $query = array(
            'role'   => 'customer',
            'number' => (int) $params['number'],
            'search' => $params['search'] ? '*' . $params['search'] . '*' : '',
            'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
        );

        $users = get_users( $query );
        $data  = array();
        foreach ( $users as $user ) {
            $data[] = self::format_customer( $user );
        }

        return $data;
    }
}
