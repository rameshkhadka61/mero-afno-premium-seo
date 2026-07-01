<?php

namespace ESEO\Modules\TitlesMeta;

class TitlesMeta {

    public function init() {
        error_log("ESEO DEBUG: TitlesMeta::init() called");
        
        // Register post meta on init
        add_action( 'init', [ $this, 'register_meta' ] );

        // Admin hooks for meta boxes and scripts
        add_action( 'admin_init', [ $this, 'register_column_hooks' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_seo_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_seo_meta_box_data' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // Frontend hooks
        add_filter( 'document_title_parts', [ $this, 'filter_title_parts' ], 10, 1 );
        
        // Conflict resolution: Output buffering to remove duplicate tags
        add_action( 'wp_head', [ $this, 'start_head_buffer' ], 0 );
        add_action( 'wp_head', [ $this, 'end_head_buffer' ], 9999 );

        // Output our tags after the buffer is flushed so they aren't stripped
        add_action( 'wp_head', [ $this, 'output_meta_tags' ], 10000 );
    }

    public function start_head_buffer() {
        ob_start();
    }

    public function end_head_buffer() {
        $head = ob_get_clean();
        
        // Strip out existing meta tags from theme/other plugins to avoid duplicates
        $head = preg_replace('/<meta name=["\']description["\'][^>]*>/i', '', $head);
        $head = preg_replace('/<meta name=["\']robots["\'][^>]*>/i', '', $head);
        $head = preg_replace('/<link rel=["\']canonical["\'][^>]*>/i', '', $head);
        $head = preg_replace('/<meta property=["\']og:[^>]*>/i', '', $head);
        $head = preg_replace('/<meta name=["\']twitter:[^>]*>/i', '', $head);
        
        echo $head;
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
        register_meta( 'post', '_eseo_social_title', $meta_args );
        register_meta( 'post', '_eseo_social_description', $meta_args );
        register_meta( 'post', '_eseo_social_image', $meta_args );
        register_meta( 'post', '_eseo_faq_schema', $meta_args );
    }

    public function register_column_hooks() {
        $public_post_types = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $public_post_types as $post_type ) {
            add_filter( "manage_{$post_type}_posts_columns", [ $this, 'add_seo_columns' ] );
            add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'render_seo_columns' ], 10, 2 );
        }
    }

    public function add_seo_columns( $columns ) {
        $columns['eseo_score'] = 'SEO Score';
        $columns['eseo_keyword'] = 'Focus Keyword';
        return $columns;
    }

    public function render_seo_columns( $column_name, $post_id ) {
        if ( $column_name === 'eseo_keyword' ) {
            $keyword = get_post_meta( $post_id, '_eseo_focus_keyword', true );
            echo ! empty( $keyword ) ? esc_html( $keyword ) : '<span style="color:#a7aaad;">&mdash;</span>';
        }

        if ( $column_name === 'eseo_score' ) {
            $title = get_post_meta( $post_id, '_eseo_meta_title', true );
            $desc = get_post_meta( $post_id, '_eseo_meta_description', true );
            $keyword = get_post_meta( $post_id, '_eseo_focus_keyword', true );

            $score = 0;
            if ( ! empty( $title ) ) $score++;
            if ( ! empty( $desc ) ) $score++;
            if ( ! empty( $keyword ) ) $score++;

            $status_class = 'none';
            $status_text = 'Not Analyzed';

            if ( $score === 3 ) {
                $status_class = 'good';
                $status_text = 'Good';
            } elseif ( $score === 2 ) {
                $status_class = 'ok';
                $status_text = 'OK';
            } elseif ( $score === 1 ) {
                $status_class = 'bad';
                $status_text = 'Needs Improvement';
            }

            echo '<div class="eseo-score-indicator eseo-score-' . esc_attr( $status_class ) . '" title="' . esc_attr( $status_text ) . '"></div>';
        }
    }

    public function add_seo_meta_box() {
        $screens = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $screens as $screen ) {
            add_meta_box(
                'eseo_meta_box',
                'Mero SEO',
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
        $social_title = get_post_meta( $post->ID, '_eseo_social_title', true );
        $social_desc = get_post_meta( $post->ID, '_eseo_social_description', true );
        $social_img = get_post_meta( $post->ID, '_eseo_social_image', true );

        $faq_schema = get_post_meta( $post->ID, '_eseo_faq_schema', true );

        ?>
        <div class="eseo-tabs-nav" style="display:flex; border-bottom:1px solid #ccd0d4; margin-bottom:15px; gap:5px;">
            <button type="button" class="eseo-tab-btn active" data-tab="general" style="padding:10px 15px; border:1px solid #ccd0d4; border-bottom:none; background:#fff; font-weight:600; cursor:pointer;">🔍 General & SERP</button>
            <button type="button" class="eseo-tab-btn" data-tab="social" style="padding:10px 15px; border:1px solid transparent; background:#f0f0f1; cursor:pointer;">🌐 Social Previews</button>
            <button type="button" class="eseo-tab-btn" data-tab="faq" style="padding:10px 15px; border:1px solid transparent; background:#f0f0f1; cursor:pointer;">⚡ FAQ Builder</button>
            <button type="button" class="eseo-tab-btn" data-tab="links" style="padding:10px 15px; border:1px solid transparent; background:#f0f0f1; cursor:pointer;">🔗 Internal Links</button>
        </div>

        <div class="eseo-meta-box-container">
            <!-- TAB 1: GENERAL & SERP -->
            <div class="eseo-tab-content eseo-tab-general" style="display:flex; flex-direction:column; gap:15px;">
                <div class="eseo-field">
                    <label for="eseo_focus_keyword"><strong>Focus Keyword</strong></label>
                    <button type="button" class="button button-secondary eseo-ai-btn" data-type="keyword" style="margin-left: 10px;">✨ Suggest Keyword</button>
                    <button type="button" class="button button-secondary eseo-lsi-btn" style="margin-left: 5px;">🤖 Suggest LSI Keywords</button>
                    <br>
                    <input type="text" id="eseo_focus_keyword" name="eseo_focus_keyword" value="<?php echo esc_attr( $keyword ); ?>" style="width:100%; margin-top: 5px;" />
                    <div id="eseo-lsi-results" style="margin-top:8px; display:none;"></div>
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
                        <div class="eseo-serp-thumb" id="eseo-serp-thumb-preview" style="display:none;"></div>
                        <div class="eseo-serp-content-wrapper">
                            <div class="eseo-serp-url" id="eseo-serp-url-preview"><?php echo esc_url( get_site_url() ); ?>/your-post-url/</div>
                            <div class="eseo-serp-title" id="eseo-serp-title-preview">Your Post Title Here - <?php echo esc_html( get_bloginfo('name') ); ?></div>
                            <div class="eseo-serp-desc" id="eseo-serp-desc-preview">Please provide a meta description. If you don't, Google will try to find a relevant part of your post to show in the search results.</div>
                        </div>
                    </div>
                </div>

                <hr style="margin: 15px 0; border: 0; border-top: 1px solid #ccd0d4;">
                
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

            <!-- TAB 2: SOCIAL PREVIEWS -->
            <div class="eseo-tab-content eseo-tab-social" style="display:none; flex-direction:column; gap:15px;">
                <div class="eseo-field">
                    <label for="eseo_social_title"><strong>Social Share Title</strong></label>
                    <input type="text" id="eseo_social_title" name="eseo_social_title" value="<?php echo esc_attr( $social_title ); ?>" style="width:100%; margin-top: 5px;" placeholder="Leave blank to use SEO Title" />
                </div>

                <div class="eseo-field">
                    <label for="eseo_social_description"><strong>Social Share Description</strong></label>
                    <textarea id="eseo_social_description" name="eseo_social_description" rows="2" style="width:100%; margin-top: 5px;" placeholder="Leave blank to use Meta Description"><?php echo esc_textarea( $social_desc ); ?></textarea>
                </div>

                <div class="eseo-field">
                    <label for="eseo_social_image"><strong>Custom Social Image URL</strong></label>
                    <div style="display:flex; gap:10px; margin-top:5px;">
                        <input type="url" id="eseo_social_image" name="eseo_social_image" value="<?php echo esc_attr( $social_img ); ?>" style="flex:1;" placeholder="https://... (1200x630 recommended for rich WhatsApp/Facebook cards)" />
                        <button type="button" class="button button-secondary eseo-upload-social-img">📁 Upload / Choose</button>
                    </div>
                </div>

                <hr style="margin: 15px 0; border: 0; border-top: 1px solid #ccd0d4;">

                <div class="eseo-field">
                    <label><strong>Live Facebook / WhatsApp Share Card Preview</strong></label>
                    <div class="eseo-fb-preview" style="border:1px solid #dadde1; border-radius:8px; overflow:hidden; max-width:500px; background:#f0f2f5; margin-top:8px;">
                        <div class="eseo-fb-img" id="eseo-fb-img-preview" style="height:260px; background-color:#e4e6eb; background-size:contain; background-position:center center; background-repeat:no-repeat; display:flex; align-items:center; justify-content:center; color:#8d949e; font-weight:bold;">No Image Selected</div>
                        <div style="padding:12px; background:#fff; border-top:1px solid #dadde1;">
                            <div style="font-size:12px; text-transform:uppercase; color:#606770;" id="eseo-fb-domain-preview"><?php echo esc_html( parse_url( get_site_url(), PHP_URL_HOST ) ); ?></div>
                            <div style="font-size:16px; font-weight:bold; color:#1d2129; margin:4px 0;" id="eseo-fb-title-preview">Your Post Title</div>
                            <div style="font-size:14px; color:#606770; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;" id="eseo-fb-desc-preview">Your post description preview will appear here.</div>
                        </div>
                    </div>
                </div>

                <div class="eseo-field" style="margin-top:10px;">
                    <label><strong>Live X (Twitter) Card Preview</strong></label>
                    <div class="eseo-tw-preview" style="border:1px solid #cfd9de; border-radius:16px; overflow:hidden; max-width:500px; background:#fff; margin-top:8px;">
                        <div class="eseo-tw-img" id="eseo-tw-img-preview" style="height:260px; background-color:#f7f9f9; background-size:contain; background-position:center center; background-repeat:no-repeat; display:flex; align-items:center; justify-content:center; color:#536471; font-weight:bold;">No Image Selected</div>
                        <div style="padding:12px;">
                            <div style="font-size:15px; font-weight:bold; color:#0f1419; margin-bottom:2px;" id="eseo-tw-title-preview">Your Post Title</div>
                            <div style="font-size:14px; color:#536471;" id="eseo-tw-desc-preview">Your post description preview will appear here.</div>
                            <div style="font-size:14px; color:#536471; margin-top:4px;" id="eseo-tw-domain-preview">🔗 <?php echo esc_html( parse_url( get_site_url(), PHP_URL_HOST ) ); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: FAQ BUILDER -->
            <div class="eseo-tab-content eseo-tab-faq" style="display:none; flex-direction:column; gap:15px;">
                <div>
                    <p class="description">Add FAQ Question and Answer pairs below. Mero SEO will automatically inject verified Google <code>FAQPage</code> rich snippet schema into your post header.</p>
                    <input type="hidden" id="eseo_faq_schema" name="eseo_faq_schema" value="<?php echo esc_attr( $faq_schema ); ?>" />
                    <div id="eseo-faq-items-list" style="display:flex; flex-direction:column; gap:10px; margin-top:10px;"></div>
                    <button type="button" class="button button-primary" id="eseo-add-faq-item-btn" style="margin-top:15px;">+ Add FAQ Question</button>
                </div>
            </div>

            <!-- TAB 4: INTERNAL LINKS -->
            <div class="eseo-tab-content eseo-tab-links" style="display:none; flex-direction:column; gap:15px;">
                <div>
                    <p class="description">Find relevant internal linking opportunities on your site to boost SEO authority and avoid orphan content.</p>
                    <button type="button" class="button button-primary" id="eseo-find-links-btn">🔍 Scan For Internal Link Opportunities</button>
                    <div id="eseo-links-results" style="margin-top:15px; display:flex; flex-direction:column; gap:10px;"></div>
                </div>
            </div>

        </div>
        <script>
        jQuery(document).ready(function($){
            // Tabs switching
            $('.eseo-tab-btn').on('click', function(e){
                e.preventDefault();
                $('.eseo-tab-btn').removeClass('active').css({background:'#f0f0f1', borderColor:'transparent'});
                $(this).addClass('active').css({background:'#fff', borderColor:'#ccd0d4', borderBottomColor:'transparent'});
                var tab = $(this).data('tab');
                $('.eseo-tab-content').hide();
                $('.eseo-tab-' + tab).css('display', 'flex');
            });

            // Media uploader
            $('.eseo-upload-social-img').on('click', function(e){
                e.preventDefault();
                var frame = wp.media({ title: 'Select Social Share Image', multiple: false, library: { type: 'image' } });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#eseo_social_image').val(attachment.url).trigger('input');
                });
                frame.open();
            });

            // FAQ Builder JS
            var faqData = [];
            try {
                var val = $('#eseo_faq_schema').val();
                if(val) faqData = JSON.parse(val);
            } catch(err){}

            function renderFaqs() {
                var $list = $('#eseo-faq-items-list');
                $list.empty();
                if(!faqData.length) {
                    $list.html('<p style="color:#666; font-style:italic;">No FAQs added yet.</p>');
                    return;
                }
                faqData.forEach(function(item, idx){
                    var html = '<div class="eseo-faq-item" style="border:1px solid #ccd0d4; padding:12px; border-radius:6px; background:#f8f9fa; position:relative;">' +
                        '<label><strong>Question ' + (idx+1) + '</strong></label><button type="button" class="button button-link-delete eseo-remove-faq" data-idx="'+idx+'" style="position:absolute; right:10px; top:10px; color:#b32d2e;">Remove</button>' +
                        '<input type="text" class="eseo-faq-q" data-idx="'+idx+'" value="'+ (item.q||'').replace(/"/g, '&quot;') +'" style="width:100%; margin:5px 0 10px;" placeholder="e.g. How do I listen live?" />' +
                        '<label><strong>Answer</strong></label>' +
                        '<textarea class="eseo-faq-a" data-idx="'+idx+'" rows="2" style="width:100%; margin-top:5px;" placeholder="e.g. Click the play button at the top of our website.">'+ (item.a||'') +'</textarea>' +
                    '</div>';
                    $list.append(html);
                });
            }

            $('#eseo-add-faq-item-btn').on('click', function(){
                faqData.push({q:'', a:''});
                $('#eseo_faq_schema').val(JSON.stringify(faqData));
                renderFaqs();
            });

            $(document).on('input', '.eseo-faq-q, .eseo-faq-a', function(){
                var idx = $(this).data('idx');
                if($(this).hasClass('eseo-faq-q')) faqData[idx].q = $(this).val();
                if($(this).hasClass('eseo-faq-a')) faqData[idx].a = $(this).val();
                $('#eseo_faq_schema').val(JSON.stringify(faqData));
            });

            $(document).on('click', '.eseo-remove-faq', function(){
                var idx = $(this).data('idx');
                faqData.splice(idx, 1);
                $('#eseo_faq_schema').val(JSON.stringify(faqData));
                renderFaqs();
            });

            renderFaqs();
        });
        </script>
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
        if ( isset( $_POST['eseo_social_title'] ) ) {
            update_post_meta( $post_id, '_eseo_social_title', sanitize_text_field( $_POST['eseo_social_title'] ) );
        }
        if ( isset( $_POST['eseo_social_description'] ) ) {
            update_post_meta( $post_id, '_eseo_social_description', sanitize_textarea_field( $_POST['eseo_social_description'] ) );
        }
        if ( isset( $_POST['eseo_social_image'] ) ) {
            update_post_meta( $post_id, '_eseo_social_image', sanitize_url( $_POST['eseo_social_image'] ) );
        }
        if ( isset( $_POST['eseo_faq_schema'] ) ) {
            update_post_meta( $post_id, '_eseo_faq_schema', wp_unslash( $_POST['eseo_faq_schema'] ) );
        }
    }

    public function enqueue_admin_scripts( $hook ) {
        global $post;

        if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
            $public_post_types = get_post_types( [ 'public' => true ], 'names' );
            if ( isset( $post->post_type ) && in_array( $post->post_type, $public_post_types ) ) {
                wp_enqueue_media();
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
        } elseif ( $hook == 'edit.php' ) {
            wp_enqueue_style( 'eseo-admin-css', plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/admin.css', [], '1.0.0' );
        }
    }

    public function filter_title_parts( $title_parts ) {
        $settings = get_option( 'eseo_titles_meta_settings', [] );

        if ( is_front_page() || is_home() ) {
            $homepage_title = isset($settings['homepage_title']) ? $settings['homepage_title'] : '';
            if ( ! empty( $homepage_title ) ) {
                $title_parts['title'] = $this->parse_variables( $homepage_title, 0, 'homepage' );
                unset( $title_parts['site'], $title_parts['tagline'] );
            }
        } elseif ( is_singular() ) {
            $post_id = get_the_ID();
            $post_type = get_post_type( $post_id );
            $custom_title = get_post_meta( $post_id, '_eseo_meta_title', true );
            
            if ( empty( $custom_title ) ) {
                $prefix = 'pt_' . $post_type;
                $custom_title = isset($settings[$prefix.'_title']) ? $settings[$prefix.'_title'] : '%title% - %sitename%';
            }

            if ( ! empty( $custom_title ) ) {
                $title_parts['title'] = $this->parse_variables( $custom_title, $post_id, 'post' );
                unset( $title_parts['site'], $title_parts['tagline'] );
            }
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term ) {
                $prefix = 'tax_' . $term->taxonomy;
                $custom_title = isset($settings[$prefix.'_title']) ? $settings[$prefix.'_title'] : '%title% Archives - %sitename%';
                if ( ! empty( $custom_title ) ) {
                    $title_parts['title'] = $this->parse_variables( $custom_title, $term->term_id, 'term' );
                    unset( $title_parts['site'], $title_parts['tagline'] );
                }
            }
        } elseif ( is_author() ) {
            $author = get_queried_object();
            if ( $author ) {
                $prefix = 'arch_author';
                $custom_title = isset($settings[$prefix.'_title']) ? $settings[$prefix.'_title'] : '%title%, Author at %sitename%';
                if ( ! empty( $custom_title ) ) {
                    $title_parts['title'] = $this->parse_variables( $custom_title, $author->ID, 'author' );
                    unset( $title_parts['site'], $title_parts['tagline'] );
                }
            }
        }

        return $title_parts;
    }

    public function output_meta_tags() {
        $settings = get_option( 'eseo_titles_meta_settings', [] );

        if ( is_front_page() || is_home() ) {
            $homepage_desc = isset($settings['homepage_desc']) ? $settings['homepage_desc'] : '';
            if ( ! empty( $homepage_desc ) ) {
                echo '<meta name="description" content="' . esc_attr( $this->parse_variables( $homepage_desc, 0, 'homepage' ) ) . '" />' . "\n";
            }
        } elseif ( is_singular() ) {
            $post_id = get_the_ID();
            $post_type = get_post_type( $post_id );
            
            // Description
            $custom_desc = get_post_meta( $post_id, '_eseo_meta_description', true );
            if ( empty( $custom_desc ) ) {
                $prefix = 'pt_' . $post_type;
                $custom_desc = isset($settings[$prefix.'_desc']) ? $settings[$prefix.'_desc'] : '%excerpt%';
            }

            if ( ! empty( $custom_desc ) ) {
                echo '<meta name="description" content="' . esc_attr( $this->parse_variables( $custom_desc, $post_id, 'post' ) ) . '" />' . "\n";
            }

            // Output Meta Robots
            $robots_index = get_post_meta( $post_id, '_eseo_meta_robots_index', true );
            if ( empty($robots_index) || $robots_index === 'default' ) {
                $prefix = 'pt_' . $post_type;
                $robots_index = isset($settings[$prefix.'_robots']) ? $settings[$prefix.'_robots'] : 'index';
            }

            $robots_follow = get_post_meta( $post_id, '_eseo_meta_robots_follow', true );
            
            $robots = [];
            if ( ! empty( $robots_index ) && $robots_index !== 'default' ) $robots[] = $robots_index;
            if ( ! empty( $robots_follow ) && $robots_follow !== 'default' ) $robots[] = $robots_follow;

            if ( ! empty( $robots ) ) {
                echo '<meta name="robots" content="' . esc_attr( implode( ', ', $robots ) ) . '" />' . "\n";
            }
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term ) {
                $prefix = 'tax_' . $term->taxonomy;
                $custom_desc = isset($settings[$prefix.'_desc']) ? $settings[$prefix.'_desc'] : '%excerpt%';
                if ( ! empty( $custom_desc ) ) {
                    echo '<meta name="description" content="' . esc_attr( $this->parse_variables( $custom_desc, $term->term_id, 'term' ) ) . '" />' . "\n";
                }
                
                $robots_index = isset($settings[$prefix.'_robots']) ? $settings[$prefix.'_robots'] : 'index';
                if ( $robots_index === 'noindex' ) {
                    echo '<meta name="robots" content="noindex, follow" />' . "\n";
                }
            }
        } elseif ( is_author() ) {
            $author = get_queried_object();
            if ( $author ) {
                $prefix = 'arch_author';
                $custom_desc = isset($settings[$prefix.'_desc']) ? $settings[$prefix.'_desc'] : '';
                if ( ! empty( $custom_desc ) ) {
                    echo '<meta name="description" content="' . esc_attr( $this->parse_variables( $custom_desc, $author->ID, 'author' ) ) . '" />' . "\n";
                }
                
                $robots_index = isset($settings[$prefix.'_robots']) ? $settings[$prefix.'_robots'] : 'noindex';
                if ( $robots_index === 'noindex' ) {
                    echo '<meta name="robots" content="noindex, follow" />' . "\n";
                }
            }
        }

        // Output Universal Canonical URL
        remove_action( 'wp_head', 'rel_canonical' );
        $canonical_url = '';
        if ( is_singular() ) {
            $canonical_url = get_post_meta( get_the_ID(), '_eseo_canonical_url', true );
            if ( empty( $canonical_url ) ) {
                $canonical_url = get_permalink();
            }
        } elseif ( is_front_page() ) {
            $canonical_url = home_url( '/' );
        } elseif ( is_home() && $page_for_posts = get_option( 'page_for_posts' ) ) {
            $canonical_url = get_permalink( $page_for_posts );
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $canonical_url = get_term_link( get_queried_object() );
        } elseif ( is_post_type_archive() ) {
            $canonical_url = get_post_type_archive_link( get_query_var( 'post_type' ) );
        } elseif ( is_author() ) {
            $canonical_url = get_author_posts_url( get_queried_object_id() );
        } else {
            $canonical_url = home_url( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) );
        }

        if ( ! is_wp_error( $canonical_url ) && ! empty( $canonical_url ) ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical_url ) . '" />' . "\n";
        }
    }

    private function parse_variables( $string, $object_id, $type = 'post' ) {
        $replacements = [
            '%sitename%'    => get_bloginfo( 'name' ),
            '%sitedesc%'    => get_bloginfo( 'description' ),
            '%currentyear%' => date( 'Y' ),
            '%currentmonth%'=> date( 'F' ),
            '%title%'       => '',
            '%date%'        => '',
            '%excerpt%'     => '',
            '%category%'    => '',
        ];

        if ( $type === 'post' ) {
            $post = get_post( $object_id );
            if ( $post ) {
                $replacements['%title%'] = $post->post_title;
                $replacements['%date%'] = get_the_date( '', $post );
                
                // Excerpt logic
                if ( ! empty( $post->post_excerpt ) ) {
                    $replacements['%excerpt%'] = wp_strip_all_tags( $post->post_excerpt );
                } else {
                    $replacements['%excerpt%'] = wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '...' );
                }

                // Category logic
                $categories = get_the_category( $post->ID );
                if ( ! empty( $categories ) ) {
                    $replacements['%category%'] = $categories[0]->name;
                }
            }
        } elseif ( $type === 'term' ) {
            $term = get_term( $object_id );
            if ( $term ) {
                $replacements['%title%'] = $term->name;
                $replacements['%excerpt%'] = wp_strip_all_tags( $term->description );
            }
        } elseif ( $type === 'homepage' ) {
            $replacements['%title%'] = get_bloginfo( 'name' );
            $replacements['%excerpt%'] = get_bloginfo( 'description' );
        } elseif ( $type === 'author' ) {
            $author = get_userdata( $object_id );
            if ( $author ) {
                $replacements['%title%'] = $author->display_name;
                $replacements['%excerpt%'] = wp_strip_all_tags( get_the_author_meta( 'description', $author->ID ) );
            }
        }

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $string );
    }
}
