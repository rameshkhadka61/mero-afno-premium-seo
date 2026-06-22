<?php

namespace ESEO\Modules\Breadcrumbs;

class Breadcrumbs {

    public function init() {
        add_shortcode( 'eseo_breadcrumbs', [ $this, 'render_shortcode' ] );
    }

    public function render_shortcode( $atts ) {
        if ( is_front_page() || is_home() ) {
            return '';
        }

        $html = '<nav class="eseo-breadcrumbs" aria-label="Breadcrumb"><ol style="list-style:none; padding:0; display:flex; gap:10px;">';
        $html .= '<li><a href="' . home_url('/') . '">Home</a></li>';

        if ( is_single() || is_page() ) {
            $post = get_post();
            
            if ( $post->post_parent ) {
                $parent_id  = $post->post_parent;
                $breadcrumbs = [];
                while ( $parent_id ) {
                    $page = get_post( $parent_id );
                    $breadcrumbs[] = '<li><a href="' . get_permalink( $page->ID ) . '">' . get_the_title( $page->ID ) . '</a></li>';
                    $parent_id  = $page->post_parent;
                }
                $breadcrumbs = array_reverse( $breadcrumbs );
                foreach ( $breadcrumbs as $crumb ) {
                    $html .= '<li>&raquo;</li>' . $crumb;
                }
            }

            if ( is_single() ) {
                $categories = get_the_category( $post->ID );
                if ( $categories ) {
                    $html .= '<li>&raquo;</li><li><a href="' . get_category_link( $categories[0]->term_id ) . '">' . $categories[0]->name . '</a></li>';
                }
            }

            $html .= '<li>&raquo;</li><li><span aria-current="page">' . get_the_title( $post->ID ) . '</span></li>';
        }

        $html .= '</ol></nav>';
        return $html;
    }
}
