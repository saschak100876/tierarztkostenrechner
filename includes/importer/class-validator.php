<?php
defined('ABSPATH') || exit;

class TKR_Validator {
    private $allowed_term_types = array('synonym', 'symptom', 'breed', 'species', 'lay_term', 'spelling_variant', 'got_term', 'treatment');

    public function validate_sheet($sheet, array $rows) {
        $errors = array();
        $required = $this->required_columns($sheet);
        $uids = array();
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            foreach ($required as $column) {
                if (!array_key_exists($column, $row) || $row[$column] === '') {
                    $errors[] = sprintf('%s Zeile %d: Pflichtfeld %s fehlt.', $sheet, $line, $column);
                }
            }
            foreach ($row as $column => $value) {
                if ($value === '') {
                    $errors[] = sprintf('%s Zeile %d: Leere Zelle in %s.', $sheet, $line, $column);
                }
            }
            $uid_column = $this->uid_column($sheet);
            if ($uid_column && isset($row[$uid_column])) {
                if (isset($uids[$row[$uid_column]])) {
                    $errors[] = sprintf('%s Zeile %d: UID %s ist doppelt.', $sheet, $line, $row[$uid_column]);
                }
                $uids[$row[$uid_column]] = true;
            }
            $this->validate_types($sheet, $row, $line, $errors);
        }
        return $errors;
    }

    public function required_columns($sheet) {
        $columns = array(
            'animals' => array('animal_uid','animal_label_de','animal_slug','animal_group','has_subgroups','has_sex_options','is_active','sort_order','notes'),
            'animal_subgroups' => array('subgroup_uid','animal_uid','subgroup_label_de','subgroup_slug','got_scope_terms','has_direct_got_hits','is_active','sort_order','notes'),
            'got_services' => array('service_uid','got_number','got_part','service_original_de','service_label_de','fee_1x','animal_scope_raw','animal_uids','subgroup_uids','sex_scope','is_general','is_active','notes'),
            'fee_rules' => array('rule_uid','rule_label_de','rule_slug','factor_min','factor_max','fixed_fee','is_emergency','is_active','sort_order','legal_basis','notes'),
            'treatments' => array('treatment_uid','animal_uid','subgroup_uid','treatment_label_de','treatment_slug','requires_search','requires_sex','is_active','sort_order','notes'),
            'treatment_services' => array('map_uid','treatment_uid','service_uid','item_type','role','is_default','is_required','condition_key','sort_order','user_note_de'),
            'search_terms' => array('search_uid','term_de','term_normalized','term_type','animal_uid','subgroup_uid','treatment_uid','service_uid','priority','is_active','notes'),
        );
        return $columns[$sheet] ?? array();
    }

    public function uid_column($sheet) {
        $map = array('animals'=>'animal_uid','animal_subgroups'=>'subgroup_uid','got_services'=>'service_uid','fee_rules'=>'rule_uid','treatments'=>'treatment_uid','treatment_services'=>'map_uid','search_terms'=>'search_uid');
        return $map[$sheet] ?? '';
    }

    private function validate_types($sheet, array $row, $line, array &$errors) {
        foreach ($row as $column => $value) {
            if (in_array($column, array('has_subgroups','has_sex_options','is_active','has_direct_got_hits','is_general','is_emergency','requires_search','requires_sex','is_default','is_required'), true) && !in_array((string) $value, array('0','1'), true)) {
                $errors[] = sprintf('%s Zeile %d: %s muss 0 oder 1 sein.', $sheet, $line, $column);
            }
        }
        if ($sheet === 'got_services') {
            if (!is_numeric($row['fee_1x'] ?? null) || (float) $row['fee_1x'] < 0) { $errors[] = sprintf('%s Zeile %d: fee_1x ist ungültig.', $sheet, $line); }
            if (!in_array($row['got_part'] ?? '', array('A','B','C'), true)) { $errors[] = sprintf('%s Zeile %d: got_part ist ungültig.', $sheet, $line); }
        }
        if ($sheet === 'treatment_services' && !in_array($row['item_type'] ?? '', array('service','note'), true)) { $errors[] = sprintf('%s Zeile %d: item_type ist ungültig.', $sheet, $line); }
        if ($sheet === 'search_terms' && !in_array($row['term_type'] ?? '', $this->allowed_term_types, true)) { $errors[] = sprintf('%s Zeile %d: term_type ist ungültig.', $sheet, $line); }
    }
}
