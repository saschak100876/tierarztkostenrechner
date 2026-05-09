<?php
defined( 'ABSPATH' ) || exit;

class TKR_Treatment_Services_Repository {

    private string $map_table;
    private string $svc_table;

    public function __construct() {
        global $wpdb;
        $this->map_table = $wpdb->prefix . 'tkr_treatment_services';
        $this->svc_table = $wpdb->prefix . 'tkr_got_services';
    }

    /**
     * Returns treatment_services rows joined with got_services data.
     */
    public function get_by_treatment( string $treatment_uid ): array {
        global $wpdb;
        $cache_key = 'tkr_ts_' . $treatment_uid;
        $cached = wp_cache_get( $cache_key, 'tkr' );
        if ( false !== $cached ) return $cached;

        $sql = $wpdb->prepare(
            "SELECT ts.*, gs.got_number, gs.service_label_de, gs.fee_1x, gs.sex_scope
             FROM {$this->map_table} ts
             LEFT JOIN {$this->svc_table} gs ON ts.service_uid = gs.service_uid
             WHERE ts.treatment_uid = %s
             ORDER BY ts.sort_order ASC",
            $treatment_uid
        );
        $results = $wpdb->get_results( $sql, ARRAY_A ) ?: [];
        wp_cache_set( $cache_key, $results, 'tkr', 300 );
        return $results;
    }
}
