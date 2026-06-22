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

        ?>
        <div class="eseo-meta-box-container" style="display:flex; flex-direction:column; gap:15px;">
            <div class="eseo-field">
                <label for="eseo_focus_keyword"><strong>Focus Keyword</strong></label><br>
                <input type="text" id="eseo_focus_keyword" name="eseo_focus_keyword" value="<?php echo esc_attr( $keyword ); ?>" style="width:100%;" />
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
            
            <div id="eseo-realtime-analyzer" style="border: 1px solid #ccd0d4; padding: 15px; background: #fff;">
                <h4>Real-Time SEO Analysis</h4>
                <ul id="eseo-analysis-results" style="list-style-type: disc; margin-left: 20px;">
                    <li>Type to start analyzing...</li>
                </ul>
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
                    'ai_nonce' => wp_create_nonce( 'eseo_ai_nonce' )
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
