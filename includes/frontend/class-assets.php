<?php
defined('ABSPATH') || exit;

class TKR_Assets {
    public function register() {
        wp_register_style('tkr-frontend', TKR_PLUGIN_URL . 'assets/css/tkr-frontend.css', array(), TKR_VERSION);
        wp_register_style('tkr-theme-default', TKR_PLUGIN_URL . 'assets/css/tkr-theme-default.css', array('tkr-frontend'), TKR_VERSION);
        wp_register_script('tkr-frontend', TKR_PLUGIN_URL . 'assets/js/tkr-frontend.js', array(), TKR_VERSION, true);
        wp_localize_script('tkr-frontend', 'tkrFrontend', array(
            'restUrl' => esc_url_raw(rest_url('tkr/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => array(
                'loading' => __('Wird geladen …', 'tierarztkostenrechner'),
                'choose' => __('Bitte auswählen', 'tierarztkostenrechner'),
                'calculate' => __('Kosten orientierend berechnen', 'tierarztkostenrechner'),
                'unknownTreatment' => __('Steht noch nicht fest', 'tierarztkostenrechner'),
            ),
        ));
    }
}
