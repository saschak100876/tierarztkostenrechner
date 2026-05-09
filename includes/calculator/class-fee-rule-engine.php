<?php
defined( 'ABSPATH' ) || exit;

class TKR_Fee_Rule_Engine {

    public function calculate( array $rule, array $service_rows, string $sex = 'any' ): array {
        $factor_min = (float) $rule['factor_min'];
        $factor_max = (float) $rule['factor_max'];
        $fixed_fee  = (float) $rule['fixed_fee'];
        $is_unknown = ( $rule['rule_uid'] === 'rule_unknown' );

        // Apply sex condition filter
        $filtered = array_filter( $service_rows, function ( $row ) use ( $sex ) {
            if ( ( $row['item_type'] ?? 'service' ) === 'note' ) return true;
            $cond = $row['condition_key'] ?? 'no_condition';
            if ( $cond === 'no_condition' ) return true;
            if ( str_starts_with( $cond, 'sex=' ) ) {
                $required_sex = substr( $cond, 4 );
                return ( $sex === $required_sex || $sex === 'any' );
            }
            return true;
        } );

        $items  = [];
        $sum_1x = 0.0;

        foreach ( $filtered as $row ) {
            $is_note  = ( ( $row['item_type'] ?? 'service' ) === 'note' || empty( $row['fee_1x'] ) );
            $fee_1x   = $is_note ? null : (float) $row['fee_1x'];

            if ( ! $is_note && (bool) $row['is_default'] ) {
                $sum_1x += (float) $row['fee_1x'];
            }

            $items[] = [
                'service_uid' => $row['service_uid'],
                'got_number'  => $is_note ? null : (int) $row['got_number'],
                'label_de'    => $is_note ? ( $row['user_note_de'] ?? '' ) : ( $row['service_label_de'] ?? '' ),
                'fee_1x'      => $fee_1x !== null ? round( $fee_1x, 2 ) : null,
                'item_type'   => $is_note ? 'note' : 'service',
                'is_default'  => (bool) $row['is_default'],
                'is_required' => (bool) $row['is_required'],
                'user_note'   => sanitize_text_field( $row['user_note_de'] ?? '' ),
            ];
        }

        if ( $is_unknown ) {
            return $this->build_comparison_result( $items, $sum_1x );
        }

        return [
            'mode'      => 'single',
            'factor_min'=> $factor_min,
            'factor_max'=> $factor_max,
            'total_min' => round( $sum_1x * $factor_min, 2 ),
            'total_max' => round( $sum_1x * $factor_max, 2 ),
            'fixed_fee' => $fixed_fee,
            'grand_min' => round( $sum_1x * $factor_min + $fixed_fee, 2 ),
            'grand_max' => round( $sum_1x * $factor_max + $fixed_fee, 2 ),
            'items'     => $items,
        ];
    }

    private function build_comparison_result( array $items, float $sum_1x ): array {
        return [
            'mode'    => 'comparison',
            'items'   => $items,
            'normal'  => [
                'factor_min' => 1.0,
                'factor_max' => 3.0,
                'grand_min'  => round( $sum_1x * 1.0, 2 ),
                'grand_max'  => round( $sum_1x * 3.0, 2 ),
                'fixed_fee'  => 0.0,
            ],
            'emergency' => [
                'factor_min' => 2.0,
                'factor_max' => 4.0,
                'grand_min'  => round( $sum_1x * 2.0 + 50.0, 2 ),
                'grand_max'  => round( $sum_1x * 4.0 + 50.0, 2 ),
                'fixed_fee'  => 50.0,
            ],
        ];
    }
}
