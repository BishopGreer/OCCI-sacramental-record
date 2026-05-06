<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCI_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        self::update_capabilities();

        // Parishes — includes per-parish certificate template URL
        dbDelta( "CREATE TABLE {$wpdb->prefix}occi_parishes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            city varchar(100) NOT NULL,
            state varchar(50) NOT NULL,
            cert_template_url varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name(100))
        ) $charset;" );

        // Baptisms
        dbDelta( "CREATE TABLE {$wpdb->prefix}occi_baptisms (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            baptism_date date NOT NULL,
            birth_date date DEFAULT NULL,
            birth_place varchar(255) DEFAULT NULL,
            first_name varchar(100) NOT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            father_first_name varchar(100) DEFAULT NULL,
            father_middle_name varchar(100) DEFAULT NULL,
            father_last_name varchar(100) DEFAULT NULL,
            mother_first_name varchar(100) DEFAULT NULL,
            mother_middle_name varchar(100) DEFAULT NULL,
            mother_last_name varchar(100) DEFAULT NULL,
            mother_maiden_name varchar(100) DEFAULT NULL,
            sponsor1_name varchar(255) DEFAULT NULL,
            sponsor1_gender varchar(1) DEFAULT NULL,
            sponsor1_is_proxy tinyint(1) DEFAULT 0,
            sponsor1_proxy_for varchar(255) DEFAULT NULL,
            sponsor2_name varchar(255) DEFAULT NULL,
            sponsor2_gender varchar(1) DEFAULT NULL,
            sponsor2_is_proxy tinyint(1) DEFAULT 0,
            sponsor2_proxy_for varchar(255) DEFAULT NULL,
            minister_name varchar(255) NOT NULL,
            minister_type varchar(50) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            alt_location varchar(255) DEFAULT NULL,
            notations text DEFAULT NULL,
            is_confidential tinyint(1) DEFAULT 0,
            record_book varchar(100) DEFAULT NULL,
            page_number varchar(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY baptism_date (baptism_date),
            KEY last_name (last_name(50))
        ) $charset;" );

        // Confirmations (flat per-person model)
        dbDelta( "CREATE TABLE {$wpdb->prefix}occi_confirmations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            confirmation_date date NOT NULL,
            bishop_name varchar(255) NOT NULL,
            first_name varchar(100) NOT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            saints_name varchar(100) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            alt_location varchar(255) DEFAULT NULL,
            notations text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY confirmation_date (confirmation_date),
            KEY last_name (last_name(50))
        ) $charset;" );

        // Marriages
        dbDelta( "CREATE TABLE {$wpdb->prefix}occi_marriages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            marriage_date date NOT NULL,
            party1_first_name varchar(100) NOT NULL,
            party1_middle_name varchar(100) DEFAULT NULL,
            party1_last_name varchar(100) NOT NULL,
            party1_maiden_name varchar(100) DEFAULT NULL,
            party1_birth_date date DEFAULT NULL,
            party2_first_name varchar(100) NOT NULL,
            party2_middle_name varchar(100) DEFAULT NULL,
            party2_last_name varchar(100) NOT NULL,
            party2_maiden_name varchar(100) DEFAULT NULL,
            party2_birth_date date DEFAULT NULL,
            witness1_name varchar(255) NOT NULL,
            witness2_name varchar(255) NOT NULL,
            minister_name varchar(255) NOT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            alt_location varchar(255) DEFAULT NULL,
            notations text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY marriage_date (marriage_date),
            KEY party1_last_name (party1_last_name(50)),
            KEY party2_last_name (party2_last_name(50))
        ) $charset;" );

        // Deaths
        dbDelta( "CREATE TABLE {$wpdb->prefix}occi_deaths (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            death_date date NOT NULL,
            first_name varchar(100) NOT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            burial_location varchar(255) DEFAULT NULL,
            burial_city varchar(100) DEFAULT NULL,
            burial_state varchar(50) DEFAULT NULL,
            funeral_date date DEFAULT NULL,
            funeral_presider varchar(255) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            is_graveside tinyint(1) DEFAULT 0,
            cemetery_name varchar(255) DEFAULT NULL,
            cemetery_city varchar(100) DEFAULT NULL,
            cemetery_state varchar(50) DEFAULT NULL,
            is_cremated tinyint(1) DEFAULT 0,
            ashes_interment_date date DEFAULT NULL,
            ashes_interment_place varchar(255) DEFAULT NULL,
            notations text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY death_date (death_date),
            KEY last_name (last_name(50))
        ) $charset;" );

        // First Communions
        dbDelta( "CREATE TABLE {$wpdb->prefix}occi_communions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            communion_date date NOT NULL,
            first_name varchar(100) NOT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            baptism_date date DEFAULT NULL,
            baptism_church varchar(255) DEFAULT NULL,
            baptism_city varchar(100) DEFAULT NULL,
            baptism_state varchar(50) DEFAULT NULL,
            presider varchar(255) NOT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            notations text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY communion_date (communion_date),
            KEY last_name (last_name(50))
        ) $charset;" );

        // Ordinations
        dbDelta( "CREATE TABLE {$wpdb->prefix}occi_ordinations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ordination_date date NOT NULL,
            first_name varchar(100) NOT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            ordination_rank varchar(50) NOT NULL,
            presiding_bishop varchar(255) NOT NULL,
            co_consecrator1 varchar(255) DEFAULT NULL,
            co_consecrator2 varchar(255) DEFAULT NULL,
            co_consecrator3 varchar(255) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            alt_location varchar(255) DEFAULT NULL,
            notations text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ordination_date (ordination_date),
            KEY last_name (last_name(50))
        ) $charset;" );

        update_option( 'occi_sr_db_version', OCCI_SR_VERSION );
    }

    /**
     * Assign capabilities to all roles at Contributor level and above.
     * Both occi_view_records and occi_manage_records are granted to
     * Contributor, Author, Editor, and Administrator.
     * Subscribers receive no access.
     */
    public static function update_capabilities() {
        $roles_with_access = [ 'contributor', 'author', 'editor', 'administrator' ];
        foreach ( $roles_with_access as $role_slug ) {
            $role = get_role( $role_slug );
            if ( $role ) {
                $role->add_cap( 'occi_view_records' );
                $role->add_cap( 'occi_manage_records' );
            }
        }
        // Explicitly revoke from Subscriber (safety measure)
        $subscriber = get_role( 'subscriber' );
        if ( $subscriber ) {
            $subscriber->remove_cap( 'occi_view_records' );
            $subscriber->remove_cap( 'occi_manage_records' );
        }
    }

    public static function deactivate() {
        // Capabilities and data persist on deactivation.
    }

    public static function get_parishes() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}occi_parishes ORDER BY name ASC" );
    }

    public static function parish_dropdown( $selected = 0 ) {
        $parishes = self::get_parishes();
        $html = '<option value="">-- Select Parish --</option>';
        foreach ( $parishes as $p ) {
            $sel  = selected( $selected, $p->id, false );
            $html .= '<option value="' . esc_attr( $p->id ) . '"' . $sel . '>'
                   . esc_html( $p->name . ', ' . $p->city . ', ' . $p->state )
                   . '</option>';
        }
        return $html;
    }
}
