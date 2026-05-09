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
            check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );
            $this->save();
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__( 'Einstellungen gespeichert.', TKR_TEXT_DOMAIN )
                . '</p></div>';
        }

        $opts = get_option( self::OPTION_KEY, [] );
        $primary    = $opts['primary_color']    ?? '#20547E';
        $accent     = $opts['accent_color']     ?? '#F39200';
        $disclaimer = $opts['show_disclaimer']  ?? '1';
        $layout     = $opts['layout_mode']      ?? 'full';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Einstellungen', TKR_TEXT_DOMAIN ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="tkr_primary_color"><?php esc_html_e( 'Primärfarbe', TKR_TEXT_DOMAIN ); ?></label></th>
                        <td><input type="color" id="tkr_primary_color" name="tkr_primary_color" value="<?php echo esc_attr( $primary ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="tkr_accent_color"><?php esc_html_e( 'Akzentfarbe', TKR_TEXT_DOMAIN ); ?></label></th>
                        <td><input type="color" id="tkr_accent_color" name="tkr_accent_color" value="<?php echo esc_attr( $accent ); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Layout', TKR_TEXT_DOMAIN ); ?></th>
                        <td>
                            <select name="tkr_layout_mode">
                                <option value="full"    <?php selected( $layout, 'full' ); ?>><?php esc_html_e( 'Vollansicht', TKR_TEXT_DOMAIN ); ?></option>
                                <option value="compact" <?php selected( $layout, 'compact' ); ?>><?php esc_html_e( 'Kompakt', TKR_TEXT_DOMAIN ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Disclaimer anzeigen', TKR_TEXT_DOMAIN ); ?></th>
                        <td><label><input type="checkbox" name="tkr_show_disclaimer" value="1" <?php checked( $disclaimer, '1' ); ?>> <?php esc_html_e( 'Ja', TKR_TEXT_DOMAIN ); ?></label></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="tkr_settings_submit" class="button button-primary" value="<?php esc_attr_e( 'Speichern', TKR_TEXT_DOMAIN ); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    private function save(): void {
        update_option( self::OPTION_KEY, [
            'primary_color'   => sanitize_hex_color( $_POST['tkr_primary_color'] ?? '#20547E' ),
            'accent_color'    => sanitize_hex_color( $_POST['tkr_accent_color']  ?? '#F39200' ),
            'layout_mode'     => in_array( $_POST['tkr_layout_mode'] ?? 'full', [ 'full', 'compact' ], true ) ? $_POST['tkr_layout_mode'] : 'full',
            'show_disclaimer' => ! empty( $_POST['tkr_show_disclaimer'] ) ? '1' : '0',
        ] );
    }
}
