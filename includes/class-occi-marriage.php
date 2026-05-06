<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Marriage {

    public static function init() {
        add_action( 'admin_post_occi_save_marriage',   [ __CLASS__, 'save' ] );
        add_action( 'admin_post_occi_delete_marriage', [ __CLASS__, 'delete' ] );
    }

    public static function page() {
        global $wpdb;
        if ( ! current_user_can( 'occi_view_records' ) ) { wp_die( 'Access denied.' ); }
        $action = $_GET['action'] ?? 'list';
        $id = intval( $_GET['id'] ?? 0 );
        if ( $action === 'add' || $action === 'edit' ) {
            $r = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occi_marriages WHERE id=%d", $id ) ) : null;
            self::render_form( $r );
        } elseif ( $action === 'view' && $id ) {
            $r = $wpdb->get_row( $wpdb->prepare( "SELECT m.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state FROM {$wpdb->prefix}occi_marriages m LEFT JOIN {$wpdb->prefix}occi_parishes p ON m.parish_id=p.id WHERE m.id=%d", $id ) );
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
        $orderby = in_array( $_GET['orderby'] ?? '', [ 'marriage_date', 'party1_last_name' ] ) ? $_GET['orderby'] : 'marriage_date';
        $order = ( ( $_GET['order'] ?? 'ASC' ) === 'DESC' ) ? 'DESC' : 'ASC';
        $where = 'WHERE 1=1'; $args = [];
        if ( $search ) { $where .= ' AND (m.party1_last_name LIKE %s OR m.party2_last_name LIKE %s OR m.party1_first_name LIKE %s OR m.party2_first_name LIKE %s)'; $like = '%' . $wpdb->esc_like( $search ) . '%'; $args = [ $like, $like, $like, $like ]; }
        if ( $date_from ) { $where .= ' AND m.marriage_date >= %s'; $args[] = $date_from; }
        if ( $date_to )   { $where .= ' AND m.marriage_date <= %s'; $args[] = $date_to; }
        $sql = "SELECT m.*, p.name AS parish_name, p.city AS parish_city FROM {$wpdb->prefix}occi_marriages m LEFT JOIN {$wpdb->prefix}occi_parishes p ON m.parish_id=p.id $where ORDER BY m.$orderby $order";
        $records = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );
        $msg = '';
        if ( isset( $_GET['saved'] ) )   $msg = '<div class="notice notice-success is-dismissible"><p>Marriage record saved.</p></div>';
        if ( isset( $_GET['deleted'] ) ) $msg = '<div class="notice notice-success is-dismissible"><p>Marriage record deleted.</p></div>';
        ?>
        <div class="wrap occi-wrap">
            <h1>Marriage Register
                <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-marriages&action=add' ) ); ?>" class="page-title-action">Add New Entry</a>
                <?php endif; ?>
            </h1>
            <?php echo $msg; ?>
            <form method="get" class="occi-search-form">
                <input type="hidden" name="page" value="occi-marriages">
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by surname...">
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
                <button type="submit" class="button">Search</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-marriages' ) ); ?>" class="button">Reset</a>
            </form>
            <table class="wp-list-table widefat fixed striped occi-register-table">
                <thead><tr>
                    <th><?php echo self::sort_link( 'marriage_date', 'Date', $orderby, $order ); ?></th>
                    <th><?php echo self::sort_link( 'party1_last_name', 'Party 1', $orderby, $order ); ?></th>
                    <th>Party 2</th>
                    <th>Witnesses</th>
                    <th>Minister</th>
                    <th>Parish</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if ( $records ) : foreach ( $records as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( occi_format_date( $r->marriage_date ) ); ?></td>
                    <td><strong><?php echo esc_html( strtoupper( $r->party1_last_name ) . ', ' . $r->party1_first_name ); ?></strong>
                        <?php if ( $r->party1_birth_date ) echo '<br><small>b. ' . esc_html( occi_format_date( $r->party1_birth_date ) ) . '</small>'; ?></td>
                    <td><strong><?php echo esc_html( strtoupper( $r->party2_last_name ) . ', ' . $r->party2_first_name ); ?></strong>
                        <?php if ( $r->party2_birth_date ) echo '<br><small>b. ' . esc_html( occi_format_date( $r->party2_birth_date ) ) . '</small>'; ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->witness1_name ); ?><br><?php echo esc_html( $r->witness2_name ); ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->minister_name ); ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->parish_name ? $r->parish_name . ', ' . $r->parish_city : ( $r->alt_location ?: '&mdash;' ) ); ?></td>
                    <td class="occi-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-marriages&action=view&id=' . $r->id ) ); ?>">View</a>
                        <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                        | <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-marriages&action=edit&id=' . $r->id ) ); ?>">Edit</a>
                        | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occi_delete_marriage&id=' . $r->id ), 'occi_delete_marriage_' . $r->id ) ); ?>" class="occi-delete" onclick="return confirm('Delete this marriage record?')">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else : ?>
                <tr><td colspan="7">No marriage records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <p class="occi-count"><?php echo count( $records ); ?> record(s) found.</p>
        </div>
        <?php
    }

    private static function render_form( $r = null ) {
        $is_edit = ! is_null( $r );
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo $is_edit ? 'Edit Marriage Record' : 'New Marriage Entry'; ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-marriages' ) ); ?>">&larr; Back to Register</a>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="occi-form">
                <?php wp_nonce_field( 'occi_save_marriage', 'occi_nonce' ); ?>
                <input type="hidden" name="action" value="occi_save_marriage">
                <?php if ( $is_edit ) : ?><input type="hidden" name="record_id" value="<?php echo esc_attr( $r->id ); ?>"><?php endif; ?>
                <div class="occi-section">
                    <h2>Marriage Details</h2>
                    <table class="form-table">
                        <tr><th><label>Date of Marriage *</label></th>
                            <td><input type="date" name="marriage_date" value="<?php echo esc_attr( $r->marriage_date ?? '' ); ?>" required></td></tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Party 1</h2>
                    <table class="form-table">
                        <tr><th><label>First Name *</label></th>
                            <td><input type="text" name="party1_first_name" class="regular-text" value="<?php echo esc_attr( $r->party1_first_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Middle Name</label></th>
                            <td><input type="text" name="party1_middle_name" class="regular-text" value="<?php echo esc_attr( $r->party1_middle_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Last Name *</label></th>
                            <td><input type="text" name="party1_last_name" class="regular-text" value="<?php echo esc_attr( $r->party1_last_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Maiden Name</label></th>
                            <td><input type="text" name="party1_maiden_name" class="regular-text" value="<?php echo esc_attr( $r->party1_maiden_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Date of Birth</label></th>
                            <td><input type="date" name="party1_birth_date" value="<?php echo esc_attr( $r->party1_birth_date ?? '' ); ?>"></td></tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Party 2</h2>
                    <table class="form-table">
                        <tr><th><label>First Name *</label></th>
                            <td><input type="text" name="party2_first_name" class="regular-text" value="<?php echo esc_attr( $r->party2_first_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Middle Name</label></th>
                            <td><input type="text" name="party2_middle_name" class="regular-text" value="<?php echo esc_attr( $r->party2_middle_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Last Name *</label></th>
                            <td><input type="text" name="party2_last_name" class="regular-text" value="<?php echo esc_attr( $r->party2_last_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Maiden Name</label></th>
                            <td><input type="text" name="party2_maiden_name" class="regular-text" value="<?php echo esc_attr( $r->party2_maiden_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Date of Birth</label></th>
                            <td><input type="date" name="party2_birth_date" value="<?php echo esc_attr( $r->party2_birth_date ?? '' ); ?>"></td></tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Witnesses &amp; Minister</h2>
                    <table class="form-table">
                        <tr><th><label>Witness 1 Full Name *</label></th>
                            <td><input type="text" name="witness1_name" class="regular-text" value="<?php echo esc_attr( $r->witness1_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Witness 2 Full Name *</label></th>
                            <td><input type="text" name="witness2_name" class="regular-text" value="<?php echo esc_attr( $r->witness2_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Officiating Minister *</label></th>
                            <td><input type="text" name="minister_name" class="regular-text" value="<?php echo esc_attr( $r->minister_name ?? '' ); ?>" required></td></tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Location</h2>
                    <table class="form-table">
                        <tr><th><label>Parish</label></th>
                            <td><select name="parish_id"><?php echo OCCI_Database::parish_dropdown( $r->parish_id ?? 0 ); ?></select></td></tr>
                        <tr><th><label>Alternate Location</label></th>
                            <td><input type="text" name="alt_location" class="regular-text" value="<?php echo esc_attr( $r->alt_location ?? '' ); ?>"></td></tr>
                    </table>
                </div>
                <div class="occi-section">
                    <h2>Notations</h2>
                    <table class="form-table">
                        <tr><th><label>Notations</label></th>
                            <td><textarea name="notations" rows="4" class="large-text"><?php echo esc_textarea( $r->notations ?? '' ); ?></textarea>
                            <p class="description">Record dispensations (Disparity of Cult, Mixed Religion, Canonical Form), convalidation, sanation, annulment notifications, and other canonical matters.</p></td></tr>
                    </table>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Record' : 'Save Record'; ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-marriages' ) ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    private static function render_view( $r ) {
        if ( ! $r ) { echo '<div class="wrap"><p>Record not found.</p></div>'; return; }
        ?>
        <div class="wrap occi-wrap">
            <h1>Marriage Certificate Record</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-marriages' ) ); ?>">&larr; Back to Register</a>
            <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-marriages&action=edit&id=' . $r->id ) ); ?>" class="button button-secondary" style="margin-left:10px">Edit Record</a>
            <?php endif; ?>
            <button onclick="window.print()" class="button" style="margin-left:10px">Print Record</button>
            <?php echo OCCI_Certificates::certificate_button( 'marriage', $r->id ); ?>
            <div class="occi-view-record">
                <div class="occi-cert-header">
                    <h2>OCCI Sacramental Record</h2><h3>MARRIAGE REGISTER</h3>
                    <?php if ( $r->parish_name ) : ?><p><?php echo esc_html( $r->parish_name . ' &bull; ' . $r->parish_city . ', ' . $r->parish_state ); ?></p><?php endif; ?>
                </div>
                <table class="occi-view-table">
                    <tr><th>Date of Marriage</th><td colspan="3"><?php echo esc_html( occi_format_date( $r->marriage_date ) ); ?></td></tr>
                    <tr><th>Party 1</th><td><?php echo esc_html( strtoupper( $r->party1_last_name ) . ', ' . $r->party1_first_name . ( $r->party1_middle_name ? ' ' . $r->party1_middle_name : '' ) . ( $r->party1_maiden_name ? ', née ' . strtoupper( $r->party1_maiden_name ) : '' ) ); ?></td>
                        <th>Date of Birth</th><td><?php echo esc_html( $r->party1_birth_date ? occi_format_date( $r->party1_birth_date ) : '&mdash;' ); ?></td></tr>
                    <tr><th>Party 2</th><td><?php echo esc_html( strtoupper( $r->party2_last_name ) . ', ' . $r->party2_first_name . ( $r->party2_middle_name ? ' ' . $r->party2_middle_name : '' ) . ( $r->party2_maiden_name ? ', née ' . strtoupper( $r->party2_maiden_name ) : '' ) ); ?></td>
                        <th>Date of Birth</th><td><?php echo esc_html( $r->party2_birth_date ? occi_format_date( $r->party2_birth_date ) : '&mdash;' ); ?></td></tr>
                    <tr><th>Witness 1</th><td><?php echo esc_html( $r->witness1_name ); ?></td>
                        <th>Witness 2</th><td><?php echo esc_html( $r->witness2_name ); ?></td></tr>
                    <tr><th>Minister</th><td><?php echo esc_html( $r->minister_name ); ?></td>
                        <th>Location</th><td><?php echo esc_html( $r->parish_name ? $r->parish_name . ', ' . $r->parish_city . ', ' . $r->parish_state : ( $r->alt_location ?: '&mdash;' ) ); ?></td></tr>
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
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_save_marriage', 'occi_nonce' ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;
        $data = [
            'marriage_date'       => sanitize_text_field( $_POST['marriage_date'] ?? '' ),
            'party1_first_name'   => sanitize_text_field( $_POST['party1_first_name'] ?? '' ),
            'party1_middle_name'  => sanitize_text_field( $_POST['party1_middle_name'] ?? '' ),
            'party1_last_name'    => sanitize_text_field( $_POST['party1_last_name'] ?? '' ),
            'party1_maiden_name'  => sanitize_text_field( $_POST['party1_maiden_name'] ?? '' ),
            'party1_birth_date'   => sanitize_text_field( $_POST['party1_birth_date'] ?? '' ) ?: null,
            'party2_first_name'   => sanitize_text_field( $_POST['party2_first_name'] ?? '' ),
            'party2_middle_name'  => sanitize_text_field( $_POST['party2_middle_name'] ?? '' ),
            'party2_last_name'    => sanitize_text_field( $_POST['party2_last_name'] ?? '' ),
            'party2_maiden_name'  => sanitize_text_field( $_POST['party2_maiden_name'] ?? '' ),
            'party2_birth_date'   => sanitize_text_field( $_POST['party2_birth_date'] ?? '' ) ?: null,
            'witness1_name'       => sanitize_text_field( $_POST['witness1_name'] ?? '' ),
            'witness2_name'       => sanitize_text_field( $_POST['witness2_name'] ?? '' ),
            'minister_name'       => sanitize_text_field( $_POST['minister_name'] ?? '' ),
            'parish_id'           => intval( $_POST['parish_id'] ?? 0 ) ?: null,
            'alt_location'        => sanitize_text_field( $_POST['alt_location'] ?? '' ),
            'notations'           => sanitize_textarea_field( $_POST['notations'] ?? '' ),
            'created_by'          => get_current_user_id(),
        ];
        $id = intval( $_POST['record_id'] ?? 0 );
        if ( $id ) { $wpdb->update( "{$wpdb->prefix}occi_marriages", $data, [ 'id' => $id ] ); }
        else { $wpdb->insert( "{$wpdb->prefix}occi_marriages", $data ); }
        wp_redirect( admin_url( 'admin.php?page=occi-marriages&saved=1' ) );
        exit;
    }

    public static function delete() {
        $id = intval( $_GET['id'] ?? 0 );
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_delete_marriage_' . $id ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occi_marriages", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occi-marriages&deleted=1' ) );
        exit;
    }

    private static function sort_link( $col, $label, $current_col, $current_order ) {
        $order = ( $current_col === $col && $current_order === 'ASC' ) ? 'DESC' : 'ASC';
        $url = admin_url( 'admin.php?page=occi-marriages&orderby=' . $col . '&order=' . $order );
        $arrow = $current_col === $col ? ( $current_order === 'ASC' ? ' &uarr;' : ' &darr;' ) : '';
        return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $arrow . '</a>';
    }
}
