<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Confirmation {

    public static function init() {
        add_action( 'admin_post_occi_save_confirmation',   [ __CLASS__, 'save' ] );
        add_action( 'admin_post_occi_delete_confirmation', [ __CLASS__, 'delete' ] );
    }

    public static function page() {
        global $wpdb;
        if ( ! current_user_can( 'occi_view_records' ) ) { wp_die( 'Access denied.' ); }
        $action = $_GET['action'] ?? 'list';
        $id     = intval( $_GET['id'] ?? 0 );

        if ( $action === 'add' || $action === 'edit' ) {
            $r = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occi_confirmations WHERE id=%d", $id ) ) : null;
            self::render_form( $r );
        } elseif ( $action === 'view' && $id ) {
            $r = $wpdb->get_row( $wpdb->prepare(
                "SELECT c.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state
                 FROM {$wpdb->prefix}occi_confirmations c
                 LEFT JOIN {$wpdb->prefix}occi_parishes p ON c.parish_id = p.id
                 WHERE c.id = %d",
                $id
            ) );
            self::render_view( $r );
        } else {
            self::render_list();
        }
    }

    private static function render_list() {
        global $wpdb;
        $search    = sanitize_text_field( $_GET['s'] ?? '' );
        $date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_GET['date_to'] ?? '' );
        $orderby   = in_array( $_GET['orderby'] ?? '', [ 'confirmation_date', 'last_name', 'bishop_name' ] ) ? $_GET['orderby'] : 'confirmation_date';
        $order     = ( ( $_GET['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

        $where = 'WHERE 1=1';
        $args  = [];
        if ( $search ) {
            $where .= ' AND (c.last_name LIKE %s OR c.first_name LIKE %s OR c.bishop_name LIKE %s)';
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $args   = [ $like, $like, $like ];
        }
        if ( $date_from ) { $where .= ' AND c.confirmation_date >= %s'; $args[] = $date_from; }
        if ( $date_to )   { $where .= ' AND c.confirmation_date <= %s'; $args[] = $date_to; }

        $ob_col = "c.$orderby";
        $sql     = "SELECT c.*, p.name AS parish_name, p.city AS parish_city
                    FROM {$wpdb->prefix}occi_confirmations c
                    LEFT JOIN {$wpdb->prefix}occi_parishes p ON c.parish_id = p.id
                    $where ORDER BY $ob_col $order";
        $records = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );

        $msg = '';
        if ( isset( $_GET['saved'] ) )   $msg = '<div class="notice notice-success is-dismissible"><p>Confirmation record saved.</p></div>';
        if ( isset( $_GET['deleted'] ) ) $msg = '<div class="notice notice-success is-dismissible"><p>Confirmation record deleted.</p></div>';
        ?>
        <div class="wrap occi-wrap">
            <h1>Confirmation Register
                <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-confirmations&action=add' ) ); ?>" class="page-title-action">Add New Entry</a>
                <?php endif; ?>
            </h1>
            <?php echo $msg; ?>
            <form method="get" class="occi-search-form">
                <input type="hidden" name="page" value="occi-confirmations">
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by name or bishop..." class="regular-text">
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" title="Date From">
                <input type="date" name="date_to"   value="<?php echo esc_attr( $date_to ); ?>"   title="Date To">
                <button type="submit" class="button">Search</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-confirmations' ) ); ?>" class="button">Reset</a>
            </form>
            <table class="wp-list-table widefat fixed striped occi-register-table">
                <thead><tr>
                    <th><?php echo self::sort_link( 'confirmation_date', 'Date', $orderby, $order ); ?></th>
                    <th><?php echo self::sort_link( 'last_name', 'Name of Confirmed', $orderby, $order ); ?></th>
                    <th>Saint's Name</th>
                    <th><?php echo self::sort_link( 'bishop_name', 'Bishop / Delegate', $orderby, $order ); ?></th>
                    <th>Parish</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if ( $records ) : foreach ( $records as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( occi_format_date( $r->confirmation_date ) ); ?></td>
                    <td><strong><?php echo esc_html( strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' ) ); ?></strong></td>
                    <td><?php echo esc_html( $r->saints_name ?: '&mdash;' ); ?></td>
                    <td><?php echo esc_html( $r->bishop_name ); ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->parish_name ? $r->parish_name . ', ' . $r->parish_city : ( $r->alt_location ?: '&mdash;' ) ); ?></td>
                    <td class="occi-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-confirmations&action=view&id=' . $r->id ) ); ?>">View</a>
                        <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                        | <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-confirmations&action=edit&id=' . $r->id ) ); ?>">Edit</a>
                        | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occi_delete_confirmation&id=' . $r->id ), 'occi_delete_confirmation_' . $r->id ) ); ?>" class="occi-delete" onclick="return confirm('Delete this confirmation record? This action cannot be undone.')">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else : ?>
                <tr><td colspan="6">No confirmation records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <p class="occi-count"><?php echo count( $records ); ?> record(s) found.</p>
        </div>
        <?php
    }

    private static function render_form( $r = null ) {
        $is_edit = ! is_null( $r );
        $msg = isset( $_GET['error'] ) ? '<div class="notice notice-error"><p>Please fill in all required fields.</p></div>' : '';
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo $is_edit ? 'Edit Confirmation Record' : 'New Confirmation Entry'; ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-confirmations' ) ); ?>">&larr; Back to Register</a>
            <?php echo $msg; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="occi-form">
                <?php wp_nonce_field( 'occi_save_confirmation', 'occi_nonce' ); ?>
                <input type="hidden" name="action" value="occi_save_confirmation">
                <?php if ( $is_edit ) : ?><input type="hidden" name="record_id" value="<?php echo esc_attr( $r->id ); ?>"><?php endif; ?>

                <div class="occi-section">
                    <h2>Confirmation Details</h2>
                    <table class="form-table">
                        <tr><th><label>Date of Confirmation *</label></th>
                            <td><input type="date" name="confirmation_date" value="<?php echo esc_attr( $r->confirmation_date ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Confirming Bishop / Delegate *</label></th>
                            <td><input type="text" name="bishop_name" class="regular-text" value="<?php echo esc_attr( $r->bishop_name ?? '' ); ?>" required placeholder="Full name and title"></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Person Confirmed</h2>
                    <table class="form-table">
                        <tr><th><label>First Name *</label></th>
                            <td><input type="text" name="first_name" class="regular-text" value="<?php echo esc_attr( $r->first_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Middle Name</label></th>
                            <td><input type="text" name="middle_name" class="regular-text" value="<?php echo esc_attr( $r->middle_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Last Name *</label></th>
                            <td><input type="text" name="last_name" class="regular-text" value="<?php echo esc_attr( $r->last_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Saint's Name Chosen</label></th>
                            <td><input type="text" name="saints_name" class="regular-text" value="<?php echo esc_attr( $r->saints_name ?? '' ); ?>" placeholder="Confirmation saint's name (optional)"></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Location</h2>
                    <table class="form-table">
                        <tr><th><label>Parish</label></th>
                            <td><select name="parish_id">
                                <?php echo OCCI_Database::parish_dropdown( $r->parish_id ?? 0 ); ?>
                            </select>
                            <p class="description">If the parish is not listed, add it under <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-parishes' ) ); ?>">Parishes</a>.</p></td></tr>
                        <tr><th><label>Alternate Location</label></th>
                            <td><input type="text" name="alt_location" class="regular-text" value="<?php echo esc_attr( $r->alt_location ?? '' ); ?>" placeholder="If confirmation did not occur at the parish"></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Notations</h2>
                    <table class="form-table">
                        <tr><th><label>Notations</label></th>
                            <td><textarea name="notations" rows="4" class="large-text"><?php echo esc_textarea( $r->notations ?? '' ); ?></textarea>
                            <p class="description">Include any relevant canonical notations. Notification to the church of baptism should be noted here once sent.</p></td></tr>
                    </table>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Record' : 'Save Record'; ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-confirmations' ) ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    private static function render_view( $r ) {
        if ( ! $r ) { echo '<div class="wrap"><p>Record not found.</p></div>'; return; }
        ?>
        <div class="wrap occi-wrap">
            <h1>Confirmation Certificate Record</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-confirmations' ) ); ?>">&larr; Back to Register</a>
            <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-confirmations&action=edit&id=' . $r->id ) ); ?>" class="button button-secondary" style="margin-left:10px">Edit Record</a>
            <?php endif; ?>
            <button onclick="window.print()" class="button" style="margin-left:10px">Print Record</button>
            <?php echo OCCI_Certificates::certificate_button( 'confirmation', $r->id ); ?>
            <div class="occi-view-record" id="occi-print-area">
                <div class="occi-cert-header">
                    <h2>OCCI Sacramental Record</h2>
                    <h3>CONFIRMATION REGISTER</h3>
                    <?php if ( $r->parish_name ) : ?>
                    <p><?php echo esc_html( $r->parish_name . ' &bull; ' . $r->parish_city . ', ' . $r->parish_state ); ?></p>
                    <?php endif; ?>
                </div>
                <table class="occi-view-table">
                    <tr>
                        <th>Date of Confirmation</th>
                        <td><?php echo esc_html( occi_format_date( $r->confirmation_date ) ); ?></td>
                        <th>Bishop / Delegate</th>
                        <td><?php echo esc_html( $r->bishop_name ); ?></td>
                    </tr>
                    <tr>
                        <th>Name of Confirmed</th>
                        <td colspan="3"><strong><?php echo esc_html( strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' ) ); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Saint's Name Chosen</th>
                        <td><?php echo esc_html( $r->saints_name ?: '&mdash;' ); ?></td>
                        <th>Location</th>
                        <td><?php
                            $loc = '';
                            if ( $r->parish_name ) $loc = $r->parish_name . ', ' . $r->parish_city . ', ' . $r->parish_state;
                            if ( $r->alt_location ) $loc .= ( $loc ? ' &mdash; ' : '' ) . $r->alt_location;
                            echo esc_html( $loc ?: '&mdash;' );
                        ?></td>
                    </tr>
                    <tr>
                        <th>Notations</th>
                        <td colspan="3"><?php echo nl2br( esc_html( $r->notations ?: 'No Notations' ) ); ?></td>
                    </tr>
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
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_save_confirmation', 'occi_nonce' ) ) {
            wp_die( 'Access denied.' );
        }
        global $wpdb;
        $data = [
            'confirmation_date' => sanitize_text_field( $_POST['confirmation_date'] ?? '' ),
            'bishop_name'       => sanitize_text_field( $_POST['bishop_name'] ?? '' ),
            'first_name'        => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'middle_name'       => sanitize_text_field( $_POST['middle_name'] ?? '' ),
            'last_name'         => sanitize_text_field( $_POST['last_name'] ?? '' ),
            'saints_name'       => sanitize_text_field( $_POST['saints_name'] ?? '' ),
            'parish_id'         => intval( $_POST['parish_id'] ?? 0 ) ?: null,
            'alt_location'      => sanitize_text_field( $_POST['alt_location'] ?? '' ),
            'notations'         => sanitize_textarea_field( $_POST['notations'] ?? '' ),
        ];
        if ( empty( $data['confirmation_date'] ) || empty( $data['bishop_name'] ) || empty( $data['first_name'] ) || empty( $data['last_name'] ) ) {
            $id = intval( $_POST['record_id'] ?? 0 );
            wp_redirect( admin_url( 'admin.php?page=occi-confirmations&action=' . ( $id ? 'edit&id=' . $id : 'add' ) . '&error=1' ) );
            exit;
        }
        $id = intval( $_POST['record_id'] ?? 0 );
        if ( $id ) {
            $wpdb->update( "{$wpdb->prefix}occi_confirmations", $data, [ 'id' => $id ] );
        } else {
            $wpdb->insert( "{$wpdb->prefix}occi_confirmations", $data );
        }
        wp_redirect( admin_url( 'admin.php?page=occi-confirmations&saved=1' ) );
        exit;
    }

    public static function delete() {
        $id = intval( $_GET['id'] ?? 0 );
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_delete_confirmation_' . $id ) ) {
            wp_die( 'Access denied.' );
        }
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occi_confirmations", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occi-confirmations&deleted=1' ) );
        exit;
    }

    private static function sort_link( $col, $label, $current_col, $current_order ) {
        $order = ( $current_col === $col && $current_order === 'ASC' ) ? 'DESC' : 'ASC';
        $url   = admin_url( 'admin.php?page=occi-confirmations&orderby=' . $col . '&order=' . $order );
        $arrow = $current_col === $col ? ( $current_order === 'ASC' ? ' &uarr;' : ' &darr;' ) : '';
        return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $arrow . '</a>';
    }
}
