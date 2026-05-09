<?php
defined( 'ABSPATH' ) || exit;

class TKR_Admin_Menu {

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
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

        add_submenu_page(
            'tkr-dashboard',
            __( 'Dashboard', TKR_TEXT_DOMAIN ),
            __( 'Dashboard', TKR_TEXT_DOMAIN ),
            'manage_options',
            'tkr-dashboard',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'tkr-dashboard',
            __( 'Import', TKR_TEXT_DOMAIN ),
            __( 'Import', TKR_TEXT_DOMAIN ),
            'manage_options',
            'tkr-import',
            [ new TKR_Import_Page(), 'render' ]
        );

        add_submenu_page(
            'tkr-dashboard',
            __( 'Einstellungen', TKR_TEXT_DOMAIN ),
            __( 'Einstellungen', TKR_TEXT_DOMAIN ),
            'manage_options',
            'tkr-settings',
            [ new TKR_Settings_Page(), 'render' ]
        );
    }

    public function render_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        global $wpdb;
        $animals_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tkr_animals" );
        $services_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tkr_got_services" );
        $treatment_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tkr_treatments" );
        $search_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tkr_search_terms" );
        $last_import     = get_option( 'tkr_last_import', null );
        $db_version      = get_option( TKR_Schema::DB_VERSION_OPTION, __( 'Nicht installiert', TKR_TEXT_DOMAIN ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Tierarztkostenrechner &mdash; Dashboard', TKR_TEXT_DOMAIN ); ?></h1>

            <div class="tkr-admin-cards" style="display:flex;gap:16px;flex-wrap:wrap;margin-top:20px;">
                <?php
                $cards = [
                    [ 'label' => __( 'Datenbankversion', TKR_TEXT_DOMAIN ), 'value' => esc_html( $db_version ) ],
                    [ 'label' => __( 'Tierarten', TKR_TEXT_DOMAIN ),        'value' => $animals_count ],
                    [ 'label' => __( 'GOT-Positionen', TKR_TEXT_DOMAIN ),   'value' => $services_count ],
                    [ 'label' => __( 'Behandlungen', TKR_TEXT_DOMAIN ),     'value' => $treatment_count ],
                    [ 'label' => __( 'Suchbegriffe', TKR_TEXT_DOMAIN ),     'value' => $search_count ],
                ];
                foreach ( $cards as $card ) :
                ?>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px 24px;min-width:150px;">
                    <div style="font-size:1.8rem;font-weight:700;color:#20547E;"><?php echo $card['value']; ?></div>
                    <div style="color:#666;font-size:0.9rem;margin-top:4px;"><?php echo $card['label']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ( $last_import ) : ?>
            <h2 style="margin-top:28px;"><?php esc_html_e( 'Letzter Import', TKR_TEXT_DOMAIN ); ?></h2>
            <table class="widefat" style="max-width:600px;">
                <tbody>
                    <tr><th><?php esc_html_e( 'Zeitstempel', TKR_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( $last_import['timestamp'] ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Benutzer-ID', TKR_TEXT_DOMAIN ); ?></th><td><?php echo (int) $last_import['user_id']; ?></td></tr>
                    <tr><th><?php esc_html_e( 'Dry Run', TKR_TEXT_DOMAIN ); ?></th><td><?php echo $last_import['dry_run'] ? 'Ja' : 'Nein'; ?></td></tr>
                    <?php foreach ( ( $last_import['counts'] ?? [] ) as $sheet => $count ) : ?>
                    <tr><th><?php echo esc_html( $sheet ); ?></th><td><?php echo (int) $count; ?> <?php esc_html_e( 'Datensätze', TKR_TEXT_DOMAIN ); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p style="margin-top:20px;"><?php esc_html_e( 'Noch kein Import durchgeführt.', TKR_TEXT_DOMAIN ); ?></p>
            <?php endif; ?>

            <p style="margin-top:24px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tkr-import' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Zum Import', TKR_TEXT_DOMAIN ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( strpos( $hook, 'tkr-' ) === false ) return;
        wp_enqueue_style( 'tkr-admin', TKR_PLUGIN_URL . 'assets/css/tkr-admin.css', [], TKR_VERSION );
    }
}
