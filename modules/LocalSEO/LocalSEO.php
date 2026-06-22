<?php

namespace ESEO\Modules\LocalSEO;

class LocalSEO {

    public function init() {
        add_action( 'wp_head', [ $this, 'output_local_schema' ], 101 );
    }

    public function output_local_schema() {
        if ( ! is_front_page() ) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'LocalBusiness',
            'name'     => get_bloginfo( 'name' ),
            'url'      => home_url(),
            'description' => get_bloginfo( 'description' )
        ];

        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}
