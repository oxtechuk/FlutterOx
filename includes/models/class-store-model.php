<?php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Store_Model {

    public static function get_store( int $vendor_id ): array {
        return Doken_Ox_Vendor_Model::format_vendor( $vendor_id );
    }

    public static function list_stores( array $args = array() ): array {
        return Doken_Ox_Vendor_Model::list_vendors( $args );
    }
}
