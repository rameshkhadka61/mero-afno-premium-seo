<?php

namespace ESEO\Modules\TitlesMeta;

class PostList {

    public function init() {
        $post_types = get_post_types( [ 'public' => true ], 'names' );
        
        foreach ( $post_types as $pt ) {
            add_filter( "manage_{$pt}_posts_columns", [ $this, 'add_seo_column' ] );
            add_action( "manage_{$pt}_posts_custom_column", [ $this, 'render_seo_column' ], 10, 2 );
        }

        add_action( 'restrict_manage_posts', [ $this, 'add_seo_filter_dropdown' ] );
        add_action( 'pre_get_posts', [ $this, 'filter_posts_by_seo_score' ] );
    }

    public function add_seo_column( $columns ) {
        $columns['eseo_score'] = 'SEO Score';
        return $columns;
    }

    public function render_seo_column( $column, $post_id ) {
        if ( $column === 'eseo_score' ) {
            $title = get_post_meta( $post_id, '_eseo_meta_title', true );
            $desc = get_post_meta( $post_id, '_eseo_meta_description', true );
            $kw = get_post_meta( $post_id, '_eseo_focus_keyword', true );

            $score = 0;
            if ( ! empty($title) ) $score++;
            if ( ! empty($desc) ) $score++;
            if ( ! empty($kw) ) $score++;

            if ( $score == 3 ) {
                echo '<div style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#7ad03a;" title="Good"></div>';
            } elseif ( $score == 2 ) {
                echo '<div style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#e88a31;" title="OK"></div>';
            } elseif ( $score == 1 ) {
                echo '<div style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#dc3232;" title="Needs Improvement"></div>';
            } else {
                echo '<div style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#dcdde0;" title="Not Analyzed"></div>';
            }
        }
    }

    public function add_seo_filter_dropdown( $post_type ) {
        $public_types = get_post_types( [ 'public' => true ], 'names' );
        if ( ! in_array( $post_type, $public_types ) ) return;

        $current = isset( $_GET['seo_filter'] ) ? sanitize_text_field( $_GET['seo_filter'] ) : '';
        ?>
        <select name="seo_filter">
            <option value="">All SEO Scores</option>
            <option value="good" <?php selected($current, 'good'); ?>>Good</option>
            <option value="ok" <?php selected($current, 'ok'); ?>>OK</option>
            <option value="needs_improvement" <?php selected($current, 'needs_improvement'); ?>>Needs Improvement</option>
            <option value="not_analyzed" <?php selected($current, 'not_analyzed'); ?>>Not Analyzed</option>
        </select>
        <?php
    }

    public function filter_posts_by_seo_score( $query ) {
        global $pagenow;
        if ( ! is_admin() || $pagenow !== 'edit.php' || ! $query->is_main_query() ) {
            return;
        }

        if ( isset( $_GET['seo_filter'] ) && $_GET['seo_filter'] !== '' ) {
            $filter = sanitize_text_field( $_GET['seo_filter'] );

            // We need to count how many of the 3 meta keys exist and are not empty
            // This is complex in WP_Query, so we will do a custom DB query to get post IDs
            global $wpdb;
            
            $meta_data = $wpdb->get_results( "
                SELECT post_id, count(meta_key) as score
                FROM {$wpdb->postmeta} 
                WHERE meta_key IN ('_eseo_meta_title', '_eseo_meta_description', '_eseo_focus_keyword') 
                AND meta_value != ''
                GROUP BY post_id
            " );

            $good_ids = [];
            $ok_ids = [];
            $needs_improvement_ids = [];

            foreach ( $meta_data as $row ) {
                if ( $row->score == 3 ) $good_ids[] = $row->post_id;
                elseif ( $row->score == 2 ) $ok_ids[] = $row->post_id;
                else $needs_improvement_ids[] = $row->post_id;
            }

            $all_analyzed = array_merge( $good_ids, $ok_ids, $needs_improvement_ids );

            if ( $filter === 'good' ) {
                $query->set( 'post__in', empty($good_ids) ? [0] : $good_ids );
            } elseif ( $filter === 'ok' ) {
                $query->set( 'post__in', empty($ok_ids) ? [0] : $ok_ids );
            } elseif ( $filter === 'needs_improvement' ) {
                $query->set( 'post__in', empty($needs_improvement_ids) ? [0] : $needs_improvement_ids );
            } elseif ( $filter === 'not_analyzed' ) {
                $query->set( 'post__not_in', empty($all_analyzed) ? [0] : $all_analyzed );
            }
        }
    }
}
