<?php

namespace ESEO\Modules\Analytics;

class Analytics {

    private $token_url = 'https://oauth2.googleapis.com/token';
    private $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth';
    private $api_url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/';
    private $redirect_uri;

    public function init() {
        $this->redirect_uri = admin_url( 'admin.php?page=eseo-analytics' );
        add_action( 'admin_init', [ $this, 'handle_oauth_callback' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_eseo_analytics_clear', [ $this, 'clear_credentials' ] );
        add_action( 'admin_post_eseo_analytics_property', [ $this, 'save_property' ] );
    }

    public function register_settings() {
        register_setting( 'eseo_analytics_options', 'eseo_gsc_client_id' );
        register_setting( 'eseo_analytics_options', 'eseo_gsc_client_secret' );
        register_setting( 'eseo_analytics_options', 'eseo_gsc_access_token' );
        register_setting( 'eseo_analytics_options', 'eseo_gsc_refresh_token' );
        register_setting( 'eseo_analytics_options', 'eseo_gsc_property' );
    }

    public function render_settings_page() {
        $client_id = get_option( 'eseo_gsc_client_id' );
        $client_secret = get_option( 'eseo_gsc_client_secret' );
        $refresh_token = get_option( 'eseo_gsc_refresh_token' );
        $property = get_option( 'eseo_gsc_property' );

        ?>
        <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">
            <h1>Google Search Console Analytics</h1>
            <p>Connect your Google Search Console account to view real-time traffic data directly in your dashboard.</p>
            
            <?php if ( ! $refresh_token ) : ?>
                <div style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px; max-width: 800px; margin-top: 20px;">
                    <h2 style="margin-top:0;">1. Enter Google OAuth Credentials</h2>
                    <p>To keep your data secure and private, you must create your own Google Cloud Project and generate OAuth 2.0 Client credentials.</p>
                    <ol>
                        <li>Go to the <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>.</li>
                        <li>Create a New Project and enable the <strong>Google Search Console API</strong>.</li>
                        <li>Go to APIs & Services > Credentials > Create Credentials > OAuth client ID.</li>
                        <li>Set Application type to "Web application".</li>
                        <li>Add this exact URL to <strong>Authorized redirect URIs</strong>:<br> <code><?php echo esc_url($this->redirect_uri); ?></code></li>
                    </ol>

                    <form method="post" action="options.php">
                        <?php settings_fields( 'eseo_analytics_options' ); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="eseo_gsc_client_id">Client ID</label></th>
                                <td>
                                    <input name="eseo_gsc_client_id" type="text" id="eseo_gsc_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" placeholder="XXXXXXX.apps.googleusercontent.com">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="eseo_gsc_client_secret">Client Secret</label></th>
                                <td>
                                    <input name="eseo_gsc_client_secret" type="password" id="eseo_gsc_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text" placeholder="GOCSPX-XXXXXXX">
                                </td>
                            </tr>
                        </table>
                        <?php submit_button( 'Save Credentials' ); ?>
                    </form>

                    <?php if ( $client_id && $client_secret ) : ?>
                        <h2 style="margin-top:30px; border-top:1px solid #eee; padding-top:20px;">2. Authenticate</h2>
                        <a href="<?php echo esc_url( $this->get_auth_url() ); ?>" class="button button-primary button-large" style="background:#4285f4; border-color:#4285f4;">Authenticate with Google</a>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px; max-width: 800px; margin-top: 20px;">
                    <h2 style="margin-top:0; color:#00a32a;">✅ Successfully Connected to Google</h2>
                    
                    <?php 
                    // Fetch properties
                    $properties = $this->fetch_properties();
                    if ( is_wp_error( $properties ) ) {
                        echo '<div class="notice notice-error"><p>Error fetching properties: ' . esc_html( $properties->get_error_message() ) . '</p></div>';
                    } elseif ( empty( $properties ) ) {
                        echo '<p>No Search Console properties found for this account.</p>';
                    } else {
                        ?>
                        <h3 style="margin-top:30px; border-top:1px solid #eee; padding-top:20px;">Select Property</h3>
                        <p>Select the Search Console property that matches this website.</p>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <input type="hidden" name="action" value="eseo_analytics_property">
                            <?php wp_nonce_field( 'eseo_save_property_nonce' ); ?>
                            <select name="eseo_gsc_property" style="width:100%; max-width:400px; padding:5px;">
                                <option value="">-- Select Property --</option>
                                <?php foreach ( $properties as $prop ) : ?>
                                    <option value="<?php echo esc_attr( $prop['siteUrl'] ); ?>" <?php selected( $property, $prop['siteUrl'] ); ?>><?php echo esc_html( $prop['siteUrl'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p>
                                <input type="submit" class="button button-primary" value="Save Property">
                            </p>
                        </form>
                        <?php
                    }
                    ?>

                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top:40px; border-top:1px solid #eee; padding-top:20px;">
                        <input type="hidden" name="action" value="eseo_analytics_clear">
                        <?php wp_nonce_field( 'eseo_clear_auth_nonce' ); ?>
                        <input type="submit" class="button" value="Disconnect Google Account" onclick="return confirm('Are you sure you want to disconnect?');">
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_auth_url() {
        $client_id = get_option( 'eseo_gsc_client_id' );
        $params = [
            'client_id'     => $client_id,
            'redirect_uri'  => $this->redirect_uri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type'   => 'offline',
            'prompt'        => 'consent'
        ];
        return add_query_arg( $params, $this->auth_url );
    }

    public function handle_oauth_callback() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'eseo-analytics' ) return;
        if ( ! isset( $_GET['code'] ) ) return;

        $code = sanitize_text_field( $_GET['code'] );
        $client_id = get_option( 'eseo_gsc_client_id' );
        $client_secret = get_option( 'eseo_gsc_client_secret' );

        $response = wp_remote_post( $this->token_url, [
            'body' => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $this->redirect_uri,
                'grant_type'    => 'authorization_code'
            ]
        ]);

        if ( is_wp_error( $response ) ) {
            wp_die( 'OAuth Error: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['access_token'] ) ) {
            update_option( 'eseo_gsc_access_token', $body['access_token'] );
        }
        if ( isset( $body['refresh_token'] ) ) {
            update_option( 'eseo_gsc_refresh_token', $body['refresh_token'] );
        }

        wp_redirect( $this->redirect_uri );
        exit;
    }

    public function clear_credentials() {
        check_admin_referer( 'eseo_clear_auth_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        delete_option( 'eseo_gsc_access_token' );
        delete_option( 'eseo_gsc_refresh_token' );
        delete_option( 'eseo_gsc_property' );
        
        // Also clear any cached dashboard data
        delete_transient('eseo_gsc_analytics_data');

        wp_redirect( $this->redirect_uri );
        exit;
    }

    public function save_property() {
        check_admin_referer( 'eseo_save_property_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $property = sanitize_text_field( $_POST['eseo_gsc_property'] );
        update_option( 'eseo_gsc_property', $property );
        
        // Clear cached data so it fetches the new property
        delete_transient('eseo_gsc_analytics_data');

        wp_redirect( $this->redirect_uri );
        exit;
    }

    private function refresh_access_token() {
        $client_id = get_option( 'eseo_gsc_client_id' );
        $client_secret = get_option( 'eseo_gsc_client_secret' );
        $refresh_token = get_option( 'eseo_gsc_refresh_token' );

        if ( ! $refresh_token ) return false;

        $response = wp_remote_post( $this->token_url, [
            'body' => [
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token'
            ]
        ]);

        if ( is_wp_error( $response ) ) return false;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body['access_token'] ) ) {
            update_option( 'eseo_gsc_access_token', $body['access_token'] );
            return $body['access_token'];
        }

        return false;
    }

    private function get_valid_access_token() {
        // Optimistically try current token, if API fails with 401, we will refresh.
        // For simplicity, we just refresh it if we don't have one, or we handle 401s in the requests.
        $token = get_option( 'eseo_gsc_access_token' );
        if ( ! $token ) {
            $token = $this->refresh_access_token();
        }
        return $token;
    }

    private function api_request( $url, $args = [] ) {
        $token = $this->get_valid_access_token();
        if ( ! $token ) return new \WP_Error( 'no_token', 'No access token available.' );

        $args['headers'] = isset( $args['headers'] ) ? $args['headers'] : [];
        $args['headers']['Authorization'] = 'Bearer ' . $token;

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 401 ) {
            // Token expired, refresh and try again
            $token = $this->refresh_access_token();
            if ( $token ) {
                $args['headers']['Authorization'] = 'Bearer ' . $token;
                $response = wp_remote_request( $url, $args );
                $code = wp_remote_retrieve_response_code( $response );
            }
        }

        if ( $code !== 200 ) {
            return new \WP_Error( 'api_error', 'API Error: ' . wp_remote_retrieve_body( $response ) );
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    public function fetch_properties() {
        $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites';
        $data = $this->api_request( $url, [ 'method' => 'GET' ] );
        
        if ( is_wp_error( $data ) ) return $data;
        if ( ! isset( $data['siteEntry'] ) ) return [];
        return $data['siteEntry'];
    }

    public function get_dashboard_data() {
        $property = get_option( 'eseo_gsc_property' );
        if ( ! $property ) return false;

        $cached = get_transient( 'eseo_gsc_analytics_data' );
        if ( $cached !== false ) return $cached;

        $end_date = date( 'Y-m-d', strtotime('-2 days') ); // GSC data is delayed by 48hrs
        $start_date = date( 'Y-m-d', strtotime('-30 days') );

        $url = $this->api_url . urlencode($property) . '/searchAnalytics/query';

        // 1. Fetch Totals & Time Series (Date grouping)
        $date_req = $this->api_request( $url, [
            'method' => 'POST',
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => json_encode([
                'startDate' => $start_date,
                'endDate'   => $end_date,
                'dimensions' => ['date'],
                'rowLimit' => 30
            ])
        ]);

        // 2. Fetch Top Pages
        $pages_req = $this->api_request( $url, [
            'method' => 'POST',
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => json_encode([
                'startDate' => $start_date,
                'endDate'   => $end_date,
                'dimensions' => ['page'],
                'rowLimit' => 5
            ])
        ]);

        // 3. Fetch Top Queries
        $queries_req = $this->api_request( $url, [
            'method' => 'POST',
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => json_encode([
                'startDate' => $start_date,
                'endDate'   => $end_date,
                'dimensions' => ['query'],
                'rowLimit' => 5
            ])
        ]);

        if ( is_wp_error( $date_req ) || is_wp_error( $pages_req ) || is_wp_error( $queries_req ) ) {
            return false;
        }

        // Aggregate Totals
        $totals = [ 'clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0 ];
        $time_series = [];
        $rows = isset($date_req['rows']) ? $date_req['rows'] : [];
        $total_pos = 0;

        foreach ( $rows as $row ) {
            $totals['clicks'] += $row['clicks'];
            $totals['impressions'] += $row['impressions'];
            $total_pos += $row['position'] * $row['impressions'];
            $time_series[] = [
                'date' => $row['keys'][0],
                'clicks' => $row['clicks'],
                'impressions' => $row['impressions']
            ];
        }

        if ( $totals['impressions'] > 0 ) {
            $totals['ctr'] = ($totals['clicks'] / $totals['impressions']) * 100;
            $totals['position'] = $total_pos / $totals['impressions'];
        }

        $data = [
            'totals' => $totals,
            'time_series' => $time_series,
            'top_pages' => isset($pages_req['rows']) ? $pages_req['rows'] : [],
            'top_queries' => isset($queries_req['rows']) ? $queries_req['rows'] : []
        ];

        set_transient( 'eseo_gsc_analytics_data', $data, 24 * HOUR_IN_SECONDS );
        return $data;
    }
}
