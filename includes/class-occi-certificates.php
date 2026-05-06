<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Certificates {

    // Content area on the 792x1056 blank certificate (percentages)
    const CONTENT_TOP    = '33%';   // below the seal
    const CONTENT_LEFT   = '13%';
    const CONTENT_WIDTH  = '74%';
    const CONTENT_BOTTOM = '7%';    // margin from bottom border

    public static function init() {
        add_action( 'admin_post_occi_print_certificate', [ __CLASS__, 'print_certificate' ] );
        add_action( 'admin_post_occi_save_cert_settings', [ __CLASS__, 'save_settings' ] );
    }

    // -------------------------------------------------------------------------
    // Settings: template image URL
    // -------------------------------------------------------------------------

    public static function settings_page() {
        if ( ! current_user_can( 'occi_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $msg = '';
        if ( isset( $_GET['saved'] ) )        $msg = '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        if ( isset( $_GET['cache_cleared'] ) ) $msg = '<div class="notice notice-success is-dismissible"><p>Update cache cleared. WordPress will check for updates on the next pass.</p></div>';
        OCCI_Updater::maybe_clear_cache();
        $template_url = get_option( 'occi_cert_template_url', '' );
        $default_url  = OCCI_SR_PLUGIN_URL . 'assets/images/certificate-template.png';
        ?>
        <div class="wrap occi-wrap">
            <h1>Certificate Settings</h1>
            <?php echo $msg; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'occi_save_cert_settings', 'occi_nonce' ); ?>
                <input type="hidden" name="action" value="occi_save_cert_settings">
                <div class="occi-section">
                    <h2>Certificate Template Image</h2>
                    <p>The certificate template is the blank background image used when printing sacramental certificates. Upload a new image to replace the default OCCI template.</p>
                    <table class="form-table">
                        <tr>
                            <th><label>Current Template</label></th>
                            <td>
                                <img src="<?php echo esc_url( $template_url ?: $default_url ); ?>" style="max-width:200px; border:1px solid #ddd; display:block; margin-bottom:10px;">
                                <p class="description">Current template preview. <?php if ( ! $template_url ) echo 'Using bundled OCCI default.'; ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cert_template_url">Template Image URL</label></th>
                            <td>
                                <input type="url" id="cert_template_url" name="cert_template_url" class="large-text" value="<?php echo esc_attr( $template_url ); ?>" placeholder="Leave blank to use the bundled OCCI template">
                                <p class="description">Enter the full URL of a certificate template image, or use the WordPress Media Library button below to upload one.</p>
                                <button type="button" class="button" id="occi-upload-cert-btn">Select / Upload via Media Library</button>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Certificate Font</h2>
                    <table class="form-table">
                        <tr>
                            <th><label>Primary Font</label></th>
                            <td>
                                <select name="cert_font">
                                    <?php
                                    $current_font = get_option( 'occi_cert_font', 'Palatino Linotype' );
                                    $fonts = [ 'Palatino Linotype', 'Georgia', 'Times New Roman', 'Garamond', 'Book Antiqua' ];
                                    foreach ( $fonts as $f ) {
                                        echo '<option value="' . esc_attr( $f ) . '"' . selected( $current_font, $f, false ) . ' style="font-family:' . esc_attr( $f ) . '">' . esc_html( $f ) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description">Serif fonts render best on ecclesiastical certificates.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php OCCI_Updater::render_settings_section(); ?>

        <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                    <?php if ( $template_url ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occi_save_cert_settings&reset=1' ), 'occi_save_cert_settings', 'occi_nonce' ) ); ?>" class="button">Reset to Default Template</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($){
            $('#occi-upload-cert-btn').on('click', function(e){
                e.preventDefault();
                var frame = wp.media({ title: 'Select Certificate Template', button: { text: 'Use this image' }, multiple: false });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#cert_template_url').val(attachment.url);
                });
                frame.open();
            });
        });
        </script>
        <?php
    }

    public static function save_settings() {
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_save_cert_settings', 'occi_nonce' ) ) { wp_die( 'Access denied.' ); }
        if ( isset( $_GET['reset'] ) ) {
            delete_option( 'occi_cert_template_url' );
        } else {
            update_option( 'occi_cert_template_url', esc_url_raw( $_POST['cert_template_url'] ?? '' ) );
            update_option( 'occi_cert_font', sanitize_text_field( $_POST['cert_font'] ?? 'Palatino Linotype' ) );
        }
        wp_redirect( admin_url( 'admin.php?page=occi-cert-settings&saved=1' ) );
        exit;
    }

    public static function get_template_url( $parish_id = 0 ) {
        // 1. Parish-specific template
        if ( $parish_id ) {
            global $wpdb;
            $parish_url = $wpdb->get_var( $wpdb->prepare(
                "SELECT cert_template_url FROM {$wpdb->prefix}occi_parishes WHERE id = %d",
                $parish_id
            ) );
            if ( $parish_url ) return $parish_url;
        }
        // 2. Global OCCI setting
        $global_url = get_option( 'occi_cert_template_url', '' );
        if ( $global_url ) return $global_url;
        // 3. Bundled default
        return OCCI_SR_PLUGIN_URL . 'assets/images/certificate-template.png';
    }

    public static function get_font() {
        return get_option( 'occi_cert_font', 'Palatino Linotype' );
    }

    // -------------------------------------------------------------------------
    // Print certificate handler — outputs standalone HTML, then dies
    // -------------------------------------------------------------------------

    public static function print_certificate() {
        if ( ! current_user_can( 'occi_view_records' ) || ! check_admin_referer( 'occi_print_certificate', 'occi_nonce' ) ) {
            wp_die( 'Access denied.' );
        }
        global $wpdb;
        $type = sanitize_key( $_GET['type'] ?? '' );
        $id   = intval( $_GET['id'] ?? 0 );
        if ( ! $type || ! $id ) { wp_die( 'Invalid request.' ); }

        $content = self::get_certificate_content( $type, $id );
        if ( ! $content ) { wp_die( 'Record not found.' ); }

        $template_url = self::get_template_url( $content['parish_id'] ?? 0 );
        $font         = self::get_font();

        // Output standalone print page
        header( 'Content-Type: text/html; charset=UTF-8' );
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( $content['title'] ); ?> &mdash; OCCI</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
@page { size: 8.5in 11in portrait; margin: 0; }
html, body { width: 8.5in; height: 11in; overflow: hidden; background: #fff; }
body { font-family: '<?php echo esc_js( $font ); ?>', Palatino, 'Book Antiqua', Georgia, serif; }

.cert-page {
    position: relative;
    width: 8.5in;
    height: 11in;
    overflow: hidden;
}
.cert-bg {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    display: block;
}
.cert-content {
    position: absolute;
    top: <?php echo self::CONTENT_TOP; ?>;
    left: <?php echo self::CONTENT_LEFT; ?>;
    width: <?php echo self::CONTENT_WIDTH; ?>;
    bottom: <?php echo self::CONTENT_BOTTOM; ?>;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    color: #1a0a0a;
    overflow: hidden;
}
.cert-type {
    font-size: 22pt;
    font-weight: bold;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #6B1A2A;
    margin-bottom: 6pt;
    border-bottom: 1pt solid #B8860B;
    padding-bottom: 4pt;
    width: 100%;
}
.cert-intro {
    font-size: 11pt;
    font-style: italic;
    margin: 10pt 0 4pt;
    color: #333;
}
.cert-name {
    font-size: 24pt;
    font-weight: bold;
    color: #1a0a0a;
    margin: 4pt 0 8pt;
    letter-spacing: 0.04em;
}
.cert-saints {
    font-size: 13pt;
    font-style: italic;
    color: #6B1A2A;
    margin-bottom: 8pt;
}
.cert-body {
    font-size: 11pt;
    line-height: 1.7;
    margin: 4pt 0;
    width: 100%;
}
.cert-body strong { color: #1a0a0a; }
.cert-detail-table {
    width: 90%;
    border-collapse: collapse;
    margin: 8pt auto;
    text-align: left;
    font-size: 10pt;
}
.cert-detail-table th {
    font-weight: bold;
    color: #6B1A2A;
    padding: 3pt 8pt 3pt 0;
    vertical-align: top;
    white-space: nowrap;
    width: 36%;
}
.cert-detail-table td {
    padding: 3pt 0;
    vertical-align: top;
}
.cert-divider {
    width: 60%;
    border: none;
    border-top: 1pt solid #B8860B;
    margin: 8pt auto;
}
.cert-notations {
    font-size: 9pt;
    font-style: italic;
    color: #444;
    width: 90%;
    text-align: left;
    margin: 4pt 0;
}
.cert-signatures {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    display: flex;
    justify-content: space-around;
    align-items: flex-end;
    padding-bottom: 6pt;
}
.cert-sig-block {
    text-align: center;
    width: 38%;
}
.cert-sig-line {
    border-top: 1pt solid #333;
    margin-bottom: 3pt;
    margin-top: 22pt;
}
.cert-sig-label {
    font-size: 8.5pt;
    color: #444;
}
.cert-issued {
    font-size: 8pt;
    color: #666;
    font-style: italic;
    position: absolute;
    bottom: 26pt;
    right: 0;
    text-align: right;
}
@media print {
    html, body { width: 8.5in; height: 11in; }
    .cert-page { page-break-after: avoid; }
    .no-print { display: none !important; }
}
</style>
</head>
<body>
<div class="cert-page">
    <img src="<?php echo esc_url( $template_url ); ?>" class="cert-bg" alt="">
    <div class="cert-content">
        <div class="cert-type"><?php echo esc_html( $content['title'] ); ?></div>
        <div class="cert-intro"><?php echo esc_html( $content['intro'] ); ?></div>
        <div class="cert-name"><?php echo esc_html( $content['name'] ); ?></div>
        <?php if ( ! empty( $content['saints_name'] ) ) : ?>
        <div class="cert-saints">in Confirmation taking the name of <?php echo esc_html( $content['saints_name'] ); ?></div>
        <?php endif; ?>
        <hr class="cert-divider">
        <table class="cert-detail-table">
            <?php foreach ( $content['details'] as $label => $value ) : ?>
            <tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr>
            <?php endforeach; ?>
        </table>
        <?php if ( ! empty( $content['notations'] ) ) : ?>
        <hr class="cert-divider">
        <div class="cert-notations"><strong>Notations:</strong> <?php echo nl2br( esc_html( $content['notations'] ) ); ?></div>
        <?php endif; ?>
        <div class="cert-issued">Issued: <?php echo esc_html( date( 'F j, Y' ) ); ?></div>
        <div class="cert-signatures">
            <div class="cert-sig-block">
                <div class="cert-sig-line"></div>
                <div class="cert-sig-label">Authorized Signatory</div>
            </div>
            <div class="cert-sig-block">
                <div class="cert-sig-line"></div>
                <div class="cert-sig-label">Parish Seal &amp; Date</div>
            </div>
        </div>
    </div>
</div>
<!-- Print controls: hidden on actual print -->
<div class="no-print" style="position:fixed;top:10px;right:10px;z-index:9999;display:flex;gap:8px;">
    <button onclick="window.print()" style="padding:8px 16px;background:#6B1A2A;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:14px;">&#128438; Print Certificate</button>
    <button onclick="window.close()" style="padding:8px 16px;background:#555;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:14px;">Close</button>
</div>
</body>
</html>
        <?php
        exit;
    }

    // -------------------------------------------------------------------------
    // Build certificate data per sacrament type
    // -------------------------------------------------------------------------

    private static function get_certificate_content( $type, $id ) {
        global $wpdb;
        switch ( $type ) {
            case 'baptism':      return self::cert_baptism( $id );
            case 'confirmation': return self::cert_confirmation( $id );
            case 'marriage':     return self::cert_marriage( $id );
            case 'death':        return self::cert_death( $id );
            case 'communion':    return self::cert_communion( $id );
            case 'ordination':   return self::cert_ordination( $id );
        }
        return null;
    }

    private static function cert_baptism( $id ) {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_baptisms b
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON b.parish_id = p.id
             WHERE b.id = %d", $id ) );
        if ( ! $r ) return null;
        $name = trim( $r->first_name . ' ' . $r->middle_name . ' ' . strtoupper( $r->last_name ) );
        $location = self::format_location( $r->parish_name, $r->parish_city, $r->parish_state, $r->alt_location );
        $details = [];
        $details['Date of Baptism']  = occi_format_date( $r->baptism_date );
        if ( $r->birth_date ) $details['Date of Birth'] = occi_format_date( $r->birth_date );
        if ( $r->birth_place ) $details['Place of Birth'] = $r->birth_place;
        $father = trim( $r->father_first_name . ' ' . $r->father_middle_name . ' ' . $r->father_last_name );
        $mother = trim( $r->mother_first_name . ' ' . $r->mother_middle_name . ' ' . $r->mother_last_name )
                  . ( $r->mother_maiden_name ? ', née ' . strtoupper( $r->mother_maiden_name ) : '' );
        if ( $father ) $details['Father'] = $father;
        if ( $r->mother_first_name ) $details['Mother'] = $mother;
        $sponsors = array_filter( [ $r->sponsor1_name, $r->sponsor2_name ] );
        if ( $sponsors ) $details['Sponsor(s)'] = implode( '; ', $sponsors );
        $details['Minister']  = $r->minister_name . ( $r->minister_type ? ', ' . $r->minister_type : '' );
        $details['Parish']    = $location;
        return [
            'parish_id' => intval( $r->parish_id ?? 0 ),
            'title'    => 'Certificate of Baptism',
            'intro'    => 'This certifies that',
            'name'     => $name,
            'details'  => $details,
            'notations'=> $r->is_confidential ? '' : $r->notations,
        ];
    }

    private static function cert_confirmation( $id ) {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare(
            "SELECT c.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_confirmations c
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON c.parish_id = p.id
             WHERE c.id = %d", $id ) );
        if ( ! $r ) return null;
        $name = trim( $r->first_name . ' ' . $r->middle_name . ' ' . strtoupper( $r->last_name ) );
        $location = self::format_location( $r->parish_name, $r->parish_city, $r->parish_state, $r->alt_location );
        $details = [
            'Date of Confirmation' => occi_format_date( $r->confirmation_date ),
            'Confirming Bishop'    => $r->bishop_name,
            'Parish'               => $location,
        ];
        return [
            'parish_id'   => intval( $r->parish_id ?? 0 ),
            'title'       => 'Certificate of Confirmation',
            'intro'       => 'This certifies that',
            'name'        => $name,
            'saints_name' => $r->saints_name,
            'details'     => $details,
            'notations'   => $r->notations,
        ];
    }

    private static function cert_marriage( $id ) {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_marriages m
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON m.parish_id = p.id
             WHERE m.id = %d", $id ) );
        if ( ! $r ) return null;
        $name1 = trim( $r->party1_first_name . ' ' . $r->party1_middle_name . ' ' . strtoupper( $r->party1_last_name ) );
        $name2 = trim( $r->party2_first_name . ' ' . $r->party2_middle_name . ' ' . strtoupper( $r->party2_last_name ) );
        $location = self::format_location( $r->parish_name, $r->parish_city, $r->parish_state, $r->alt_location );
        $details = [
            'Date of Marriage' => occi_format_date( $r->marriage_date ),
            'Witness 1'        => $r->witness1_name,
            'Witness 2'        => $r->witness2_name,
            'Minister'         => $r->minister_name,
            'Parish'           => $location,
        ];
        return [
            'parish_id' => intval( $r->parish_id ?? 0 ),
            'title'    => 'Certificate of Marriage',
            'intro'    => 'This certifies the holy union of',
            'name'     => $name1 . "\n& " . $name2,
            'details'  => $details,
            'notations'=> $r->notations,
        ];
    }

    private static function cert_death( $id ) {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare(
            "SELECT d.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_deaths d
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON d.parish_id = p.id
             WHERE d.id = %d", $id ) );
        if ( ! $r ) return null;
        $name = trim( $r->first_name . ' ' . $r->middle_name . ' ' . strtoupper( $r->last_name ) );
        $details = [ 'Date of Death' => occi_format_date( $r->death_date ) ];
        if ( $r->funeral_date ) $details['Date of Funeral']  = occi_format_date( $r->funeral_date );
        if ( $r->funeral_presider ) $details['Presider']     = $r->funeral_presider;
        $burial = implode( ', ', array_filter( [ $r->burial_location, $r->burial_city, $r->burial_state ] ) );
        if ( $burial ) $details['Place of Burial'] = $burial;
        if ( $r->is_cremated && $r->ashes_interment_place ) $details['Ashes Interred'] = $r->ashes_interment_place;
        $parish = self::format_location( $r->parish_name, $r->parish_city, $r->parish_state, '' );
        if ( $parish ) $details['Parish'] = $parish;
        return [
            'parish_id' => intval( $r->parish_id ?? 0 ),
            'title'    => 'Record of Christian Burial',
            'intro'    => 'This certifies the Christian burial of',
            'name'     => $name,
            'details'  => $details,
            'notations'=> $r->notations,
        ];
    }

    private static function cert_communion( $id ) {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare(
            "SELECT c.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_communions c
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON c.parish_id = p.id
             WHERE c.id = %d", $id ) );
        if ( ! $r ) return null;
        $name = trim( $r->first_name . ' ' . $r->middle_name . ' ' . strtoupper( $r->last_name ) );
        $details = [ 'Date of Reception' => occi_format_date( $r->communion_date ) ];
        if ( $r->baptism_date )   $details['Date of Baptism']   = occi_format_date( $r->baptism_date );
        if ( $r->baptism_church ) $details['Church of Baptism'] = implode( ', ', array_filter( [ $r->baptism_church, $r->baptism_city, $r->baptism_state ] ) );
        $details['Presider'] = $r->presider;
        $details['Parish']   = self::format_location( $r->parish_name, $r->parish_city, $r->parish_state, '' );
        return [
            'parish_id' => intval( $r->parish_id ?? 0 ),
            'title'    => 'Certificate of First Holy Communion',
            'intro'    => 'This certifies that',
            'name'     => $name,
            'details'  => $details,
            'notations'=> $r->notations,
        ];
    }

    private static function cert_ordination( $id ) {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare(
            "SELECT o.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
             FROM {$wpdb->prefix}occi_ordinations o
             LEFT JOIN {$wpdb->prefix}occi_parishes p ON o.parish_id = p.id
             WHERE o.id = %d", $id ) );
        if ( ! $r ) return null;
        $name = trim( $r->first_name . ' ' . $r->middle_name . ' ' . strtoupper( $r->last_name ) );
        $location = self::format_location( $r->parish_name, $r->parish_city, $r->parish_state, $r->alt_location );
        $details = [
            'Date of Ordination' => occi_format_date( $r->ordination_date ),
            'Rank'               => $r->ordination_rank,
            'Presiding Bishop'   => $r->presiding_bishop,
        ];
        $co = array_filter( [ $r->co_consecrator1, $r->co_consecrator2, $r->co_consecrator3 ] );
        if ( $co ) $details['Co-Consecrators'] = implode( '; ', $co );
        $details['Parish'] = $location;
        return [
            'parish_id' => intval( $r->parish_id ?? 0 ),
            'title'    => 'Certificate of Ordination',
            'intro'    => 'This certifies that',
            'name'     => $name,
            'details'  => $details,
            'notations'=> $r->notations,
        ];
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private static function format_location( $parish, $city, $state, $alt ) {
        $parts = array_filter( [ $parish, $city, $state ] );
        $loc   = $parts ? implode( ', ', $parts ) : '';
        if ( $alt ) $loc .= ( $loc ? ' — ' : '' ) . $alt;
        return $loc ?: '&mdash;';
    }

    // -------------------------------------------------------------------------
    // Generate the print certificate URL for use in register view pages
    // -------------------------------------------------------------------------

    public static function certificate_button( $type, $id ) {
        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=occi_print_certificate&type=' . $type . '&id=' . $id ),
            'occi_print_certificate',
            'occi_nonce'
        );
        return '<a href="' . esc_url( $url ) . '" target="_blank" class="button button-primary occi-cert-btn">&#127881; Print Certificate</a>';
    }
}
