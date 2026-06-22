<?php

namespace ESEO\Modules\Redirects;

class Redirects {

    public function init() {
        add_action( 'template_redirect', [ $this, 'intercept_404' ] );
    }

    public function intercept_404() {
        if ( ! is_404() ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'eseo_redirects';

        // Check if table exists (avoids error if plugin wasn't fully activated)
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
            return;
        }

        $current_url = sanitize_text_field( $_SERVER['REQUEST_URI'] );
        $current_url = ltrim( $current_url, '/' );

        // Direct match
        $query = $wpdb->prepare( "SELECT * FROM $table_name WHERE url_from = %s AND status = 'active' LIMIT 1", $current_url );
        $redirect = $wpdb->get_row( $query );

        if ( $redirect ) {
            // Update hits
            $wpdb->query( $wpdb->prepare( "UPDATE $table_name SET hits = hits + 1, last_accessed = %s WHERE id = %d", current_time('mysql'), $redirect->id ) );

            $to_url = $redirect->url_to;
            if ( strpos( $to_url, 'http' ) !== 0 ) {
                $to_url = home_url( '/' . ltrim( $to_url, '/' ) );
            }

            $type = intval( $redirect->type );
            if ( ! in_array( $type, [ 301, 302, 307, 410, 451 ] ) ) {
                $type = 301;
            }

            wp_redirect( $to_url, $type );
            exit;
        }
    }
}
