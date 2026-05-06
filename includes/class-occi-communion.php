<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Communion {

    public static function init() {
        add_action( 'admin_post_occi_save_communion',   [ __CLASS__, 'save' ] );
        add_action( 'admin_post_occi_delete_communion', [ __CLASS__, 'delete' ] );
    }

    public static function page() {
        global $wpdb;
        if ( ! current_user_can( 'occi_view_records' ) ) { wp_die( 'Access denied.' ); }
        $action = $_GET['action'] ?? 'list';
        $id = intval( $_GET['id'] ?? 0 );
        if ( $action === 'add' || $action === 'edit' ) {
            $r = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occi_communions WHERE id=%d", $id ) ) : null;
            self::render_form( $r );
        } elseif ( $action === 'view' && $id ) {
            $r = $wpdb->get_row( $wpdb->prepare( "SELECT c.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state FROM {$wpdb->prefix}occi_communions c LEFT JOIN {$wpdb->prefix}occi_parishes p ON c.parish_id=p.id WHERE c.id=%d", $id ) );
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
        if ( $search ) { $where .= ' AND (c.last_name LIKE %s OR c.first_name LIKE %s)'; $like = '%' . $wpdb->esc_like( $search ) . '%'; $args = [ $like, $like ]; }
        if ( $date_from ) { $where .= ' AND c.communion_date >= %s'; $args[] = $date_from; }
        if ( $date_to )   { $where .= ' AND c.communion_date <= %s'; $args[] = $date_to; }
        $sql = "SELECT c.*, p.name AS parish_name FROM {$wpdb->prefix}occi_communions c LEFT JOIN {$wpdb->prefix}occi_parishes p ON c.parish_id=p.id $where ORDER BY c.communion_date DESC, c.last_name ASC";
        $records = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );
        $msg = '';
        if ( isset( $_GET['saved'] ) )   $msg = '<div class="notice notice-success is-dismissible"><p>Record saved.</p></div>';
        if ( isset( $_GET['deleted'] ) ) $msg = '<div class="notice notice-success is-dismissible"><p>Record deleted.</p></div>';
        ?>
        <div class="wrap occi-wrap">
            <h1>First Holy Communion Register
                <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-communions&action=add' ) ); ?>" class="page-title-action">Add New Entry</a>
                <?php endif; ?>
            </h1>
            <?php echo $msg; ?>
            <form method="get" class="occi-search-form">
                <input type="hidden" name="page" value="occi-communions">
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by name...">
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
                <button type="submit" class="button">Search</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-communions' ) ); ?>" class="button">Reset</a>
            </form>
            <table class="wp-list-table widefat fixed striped occi-register-table">
                <thead><tr><th>Date of Reception</th><th>Communicant</th><th>Baptism Date</th><th>Church of Baptism</th><th>Presider</th><th>Parish</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ( $records ) : foreach ( $records as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( occi_format_date( $r->communion_date ) ); ?></td>
                    <td><strong><?php echo esc_html( strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' ) ); ?></strong></td>
                    <td><?php echo esc_html( $r->baptism_date ? occi_format_date( $r->baptism_date ) : '&mdash;' ); ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->baptism_church ? $r->baptism_church . ( $r->baptism_city ? ', ' . $r->baptism_city : '' ) : '&mdash;' ); ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->presider ); ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->parish_name ?: '&mdash;' ); ?></td>
                    <td class="occi-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-communions&action=view&id=' . $r->id ) ); ?>">View</a>
                        <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                        | <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-communions&action=edit&id=' . $r->id ) ); ?>">Edit</a>
                        | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occi_delete_communion&id=' . $r->id ), 'occi_delete_communion_' . $r->id ) ); ?>" class="occi-delete" onclick="return confirm('Delete this record?')">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else : ?>
                <tr><td colspan="7">No First Communion records found.</td></tr>
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
            <h1><?php echo $is_edit ? 'Edit First Communion Record' : 'New First Communion Entry'; ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-communions' ) ); ?>">&larr; Back to Register</a>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="occi-form">
                <?php wp_nonce_field( 'occi_save_communion', 'occi_nonce' ); ?>
                <input type="hidden" name="action" value="occi_save_communion">
                <?php if ( $is_edit ) : ?><input type="hidden" name="record_id" value="<?php echo esc_attr( $r->id ); ?>"><?php endif; ?>
                <div class="occi-section">
                    <h2>Communicant</h2>
                    <table class="form-table">
                        <tr><th><label>Date of Reception *</label></th>
                            <td><input type="date" name="communion_date" value="<?php echo esc_attr( $r->communion_date ?? '' ); ?>" required></td></tr>
                        <tr><th><label>First Name *</label></th>
                            <td><input type="text" name="first_name" class="regular-text" value="<?php echo esc_attr( $r->first_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Middle Name</label></th>
                            <td><input type="text" name="middle_name" class="regular-text" value="<?php echo esc_attr( $r->middle_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Last Name *</label></th>
                            <td><input type="text" name="last_name" class="regular-text" value="<?php echo esc_attr( $r->last_name ?? '' ); ?>" required></td></tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Baptism Information</h2>
                    <table class="form-table">
                        <tr><th><label>Date of Baptism</label></th>
                            <td><input type="date" name="baptism_date" value="<?php echo esc_attr( $r->baptism_date ?? '' ); ?>"></td></tr>
                        <tr><th><label>Church of Baptism</label></th>
                            <td><input type="text" name="baptism_church" class="regular-text" value="<?php echo esc_attr( $r->baptism_church ?? '' ); ?>"></td></tr>
                        <tr><th><label>City of Baptism</label></th>
                            <td><input type="text" name="baptism_city" class="regular-text" value="<?php echo esc_attr( $r->baptism_city ?? '' ); ?>"></td></tr>
                        <tr><th><label>State of Baptism</label></th>
                            <td><input type="text" name="baptism_state" class="small-text" value="<?php echo esc_attr( $r->baptism_state ?? '' ); ?>"></td></tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Presider &amp; Parish</h2>
                    <table class="form-table">
                        <tr><th><label>Presider *</label></th>
                            <td><input type="text" name="presider" class="regular-text" value="<?php echo esc_attr( $r->presider ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Parish</label></th>
                            <td><select name="parish_id"><?php echo OCCI_Database::parish_dropdown( $r->parish_id ?? 0 ); ?></select></td></tr>
                        <tr><th><label>Notations</label></th>
                            <td><textarea name="notations" rows="3" class="large-text"><?php echo esc_textarea( $r->notations ?? '' ); ?></textarea></td></tr>
                    </table>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Record' : 'Save Record'; ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-communions' ) ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    private static function render_view( $r ) {
        if ( ! $r ) { echo '<div class="wrap"><p>Record not found.</p></div>'; return; }
        ?>
        <div class="wrap occi-wrap">
            <h1>First Holy Communion Record</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-communions' ) ); ?>">&larr; Back</a>
            <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-communions&action=edit&id=' . $r->id ) ); ?>" class="button button-secondary" style="margin-left:10px">Edit</a>
            <?php endif; ?>
            <button onclick="window.print()" class="button" style="margin-left:10px">Print Record</button>
            <?php echo OCCI_Certificates::certificate_button( 'communion', $r->id ); ?>
            <div class="occi-view-record">
                <div class="occi-cert-header"><h2>OCCI Sacramental Record</h2><h3>FIRST HOLY COMMUNION REGISTER</h3>
                    <?php if ( $r->parish_name ) : ?><p><?php echo esc_html( $r->parish_name . ' &bull; ' . $r->parish_city . ', ' . $r->parish_state ); ?></p><?php endif; ?>
                </div>
                <table class="occi-view-table">
                    <tr><th>Date of Reception</th><td><?php echo esc_html( occi_format_date( $r->communion_date ) ); ?></td>
                        <th>Communicant</th><td><strong><?php echo esc_html( strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' ) ); ?></strong></td></tr>
                    <tr><th>Date of Baptism</th><td><?php echo esc_html( $r->baptism_date ? occi_format_date( $r->baptism_date ) : '&mdash;' ); ?></td>
                        <th>Church of Baptism</th><td><?php echo esc_html( $r->baptism_church ? $r->baptism_church . ', ' . $r->baptism_city . ', ' . $r->baptism_state : '&mdash;' ); ?></td></tr>
                    <tr><th>Presider</th><td><?php echo esc_html( $r->presider ); ?></td>
                        <th>Parish</th><td><?php echo esc_html( $r->parish_name ? $r->parish_name . ', ' . $r->parish_city . ', ' . $r->parish_state : '&mdash;' ); ?></td></tr>
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
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_save_communion', 'occi_nonce' ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;
        $data = [
            'communion_date'  => sanitize_text_field( $_POST['communion_date'] ?? '' ),
            'first_name'      => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'middle_name'     => sanitize_text_field( $_POST['middle_name'] ?? '' ),
            'last_name'       => sanitize_text_field( $_POST['last_name'] ?? '' ),
            'baptism_date'    => sanitize_text_field( $_POST['baptism_date'] ?? '' ) ?: null,
            'baptism_church'  => sanitize_text_field( $_POST['baptism_church'] ?? '' ),
            'baptism_city'    => sanitize_text_field( $_POST['baptism_city'] ?? '' ),
            'baptism_state'   => sanitize_text_field( $_POST['baptism_state'] ?? '' ),
            'presider'        => sanitize_text_field( $_POST['presider'] ?? '' ),
            'parish_id'       => intval( $_POST['parish_id'] ?? 0 ) ?: null,
            'notations'       => sanitize_textarea_field( $_POST['notations'] ?? '' ),
        ];
        $id = intval( $_POST['record_id'] ?? 0 );
        if ( $id ) { $wpdb->update( "{$wpdb->prefix}occi_communions", $data, [ 'id' => $id ] ); }
        else { $wpdb->insert( "{$wpdb->prefix}occi_communions", $data ); }
        wp_redirect( admin_url( 'admin.php?page=occi-communions&saved=1' ) );
        exit;
    }

    public static function delete() {
        $id = intval( $_GET['id'] ?? 0 );
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_delete_communion_' . $id ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occi_communions", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occi-communions&deleted=1' ) );
        exit;
    }
}
