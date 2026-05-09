<?php
defined( 'ABSPATH' ) || exit;

class TKR_Subgroups_Repository {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tkr_animal_subgroups';
    }

    public function get_by_animal( string $animal_uid ): array {
        global $wpdb;
        $cache_key = 'tkr_subgroups_' . $animal_uid;
        $cached = wp_cache_get( $cache_key, 'tkr' );
        if ( false !== $cached ) return $cached;

        $results = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE animal_uid = %s AND is_active = 1 ORDER BY sort_order ASC", $animal_uid ),
            ARRAY_A
        );
        $results = $results ?: [];
        wp_cache_set( $cache_key, $results, 'tkr', 300 );
        return $results;
    }

    public function get_by_uid( string $uid ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE subgroup_uid = %s LIMIT 1", $uid ),
            ARRAY_A
        );
        return $row ?: null;
    }
}
