<?php

namespace ESEO\Modules\SocialSEO;

class SocialSEO {

    public function init() {
        add_action( 'wp_head', [ $this, 'output_opengraph_tags' ], 5 );
    }

    public function output_opengraph_tags() {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_the_ID();
        
        $title = get_post_meta( $post_id, '_eseo_meta_title', true ) ?: get_the_title();
        $desc = get_post_meta( $post_id, '_eseo_meta_description', true ) ?: wp_trim_words( get_post_field( 'post_content', $post_id ), 20, '...' );
        $url = get_permalink();
        $site_name = get_bloginfo( 'name' );

        echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";

        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";

        if ( has_post_thumbnail( $post_id ) ) {
            $image_url = get_the_post_thumbnail_url( $post_id, 'full' );
            echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url( $image_url ) . '" />' . "\n";
        }
    }
}
