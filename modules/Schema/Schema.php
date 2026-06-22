<?php

namespace ESEO\Modules\Schema;

class Schema {

    public function init() {
        add_action( 'wp_head', [ $this, 'output_schema' ], 100 );
    }

    public function output_schema() {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_the_ID();
        $post = get_post( $post_id );
        $author_id = $post->post_author;

        // Basic Article Schema
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Article',
            'headline' => get_post_meta( $post_id, '_eseo_meta_title', true ) ?: $post->post_title,
            'datePublished' => get_the_date( 'c', $post_id ),
            'dateModified'  => get_the_modified_date( 'c', $post_id ),
            'author' => [
                '@type' => 'Person',
                'name'  => get_the_author_meta( 'display_name', $author_id )
            ]
        ];

        // Add image if available
        if ( has_post_thumbnail( $post_id ) ) {
            $thumbnail_id = get_post_thumbnail_id( $post_id );
            $thumbnail_url = wp_get_attachment_url( $thumbnail_id );
            $schema['image'] = [
                $thumbnail_url
            ];
        }

        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}
