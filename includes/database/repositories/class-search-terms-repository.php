<?php
defined( 'ABSPATH' ) || exit;

class TKR_Search_Terms_Repository {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tkr_search_terms';
    }

    /**
     * Returns search_terms rows matching the normalized query.
     * Filters by animal_uid if provided (also includes no_animal rows).
     */
    public function search( string $normalized_query, string $animal_uid = '', string $subgroup_uid = '', int $limit = 20 ): array {
        global $wpdb;

        $like = '%' . $wpdb->esc_like( $normalized_query ) . '%';
        $params = [ $like, $like ];

        $animal_filter = '';
        if ( $animal_uid ) {
            $animal_filter = $wpdb->prepare( " AND (animal_uid = %s OR animal_uid = 'no_animal')", $animal_uid );
        }

        $subgroup_filter = '';
        if ( $subgroup_uid ) {
            $subgroup_filter = $wpdb->prepare( " AND (subgroup_uid = %s OR subgroup_uid = 'no_subgroup')", $subgroup_uid );
        }

        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE is_active = 1
               AND (term_normalized LIKE %s OR term_de LIKE %s)
               {$animal_filter}
               {$subgroup_filter}
             ORDER BY
               CASE WHEN term_normalized = %s THEN 0 ELSE 1 END,
               priority DESC
             LIMIT %d",
            $like,
            $like,
            $normalized_query,
            $limit
        );

        return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
    }

    public function count_all(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
    }
}
