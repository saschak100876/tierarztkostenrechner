<?php
defined('ABSPATH') || exit;

class TKR_Activator {
    public static function activate() {
        require_once TKR_PLUGIN_DIR . 'includes/database/class-schema.php';
        TKR_Schema::create_tables();
        TKR_Schema::seed_defaults();
        update_option('tkr_db_version', TKR_DB_VERSION);
        update_option('tkr_version', TKR_VERSION);
    }
}
