<?php
defined('ABSPATH') || exit;

class TKR_Calculator {
    private $repositories;
    private $engine;

    public function __construct(TKR_Repositories $repositories) {
        $this->repositories = $repositories;
        $this->engine = new TKR_Fee_Rule_Engine();
    }

    public function calculate($animal_uid, $treatment_uid, $rule_uid, $selected_map_uids = array()) {
        $items = $this->repositories->get_treatment_services($treatment_uid);
        $selected_map_uids = array_filter((array) $selected_map_uids);
        foreach ($items as &$item) {
            $default_selected = !empty($item['is_default']) || !empty($item['is_required']);
            $item['selected'] = $selected_map_uids ? in_array($item['map_uid'], $selected_map_uids, true) || !empty($item['is_required']) : $default_selected;
        }
        unset($item);

        if ($rule_uid === 'rule_unknown') {
            $normal = $this->repositories->get_fee_rule('rule_normal');
            $emergency = $this->repositories->get_fee_rule('rule_emergency');
            return array(
                'input' => compact('animal_uid', 'treatment_uid', 'rule_uid'),
                'scenarios' => array(
                    'normal' => $normal ? $this->engine->calculate_range($items, $normal) : null,
                    'emergency' => $emergency ? $this->engine->calculate_range($items, $emergency) : null,
                ),
                'items' => $this->format_items($items),
                'notices' => $this->notices(true),
            );
        }

        $rule = $this->repositories->get_fee_rule($rule_uid);
        if (!$rule) {
            return new WP_Error('tkr_rule_not_found', __('Die gewählte Behandlungssituation wurde nicht gefunden.', 'tierarztkostenrechner'), array('status' => 404));
        }
        return array(
            'input' => compact('animal_uid', 'treatment_uid', 'rule_uid'),
            'range' => $this->engine->calculate_range($items, $rule),
            'items' => $this->format_items($items),
            'notices' => $this->notices(!empty($rule['is_emergency'])),
        );
    }

    private function format_items(array $items) {
        return array_map(static function ($item) {
            return array(
                'map_uid' => $item['map_uid'],
                'service_uid' => $item['service_uid'],
                'item_type' => $item['item_type'],
                'got_number' => isset($item['got_number']) ? (int) $item['got_number'] : null,
                'label_de' => $item['service_label_de'] ?: $item['user_note_de'],
                'fee_1x' => isset($item['fee_1x']) ? (float) $item['fee_1x'] : 0,
                'selected' => (bool) $item['selected'],
                'is_required' => (bool) $item['is_required'],
                'user_note_de' => $item['user_note_de'],
            );
        }, $items);
    }

    private function notices($emergency) {
        $notices = array(
            __('Der Rechner zeigt eine unverbindliche Orientierung auf Basis der GOT.', 'tierarztkostenrechner'),
            __('Medikamente, Verbrauchsmaterial, Laborleistungen oder besondere Umstände können zusätzlich anfallen.', 'tierarztkostenrechner'),
            __('Der Rechner ersetzt keine tierärztliche Beratung und ist keine Diagnose oder verbindliche Kostenzusage.', 'tierarztkostenrechner'),
        );
        if ($emergency) {
            $notices[] = __('Im tierärztlichen Notdienst wird die Notdienstgebühr von 50 EUR nur einmal pro Angelegenheit berücksichtigt.', 'tierarztkostenrechner');
        }
        return $notices;
    }
}
