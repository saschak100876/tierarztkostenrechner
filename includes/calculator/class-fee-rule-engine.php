<?php
defined('ABSPATH') || exit;

class TKR_Fee_Rule_Engine {
    public function calculate_range(array $items, array $rule) {
        $base = 0.0;
        foreach ($items as $item) {
            if (($item['item_type'] ?? 'service') !== 'service' || empty($item['selected'])) {
                continue;
            }
            $base += (float) ($item['fee_1x'] ?? 0);
        }
        $fixed = (float) ($rule['fixed_fee'] ?? 0);
        return array(
            'factor_min' => (float) $rule['factor_min'],
            'factor_max' => (float) $rule['factor_max'],
            'total_min' => round($base * (float) $rule['factor_min'] + $fixed, 2),
            'total_max' => round($base * (float) $rule['factor_max'] + $fixed, 2),
            'fixed_fee' => round($fixed, 2),
        );
    }
}
