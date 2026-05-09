<?php
defined('ABSPATH') || exit;

class TKR_Embed_Controller {
    public static function render_placeholder() {
        return do_shortcode('[tierarztkostenrechner layout="compact"]');
    }
}
