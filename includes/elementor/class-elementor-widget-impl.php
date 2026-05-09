<?php
defined('ABSPATH') || exit;

class TKR_Elementor_Widget_Impl extends \Elementor\Widget_Base {
    public function get_name() { return 'tierarztkostenrechner'; }
    public function get_title() { return __('Tierarztkostenrechner', 'tierarztkostenrechner'); }
    public function get_icon() { return 'eicon-form-horizontal'; }
    public function get_categories() { return array('general'); }
    protected function register_controls() {
        $this->start_controls_section('content', array('label' => __('Einstellungen', 'tierarztkostenrechner')));
        $this->add_control('layout_mode', array('label' => __('Layout', 'tierarztkostenrechner'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'full', 'options' => array('full' => __('Voll', 'tierarztkostenrechner'), 'compact' => __('Kompakt', 'tierarztkostenrechner'))));
        $this->add_control('primary_color', array('label' => __('Primärfarbe', 'tierarztkostenrechner'), 'type' => \Elementor\Controls_Manager::COLOR));
        $this->add_control('accent_color', array('label' => __('Akzentfarbe', 'tierarztkostenrechner'), 'type' => \Elementor\Controls_Manager::COLOR));
        $this->end_controls_section();
    }
    protected function render() {
        $settings = $this->get_settings_for_display();
        $style = '';
        if (!empty($settings['primary_color'])) { $style .= '--tkr-primary:' . esc_attr($settings['primary_color']) . ';'; }
        if (!empty($settings['accent_color'])) { $style .= '--tkr-accent:' . esc_attr($settings['accent_color']) . ';'; }
        echo '<div style="' . esc_attr($style) . '">' . do_shortcode('[tierarztkostenrechner layout="' . esc_attr($settings['layout_mode']) . '"]') . '</div>';
    }
}
