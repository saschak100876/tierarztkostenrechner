<?php
defined('ABSPATH') || exit;

class TKR_Search_Service {
    private $repositories;

    public function __construct(TKR_Repositories $repositories) {
        $this->repositories = $repositories;
    }

    public function search($query, $animal_uid = '', $subgroup_uid = '') {
        $normalized = TKR_Search_Normalizer::normalize($query);
        if (strlen($normalized) < 2) {
            return array();
        }
        $rows = $this->repositories->search_terms($normalized, $animal_uid, $subgroup_uid);
        foreach ($rows as &$row) {
            $exact = $row['term_normalized'] === $normalized ? 30 : 0;
            $treatment_bonus = $row['treatment_uid'] !== 'no_treatment' ? 20 : 0;
            $row['score'] = (int) $row['priority'] + $exact + $treatment_bonus;
            $row['result_type'] = $row['treatment_uid'] !== 'no_treatment' ? 'treatment' : 'got_service';
            $row['notice_de'] = __('Suchtreffer sind keine Diagnose. Sie zeigen nur mögliche Kostenbestandteile.', 'tierarztkostenrechner');
        }
        unset($row);
        usort($rows, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        return array_slice($rows, 0, 10);
    }
}
