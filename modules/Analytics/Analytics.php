<?php

namespace ESEO\Modules\Analytics;

class Analytics {

    public function init() {
        add_action( 'admin_post_eseo_analytics_clear_cache', [ $this, 'clear_cache' ] );
    }

    public function render_settings_page() {
        $status = $this->get_site_kit_status();
        
        ?>
        <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">
            <h1>Search Console Integration</h1>
            <p>Get valuable insights directly in your dashboard.</p>
            
            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:24px; max-width: 350px; margin-top: 20px;">
                <div style="display:flex; align-items:center; margin-bottom:20px;">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" style="height:24px; margin-right:10px;" alt="Google">
                    <span style="font-size:20px; font-weight:600; color:#5f6368;">Site Kit</span>
                </div>
                
                <h2 style="margin:0 0 10px 0; font-size:18px;">Get valuable insights with Site Kit by Google</h2>
                <p style="color:#50575e; font-size: 14px; line-height:1.5;">View traffic and search rankings on your dashboard by connecting your Google account.</p>
                <a href="https://sitekit.withgoogle.com/" target="_blank" style="text-decoration:none; color:#4285f4; font-weight:500;">Learn more &rarr;</a>
                
                <div style="margin: 20px 0 0 0; border-top: 1px solid #eee; padding-top: 20px;">
                    <?php if ( $status === 'not_installed' ) : ?>
                        <div style="color: #d63638; font-weight: bold; margin-bottom: 15px;">Site Kit is not installed.</div>
                        <a href="<?php echo admin_url('plugin-install.php?s=google+site+kit&tab=search&type=term'); ?>" class="button button-primary" style="width:100%; text-align:center;">Install Google Site Kit</a>
                    
                    <?php elseif ( $status === 'inactive' ) : ?>
                        <div style="color: #d63638; font-weight: bold; margin-bottom: 15px;">Site Kit is installed but inactive.</div>
                        <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-primary" style="width:100%; text-align:center;">Activate Google Site Kit</a>
                    
                    <?php elseif ( $status === 'not_connected' ) : ?>
                        <div style="color: #e88a31; font-weight: bold; margin-bottom: 15px;">Site Kit is active but not connected.</div>
                        <a href="<?php echo admin_url('admin.php?page=googlesitekit-dashboard'); ?>" class="button button-primary" style="width:100%; text-align:center;">Connect Google Account</a>
                    
                    <?php elseif ( $status === 'connected' ) : ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px;">
                            <span style="font-weight: 500; color: #1d2327;">Successfully connected</span>
                            <span style="color: #00a32a; font-weight: bold;">✓</span>
                        </div>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <input type="hidden" name="action" value="eseo_analytics_clear_cache">
                            <?php wp_nonce_field( 'eseo_clear_cache_nonce' ); ?>
                            <input type="submit" class="button" style="width:100%; text-align:center;" value="Refresh Cached Data">
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function clear_cache() {
        check_admin_referer( 'eseo_clear_cache_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        delete_transient( 'eseo_gsc_analytics_data' );

        wp_redirect( admin_url( 'admin.php?page=eseo-analytics' ) );
        exit;
    }

    public function get_site_kit_status() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! file_exists( WP_PLUGIN_DIR . '/google-site-kit/google-site-kit.php' ) ) {
            return 'not_installed';
        }

        if ( ! is_plugin_active( 'google-site-kit/google-site-kit.php' ) ) {
            return 'inactive';
        }

        $settings = get_option( 'googlesitekit_search-console_settings' );
        if ( empty( $settings ) || empty( $settings['propertyID'] ) ) {
            return 'not_connected';
        }

        return 'connected';
    }

    public function get_dashboard_data() {
        if ( $this->get_site_kit_status() !== 'connected' ) {
            return false;
        }

        $cached = get_transient( 'eseo_gsc_analytics_data' );
        if ( $cached !== false ) return $cached;

        $end_date = date( 'Y-m-d', strtotime('-2 days') ); // GSC data is delayed
        $start_date = date( 'Y-m-d', strtotime('-30 days') );

        // Natively hook into Site Kit's REST API using WordPress loopback
        $request_date = new \WP_REST_Request( 'GET', '/google-site-kit/v1/modules/search-console/data/searchanalytics' );
        $request_date->set_query_params([
            'startDate' => $start_date,
            'endDate' => $end_date,
            'dimensions' => 'date',
            'limit' => 30
        ]);
        $response_date = rest_do_request( $request_date );

        $request_pages = new \WP_REST_Request( 'GET', '/google-site-kit/v1/modules/search-console/data/searchanalytics' );
        $request_pages->set_query_params([
            'startDate' => $start_date,
            'endDate' => $end_date,
            'dimensions' => 'page',
            'limit' => 5
        ]);
        $response_pages = rest_do_request( $request_pages );

        $request_queries = new \WP_REST_Request( 'GET', '/google-site-kit/v1/modules/search-console/data/searchanalytics' );
        $request_queries->set_query_params([
            'startDate' => $start_date,
            'endDate' => $end_date,
            'dimensions' => 'query',
            'limit' => 5
        ]);
        $response_queries = rest_do_request( $request_queries );

        if ( $response_date->is_error() || $response_pages->is_error() || $response_queries->is_error() ) {
            return false;
        }

        $date_data = $response_date->get_data();
        $pages_data = $response_pages->get_data();
        $queries_data = $response_queries->get_data();

        if ( ! is_array($date_data) ) {
            return false; 
        }

        // Aggregate Totals
        $totals = [ 'clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0 ];
        $time_series = [];
        $total_pos = 0;

        foreach ( $date_data as $row ) {
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
            'top_pages' => is_array($pages_data) ? $pages_data : [],
            'top_queries' => is_array($queries_data) ? $queries_data : []
        ];

        set_transient( 'eseo_gsc_analytics_data', $data, 24 * HOUR_IN_SECONDS );
        return $data;
    }
}
