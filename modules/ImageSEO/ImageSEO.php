<?php

namespace ESEO\Modules\ImageSEO;

class ImageSEO {

    public function init() {
        // Run with high priority so it catches images before final output
        add_filter( 'the_content', [ $this, 'add_missing_image_attributes' ], 99 );
        add_filter( 'post_thumbnail_html', [ $this, 'add_missing_thumbnail_attributes' ], 99, 5 );
    }

    public function add_missing_image_attributes( $content ) {
        if ( ! is_singular() ) {
            return $content;
        }

        if ( empty( $content ) ) {
            return $content;
        }

        $post_id = get_the_ID();
        $fallback_text = $this->get_fallback_text( $post_id );

        // Match all img tags
        $content = preg_replace_callback( '/<img[^>]+>/i', function( $matches ) use ( $fallback_text ) {
            $img_tag = $matches[0];

            // Check for existing alt attribute
            if ( ! preg_match( '/alt=["\'](.*?)["\']/i', $img_tag, $alt_matches ) || empty( trim( $alt_matches[1] ) ) ) {
                if ( strpos( $img_tag, 'alt=' ) === false ) {
                    $img_tag = str_replace( '<img', '<img alt="' . esc_attr( $fallback_text ) . '"', $img_tag );
                } else {
                    $img_tag = preg_replace( '/alt=["\'][\s]*["\']/i', 'alt="' . esc_attr( $fallback_text ) . '"', $img_tag );
                }
            }

            // Check for existing title attribute
            if ( ! preg_match( '/title=["\'](.*?)["\']/i', $img_tag, $title_matches ) || empty( trim( $title_matches[1] ) ) ) {
                if ( strpos( $img_tag, 'title=' ) === false ) {
                    $img_tag = str_replace( '<img', '<img title="' . esc_attr( $fallback_text ) . '"', $img_tag );
                } else {
                    $img_tag = preg_replace( '/title=["\'][\s]*["\']/i', 'title="' . esc_attr( $fallback_text ) . '"', $img_tag );
                }
            }

            return $img_tag;
        }, $content );

        return $content;
    }

    public function add_missing_thumbnail_attributes( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
        if ( empty( $html ) ) {
            return $html;
        }

        $fallback_text = $this->get_fallback_text( $post_id );

        if ( ! preg_match( '/alt=["\'](.*?)["\']/i', $html, $alt_matches ) || empty( trim( $alt_matches[1] ) ) ) {
            if ( strpos( $html, 'alt=' ) === false ) {
                $html = str_replace( '<img', '<img alt="' . esc_attr( $fallback_text ) . '"', $html );
            } else {
                $html = preg_replace( '/alt=["\'][\s]*["\']/i', 'alt="' . esc_attr( $fallback_text ) . '"', $html );
            }
        }

        if ( ! preg_match( '/title=["\'](.*?)["\']/i', $html, $title_matches ) || empty( trim( $title_matches[1] ) ) ) {
            if ( strpos( $html, 'title=' ) === false ) {
                $html = str_replace( '<img', '<img title="' . esc_attr( $fallback_text ) . '"', $html );
            } else {
                $html = preg_replace( '/title=["\'][\s]*["\']/i', 'title="' . esc_attr( $fallback_text ) . '"', $html );
            }
        }

        return $html;
    }

    private function get_fallback_text( $post_id ) {
        $keyword = get_post_meta( $post_id, '_eseo_focus_keyword', true );
        if ( ! empty( $keyword ) ) {
            return $keyword;
        }
        
        $title = get_the_title( $post_id );
        return $title;
    }
}
