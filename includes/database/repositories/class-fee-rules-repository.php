<?php
defined( 'ABSPATH' ) || exit;

class TKR_Fee_Rules_Repository {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tkr_fee_rules';
    }

    public function get_all_active(): array {
        global $wpdb;
        $cache_key = 'tkr_fee_rules_active';
        $cached    = wp_cache_get( $cache_key, 'tkr' );
        if ( false !== $cached ) return $cached;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results( "SELECT * FROM `{$this->table}` WHERE is_active = 1 ORDER BY sort_order ASC", ARRAY_A );
        $results = $results ?: [];
        wp_cache_set( $cache_key, $results, 'tkr', 300 );
        return $results;
    }

    public function get_by_uid( string $uid ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE rule_uid = %s LIMIT 1", $uid ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function count(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->table}`" );
    }
}
