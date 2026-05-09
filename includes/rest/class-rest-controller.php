<?php
defined('ABSPATH') || exit;

class TKR_REST_Controller {
    private $repositories;
    private $calculator;
    private $search;

    public function __construct(TKR_Repositories $repositories, TKR_Calculator $calculator, TKR_Search_Service $search) {
        $this->repositories = $repositories;
        $this->calculator = $calculator;
        $this->search = $search;
    }

    public function register_routes() {
        register_rest_route('tkr/v1', '/animals', array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'animals'), 'permission_callback' => '__return_true'));
        register_rest_route('tkr/v1', '/subgroups', array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'subgroups'), 'permission_callback' => '__return_true', 'args' => array('animal_uid' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'))));
        register_rest_route('tkr/v1', '/fee-rules', array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'fee_rules'), 'permission_callback' => '__return_true'));
        register_rest_route('tkr/v1', '/treatments', array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'treatments'), 'permission_callback' => '__return_true', 'args' => array('animal_uid' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'), 'subgroup_uid' => array('sanitize_callback' => 'sanitize_text_field'))));
        register_rest_route('tkr/v1', '/search', array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'search_endpoint'), 'permission_callback' => '__return_true'));
        register_rest_route('tkr/v1', '/treatment-services', array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'treatment_services'), 'permission_callback' => '__return_true', 'args' => array('treatment_uid' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'))));
        register_rest_route('tkr/v1', '/calculate', array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'calculate'), 'permission_callback' => '__return_true'));
    }

    public function animals() { return rest_ensure_response($this->repositories->get_animals()); }
    public function subgroups(WP_REST_Request $request) { return rest_ensure_response($this->repositories->get_subgroups($request['animal_uid'])); }
    public function fee_rules() { return rest_ensure_response($this->repositories->get_fee_rules()); }
    public function treatments(WP_REST_Request $request) { return rest_ensure_response($this->repositories->get_treatments($request['animal_uid'], $request['subgroup_uid'] ?: 'no_subgroup')); }
    public function treatment_services(WP_REST_Request $request) { return rest_ensure_response($this->repositories->get_treatment_services($request['treatment_uid'])); }
    public function search_endpoint(WP_REST_Request $request) {
        return rest_ensure_response($this->search->search(sanitize_text_field($request['q']), sanitize_text_field($request['animal_uid']), sanitize_text_field($request['subgroup_uid'])));
    }
    public function calculate(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $result = $this->calculator->calculate(
            sanitize_text_field($params['animal_uid'] ?? ''),
            sanitize_text_field($params['treatment_uid'] ?? ''),
            sanitize_text_field($params['rule_uid'] ?? 'rule_normal'),
            array_map('sanitize_text_field', (array) ($params['selected_map_uids'] ?? array()))
        );
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }
}
