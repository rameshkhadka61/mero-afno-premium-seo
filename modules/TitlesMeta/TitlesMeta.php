<?php

namespace ESEO\Modules\TitlesMeta;

class TitlesMeta {

    public function init() {
        error_log("ESEO DEBUG: TitlesMeta::init() called");
        
        // Register post meta on init
        add_action( 'init', [ $this, 'register_meta' ] );

        // Admin hooks for meta boxes and scripts
        add_action( 'add_meta_boxes', [ $this, 'add_seo_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_seo_meta_box_data' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // Frontend hooks
        add_filter( 'document_title_parts', [ $this, 'filter_title_parts' ], 10, 1 );
        add_action( 'wp_head', [ $this, 'output_meta_tags' ], 1 );
    }

    public function register_meta() {
        $meta_args = [
            'type'         => 'string',
            'description'  => 'SEO Meta Title',
            'single'       => true,
            'show_in_rest' => true,
        ];
        register_meta( 'post', '_eseo_meta_title', $meta_args );
        register_meta( 'post', '_eseo_meta_description', $meta_args );
        register_meta( 'post', '_eseo_focus_keyword', $meta_args );
        register_meta( 'post', '_eseo_canonical_url', $meta_args );
        register_meta( 'post', '_eseo_meta_robots_index', $meta_args );
        register_meta( 'post', '_eseo_meta_robots_follow', $meta_args );
    }

    public function add_seo_meta_box() {
        $screens = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $screens as $screen ) {
            add_meta_box(
                'eseo_meta_box',
                'Mero Afno Premium SEO',
                [ $this, 'render_seo_meta_box' ],
                $screen,
                'normal',
                'high'
            );
        }
    }

    public function render_seo_meta_box( $post ) {
        wp_nonce_field( 'eseo_save_meta_box_data', 'eseo_meta_box_nonce' );

        $title = get_post_meta( $post->ID, '_eseo_meta_title', true );
        $desc = get_post_meta( $post->ID, '_eseo_meta_description', true );
        $keyword = get_post_meta( $post->ID, '_eseo_focus_keyword', true );
        $canonical = get_post_meta( $post->ID, '_eseo_canonical_url', true );
        $robots_index = get_post_meta( $post->ID, '_eseo_meta_robots_index', true );
        $robots_follow = get_post_meta( $post->ID, '_eseo_meta_robots_follow', true );

        ?>
        <div class="eseo-meta-box-container" style="display:flex; flex-direction:column; gap:15px;">
            <div class="eseo-field">
                <label for="eseo_focus_keyword"><strong>Focus Keyword</strong></label>
                <button type="button" class="button button-secondary eseo-ai-btn" data-type="keyword" style="margin-left: 10px;">✨ Suggest Keyword</button>
                <br>
                <input type="text" id="eseo_focus_keyword" name="eseo_focus_keyword" value="<?php echo esc_attr( $keyword ); ?>" style="width:100%; margin-top: 5px;" />
            </div>
            <div class="eseo-field">
                <label for="eseo_meta_title"><strong>SEO Title</strong></label>
                <button type="button" class="button button-secondary eseo-ai-btn" data-type="title" style="margin-left: 10px;">✨ Generate with AI</button>
                <br>
                <input type="text" id="eseo_meta_title" name="eseo_meta_title" value="<?php echo esc_attr( $title ); ?>" style="width:100%; margin-top: 5px;" placeholder="%title% - %sitename%" />
                <p class="description">Variables available: %title%, %sitename%, %date%, %currentyear%</p>
            </div>
            <div class="eseo-field">
                <label for="eseo_meta_description"><strong>Meta Description</strong></label>
                <button type="button" class="button button-secondary eseo-ai-btn" data-type="description" style="margin-left: 10px;">✨ Generate with AI</button>
                <br>
                <textarea id="eseo_meta_description" name="eseo_meta_description" rows="3" style="width:100%; margin-top: 5px;"><?php echo esc_textarea( $desc ); ?></textarea>
            </div>
            
            <div class="eseo-field">
                <label><strong>Real-Time SEO Analysis</strong></label>
                <ul id="eseo-analysis-results" style="background:#f8f9fa; padding:15px; border-radius:4px; border:1px solid #ccd0d4; margin-top:5px;">
                    <li>⏳ Analyzing...</li>
                </ul>
            </div>

            <!-- SERP Preview Block -->
            <div class="eseo-field">
                <label><strong>Google Search Preview</strong></label>
                <div class="eseo-serp-toggle-bar">
                    <button type="button" class="eseo-serp-toggle-btn active" id="eseo-serp-desktop-btn">🖥️ Desktop</button>
                    <button type="button" class="eseo-serp-toggle-btn" id="eseo-serp-mobile-btn">📱 Mobile</button>
                </div>
                <div class="eseo-serp-preview" id="eseo-serp-preview-box">
                    <div class="eseo-serp-url" id="eseo-serp-url-preview"><?php echo esc_url( get_site_url() ); ?>/your-post-url/</div>
                    <div class="eseo-serp-title" id="eseo-serp-title-preview">Your Post Title Here - <?php echo esc_html( get_bloginfo('name') ); ?></div>
                    <div class="eseo-serp-desc" id="eseo-serp-desc-preview">Please provide a meta description. If you don't, Google will try to find a relevant part of your post to show in the search results.</div>
                </div>
            </div>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ccd0d4;">
            
            <div class="eseo-field">
                <h3 style="margin-top: 0;">Advanced SEO</h3>
                <label for="eseo_canonical_url"><strong>Canonical URL</strong></label>
                <br>
                <input type="url" id="eseo_canonical_url" name="eseo_canonical_url" value="<?php echo esc_attr( $canonical ); ?>" style="width:100%; margin-top: 5px;" placeholder="Leave empty to use default permalink" />
            </div>

            <div class="eseo-field" style="display:flex; gap:20px; margin-top: 10px;">
                <div style="flex:1;">
                    <label for="eseo_meta_robots_index"><strong>Meta Robots Index</strong></label>
                    <select id="eseo_meta_robots_index" name="eseo_meta_robots_index" style="width:100%; margin-top: 5px;">
                        <option value="default" <?php selected($robots_index, 'default'); ?>>Default (Index)</option>
                        <option value="noindex" <?php selected($robots_index, 'noindex'); ?>>NoIndex</option>
                        <option value="index" <?php selected($robots_index, 'index'); ?>>Index</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label for="eseo_meta_robots_follow"><strong>Meta Robots Follow</strong></label>
                    <select id="eseo_meta_robots_follow" name="eseo_meta_robots_follow" style="width:100%; margin-top: 5px;">
                        <option value="default" <?php selected($robots_follow, 'default'); ?>>Default (Follow)</option>
                        <option value="nofollow" <?php selected($robots_follow, 'nofollow'); ?>>NoFollow</option>
                        <option value="follow" <?php selected($robots_follow, 'follow'); ?>>Follow</option>
                    </select>
                </div>
            </div>

        </div>
        <?php
    }

    public function save_seo_meta_box_data( $post_id ) {
        if ( ! isset( $_POST['eseo_meta_box_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['eseo_meta_box_nonce'], 'eseo_save_meta_box_data' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['eseo_meta_title'] ) ) {
            update_post_meta( $post_id, '_eseo_meta_title', sanitize_text_field( $_POST['eseo_meta_title'] ) );
        }
        if ( isset( $_POST['eseo_meta_description'] ) ) {
            update_post_meta( $post_id, '_eseo_meta_description', sanitize_textarea_field( $_POST['eseo_meta_description'] ) );
        }
        if ( isset( $_POST['eseo_focus_keyword'] ) ) {
            update_post_meta( $post_id, '_eseo_focus_keyword', sanitize_text_field( $_POST['eseo_focus_keyword'] ) );
        }
        if ( isset( $_POST['eseo_canonical_url'] ) ) {
            update_post_meta( $post_id, '_eseo_canonical_url', sanitize_url( $_POST['eseo_canonical_url'] ) );
        }
        if ( isset( $_POST['eseo_meta_robots_index'] ) ) {
            update_post_meta( $post_id, '_eseo_meta_robots_index', sanitize_text_field( $_POST['eseo_meta_robots_index'] ) );
        }
        if ( isset( $_POST['eseo_meta_robots_follow'] ) ) {
            update_post_meta( $post_id, '_eseo_meta_robots_follow', sanitize_text_field( $_POST['eseo_meta_robots_follow'] ) );
        }
    }

    public function enqueue_admin_scripts( $hook ) {
        global $post;

        if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
            $public_post_types = get_post_types( [ 'public' => true ], 'names' );
            if ( isset( $post->post_type ) && in_array( $post->post_type, $public_post_types ) ) {
                
                // Enqueue Premium CSS
                wp_enqueue_style( 'eseo-admin-css', plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/admin.css', [], '1.0.0' );

                wp_enqueue_script( 'eseo-admin-js', plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/admin.js', [ 'jquery' ], filemtime( plugin_dir_path( dirname( __DIR__ ) ) . 'assets/js/admin.js' ), true );
                
                // Pass variables to JS
                wp_localize_script( 'eseo-admin-js', 'eseo_vars', [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'ai_nonce' => wp_create_nonce( 'eseo_ai_nonce' ),
                    'site_url' => esc_url( get_site_url() ),
                ]);
            }
        }
    }

    public function filter_title_parts( $title_parts ) {
        if ( is_singular() ) {
            $post_id = get_the_ID();
            $custom_title = get_post_meta( $post_id, '_eseo_meta_title', true );
            
            if ( ! empty( $custom_title ) ) {
                $parsed_title = $this->parse_variables( $custom_title, $post_id );
                // Replace the entire title with our custom parsed title
                $title_parts['title'] = $parsed_title;
                // Unset other parts so WordPress doesn't append sitename again if we already did
                unset( $title_parts['site'] );
                unset( $title_parts['tagline'] );
            }
        }
        return $title_parts;
    }

    public function output_meta_tags() {
        if ( is_singular() ) {
            $post_id = get_the_ID();
            $custom_desc = get_post_meta( $post_id, '_eseo_meta_description', true );
            
            if ( ! empty( $custom_desc ) ) {
                $parsed_desc = $this->parse_variables( $custom_desc, $post_id );
                echo '<meta name="description" content="' . esc_attr( $parsed_desc ) . '" />' . "\n";
            }

            // Output Canonical URL
            $canonical = get_post_meta( $post_id, '_eseo_canonical_url', true );
            if ( ! empty( $canonical ) ) {
                // Remove default WP canonical
                remove_action( 'wp_head', 'rel_canonical' );
                echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
            }

            // Output Meta Robots
            $robots_index = get_post_meta( $post_id, '_eseo_meta_robots_index', true );
            $robots_follow = get_post_meta( $post_id, '_eseo_meta_robots_follow', true );
            
            $robots = [];
            if ( ! empty( $robots_index ) && $robots_index !== 'default' ) {
                $robots[] = $robots_index;
            }
            if ( ! empty( $robots_follow ) && $robots_follow !== 'default' ) {
                $robots[] = $robots_follow;
            }

            if ( ! empty( $robots ) ) {
                // If WordPress already output noindex (e.g. from global settings), be careful.
                // But we are explicitly overriding it here.
                echo '<meta name="robots" content="' . esc_attr( implode( ', ', $robots ) ) . '" />' . "\n";
            }
        }
    }

    private function parse_variables( $string, $post_id ) {
        $post = get_post( $post_id );
        
        $replacements = [
            '%title%'       => $post->post_title,
            '%sitename%'    => get_bloginfo( 'name' ),
            '%date%'        => get_the_date( '', $post_id ),
            '%currentyear%' => date( 'Y' ),
        ];

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $string );
    }
}
