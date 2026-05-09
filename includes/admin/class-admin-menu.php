<?php
defined( 'ABSPATH' ) || exit;

class TKR_Admin_Menu {

    public function init(): void {
        add_action( 'admin_menu',            [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menus(): void {
        add_menu_page(
            __( 'Tierarztkostenrechner', TKR_TEXT_DOMAIN ),
            __( 'TKR', TKR_TEXT_DOMAIN ),
            'manage_options',
            'tkr-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-calculator',
            56
        );

        add_submenu_page( 'tkr-dashboard', __( 'Dashboard', TKR_TEXT_DOMAIN ),    __( 'Dashboard', TKR_TEXT_DOMAIN ),    'manage_options', 'tkr-dashboard', [ $this, 'render_dashboard' ] );
        add_submenu_page( 'tkr-dashboard', __( 'Import', TKR_TEXT_DOMAIN ),        __( 'Import', TKR_TEXT_DOMAIN ),        'manage_options', 'tkr-import',    [ new TKR_Import_Page(), 'render' ] );
        add_submenu_page( 'tkr-dashboard', __( 'Einstellungen', TKR_TEXT_DOMAIN ), __( 'Einstellungen', TKR_TEXT_DOMAIN ), 'manage_options', 'tkr-settings',  [ new TKR_Settings_Page(), 'render' ] );
    }

    public function render_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $counts     = TKR_Schema::get_table_counts();
        $db_version = get_option( TKR_Schema::DB_VERSION_OPTION, __( 'Nicht installiert', TKR_TEXT_DOMAIN ) );
        $last       = get_option( 'tkr_last_import', null );

        // Readiness: animals, fee_rules and treatments must have data
        $ready_tables  = [ 'animals', 'fee_rules', 'treatments' ];
        $is_ready      = true;
        $empty_tables  = [];
        foreach ( $ready_tables as $t ) {
            if ( ! isset( $counts[ $t ] ) || $counts[ $t ] < 1 ) {
                $is_ready      = false;
                $empty_tables[] = $t;
            }
        }

        $import_url = esc_url( admin_url( 'admin.php?page=tkr-import' ) );

        $table_labels = [
            'animals'            => __( 'Tierarten', TKR_TEXT_DOMAIN ),
            'animal_subgroups'   => __( 'Untergruppen', TKR_TEXT_DOMAIN ),
            'got_services'       => __( 'GOT-Positionen', TKR_TEXT_DOMAIN ),
            'fee_rules'          => __( 'Gebührenregeln', TKR_TEXT_DOMAIN ),
            'treatments'         => __( 'Behandlungen', TKR_TEXT_DOMAIN ),
            'treatment_services' => __( 'Behandlung → GOT-Verknüpfungen', TKR_TEXT_DOMAIN ),
            'search_terms'       => __( 'Suchbegriffe', TKR_TEXT_DOMAIN ),
        ];

        ?>
        <div class="wrap tkr-dashboard">
            <h1><?php esc_html_e( 'Tierarztkostenrechner – Dashboard', TKR_TEXT_DOMAIN ); ?></h1>

            <?php if ( ! $is_ready ) : ?>
            <div class="notice notice-warning" style="padding:12px 16px;">
                <strong><?php esc_html_e( '⚠ Rechner noch nicht einsatzbereit.', TKR_TEXT_DOMAIN ); ?></strong>
                <?php
                echo ' ';
                $label_list = array_map( fn( $t ) => '<em>' . esc_html( $table_labels[ $t ] ?? $t ) . '</em>', $empty_tables );
                printf(
                    wp_kses( __( 'Folgende Tabellen sind leer: %s. Bitte <a href="%s">Masterdatei importieren</a>.', TKR_TEXT_DOMAIN ), [ 'a' => [ 'href' => [] ], 'em' => [] ] ),
                    implode( ', ', $label_list ),
                    $import_url
                );
                ?>
            </div>
            <?php else : ?>
            <div class="notice notice-success" style="padding:12px 16px;">
                <strong><?php esc_html_e( '✓ Rechner ist einsatzbereit.', TKR_TEXT_DOMAIN ); ?></strong>
                <?php echo ' '; esc_html_e( 'Shortcode:', TKR_TEXT_DOMAIN ); ?>
                <code>[tierarztkostenrechner]</code>
            </div>
            <?php endif; ?>

            <h2 style="margin-top:24px;"><?php esc_html_e( 'Datenstatus', TKR_TEXT_DOMAIN ); ?></h2>
            <table class="widefat tkr-status-table" style="max-width:560px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Tabelle', TKR_TEXT_DOMAIN ); ?></th>
                        <th style="text-align:right;"><?php esc_html_e( 'Datensätze', TKR_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Status', TKR_TEXT_DOMAIN ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $table_labels as $key => $label ) :
                    $count  = $counts[ $key ] ?? null;
                    $is_crit = in_array( $key, $ready_tables, true );
                    if ( $count === null ) {
                        $badge = '<span style="color:#c00;">Tabelle fehlt</span>';
                    } elseif ( $count === 0 && $is_crit ) {
                        $badge = '<span style="color:#c00;">⚠ Leer – Import erforderlich</span>';
                    } elseif ( $count === 0 ) {
                        $badge = '<span style="color:#888;">Leer</span>';
                    } else {
                        $badge = '<span style="color:#2e7d32;">✓</span>';
                    }
                ?>
                    <tr>
                        <td><?php echo esc_html( $label ); ?></td>
                        <td style="text-align:right;"><?php echo $count !== null ? number_format_i18n( $count ) : '–'; ?></td>
                        <td><?php echo wp_kses_post( $badge ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;">
                <a href="<?php echo $import_url; ?>" class="button button-primary"><?php esc_html_e( 'Zum Import', TKR_TEXT_DOMAIN ); ?></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tkr-settings' ) ); ?>" class="button"><?php esc_html_e( 'Einstellungen', TKR_TEXT_DOMAIN ); ?></a>
            </div>

            <?php if ( $last ) : ?>
            <h2 style="margin-top:32px;"><?php esc_html_e( 'Letzter Import', TKR_TEXT_DOMAIN ); ?></h2>
            <table class="widefat" style="max-width:560px;">
                <tbody>
                <tr><th><?php esc_html_e( 'Zeitpunkt', TKR_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( $last['timestamp'] ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Benutzer-ID', TKR_TEXT_DOMAIN ); ?></th><td><?php echo (int) $last['user_id']; ?></td></tr>
                <tr><th><?php esc_html_e( 'Dry Run', TKR_TEXT_DOMAIN ); ?></th><td><?php echo $last['dry_run'] ? esc_html__( 'Ja', TKR_TEXT_DOMAIN ) : esc_html__( 'Nein', TKR_TEXT_DOMAIN ); ?></td></tr>
                <?php foreach ( ( $last['counts'] ?? [] ) as $sheet => $c ) : ?>
                <tr><th><?php echo esc_html( $table_labels[ $sheet ] ?? $sheet ); ?></th><td><?php echo number_format_i18n( (int) $c ); ?></td></tr>
                <?php endforeach; ?>
                <?php if ( ! empty( $last['warnings'] ) ) : ?>
                <tr><th><?php esc_html_e( 'Warnungen', TKR_TEXT_DOMAIN ); ?></th><td><?php echo (int) count( $last['warnings'] ); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <p style="margin-top:24px;color:#888;font-size:0.85rem;">
                <?php printf( esc_html__( 'DB-Schema-Version: %s | Plugin-Version: %s', TKR_TEXT_DOMAIN ), esc_html( $db_version ), TKR_VERSION ); ?>
            </p>
        </div>
        <?php
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'tkr-' ) === false && strpos( $hook, 'tkr_' ) === false ) return;
        wp_enqueue_style( 'tkr-admin', TKR_PLUGIN_URL . 'assets/css/tkr-admin.css', [], TKR_VERSION );
    }
}
