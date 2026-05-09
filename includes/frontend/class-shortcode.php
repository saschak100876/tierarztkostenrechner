<?php
defined('ABSPATH') || exit;

class TKR_Shortcode {
    public function register() {
        add_shortcode('tierarztkostenrechner', array($this, 'render'));
    }

    public function render($atts = array()) {
        $atts = shortcode_atts(array('default_animal' => '', 'default_treatment' => '', 'layout' => 'full'), $atts, 'tierarztkostenrechner');
        wp_enqueue_style('tkr-theme-default');
        wp_enqueue_script('tkr-frontend');
        ob_start();
        ?>
        <div class="tkr-calculator" data-default-animal="<?php echo esc_attr($atts['default_animal']); ?>" data-default-treatment="<?php echo esc_attr($atts['default_treatment']); ?>" data-layout="<?php echo esc_attr($atts['layout']); ?>">
            <noscript><?php esc_html_e('Bitte aktivieren Sie JavaScript, um den Tierarztkostenrechner zu nutzen.', 'tierarztkostenrechner'); ?></noscript>
            <div class="tkr-card">
                <h2><?php esc_html_e('Tierarztkostenrechner', 'tierarztkostenrechner'); ?></h2>
                <p class="tkr-muted"><?php esc_html_e('Unverbindliche Orientierung nach GOT – keine Diagnose, keine Rechtsberatung und kein verbindlicher Kostenvoranschlag.', 'tierarztkostenrechner'); ?></p>
                <div class="tkr-app" aria-live="polite"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
