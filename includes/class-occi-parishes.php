<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Parishes {

    public static function init() {
        add_action( 'admin_post_occi_save_parish',   [ __CLASS__, 'save' ] );
        add_action( 'admin_post_occi_delete_parish', [ __CLASS__, 'delete' ] );
    }

    public static function page() {
        global $wpdb;
        if ( ! current_user_can( 'occi_manage_records' ) ) { wp_die( 'Access denied.' ); }

        $message = '';
        if ( isset( $_GET['saved'] ) )   $message = '<div class="notice notice-success is-dismissible"><p>Parish saved.</p></div>';
        if ( isset( $_GET['deleted'] ) ) $message = '<div class="notice notice-success is-dismissible"><p>Parish deleted.</p></div>';

        $editing = null;
        if ( isset( $_GET['action'], $_GET['id'] ) && $_GET['action'] === 'edit' ) {
            $editing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}occi_parishes WHERE id = %d",
                intval( $_GET['id'] )
            ) );
        }

        $parishes = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}occi_parishes ORDER BY state, city, name" );
        $default_url = OCCI_SR_PLUGIN_URL . 'assets/images/certificate-template.png';
        ?>
        <div class="wrap occi-wrap">
            <h1>Parish Registry</h1>
            <?php echo $message; ?>
            <div class="occi-two-col">
                <!-- Parish list -->
                <div class="occi-col-main">
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr>
                            <th>Parish Name</th><th>City</th><th>State</th><th>Certificate</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if ( $parishes ) : foreach ( $parishes as $p ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $p->name ); ?></strong></td>
                                <td><?php echo esc_html( $p->city ); ?></td>
                                <td><?php echo esc_html( $p->state ); ?></td>
                                <td>
                                    <?php if ( $p->cert_template_url ) : ?>
                                        <img src="<?php echo esc_url( $p->cert_template_url ); ?>"
                                             style="height:32px; border:1px solid #ddd; border-radius:2px;"
                                             title="Parish-specific template">
                                    <?php else : ?>
                                        <span style="color:#888; font-size:0.85em; font-style:italic;">Using OCCI default</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-parishes&action=edit&id=' . $p->id ) ); ?>">Edit</a> |
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occi_delete_parish&id=' . $p->id ), 'occi_delete_parish_' . $p->id ) ); ?>"
                                       onclick="return confirm('Delete this parish? This cannot be undone.')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="5">No parishes found. Add one using the form.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add / edit form -->
                <div class="occi-col-side">
                    <div class="occi-form-box">
                        <h2><?php echo $editing ? 'Edit Parish' : 'Add Parish'; ?></h2>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'occi_save_parish', 'occi_nonce' ); ?>
                            <input type="hidden" name="action" value="occi_save_parish">
                            <?php if ( $editing ) : ?>
                            <input type="hidden" name="parish_id" value="<?php echo esc_attr( $editing->id ); ?>">
                            <?php endif; ?>
                            <table class="form-table">
                                <tr><th><label>Parish Name *</label></th>
                                    <td><input type="text" name="name" class="regular-text"
                                               value="<?php echo esc_attr( $editing->name ?? '' ); ?>" required></td></tr>
                                <tr><th><label>City *</label></th>
                                    <td><input type="text" name="city" class="regular-text"
                                               value="<?php echo esc_attr( $editing->city ?? '' ); ?>" required></td></tr>
                                <tr><th><label>State *</label></th>
                                    <td><input type="text" name="state" class="small-text" maxlength="50"
                                               value="<?php echo esc_attr( $editing->state ?? '' ); ?>" required></td></tr>

                                <!-- Per-parish certificate template -->
                                <tr><th><label>Certificate Template</label></th>
                                    <td>
                                        <?php $current_cert = $editing->cert_template_url ?? ''; ?>
                                        <?php if ( $current_cert ) : ?>
                                        <img id="occi-parish-cert-preview"
                                             src="<?php echo esc_url( $current_cert ); ?>"
                                             style="max-width:140px; max-height:80px; display:block; border:1px solid #ddd; margin-bottom:6px;">
                                        <?php else : ?>
                                        <img id="occi-parish-cert-preview"
                                             src="<?php echo esc_url( $default_url ); ?>"
                                             style="max-width:140px; max-height:80px; display:block; border:1px solid #ddd; margin-bottom:6px; opacity:0.5;"
                                             title="OCCI default (no parish-specific template set)">
                                        <?php endif; ?>
                                        <input type="url" name="cert_template_url" id="occi-parish-cert-url"
                                               class="regular-text"
                                               value="<?php echo esc_attr( $current_cert ); ?>"
                                               placeholder="Leave blank to use OCCI default">
                                        <br><br>
                                        <button type="button" class="button" id="occi-parish-cert-btn">
                                            Select via Media Library
                                        </button>
                                        <?php if ( $current_cert ) : ?>
                                        <button type="button" class="button" id="occi-parish-cert-clear">
                                            Clear (use OCCI default)
                                        </button>
                                        <?php endif; ?>
                                        <p class="description">Optional. If set, this image will be used for all certificates issued by this parish instead of the global OCCI template.</p>
                                    </td></tr>
                            </table>
                            <p>
                                <button type="submit" class="button button-primary">
                                    <?php echo $editing ? 'Update Parish' : 'Add Parish'; ?>
                                </button>
                                <?php if ( $editing ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-parishes' ) ); ?>" class="button">Cancel</a>
                                <?php endif; ?>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#occi-parish-cert-btn').on('click', function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: 'Select Parish Certificate Template',
                    button: { text: 'Use this image' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#occi-parish-cert-url').val(att.url);
                    $('#occi-parish-cert-preview').attr('src', att.url).css('opacity', 1);
                });
                frame.open();
            });
            $('#occi-parish-cert-clear').on('click', function(e) {
                e.preventDefault();
                $('#occi-parish-cert-url').val('');
                $('#occi-parish-cert-preview').attr('src', '<?php echo esc_js( $default_url ); ?>').css('opacity', 0.5);
            });
        });
        </script>
        <?php
    }

    public static function save() {
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_save_parish', 'occi_nonce' ) ) {
            wp_die( 'Access denied.' );
        }
        global $wpdb;
        $data = [
            'name'              => sanitize_text_field( $_POST['name']  ?? '' ),
            'city'              => sanitize_text_field( $_POST['city']  ?? '' ),
            'state'             => sanitize_text_field( $_POST['state'] ?? '' ),
            'cert_template_url' => esc_url_raw( $_POST['cert_template_url'] ?? '' ) ?: null,
        ];
        $id = intval( $_POST['parish_id'] ?? 0 );
        if ( $id ) {
            $wpdb->update( "{$wpdb->prefix}occi_parishes", $data, [ 'id' => $id ] );
        } else {
            $wpdb->insert( "{$wpdb->prefix}occi_parishes", $data );
        }
        wp_redirect( admin_url( 'admin.php?page=occi-parishes&saved=1' ) );
        exit;
    }

    public static function delete() {
        $id = intval( $_GET['id'] ?? 0 );
        if ( ! current_user_can( 'occi_manage_records' ) || ! check_admin_referer( 'occi_delete_parish_' . $id ) ) {
            wp_die( 'Access denied.' );
        }
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occi_parishes", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occi-parishes&deleted=1' ) );
        exit;
    }
}
