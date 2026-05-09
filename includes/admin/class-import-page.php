<?php
defined( 'ABSPATH' ) || exit;

class TKR_Import_Page {

    const NONCE_ACTION    = 'tkr_import_action';
    const NONCE_FIELD     = 'tkr_import_nonce';
    const DOWNLOAD_NONCE  = 'tkr_download_template';

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Handle template download
        if ( isset( $_GET['tkr_download_template'] ) ) {
            check_admin_referer( self::DOWNLOAD_NONCE );
            $this->stream_template_zip();
            exit;
        }

        $report  = null;
        $success = null;

        if ( isset( $_POST['tkr_import_submit'] ) ) {
            if ( ! check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD ) ) {
                wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', TKR_TEXT_DOMAIN ) );
            }
            [ $report, $success ] = $this->handle_upload();
        }

        $download_url = wp_nonce_url(
            admin_url( 'admin.php?page=tkr-import&tkr_download_template=1' ),
            self::DOWNLOAD_NONCE
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Masterdatei-Import', TKR_TEXT_DOMAIN ); ?></h1>

            <p>
                <?php esc_html_e( 'Laden Sie je Sheet eine CSV-Datei (Trennzeichen: Komma, Encoding: UTF-8) hoch.', TKR_TEXT_DOMAIN ); ?>
                <a href="<?php echo esc_url( $download_url ); ?>" class="button button-secondary" style="margin-left:12px;">
                    ⬇ <?php esc_html_e( 'CSV-Vorlagen herunterladen', TKR_TEXT_DOMAIN ); ?>
                </a>
            </p>

            <?php if ( null !== $report ) $this->render_report( $report, $success ); ?>

            <form method="post" enctype="multipart/form-data" style="margin-top:24px;">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

                <table class="widefat" style="max-width:640px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Sheet / Datei', TKR_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'CSV-Datei hochladen', TKR_TEXT_DOMAIN ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sheets = [
                        'animals'            => __( 'animals.csv — Tierarten', TKR_TEXT_DOMAIN ),
                        'animal_subgroups'   => __( 'animal_subgroups.csv — Untergruppen', TKR_TEXT_DOMAIN ),
                        'got_services'       => __( 'got_services.csv — GOT-Positionen', TKR_TEXT_DOMAIN ),
                        'fee_rules'          => __( 'fee_rules.csv — Gebührenregeln', TKR_TEXT_DOMAIN ),
                        'treatments'         => __( 'treatments.csv — Behandlungen', TKR_TEXT_DOMAIN ),
                        'treatment_services' => __( 'treatment_services.csv — Verknüpfungen', TKR_TEXT_DOMAIN ),
                        'search_terms'       => __( 'search_terms.csv — Suchbegriffe', TKR_TEXT_DOMAIN ),
                    ];
                    foreach ( $sheets as $key => $label ) :
                    ?>
                        <tr>
                            <td><label for="tkr_file_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></td>
                            <td><input type="file" id="tkr_file_<?php echo esc_attr( $key ); ?>" name="tkr_files[<?php echo esc_attr( $key ); ?>]" accept=".csv"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top:16px;">
                    <label>
                        <input type="checkbox" name="tkr_dry_run" value="1" checked>
                        <strong><?php esc_html_e( 'Dry Run', TKR_TEXT_DOMAIN ); ?></strong>
                        — <?php esc_html_e( 'Nur prüfen, nichts in die Datenbank schreiben.', TKR_TEXT_DOMAIN ); ?>
                    </label>
                </p>
                <p class="submit">
                    <input type="submit" name="tkr_import_submit" class="button button-primary"
                        value="<?php esc_attr_e( 'Import starten', TKR_TEXT_DOMAIN ); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    // ---- Upload handler ----

    private function handle_upload(): array {
        $dry_run = ! empty( $_POST['tkr_dry_run'] );
        $files   = $_FILES['tkr_files'] ?? [];
        $sheets  = [];

        if ( ! empty( $files['tmp_name'] ) && is_array( $files['tmp_name'] ) ) {
            foreach ( $files['tmp_name'] as $sheet => $tmp ) {
                if ( empty( $tmp ) || ! is_uploaded_file( $tmp ) ) continue;
                // Reject files that are clearly not CSV by MIME
                $finfo = finfo_open( FILEINFO_MIME_TYPE );
                $mime  = finfo_file( $finfo, $tmp );
                finfo_close( $finfo );
                $allowed_mimes = [ 'text/csv', 'text/plain', 'application/csv', 'application/octet-stream' ];
                if ( ! in_array( $mime, $allowed_mimes, true ) ) {
                    continue;
                }
                $parsed = TKR_Importer::parse_csv_files( [ $sheet => $tmp ] );
                if ( ! empty( $parsed[ $sheet ] ) ) {
                    $sheets[ $sheet ] = $parsed[ $sheet ];
                }
            }
        }

        if ( empty( $sheets ) ) {
            return [
                [ 'errors' => [ __( 'Keine gültigen CSV-Dateien hochgeladen.', TKR_TEXT_DOMAIN ) ], 'warnings' => [], 'counts' => [], 'dry_run' => $dry_run ],
                false,
            ];
        }

        $importer = new TKR_Importer();
        $success  = $importer->import( $sheets, $dry_run );
        return [ $importer->get_report(), $success ];
    }

    // ---- Report rendering ----

    private function render_report( array $report, ?bool $success ): void {
        $dry = (bool) ( $report['dry_run'] ?? false );

        if ( $success === null || $success === false ) {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . ( $dry
                    ? esc_html__( 'Validierung fehlgeschlagen. Bitte die Fehler unten beheben.', TKR_TEXT_DOMAIN )
                    : esc_html__( 'Import fehlgeschlagen. Keine Daten wurden gespeichert.', TKR_TEXT_DOMAIN ) )
                . '</p></div>';
        } else {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . ( $dry
                    ? esc_html__( '✓ Validierung erfolgreich. Dry-Run-Modus: keine Daten wurden geschrieben. Deaktivieren Sie die Option und starten Sie den Import erneut.', TKR_TEXT_DOMAIN )
                    : esc_html__( '✓ Import erfolgreich. Alle Daten wurden gespeichert.', TKR_TEXT_DOMAIN ) )
                . '</p></div>';
        }

        if ( ! empty( $report['errors'] ) ) {
            echo '<div class="notice notice-error"><p><strong>'
                . sprintf( esc_html__( '%d Fehler gefunden:', TKR_TEXT_DOMAIN ), count( $report['errors'] ) )
                . '</strong></p><ul style="margin-left:1.5em;list-style:disc;">';
            foreach ( $report['errors'] as $e ) {
                echo '<li>' . esc_html( $e ) . '</li>';
            }
            echo '</ul></div>';
        }

        if ( ! empty( $report['warnings'] ) ) {
            echo '<div class="notice notice-warning"><p><strong>'
                . sprintf( esc_html__( '%d Warnungen:', TKR_TEXT_DOMAIN ), count( $report['warnings'] ) )
                . '</strong></p><ul style="margin-left:1.5em;list-style:disc;">';
            foreach ( $report['warnings'] as $w ) {
                echo '<li>' . esc_html( $w ) . '</li>';
            }
            echo '</ul></div>';
        }

        if ( ! empty( $report['counts'] ) ) {
            $table_labels = [
                'animals'            => __( 'Tierarten', TKR_TEXT_DOMAIN ),
                'animal_subgroups'   => __( 'Untergruppen', TKR_TEXT_DOMAIN ),
                'got_services'       => __( 'GOT-Positionen', TKR_TEXT_DOMAIN ),
                'fee_rules'          => __( 'Gebührenregeln', TKR_TEXT_DOMAIN ),
                'treatments'         => __( 'Behandlungen', TKR_TEXT_DOMAIN ),
                'treatment_services' => __( 'Behandlung → GOT-Verknüpfungen', TKR_TEXT_DOMAIN ),
                'search_terms'       => __( 'Suchbegriffe', TKR_TEXT_DOMAIN ),
            ];
            echo '<h3>' . esc_html__( 'Zusammenfassung der geprüften/importierten Datensätze', TKR_TEXT_DOMAIN ) . '</h3>';
            echo '<table class="widefat" style="max-width:440px;"><tbody>';
            foreach ( $report['counts'] as $sheet => $count ) {
                echo '<tr><th>' . esc_html( $table_labels[ $sheet ] ?? $sheet ) . '</th><td>' . number_format_i18n( (int) $count ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
    }

    // ---- Template ZIP download ----

    private function stream_template_zip(): void {
        $template_dir = TKR_PLUGIN_DIR . 'data/csv-templates/';
        $files        = glob( $template_dir . '*.csv' );

        if ( ! class_exists( 'ZipArchive' ) || empty( $files ) ) {
            wp_die( esc_html__( 'ZIP-Erstellung nicht möglich. PHP-Erweiterung ZipArchive fehlt oder keine Vorlagen vorhanden.', TKR_TEXT_DOMAIN ) );
        }

        $tmp = wp_tempnam( 'tkr-templates' );
        $zip = new ZipArchive();
        if ( $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            wp_die( esc_html__( 'ZIP konnte nicht erstellt werden.', TKR_TEXT_DOMAIN ) );
        }

        foreach ( $files as $file ) {
            $zip->addFile( $file, basename( $file ) );
        }
        $zip->close();

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="tkr-csv-vorlagen.zip"' );
        header( 'Content-Length: ' . filesize( $tmp ) );
        header( 'Pragma: no-cache' );
        readfile( $tmp );
        unlink( $tmp );
    }
}
