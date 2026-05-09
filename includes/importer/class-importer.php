<?php
defined('ABSPATH') || exit;

class TKR_Importer {
    private $validator;

    public function __construct() {
        $this->validator = new TKR_Validator();
    }

    public function import_csv($sheet, $file, $dry_run = true) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('tkr_forbidden', __('Keine Berechtigung für den Import.', 'tierarztkostenrechner'));
        }
        $rows = $this->read_csv($file);
        $errors = $this->validator->validate_sheet($sheet, $rows);
        if ($errors || $dry_run) {
            $this->store_report($file, $dry_run, $errors ? 'error' : 'ok', array('rows' => count($rows), 'errors' => count($errors)), implode("\n", $errors));
            return array('dry_run' => (bool) $dry_run, 'rows' => count($rows), 'errors' => $errors);
        }
        global $wpdb;
        $table = TKR_Schema::table($sheet);
        $uid = $this->validator->uid_column($sheet);
        $wpdb->query('START TRANSACTION');
        $count = 0;
        foreach ($rows as $row) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE $uid = %s", $row[$uid]));
            if ($existing) {
                $wpdb->update($table, $row, array('id' => (int) $existing));
            } else {
                $wpdb->insert($table, $row);
            }
            if ($wpdb->last_error) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('tkr_import_failed', $wpdb->last_error);
            }
            $count++;
        }
        $wpdb->query('COMMIT');
        $this->store_report($file, false, 'ok', array('rows' => $count), '');
        return array('dry_run' => false, 'rows' => $count, 'errors' => array());
    }

    private function read_csv($file) {
        $handle = fopen($file, 'r');
        if (!$handle) { return array(); }
        $headers = fgetcsv($handle, 0, ',');
        if (!$headers || count($headers) === 1) {
            rewind($handle);
            $headers = fgetcsv($handle, 0, ';');
            $delimiter = ';';
        } else {
            $delimiter = ',';
        }
        $headers = array_map('trim', $headers ?: array());
        $rows = array();
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row = array();
            foreach ($headers as $index => $header) { $row[$header] = isset($data[$index]) ? trim($data[$index]) : ''; }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function store_report($source, $dry_run, $status, array $counts, $message) {
        global $wpdb;
        $wpdb->insert(TKR_Schema::table('import_reports'), array(
            'imported_at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'source_name' => basename($source),
            'dry_run' => $dry_run ? 1 : 0,
            'status' => $status,
            'message' => $message,
            'counts' => wp_json_encode($counts),
        ));
    }
}
