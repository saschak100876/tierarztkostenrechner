<?php
defined( 'ABSPATH' ) || exit;

class TKR_REST_Controller {

    const NAMESPACE = 'tkr/v1';

    public function register_routes(): void {
        add_action( 'rest_api_init', [ $this, 'do_register_routes' ] );
    }

    public function do_register_routes(): void {
        register_rest_route( self::NAMESPACE, '/animals', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_animals' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NAMESPACE, '/subgroups', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_subgroups' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'animal_uid' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/fee-rules', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_fee_rules' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NAMESPACE, '/treatments', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_treatments' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'animal_uid'   => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'subgroup_uid' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/search', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'search' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'q'            => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'animal_uid'   => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
                'subgroup_uid' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/treatment-services', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_treatment_services' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'treatment_uid' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/calculate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'calculate' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'animal_uid'    => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'treatment_uid' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'rule_uid'      => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'sex'           => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => 'any' ],
            ],
        ] );
    }

    public function get_animals( WP_REST_Request $request ): WP_REST_Response {
        $repo    = new TKR_Animals_Repository();
        $animals = $repo->get_all_active();
        return new WP_REST_Response( $animals );
    }

    public function get_subgroups( WP_REST_Request $request ): WP_REST_Response {
        $animal_uid = $request->get_param( 'animal_uid' );
        $repo       = new TKR_Subgroups_Repository();
        return new WP_REST_Response( $repo->get_by_animal( $animal_uid ) );
    }

    public function get_fee_rules( WP_REST_Request $request ): WP_REST_Response {
        $repo  = new TKR_Fee_Rules_Repository();
        $rules = $repo->get_all_active();
        return new WP_REST_Response( $rules );
    }

    public function get_treatments( WP_REST_Request $request ): WP_REST_Response {
        $animal_uid   = $request->get_param( 'animal_uid' );
        $subgroup_uid = $request->get_param( 'subgroup_uid' );
        $repo         = new TKR_Treatments_Repository();
        $treatments   = $repo->get_by_animal( $animal_uid, $subgroup_uid );

        // Prepend the "not yet known" option
        array_unshift( $treatments, [
            'treatment_uid'      => 'treatment_search',
            'treatment_label_de' => __( 'Steht noch nicht fest', TKR_TEXT_DOMAIN ),
            'requires_search'    => 1,
            'sort_order'         => 0,
        ] );

        return new WP_REST_Response( $treatments );
    }

    public function search( WP_REST_Request $request ): WP_REST_Response {
        $q            = $request->get_param( 'q' );
        $animal_uid   = $request->get_param( 'animal_uid' );
        $subgroup_uid = $request->get_param( 'subgroup_uid' );

        if ( mb_strlen( $q ) < 2 ) {
            return new WP_REST_Response( [] );
        }

        $search_service = new TKR_Search_Service();
        $results        = $search_service->search( $q, $animal_uid, $subgroup_uid );
        return new WP_REST_Response( $results );
    }

    public function get_treatment_services( WP_REST_Request $request ): WP_REST_Response {
        $treatment_uid = $request->get_param( 'treatment_uid' );
        $repo          = new TKR_Treatment_Services_Repository();
        return new WP_REST_Response( $repo->get_by_treatment( $treatment_uid ) );
    }

    public function calculate( WP_REST_Request $request ) {
        $animal_uid    = $request->get_param( 'animal_uid' );
        $treatment_uid = $request->get_param( 'treatment_uid' );
        $rule_uid      = $request->get_param( 'rule_uid' );
        $sex           = $request->get_param( 'sex' );

        // Whitelist sex values
        if ( ! in_array( $sex, [ 'male', 'female', 'any' ], true ) ) {
            $sex = 'any';
        }

        $calculator = new TKR_Calculator();
        $result     = $calculator->calculate( $animal_uid, $treatment_uid, $rule_uid, $sex );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $result );
    }
}
