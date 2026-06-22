<?php

namespace ESEO\Modules\Performance;

class Performance {

    public function init() {
        // Optimize Core Web Vitals by deferring non-essential scripts
        add_filter( 'script_loader_tag', [ $this, 'defer_scripts' ], 10, 2 );
    }

    public function defer_scripts( $tag, $handle ) {
        // Simple example: defer scripts unless they are critical
        if ( is_admin() || strpos( $tag, 'jquery.min.js' ) !== false ) {
            return $tag;
        }
        
        // Add defer attribute
        if ( strpos( $tag, 'defer' ) === false ) {
            $tag = str_replace( ' src', ' defer="defer" src', $tag );
        }
        return $tag;
    }
}
