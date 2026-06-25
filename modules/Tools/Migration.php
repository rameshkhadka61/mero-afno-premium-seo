<?php

namespace ESEO\Modules\Tools;

class Migration {

    public function init() {
        add_action( 'wp_ajax_eseo_migrate_data', [ $this, 'ajax_migrate_data' ] );
    }

    public function render_tools_page() {
        ?>
        <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">
            <h1>Migration & Tools</h1>
            <p>Safely import your historical SEO metadata from other plugins into Mero SEO.</p>
            
            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px; max-width: 800px; margin-top: 20px;">
                <h2 style="margin-top:0;">SEO Data Importer</h2>
                <p>This process will copy your existing post metadata (Titles, Descriptions, Focus Keywords, Canonicals, and Robots) into our system. <strong>This is a non-destructive action.</strong> Your original data from the other plugins will not be deleted.</p>
                
                <div style="margin-top: 20px; padding: 15px; border: 1px solid #f0f0f1; border-radius: 4px; background: #f8f9fa;">
                    <h3>Yoast SEO</h3>
                    <p>Import `_yoast_wpseo_*` meta fields.</p>
                    <button class="button button-primary eseo-migrate-btn" data-plugin="yoast">Import Yoast Data</button>
                    <div class="eseo-progress-container" id="progress-yoast" style="display:none; margin-top: 15px;">
                        <div style="width: 100%; background: #ddd; border-radius: 3px; overflow: hidden;">
                            <div class="eseo-progress-bar" style="width: 0%; height: 10px; background: #2271b1;"></div>
                        </div>
                        <p class="eseo-progress-text" style="font-size: 12px; margin-top: 5px;">Starting migration...</p>
                    </div>
                </div>

                <div style="margin-top: 20px; padding: 15px; border: 1px solid #f0f0f1; border-radius: 4px; background: #f8f9fa;">
                    <h3>Rank Math</h3>
                    <p>Import `rank_math_*` meta fields.</p>
                    <button class="button button-primary eseo-migrate-btn" data-plugin="rankmath">Import Rank Math Data</button>
                    <div class="eseo-progress-container" id="progress-rankmath" style="display:none; margin-top: 15px;">
                        <div style="width: 100%; background: #ddd; border-radius: 3px; overflow: hidden;">
                            <div class="eseo-progress-bar" style="width: 0%; height: 10px; background: #2271b1;"></div>
                        </div>
                        <p class="eseo-progress-text" style="font-size: 12px; margin-top: 5px;">Starting migration...</p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.eseo-migrate-btn').on('click', function(e) {
                e.preventDefault();
                let plugin = $(this).data('plugin');
                let container = $('#progress-' + plugin);
                let bar = container.find('.eseo-progress-bar');
                let text = container.find('.eseo-progress-text');
                let btn = $(this);

                if (!confirm('Are you sure you want to start importing data from ' + plugin + '?')) {
                    return;
                }

                btn.prop('disabled', true);
                container.show();

                function processBatch(offset) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'eseo_migrate_data',
                            plugin: plugin,
                            offset: offset,
                            nonce: '<?php echo wp_create_nonce("eseo_migrate_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                let data = response.data;
                                let percentage = Math.round((data.offset / data.total) * 100);
                                if (percentage > 100) percentage = 100;

                                bar.css('width', percentage + '%');
                                text.text('Processed ' + data.offset + ' of ' + data.total + ' posts...');

                                if (data.done) {
                                    text.text('Migration Complete! ' + data.total + ' posts processed.');
                                    text.css('color', 'green');
                                    btn.prop('disabled', false).text('Import Completed');
                                } else {
                                    processBatch(data.offset);
                                }
                            } else {
                                text.text('Error: ' + response.data);
                                text.css('color', 'red');
                                btn.prop('disabled', false);
                            }
                        },
                        error: function() {
                            text.text('A server error occurred. Please try again.');
                            text.css('color', 'red');
                            btn.prop('disabled', false);
                        }
                    });
                }

                processBatch(0);
            });
        });
        </script>
        <?php
    }

    public function ajax_migrate_data() {
        check_ajax_referer( 'eseo_migrate_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $plugin = sanitize_text_field( $_POST['plugin'] );
        $offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
        $limit = 50; // Process 50 posts per request

        global $wpdb;

        // Get total posts
        $total_posts = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status IN ('publish', 'draft', 'pending', 'private', 'future') AND post_type NOT IN ('revision', 'nav_menu_item')" );

        if ( $offset >= $total_posts ) {
            wp_send_json_success( [ 'done' => true, 'offset' => $offset, 'total' => $total_posts ] );
        }

        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish', 'draft', 'pending', 'private', 'future') AND post_type NOT IN ('revision', 'nav_menu_item') ORDER BY ID ASC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ) );

        foreach ( $posts as $post ) {
            $post_id = $post->ID;

            if ( $plugin === 'yoast' ) {
                $this->migrate_yoast_post( $post_id );
            } elseif ( $plugin === 'rankmath' ) {
                $this->migrate_rankmath_post( $post_id );
            }
        }

        wp_send_json_success( [ 'done' => false, 'offset' => $offset + count($posts), 'total' => $total_posts ] );
    }

    private function migrate_yoast_post( $post_id ) {
        $title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
        $desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
        $focus_kw = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
        $canonical = get_post_meta( $post_id, '_yoast_wpseo_canonical', true );
        $noindex = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
        $nofollow = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true );

        if ( $title ) update_post_meta( $post_id, '_eseo_meta_title', $title );
        if ( $desc ) update_post_meta( $post_id, '_eseo_meta_description', $desc );
        if ( $focus_kw ) update_post_meta( $post_id, '_eseo_focus_keyword', $focus_kw );
        if ( $canonical ) update_post_meta( $post_id, '_eseo_canonical_url', $canonical );

        if ( $noindex == '1' ) {
            update_post_meta( $post_id, '_eseo_meta_robots_index', 'noindex' );
        } elseif ( $noindex == '2' ) {
            update_post_meta( $post_id, '_eseo_meta_robots_index', 'index' );
        }

        if ( $nofollow == '1' ) {
            update_post_meta( $post_id, '_eseo_meta_robots_follow', 'nofollow' );
        }
    }

    private function migrate_rankmath_post( $post_id ) {
        $title = get_post_meta( $post_id, 'rank_math_title', true );
        $desc = get_post_meta( $post_id, 'rank_math_description', true );
        $focus_kw = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
        $canonical = get_post_meta( $post_id, 'rank_math_canonical_url', true );
        $robots = get_post_meta( $post_id, 'rank_math_robots', true );

        if ( $title ) update_post_meta( $post_id, '_eseo_meta_title', $title );
        if ( $desc ) update_post_meta( $post_id, '_eseo_meta_description', $desc );
        if ( $focus_kw ) update_post_meta( $post_id, '_eseo_focus_keyword', $focus_kw );
        if ( $canonical ) update_post_meta( $post_id, '_eseo_canonical_url', $canonical );

        if ( is_array( $robots ) ) {
            if ( in_array( 'noindex', $robots ) ) {
                update_post_meta( $post_id, '_eseo_meta_robots_index', 'noindex' );
            } elseif ( in_array( 'index', $robots ) ) {
                update_post_meta( $post_id, '_eseo_meta_robots_index', 'index' );
            }

            if ( in_array( 'nofollow', $robots ) ) {
                update_post_meta( $post_id, '_eseo_meta_robots_follow', 'nofollow' );
            }
        }
    }
}
