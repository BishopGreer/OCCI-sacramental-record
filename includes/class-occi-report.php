<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Report {

    public static function init() {
        add_action( 'admin_post_occi_print_report', [ __CLASS__, 'print_report' ] );
    }

    // -------------------------------------------------------------------------
    // Admin search page
    // -------------------------------------------------------------------------

    public static function page() {
        if ( ! current_user_can( 'occi_view_records' ) ) { wp_die( 'Access denied.' ); }

        $first = sanitize_text_field( $_GET['first'] ?? '' );
        $last  = sanitize_text_field( $_GET['last']  ?? '' );
        $results = ( $first || $last ) ? self::search_person( $first, $last ) : null;
        ?>
        <div class="wrap occi-wrap">
            <h1>Person Sacramental Report</h1>
            <p>Search all registers for all sacramental records associated with an individual. The resulting report may be printed or sent to a requesting parish.</p>

            <form method="get" class="occi-search-form" style="margin-bottom:20px;">
                <input type="hidden" name="page" value="occi-report">
                <input type="text" name="first" value="<?php echo esc_attr( $first ); ?>" placeholder="First name" class="regular-text">
                <input type="text" name="last"  value="<?php echo esc_attr( $last );  ?>" placeholder="Last name"  class="regular-text">
                <button type="submit" class="button button-primary">Search All Registers</button>
                <?php if ( $results !== null ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-report' ) ); ?>" class="button">Reset</a>
                <?php endif; ?>
            </form>

            <?php if ( $results === null ) : ?>
            <div class="occi-notice">
                <p>Enter a first name, last name, or both to search across all six sacramental registers simultaneously.</p>
            </div>

            <?php elseif ( self::results_are_empty( $results ) ) : ?>
            <div class="notice notice-warning"><p>No records found for the name entered. Try a partial name or check the spelling.</p></div>

            <?php else : ?>

            <div style="margin-bottom:16px; display:flex; gap:10px; align-items:center;">
                <strong><?php echo self::count_total( $results ); ?> record(s) found across <?php echo self::count_registers( $results ); ?> register(s).</strong>
                <?php
                $print_url = wp_nonce_url(
                    admin_url( 'admin-post.php?action=occi_print_report&first=' . urlencode( $first ) . '&last=' . urlencode( $last ) ),
                    'occi_print_report', 'occi_nonce'
                );
                ?>
                <a href="<?php echo esc_url( $print_url ); ?>" target="_blank" class="button button-primary">&#128438; Print / Save Report</a>
            </div>

            <?php self::render_results( $results, $first, $last ); ?>

            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Search all registers
    // -------------------------------------------------------------------------

    public static function search_person( $first, $last ) {
        global $wpdb;
        $results = [];

        // Build LIKE clauses
        $like_first = $first ? '%' . $wpdb->esc_like( $first ) . '%' : null;
        $like_last  = $last  ? '%' . $wpdb->esc_like( $last )  . '%' : null;

        // BAPTISMS
        $where = self::name_where( 'b', 'first_name', 'last_name', $like_first, $like_last );
        $results['baptisms'] = $wpdb->get_results(
            "SELECT b.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_baptisms b
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON b.parish_id = p.id
             WHERE $where ORDER BY b.baptism_date ASC"
        );

        // CONFIRMATIONS
        $where = self::name_where( 'c', 'first_name', 'last_name', $like_first, $like_last );
        $results['confirmations'] = $wpdb->get_results(
            "SELECT c.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_confirmations c
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON c.parish_id = p.id
             WHERE $where ORDER BY c.confirmation_date ASC"
        );

        // MARRIAGES (search both parties)
        $where = self::marriage_where( $like_first, $like_last );
        $results['marriages'] = $wpdb->get_results(
            "SELECT m.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_marriages m
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON m.parish_id = p.id
             WHERE $where ORDER BY m.marriage_date ASC"
        );

        // DEATHS
        $where = self::name_where( 'd', 'first_name', 'last_name', $like_first, $like_last );
        $results['deaths'] = $wpdb->get_results(
            "SELECT d.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_deaths d
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON d.parish_id = p.id
             WHERE $where ORDER BY d.death_date ASC"
        );

        // COMMUNIONS
        $where = self::name_where( 'c', 'first_name', 'last_name', $like_first, $like_last );
        $results['communions'] = $wpdb->get_results(
            "SELECT c.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_communions c
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON c.parish_id = p.id
             WHERE $where ORDER BY c.communion_date ASC"
        );

        // ORDINATIONS
        $where = self::name_where( 'o', 'first_name', 'last_name', $like_first, $like_last );
        $results['ordinations'] = $wpdb->get_results(
            "SELECT o.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_ordinations o
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON o.parish_id = p.id
             WHERE $where ORDER BY o.ordination_date ASC"
        );

        return $results;
    }

    private static function name_where( $alias, $fn_col, $ln_col, $like_first, $like_last ) {
        global $wpdb;
        $parts = [];
        if ( $like_first ) $parts[] = $wpdb->prepare( "$alias.$fn_col LIKE %s", $like_first );
        if ( $like_last )  $parts[] = $wpdb->prepare( "$alias.$ln_col LIKE %s", $like_last );
        return $parts ? implode( ' AND ', $parts ) : '1=1';
    }

    private static function marriage_where( $like_first, $like_last ) {
        global $wpdb;
        if ( $like_first && $like_last ) {
            return $wpdb->prepare(
                "(m.party1_first_name LIKE %s AND m.party1_last_name LIKE %s) OR (m.party2_first_name LIKE %s AND m.party2_last_name LIKE %s)",
                $like_first, $like_last, $like_first, $like_last
            );
        } elseif ( $like_last ) {
            return $wpdb->prepare( "m.party1_last_name LIKE %s OR m.party2_last_name LIKE %s", $like_last, $like_last );
        } elseif ( $like_first ) {
            return $wpdb->prepare( "m.party1_first_name LIKE %s OR m.party2_first_name LIKE %s", $like_first, $like_first );
        }
        return '1=1';
    }

    private static function results_are_empty( $results ) {
        foreach ( $results as $set ) { if ( $set ) return false; }
        return true;
    }

    private static function count_total( $results ) {
        return array_sum( array_map( 'count', $results ) );
    }

    private static function count_registers( $results ) {
        return count( array_filter( $results ) );
    }

    // -------------------------------------------------------------------------
    // Render results in admin
    // -------------------------------------------------------------------------

    private static function render_results( $results, $first, $last ) {
        $sections = [
            'baptisms'      => [ 'Baptisms',            'occi-baptisms',      'baptism' ],
            'confirmations' => [ 'Confirmations',        'occi-confirmations', 'confirmation' ],
            'marriages'     => [ 'Marriages',            'occi-marriages',     'marriage' ],
            'deaths'        => [ 'Deaths',               'occi-deaths',        'death' ],
            'communions'    => [ 'First Communions',     'occi-communions',    'communion' ],
            'ordinations'   => [ 'Ordinations',          'occi-ordinations',   'ordination' ],
        ];
        foreach ( $sections as $key => [ $label, $page_slug, $cert_type ] ) {
            if ( empty( $results[ $key ] ) ) continue;
            echo '<div class="occi-section">';
            echo '<h2>' . esc_html( $label ) . ' (' . count( $results[ $key ] ) . ')</h2>';
            echo '<table class="wp-list-table widefat fixed striped occi-register-table">';
            echo '<thead><tr><th>Date</th><th>Name</th><th>Details</th><th>Parish</th><th>Actions</th></tr></thead><tbody>';
            foreach ( $results[ $key ] as $r ) {
                $date   = '';
                $name   = '';
                $detail = '';
                switch ( $key ) {
                    case 'baptisms':
                        $date   = occi_format_date( $r->baptism_date );
                        $name   = strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' );
                        $detail = 'Minister: ' . $r->minister_name;
                        break;
                    case 'confirmations':
                        $date   = occi_format_date( $r->confirmation_date );
                        $name   = strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' );
                        $detail = 'Bishop: ' . $r->bishop_name . ( $r->saints_name ? ' | Saint\'s Name: ' . $r->saints_name : '' );
                        break;
                    case 'marriages':
                        $date   = occi_format_date( $r->marriage_date );
                        $name   = strtoupper( $r->party1_last_name ) . ', ' . $r->party1_first_name . ' &amp; ' . strtoupper( $r->party2_last_name ) . ', ' . $r->party2_first_name;
                        $detail = 'Minister: ' . $r->minister_name;
                        break;
                    case 'deaths':
                        $date   = occi_format_date( $r->death_date );
                        $name   = strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' );
                        $detail = $r->funeral_presider ? 'Presider: ' . $r->funeral_presider : '';
                        break;
                    case 'communions':
                        $date   = occi_format_date( $r->communion_date );
                        $name   = strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' );
                        $detail = 'Presider: ' . $r->presider;
                        break;
                    case 'ordinations':
                        $date   = occi_format_date( $r->ordination_date );
                        $name   = strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' );
                        $detail = 'Rank: ' . $r->ordination_rank . ' | Bishop: ' . $r->presiding_bishop;
                        break;
                }
                $parish = $r->parish_name ? $r->parish_name . ', ' . $r->parish_city : '';
                echo '<tr>';
                echo '<td>' . esc_html( $date ) . '</td>';
                echo '<td><strong>' . esc_html( $name ) . '</strong></td>';
                echo '<td class="occi-small">' . esc_html( $detail ) . '</td>';
                echo '<td class="occi-small">' . esc_html( $parish ?: '&mdash;' ) . '</td>';
                echo '<td class="occi-actions">';
                echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $page_slug . '&action=view&id=' . $r->id ) ) . '">View</a>';
                echo ' | ' . OCCI_Certificates::certificate_button( $cert_type, $r->id );
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
    }

    // -------------------------------------------------------------------------
    // Print report — standalone HTML document
    // -------------------------------------------------------------------------

    public static function print_report() {
        if ( ! current_user_can( 'occi_view_records' ) || ! check_admin_referer( 'occi_print_report', 'occi_nonce' ) ) {
            wp_die( 'Access denied.' );
        }
        $first   = sanitize_text_field( $_GET['first'] ?? '' );
        $last    = sanitize_text_field( $_GET['last']  ?? '' );
        $results = self::search_person( $first, $last );
        $issued  = date( 'F j, Y' );
        $font    = OCCI_Certificates::get_font();
        $logo    = OCCI_SR_PLUGIN_URL . 'assets/images/certificate-template.png';

        header( 'Content-Type: text/html; charset=UTF-8' );
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sacramental Report &mdash; <?php echo esc_html( trim( $first . ' ' . $last ) ?: 'OCCI' ); ?></title>
<style>
* { box-sizing: border-box; }
@page { size: 8.5in 11in portrait; margin: 0.75in 0.85in; }
body {
    font-family: '<?php echo esc_js( $font ); ?>', Palatino, Georgia, serif;
    font-size: 10.5pt;
    color: #1a0a0a;
    line-height: 1.5;
    margin: 0;
    padding: 0;
}
.report-header {
    border-bottom: 2pt solid #B8860B;
    margin-bottom: 18pt;
    padding-bottom: 10pt;
    display: flex;
    align-items: center;
    gap: 16pt;
}
.report-logo {
    width: 60pt;
    height: 60pt;
    object-fit: contain;
}
.report-title-block h1 {
    font-size: 15pt;
    color: #6B1A2A;
    margin: 0 0 2pt;
    letter-spacing: 0.04em;
}
.report-title-block p {
    font-size: 9pt;
    color: #666;
    margin: 0;
    font-style: italic;
}
.report-subject {
    background: #FDF9F0;
    border: 1pt solid #B8860B;
    border-radius: 3pt;
    padding: 8pt 12pt;
    margin-bottom: 14pt;
    font-size: 10pt;
}
.report-subject strong { color: #6B1A2A; }
.sacrament-section {
    margin-bottom: 16pt;
    page-break-inside: avoid;
}
.sacrament-section h2 {
    font-size: 11pt;
    font-variant: small-caps;
    letter-spacing: 0.06em;
    color: #6B1A2A;
    border-bottom: 1pt solid #B8860B;
    padding-bottom: 3pt;
    margin: 0 0 6pt;
}
.record-block {
    border: 1pt solid #ddd;
    border-left: 3pt solid #6B1A2A;
    padding: 6pt 10pt;
    margin-bottom: 8pt;
    page-break-inside: avoid;
}
.record-table { width: 100%; border-collapse: collapse; }
.record-table th {
    text-align: left;
    width: 30%;
    font-weight: bold;
    color: #6B1A2A;
    padding: 2pt 6pt 2pt 0;
    vertical-align: top;
    font-size: 9.5pt;
}
.record-table td {
    padding: 2pt 0;
    vertical-align: top;
    font-size: 10pt;
}
.record-name {
    font-size: 11pt;
    font-weight: bold;
    margin-bottom: 4pt;
}
.record-notations {
    font-style: italic;
    color: #444;
    font-size: 9pt;
    margin-top: 4pt;
    border-top: 0.5pt solid #eee;
    padding-top: 3pt;
}
.no-records {
    color: #888;
    font-style: italic;
    text-align: center;
    padding: 10pt;
    font-size: 9.5pt;
}
.report-footer {
    border-top: 1pt solid #B8860B;
    margin-top: 20pt;
    padding-top: 10pt;
    font-size: 8.5pt;
    color: #666;
    display: flex;
    justify-content: space-between;
}
.report-sig-area {
    margin-top: 30pt;
    display: flex;
    justify-content: space-between;
    page-break-inside: avoid;
}
.report-sig-block { width: 42%; text-align: center; }
.report-sig-line { border-top: 1pt solid #333; margin-bottom: 4pt; margin-top: 30pt; }
.report-sig-label { font-size: 8.5pt; color: #444; }
@media print {
    .no-print { display: none !important; }
    body { margin: 0; }
    .sacrament-section { page-break-inside: avoid; }
}
</style>
</head>
<body>

<div class="no-print" style="position:fixed;top:10px;right:10px;z-index:9999;display:flex;gap:8px;">
    <button onclick="window.print()" style="padding:8px 16px;background:#6B1A2A;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:14px;">&#128438; Print Report</button>
    <button onclick="window.close()" style="padding:8px 16px;background:#555;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:14px;">Close</button>
</div>

<div class="report-header">
    <div class="report-title-block">
        <h1>Old Catholic Churches International</h1>
        <p>Sacramental Record Report &mdash; Confidential Canonical Document</p>
        <p>Issued: <?php echo esc_html( $issued ); ?></p>
    </div>
</div>

<div class="report-subject">
    <strong>Subject:</strong> <?php echo esc_html( trim( $first . ' ' . strtoupper( $last ) ) ?: 'All Matching Records' ); ?>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Search Terms:</strong>
    <?php if ( $first ) echo 'First name: ' . esc_html( $first ) . ' '; ?>
    <?php if ( $last )  echo 'Last name: '  . esc_html( $last ); ?>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Total Records Found:</strong> <?php echo self::count_total( $results ); ?>
</div>

<?php
$sections = [
    'baptisms'      => 'Baptisms',
    'confirmations' => 'Confirmations',
    'marriages'     => 'Marriages',
    'deaths'        => 'Deaths',
    'communions'    => 'First Communions',
    'ordinations'   => 'Ordinations',
];
foreach ( $sections as $key => $label ) :
    $set = $results[ $key ] ?? [];
?>
<div class="sacrament-section">
    <h2><?php echo esc_html( $label ); ?></h2>
    <?php if ( empty( $set ) ) : ?>
    <p class="no-records">No <?php echo esc_html( strtolower( $label ) ); ?> records found for this search.</p>
    <?php else : foreach ( $set as $r ) : ?>
    <div class="record-block">
        <?php self::render_record_block( $key, $r ); ?>
    </div>
    <?php endforeach; endif; ?>
</div>
<?php endforeach; ?>

<div class="report-sig-area">
    <div class="report-sig-block">
        <div class="report-sig-line"></div>
        <div class="report-sig-label">Authorized Signatory / Pastor</div>
    </div>
    <div class="report-sig-block">
        <div class="report-sig-line"></div>
        <div class="report-sig-label">Parish Seal &amp; Date Issued</div>
    </div>
</div>

<div class="report-footer">
    <span>Old Catholic Churches International &mdash; <em>Pax et Bonum</em></span>
    <span>Confidential Canonical Document &mdash; Church Use Only</span>
</div>

</body>
</html>
        <?php
        exit;
    }

    private static function render_record_block( $type, $r ) {
        $name = '';
        $rows = [];
        switch ( $type ) {
            case 'baptisms':
                $name = strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' );
                $rows = [
                    'Date of Baptism'  => occi_format_date( $r->baptism_date ),
                    'Date of Birth'    => $r->birth_date ? occi_format_date( $r->birth_date ) : '',
                    'Place of Birth'   => $r->birth_place ?? '',
                    'Father'           => trim( $r->father_first_name . ' ' . $r->father_middle_name . ' ' . $r->father_last_name ),
                    'Mother'           => trim( $r->mother_first_name . ' ' . $r->mother_middle_name . ' ' . $r->mother_last_name ) . ( $r->mother_maiden_name ? ', née ' . strtoupper( $r->mother_maiden_name ) : '' ),
                    'Sponsor 1'        => $r->sponsor1_name ?? '',
                    'Sponsor 2'        => $r->sponsor2_name ?? '',
                    'Minister'         => $r->minister_name . ( $r->minister_type ? ', ' . $r->minister_type : '' ),
                    'Parish'           => implode( ', ', array_filter( [ $r->parish_name, $r->parish_city, $r->parish_state ] ) ),
                ];
                if ( $r->alt_location ) $rows['Alternate Location'] = $r->alt_location;
                break;
            case 'confirmations':
                $name = strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' );
                $rows = [
                    'Date of Confirmation' => occi_format_date( $r->confirmation_date ),
                    'Saint\'s Name'        => $r->saints_name ?? '',
                    'Bishop / Delegate'    => $r->bishop_name,
                    'Parish'               => implode( ', ', array_filter( [ $r->parish_name, $r->parish_city, $r->parish_state ] ) ),
                ];
                if ( $r->alt_location ) $rows['Alternate Location'] = $r->alt_location;
                break;
            case 'marriages':
                $name = strtoupper( $r->party1_last_name ) . ', ' . $r->party1_first_name . ' &amp; ' . strtoupper( $r->party2_last_name ) . ', ' . $r->party2_first_name;
                $rows = [
                    'Date of Marriage' => occi_format_date( $r->marriage_date ),
                    'Party 1'          => $r->party1_first_name . ' ' . $r->party1_last_name . ( $r->party1_birth_date ? ' (b. ' . occi_format_date( $r->party1_birth_date ) . ')' : '' ),
                    'Party 2'          => $r->party2_first_name . ' ' . $r->party2_last_name . ( $r->party2_birth_date ? ' (b. ' . occi_format_date( $r->party2_birth_date ) . ')' : '' ),
                    'Witness 1'        => $r->witness1_name,
                    'Witness 2'        => $r->witness2_name,
                    'Minister'         => $r->minister_name,
                    'Parish'           => implode( ', ', array_filter( [ $r->parish_name, $r->parish_city, $r->parish_state ] ) ),
                ];
                break;
            case 'deaths':
                $name = strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' );
                $rows = [
                    'Date of Death'  => occi_format_date( $r->death_date ),
                    'Funeral Date'   => $r->funeral_date ? occi_format_date( $r->funeral_date ) : '',
                    'Presider'       => $r->funeral_presider ?? '',
                    'Burial'         => implode( ', ', array_filter( [ $r->burial_location, $r->burial_city, $r->burial_state ] ) ),
                    'Parish'         => implode( ', ', array_filter( [ $r->parish_name, $r->parish_city, $r->parish_state ] ) ),
                ];
                if ( $r->is_cremated ) $rows['Cremation'] = 'Yes' . ( $r->ashes_interment_place ? ' — Ashes: ' . $r->ashes_interment_place : '' );
                if ( $r->is_graveside && $r->cemetery_name ) $rows['Cemetery'] = implode( ', ', array_filter( [ $r->cemetery_name, $r->cemetery_city, $r->cemetery_state ] ) );
                break;
            case 'communions':
                $name = strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' );
                $rows = [
                    'Date of Reception' => occi_format_date( $r->communion_date ),
                    'Date of Baptism'   => $r->baptism_date ? occi_format_date( $r->baptism_date ) : '',
                    'Church of Baptism' => implode( ', ', array_filter( [ $r->baptism_church, $r->baptism_city, $r->baptism_state ] ) ),
                    'Presider'          => $r->presider,
                    'Parish'            => implode( ', ', array_filter( [ $r->parish_name, $r->parish_city, $r->parish_state ] ) ),
                ];
                break;
            case 'ordinations':
                $name = strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' );
                $rows = [
                    'Date of Ordination' => occi_format_date( $r->ordination_date ),
                    'Rank'               => $r->ordination_rank,
                    'Presiding Bishop'   => $r->presiding_bishop,
                    'Parish'             => implode( ', ', array_filter( [ $r->parish_name, $r->parish_city, $r->parish_state ] ) ),
                ];
                $co = array_filter( [ $r->co_consecrator1 ?? '', $r->co_consecrator2 ?? '', $r->co_consecrator3 ?? '' ] );
                if ( $co ) $rows['Co-Consecrators'] = implode( '; ', $co );
                break;
        }
        echo '<div class="record-name">' . esc_html( $name ) . '</div>';
        echo '<table class="record-table">';
        foreach ( $rows as $label => $val ) {
            if ( ! $val ) continue;
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
        }
        echo '</table>';
        if ( ! empty( $r->notations ) ) {
            echo '<div class="record-notations"><strong>Notations:</strong> ' . nl2br( esc_html( $r->notations ) ) . '</div>';
        }
    }
}
