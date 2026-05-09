<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Updater {

    const GITHUB_REPO    = 'BishopGreer/OCCI-sacramental-record';
    const TRANSIENT_KEY  = 'occi_sr_update_check';
    const CACHE_HOURS    = 12;
    const PLUGIN_SLUG    = 'occi-sacramental-records/occi-sacramental-records.php';
    const PLUGIN_BASE    = 'occi-sacramental-records';

    public static function init() {
        $instance = new self();
        add_filter( 'pre_set_site_transient_update_plugins', [ $instance, 'check_for_update' ] );
        add_filter( 'plugins_api',                           [ $instance, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_source_selection',             [ $instance, 'fix_directory_name' ], 10, 4 );
    }

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $remote = $this->get_remote_info();
        if ( ! $remote || empty( $remote->version ) ) return $transient;

        if ( version_compare( OCCI_SR_VERSION, $remote->version, '<' ) ) {
            $transient->response[ self::PLUGIN_SLUG ] = (object) [
                'slug'         => self::PLUGIN_BASE,
                'plugin'       => self::PLUGIN_SLUG,
                'new_version'  => $remote->version,
                'url'          => $remote->details_url,
                'package'      => $remote->download_url,
                'icons'        => [
                    '1x'  => OCCI_SR_PLUGIN_URL . 'assets/images/icon-128x128.png',
                    '2x'  => OCCI_SR_PLUGIN_URL . 'assets/images/icon-256x256.png',
                ],
                'banners'      => [
                    'low'  => OCCI_SR_PLUGIN_URL . 'assets/images/banner-772x250.png',
                    'high' => OCCI_SR_PLUGIN_URL . 'assets/images/banner-1544x500.png',
                ],
                'tested'       => $remote->tested,
                'requires'     => '6.0',
                'requires_php' => '8.0',
            ];
        } else {
            $transient->no_update[ self::PLUGIN_SLUG ] = (object) [
                'slug'        => self::PLUGIN_BASE,
                'plugin'      => self::PLUGIN_SLUG,
                'new_version' => OCCI_SR_VERSION,
                'url'         => '',
                'package'     => '',
            ];
        }
        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== self::PLUGIN_BASE ) return $result;

        $remote = $this->get_remote_info();
        if ( ! $remote ) return $result;

        return (object) [
            'name'           => 'OCCI Sacramental Records',
            'slug'           => self::PLUGIN_BASE,
            'version'        => $remote->version,
            'author'         => 'Old Catholic Churches International',
            'author_profile' => 'https://myocci.org',
            'homepage'       => 'https://github.com/' . self::GITHUB_REPO,
            'requires'       => '6.0',
            'requires_php'   => '8.0',
            'tested'         => $remote->tested,
            'last_updated'   => $remote->last_updated,
            'download_link'  => $remote->download_url,
            'icons'          => [
                '1x'  => OCCI_SR_PLUGIN_URL . 'assets/images/icon-128x128.png',
                '2x'  => OCCI_SR_PLUGIN_URL . 'assets/images/icon-256x256.png',
            ],
            'banners'        => [
                'low'  => OCCI_SR_PLUGIN_URL . 'assets/images/banner-772x250.png',
                'high' => OCCI_SR_PLUGIN_URL . 'assets/images/banner-1544x500.png',
            ],
            'sections'       => [
                'description' => 'National sacramental record database for Old Catholic Churches International.',
                'changelog'   => $remote->changelog,
            ],
        ];
    }

    public function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra = [] ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::PLUGIN_SLUG ) {
            return $source;
        }
        global $wp_filesystem;
        $correct = trailingslashit( $remote_source ) . self::PLUGIN_BASE . '/';
        if ( $source !== $correct && $wp_filesystem->is_dir( $source ) ) {
            if ( $wp_filesystem->move( $source, $correct ) ) {
                return $correct;
            }
        }
        return $source;
    }

    private function get_remote_info(): ?object {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached !== false ) return $cached;

        $api_url  = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
        $response = wp_remote_get( $api_url, [
            'timeout'    => 10,
            'headers'    => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'OCCI-Sacramental-Records/' . OCCI_SR_VERSION,
            ],
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $release || empty( $release->tag_name ) ) return null;

        $version      = ltrim( $release->tag_name, 'v' );
        $download_url = $release->zipball_url ?? '';
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( str_ends_with( $asset->name ?? '', '.zip' ) ) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        $info = (object) [
            'version'      => $version,
            'download_url' => $download_url,
            'details_url'  => $release->html_url ?? ( 'https://github.com/' . self::GITHUB_REPO . '/releases' ),
            'last_updated' => isset( $release->published_at ) ? date( 'Y-m-d', strtotime( $release->published_at ) ) : '',
            'changelog'    => '<p>' . nl2br( esc_html( $release->body ?? '' ) ) . '</p>',
            'tested'       => '6.8',
        ];

        set_transient( self::TRANSIENT_KEY, $info, HOUR_IN_SECONDS * self::CACHE_HOURS );
        return $info;
    }

    public static function render_settings_section() {
        $remote = ( new self() )->get_remote_info();
        ?>
        <div class="occi-section" style="margin-top:24px;">
            <h2>Automatic Updates</h2>
            <p>This plugin checks for updates automatically via its GitHub repository. No configuration is required.</p>
            <table class="form-table">
                <tr><th>Update Source</th><td><a href="https://github.com/<?php echo esc_html( self::GITHUB_REPO ); ?>/releases" target="_blank">github.com/<?php echo esc_html( self::GITHUB_REPO ); ?></a></td></tr>
                <tr><th>Installed Version</th><td><?php echo esc_html( OCCI_SR_VERSION ); ?></td></tr>
                <tr><th>Latest Available</th><td><?php echo $remote ? esc_html( $remote->version ) : 'Unable to reach GitHub'; ?></td></tr>
                <tr><th>Check Interval</th><td>Every <?php echo self::CACHE_HOURS; ?> hours</td></tr>
            </table>
            <?php if ( current_user_can( 'update_plugins' ) ) : ?>
            <p style="margin-top:12px;">
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=occi-cert-settings&occi_clear_update_cache=1' ), 'occi_clear_update_cache' ) ); ?>"
                   class="button">Force Update Check Now</a>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function maybe_clear_cache() {
        if ( ! isset( $_GET['occi_clear_update_cache'] ) ) return;
        if ( ! check_admin_referer( 'occi_clear_update_cache' ) ) return;
        delete_transient( self::TRANSIENT_KEY );
        delete_site_transient( 'update_plugins' );
        wp_redirect( admin_url( 'admin.php?page=occi-cert-settings&cache_cleared=1' ) );
        exit;
    }
}
