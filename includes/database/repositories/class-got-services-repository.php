<?php
defined( 'ABSPATH' ) || exit;

class TKR_Got_Services_Repository {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tkr_got_services';
    }

    public function get_by_uid( string $uid ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE service_uid = %s AND is_active = 1 LIMIT 1", $uid ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function get_by_uids( array $uids ): array {
        if ( empty( $uids ) ) return [];
        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $uids ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE service_uid IN ($placeholders) AND is_active = 1", ...$uids );
        return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
    }

    public function count(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->table}`" );
    }
}
