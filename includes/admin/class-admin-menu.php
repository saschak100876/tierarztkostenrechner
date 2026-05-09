<?php
defined('ABSPATH') || exit;

class TKR_Admin_Menu {
    public function register() {
        add_menu_page(__('Tierarztkostenrechner', 'tierarztkostenrechner'), __('Tierarztkostenrechner', 'tierarztkostenrechner'), 'manage_options', 'tierarztkostenrechner', array($this, 'dashboard'), 'dashicons-pets', 56);
        add_submenu_page('tierarztkostenrechner', __('Import', 'tierarztkostenrechner'), __('Import', 'tierarztkostenrechner'), 'manage_options', 'tierarztkostenrechner-import', array($this, 'import_page'));
    }

    public function dashboard() {
        $repo = new TKR_Repositories();
        $tables = array('animals','animal_subgroups','got_services','fee_rules','treatments','treatment_services','search_terms');
        echo '<div class="wrap"><h1>' . esc_html__('Tierarztkostenrechner', 'tierarztkostenrechner') . '</h1>';
        echo '<p>' . esc_html__('Status der importierten Daten und Plugin-Version.', 'tierarztkostenrechner') . '</p><table class="widefat striped"><tbody>';
        echo '<tr><th>' . esc_html__('Plugin-Version', 'tierarztkostenrechner') . '</th><td>' . esc_html(TKR_VERSION) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Datenbankschema', 'tierarztkostenrechner') . '</th><td>' . esc_html(get_option('tkr_db_version')) . '</td></tr>';
        foreach ($tables as $table) {
            echo '<tr><th>' . esc_html($table) . '</th><td>' . esc_html((string) $repo->count_table($table)) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function import_page() {
        if (!empty($_POST['tkr_import_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tkr_import_nonce'])), 'tkr_import') && current_user_can('manage_options')) {
            $sheet = sanitize_key($_POST['sheet'] ?? '');
            $dry_run = !empty($_POST['dry_run']);
            if (!empty($_FILES['csv']['tmp_name'])) {
                $importer = new TKR_Importer();
                $result = $importer->import_csv($sheet, sanitize_text_field($_FILES['csv']['tmp_name']), $dry_run);
                echo '<div class="notice notice-info"><pre>' . esc_html(print_r($result, true)) . '</pre></div>';
            }
        }
        $sheets = array('animals','animal_subgroups','got_services','fee_rules','treatments','treatment_services','search_terms');
        echo '<div class="wrap"><h1>' . esc_html__('Import', 'tierarztkostenrechner') . '</h1><form method="post" enctype="multipart/form-data">';
        wp_nonce_field('tkr_import', 'tkr_import_nonce');
        echo '<table class="form-table"><tr><th><label for="sheet">' . esc_html__('Sheet', 'tierarztkostenrechner') . '</label></th><td><select name="sheet" id="sheet">';
        foreach ($sheets as $sheet) { echo '<option value="' . esc_attr($sheet) . '">' . esc_html($sheet) . '</option>'; }
        echo '</select></td></tr><tr><th>' . esc_html__('CSV-Datei', 'tierarztkostenrechner') . '</th><td><input type="file" name="csv" accept=".csv" required></td></tr><tr><th>' . esc_html__('Dry Run', 'tierarztkostenrechner') . '</th><td><label><input type="checkbox" name="dry_run" value="1" checked> ' . esc_html__('Nur validieren, nicht schreiben', 'tierarztkostenrechner') . '</label></td></tr></table>';
        submit_button(__('Import starten', 'tierarztkostenrechner'));
        echo '</form></div>';
    }
}
