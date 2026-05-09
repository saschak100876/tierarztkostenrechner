<?php
defined( 'ABSPATH' ) || exit;

class TKR_Schema {

    const DB_VERSION = '1.0.0';
    const DB_VERSION_OPTION = 'tkr_db_version';

    public static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $p = $wpdb->prefix;

        $sql = [];

        $sql[] = "CREATE TABLE {$p}tkr_animals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            animal_uid VARCHAR(64) NOT NULL,
            animal_label_de VARCHAR(120) NOT NULL,
            animal_slug VARCHAR(120) NOT NULL,
            animal_group VARCHAR(50) NOT NULL,
            has_subgroups TINYINT(1) NOT NULL DEFAULT 0,
            has_sex_options TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            notes TEXT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY animal_uid (animal_uid),
            KEY sort_order (sort_order)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}tkr_animal_subgroups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subgroup_uid VARCHAR(64) NOT NULL,
            animal_uid VARCHAR(64) NOT NULL,
            subgroup_label_de VARCHAR(120) NOT NULL,
            subgroup_slug VARCHAR(120) NOT NULL,
            got_scope_terms TEXT NOT NULL,
            has_direct_got_hits TINYINT(1) NOT NULL DEFAULT 1,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            notes TEXT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY subgroup_uid (subgroup_uid),
            KEY animal_uid (animal_uid)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}tkr_got_services (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_uid VARCHAR(64) NOT NULL,
            got_number INT NOT NULL,
            got_part CHAR(1) NOT NULL,
            service_original_de TEXT NOT NULL,
            service_label_de TEXT NOT NULL,
            fee_1x DECIMAL(10,2) NOT NULL,
            animal_scope_raw TEXT NOT NULL,
            animal_uids TEXT NOT NULL,
            subgroup_uids TEXT NOT NULL,
            sex_scope VARCHAR(20) NOT NULL DEFAULT 'any',
            is_general TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY service_uid (service_uid),
            KEY got_number (got_number)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}tkr_fee_rules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_uid VARCHAR(64) NOT NULL,
            rule_label_de VARCHAR(160) NOT NULL,
            rule_slug VARCHAR(120) NOT NULL,
            factor_min DECIMAL(4,2) NOT NULL,
            factor_max DECIMAL(4,2) NOT NULL,
            fixed_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            is_emergency TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            legal_basis VARCHAR(120) NOT NULL,
            notes TEXT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY rule_uid (rule_uid),
            KEY sort_order (sort_order)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}tkr_treatments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            treatment_uid VARCHAR(96) NOT NULL,
            animal_uid VARCHAR(64) NOT NULL,
            subgroup_uid VARCHAR(64) NOT NULL,
            treatment_label_de VARCHAR(180) NOT NULL,
            treatment_slug VARCHAR(160) NOT NULL,
            requires_search TINYINT(1) NOT NULL DEFAULT 0,
            requires_sex TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            notes TEXT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY treatment_uid (treatment_uid),
            KEY animal_uid (animal_uid),
            KEY subgroup_uid (subgroup_uid)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}tkr_treatment_services (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            map_uid VARCHAR(128) NOT NULL,
            treatment_uid VARCHAR(96) NOT NULL,
            service_uid VARCHAR(64) NOT NULL,
            item_type VARCHAR(20) NOT NULL DEFAULT 'service',
            role VARCHAR(50) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 1,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            condition_key VARCHAR(120) NOT NULL DEFAULT 'no_condition',
            sort_order INT NOT NULL DEFAULT 0,
            user_note_de TEXT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY map_uid (map_uid),
            KEY treatment_uid (treatment_uid),
            KEY service_uid (service_uid)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}tkr_search_terms (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            search_uid VARCHAR(128) NOT NULL,
            term_de VARCHAR(255) NOT NULL,
            term_normalized VARCHAR(255) NOT NULL,
            term_type VARCHAR(40) NOT NULL,
            animal_uid VARCHAR(64) NOT NULL,
            subgroup_uid VARCHAR(64) NOT NULL,
            treatment_uid VARCHAR(96) NOT NULL,
            service_uid VARCHAR(64) NOT NULL,
            priority INT NOT NULL DEFAULT 50,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY search_uid (search_uid),
            KEY term_normalized (term_normalized(191)),
            KEY animal_uid (animal_uid),
            KEY priority (priority)
        ) $charset;";

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    public static function drop_tables(): void {
        global $wpdb;
        $p = $wpdb->prefix;
        $tables = [
            'tkr_search_terms',
            'tkr_treatment_services',
            'tkr_treatments',
            'tkr_fee_rules',
            'tkr_got_services',
            'tkr_animal_subgroups',
            'tkr_animals',
        ];
        foreach ( $tables as $t ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS {$p}{$t}" );
        }
        delete_option( self::DB_VERSION_OPTION );
    }
}
