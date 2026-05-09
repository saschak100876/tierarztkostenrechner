<?php
defined( 'ABSPATH' ) || exit;

class TKR_Settings_Page {

    const OPTION_KEY   = 'tkr_settings';
    const NONCE_ACTION = 'tkr_settings_save';
    const NONCE_FIELD  = 'tkr_settings_nonce';

    public static function get( string $key, $default = '' ) {
        $opts = get_option( self::OPTION_KEY, [] );
        return $opts[ $key ] ?? $default;
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( isset( $_POST['tkr_settings_submit'] ) ) {
            if ( ! check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD ) ) {
                wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', TKR_TEXT_DOMAIN ) );
            }
            $this->save();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Einstellungen gespeichert.', TKR_TEXT_DOMAIN ) . '</p></div>';
        }

        $primary    = self::get( 'primary_color',   '#20547E' );
        $accent     = self::get( 'accent_color',    '#F39200' );
        $disclaimer = self::get( 'show_disclaimer', '1' );
        $layout     = self::get( 'layout_mode',     'full' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Einstellungen – Tierarztkostenrechner', TKR_TEXT_DOMAIN ); ?></h1>
            <p><?php esc_html_e( 'Diese Einstellungen gelten global für alle Shortcode-Einbindungen, sofern keine abweichenden Shortcode-Parameter gesetzt sind.', TKR_TEXT_DOMAIN ); ?></p>
            <form method="post">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tkr_primary_color"><?php esc_html_e( 'Primärfarbe', TKR_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <input type="color" id="tkr_primary_color" name="tkr_primary_color" value="<?php echo esc_attr( $primary ); ?>">
                            <p class="description"><?php esc_html_e( 'Headings, Rahmen, Schaltflächen. CSS-Variable: --tkr-primary', TKR_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tkr_accent_color"><?php esc_html_e( 'Akzentfarbe', TKR_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <input type="color" id="tkr_accent_color" name="tkr_accent_color" value="<?php echo esc_attr( $accent ); ?>">
                            <p class="description"><?php esc_html_e( 'Aktive Auswahl, CTA-Elemente. CSS-Variable: --tkr-accent', TKR_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Layout-Modus', TKR_TEXT_DOMAIN ); ?></th>
                        <td>
                            <select name="tkr_layout_mode">
                                <option value="full"    <?php selected( $layout, 'full' ); ?>><?php esc_html_e( 'Vollansicht', TKR_TEXT_DOMAIN ); ?></option>
                                <option value="compact" <?php selected( $layout, 'compact' ); ?>><?php esc_html_e( 'Kompakt', TKR_TEXT_DOMAIN ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Wird als CSS-Klasse tkr-layout-full / tkr-layout-compact am Rechner gesetzt.', TKR_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Disclaimer', TKR_TEXT_DOMAIN ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="tkr_show_disclaimer" value="1" <?php checked( $disclaimer, '1' ); ?>>
                                <?php esc_html_e( 'Disclaimer im Ergebnis anzeigen', TKR_TEXT_DOMAIN ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Empfohlen. Kann per Shortcode-Attribut show_disclaimer="0" überschrieben werden.', TKR_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="tkr_settings_submit" class="button button-primary"
                        value="<?php esc_attr_e( 'Speichern', TKR_TEXT_DOMAIN ); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    private function save(): void {
        $primary = sanitize_hex_color( wp_unslash( $_POST['tkr_primary_color'] ?? '' ) ) ?: '#20547E';
        $accent  = sanitize_hex_color( wp_unslash( $_POST['tkr_accent_color']  ?? '' ) ) ?: '#F39200';
        $layout  = in_array( $_POST['tkr_layout_mode'] ?? 'full', [ 'full', 'compact' ], true )
            ? $_POST['tkr_layout_mode']
            : 'full';

        update_option( self::OPTION_KEY, [
            'primary_color'   => $primary,
            'accent_color'    => $accent,
            'layout_mode'     => $layout,
            'show_disclaimer' => ! empty( $_POST['tkr_show_disclaimer'] ) ? '1' : '0',
        ] );
    }
}
