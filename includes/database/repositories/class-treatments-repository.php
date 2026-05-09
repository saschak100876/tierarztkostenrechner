<?php
defined( 'ABSPATH' ) || exit;

class TKR_Treatments_Repository {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tkr_treatments';
    }

    public function get_by_animal( string $animal_uid, string $subgroup_uid = '' ): array {
        global $wpdb;
        $cache_key = 'tkr_treatments_' . $animal_uid . '_' . $subgroup_uid;
        $cached = wp_cache_get( $cache_key, 'tkr' );
        if ( false !== $cached ) return $cached;

        if ( $subgroup_uid ) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE animal_uid = %s AND (subgroup_uid = %s OR subgroup_uid = 'no_subgroup') AND is_active = 1 ORDER BY sort_order ASC",
                    $animal_uid, $subgroup_uid
                ),
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE animal_uid = %s AND is_active = 1 ORDER BY sort_order ASC",
                    $animal_uid
                ),
                ARRAY_A
            );
        }
        $results = $results ?: [];
        wp_cache_set( $cache_key, $results, 'tkr', 300 );
        return $results;
    }

    public function get_by_uid( string $uid ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE treatment_uid = %s LIMIT 1", $uid ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function count_all(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
    }
}
