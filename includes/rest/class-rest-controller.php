<?php
/**
 * REST API controller.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

class TKR_REST_Controller {
	private TKR_Lookup_Repository $repository;
	private TKR_Calculator $calculator;
	private TKR_Search_Service $search;

	public function __construct( TKR_Lookup_Repository $repository, TKR_Calculator $calculator, TKR_Search_Service $search ) {
		$this->repository = $repository;
		$this->calculator = $calculator;
		$this->search     = $search;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$namespace = 'tkr/v1';
		register_rest_route( $namespace, '/animals', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'animals' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $namespace, '/subgroups', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'subgroups' ), 'permission_callback' => '__return_true', 'args' => array( 'animal_uid' => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ) ) ) );
		register_rest_route( $namespace, '/fee-rules', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'fee_rules' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $namespace, '/treatments', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'treatments' ), 'permission_callback' => '__return_true', 'args' => array( 'animal_uid' => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ), 'subgroup_uid' => array( 'required' => false, 'sanitize_callback' => 'sanitize_key' ) ) ) );
		register_rest_route( $namespace, '/search', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'search' ), 'permission_callback' => '__return_true', 'args' => array( 'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ), 'animal_uid' => array( 'required' => false, 'sanitize_callback' => 'sanitize_key' ), 'subgroup_uid' => array( 'required' => false, 'sanitize_callback' => 'sanitize_key' ) ) ) );
		register_rest_route( $namespace, '/treatment-services', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'treatment_services' ), 'permission_callback' => '__return_true', 'args' => array( 'treatment_uid' => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ) ) ) );
		register_rest_route( $namespace, '/calculate', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'calculate' ), 'permission_callback' => '__return_true' ) );
	}

	public function animals(): WP_REST_Response { return rest_ensure_response( $this->repository->get_animals() ); }

	public function subgroups( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->repository->get_subgroups( (string) $request['animal_uid'] ) );
	}

	public function fee_rules(): WP_REST_Response { return rest_ensure_response( $this->repository->get_fee_rules() ); }

	public function treatments( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->repository->get_treatments( (string) $request['animal_uid'], (string) ( $request['subgroup_uid'] ?: 'no_subgroup' ) ) );
	}

	public function search( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->search->search( (string) $request['q'], (string) ( $request['animal_uid'] ?: '' ), (string) ( $request['subgroup_uid'] ?: '' ) ) );
	}

	public function treatment_services( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->repository->get_treatment_services( (string) $request['treatment_uid'] ) );
	}

	public function calculate( WP_REST_Request $request ) {
		$params        = $request->get_json_params();
		$params        = is_array( $params ) ? $params : array();
		$treatment_uid = sanitize_key( (string) ( $params['treatment_uid'] ?? '' ) );
		$rule_uid      = sanitize_key( (string) ( $params['rule_uid'] ?? '' ) );

		if ( '' === $treatment_uid || '' === $rule_uid ) {
			return new WP_Error( 'tkr_missing_input', __( 'treatment_uid und rule_uid sind erforderlich.', 'tierarztkostenrechner' ), array( 'status' => 400 ) );
		}

		$rule = $this->repository->get_fee_rule( $rule_uid );
		if ( ! $rule ) {
			return new WP_Error( 'tkr_rule_not_found', __( 'Die Gebührenregel wurde nicht gefunden.', 'tierarztkostenrechner' ), array( 'status' => 404 ) );
		}

		$items = $this->repository->get_treatment_services( $treatment_uid );
		if ( empty( $items ) ) {
			return new WP_Error( 'tkr_treatment_empty', __( 'Für diese Behandlung sind keine typischen Einzelpositionen hinterlegt.', 'tierarztkostenrechner' ), array( 'status' => 404 ) );
		}

		$selected = array();
		if ( isset( $params['selected_map_uids'] ) && is_array( $params['selected_map_uids'] ) ) {
			$selected = array_map( 'sanitize_key', $params['selected_map_uids'] );
		}

		$result          = $this->calculator->calculate( $items, $rule, array( 'selected_map_uids' => $selected, 'sex' => sanitize_key( (string) ( $params['sex'] ?? 'unknown' ) ) ) );
		$result['input'] = array(
			'animal_uid'     => sanitize_key( (string) ( $params['animal_uid'] ?? '' ) ),
			'treatment_uid'  => $treatment_uid,
			'rule_uid'       => $rule_uid,
			'subgroup_uid'   => sanitize_key( (string) ( $params['subgroup_uid'] ?? 'no_subgroup' ) ),
		);

		return rest_ensure_response( $result );
	}
}
