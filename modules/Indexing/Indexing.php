<?php

namespace ESEO\Modules\Indexing;

class Indexing {

    public function init() {
        // Hook into post status transitions
        add_action( 'transition_post_status', [ $this, 'handle_post_transition' ], 10, 3 );
    }

    public function handle_post_transition( $new_status, $old_status, $post ) {
        // Only care about public post types
        $public_post_types = get_post_types( [ 'public' => true ], 'names' );
        if ( ! in_array( $post->post_type, $public_post_types ) ) {
            return;
        }

        $url = get_permalink( $post->ID );
        if ( ! $url ) {
            return;
        }

        if ( $new_status === 'publish' && $old_status !== 'publish' ) {
            // Newly published
            $this->notify_search_engines( $url, 'URL_UPDATED' );
        } elseif ( $new_status === 'publish' && $old_status === 'publish' ) {
            // Updated
            $this->notify_search_engines( $url, 'URL_UPDATED' );
        } elseif ( $new_status === 'trash' || $new_status === 'delete' ) {
            // Deleted or trashed
            if ( $old_status === 'publish' ) {
                $this->notify_search_engines( $url, 'URL_DELETED' );
            }
        }
    }

    private function notify_search_engines( $url, $type = 'URL_UPDATED' ) {
        // Run in background if possible, or schedule single event to avoid slowing down saving
        wp_schedule_single_event( time(), 'eseo_ping_search_engines', [ $url, $type ] );
    }

    public static function execute_ping( $url, $type ) {
        $instance = new self();
        $instance->ping_google( $url, $type );
        $instance->ping_bing( $url ); // Bing only supports submission, not deletion via this specific endpoint easily
    }

    private function ping_google( $url, $type ) {
        $json_key = get_option( 'eseo_google_indexing_key' );
        if ( empty( $json_key ) ) {
            return;
        }

        $key_data = json_decode( $json_key, true );
        if ( ! $key_data || empty( $key_data['private_key'] ) || empty( $key_data['client_email'] ) ) {
            return;
        }

        $token = $this->get_google_access_token( $key_data );
        if ( ! $token ) {
            return;
        }

        $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
        $body = wp_json_encode( [
            'url'  => $url,
            'type' => $type
        ] );

        $response = wp_remote_post( $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ],
            'body'    => $body,
            'timeout' => 15
        ] );

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code == 200 ) {
                update_option( 'eseo_last_google_ping', time() );
            } else {
                error_log( 'ESEO Google Indexing Error: ' . wp_remote_retrieve_body( $response ) );
            }
        }
    }

    private function ping_bing( $url ) {
        $bing_key = get_option( 'eseo_bing_api_key' );
        if ( empty( $bing_key ) ) {
            return;
        }

        $site_url = esc_url( get_site_url() );
        $endpoint = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrl?apikey=' . $bing_key;

        $body = wp_json_encode( [
            'siteUrl' => $site_url,
            'url'     => $url
        ] );

        $response = wp_remote_post( $endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json'
            ],
            'body'    => $body,
            'timeout' => 15
        ] );

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code == 200 ) {
                update_option( 'eseo_last_bing_ping', time() );
            } else {
                error_log( 'ESEO Bing Indexing Error: ' . wp_remote_retrieve_body( $response ) );
            }
        }
    }

    private function get_google_access_token( $key_data ) {
        // Simple JWT implementation for Google OAuth2
        $header = wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] );
        $now = time();
        $claim = wp_json_encode( [
            'iss'   => $key_data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now
        ] );

        $base64UrlHeader = str_replace( ['+', '/', '='], ['-', '_', ''], base64_encode( $header ) );
        $base64UrlClaim = str_replace( ['+', '/', '='], ['-', '_', ''], base64_encode( $claim ) );
        $signature_input = $base64UrlHeader . '.' . $base64UrlClaim;

        $signature = '';
        if ( ! openssl_sign( $signature_input, $signature, $key_data['private_key'], 'sha256' ) ) {
            return false;
        }

        $base64UrlSignature = str_replace( ['+', '/', '='], ['-', '_', ''], base64_encode( $signature ) );
        $jwt = $signature_input . '.' . $base64UrlSignature;

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            ]
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        return isset( $data['access_token'] ) ? $data['access_token'] : false;
    }
}
