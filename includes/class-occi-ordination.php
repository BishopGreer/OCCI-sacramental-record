<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Ordination {

    public static function init() {
        add_action( 'admin_post_occi_save_ordination',   [ __CLASS__, 'save' ] );
        add_action( 'admin_post_occi_delete_ordination', [ __CLASS__, 'delete' ] );
    }

    public static function page() {
        global $wpdb;
        if ( ! current_user_can( 'occi_view_records' ) ) { wp_die( 'Access denied.' ); }
        $action = $_GET['action'] ?? 'list';
        $id = intval( $_GET['id'] ?? 0 );
        if ( $action === 'add' || $action === 'edit' ) {
            $r = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occi_ordinations WHERE id=%d", $id ) ) : null;
            self::render_form( $r );
        } elseif ( $action === 'view' && $id ) {
            $r = $wpdb->get_row( $wpdb->prepare( "SELECT o.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state FROM {$wpdb->prefix}occi_ordinations o LEFT JOIN {$wpdb->prefix}occi_parishes p ON o.parish_id=p.id WHERE o.id=%d", $id ) );
            self::render_view( $r );
        } else {
            self::render_list();
        }
    }

    private static function render_list() {
        global $wpdb;
        $search      = sanitize_text_field( $_GET['s'] ?? '' );
        $rank_filter = sanitize_text_field( $_GET['rank'] ?? '' );
        $where = 'WHERE 1=1'; $args = [];
        if ( $search ) {
            $where .= ' AND (o.last_name LIKE %s OR o.first_name LIKE %s OR o.presiding_bishop LIKE %s)';
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $args   = [ $like, $like, $like ];
        }
        if ( $rank_filter ) { $where .= ' AND o.ordination_rank = %s'; $args[] = $rank_filter; }
        $sql     = "SELECT o.*, p.name AS parish_name FROM {$wpdb->prefix}occi_ordinations o LEFT JOIN {$wpdb->prefix}occi_parishes p ON o.parish_id=p.id $where ORDER BY o.ordination_date DESC";
        $records = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );
        $msg = '';
        if ( isset( $_GET['saved'] ) )   $msg = '<div class="notice notice-success is-dismissible"><p>Ordination record saved.</p></div>';
        if ( isset( $_GET['deleted'] ) ) $msg = '<div class="notice notice-success is-dismissible"><p>Ordination record deleted.</p></div>';
        ?>
        <div class="wrap occi-wrap">
            <h1>Ordination Register
                <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-ordinations&action=add' ) ); ?>" class="page-title-action">Add New Entry</a>
                <?php endif; ?>
            </h1>
            <?php echo $msg; ?>
            <form method="get" class="occi-search-form">
                <input type="hidden" name="page" value="occi-ordinations">
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by name or bishop...">
                <select name="rank">
                    <option value="">All Ranks</option>
                    <option value="Deacon" <?php selected( $rank_filter, 'Deacon' ); ?>>Deacon</option>
                    <option value="Priest"  <?php selected( $rank_filter, 'Priest' ); ?>>Priest</option>
                    <option value="Bishop"  <?php selected( $rank_filter, 'Bishop' ); ?>>Bishop</option>
                </select>
                <button type="submit" class="button">Search</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-ordinations' ) ); ?>" class="button">Reset</a>
            </form>
            <table class="wp-list-table widefat fixed striped occi-register-table">
                <thead><tr>
                    <th>Date</th><th>Ordinand</th><th>Rank</th><th>Presiding Bishop</th><th>Co-Consecrators</th><th>Parish / Location</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if ( $records ) : foreach ( $records as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( occi_format_date( $r->ordination_date ) ); ?></td>
                    <td><strong><?php echo esc_html( strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' ) ); ?></strong></td>
                    <td><span class="occi-rank occi-rank-<?php echo esc_attr( strtolower( $r->ordination_rank ) ); ?>"><?php echo esc_html( $r->ordination_rank ); ?></span></td>
                    <td><?php echo esc_html( $r->presiding_bishop ); ?></td>
                    <td class="occi-small"><?php
                        $co = array_filter( [ $r->co_consecrator1, $r->co_consecrator2, $r->co_consecrator3 ] );
                        echo esc_html( $co ? implode( '; ', $co ) : ( $r->ordination_rank === 'Bishop' ? 'None recorded' : '&mdash;' ) );
                    ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->parish_name ?: ( $r->alt_location ?: '&mdash;' ) ); ?></td>
                    <td class="occi-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-ordinations&action=view&id=' . $r->id ) ); ?>">View</a>
                        <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                        | <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-ordinations&action=edit&id=' . $r->id ) ); ?>">Edit</a>
                        | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occi_delete_ordination&id=' . $r->id ), 'occi_delete_ordination_' . $r->id ) ); ?>" class="occi-delete" onclick="return confirm('Delete this ordination record? This action cannot be undone.')">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else : ?>
                <tr><td colspan="7">No ordination records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <p class="occi-count"><?php echo count( $records ); ?> record(s) found.</p>
        </div>
        <?php
    }

    private static function render_form( $r = null ) {
        $is_edit = ! is_null( $r );
        $rank    = $r->ordination_rank ?? '';
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo $is_edit ? 'Edit Ordination Record' : 'New Ordination Entry'; ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-ordinations' ) ); ?>">&larr; Back to Register</a>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="occi-form">
                <?php wp_nonce_field( 'occi_save_ordination', 'occi_nonce' ); ?>
                <input type="hidden" name="action" value="occi_save_ordination">
                <?php if ( $is_edit ) : ?><input type="hidden" name="record_id" value="<?php echo esc_attr( $r->id ); ?>"><?php endif; ?>

                <div class="occi-section">
                    <h2>Ordination Details</h2>
                    <table class="form-table">
                        <tr><th><label>Date of Ordination *</label></th>
                            <td><input type="date" name="ordination_date" value="<?php echo esc_attr( $r->ordination_date ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Rank *</label></th>
                            <td><select name="ordination_rank" required>
                                <option value="">-- Select Rank --</option>
                                <option value="Deacon" <?php selected( $rank, 'Deacon' ); ?>>Deacon</option>
                                <option value="Priest"  <?php selected( $rank, 'Priest' ); ?>>Priest</option>
                                <option value="Bishop"  <?php selected( $rank, 'Bishop' ); ?>>Bishop</option>
                            </select></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Ordinand</h2>
                    <table class="form-table">
                        <tr><th><label>First Name *</label></th>
                            <td><input type="text" name="first_name" class="regular-text" value="<?php echo esc_attr( $r->first_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Middle Name</label></th>
                            <td><input type="text" name="middle_name" class="regular-text" value="<?php echo esc_attr( $r->middle_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Last Name *</label></th>
                            <td><input type="text" name="last_name" class="regular-text" value="<?php echo esc_attr( $r->last_name ?? '' ); ?>" required></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Presiding Bishop</h2>
                    <table class="form-table">
                        <tr><th><label>Presiding Bishop *</label></th>
                            <td><input type="text" name="presiding_bishop" class="regular-text" value="<?php echo esc_attr( $r->presiding_bishop ?? '' ); ?>" required placeholder="Full name and title"></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Co-Consecrators</h2>
                    <p class="description">Required for episcopal ordinations. Leave blank for diaconal and presbyteral ordinations.</p>
                    <table class="form-table">
                        <tr><th><label>Co-Consecrator 1</label></th>
                            <td><input type="text" name="co_consecrator1" class="regular-text" value="<?php echo esc_attr( $r->co_consecrator1 ?? '' ); ?>" placeholder="Full name and title"></td></tr>
                        <tr><th><label>Co-Consecrator 2</label></th>
                            <td><input type="text" name="co_consecrator2" class="regular-text" value="<?php echo esc_attr( $r->co_consecrator2 ?? '' ); ?>" placeholder="Full name and title"></td></tr>
                        <tr><th><label>Co-Consecrator 3</label></th>
                            <td><input type="text" name="co_consecrator3" class="regular-text" value="<?php echo esc_attr( $r->co_consecrator3 ?? '' ); ?>" placeholder="Full name and title"></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Location</h2>
                    <table class="form-table">
                        <tr><th><label>Parish</label></th>
                            <td><select name="parish_id"><?php echo OCCI_Database::parish_dropdown( $r->parish_id ?? 0 ); ?></select>
                            <p class="description">If the parish is not listed, add it under <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-parishes' ) ); ?>">Parishes</a>.</p></td></tr>
                        <tr><th><label>Alternate Location</label></th>
                            <td><input type="text" name="alt_location" class="regular-text" value="<?php echo esc_attr( $r->alt_location ?? '' ); ?>" placeholder="If the ordination did not occur at the parish"></td></tr>
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
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-ordinations' ) ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    private static function render_view( $r ) {
        if ( ! $r ) { echo '<div class="wrap"><p>Record not found.</p></div>'; return; }
        $co_cons = array_filter( [ $r->co_consecrator1, $r->co_consecrator2, $r->co_consecrator3 ] );
        ?>
        <div class="wrap occi-wrap">
            <h1>Ordination Record</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-ordinations' ) ); ?>">&larr; Back to Register</a>
            <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-ordinations&action=edit&id=' . $r->id ) ); ?>" class="button button-secondary" style="margin-left:10px">Edit Record</a>
            <?php endif; ?>
            <button onclick="window.print()" class="button" style="margin-left:10px">Print Record</button>
            <?php echo OCCI_Certificates::certificate_button( 'ordination', $r->id ); ?>
            <div class="occi-view-record" id="occi-print-area">
                <div class="occi-cert-header">
                    <h2>OCCI Sacramental Record</h2>
                    <h3>ORDINATION REGISTER</h3>
                    <?php if ( $r->parish_name ) : ?>
                    <p><?php echo esc_html( $r->parish_name . ' &bull; ' . $r->parish_city . ', ' . $r->parish_state ); ?></p>
                    <?php endif; ?>
                </div>
                <table class="occi-view-table">
                    <tr>
                        <th>Date of Ordination</th>
                        <td><?php echo esc_html( occi_format_date( $r->ordination_date ) ); ?></td>
                        <th>Rank</th>
                        <td><strong><?php echo esc_html( $r->ordination_rank ); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Ordinand</th>
                        <td colspan="3"><strong><?php echo esc_html( strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' ) ); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Presiding Bishop</th>
                        <td colspan="3"><?php echo esc_html( $r->presiding_bishop ); ?></td>
                    </tr>
                    <tr>
                        <th>Co-Consecrators</th>
                        <td colspan="3"><?php echo esc_html( $co_cons ? implode( '; ', $co_cons ) : 'None' ); ?></td>
                    </tr>
                    <tr>
                        <th>Location</th>
                        <td colspan="3"><?php
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
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_save_ordination', 'occi_nonce' ) ) {
            wp_die( 'Access denied.' );
        }
        global $wpdb;
        $data = [
            'ordination_date'  => sanitize_text_field( $_POST['ordination_date'] ?? '' ),
            'first_name'       => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'middle_name'      => sanitize_text_field( $_POST['middle_name'] ?? '' ),
            'last_name'        => sanitize_text_field( $_POST['last_name'] ?? '' ),
            'ordination_rank'  => sanitize_text_field( $_POST['ordination_rank'] ?? '' ),
            'presiding_bishop' => sanitize_text_field( $_POST['presiding_bishop'] ?? '' ),
            'co_consecrator1'  => sanitize_text_field( $_POST['co_consecrator1'] ?? '' ),
            'co_consecrator2'  => sanitize_text_field( $_POST['co_consecrator2'] ?? '' ),
            'co_consecrator3'  => sanitize_text_field( $_POST['co_consecrator3'] ?? '' ),
            'parish_id'        => intval( $_POST['parish_id'] ?? 0 ) ?: null,
            'alt_location'     => sanitize_text_field( $_POST['alt_location'] ?? '' ),
            'notations'        => sanitize_textarea_field( $_POST['notations'] ?? '' ),
        ];
        $id = intval( $_POST['record_id'] ?? 0 );
        if ( $id ) {
            $wpdb->update( "{$wpdb->prefix}occi_ordinations", $data, [ 'id' => $id ] );
        } else {
            $wpdb->insert( "{$wpdb->prefix}occi_ordinations", $data );
        }
        wp_redirect( admin_url( 'admin.php?page=occi-ordinations&saved=1' ) );
        exit;
    }

    public static function delete() {
        $id = intval( $_GET['id'] ?? 0 );
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_delete_ordination_' . $id ) ) {
            wp_die( 'Access denied.' );
        }
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occi_ordinations", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occi-ordinations&deleted=1' ) );
        exit;
    }
}
