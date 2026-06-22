<?php

namespace ESEO\Modules\AiSEO;

class AiSEO {

    public function init() {
        add_action( 'wp_ajax_eseo_generate_meta', [ $this, 'ajax_generate_meta' ] );
    }

    public function ajax_generate_meta() {
        check_ajax_referer( 'eseo_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $type = sanitize_text_field( $_POST['type'] ); // 'title' or 'description'
        $keyword = sanitize_text_field( $_POST['keyword'] );
        $content = sanitize_textarea_field( $_POST['content'] ?? '' );
        
        $openai_key = get_option( 'eseo_openai_key' );
        $gemini_key = get_option( 'eseo_gemini_key' );
        $preferred_engine = get_option( 'eseo_preferred_ai_engine', 'smart' );

        if ( empty( $openai_key ) && empty( $gemini_key ) ) {
            wp_send_json_error( [
                'code' => 'no_api_key',
                'message' => 'Please configure either your OpenAI or Gemini API Key in the Enterprise SEO Settings.'
            ] );
        }

        $prompt = '';
        if ( $type === 'title' ) {
            $prompt = "Generate a highly engaging, SEO-optimized title for a blog post about '{$keyword}'. The title must be under 60 characters. Return only the title text.";
        } elseif ( $type === 'description' ) {
            $prompt = "Generate an SEO-optimized meta description for a blog post about '{$keyword}'. Include the keyword naturally. Keep it between 140 and 160 characters. Return only the description text.";
        } elseif ( $type === 'keyword' ) {
            $post_title = sanitize_text_field( $_POST['post_title'] ?? '' );
            $prompt = "Act as an expert SEO researcher. Based on the following post title: '{$post_title}', suggest the single most effective Focus Keyword. The keyword should ideally have high search volume intent. Return ONLY the keyword text (1 to 4 words maximum), nothing else.";
        }

        // Determine Engine based on user preference and key availability
        $engine = '';

        if ( $preferred_engine === 'openai' && ! empty( $openai_key ) ) {
            $engine = 'openai';
        } elseif ( $preferred_engine === 'gemini' && ! empty( $gemini_key ) ) {
            $engine = 'gemini';
        } elseif ( $preferred_engine === 'smart' && ! empty( $openai_key ) && ! empty( $gemini_key ) ) {
            // Both keys exist and smart routing is selected
            if ( $type === 'keyword' ) {
                $engine = 'gemini';
            } else {
                $engine = 'openai';
            }
        } elseif ( ! empty( $openai_key ) ) {
            // Fallback if the preferred key is missing
            $engine = 'openai';
        } else {
            $engine = 'gemini';
        }
        
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
                    ]
                ]),
                'timeout' => 15,
            ]);

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( 'OpenAI API Request Failed: ' . $response->get_error_message() );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['choices'][0]['message']['content'] ) ) {
                $generated = trim( $data['choices'][0]['message']['content'], '"\'' );
                wp_send_json_success( $generated );
            } else {
                wp_send_json_error( 'Invalid OpenAI API Response.' );
            }
        } else {
            // Gemini API
            $gemini_model = get_option( 'eseo_gemini_model', 'gemini-2.0-flash' );
            $response = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/' . $gemini_model . ':generateContent?key=' . $gemini_key, [
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
                    ]
                ]),
                'timeout' => 15,
            ]);

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( 'Gemini API Request Failed: ' . $response->get_error_message() );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
                $generated = trim( $data['candidates'][0]['content']['parts'][0]['text'], '"\'' );
                wp_send_json_success( $generated );
            } else {
                wp_send_json_error( 'Invalid Gemini API Response. ' . $body );
            }
        }
    }
}
