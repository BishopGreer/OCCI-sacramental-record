<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_footer', [ __CLASS__, 'render_footer' ] );
    }

    public static function register_menus() {
        add_menu_page(
            'OCCI Sacramental Records',
            'Sacramental Records',
            'occi_view_records',
            'occi-sacramental-records',
            [ __CLASS__, 'dashboard_page' ],
            'dashicons-book-alt',
            30
        );
        add_submenu_page( 'occi-sacramental-records', 'Dashboard',          'Dashboard',          'occi_view_records',   'occi-sacramental-records', [ __CLASS__, 'dashboard_page' ] );
        add_submenu_page( 'occi-sacramental-records', 'Baptisms',           'Baptisms',           'occi_view_records',   'occi-baptisms',            [ 'OCCI_Baptism', 'page' ] );
        add_submenu_page( 'occi-sacramental-records', 'Confirmations',      'Confirmations',      'occi_view_records',   'occi-confirmations',       [ 'OCCI_Confirmation', 'page' ] );
        add_submenu_page( 'occi-sacramental-records', 'Marriages',          'Marriages',          'occi_view_records',   'occi-marriages',           [ 'OCCI_Marriage', 'page' ] );
        add_submenu_page( 'occi-sacramental-records', 'Deaths',             'Deaths',             'occi_view_records',   'occi-deaths',              [ 'OCCI_Death', 'page' ] );
        add_submenu_page( 'occi-sacramental-records', 'First Communions',   'First Communions',   'occi_view_records',   'occi-communions',          [ 'OCCI_Communion', 'page' ] );
        add_submenu_page( 'occi-sacramental-records', 'Ordinations',        'Ordinations',        'occi_view_records',   'occi-ordinations',         [ 'OCCI_Ordination', 'page' ] );
        add_submenu_page( 'occi-sacramental-records', 'Import / Export',      'Import / Export',      'occi_view_records',   'occi-import-export',       [ 'OCCI_ImportExport', 'page' ] );
        add_submenu_page( 'occi-sacramental-records', 'Person Report',      'Person Report',      'occi_view_records',   'occi-report',              [ 'OCCI_Report', 'page' ] );
        add_submenu_page( 'occi-sacramental-records', 'Parishes',           'Parishes',           'occi_manage_records', 'occi-parishes',            [ 'OCCI_Parishes', 'page' ] );
        add_submenu_page( 'occi-sacramental-records', 'Certificate Settings','Certificate Settings','occi_manage_records','occi-cert-settings',      [ 'OCCI_Certificates', 'settings_page' ] );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'occi' ) === false ) return;
        wp_enqueue_style( 'occi-admin', OCCI_SR_PLUGIN_URL . 'admin/css/occi-admin.css', [], OCCI_SR_VERSION );
        wp_enqueue_script( 'occi-admin', OCCI_SR_PLUGIN_URL . 'admin/js/occi-admin.js', [ 'jquery' ], OCCI_SR_VERSION, true );
        // Media uploader for certificate settings
        if ( strpos( $hook, 'cert-settings' ) !== false || strpos( $hook, 'occi-parishes' ) !== false ) {
            wp_enqueue_media();
        }
    }

    public static function dashboard_page() {
        global $wpdb;
        if ( ! current_user_can( 'occi_view_records' ) ) { wp_die( 'Access denied.' ); }
        $counts = [
            'Baptisms'         => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occi_baptisms" ),      'occi-baptisms' ],
            'Confirmations'    => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occi_confirmations" ), 'occi-confirmations' ],
            'Marriages'        => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occi_marriages" ),     'occi-marriages' ],
            'Deaths'           => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occi_deaths" ),        'occi-deaths' ],
            'First Communions' => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occi_communions" ),   'occi-communions' ],
            'Ordinations'      => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occi_ordinations" ),  'occi-ordinations' ],
        ];
        ?>
        <div class="wrap occi-wrap">
            <h1><span class="dashicons dashicons-book-alt"></span> OCCI Sacramental Records</h1>
            
            <div class="occi-dashboard-grid">
                <?php foreach ( $counts as $label => [ $count, $slug ] ) : ?>
                <div class="occi-stat-card">
                    <div class="occi-stat-number"><?php echo intval( $count ); ?></div>
                    <div class="occi-stat-label"><?php echo esc_html( $label ); ?></div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="button button-primary">View Register</a>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="occi-dashboard-tools">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-report' ) ); ?>" class="button button-secondary occi-tool-btn">&#128269; Person Report</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-cert-settings' ) ); ?>" class="button button-secondary occi-tool-btn">&#127881; Certificate Settings</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-parishes' ) ); ?>" class="button button-secondary occi-tool-btn">&#127776; Manage Parishes</a>
            </div>
            <div class="occi-notice">
                <p><strong>Pax et Bonum.</strong> This database is confidential. Access is restricted to authorized diocesan and parish personnel only. All records are permanent canonical documents.</p>
            </div>
        </div>
        <?php
    }

    public static function render_footer() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'occi' ) === false ) return;
        ?>
        <div class="occi-admin-footer">
            <span>Old Catholic Churches International &mdash; National Sacramental Database</span>
            <span>v<?php echo esc_html( OCCI_SR_VERSION ); ?> &mdash; <em>Pax et Bonum</em></span>
        </div>
        <?php
    }

}