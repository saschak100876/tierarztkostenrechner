<?php
defined('ABSPATH') || exit;

class TKR_Elementor_Widget {
    public static function register_widget($widgets_manager) {
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }
        require_once __DIR__ . '/class-elementor-widget-impl.php';
        $widgets_manager->register(new TKR_Elementor_Widget_Impl());
    }
}
