<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Format a date string for display in the register.
 * Prints month name rather than number per handbook guidelines.
 */
function occi_format_date( $date_string ) {
    if ( empty( $date_string ) || $date_string === '0000-00-00' ) return '';
    $ts = strtotime( $date_string );
    return $ts ? date( 'M. j, Y', $ts ) : $date_string;
}
