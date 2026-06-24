<?php

namespace ESEO\Modules\Schema;

class Schema {

    public function init() {
        // Output schema in wp_head
        add_action( 'wp_head', [ $this, 'generate_schema' ], 99 );
    }

    public function generate_schema() {
        $settings = get_option( 'eseo_schema_settings', [] );
        $entity_type = isset( $settings['entity_type'] ) ? $settings['entity_type'] : 'organization';
        $entity_name = isset( $settings['entity_name'] ) ? $settings['entity_name'] : get_bloginfo('name');
        $logo_url = isset( $settings['logo_url'] ) ? $settings['logo_url'] : '';
        
        $site_url = trailingslashit( get_site_url() );
        
        // Base Graph Array
        $graph = [];

        // 1. Organization / Person Node
        $publisher_id = $site_url . '#' . $entity_type;
        $publisher_node = [
            '@type' => $entity_type === 'organization' ? 'Organization' : 'Person',
            '@id' => $publisher_id,
            'name' => $entity_name,
            'url' => $site_url,
        ];
        
        if ( $logo_url ) {
            $publisher_node['logo'] = [
                '@type' => 'ImageObject',
                '@id' => $site_url . '#logo',
                'inLanguage' => get_bloginfo( 'language' ),
                'url' => $logo_url,
                'caption' => $entity_name
            ];
            if ( $entity_type === 'organization' ) {
                $publisher_node['image'] = [ '@id' => $site_url . '#logo' ];
            }
        }
        $graph[] = $publisher_node;

        // 2. WebSite Node
        $website_id = $site_url . '#website';
        $graph[] = [
            '@type' => 'WebSite',
            '@id' => $website_id,
            'url' => $site_url,
            'name' => get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'publisher' => [ '@id' => $publisher_id ],
            'potentialAction' => [
                [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => $site_url . '?s={search_term_string}'
                    ],
                    'query-input' => 'required name=search_term_string'
                ]
            ],
            'inLanguage' => get_bloginfo( 'language' )
        ];

        // 3. WebPage Node
        $current_url = $this->get_current_url();
        $webpage_id = $current_url . '#webpage';
        
        $webpage_node = [
            '@type' => 'WebPage',
            '@id' => $webpage_id,
            'url' => $current_url,
            'name' => wp_get_document_title(),
            'isPartOf' => [ '@id' => $website_id ],
            'inLanguage' => get_bloginfo( 'language' )
        ];

        if ( is_singular() ) {
            global $post;
            $webpage_node['datePublished'] = get_the_date( 'c', $post );
            $webpage_node['dateModified'] = get_the_modified_date( 'c', $post );
            
            // Add breadcrumb reference (simplified)
            $webpage_node['breadcrumb'] = [ '@id' => $current_url . '#breadcrumb' ];
            
            // Generate Breadcrumb Node
            $graph[] = $this->get_breadcrumb_node( $current_url, $post );

            // 4. Article Node
            if ( $post->post_type === 'post' ) {
                $article_id = $current_url . '#article';
                $author_id = $site_url . '#/schema/person/' . $post->post_author;
                
                $graph[] = [
                    '@type' => 'Article',
                    '@id' => $article_id,
                    'isPartOf' => [ '@id' => $webpage_id ],
                    'author' => [
                        'name' => get_the_author_meta('display_name', $post->post_author),
                        '@id' => $author_id
                    ],
                    'headline' => get_the_title( $post ),
                    'datePublished' => get_the_date( 'c', $post ),
                    'dateModified' => get_the_modified_date( 'c', $post ),
                    'mainEntityOfPage' => [ '@id' => $webpage_id ],
                    'publisher' => [ '@id' => $publisher_id ],
                    'inLanguage' => get_bloginfo( 'language' )
                ];

                // 5. Author Node
                $graph[] = [
                    '@type' => 'Person',
                    '@id' => $author_id,
                    'name' => get_the_author_meta('display_name', $post->post_author),
                    'url' => get_author_posts_url( $post->post_author )
                ];
            }
        } elseif ( is_front_page() || is_home() ) {
            $webpage_node['@type'] = 'CollectionPage';
            $webpage_node['about'] = [ '@id' => $publisher_id ];
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $webpage_node['@type'] = 'CollectionPage';
        }

        $graph[] = $webpage_node;

        // Output Final JSON-LD
        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => $graph
        ];

        echo '<script type="application/ld+json" class="eseo-schema-graph">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>';
    }

    private function get_current_url() {
        global $wp;
        return home_url( add_query_arg( [], $wp->request ) );
    }

    private function get_breadcrumb_node( $current_url, $post ) {
        $site_url = trailingslashit( get_site_url() );
        $elements = [];
        
        // Home
        $elements[] = [
            '@type' => 'ListItem',
            'position' => 1,
            'item' => [
                '@type' => 'WebPage',
                '@id' => $site_url,
                'url' => $site_url,
                'name' => 'Home'
            ]
        ];

        // Current Post
        $elements[] = [
            '@type' => 'ListItem',
            'position' => 2,
            'item' => [
                '@type' => 'WebPage',
                '@id' => $current_url,
                'url' => $current_url,
                'name' => get_the_title( $post )
            ]
        ];

        return [
            '@type' => 'BreadcrumbList',
            '@id' => $current_url . '#breadcrumb',
            'itemListElement' => $elements
        ];
    }
}
