<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class TKR_Elementor_Widget extends Widget_Base {

    public function get_name(): string    { return 'tierarztkostenrechner'; }
    public function get_title(): string   { return __( 'Tierarztkostenrechner', TKR_TEXT_DOMAIN ); }
    public function get_icon(): string    { return 'eicon-form-horizontal'; }
    public function get_categories(): array { return [ 'general' ]; }

    protected function register_controls(): void {
        $this->start_controls_section( 'section_settings', [ 'label' => __( 'Einstellungen', TKR_TEXT_DOMAIN ) ] );

        $animal_options = [ '' => __( 'Keine Vorauswahl', TKR_TEXT_DOMAIN ) ];
        foreach ( ( new TKR_Animals_Repository() )->get_all_active() as $a ) {
            $animal_options[ esc_attr( $a['animal_uid'] ) ] = esc_html( $a['animal_label_de'] );
        }

        $this->add_control( 'default_animal', [
            'label'   => __( 'Standard-Tierart', TKR_TEXT_DOMAIN ),
            'type'    => Controls_Manager::SELECT,
            'options' => $animal_options,
            'default' => '',
        ] );

        $this->add_control( 'layout_mode', [
            'label'   => __( 'Layout', TKR_TEXT_DOMAIN ),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'full'    => __( 'Vollansicht', TKR_TEXT_DOMAIN ),
                'compact' => __( 'Kompakt', TKR_TEXT_DOMAIN ),
            ],
            'default' => 'full',
        ] );

        $this->add_control( 'show_disclaimer', [
            'label'        => __( 'Disclaimer anzeigen', TKR_TEXT_DOMAIN ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Ja', TKR_TEXT_DOMAIN ),
            'label_off'    => __( 'Nein', TKR_TEXT_DOMAIN ),
            'return_value' => '1',
            'default'      => '1',
        ] );

        $this->end_controls_section();

        $this->start_controls_section( 'section_style', [
            'label' => __( 'Farben', TKR_TEXT_DOMAIN ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'primary_color', [
            'label'     => __( 'Primärfarbe', TKR_TEXT_DOMAIN ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#20547E',
            'selectors' => [ '{{WRAPPER}} [data-tkr-instance]' => '--tkr-primary: {{VALUE}};' ],
        ] );

        $this->add_control( 'accent_color', [
            'label'     => __( 'Akzentfarbe', TKR_TEXT_DOMAIN ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#F39200',
            'selectors' => [ '{{WRAPPER}} [data-tkr-instance]' => '--tkr-accent: {{VALUE}};' ],
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();

        tkr_plugin()->do_enqueue_frontend_assets();

        static $count = 0;
        $count++;
        $id = 'tkr-elementor-' . $count;

        $layout = in_array( $s['layout_mode'] ?? 'full', [ 'full', 'compact' ], true ) ? $s['layout_mode'] : 'full';

        echo '<div id="' . esc_attr( $id ) . '"'
            . ' data-tkr-instance="1"'
            . ' data-default-animal="' . esc_attr( $s['default_animal'] ?? '' ) . '"'
            . ' data-layout="' . esc_attr( $layout ) . '"'
            . ' data-show-disclaimer="' . esc_attr( $s['show_disclaimer'] ?? '1' ) . '"'
            . '></div>';
    }
}
