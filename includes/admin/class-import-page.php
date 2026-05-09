<?php
defined( 'ABSPATH' ) || exit;

class TKR_Import_Page {

    const NONCE_ACTION = 'tkr_import_action';
    const NONCE_FIELD  = 'tkr_import_nonce';

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $report  = null;
        $success = null;

        if ( isset( $_POST['tkr_import_submit'] ) ) {
            if ( ! check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD ) ) {
                wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', TKR_TEXT_DOMAIN ) );
            }
            [ $report, $success ] = $this->handle_upload();
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Masterdatei-Import', TKR_TEXT_DOMAIN ); ?></h1>
            <p><?php esc_html_e( 'CSV-Dateien je Sheet hochladen (Spaltentrennzeichen: Komma, UTF-8).', TKR_TEXT_DOMAIN ); ?></p>

            <?php if ( $report ) $this->render_report( $report, $success ); ?>

            <form method="post" enctype="multipart/form-data" style="margin-top:20px;">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

                <table class="form-table">
                    <?php
                    $sheets = [
                        'animals'            => __( 'animals.csv', TKR_TEXT_DOMAIN ),
                        'animal_subgroups'   => __( 'animal_subgroups.csv', TKR_TEXT_DOMAIN ),
                        'got_services'       => __( 'got_services.csv', TKR_TEXT_DOMAIN ),
                        'fee_rules'          => __( 'fee_rules.csv', TKR_TEXT_DOMAIN ),
                        'treatments'         => __( 'treatments.csv', TKR_TEXT_DOMAIN ),
                        'treatment_services' => __( 'treatment_services.csv', TKR_TEXT_DOMAIN ),
                        'search_terms'       => __( 'search_terms.csv', TKR_TEXT_DOMAIN ),
                    ];
                    foreach ( $sheets as $key => $label ) :
                    ?>
                    <tr>
                        <th><label for="tkr_file_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                        <td><input type="file" id="tkr_file_<?php echo esc_attr( $key ); ?>" name="tkr_files[<?php echo esc_attr( $key ); ?>]" accept=".csv"></td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <p>
                    <label>
                        <input type="checkbox" name="tkr_dry_run" value="1" checked>
                        <?php esc_html_e( 'Dry Run (nur prüfen, nicht schreiben)', TKR_TEXT_DOMAIN ); ?>
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

    private function handle_upload(): array {
        $dry_run = ! empty( $_POST['tkr_dry_run'] );
        $files   = $_FILES['tkr_files'] ?? [];
        $sheets  = [];

        if ( ! empty( $files['tmp_name'] ) ) {
            foreach ( $files['tmp_name'] as $sheet => $tmp ) {
                if ( empty( $tmp ) || ! is_uploaded_file( $tmp ) ) continue;
                $parsed = TKR_Importer::parse_csv_files( [ $sheet => $tmp ] );
                if ( ! empty( $parsed[ $sheet ] ) ) {
                    $sheets[ $sheet ] = $parsed[ $sheet ];
                }
            }
        }

        if ( empty( $sheets ) ) {
            return [ [ 'errors' => [ __( 'Keine gültigen CSV-Dateien hochgeladen.', TKR_TEXT_DOMAIN ) ] ], false ];
        }

        $importer = new TKR_Importer();
        $success  = $importer->import( $sheets, $dry_run );
        return [ $importer->get_report(), $success ];
    }

    private function render_report( array $report, bool $success ): void {
        $class = $success ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>';
        echo $success
            ? esc_html__( 'Import erfolgreich.', TKR_TEXT_DOMAIN )
            : esc_html__( 'Import fehlgeschlagen.', TKR_TEXT_DOMAIN );
        echo '</p></div>';

        if ( ! empty( $report['errors'] ) ) {
            echo '<div class="notice notice-error"><ul>';
            foreach ( $report['errors'] as $e ) {
                echo '<li>' . esc_html( $e ) . '</li>';
            }
            echo '</ul></div>';
        }

        if ( ! empty( $report['warnings'] ) ) {
            echo '<div class="notice notice-warning"><ul>';
            foreach ( $report['warnings'] as $w ) {
                echo '<li>' . esc_html( $w ) . '</li>';
            }
            echo '</ul></div>';
        }

        if ( ! empty( $report['counts'] ) ) {
            echo '<h3>' . esc_html__( 'Importierte Datensätze', TKR_TEXT_DOMAIN ) . '</h3><ul>';
            foreach ( $report['counts'] as $sheet => $count ) {
                echo '<li><strong>' . esc_html( $sheet ) . '</strong>: ' . (int) $count . '</li>';
            }
            echo '</ul>';
        }
    }
}
