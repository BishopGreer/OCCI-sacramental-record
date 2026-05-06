<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Death {

    public static function init() {
        add_action( 'admin_post_occi_save_death',   [ __CLASS__, 'save' ] );
        add_action( 'admin_post_occi_delete_death', [ __CLASS__, 'delete' ] );
    }

    public static function page() {
        global $wpdb;
        if ( ! current_user_can( 'occi_view_records' ) ) { wp_die( 'Access denied.' ); }
        $action = $_GET['action'] ?? 'list';
        $id = intval( $_GET['id'] ?? 0 );
        if ( $action === 'add' || $action === 'edit' ) {
            $r = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occi_deaths WHERE id=%d", $id ) ) : null;
            self::render_form( $r );
        } elseif ( $action === 'view' && $id ) {
            $r = $wpdb->get_row( $wpdb->prepare( "SELECT d.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state FROM {$wpdb->prefix}occi_deaths d LEFT JOIN {$wpdb->prefix}occi_parishes p ON d.parish_id=p.id WHERE d.id=%d", $id ) );
            self::render_view( $r );
        } else {
            self::render_list();
        }
    }

    private static function render_list() {
        global $wpdb;
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_GET['date_to'] ?? '' );
        $where = 'WHERE 1=1'; $args = [];
        if ( $search ) { $where .= ' AND (d.last_name LIKE %s OR d.first_name LIKE %s)'; $like = '%' . $wpdb->esc_like( $search ) . '%'; $args = [ $like, $like ]; }
        if ( $date_from ) { $where .= ' AND d.death_date >= %s'; $args[] = $date_from; }
        if ( $date_to )   { $where .= ' AND d.death_date <= %s'; $args[] = $date_to; }
        $sql = "SELECT d.*, p.name AS parish_name FROM {$wpdb->prefix}occi_deaths d LEFT JOIN {$wpdb->prefix}occi_parishes p ON d.parish_id=p.id $where ORDER BY d.death_date DESC";
        $records = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );
        $msg = '';
        if ( isset( $_GET['saved'] ) )   $msg = '<div class="notice notice-success is-dismissible"><p>Death record saved.</p></div>';
        if ( isset( $_GET['deleted'] ) ) $msg = '<div class="notice notice-success is-dismissible"><p>Death record deleted.</p></div>';
        ?>
        <div class="wrap occi-wrap">
            <h1>Death Register
                <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-deaths&action=add' ) ); ?>" class="page-title-action">Add New Entry</a>
                <?php endif; ?>
            </h1>
            <?php echo $msg; ?>
            <form method="get" class="occi-search-form">
                <input type="hidden" name="page" value="occi-deaths">
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by name...">
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
                <button type="submit" class="button">Search</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-deaths' ) ); ?>" class="button">Reset</a>
            </form>
            <table class="wp-list-table widefat fixed striped occi-register-table">
                <thead><tr><th>Date of Death</th><th>Deceased</th><th>Burial Location</th><th>Funeral Date</th><th>Presider</th><th>Parish</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ( $records ) : foreach ( $records as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( occi_format_date( $r->death_date ) ); ?></td>
                    <td><strong><?php echo esc_html( strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' ) ); ?></strong>
                        <?php if ( $r->is_cremated ) echo '<br><small><em>Cremated</em></small>'; ?>
                        <?php if ( $r->is_graveside ) echo '<br><small><em>Graveside</em></small>'; ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->burial_location ? $r->burial_location . ', ' . $r->burial_city . ', ' . $r->burial_state : '&mdash;' ); ?></td>
                    <td><?php echo esc_html( $r->funeral_date ? occi_format_date( $r->funeral_date ) : '&mdash;' ); ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->funeral_presider ?: '&mdash;' ); ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->parish_name ?: '&mdash;' ); ?></td>
                    <td class="occi-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-deaths&action=view&id=' . $r->id ) ); ?>">View</a>
                        <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                        | <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-deaths&action=edit&id=' . $r->id ) ); ?>">Edit</a>
                        | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occi_delete_death&id=' . $r->id ), 'occi_delete_death_' . $r->id ) ); ?>" class="occi-delete" onclick="return confirm('Delete this death record?')">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else : ?>
                <tr><td colspan="7">No death records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function render_form( $r = null ) {
        $is_edit = ! is_null( $r );
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo $is_edit ? 'Edit Death Record' : 'New Death Entry'; ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-deaths' ) ); ?>">&larr; Back to Register</a>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="occi-form">
                <?php wp_nonce_field( 'occi_save_death', 'occi_nonce' ); ?>
                <input type="hidden" name="action" value="occi_save_death">
                <?php if ( $is_edit ) : ?><input type="hidden" name="record_id" value="<?php echo esc_attr( $r->id ); ?>"><?php endif; ?>
                <div class="occi-section">
                    <h2>Deceased</h2>
                    <table class="form-table">
                        <tr><th><label>Date of Death *</label></th>
                            <td><input type="date" name="death_date" value="<?php echo esc_attr( $r->death_date ?? '' ); ?>" required></td></tr>
                        <tr><th><label>First Name *</label></th>
                            <td><input type="text" name="first_name" class="regular-text" value="<?php echo esc_attr( $r->first_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Middle Name</label></th>
                            <td><input type="text" name="middle_name" class="regular-text" value="<?php echo esc_attr( $r->middle_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Last Name *</label></th>
                            <td><input type="text" name="last_name" class="regular-text" value="<?php echo esc_attr( $r->last_name ?? '' ); ?>" required></td></tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Burial Information</h2>
                    <table class="form-table">
                        <tr><th><label>Burial Location / Cemetery</label></th>
                            <td><input type="text" name="burial_location" class="regular-text" value="<?php echo esc_attr( $r->burial_location ?? '' ); ?>"></td></tr>
                        <tr><th><label>Burial City</label></th>
                            <td><input type="text" name="burial_city" class="regular-text" value="<?php echo esc_attr( $r->burial_city ?? '' ); ?>"></td></tr>
                        <tr><th><label>Burial State</label></th>
                            <td><input type="text" name="burial_state" class="small-text" value="<?php echo esc_attr( $r->burial_state ?? '' ); ?>"></td></tr>
                        <tr><th><label>Cremation</label></th>
                            <td><label><input type="checkbox" name="is_cremated" value="1" <?php checked( $r->is_cremated ?? 0, 1 ); ?>> Body was cremated</label>
                            <p class="description">Note: Do not record the date or place of cremation per canon law. Record only the interment of ashes below.</p></td></tr>
                        <tr><th><label>Date of Ashes Interment</label></th>
                            <td><input type="date" name="ashes_interment_date" value="<?php echo esc_attr( $r->ashes_interment_date ?? '' ); ?>"></td></tr>
                        <tr><th><label>Place of Ashes Interment</label></th>
                            <td><input type="text" name="ashes_interment_place" class="regular-text" value="<?php echo esc_attr( $r->ashes_interment_place ?? '' ); ?>"></td></tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Funeral Service</h2>
                    <table class="form-table">
                        <tr><th><label>Date of Funeral</label></th>
                            <td><input type="date" name="funeral_date" value="<?php echo esc_attr( $r->funeral_date ?? '' ); ?>"></td></tr>
                        <tr><th><label>Presider</label></th>
                            <td><input type="text" name="funeral_presider" class="regular-text" value="<?php echo esc_attr( $r->funeral_presider ?? '' ); ?>"></td></tr>
                        <tr><th><label>Parish (Funeral)</label></th>
                            <td><select name="parish_id"><?php echo OCCI_Database::parish_dropdown( $r->parish_id ?? 0 ); ?></select></td></tr>
                        <tr><th><label>Graveside Service</label></th>
                            <td><label><input type="checkbox" name="is_graveside" value="1" id="is_graveside" <?php checked( $r->is_graveside ?? 0, 1 ); ?>> This was a graveside service</label></td></tr>
                        <tr><th><label>Cemetery Name</label></th>
                            <td><input type="text" name="cemetery_name" class="regular-text" value="<?php echo esc_attr( $r->cemetery_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Cemetery City</label></th>
                            <td><input type="text" name="cemetery_city" class="regular-text" value="<?php echo esc_attr( $r->cemetery_city ?? '' ); ?>"></td></tr>
                        <tr><th><label>Cemetery State</label></th>
                            <td><input type="text" name="cemetery_state" class="small-text" value="<?php echo esc_attr( $r->cemetery_state ?? '' ); ?>"></td></tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Notations</h2>
                    <table class="form-table">
                        <tr><th><label>Notations</label></th>
                            <td><textarea name="notations" rows="4" class="large-text"><?php echo esc_textarea( $r->notations ?? '' ); ?></textarea></td></tr>
                    </table>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Record' : 'Save Record'; ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-deaths' ) ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    private static function render_view( $r ) {
        if ( ! $r ) { echo '<div class="wrap"><p>Record not found.</p></div>'; return; }
        ?>
        <div class="wrap occi-wrap">
            <h1>Death Register Record</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-deaths' ) ); ?>">&larr; Back to Register</a>
            <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-deaths&action=edit&id=' . $r->id ) ); ?>" class="button button-secondary" style="margin-left:10px">Edit</a>
            <?php endif; ?>
            <button onclick="window.print()" class="button" style="margin-left:10px">Print Record</button>
            <?php echo OCCI_Certificates::certificate_button( 'death', $r->id ); ?>
            <div class="occi-view-record">
                <div class="occi-cert-header"><h2>OCCI Sacramental Record</h2><h3>DEATH REGISTER</h3>
                    <?php if ( $r->parish_name ) : ?><p><?php echo esc_html( $r->parish_name . ' &bull; ' . $r->parish_city . ', ' . $r->parish_state ); ?></p><?php endif; ?>
                </div>
                <table class="occi-view-table">
                    <tr><th>Date of Death</th><td><?php echo esc_html( occi_format_date( $r->death_date ) ); ?></td>
                        <th>Deceased</th><td><strong><?php echo esc_html( strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' ) ); ?></strong></td></tr>
                    <tr><th>Burial Location</th><td colspan="3"><?php echo esc_html( implode( ', ', array_filter( [ $r->burial_location, $r->burial_city, $r->burial_state ] ) ) ?: '&mdash;' ); ?></td></tr>
                    <?php if ( $r->is_cremated ) : ?>
                    <tr><th>Cremation</th><td colspan="3">Yes. <?php echo $r->ashes_interment_date ? 'Ashes interred: ' . esc_html( occi_format_date( $r->ashes_interment_date ) . ( $r->ashes_interment_place ? ', ' . $r->ashes_interment_place : '' ) ) : ''; ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Funeral Date</th><td><?php echo esc_html( $r->funeral_date ? occi_format_date( $r->funeral_date ) : '&mdash;' ); ?></td>
                        <th>Presider</th><td><?php echo esc_html( $r->funeral_presider ?: '&mdash;' ); ?></td></tr>
                    <tr><th>Parish</th><td><?php echo esc_html( $r->parish_name ? $r->parish_name . ', ' . $r->parish_city . ', ' . $r->parish_state : '&mdash;' ); ?></td>
                        <th>Graveside Service</th><td><?php echo $r->is_graveside ? 'Yes' : 'No'; ?></td></tr>
                    <?php if ( $r->is_graveside && $r->cemetery_name ) : ?>
                    <tr><th>Cemetery</th><td colspan="3"><?php echo esc_html( implode( ', ', array_filter( [ $r->cemetery_name, $r->cemetery_city, $r->cemetery_state ] ) ) ); ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Notations</th><td colspan="3"><?php echo nl2br( esc_html( $r->notations ?: 'No Notations' ) ); ?></td></tr>
                </table>
                <div class="occi-cert-footer">
                    <div class="occi-sig-line"><p>_________________________________</p><p>Authorized Signatory</p></div>
                    <div class="occi-sig-line"><p>_________________________________</p><p>Date Issued</p></div>
                    <p class="occi-cert-note">This is an official record of the sacramental register. <em>Pax et Bonum.</em></p>
                </div>
            </div>
        </div>
        <?php
    }

    public static function save() {
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_save_death', 'occi_nonce' ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;
        $data = [
            'death_date'             => sanitize_text_field( $_POST['death_date'] ?? '' ),
            'first_name'             => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'middle_name'            => sanitize_text_field( $_POST['middle_name'] ?? '' ),
            'last_name'              => sanitize_text_field( $_POST['last_name'] ?? '' ),
            'burial_location'        => sanitize_text_field( $_POST['burial_location'] ?? '' ),
            'burial_city'            => sanitize_text_field( $_POST['burial_city'] ?? '' ),
            'burial_state'           => sanitize_text_field( $_POST['burial_state'] ?? '' ),
            'funeral_date'           => sanitize_text_field( $_POST['funeral_date'] ?? '' ) ?: null,
            'funeral_presider'       => sanitize_text_field( $_POST['funeral_presider'] ?? '' ),
            'parish_id'              => intval( $_POST['parish_id'] ?? 0 ) ?: null,
            'is_graveside'           => isset( $_POST['is_graveside'] ) ? 1 : 0,
            'cemetery_name'          => sanitize_text_field( $_POST['cemetery_name'] ?? '' ),
            'cemetery_city'          => sanitize_text_field( $_POST['cemetery_city'] ?? '' ),
            'cemetery_state'         => sanitize_text_field( $_POST['cemetery_state'] ?? '' ),
            'is_cremated'            => isset( $_POST['is_cremated'] ) ? 1 : 0,
            'ashes_interment_date'   => sanitize_text_field( $_POST['ashes_interment_date'] ?? '' ) ?: null,
            'ashes_interment_place'  => sanitize_text_field( $_POST['ashes_interment_place'] ?? '' ),
            'notations'              => sanitize_textarea_field( $_POST['notations'] ?? '' ),
        ];
        $id = intval( $_POST['record_id'] ?? 0 );
        if ( $id ) { $wpdb->update( "{$wpdb->prefix}occi_deaths", $data, [ 'id' => $id ] ); }
        else { $wpdb->insert( "{$wpdb->prefix}occi_deaths", $data ); }
        wp_redirect( admin_url( 'admin.php?page=occi-deaths&saved=1' ) );
        exit;
    }

    public static function delete() {
        $id = intval( $_GET['id'] ?? 0 );
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_delete_death_' . $id ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occi_deaths", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occi-deaths&deleted=1' ) );
        exit;
    }
}
