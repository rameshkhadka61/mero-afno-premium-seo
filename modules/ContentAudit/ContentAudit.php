<?php

namespace ESEO\Modules\ContentAudit;

class ContentAudit {

    public function init() {
        add_action( 'save_post', [ $this, 'schedule_content_audit' ], 20, 2 );
        add_action( 'eseo_async_content_audit', [ $this, 'process_content_audit' ] );
    }

    public function schedule_content_audit( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( $post->post_status !== 'publish' ) {
            return;
        }

        // Schedule async event to process links so we don't slow down saving
        if ( ! wp_next_scheduled( 'eseo_async_content_audit', [ $post_id ] ) ) {
            wp_schedule_single_event( time(), 'eseo_async_content_audit', [ $post_id ] );
        }
    }

    public function process_content_audit( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'eseo_links';
        
        // Skip if table doesn't exist yet
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
            return;
        }

        // Delete old links for this post
        $wpdb->delete( $table_name, [ 'post_id' => $post_id ], [ '%d' ] );

        // Extract links from content
        $content = $post->post_content;
        $site_url = home_url();

        if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches ) ) {
            $urls = $matches[1];
            $anchors = $matches[2];

            foreach ( $urls as $index => $url ) {
                $type = ( strpos( $url, $site_url ) !== false || strpos( $url, '/' ) === 0 ) ? 'internal' : 'external';
                $anchor = strip_tags( $anchors[ $index ] );

                $wpdb->insert(
                    $table_name,
                    [
                        'post_id'     => $post_id,
                        'target_url'  => $url,
                        'anchor_text' => $anchor,
                        'type'        => $type,
                        'status'      => 'ok' // Can be updated by a background checker later
                    ],
                    [ '%d', '%s', '%s', '%s', '%s' ]
                );
            }
        }
    }
}
