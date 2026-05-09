<?php
defined('ABSPATH') || exit;

class TKR_Schema {
    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'tkr_' . $name;
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sql = array();
        $sql[] = "CREATE TABLE " . self::table('animals') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            animal_uid VARCHAR(64) NOT NULL,
            animal_label_de VARCHAR(120) NOT NULL,
            animal_slug VARCHAR(120) NOT NULL,
            animal_group VARCHAR(50) NOT NULL,
            has_subgroups TINYINT(1) NOT NULL DEFAULT 0,
            has_sex_options TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            PRIMARY KEY (id), UNIQUE KEY animal_uid (animal_uid), KEY active_sort (is_active, sort_order)
        ) $charset;";
        $sql[] = "CREATE TABLE " . self::table('animal_subgroups') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subgroup_uid VARCHAR(64) NOT NULL,
            animal_uid VARCHAR(64) NOT NULL,
            subgroup_label_de VARCHAR(120) NOT NULL,
            subgroup_slug VARCHAR(120) NOT NULL,
            got_scope_terms TEXT NOT NULL,
            has_direct_got_hits TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            PRIMARY KEY (id), UNIQUE KEY subgroup_uid (subgroup_uid), KEY animal_active (animal_uid, is_active, sort_order)
        ) $charset;";
        $sql[] = "CREATE TABLE " . self::table('got_services') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_uid VARCHAR(64) NOT NULL,
            got_number INT NOT NULL DEFAULT 0,
            got_part CHAR(1) NOT NULL,
            service_original_de TEXT NOT NULL,
            service_label_de TEXT NOT NULL,
            fee_1x DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            animal_scope_raw TEXT NOT NULL,
            animal_uids TEXT NOT NULL,
            subgroup_uids TEXT NOT NULL,
            sex_scope VARCHAR(20) NOT NULL DEFAULT 'any',
            is_general TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            PRIMARY KEY (id), UNIQUE KEY service_uid (service_uid), KEY got_number (got_number), KEY active (is_active)
        ) $charset;";
        $sql[] = "CREATE TABLE " . self::table('fee_rules') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_uid VARCHAR(64) NOT NULL,
            rule_label_de VARCHAR(160) NOT NULL,
            rule_slug VARCHAR(120) NOT NULL,
            factor_min DECIMAL(4,2) NOT NULL DEFAULT 1.00,
            factor_max DECIMAL(4,2) NOT NULL DEFAULT 3.00,
            fixed_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            is_emergency TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            legal_basis VARCHAR(120) NOT NULL,
            notes TEXT NULL,
            PRIMARY KEY (id), UNIQUE KEY rule_uid (rule_uid), KEY active_sort (is_active, sort_order)
        ) $charset;";
        $sql[] = "CREATE TABLE " . self::table('treatments') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            treatment_uid VARCHAR(96) NOT NULL,
            animal_uid VARCHAR(64) NOT NULL,
            subgroup_uid VARCHAR(64) NOT NULL DEFAULT 'no_subgroup',
            treatment_label_de VARCHAR(180) NOT NULL,
            treatment_slug VARCHAR(160) NOT NULL,
            requires_search TINYINT(1) NOT NULL DEFAULT 0,
            requires_sex TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            PRIMARY KEY (id), UNIQUE KEY treatment_uid (treatment_uid), KEY animal_subgroup (animal_uid, subgroup_uid, is_active, sort_order)
        ) $charset;";
        $sql[] = "CREATE TABLE " . self::table('treatment_services') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            map_uid VARCHAR(128) NOT NULL,
            treatment_uid VARCHAR(96) NOT NULL,
            service_uid VARCHAR(64) NOT NULL DEFAULT 'no_service',
            item_type VARCHAR(20) NOT NULL,
            role VARCHAR(50) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 1,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            condition_key VARCHAR(120) NOT NULL DEFAULT 'no_condition',
            sort_order INT NOT NULL DEFAULT 0,
            user_note_de TEXT NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY map_uid (map_uid), KEY treatment (treatment_uid, sort_order), KEY service_uid (service_uid)
        ) $charset;";
        $sql[] = "CREATE TABLE " . self::table('search_terms') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            search_uid VARCHAR(128) NOT NULL,
            term_de VARCHAR(255) NOT NULL,
            term_normalized VARCHAR(255) NOT NULL,
            term_type VARCHAR(40) NOT NULL,
            animal_uid VARCHAR(64) NOT NULL DEFAULT 'no_animal',
            subgroup_uid VARCHAR(64) NOT NULL DEFAULT 'no_subgroup',
            treatment_uid VARCHAR(96) NOT NULL DEFAULT 'no_treatment',
            service_uid VARCHAR(64) NOT NULL DEFAULT 'no_service',
            priority INT NOT NULL DEFAULT 50,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            PRIMARY KEY (id), UNIQUE KEY search_uid (search_uid), KEY term_normalized (term_normalized(191)), KEY animal_active (animal_uid, is_active, priority)
        ) $charset;";
        $sql[] = "CREATE TABLE " . self::table('import_reports') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            imported_at DATETIME NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_name VARCHAR(255) NOT NULL,
            dry_run TINYINT(1) NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL,
            message TEXT NULL,
            counts LONGTEXT NULL,
            PRIMARY KEY (id), KEY imported_at (imported_at)
        ) $charset;";
        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }

    public static function seed_defaults() {
        global $wpdb;
        if (!(int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table('animals'))) {
            $animals = array(
                array('animal_cat','Katze','katze','small_animal',0,1,1,10,'no_note'),
                array('animal_dog','Hund','hund','small_animal',0,1,1,20,'no_note'),
                array('animal_rabbit','Kaninchen','kaninchen','small_mammal',0,0,1,30,'no_note'),
                array('animal_guinea_pig','Meerschweinchen','meerschweinchen','small_mammal',0,0,1,40,'no_note'),
                array('animal_hamster','Hamster','hamster','small_mammal',0,0,1,50,'no_note'),
                array('animal_bird','Vogel','vogel','bird',1,0,1,60,'no_note'),
                array('animal_horse','Pferd','pferd','horse',1,1,1,70,'no_note'),
                array('animal_rat','Ratte','ratte','small_mammal',0,0,1,80,'no_note'),
                array('animal_mouse','Maus','maus','small_mammal',0,0,1,90,'no_note'),
                array('animal_ferret','Frettchen','frettchen','small_animal',0,1,1,100,'no_note'),
                array('animal_reptile','Reptil','reptil','exotic',0,0,1,110,'no_note'),
                array('animal_amphibian','Amphibie','amphibie','exotic',0,0,1,120,'no_note'),
            );
            foreach ($animals as $a) {
                $wpdb->insert(self::table('animals'), array('animal_uid'=>$a[0], 'animal_label_de'=>$a[1], 'animal_slug'=>$a[2], 'animal_group'=>$a[3], 'has_subgroups'=>$a[4], 'has_sex_options'=>$a[5], 'is_active'=>$a[6], 'sort_order'=>$a[7], 'notes'=>$a[8]));
            }
        }
        if (!(int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table('animal_subgroups'))) {
            $subgroups = array(
                array('bird_indoor','animal_bird','Stubenvögel','stubenvoegel','Stubenvögel',1,1,10,'no_note'),
                array('bird_aviary','animal_bird','Volierenvögel','volierenvoegel','Volierenvögel',1,1,20,'no_note'),
                array('bird_large_psittacine','animal_bird','Großpsittaciden','grosspsittaciden','Großpsittaciden',1,1,30,'no_note'),
                array('horse_stallion','animal_horse','Hengst','hengst','Hengst',1,1,10,'no_note'),
                array('horse_gelding','animal_horse','Wallach','wallach','Wallach',0,1,20,'Fällt auf allgemeine Pferde-/Equidenlogik zurück.'),
                array('horse_mare','animal_horse','Stute','stute','Stute',1,1,30,'no_note'),
                array('horse_foal','animal_horse','Fohlen','fohlen','Fohlen',1,1,40,'no_note'),
            );
            foreach ($subgroups as $sg) {
                $wpdb->insert(self::table('animal_subgroups'), array('subgroup_uid'=>$sg[0], 'animal_uid'=>$sg[1], 'subgroup_label_de'=>$sg[2], 'subgroup_slug'=>$sg[3], 'got_scope_terms'=>$sg[4], 'has_direct_got_hits'=>$sg[5], 'is_active'=>$sg[6], 'sort_order'=>$sg[7], 'notes'=>$sg[8]));
            }
        }
        if (!(int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table('fee_rules'))) {
            $rules = array(
                array('rule_normal','Normale Sprechstunde','normale-sprechstunde',1.00,3.00,0.00,0,1,10,'GOT','Normaler GOT-Rahmen'),
                array('rule_evening_night','Abend / Nacht, 18:00 bis 8:00 Uhr','abend-nacht',1.00,3.00,0.00,0,1,20,'GOT','Besondere Zeit, nicht automatisch Notdienst.'),
                array('rule_weekend_holiday','Wochenende / Feiertag','wochenende-feiertag',1.00,3.00,0.00,0,1,30,'GOT','Besondere Zeit, nicht automatisch Notdienst.'),
                array('rule_emergency','Tierärztlicher Notdienst','notdienst',2.00,4.00,50.00,1,1,40,'§ 4 GOT','Notdienstgebühr nur einmal pro Angelegenheit'),
                array('rule_unknown','Ich weiß es noch nicht / erst informieren','unbekannt',1.00,3.00,0.00,0,1,50,'GOT','Zeigt Vergleichsszenarien.'),
            );
            foreach ($rules as $r) {
                $wpdb->insert(self::table('fee_rules'), array('rule_uid'=>$r[0], 'rule_label_de'=>$r[1], 'rule_slug'=>$r[2], 'factor_min'=>$r[3], 'factor_max'=>$r[4], 'fixed_fee'=>$r[5], 'is_emergency'=>$r[6], 'is_active'=>$r[7], 'sort_order'=>$r[8], 'legal_basis'=>$r[9], 'notes'=>$r[10]));
            }
        }
    }

}
