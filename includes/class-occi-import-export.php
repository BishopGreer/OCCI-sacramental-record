<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_ImportExport {

    const FORMAT_VERSION = 1;

    public static function init() {
        add_action( 'admin_post_occi_export', [ __CLASS__, 'handle_export' ] );
        add_action( 'admin_post_occi_import', [ __CLASS__, 'handle_import' ] );
    }

    // =========================================================================
    // Admin page
    // =========================================================================

    public static function page() {
        if ( ! current_user_can( 'occi_view_records' ) ) { wp_die( 'Access denied.' ); }

        // Show import results if stored
        $results = get_transient( 'occi_import_results_' . get_current_user_id() );
        if ( $results ) {
            delete_transient( 'occi_import_results_' . get_current_user_id() );
        }
        $parishes = OCCI_Database::get_parishes();
        ?>
        <div class="wrap occi-wrap">
            <h1>Import / Export Sacramental Records</h1>

            <?php if ( $results ) : self::render_import_results( $results ); endif; ?>

            <div class="occi-two-col" style="align-items: flex-start; gap: 28px;">

                <!-- EXPORT -->
                <div style="flex: 1;">
                    <div class="occi-section">
                        <h2>Export Records</h2>
                        <p>Export sacramental records to a JSON file that can be sent to the national OCCI database or another parish installation. The file includes all record types selected and embeds parish information in each record.</p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'occi_export', 'occi_nonce' ); ?>
                            <input type="hidden" name="action" value="occi_export">
                            <table class="form-table">
                                <tr>
                                    <th><label>Filter by Parish</label></th>
                                    <td>
                                        <select name="export_parish_id">
                                            <option value="0">All Parishes</option>
                                            <?php foreach ( $parishes as $p ) : ?>
                                            <option value="<?php echo esc_attr( $p->id ); ?>">
                                                <?php echo esc_html( $p->name . ', ' . $p->city . ', ' . $p->state ); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label>Record Types</label></th>
                                    <td>
                                        <?php
                                        $types = [
                                            'baptisms'      => 'Baptisms',
                                            'confirmations' => 'Confirmations',
                                            'marriages'     => 'Marriages',
                                            'deaths'        => 'Deaths',
                                            'communions'    => 'First Communions',
                                            'ordinations'   => 'Ordinations',
                                        ];
                                        foreach ( $types as $key => $label ) :
                                        ?>
                                        <label style="display:block; margin-bottom:4px;">
                                            <input type="checkbox" name="export_types[]" value="<?php echo esc_attr( $key ); ?>" checked>
                                            <?php echo esc_html( $label ); ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            </table>
                            <p>
                                <button type="submit" class="button button-primary">&#11123; Download Export File</button>
                            </p>
                        </form>
                    </div>
                </div>

                <!-- IMPORT -->
                <div style="flex: 1;">
                    <div class="occi-section">
                        <h2>Import Records</h2>
                        <?php if ( ! current_user_can( 'occi_manage_records' ) ) : ?>
                        <div class="notice notice-warning inline"><p>You need manage permissions to import records.</p></div>
                        <?php else : ?>
                        <p>Upload a JSON export file from a parish installation. The import engine will:</p>
                        <ul style="list-style: disc; margin-left: 1.5em; line-height: 1.8;">
                            <li><strong>Skip</strong> records that already exist in this database</li>
                            <li><strong>Add</strong> new sacrament records for people already in the database</li>
                            <li><strong>Create</strong> new records for people not yet in the database</li>
                            <li><strong>Match</strong> individuals by name <em>and</em> date of birth where available, so two people with the same name are never conflated</li>
                            <li><strong>Match or create</strong> parishes by name, city, and state</li>
                        </ul>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                              enctype="multipart/form-data">
                            <?php wp_nonce_field( 'occi_import', 'occi_nonce' ); ?>
                            <input type="hidden" name="action" value="occi_import">
                            <table class="form-table">
                                <tr>
                                    <th><label>Export File (.json)</label></th>
                                    <td>
                                        <input type="file" name="import_file" accept=".json" required>
                                        <p class="description">Select an OCCI sacramental records export file.</p>
                                    </td>
                                </tr>
                            </table>
                            <p>
                                <button type="submit" class="button button-primary">&#11121; Import Records</button>
                            </p>
                        </form>
                        <?php endif; ?>
                    </div>

                    <div class="occi-section" style="margin-top:20px;">
                        <h2>File Format Notes</h2>
                        <p style="font-size:0.9em; color:#555;">
                            Export files use OCCI JSON format v<?php echo self::FORMAT_VERSION; ?>. Files are not
                            compatible with other sacramental software. Keep export files confidential;
                            they contain personally identifiable canonical information.
                            Do not transmit by unencrypted email.
                        </p>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    // =========================================================================
    // Export
    // =========================================================================

    public static function handle_export() {
        if ( ! current_user_can( 'occi_view_records' ) || ! check_admin_referer( 'occi_export', 'occi_nonce' ) ) {
            wp_die( 'Access denied.' );
        }
        $parish_id    = intval( $_POST['export_parish_id'] ?? 0 );
        $export_types = array_map( 'sanitize_key', (array) ( $_POST['export_types'] ?? [] ) );
        if ( empty( $export_types ) ) {
            wp_die( 'Please select at least one record type to export.' );
        }

        global $wpdb;
        $parish_label = 'All Parishes';
        if ( $parish_id ) {
            $p = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occi_parishes WHERE id = %d", $parish_id ) );
            if ( $p ) $parish_label = $p->name . ', ' . $p->city . ', ' . $p->state;
        }

        $data = [
            'occi_export' => [
                'format_version' => self::FORMAT_VERSION,
                'plugin_version' => OCCI_SR_VERSION,
                'exported_at'    => current_time( 'c' ),
                'exported_by'    => $parish_label,
                'parish_filter'  => $parish_id ?: 'all',
            ],
        ];

        foreach ( $export_types as $type ) {
            $data[ $type ] = self::export_register( $type, $parish_id );
        }

        // Set record counts in meta
        $counts = [];
        foreach ( $export_types as $type ) {
            $counts[ $type ] = count( $data[ $type ] ?? [] );
        }
        $data['occi_export']['record_counts'] = $counts;

        $filename = 'occi-export-' . sanitize_file_name( strtolower( str_replace( [ ',', ' ', '/' ], [ '', '-', '-' ], $parish_label ) ) ) . '-' . date( 'Y-m-d' ) . '.json';

        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    private static function export_register( $type, $parish_id ) {
        global $wpdb;
        $where = $parish_id ? $wpdb->prepare( 'AND t.parish_id = %d', $parish_id ) : '';

        switch ( $type ) {
            case 'baptisms':
                $rows = $wpdb->get_results(
                    "SELECT t.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
                     FROM {$wpdb->prefix}occi_baptisms t
                     LEFT JOIN {$wpdb->prefix}occi_parishes p ON t.parish_id = p.id
                     WHERE 1=1 $where ORDER BY t.baptism_date ASC"
                );
                break;
            case 'confirmations':
                $rows = $wpdb->get_results(
                    "SELECT t.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
                     FROM {$wpdb->prefix}occi_confirmations t
                     LEFT JOIN {$wpdb->prefix}occi_parishes p ON t.parish_id = p.id
                     WHERE 1=1 $where ORDER BY t.confirmation_date ASC"
                );
                break;
            case 'marriages':
                $rows = $wpdb->get_results(
                    "SELECT t.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
                     FROM {$wpdb->prefix}occi_marriages t
                     LEFT JOIN {$wpdb->prefix}occi_parishes p ON t.parish_id = p.id
                     WHERE 1=1 $where ORDER BY t.marriage_date ASC"
                );
                break;
            case 'deaths':
                $rows = $wpdb->get_results(
                    "SELECT t.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
                     FROM {$wpdb->prefix}occi_deaths t
                     LEFT JOIN {$wpdb->prefix}occi_parishes p ON t.parish_id = p.id
                     WHERE 1=1 $where ORDER BY t.death_date ASC"
                );
                break;
            case 'communions':
                $rows = $wpdb->get_results(
                    "SELECT t.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
                     FROM {$wpdb->prefix}occi_communions t
                     LEFT JOIN {$wpdb->prefix}occi_parishes p ON t.parish_id = p.id
                     WHERE 1=1 $where ORDER BY t.communion_date ASC"
                );
                break;
            case 'ordinations':
                $rows = $wpdb->get_results(
                    "SELECT t.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
                     FROM {$wpdb->prefix}occi_ordinations t
                     LEFT JOIN {$wpdb->prefix}occi_parishes p ON t.parish_id = p.id
                     WHERE 1=1 $where ORDER BY t.ordination_date ASC"
                );
                break;
            default:
                return [];
        }

        // Convert to plain arrays; remove internal IDs to avoid confusion on import
        $out = [];
        foreach ( $rows as $row ) {
            $arr = (array) $row;
            $arr['_original_id'] = $arr['id'];   // keep for reference only
            unset( $arr['id'], $arr['created_by'] );
            $out[] = $arr;
        }
        return $out;
    }

    // =========================================================================
    // Import
    // =========================================================================

    public static function handle_import() {
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_import', 'occi_nonce' ) ) {
            wp_die( 'Access denied.' );
        }

        if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
            wp_die( 'No file uploaded.' );
        }

        $file_content = file_get_contents( $_FILES['import_file']['tmp_name'] );
        if ( $file_content === false ) {
            wp_die( 'Could not read the uploaded file.' );
        }

        $data = json_decode( $file_content, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['occi_export'] ) ) {
            wp_die( 'Invalid file format. Please upload a valid OCCI sacramental records export file.' );
        }

        if ( ( $data['occi_export']['format_version'] ?? 0 ) !== self::FORMAT_VERSION ) {
            wp_die( 'This export file was created with an incompatible format version. Please update the plugin on both systems.' );
        }

        $results = self::process_import( $data );

        set_transient( 'occi_import_results_' . get_current_user_id(), $results, 300 );
        wp_redirect( admin_url( 'admin.php?page=occi-import-export&imported=1' ) );
        exit;
    }

    private static function process_import( array $data ) {
        $results = [
            'source'        => $data['occi_export']['exported_by'] ?? 'Unknown',
            'exported_at'   => $data['occi_export']['exported_at'] ?? '',
            'parishes_created' => 0,
            'registers'     => [],
        ];

        $register_types = [ 'baptisms', 'confirmations', 'marriages', 'deaths', 'communions', 'ordinations' ];

        foreach ( $register_types as $type ) {
            if ( ! isset( $data[ $type ] ) || ! is_array( $data[ $type ] ) ) continue;
            $method = 'import_' . $type;
            $reg_result = self::$method( $data[ $type ], $results['parishes_created'] );
            $results['parishes_created'] = $reg_result['parishes_created'];
            $results['registers'][ $type ] = [
                'label'    => ucfirst( $type ),
                'total'    => count( $data[ $type ] ),
                'imported' => $reg_result['imported'],
                'skipped'  => $reg_result['skipped'],
                'errors'   => $reg_result['errors'],
            ];
        }

        return $results;
    }

    // =========================================================================
    // Per-register import handlers
    // =========================================================================

    private static function import_baptisms( array $records, int $parishes_created ): array {
        global $wpdb;
        $imported = 0; $skipped = 0; $errors = [];

        foreach ( $records as $r ) {
            if ( empty( $r['first_name'] ) || empty( $r['last_name'] ) || empty( $r['baptism_date'] ) ) {
                $errors[] = 'Skipped malformed baptism record (missing required fields).';
                continue;
            }
            if ( self::baptism_exists( $r ) ) { $skipped++; continue; }

            $parish_id = self::find_or_create_parish( $r['parish_name'] ?? '', $r['parish_city'] ?? '', $r['parish_state'] ?? '', $parishes_created );

            $insert = [
                'baptism_date'       => sanitize_text_field( $r['baptism_date'] ?? '' ),
                'birth_date'         => sanitize_text_field( $r['birth_date'] ?? '' ) ?: null,
                'birth_place'        => sanitize_text_field( $r['birth_place'] ?? '' ),
                'first_name'         => sanitize_text_field( $r['first_name'] ),
                'middle_name'        => sanitize_text_field( $r['middle_name'] ?? '' ),
                'last_name'          => sanitize_text_field( $r['last_name'] ),
                'father_first_name'  => sanitize_text_field( $r['father_first_name'] ?? '' ),
                'father_middle_name' => sanitize_text_field( $r['father_middle_name'] ?? '' ),
                'father_last_name'   => sanitize_text_field( $r['father_last_name'] ?? '' ),
                'mother_first_name'  => sanitize_text_field( $r['mother_first_name'] ?? '' ),
                'mother_middle_name' => sanitize_text_field( $r['mother_middle_name'] ?? '' ),
                'mother_last_name'   => sanitize_text_field( $r['mother_last_name'] ?? '' ),
                'mother_maiden_name' => sanitize_text_field( $r['mother_maiden_name'] ?? '' ),
                'sponsor1_name'      => sanitize_text_field( $r['sponsor1_name'] ?? '' ),
                'sponsor1_gender'    => sanitize_text_field( $r['sponsor1_gender'] ?? '' ),
                'sponsor1_is_proxy'  => intval( $r['sponsor1_is_proxy'] ?? 0 ),
                'sponsor1_proxy_for' => sanitize_text_field( $r['sponsor1_proxy_for'] ?? '' ),
                'sponsor2_name'      => sanitize_text_field( $r['sponsor2_name'] ?? '' ),
                'sponsor2_gender'    => sanitize_text_field( $r['sponsor2_gender'] ?? '' ),
                'sponsor2_is_proxy'  => intval( $r['sponsor2_is_proxy'] ?? 0 ),
                'sponsor2_proxy_for' => sanitize_text_field( $r['sponsor2_proxy_for'] ?? '' ),
                'minister_name'      => sanitize_text_field( $r['minister_name'] ?? '' ),
                'minister_type'      => sanitize_text_field( $r['minister_type'] ?? '' ),
                'parish_id'          => $parish_id,
                'alt_location'       => sanitize_text_field( $r['alt_location'] ?? '' ),
                'notations'          => sanitize_textarea_field( $r['notations'] ?? '' ),
                'is_confidential'    => intval( $r['is_confidential'] ?? 0 ),
                'record_book'        => sanitize_text_field( $r['record_book'] ?? '' ),
                'page_number'        => sanitize_text_field( $r['page_number'] ?? '' ),
            ];
            $ok = $wpdb->insert( "{$wpdb->prefix}occi_baptisms", $insert );
            if ( $ok ) { $imported++; } else { $errors[] = 'DB error inserting baptism: ' . $r['first_name'] . ' ' . $r['last_name']; }
        }
        return compact( 'imported', 'skipped', 'errors', 'parishes_created' );
    }

    private static function import_confirmations( array $records, int $parishes_created ): array {
        global $wpdb;
        $imported = 0; $skipped = 0; $errors = [];

        foreach ( $records as $r ) {
            if ( empty( $r['first_name'] ) || empty( $r['last_name'] ) || empty( $r['confirmation_date'] ) ) {
                $errors[] = 'Skipped malformed confirmation record.'; continue;
            }
            if ( self::confirmation_exists( $r ) ) { $skipped++; continue; }

            $parish_id = self::find_or_create_parish( $r['parish_name'] ?? '', $r['parish_city'] ?? '', $r['parish_state'] ?? '', $parishes_created );

            $insert = [
                'confirmation_date' => sanitize_text_field( $r['confirmation_date'] ),
                'bishop_name'       => sanitize_text_field( $r['bishop_name'] ?? '' ),
                'first_name'        => sanitize_text_field( $r['first_name'] ),
                'middle_name'       => sanitize_text_field( $r['middle_name'] ?? '' ),
                'last_name'         => sanitize_text_field( $r['last_name'] ),
                'saints_name'       => sanitize_text_field( $r['saints_name'] ?? '' ),
                'parish_id'         => $parish_id,
                'alt_location'      => sanitize_text_field( $r['alt_location'] ?? '' ),
                'notations'         => sanitize_textarea_field( $r['notations'] ?? '' ),
            ];
            $ok = $wpdb->insert( "{$wpdb->prefix}occi_confirmations", $insert );
            if ( $ok ) { $imported++; } else { $errors[] = 'DB error inserting confirmation: ' . $r['first_name'] . ' ' . $r['last_name']; }
        }
        return compact( 'imported', 'skipped', 'errors', 'parishes_created' );
    }

    private static function import_marriages( array $records, int $parishes_created ): array {
        global $wpdb;
        $imported = 0; $skipped = 0; $errors = [];

        foreach ( $records as $r ) {
            if ( empty( $r['party1_last_name'] ) || empty( $r['party2_last_name'] ) || empty( $r['marriage_date'] ) ) {
                $errors[] = 'Skipped malformed marriage record.'; continue;
            }
            if ( self::marriage_exists( $r ) ) { $skipped++; continue; }

            $parish_id = self::find_or_create_parish( $r['parish_name'] ?? '', $r['parish_city'] ?? '', $r['parish_state'] ?? '', $parishes_created );

            $insert = [
                'marriage_date'      => sanitize_text_field( $r['marriage_date'] ),
                'party1_first_name'  => sanitize_text_field( $r['party1_first_name'] ?? '' ),
                'party1_middle_name' => sanitize_text_field( $r['party1_middle_name'] ?? '' ),
                'party1_last_name'   => sanitize_text_field( $r['party1_last_name'] ),
                'party1_maiden_name' => sanitize_text_field( $r['party1_maiden_name'] ?? '' ),
                'party1_birth_date'  => sanitize_text_field( $r['party1_birth_date'] ?? '' ) ?: null,
                'party2_first_name'  => sanitize_text_field( $r['party2_first_name'] ?? '' ),
                'party2_middle_name' => sanitize_text_field( $r['party2_middle_name'] ?? '' ),
                'party2_last_name'   => sanitize_text_field( $r['party2_last_name'] ),
                'party2_maiden_name' => sanitize_text_field( $r['party2_maiden_name'] ?? '' ),
                'party2_birth_date'  => sanitize_text_field( $r['party2_birth_date'] ?? '' ) ?: null,
                'witness1_name'      => sanitize_text_field( $r['witness1_name'] ?? '' ),
                'witness2_name'      => sanitize_text_field( $r['witness2_name'] ?? '' ),
                'minister_name'      => sanitize_text_field( $r['minister_name'] ?? '' ),
                'parish_id'          => $parish_id,
                'alt_location'       => sanitize_text_field( $r['alt_location'] ?? '' ),
                'notations'          => sanitize_textarea_field( $r['notations'] ?? '' ),
            ];
            $ok = $wpdb->insert( "{$wpdb->prefix}occi_marriages", $insert );
            if ( $ok ) { $imported++; } else { $errors[] = 'DB error inserting marriage: ' . $r['party1_last_name'] . ' / ' . $r['party2_last_name']; }
        }
        return compact( 'imported', 'skipped', 'errors', 'parishes_created' );
    }

    private static function import_deaths( array $records, int $parishes_created ): array {
        global $wpdb;
        $imported = 0; $skipped = 0; $errors = [];

        foreach ( $records as $r ) {
            if ( empty( $r['first_name'] ) || empty( $r['last_name'] ) || empty( $r['death_date'] ) ) {
                $errors[] = 'Skipped malformed death record.'; continue;
            }
            if ( self::death_exists( $r ) ) { $skipped++; continue; }

            $parish_id = self::find_or_create_parish( $r['parish_name'] ?? '', $r['parish_city'] ?? '', $r['parish_state'] ?? '', $parishes_created );

            $insert = [
                'death_date'            => sanitize_text_field( $r['death_date'] ),
                'first_name'            => sanitize_text_field( $r['first_name'] ),
                'middle_name'           => sanitize_text_field( $r['middle_name'] ?? '' ),
                'last_name'             => sanitize_text_field( $r['last_name'] ),
                'burial_location'       => sanitize_text_field( $r['burial_location'] ?? '' ),
                'burial_city'           => sanitize_text_field( $r['burial_city'] ?? '' ),
                'burial_state'          => sanitize_text_field( $r['burial_state'] ?? '' ),
                'funeral_date'          => sanitize_text_field( $r['funeral_date'] ?? '' ) ?: null,
                'funeral_presider'      => sanitize_text_field( $r['funeral_presider'] ?? '' ),
                'parish_id'             => $parish_id,
                'is_graveside'          => intval( $r['is_graveside'] ?? 0 ),
                'cemetery_name'         => sanitize_text_field( $r['cemetery_name'] ?? '' ),
                'cemetery_city'         => sanitize_text_field( $r['cemetery_city'] ?? '' ),
                'cemetery_state'        => sanitize_text_field( $r['cemetery_state'] ?? '' ),
                'is_cremated'           => intval( $r['is_cremated'] ?? 0 ),
                'ashes_interment_date'  => sanitize_text_field( $r['ashes_interment_date'] ?? '' ) ?: null,
                'ashes_interment_place' => sanitize_text_field( $r['ashes_interment_place'] ?? '' ),
                'notations'             => sanitize_textarea_field( $r['notations'] ?? '' ),
            ];
            $ok = $wpdb->insert( "{$wpdb->prefix}occi_deaths", $insert );
            if ( $ok ) { $imported++; } else { $errors[] = 'DB error inserting death: ' . $r['first_name'] . ' ' . $r['last_name']; }
        }
        return compact( 'imported', 'skipped', 'errors', 'parishes_created' );
    }

    private static function import_communions( array $records, int $parishes_created ): array {
        global $wpdb;
        $imported = 0; $skipped = 0; $errors = [];

        foreach ( $records as $r ) {
            if ( empty( $r['first_name'] ) || empty( $r['last_name'] ) || empty( $r['communion_date'] ) ) {
                $errors[] = 'Skipped malformed First Communion record.'; continue;
            }
            if ( self::communion_exists( $r ) ) { $skipped++; continue; }

            $parish_id = self::find_or_create_parish( $r['parish_name'] ?? '', $r['parish_city'] ?? '', $r['parish_state'] ?? '', $parishes_created );

            $insert = [
                'communion_date'  => sanitize_text_field( $r['communion_date'] ),
                'first_name'      => sanitize_text_field( $r['first_name'] ),
                'middle_name'     => sanitize_text_field( $r['middle_name'] ?? '' ),
                'last_name'       => sanitize_text_field( $r['last_name'] ),
                'baptism_date'    => sanitize_text_field( $r['baptism_date'] ?? '' ) ?: null,
                'baptism_church'  => sanitize_text_field( $r['baptism_church'] ?? '' ),
                'baptism_city'    => sanitize_text_field( $r['baptism_city'] ?? '' ),
                'baptism_state'   => sanitize_text_field( $r['baptism_state'] ?? '' ),
                'presider'        => sanitize_text_field( $r['presider'] ?? '' ),
                'parish_id'       => $parish_id,
                'notations'       => sanitize_textarea_field( $r['notations'] ?? '' ),
            ];
            $ok = $wpdb->insert( "{$wpdb->prefix}occi_communions", $insert );
            if ( $ok ) { $imported++; } else { $errors[] = 'DB error inserting First Communion: ' . $r['first_name'] . ' ' . $r['last_name']; }
        }
        return compact( 'imported', 'skipped', 'errors', 'parishes_created' );
    }

    private static function import_ordinations( array $records, int $parishes_created ): array {
        global $wpdb;
        $imported = 0; $skipped = 0; $errors = [];

        foreach ( $records as $r ) {
            if ( empty( $r['first_name'] ) || empty( $r['last_name'] ) || empty( $r['ordination_date'] ) ) {
                $errors[] = 'Skipped malformed ordination record.'; continue;
            }
            if ( self::ordination_exists( $r ) ) { $skipped++; continue; }

            $parish_id = self::find_or_create_parish( $r['parish_name'] ?? '', $r['parish_city'] ?? '', $r['parish_state'] ?? '', $parishes_created );

            $insert = [
                'ordination_date'  => sanitize_text_field( $r['ordination_date'] ),
                'first_name'       => sanitize_text_field( $r['first_name'] ),
                'middle_name'      => sanitize_text_field( $r['middle_name'] ?? '' ),
                'last_name'        => sanitize_text_field( $r['last_name'] ),
                'ordination_rank'  => sanitize_text_field( $r['ordination_rank'] ?? '' ),
                'presiding_bishop' => sanitize_text_field( $r['presiding_bishop'] ?? '' ),
                'co_consecrator1'  => sanitize_text_field( $r['co_consecrator1'] ?? '' ),
                'co_consecrator2'  => sanitize_text_field( $r['co_consecrator2'] ?? '' ),
                'co_consecrator3'  => sanitize_text_field( $r['co_consecrator3'] ?? '' ),
                'parish_id'        => $parish_id,
                'alt_location'     => sanitize_text_field( $r['alt_location'] ?? '' ),
                'notations'        => sanitize_textarea_field( $r['notations'] ?? '' ),
            ];
            $ok = $wpdb->insert( "{$wpdb->prefix}occi_ordinations", $insert );
            if ( $ok ) { $imported++; } else { $errors[] = 'DB error inserting ordination: ' . $r['first_name'] . ' ' . $r['last_name']; }
        }
        return compact( 'imported', 'skipped', 'errors', 'parishes_created' );
    }

    // =========================================================================
    // Duplicate detection
    // Each check uses name + sacrament date as the base uniqueness key.
    // Birth date is added to the baptism check to distinguish same-name individuals.
    // =========================================================================

    private static function baptism_exists( array $r ): bool {
        global $wpdb;
        // Base key: name + baptism date
        $sql  = "SELECT id FROM {$wpdb->prefix}occi_baptisms WHERE first_name = %s AND last_name = %s AND baptism_date = %s";
        $args = [ $r['first_name'], $r['last_name'], $r['baptism_date'] ];
        // If birth date is present in the incoming record, add it to the check.
        // This means two people named John Smith baptized on the same day but born
        // on different days are treated as different people.
        if ( ! empty( $r['birth_date'] ) ) {
            $sql  .= ' AND birth_date = %s';
            $args[] = $r['birth_date'];
        }
        return (bool) $wpdb->get_var( $wpdb->prepare( $sql . ' LIMIT 1', $args ) );
    }

    private static function confirmation_exists( array $r ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}occi_confirmations
             WHERE first_name = %s AND last_name = %s AND confirmation_date = %s LIMIT 1",
            $r['first_name'], $r['last_name'], $r['confirmation_date']
        ) );
    }

    private static function marriage_exists( array $r ): bool {
        global $wpdb;
        // Check both party orderings so A+B and B+A are not double-inserted
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}occi_marriages WHERE marriage_date = %s
             AND ((party1_last_name = %s AND party1_first_name = %s AND party2_last_name = %s AND party2_first_name = %s)
               OR (party1_last_name = %s AND party1_first_name = %s AND party2_last_name = %s AND party2_first_name = %s))
             LIMIT 1",
            $r['marriage_date'],
            $r['party1_last_name'], $r['party1_first_name'] ?? '', $r['party2_last_name'], $r['party2_first_name'] ?? '',
            $r['party2_last_name'], $r['party2_first_name'] ?? '', $r['party1_last_name'], $r['party1_first_name'] ?? ''
        ) );
    }

    private static function death_exists( array $r ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}occi_deaths
             WHERE first_name = %s AND last_name = %s AND death_date = %s LIMIT 1",
            $r['first_name'], $r['last_name'], $r['death_date']
        ) );
    }

    private static function communion_exists( array $r ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}occi_communions
             WHERE first_name = %s AND last_name = %s AND communion_date = %s LIMIT 1",
            $r['first_name'], $r['last_name'], $r['communion_date']
        ) );
    }

    private static function ordination_exists( array $r ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}occi_ordinations
             WHERE first_name = %s AND last_name = %s AND ordination_date = %s LIMIT 1",
            $r['first_name'], $r['last_name'], $r['ordination_date']
        ) );
    }

    // =========================================================================
    // Parish matching: find by name+city+state, or create
    // =========================================================================

    private static function find_or_create_parish( string $name, string $city, string $state, int &$parishes_created ): ?int {
        global $wpdb;
        if ( ! $name ) return null;
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}occi_parishes WHERE name = %s AND city = %s AND state = %s LIMIT 1",
            $name, $city, $state
        ) );
        if ( $existing ) return intval( $existing );
        // Create it
        $wpdb->insert( "{$wpdb->prefix}occi_parishes", [
            'name'  => sanitize_text_field( $name ),
            'city'  => sanitize_text_field( $city ),
            'state' => sanitize_text_field( $state ),
        ] );
        $parishes_created++;
        return $wpdb->insert_id ?: null;
    }

    // =========================================================================
    // Import results display
    // =========================================================================

    private static function render_import_results( array $results ) {
        $total_imported = array_sum( array_column( $results['registers'], 'imported' ) );
        $total_skipped  = array_sum( array_column( $results['registers'], 'skipped' ) );
        $all_errors     = array_merge( ...array_column( $results['registers'], 'errors' ) );
        ?>
        <div class="occi-section" style="border-top-color: #2e7d32; margin-bottom: 24px;">
            <h2 style="color: #2e7d32;">&#10003; Import Complete</h2>
            <p>
                <strong>Source:</strong> <?php echo esc_html( $results['source'] ); ?>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong>Exported:</strong> <?php echo esc_html( $results['exported_at'] ); ?>
            </p>
            <p>
                <strong><?php echo intval( $total_imported ); ?></strong> records imported &nbsp;&nbsp;
                <strong><?php echo intval( $total_skipped ); ?></strong> records skipped (already exist) &nbsp;&nbsp;
                <strong><?php echo intval( $results['parishes_created'] ); ?></strong> new parish(es) created
            </p>
            <table class="wp-list-table widefat fixed striped" style="max-width: 600px; margin-top: 12px;">
                <thead><tr>
                    <th>Register</th><th>In File</th><th>Imported</th><th>Skipped</th><th>Errors</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $results['registers'] as $key => $reg ) : ?>
                <tr>
                    <td><?php echo esc_html( $reg['label'] ); ?></td>
                    <td><?php echo intval( $reg['total'] ); ?></td>
                    <td style="color: #2e7d32; font-weight: 600;"><?php echo intval( $reg['imported'] ); ?></td>
                    <td style="color: #888;"><?php echo intval( $reg['skipped'] ); ?></td>
                    <td style="color: <?php echo $reg['errors'] ? '#c0392b' : '#888'; ?>">
                        <?php echo intval( count( $reg['errors'] ) ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ( $all_errors ) : ?>
            <details style="margin-top: 12px;">
                <summary style="cursor: pointer; color: #c0392b;">
                    &#9888; <?php echo count( $all_errors ); ?> error(s) &mdash; click to expand
                </summary>
                <ul style="margin-top: 8px; color: #c0392b;">
                    <?php foreach ( $all_errors as $err ) : ?>
                    <li><?php echo esc_html( $err ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </div>
        <?php
    }
}
