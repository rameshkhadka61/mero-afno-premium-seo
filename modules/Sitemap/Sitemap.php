<?php

namespace ESEO\Modules\Sitemap;

class Sitemap {

    public function init() {
        // Register rewrite rules for sitemap
        add_action( 'init', [ $this, 'register_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_action( 'template_redirect', [ $this, 'render_sitemap' ] );
        
        // Add sitemap link to virtual robots.txt
        add_action( 'do_robotstxt', [ $this, 'add_sitemap_to_robots' ] );

        // Clear cache when posts are updated
        add_action( 'save_post', [ $this, 'clear_sitemap_cache' ] );
    }

    public function clear_sitemap_cache() {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_eseo_sitemap_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_eseo_sitemap_%'" );
    }

    public function register_rewrite_rules() {
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?eseo_sitemap=index', 'top' );
        add_rewrite_rule( '^sitemap-([^/]+)\.xml$', 'index.php?eseo_sitemap=$matches[1]', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'eseo_sitemap';
        return $vars;
    }

    public function render_sitemap() {
        $sitemap_type = get_query_var( 'eseo_sitemap' );
        
        if ( ! $sitemap_type ) {
            return;
        }

        header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );
        
        $cache_key = 'eseo_sitemap_' . md5( $sitemap_type );
        $cached_xml = get_transient( $cache_key );

        if ( false !== $cached_xml ) {
            echo $cached_xml;
            exit;
        }

        ob_start();
        if ( 'index' === $sitemap_type ) {
            $this->render_sitemap_index();
        } else {
            $this->render_sitemap_content( $sitemap_type );
        }
        $xml_output = ob_get_clean();

        set_transient( $cache_key, $xml_output, 12 * HOUR_IN_SECONDS );
        echo $xml_output;
        
        exit;
    }

    private function render_sitemap_index() {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        $post_types = get_post_types( [ 'public' => true ], 'names' );
        $base_url = home_url( '/' );
        
        foreach ( $post_types as $post_type ) {
            echo "\t<sitemap>\n";
            echo "\t\t<loc>" . esc_url( $base_url . 'sitemap-' . $post_type . '.xml' ) . "</loc>\n";
            echo "\t\t<lastmod>" . date( 'c' ) . "</lastmod>\n";
            echo "\t</sitemap>\n";
        }
        
        echo '</sitemapindex>';
    }

    private function render_sitemap_content( $post_type ) {
        $post_types = get_post_types( [ 'public' => true ], 'names' );
        
        if ( ! in_array( $post_type, $post_types ) ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();
            return;
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1000,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
        
        $query = new \WP_Query( $args );
        
        foreach ( $query->posts as $post_id ) {
            $url = get_permalink( $post_id );
            $modified = get_post_modified_time( 'Y-m-d\TH:i:s+00:00', true, $post_id );
            
            echo "\t<url>\n";
            echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
            echo "\t\t<lastmod>" . $modified . "</lastmod>\n";
            echo "\t\t<changefreq>weekly</changefreq>\n";
            echo "\t\t<priority>0.8</priority>\n";
            echo "\t</url>\n";
        }
        
        echo '</urlset>';
    }

    public function add_sitemap_to_robots( $output ) {
        $sitemap_url = home_url( '/sitemap.xml' );
        return $output . "\nSitemap: " . esc_url( $sitemap_url ) . "\n";
    }
}
