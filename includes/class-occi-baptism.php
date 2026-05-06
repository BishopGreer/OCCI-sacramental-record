<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Baptism {

    public static function init() {
        add_action( 'admin_post_occi_save_baptism',   [ __CLASS__, 'save' ] );
        add_action( 'admin_post_occi_delete_baptism', [ __CLASS__, 'delete' ] );
    }

    public static function page() {
        global $wpdb;
        if ( ! current_user_can( 'occi_view_records' ) ) { wp_die( 'Access denied.' ); }

        $action = $_GET['action'] ?? 'list';
        $id     = intval( $_GET['id'] ?? 0 );

        if ( $action === 'add' || $action === 'edit' ) {
            $record = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT b.*, p.name AS parish_name FROM {$wpdb->prefix}occi_baptisms b LEFT JOIN {$wpdb->prefix}occi_parishes p ON b.parish_id=p.id WHERE b.id=%d", $id ) ) : null;
            self::render_form( $record );
        } elseif ( $action === 'view' && $id ) {
            $record = $wpdb->get_row( $wpdb->prepare( "SELECT b.*, p.name AS parish_name, p.city AS parish_city, p.state AS parish_state FROM {$wpdb->prefix}occi_baptisms b LEFT JOIN {$wpdb->prefix}occi_parishes p ON b.parish_id=p.id WHERE b.id=%d", $id ) );
            self::render_view( $record );
        } else {
            self::render_list();
        }
    }

    private static function render_list() {
        global $wpdb;
        $search    = sanitize_text_field( $_GET['s'] ?? '' );
        $date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_GET['date_to'] ?? '' );
        $orderby   = in_array( $_GET['orderby'] ?? '', [ 'baptism_date', 'last_name', 'parish_name' ] ) ? $_GET['orderby'] : 'baptism_date';
        $order     = ( ( $_GET['order'] ?? 'ASC' ) === 'DESC' ) ? 'DESC' : 'ASC';

        $where = 'WHERE 1=1';
        $args  = [];
        if ( $search ) {
            $where .= ' AND (b.last_name LIKE %s OR b.first_name LIKE %s OR b.father_last_name LIKE %s OR b.mother_maiden_name LIKE %s)';
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $args   = array_merge( $args, [ $like, $like, $like, $like ] );
        }
        if ( $date_from ) { $where .= ' AND b.baptism_date >= %s'; $args[] = $date_from; }
        if ( $date_to )   { $where .= ' AND b.baptism_date <= %s'; $args[] = $date_to; }

        $ob_col = $orderby === 'parish_name' ? 'p.name' : "b.$orderby";
        $sql = "SELECT b.*, p.name AS parish_name, p.city AS parish_city FROM {$wpdb->prefix}occi_baptisms b LEFT JOIN {$wpdb->prefix}occi_parishes p ON b.parish_id=p.id $where ORDER BY $ob_col $order";
        $records = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );

        $message = '';
        if ( isset( $_GET['saved'] ) )   $message = '<div class="notice notice-success is-dismissible"><p>Baptism record saved.</p></div>';
        if ( isset( $_GET['deleted'] ) ) $message = '<div class="notice notice-success is-dismissible"><p>Baptism record deleted.</p></div>';
        ?>
        <div class="wrap occi-wrap">
            <h1>Baptism Register
                <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-baptisms&action=add' ) ); ?>" class="page-title-action">Add New Entry</a>
                <?php endif; ?>
            </h1>
            <?php echo $message; ?>
            <form method="get" class="occi-search-form">
                <input type="hidden" name="page" value="occi-baptisms">
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by name..." class="regular-text">
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" title="Date From">
                <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" title="Date To">
                <button type="submit" class="button">Search</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-baptisms' ) ); ?>" class="button">Reset</a>
            </form>
            <table class="wp-list-table widefat fixed striped occi-register-table">
                <thead><tr>
                    <th><?php echo self::sort_link( 'baptism_date', 'Date', $orderby, $order ); ?></th>
                    <th><?php echo self::sort_link( 'last_name', 'Name of Baptized', $orderby, $order ); ?></th>
                    <th>Father</th>
                    <th>Mother (Maiden)</th>
                    <th>Sponsors</th>
                    <th>Minister</th>
                    <th><?php echo self::sort_link( 'parish_name', 'Parish', $orderby, $order ); ?></th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if ( $records ) : foreach ( $records as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( occi_format_date( $r->baptism_date ) ); ?></td>
                    <td><strong><?php echo esc_html( strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' ) ); ?></strong></td>
                    <td><?php echo esc_html( $r->father_first_name ? $r->father_last_name . ', ' . $r->father_first_name : '&mdash;' ); ?></td>
                    <td><?php echo esc_html( $r->mother_first_name ? $r->mother_last_name . ', ' . $r->mother_first_name . ( $r->mother_maiden_name ? ' (née ' . $r->mother_maiden_name . ')' : '' ) : '&mdash;' ); ?></td>
                    <td class="occi-small"><?php echo esc_html( implode( '; ', array_filter( [ $r->sponsor1_name, $r->sponsor2_name ] ) ) ?: '&mdash;' ); ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->minister_name ); ?></td>
                    <td class="occi-small"><?php echo esc_html( $r->parish_name ? $r->parish_name . ', ' . $r->parish_city : ( $r->alt_location ?: '&mdash;' ) ); ?></td>
                    <td class="occi-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-baptisms&action=view&id=' . $r->id ) ); ?>">View</a>
                        <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
                        | <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-baptisms&action=edit&id=' . $r->id ) ); ?>">Edit</a>
                        | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occi_delete_baptism&id=' . $r->id ), 'occi_delete_baptism_' . $r->id ) ); ?>" class="occi-delete" onclick="return confirm('Delete this baptism record? This action cannot be undone.')">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else : ?>
                <tr><td colspan="8">No baptism records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <p class="occi-count"><?php echo count( $records ); ?> record(s) found.</p>
        </div>
        <?php
    }

    private static function render_form( $r = null ) {
        $is_edit = ! is_null( $r );
        $message = isset( $_GET['error'] ) ? '<div class="notice notice-error"><p>Please fill in all required fields.</p></div>' : '';
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo $is_edit ? 'Edit Baptism Record' : 'New Baptism Entry'; ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-baptisms' ) ); ?>">&larr; Back to Register</a>
            <?php echo $message; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="occi-form">
                <?php wp_nonce_field( 'occi_save_baptism', 'occi_nonce' ); ?>
                <input type="hidden" name="action" value="occi_save_baptism">
                <?php if ( $is_edit ) : ?><input type="hidden" name="record_id" value="<?php echo esc_attr( $r->id ); ?>"><?php endif; ?>

                <div class="occi-section">
                    <h2>Baptism Details</h2>
                    <table class="form-table">
                        <tr><th><label>Date of Baptism *</label></th>
                            <td><input type="date" name="baptism_date" value="<?php echo esc_attr( $r->baptism_date ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Date of Birth</label></th>
                            <td><input type="date" name="birth_date" value="<?php echo esc_attr( $r->birth_date ?? '' ); ?>"></td></tr>
                        <tr><th><label>Place of Birth</label></th>
                            <td><input type="text" name="birth_place" class="regular-text" value="<?php echo esc_attr( $r->birth_place ?? '' ); ?>"></td></tr>
                        <tr><th><label>Record Book / Volume</label></th>
                            <td><input type="text" name="record_book" class="small-text" value="<?php echo esc_attr( $r->record_book ?? '' ); ?>"></td></tr>
                        <tr><th><label>Page Number</label></th>
                            <td><input type="text" name="page_number" class="small-text" value="<?php echo esc_attr( $r->page_number ?? '' ); ?>"></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Person Baptized</h2>
                    <table class="form-table">
                        <tr><th><label>First Name *</label></th>
                            <td><input type="text" name="first_name" class="regular-text" value="<?php echo esc_attr( $r->first_name ?? '' ); ?>" required placeholder="As given at baptism"></td></tr>
                        <tr><th><label>Middle Name</label></th>
                            <td><input type="text" name="middle_name" class="regular-text" value="<?php echo esc_attr( $r->middle_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Last Name *</label></th>
                            <td><input type="text" name="last_name" class="regular-text" value="<?php echo esc_attr( $r->last_name ?? '' ); ?>" required></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Father</h2>
                    <table class="form-table">
                        <tr><th><label>First Name</label></th>
                            <td><input type="text" name="father_first_name" class="regular-text" value="<?php echo esc_attr( $r->father_first_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Middle Name</label></th>
                            <td><input type="text" name="father_middle_name" class="regular-text" value="<?php echo esc_attr( $r->father_middle_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Last Name</label></th>
                            <td><input type="text" name="father_last_name" class="regular-text" value="<?php echo esc_attr( $r->father_last_name ?? '' ); ?>"></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Mother</h2>
                    <table class="form-table">
                        <tr><th><label>First Name</label></th>
                            <td><input type="text" name="mother_first_name" class="regular-text" value="<?php echo esc_attr( $r->mother_first_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Middle Name</label></th>
                            <td><input type="text" name="mother_middle_name" class="regular-text" value="<?php echo esc_attr( $r->mother_middle_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Last Name (Current / Married)</label></th>
                            <td><input type="text" name="mother_last_name" class="regular-text" value="<?php echo esc_attr( $r->mother_last_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Maiden Name *</label></th>
                            <td><input type="text" name="mother_maiden_name" class="regular-text" value="<?php echo esc_attr( $r->mother_maiden_name ?? '' ); ?>" placeholder="Surname at birth (required)"></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Sponsors / Godparents</h2>
                    <p class="description">Maximum two sponsors: one male and/or one female (Canon 873).</p>
                    <table class="form-table">
                        <tr><th><label>Sponsor 1 Full Name</label></th>
                            <td><input type="text" name="sponsor1_name" class="regular-text" value="<?php echo esc_attr( $r->sponsor1_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Sponsor 1 Gender</label></th>
                            <td><select name="sponsor1_gender">
                                <option value="">-- Select --</option>
                                <option value="M" <?php selected( $r->sponsor1_gender ?? '', 'M' ); ?>>Male</option>
                                <option value="F" <?php selected( $r->sponsor1_gender ?? '', 'F' ); ?>>Female</option>
                            </select></td></tr>
                        <tr><th><label>Sponsor 1 is Proxy?</label></th>
                            <td><label><input type="checkbox" name="sponsor1_is_proxy" value="1" <?php checked( $r->sponsor1_is_proxy ?? 0, 1 ); ?>> This person is a proxy</label>
                            <br><input type="text" name="sponsor1_proxy_for" class="regular-text" value="<?php echo esc_attr( $r->sponsor1_proxy_for ?? '' ); ?>" placeholder="Proxy for (name of actual sponsor)"></td></tr>
                        <tr><th><label>Sponsor 2 Full Name</label></th>
                            <td><input type="text" name="sponsor2_name" class="regular-text" value="<?php echo esc_attr( $r->sponsor2_name ?? '' ); ?>"></td></tr>
                        <tr><th><label>Sponsor 2 Gender</label></th>
                            <td><select name="sponsor2_gender">
                                <option value="">-- Select --</option>
                                <option value="M" <?php selected( $r->sponsor2_gender ?? '', 'M' ); ?>>Male</option>
                                <option value="F" <?php selected( $r->sponsor2_gender ?? '', 'F' ); ?>>Female</option>
                            </select></td></tr>
                        <tr><th><label>Sponsor 2 is Proxy?</label></th>
                            <td><label><input type="checkbox" name="sponsor2_is_proxy" value="1" <?php checked( $r->sponsor2_is_proxy ?? 0, 1 ); ?>> This person is a proxy</label>
                            <br><input type="text" name="sponsor2_proxy_for" class="regular-text" value="<?php echo esc_attr( $r->sponsor2_proxy_for ?? '' ); ?>" placeholder="Proxy for (name of actual sponsor)"></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Officiating Minister</h2>
                    <table class="form-table">
                        <tr><th><label>Minister Name *</label></th>
                            <td><input type="text" name="minister_name" class="regular-text" value="<?php echo esc_attr( $r->minister_name ?? '' ); ?>" required></td></tr>
                        <tr><th><label>Minister Type</label></th>
                            <td><select name="minister_type">
                                <option value="">-- Select --</option>
                                <option value="Priest" <?php selected( $r->minister_type ?? '', 'Priest' ); ?>>Priest</option>
                                <option value="Deacon" <?php selected( $r->minister_type ?? '', 'Deacon' ); ?>>Deacon</option>
                                <option value="Bishop" <?php selected( $r->minister_type ?? '', 'Bishop' ); ?>>Bishop</option>
                                <option value="Layperson" <?php selected( $r->minister_type ?? '', 'Layperson' ); ?>>Layperson (Emergency)</option>
                            </select></td></tr>
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
                            <td><input type="text" name="alt_location" class="regular-text" value="<?php echo esc_attr( $r->alt_location ?? '' ); ?>" placeholder="If baptism did not occur at the parish"></td></tr>
                    </table>
                </div>

                <div class="occi-section">
                    <h2>Notations</h2>
                    <table class="form-table">
                        <tr><th><label>Notations</label></th>
                            <td><textarea name="notations" rows="4" class="large-text"><?php echo esc_textarea( $r->notations ?? '' ); ?></textarea>
                            <p class="description">Include notations for conditional baptism, reception into full communion, subsequent sacraments (confirmation, marriage, ordination, etc.), annulments, laicizations, and other canonical matters.</p></td></tr>
                        <tr><th><label>Confidential</label></th>
                            <td><label><input type="checkbox" name="is_confidential" value="1" <?php checked( $r->is_confidential ?? 0, 1 ); ?>>
                            Mark this record as confidential (will not appear on certificates)</label></td></tr>
                    </table>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Record' : 'Save Record'; ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-baptisms' ) ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    private static function render_view( $r ) {
        if ( ! $r ) { echo '<div class="wrap"><p>Record not found.</p></div>'; return; }
        ?>
        <div class="wrap occi-wrap">
            <h1>Baptism Certificate Record</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-baptisms' ) ); ?>">&larr; Back to Register</a>
            <?php if ( current_user_can( 'occi_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-baptisms&action=edit&id=' . $r->id ) ); ?>" class="button button-secondary" style="margin-left:10px">Edit Record</a>
            <?php endif; ?>
            <button onclick="window.print()" class="button" style="margin-left:10px">Print Record</button>
            <?php echo OCCI_Certificates::certificate_button( 'baptism', $r->id ); ?>
            <div class="occi-view-record" id="occi-print-area">
                <div class="occi-cert-header">
                    <h2>OCCI Sacramental Record</h2>
                    <h3>BAPTISM REGISTER</h3>
                    <?php if ( $r->parish_name ) : ?>
                    <p><?php echo esc_html( $r->parish_name . ' &bull; ' . $r->parish_city . ', ' . $r->parish_state ); ?></p>
                    <?php endif; ?>
                </div>
                <table class="occi-view-table">
                    <tr><th>Date of Baptism</th><td><?php echo esc_html( occi_format_date( $r->baptism_date ) ); ?></td>
                        <th>Date of Birth</th><td><?php echo esc_html( $r->birth_date ? occi_format_date( $r->birth_date ) : '&mdash;' ); ?></td></tr>
                    <tr><th>Place of Birth</th><td colspan="3"><?php echo esc_html( $r->birth_place ?: '&mdash;' ); ?></td></tr>
                    <tr><th>Name of Baptized</th><td colspan="3"><strong><?php echo esc_html( strtoupper( $r->last_name ) . ', ' . $r->first_name . ( $r->middle_name ? ' ' . $r->middle_name : '' ) ); ?></strong></td></tr>
                    <tr><th>Father</th><td><?php echo esc_html( $r->father_first_name ? $r->father_first_name . ( $r->father_middle_name ? ' ' . $r->father_middle_name : '' ) . ' ' . strtoupper( $r->father_last_name ) : '&mdash;' ); ?></td>
                        <th>Mother</th><td><?php echo esc_html( $r->mother_first_name ? $r->mother_first_name . ( $r->mother_middle_name ? ' ' . $r->mother_middle_name : '' ) . ' ' . strtoupper( $r->mother_last_name ) . ( $r->mother_maiden_name ? ', née ' . strtoupper( $r->mother_maiden_name ) : '' ) : '&mdash;' ); ?></td></tr>
                    <tr><th>Sponsor 1</th><td><?php echo esc_html( $r->sponsor1_name ? $r->sponsor1_name . ( $r->sponsor1_is_proxy ? ' [Proxy for ' . $r->sponsor1_proxy_for . ']' : '' ) : '&mdash;' ); ?></td>
                        <th>Sponsor 2</th><td><?php echo esc_html( $r->sponsor2_name ? $r->sponsor2_name . ( $r->sponsor2_is_proxy ? ' [Proxy for ' . $r->sponsor2_proxy_for . ']' : '' ) : '&mdash;' ); ?></td></tr>
                    <tr><th>Minister</th><td><?php echo esc_html( $r->minister_name . ( $r->minister_type ? ', ' . $r->minister_type : '' ) ); ?></td>
                        <th>Location</th><td><?php echo esc_html( $r->parish_name ? $r->parish_name . ', ' . $r->parish_city . ', ' . $r->parish_state : ( $r->alt_location ?: '&mdash;' ) ); ?><?php if ( $r->alt_location && $r->parish_name ) echo '<br><em>Alternate location: ' . esc_html( $r->alt_location ) . '</em>'; ?></td></tr>
                    <?php if ( $r->record_book || $r->page_number ) : ?>
                    <tr><th>Register Volume</th><td><?php echo esc_html( $r->record_book ?: '&mdash;' ); ?></td>
                        <th>Page</th><td><?php echo esc_html( $r->page_number ?: '&mdash;' ); ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Notations</th><td colspan="3"><?php echo nl2br( esc_html( $r->notations ?: 'No Notations' ) ); ?></td></tr>
                    <?php if ( $r->is_confidential ) : ?>
                    <tr><td colspan="4" class="occi-confidential">CONFIDENTIAL &mdash; Do not include on certificate</td></tr>
                    <?php endif; ?>
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
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_save_baptism', 'occi_nonce' ) ) {
            wp_die( 'Access denied.' );
        }
        global $wpdb;
        $data = [
            'baptism_date'        => sanitize_text_field( $_POST['baptism_date'] ?? '' ),
            'birth_date'          => sanitize_text_field( $_POST['birth_date'] ?? '' ) ?: null,
            'birth_place'         => sanitize_text_field( $_POST['birth_place'] ?? '' ),
            'first_name'          => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'middle_name'         => sanitize_text_field( $_POST['middle_name'] ?? '' ),
            'last_name'           => sanitize_text_field( $_POST['last_name'] ?? '' ),
            'father_first_name'   => sanitize_text_field( $_POST['father_first_name'] ?? '' ),
            'father_middle_name'  => sanitize_text_field( $_POST['father_middle_name'] ?? '' ),
            'father_last_name'    => sanitize_text_field( $_POST['father_last_name'] ?? '' ),
            'mother_first_name'   => sanitize_text_field( $_POST['mother_first_name'] ?? '' ),
            'mother_middle_name'  => sanitize_text_field( $_POST['mother_middle_name'] ?? '' ),
            'mother_last_name'    => sanitize_text_field( $_POST['mother_last_name'] ?? '' ),
            'mother_maiden_name'  => sanitize_text_field( $_POST['mother_maiden_name'] ?? '' ),
            'sponsor1_name'       => sanitize_text_field( $_POST['sponsor1_name'] ?? '' ),
            'sponsor1_gender'     => sanitize_text_field( $_POST['sponsor1_gender'] ?? '' ),
            'sponsor1_is_proxy'   => isset( $_POST['sponsor1_is_proxy'] ) ? 1 : 0,
            'sponsor1_proxy_for'  => sanitize_text_field( $_POST['sponsor1_proxy_for'] ?? '' ),
            'sponsor2_name'       => sanitize_text_field( $_POST['sponsor2_name'] ?? '' ),
            'sponsor2_gender'     => sanitize_text_field( $_POST['sponsor2_gender'] ?? '' ),
            'sponsor2_is_proxy'   => isset( $_POST['sponsor2_is_proxy'] ) ? 1 : 0,
            'sponsor2_proxy_for'  => sanitize_text_field( $_POST['sponsor2_proxy_for'] ?? '' ),
            'minister_name'       => sanitize_text_field( $_POST['minister_name'] ?? '' ),
            'minister_type'       => sanitize_text_field( $_POST['minister_type'] ?? '' ),
            'parish_id'           => intval( $_POST['parish_id'] ?? 0 ) ?: null,
            'alt_location'        => sanitize_text_field( $_POST['alt_location'] ?? '' ),
            'notations'           => sanitize_textarea_field( $_POST['notations'] ?? '' ),
            'is_confidential'     => isset( $_POST['is_confidential'] ) ? 1 : 0,
            'record_book'         => sanitize_text_field( $_POST['record_book'] ?? '' ),
            'page_number'         => sanitize_text_field( $_POST['page_number'] ?? '' ),
            'created_by'          => get_current_user_id(),
        ];
        if ( empty( $data['baptism_date'] ) || empty( $data['first_name'] ) || empty( $data['last_name'] ) || empty( $data['minister_name'] ) ) {
            wp_redirect( admin_url( 'admin.php?page=occi-baptisms&action=' . ( intval( $_POST['record_id'] ?? 0 ) ? 'edit&id=' . intval( $_POST['record_id'] ) : 'add' ) . '&error=1' ) );
            exit;
        }
        $id = intval( $_POST['record_id'] ?? 0 );
        if ( $id ) {
            $wpdb->update( "{$wpdb->prefix}occi_baptisms", $data, [ 'id' => $id ] );
        } else {
            $wpdb->insert( "{$wpdb->prefix}occi_baptisms", $data );
            $id = $wpdb->insert_id;
        }
        wp_redirect( admin_url( 'admin.php?page=occi-baptisms&saved=1' ) );
        exit;
    }

    public static function delete() {
        $id = intval( $_GET['id'] ?? 0 );
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_delete_baptism_' . $id ) ) {
            wp_die( 'Access denied.' );
        }
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occi_baptisms", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occi-baptisms&deleted=1' ) );
        exit;
    }

    private static function sort_link( $col, $label, $current_col, $current_order ) {
        $order = ( $current_col === $col && $current_order === 'ASC' ) ? 'DESC' : 'ASC';
        $url   = admin_url( 'admin.php?page=occi-baptisms&orderby=' . $col . '&order=' . $order );
        $arrow = $current_col === $col ? ( $current_order === 'ASC' ? ' &uarr;' : ' &darr;' ) : '';
        return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $arrow . '</a>';
    }
}
