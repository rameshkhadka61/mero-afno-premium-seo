<?php

namespace ESEO\Modules\Redirects;

class Redirects {

    public function init() {
        add_action( 'template_redirect', [ $this, 'intercept_404' ] );
        add_action( 'post_updated', [ $this, 'auto_redirect_slug_change' ], 10, 3 );
        add_action( 'admin_init', [ $this, 'handle_admin_actions' ] );
    }

    public function ensure_tables() {
        global $wpdb;
        $table_redirects = $wpdb->prefix . 'eseo_redirects';
        $table_404 = $wpdb->prefix . 'eseo_404_logs';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_redirects'" ) !== $table_redirects || $wpdb->get_var( "SHOW TABLES LIKE '$table_404'" ) !== $table_404 ) {
            \ESEO\Core\Activator::activate();
        }
    }

    public function auto_redirect_slug_change( $post_id, $post_after, $post_before ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! in_array( $post_after->post_status, [ 'publish', 'private' ], true ) ) {
            return;
        }
        if ( $post_before->post_name === $post_after->post_name ) {
            return;
        }

        $old_permalink = get_permalink( $post_before );
        $new_permalink = get_permalink( $post_after );

        if ( ! $old_permalink || ! $new_permalink || $old_permalink === $new_permalink ) {
            return;
        }

        $url_from = ltrim( parse_url( $old_permalink, PHP_URL_PATH ), '/' );
        $url_to   = ltrim( parse_url( $new_permalink, PHP_URL_PATH ), '/' );

        if ( empty( $url_from ) || empty( $url_to ) ) {
            return;
        }

        $this->ensure_tables();
        global $wpdb;
        $table_name = $wpdb->prefix . 'eseo_redirects';

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE url_from = %s LIMIT 1", $url_from ) );
        if ( ! $exists ) {
            $wpdb->insert( $table_name, [
                'url_from' => $url_from,
                'url_to'   => '/' . $url_to,
                'type'     => '301',
                'status'   => 'active',
                'hits'     => 0
            ] );
        } else {
            $wpdb->update( $table_name, [
                'url_to' => '/' . $url_to,
                'type'   => '301',
                'status' => 'active'
            ], [ 'id' => $exists ] );
        }
    }

    public function intercept_404() {
        if ( ! is_404() ) {
            return;
        }

        $this->ensure_tables();
        global $wpdb;
        $table_name = $wpdb->prefix . 'eseo_redirects';
        $table_404  = $wpdb->prefix . 'eseo_404_logs';

        $current_url = sanitize_text_field( $_SERVER['REQUEST_URI'] );
        $current_url = ltrim( $current_url, '/' );

        // Direct match
        $query = $wpdb->prepare( "SELECT * FROM $table_name WHERE url_from = %s AND status = 'active' LIMIT 1", $current_url );
        $redirect = $wpdb->get_row( $query );

        if ( $redirect ) {
            $wpdb->query( $wpdb->prepare( "UPDATE $table_name SET hits = hits + 1, last_accessed = %s WHERE id = %d", current_time('mysql'), $redirect->id ) );

            $to_url = $redirect->url_to;
            if ( strpos( $to_url, 'http' ) !== 0 ) {
                $to_url = home_url( '/' . ltrim( $to_url, '/' ) );
            }

            $type = intval( $redirect->type );
            if ( ! in_array( $type, [ 301, 302, 307, 410, 451 ], true ) ) {
                $type = 301;
            }

            wp_redirect( $to_url, $type );
            exit;
        } else {
            // Log 404
            $ref = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( $_SERVER['HTTP_REFERER'] ) : '';
            $ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'], 0, 250 ) ) : '';
            $now = current_time( 'mysql' );

            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, hits FROM $table_404 WHERE url = %s LIMIT 1", $current_url ) );
            if ( $existing ) {
                $wpdb->query( $wpdb->prepare( "UPDATE $table_404 SET hits = hits + 1, last_accessed = %s, referrer = %s WHERE id = %d", $now, $ref, $existing->id ) );
            } else {
                $wpdb->insert( $table_404, [
                    'url'           => $current_url,
                    'referrer'      => $ref,
                    'user_agent'    => $ua,
                    'hits'          => 1,
                    'last_accessed' => $now
                ] );
            }
        }
    }

    public function handle_admin_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( isset( $_POST['eseo_add_redirect'] ) && check_admin_referer( 'eseo_redirects_action' ) ) {
            $this->ensure_tables();
            global $wpdb;
            $table_name = $wpdb->prefix . 'eseo_redirects';
            $from = ltrim( sanitize_text_field( $_POST['url_from'] ), '/' );
            $to   = sanitize_text_field( $_POST['url_to'] );
            $type = sanitize_text_field( $_POST['redirect_type'] );
            if ( $from && $to ) {
                $wpdb->insert( $table_name, [
                    'url_from' => $from,
                    'url_to'   => $to,
                    'type'     => $type,
                    'status'   => 'active',
                    'hits'     => 0
                ] );
                // If this was added from a 404 log, remove from 404 logs
                if ( isset( $_POST['delete_404_id'] ) && intval( $_POST['delete_404_id'] ) > 0 ) {
                    $table_404 = $wpdb->prefix . 'eseo_404_logs';
                    $wpdb->delete( $table_404, [ 'id' => intval( $_POST['delete_404_id'] ) ] );
                }
            }
            wp_redirect( admin_url( 'admin.php?page=eseo-redirects&added=1' ) );
            exit;
        }
        if ( isset( $_GET['delete_redirect'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_redirect_' . intval( $_GET['delete_redirect'] ) ) ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'eseo_redirects';
            $wpdb->delete( $table_name, [ 'id' => intval( $_GET['delete_redirect'] ) ] );
            wp_redirect( admin_url( 'admin.php?page=eseo-redirects&deleted=1' ) );
            exit;
        }
        if ( isset( $_GET['clear_404s'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'clear_404s_nonce' ) ) {
            global $wpdb;
            $table_404 = $wpdb->prefix . 'eseo_404_logs';
            $wpdb->query( "TRUNCATE TABLE $table_404" );
            wp_redirect( admin_url( 'admin.php?page=eseo-redirects&tab=404s&cleared=1' ) );
            exit;
        }
    }

    public function render_settings_page() {
        $this->ensure_tables();
        global $wpdb;
        $table_redirects = $wpdb->prefix . 'eseo_redirects';
        $table_404 = $wpdb->prefix . 'eseo_404_logs';

        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'redirects';
        $redirects = $wpdb->get_results( "SELECT * FROM $table_redirects ORDER BY id DESC LIMIT 100" );
        $logs_404  = $wpdb->get_results( "SELECT * FROM $table_404 ORDER BY hits DESC LIMIT 100" );
        ?>
        <div class="wrap">
            <h1 style="display:flex; align-items:center; gap:10px;">⚡ Redirects & 404 Monitor</h1>
            <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="<?php echo admin_url('admin.php?page=eseo-redirects&tab=redirects'); ?>" class="nav-tab <?php echo $tab === 'redirects' ? 'nav-tab-active' : ''; ?>">🔄 301 Redirect Manager (<?php echo count($redirects); ?>)</a>
                <a href="<?php echo admin_url('admin.php?page=eseo-redirects&tab=404s'); ?>" class="nav-tab <?php echo $tab === '404s' ? 'nav-tab-active' : ''; ?>">🚨 404 Error Monitor (<?php echo count($logs_404); ?>)</a>
            </h2>

            <?php if ( $tab === 'redirects' ): ?>
            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-bottom:20px;">
                <h3 style="margin-top:0;">Add New Redirect Rule</h3>
                <form method="post" action="">
                    <?php wp_nonce_field( 'eseo_redirects_action' ); ?>
                    <div style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
                        <div style="flex:2; min-width:200px;">
                            <label><strong>Source URL Path (From)</strong></label>
                            <input type="text" name="url_from" placeholder="e.g. old-post-slug/" required style="width:100%; margin-top:5px;" />
                        </div>
                        <div style="flex:2; min-width:200px;">
                            <label><strong>Target URL Path or Full URL (To)</strong></label>
                            <input type="text" name="url_to" placeholder="e.g. /new-post-slug/ or https://..." required style="width:100%; margin-top:5px;" />
                        </div>
                        <div style="flex:1; min-width:120px;">
                            <label><strong>Redirect Type</strong></label>
                            <select name="redirect_type" style="width:100%; margin-top:5px;">
                                <option value="301">301 (Permanent)</option>
                                <option value="302">302 (Temporary)</option>
                                <option value="307">307 (Temporary Redirect)</option>
                                <option value="410">410 (Content Deleted)</option>
                            </select>
                        </div>
                        <div>
                            <input type="hidden" name="delete_404_id" id="eseo_delete_404_id" value="0" />
                            <button type="submit" name="eseo_add_redirect" class="button button-primary" style="height:30px;">+ Add Redirect</button>
                        </div>
                    </div>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Source URL (From)</th>
                        <th>Target URL (To)</th>
                        <th>Type</th>
                        <th>Hits</th>
                        <th>Last Accessed</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $redirects ) ): ?>
                    <tr><td colspan="7" style="text-align:center; padding:20px;">No redirects created yet. Note: When you change a post slug, Mero SEO will automatically create a 301 redirect rule here!</td></tr>
                    <?php else: foreach ( $redirects as $row ): ?>
                    <tr>
                        <td><?php echo intval($row->id); ?></td>
                        <td><code>/<?php echo esc_html($row->url_from); ?></code></td>
                        <td><a href="<?php echo esc_url($row->url_to); ?>" target="_blank"><code><?php echo esc_html($row->url_to); ?></code></a></td>
                        <td><span style="background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; font-weight:bold;"><?php echo esc_html($row->type); ?></span></td>
                        <td><strong><?php echo intval($row->hits); ?></strong></td>
                        <td><?php echo ! empty($row->last_accessed) && $row->last_accessed !== '0000-00-00 00:00:00' ? esc_html($row->last_accessed) : 'Never'; ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url( admin_url('admin.php?page=eseo-redirects&delete_redirect='.$row->id), 'delete_redirect_'.$row->id ); ?>" class="button button-small" style="color:#b32d2e; border-color:#b32d2e;" onclick="return confirm('Delete this redirect rule?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php elseif ( $tab === '404s' ): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <p class="description">Below are unresolved 404 broken links caught on your site. Click <strong>Redirect</strong> to quickly map them to active content.</p>
                <?php if ( ! empty( $logs_404 ) ): ?>
                <a href="<?php echo wp_nonce_url( admin_url('admin.php?page=eseo-redirects&tab=404s&clear_404s=1'), 'clear_404s_nonce' ); ?>" class="button button-secondary" style="color:#b32d2e;" onclick="return confirm('Clear all logged 404 errors?');">🗑️ Clear 404 Logs</a>
                <?php endif; ?>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>404 Broken URL</th>
                        <th>Referrer (Where they came from)</th>
                        <th>Hits</th>
                        <th>Last Accessed</th>
                        <th style="width:140px;">Quick Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $logs_404 ) ): ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px;">🎉 No 404 broken URLs logged! Your site links are clean.</td></tr>
                    <?php else: foreach ( $logs_404 as $log ): ?>
                    <tr>
                        <td><code style="color:#b32d2e;">/<?php echo esc_html($log->url); ?></code></td>
                        <td><?php echo !empty($log->referrer) ? '<a href="'.esc_url($log->referrer).'" target="_blank">'.esc_html($log->referrer).'</a>' : '<span style="color:#999;">Direct / Unknown</span>'; ?></td>
                        <td><span style="background:#fee2e2; color:#b91c1c; padding:2px 8px; border-radius:12px; font-weight:bold;"><?php echo intval($log->hits); ?></span></td>
                        <td><?php echo esc_html($log->last_accessed); ?></td>
                        <td>
                            <button type="button" class="button button-small button-primary eseo-quick-redirect-btn" data-id="<?php echo intval($log->id); ?>" data-url="<?php echo esc_attr($log->url); ?>">🔄 Fix Redirect</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <script>
            jQuery(document).ready(function($){
                $('.eseo-quick-redirect-btn').on('click', function(){
                    var url = $(this).data('url');
                    var id = $(this).data('id');
                    var target = prompt('Enter destination URL or path to redirect /' + url + ' to:', '/');
                    if (target) {
                        $('input[name="url_from"]').val(url);
                        $('input[name="url_to"]').val(target);
                        $('#eseo_delete_404_id').val(id);
                        window.location.href = '<?php echo admin_url('admin.php?page=eseo-redirects&tab=redirects'); ?>';
                    }
                });
            });
            </script>
            <?php endif; ?>
        </div>
        <?php
    }
}
