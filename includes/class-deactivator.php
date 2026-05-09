<?php
defined('ABSPATH') || exit;

class TKR_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook('tkr_daily_maintenance');
    }
}
