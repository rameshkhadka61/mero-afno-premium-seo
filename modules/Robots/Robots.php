<?php

namespace ESEO\Modules\Robots;

class Robots {

    public function init() {
        add_action( 'init', [ $this, 'register_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_action( 'template_redirect', [ $this, 'render_robots_txt' ] );
        
        // Keep the filter for default WP output just in case
        add_filter( 'robots_txt', [ $this, 'custom_robots_txt' ], 10, 2 );
    }

    public function register_rewrite_rules() {
        add_rewrite_rule( '^robots\.txt$', 'index.php?eseo_robots=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'eseo_robots';
        return $vars;
    }

    public function render_robots_txt() {
        if ( get_query_var( 'eseo_robots' ) ) {
            header( 'Content-Type: text/plain; charset=utf-8' );
            
            $output = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
            
            $custom_rules = get_option( 'eseo_robots_txt', '' );
            if ( ! empty( $custom_rules ) ) {
                $output = $custom_rules . "\n";
            }
            
            // Add sitemap
            $sitemap_url = home_url( '/sitemap.xml' );
            $output .= "\nSitemap: " . esc_url( $sitemap_url ) . "\n";
            
            // Allow other plugins to hook in if they use the standard WP hook
            do_action( 'do_robotstxt' );
            
            echo apply_filters( 'eseo_robots_txt_output', $output );
            exit;
        }
    }

    public function custom_robots_txt( $output, $public ) {
        $custom_rules = get_option( 'eseo_robots_txt', '' );
        
        if ( ! empty( $custom_rules ) ) {
            return $custom_rules;
        }

        return $output;
    }
}
