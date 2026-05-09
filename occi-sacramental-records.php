<?php
/**
 * Plugin Name:       OCCI Sacramental Records
 * Plugin URI:        https://myocci.org
 * Description:       National sacramental record database for Old Catholic Churches International. Manages Baptism, Confirmation, Marriage, Death, First Communion, and Ordination registers.
 * Version:           1.0.7
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Old Catholic Churches International
 * Author URI:        https://myocci.org
 * License:           GPL-2.0-or-later
 * Text Domain:       occi-sacramental-records
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'OCCI_SR_VERSION',    '1.0.7' );
define( 'OCCI_SR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OCCI_SR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once OCCI_SR_PLUGIN_DIR . 'includes/functions.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-database.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-admin.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-parishes.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-baptism.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-confirmation.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-marriage.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-death.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-communion.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-ordination.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-certificates.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-report.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-import-export.php';
require_once OCCI_SR_PLUGIN_DIR . 'includes/class-occi-updater.php';

register_activation_hook( __FILE__, [ 'OCCI_Database', 'install' ] );
register_deactivation_hook( __FILE__, [ 'OCCI_Database', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    if ( get_option( 'occi_sr_db_version' ) !== OCCI_SR_VERSION ) {
        OCCI_Database::install();
    }
    OCCI_Admin::init();
    OCCI_Parishes::init();
    OCCI_Baptism::init();
    OCCI_Confirmation::init();
    OCCI_Marriage::init();
    OCCI_Death::init();
    OCCI_Communion::init();
    OCCI_Ordination::init();
    OCCI_Certificates::init();
    OCCI_Report::init();
    OCCI_ImportExport::init();
    OCCI_Updater::init();
} );
