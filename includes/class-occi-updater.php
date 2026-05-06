<?php
/**
 * OCCI Sacramental Records — Automatic Update Checker
 *
 * Supports two update sources, configured via constants in wp-config.php:
 *
 * SELF-HOSTED (recommended — you control everything):
 *   define( 'OCCI_UPDATE_URL', 'https://myocci.org/updates/occi-sacramental-records.json' );
 *
 * GITHUB RELEASES:
 *   define( 'OCCI_UPDATE_URL', 'https://github.com/YOUR-ORG/occi-sacramental-records' );
 *   define( 'OCCI_UPDATE_SOURCE', 'github' );
 *   // Optional: add a personal access token for private repos:
 *   define( 'OCCI_UPDATE_GITHUB_TOKEN', 'ghp_yourtoken' );
 *
 * The self-hosted JSON file format (upload to myocci.org):
 * {
 *   "version":      "1.0.5",
 *   "download_url": "https://myocci.org/updates/occi-sacramental-records-1.0.5.zip",
 *   "requires":     "6.0",
 *   "requires_php": "8.0",
 *   "tested":       "6.8",
 *   "last_updated": "2026-05-05",
 *   "changelog":    "<h4>1.0.5</h4><ul><li>What changed</li></ul>"
 * }
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Updater {

    const TRANSIENT_KEY   = 'occi_sr_update_check';
    const CACHE_HOURS     = 12;
    const PLUGIN_SLUG     = 'occi-sacramental-records/occi-sacramental-records.php';
    const PLUGIN_BASENAME = 'occi-sacramental-records';

    private string $update_url;
    private string $source_type;
    private string $github_token;

    public function __construct() {
        $this->update_url   = defined( 'OCCI_UPDATE_URL' )          ? OCCI_UPDATE_URL          : '';
        $this->source_type  = defined( 'OCCI_UPDATE_SOURCE' )        ? OCCI_UPDATE_SOURCE        : 'self_hosted';
        $this->github_token = defined( 'OCCI_UPDATE_GITHUB_TOKEN' )  ? OCCI_UPDATE_GITHUB_TOKEN  : '';
    }

    public static function init() {
        $instance = new self();
        if ( ! $instance->update_url ) return; // No source configured; updates disabled.
        add_filter( 'pre_set_site_transient_update_plugins', [ $instance, 'check_for_update' ] );
        add_filter( 'plugins_api',                           [ $instance, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_source_selection',             [ $instance, 'fix_directory_name' ], 10, 4 );
    }

    // -------------------------------------------------------------------------
    // WordPress update transient hook — inject update info if a newer version exists
    // -------------------------------------------------------------------------

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $remote = $this->get_remote_info();
        if ( ! $remote || empty( $remote->version ) ) return $transient;

        if ( version_compare( OCCI_SR_VERSION, $remote->version, '<' ) ) {
            $transient->response[ self::PLUGIN_SLUG ] = (object) [
                'slug'        => self::PLUGIN_BASENAME,
                'plugin'      => self::PLUGIN_SLUG,
                'new_version' => $remote->version,
                'url'         => $remote->details_url ?? $this->update_url,
                'package'     => $remote->download_url ?? '',
                'icons'       => [],
                'banners'     => [],
                'tested'      => $remote->tested ?? '',
                'requires'    => $remote->requires ?? '6.0',
                'requires_php'=> $remote->requires_php ?? '8.0',
            ];
        } else {
            // No update: put in no_update so WP knows we checked
            $transient->no_update[ self::PLUGIN_SLUG ] = (object) [
                'slug'        => self::PLUGIN_BASENAME,
                'plugin'      => self::PLUGIN_SLUG,
                'new_version' => OCCI_SR_VERSION,
                'url'         => '',
                'package'     => '',
            ];
        }
        return $transient;
    }

    // -------------------------------------------------------------------------
    // Plugin info popup (shown when user clicks "View version X.X.X details")
    // -------------------------------------------------------------------------

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== self::PLUGIN_BASENAME ) return $result;

        $remote = $this->get_remote_info();
        if ( ! $remote ) return $result;

        return (object) [
            'name'          => 'OCCI Sacramental Records',
            'slug'          => self::PLUGIN_BASENAME,
            'version'       => $remote->version ?? OCCI_SR_VERSION,
            'author'        => 'Old Catholic Churches International',
            'author_profile'=> 'https://myocci.org',
            'homepage'      => 'https://myocci.org',
            'requires'      => $remote->requires     ?? '6.0',
            'requires_php'  => $remote->requires_php ?? '8.0',
            'tested'        => $remote->tested        ?? '',
            'last_updated'  => $remote->last_updated  ?? '',
            'download_link' => $remote->download_url  ?? '',
            'sections'      => [
                'description' => 'National sacramental record database for Old Catholic Churches International.',
                'changelog'   => $remote->changelog ?? '<p>See release notes.</p>',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Fix directory name after download (GitHub zips get a hash-suffixed folder)
    // -------------------------------------------------------------------------

    public function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra = [] ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::PLUGIN_SLUG ) {
            return $source;
        }
        global $wp_filesystem;
        $correct = trailingslashit( $remote_source ) . self::PLUGIN_BASENAME . '/';
        if ( $source !== $correct && $wp_filesystem->is_dir( $source ) ) {
            if ( $wp_filesystem->move( $source, $correct ) ) {
                return $correct;
            }
        }
        return $source;
    }

    // -------------------------------------------------------------------------
    // Fetch and cache remote version info
    // -------------------------------------------------------------------------

    private function get_remote_info(): ?object {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached !== false ) return $cached;

        $info = ( $this->source_type === 'github' )
            ? $this->fetch_github()
            : $this->fetch_self_hosted();

        if ( $info ) {
            set_transient( self::TRANSIENT_KEY, $info, HOUR_IN_SECONDS * self::CACHE_HOURS );
        }
        return $info;
    }

    // -------------------------------------------------------------------------
    // Self-hosted JSON source
    // -------------------------------------------------------------------------

    private function fetch_self_hosted(): ?object {
        $response = wp_remote_get( $this->update_url, [
            'timeout'    => 10,
            'user-agent' => 'OCCI-Sacramental-Records/' . OCCI_SR_VERSION . '; ' . home_url(),
        ] );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ) );
        return ( $body && isset( $body->version ) ) ? $body : null;
    }

    // -------------------------------------------------------------------------
    // GitHub releases source
    // Reads the latest release from the GitHub Releases API.
    // The download URL is taken from the first .zip release asset if present,
    // otherwise falls back to the auto-generated zipball.
    // -------------------------------------------------------------------------

    private function fetch_github(): ?object {
        // Expect URL like https://github.com/OWNER/REPO
        $parts = array_filter( explode( '/', trim( $this->update_url, '/' ) ) );
        $parts = array_values( $parts );
        // parts: [0]=>'https:', [1]=>'', [2]=>'github.com', [3]=>owner, [4]=>repo
        // after filter+reindex from non-empty: [0]=>'https:', [1]=>'github.com', [2]=>owner, [3]=>repo
        // Actually let's just parse the URL properly
        $parsed = parse_url( $this->update_url );
        $path_parts = array_values( array_filter( explode( '/', $parsed['path'] ?? '' ) ) );
        if ( count( $path_parts ) < 2 ) return null;
        $owner = $path_parts[0];
        $repo  = $path_parts[1];

        $api_url = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";
        $headers = [
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'OCCI-Sacramental-Records/' . OCCI_SR_VERSION,
        ];
        if ( $this->github_token ) {
            $headers['Authorization'] = 'Bearer ' . $this->github_token;
        }

        $response = wp_remote_get( $api_url, [ 'timeout' => 10, 'headers' => $headers ] );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $release || empty( $release->tag_name ) ) return null;

        $version = ltrim( $release->tag_name, 'v' );

        // Find a .zip asset; fall back to zipball_url
        $download_url = $release->zipball_url ?? '';
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( str_ends_with( $asset->name ?? '', '.zip' ) ) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        // Parse changelog from release body (convert basic markdown)
        $changelog = '<p>' . nl2br( esc_html( $release->body ?? '' ) ) . '</p>';

        return (object) [
            'version'      => $version,
            'download_url' => $download_url,
            'details_url'  => $release->html_url ?? $this->update_url,
            'last_updated' => isset( $release->published_at ) ? date( 'Y-m-d', strtotime( $release->published_at ) ) : '',
            'changelog'    => $changelog,
            'requires'     => '6.0',
            'requires_php' => '8.0',
            'tested'       => '6.8',
        ];
    }

    // -------------------------------------------------------------------------
    // Admin settings UI — shown on the Certificate Settings page
    // -------------------------------------------------------------------------

    public static function render_settings_section() {
        $configured_url    = defined( 'OCCI_UPDATE_URL' )    ? OCCI_UPDATE_URL    : '';
        $configured_source = defined( 'OCCI_UPDATE_SOURCE' ) ? OCCI_UPDATE_SOURCE : 'self_hosted';
        $configured_token  = defined( 'OCCI_UPDATE_GITHUB_TOKEN' ) ? '(set in wp-config.php)' : '(not set)';
        ?>
        <div class="occi-section" style="margin-top:24px;">
            <h2>Automatic Updates</h2>
            <p>To enable automatic update checking, add one of the following blocks to your <code>wp-config.php</code> file:</p>

            <h3 style="font-size:1em; margin-bottom:4px;">Option A &mdash; Self-Hosted (Recommended)</h3>
            <p>Host a JSON file at a URL you control on myocci.org. Update the file whenever a new version is released.</p>
            <pre style="background:#f0f0f0; padding:12px; border-radius:3px; overflow-x:auto; font-size:0.85em;">// Add to wp-config.php:
define( 'OCCI_UPDATE_URL', 'https://myocci.org/updates/occi-sacramental-records.json' );
// OCCI_UPDATE_SOURCE defaults to 'self_hosted' — no need to define it.</pre>

            <p>The JSON file at that URL must contain:</p>
            <pre style="background:#f0f0f0; padding:12px; border-radius:3px; overflow-x:auto; font-size:0.85em;">{
  "version":      "1.0.4",
  "download_url": "https://myocci.org/updates/occi-sacramental-records-1.0.4.zip",
  "requires":     "6.0",
  "requires_php": "8.0",
  "tested":       "6.8",
  "last_updated": "2026-05-05",
  "changelog":    "&lt;h4&gt;1.0.4&lt;/h4&gt;&lt;ul&gt;&lt;li&gt;What changed&lt;/li&gt;&lt;/ul&gt;"
}</pre>

            <h3 style="font-size:1em; margin-bottom:4px; margin-top:16px;">Option B &mdash; GitHub Releases</h3>
            <p>Tag each release in a GitHub repository. The plugin reads the latest release automatically.</p>
            <pre style="background:#f0f0f0; padding:12px; border-radius:3px; overflow-x:auto; font-size:0.85em;">// Add to wp-config.php:
define( 'OCCI_UPDATE_URL',    'https://github.com/YOUR-ORG/occi-sacramental-records' );
define( 'OCCI_UPDATE_SOURCE', 'github' );
// For private repositories only:
define( 'OCCI_UPDATE_GITHUB_TOKEN', 'ghp_your_personal_access_token' );</pre>
            <p>For GitHub, each release ZIP must be attached as a release asset, or the auto-generated zipball will be used. Tag versions as <code>v1.0.4</code>, <code>v1.0.5</code>, etc.</p>

            <table class="form-table" style="margin-top:12px;">
                <tr><th>Current Update Source</th><td><code><?php echo $configured_url ? esc_html( $configured_url ) : 'Not configured (updates disabled)'; ?></code></td></tr>
                <tr><th>Source Type</th><td><code><?php echo esc_html( $configured_source ); ?></code></td></tr>
                <?php if ( $configured_source === 'github' ) : ?>
                <tr><th>GitHub Token</th><td><code><?php echo esc_html( $configured_token ); ?></code></td></tr>
                <?php endif; ?>
                <tr><th>Check Interval</th><td>Every <?php echo self::CACHE_HOURS; ?> hours</td></tr>
                <tr><th>Current Version</th><td><?php echo esc_html( OCCI_SR_VERSION ); ?></td></tr>
            </table>

            <?php if ( $configured_url && current_user_can( 'update_plugins' ) ) : ?>
            <p style="margin-top:12px;">
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=occi-cert-settings&occi_clear_update_cache=1' ), 'occi_clear_update_cache' ) ); ?>"
                   class="button">Force Update Check Now</a>
                <span class="description" style="margin-left:8px;">Clears the cached result and re-checks the update source immediately.</span>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function maybe_clear_cache() {
        if ( ! isset( $_GET['occi_clear_update_cache'] ) ) return;
        if ( ! check_admin_referer( 'occi_clear_update_cache' ) ) return;
        delete_transient( self::TRANSIENT_KEY );
        // Also clear WP's own plugin update transient so it re-checks everything
        delete_site_transient( 'update_plugins' );
        wp_redirect( admin_url( 'admin.php?page=occi-cert-settings&cache_cleared=1' ) );
        exit;
    }
}
