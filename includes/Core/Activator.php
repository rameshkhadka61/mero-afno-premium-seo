<?php

namespace ESEO\Core;

class Activator {

    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Redirects table
        $table_name = $wpdb->prefix . 'eseo_redirects';
        
        $sql1 = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url_from varchar(191) NOT NULL,
            url_to varchar(255) NOT NULL,
            type varchar(10) DEFAULT '301' NOT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            hits int(11) DEFAULT 0 NOT NULL,
            last_accessed datetime NULL,
            PRIMARY KEY  (id),
            KEY url_from (url_from(191))
        ) $charset_collate;";

        // Internal Links Table
        $links_table_name = $wpdb->prefix . 'eseo_links';
        
        $sql2 = "CREATE TABLE $links_table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            target_url varchar(255) NOT NULL,
            anchor_text varchar(255) DEFAULT '' NOT NULL,
            type varchar(20) DEFAULT 'internal' NOT NULL,
            status varchar(20) DEFAULT 'ok' NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id)
        ) $charset_collate;";

        // 404 Logs Table
        $logs_table_name = $wpdb->prefix . 'eseo_404_logs';
        $sql3 = "CREATE TABLE $logs_table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(191) NOT NULL,
            referrer varchar(255) DEFAULT '' NOT NULL,
            user_agent varchar(255) DEFAULT '' NOT NULL,
            hits int(11) DEFAULT 1 NOT NULL,
            last_accessed datetime NULL,
            PRIMARY KEY  (id),
            KEY url (url(191))
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
        
        // Flush rewrite rules for sitemaps
        flush_rewrite_rules();
    }
}
