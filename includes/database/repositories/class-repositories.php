<?php
defined('ABSPATH') || exit;

class TKR_Repositories {
    public function get_animals() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . TKR_Schema::table('animals') . " WHERE is_active = 1 ORDER BY sort_order ASC, animal_label_de ASC", ARRAY_A);
    }

    public function get_subgroups($animal_uid) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM " . TKR_Schema::table('animal_subgroups') . " WHERE is_active = 1 AND animal_uid = %s ORDER BY sort_order ASC", $animal_uid), ARRAY_A);
    }

    public function get_fee_rules() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . TKR_Schema::table('fee_rules') . " WHERE is_active = 1 ORDER BY sort_order ASC", ARRAY_A);
    }

    public function get_fee_rule($rule_uid) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . TKR_Schema::table('fee_rules') . " WHERE is_active = 1 AND rule_uid = %s", $rule_uid), ARRAY_A);
    }

    public function get_treatments($animal_uid, $subgroup_uid = 'no_subgroup') {
        global $wpdb;
        $subgroup_uid = $subgroup_uid ?: 'no_subgroup';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . TKR_Schema::table('treatments') . " WHERE is_active = 1 AND animal_uid = %s AND (subgroup_uid = %s OR subgroup_uid = 'no_subgroup') ORDER BY requires_search DESC, sort_order ASC, treatment_label_de ASC",
            $animal_uid,
            $subgroup_uid
        ), ARRAY_A);
    }

    public function get_treatment_services($treatment_uid) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT ts.*, gs.got_number, gs.got_part, gs.service_label_de, gs.fee_1x FROM " . TKR_Schema::table('treatment_services') . " ts LEFT JOIN " . TKR_Schema::table('got_services') . " gs ON ts.service_uid = gs.service_uid WHERE ts.treatment_uid = %s ORDER BY ts.sort_order ASC",
            $treatment_uid
        ), ARRAY_A);
    }

    public function get_services_by_uids($service_uids) {
        global $wpdb;
        $service_uids = array_values(array_filter(array_map('sanitize_text_field', (array) $service_uids)));
        if (!$service_uids) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($service_uids), '%s'));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM " . TKR_Schema::table('got_services') . " WHERE is_active = 1 AND service_uid IN ($placeholders)", $service_uids), ARRAY_A);
    }

    public function search_terms($query, $animal_uid = '', $subgroup_uid = '') {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($query) . '%';
        $animal_uid = $animal_uid ?: 'no_animal';
        $subgroup_uid = $subgroup_uid ?: 'no_subgroup';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . TKR_Schema::table('search_terms') . " WHERE is_active = 1 AND term_normalized LIKE %s AND (animal_uid = %s OR animal_uid = 'no_animal') AND (subgroup_uid = %s OR subgroup_uid = 'no_subgroup') ORDER BY priority DESC, term_normalized ASC LIMIT 20",
            $like,
            $animal_uid,
            $subgroup_uid
        ), ARRAY_A);
    }

    public function count_table($table) {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . TKR_Schema::table($table));
    }
}
