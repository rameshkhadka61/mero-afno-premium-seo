<?php

namespace ESEO\Modules\AiSEO;

class BulkSEO {

    public function init() {
        add_action( 'wp_ajax_eseo_get_unanalyzed_posts', [ $this, 'ajax_get_unanalyzed_posts' ] );
        add_action( 'wp_ajax_eseo_bulk_optimize_post', [ $this, 'ajax_bulk_optimize_post' ] );
    }

    public function ajax_get_unanalyzed_posts() {
        check_ajax_referer( 'eseo_dashboard_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;

        // Find all public post types
        $public_types = get_post_types( [ 'public' => true ], 'names' );
        $public_types = array_diff( $public_types, [ 'attachment' ] );
        
        $types_sql = "'" . implode( "','", array_map( 'esc_sql', $public_types ) ) . "'";

        // Find posts that do NOT have a complete SEO profile (missing title OR missing description OR missing keyword)
        // Wait, the simplest way is to find posts that are missing ANY of the three.
        // It's easier to find posts whose total count of non-empty SEO meta fields is < 3
        
        $query = "
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN (
                SELECT post_id, COUNT(meta_key) as score
                FROM {$wpdb->postmeta}
                WHERE meta_key IN ('_eseo_meta_title', '_eseo_meta_description', '_eseo_focus_keyword') 
                AND meta_value != ''
                GROUP BY post_id
            ) m ON p.ID = m.post_id
            WHERE p.post_status = 'publish' 
            AND p.post_type IN ($types_sql)
            AND (m.score IS NULL OR m.score < 3)
            ORDER BY p.post_date DESC
        ";

        $post_ids = $wpdb->get_col( $query );

        wp_send_json_success( $post_ids );
    }

    public function ajax_bulk_optimize_post() {
        check_ajax_referer( 'eseo_dashboard_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( 'Invalid post ID' );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( 'Post not found' );
        }

        $openai_key = get_option( 'eseo_openai_key' );
        $gemini_key = get_option( 'eseo_gemini_key' );
        $preferred_engine = get_option( 'eseo_preferred_ai_engine', 'smart' );

        if ( empty( $openai_key ) && empty( $gemini_key ) ) {
            wp_send_json_error( 'No API Key configured.' );
        }

        // Build Context
        $categories_text = '';
        $categories = get_the_category( $post_id );
        if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
            $cat_names = wp_list_pluck( $categories, 'name' );
            $categories_text = implode( ', ', $cat_names );
        }

        $content_snippet = wp_trim_words( strip_tags( $post->post_content ), 300 ); // Send up to 300 words

        $context = "Title: {$post->post_title}\n";
        if ( ! empty( $categories_text ) ) {
            $context .= "Categories: {$categories_text}\n";
        }
        if ( ! empty( $content_snippet ) ) {
            $context .= "Content Snippet: {$content_snippet}\n";
        }

        $prompt = "You are an expert SEO copywriter. Analyze the following blog post context:\n\n{$context}\n\nTask: Generate a highly unique, engaging SEO Title, a Meta Description, and a Focus Keyword. Draw heavily from the category themes and the content snippet to make it specific to this exact article.\n\nRules:\n1. The 'title' must be under 60 characters.\n2. The 'description' must be between 140 and 160 characters.\n3. The 'keyword' must be the single most effective Focus Keyword (1 to 4 words maximum).\n4. You MUST weave the 'keyword' into both the 'title' and the 'description'.\n\nOutput Format: Return the response strictly as valid JSON with keys 'title', 'description', and 'keyword', nothing else. Do not include markdown code blocks.";

        // Determine Engine
        $engine = '';
        if ( $preferred_engine === 'openai' && ! empty( $openai_key ) ) {
            $engine = 'openai';
        } elseif ( $preferred_engine === 'gemini' && ! empty( $gemini_key ) ) {
            $engine = 'gemini';
        } elseif ( ! empty( $gemini_key ) ) {
            $engine = 'gemini';
        } else {
            $engine = 'openai';
        }

        $json_response = '';

        if ( $engine === 'openai' ) {
            $openai_model = get_option( 'eseo_openai_model', 'gpt-3.5-turbo' );
            $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $openai_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model' => $openai_model,
                    'messages' => [
                        [ 'role' => 'user', 'content' => $prompt ]
                    ],
                    'response_format' => [ 'type' => 'json_object' ]
                ]),
                'timeout' => 20,
            ]);

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( 'OpenAI API Error: ' . $response->get_error_message() );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['choices'][0]['message']['content'] ) ) {
                $json_response = $data['choices'][0]['message']['content'];
            } else {
                wp_send_json_error( 'Invalid OpenAI API Response: ' . $body );
            }
        } else {
            // Gemini API
            $gemini_model = get_option( 'eseo_ai_model_v2', 'gemini-2.0-flash' );
            $response = wp_remote_post( 'https://generativelanguage.googleapis.com/v1/models/' . $gemini_model . ':generateContent?key=' . $gemini_key, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'contents' => [
                        [
                            'parts' => [
                                [ 'text' => $prompt ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json'
                    ]
                ]),
                'timeout' => 20,
            ]);

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( 'Gemini API Error: ' . $response->get_error_message() );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
                $json_response = $data['candidates'][0]['content']['parts'][0]['text'];
            } else {
                wp_send_json_error( 'Invalid Gemini API Response. ' . $body );
            }
        }

        // Parse JSON
        $parsed = json_decode( $json_response, true );
        if ( ! $parsed || ! isset( $parsed['title'] ) || ! isset( $parsed['description'] ) || ! isset( $parsed['keyword'] ) ) {
            wp_send_json_error( 'Failed to parse JSON response from AI: ' . $json_response );
        }

        // Save to Post Meta
        // Only overwrite if it was previously empty, but the prompt query fetches missing so it's fine.
        $existing_title = get_post_meta( $post_id, '_eseo_meta_title', true );
        $existing_desc = get_post_meta( $post_id, '_eseo_meta_description', true );
        $existing_kw = get_post_meta( $post_id, '_eseo_focus_keyword', true );

        if ( empty( $existing_title ) ) {
            update_post_meta( $post_id, '_eseo_meta_title', sanitize_text_field( $parsed['title'] ) );
        }
        if ( empty( $existing_desc ) ) {
            update_post_meta( $post_id, '_eseo_meta_description', sanitize_textarea_field( $parsed['description'] ) );
        }
        if ( empty( $existing_kw ) ) {
            update_post_meta( $post_id, '_eseo_focus_keyword', sanitize_text_field( $parsed['keyword'] ) );
        }

        wp_send_json_success( 'Optimized post ID ' . $post_id );
    }
}
