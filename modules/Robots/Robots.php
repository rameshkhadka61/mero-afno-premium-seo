<?php

namespace ESEO\Modules\Robots;

class Robots {

    public function init() {
        add_action( 'do_robotstxt', [ $this, 'custom_robots_txt' ], 10, 2 );
    }

    public function custom_robots_txt( $output, $public ) {
        // Here we could fetch custom rules from DB options
        $custom_rules = get_option( 'eseo_robots_txt', '' );
        
        if ( ! empty( $custom_rules ) ) {
            return $custom_rules; // User has complete override
        }

        return $output;
    }
}
