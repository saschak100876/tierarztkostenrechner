<?php
defined('ABSPATH') || exit;

class TKR_Migrations {
    public static function maybe_migrate() {
        if (get_option('tkr_db_version') !== TKR_DB_VERSION) {
            TKR_Schema::create_tables();
            update_option('tkr_db_version', TKR_DB_VERSION);
        }
    }
}
