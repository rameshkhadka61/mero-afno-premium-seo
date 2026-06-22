<?php

namespace ESEO\Admin;

class Menu {

    public function init() {
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_admin_menu() {
        add_menu_page(
            'Mero Afno Premium SEO',
            'Mero Afno Premium SEO',
            'manage_options',
            'enterprise-seo',
            [ $this, 'render_dashboard' ],
            'dashicons-chart-line',
            80
        );

        add_submenu_page(
            'enterprise-seo',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'enterprise-seo',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'enterprise-seo',
            'AI Settings',
            'AI Settings',
            'manage_options',
            'eseo-ai-settings',
            [ $this, 'render_ai_settings' ]
        );

        add_submenu_page(
            'enterprise-seo',
            'Indexing API',
            'Indexing API',
            'manage_options',
            'eseo-indexing-settings',
            [ $this, 'render_indexing_settings' ]
        );
    }

    public function register_settings() {
        register_setting( 'eseo_ai_options', 'eseo_openai_key' );
        register_setting( 'eseo_ai_options', 'eseo_openai_model' );
        register_setting( 'eseo_ai_options', 'eseo_gemini_key' );
        register_setting( 'eseo_ai_options', 'eseo_gemini_model' );
        register_setting( 'eseo_ai_options', 'eseo_preferred_ai_engine' );
        register_setting( 'eseo_ai_options', 'eseo_modules_disabled', [
            'type' => 'array',
            'default' => []
        ]);

        register_setting( 'eseo_indexing_options', 'eseo_google_indexing_key' );
        register_setting( 'eseo_indexing_options', 'eseo_bing_api_key' );
    }

    public function render_dashboard() {
        global $wpdb;

        // Fetch dynamic stats safely
        $redirects_table = $wpdb->prefix . 'eseo_redirects';
        $links_table = $wpdb->prefix . 'eseo_links';

        $total_redirects = get_transient( 'eseo_dashboard_redirects_count' );
        if ( false === $total_redirects ) {
            $total_redirects = 0;
            if ( $wpdb->get_var("SHOW TABLES LIKE '$redirects_table'") === $redirects_table ) {
                $total_redirects = (int) $wpdb->get_var("SELECT SUM(hits) FROM $redirects_table");
            }
            set_transient( 'eseo_dashboard_redirects_count', $total_redirects, 12 * HOUR_IN_SECONDS );
        }

        $total_links = get_transient( 'eseo_dashboard_links_count' );
        if ( false === $total_links ) {
            $total_links = 0;
            if ( $wpdb->get_var("SHOW TABLES LIKE '$links_table'") === $links_table ) {
                $total_links = (int) $wpdb->get_var("SELECT COUNT(*) FROM $links_table");
            }
            set_transient( 'eseo_dashboard_links_count', $total_links, 12 * HOUR_IN_SECONDS );
        }

        ?>
        <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">
            <h1>Mero Afno Premium SEO Dashboard</h1>
            <p>Welcome to your site's SEO control center.</p>

            <div class="eseo-dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; margin-top:20px;">
                
                <div class="eseo-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px;">
                    <h2 style="margin-top:0; border-bottom:1px solid #f0f0f1; padding-bottom:10px;">Traffic Saved</h2>
                    <div class="eseo-stat" style="font-size:32px; font-weight:bold; color:#0a4b78; margin:10px 0;">
                        <?php echo number_format_i18n( (int) $total_redirects ); ?>
                    </div>
                    <p class="description">404 errors successfully intercepted and redirected.</p>
                </div>

                <div class="eseo-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px;">
                    <h2 style="margin-top:0; border-bottom:1px solid #f0f0f1; padding-bottom:10px;">Content Links Audited</h2>
                    <div class="eseo-stat" style="font-size:32px; font-weight:bold; color:#00a32a; margin:10px 0;">
                        <?php echo number_format_i18n( (int) $total_links ); ?>
                    </div>
                    <p class="description">Internal and external links being monitored.</p>
                </div>

                <div class="eseo-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px;">
                    <h2 style="margin-top:0; border-bottom:1px solid #f0f0f1; padding-bottom:10px;">System Status</h2>
                    <ul style="list-style-type:none; padding-left:0; margin-bottom:0;">
                        <li style="margin-bottom:8px;">✅ Sitemap Generator (Cached)</li>
                        <li style="margin-bottom:8px;">✅ JSON-LD Article Schema</li>
                        <li style="margin-bottom:8px;">✅ Background Content Audit</li>
                        <li style="margin-bottom:0;">✅ Performance Optimization</li>
                    </ul>
                </div>

            </div>
        </div>
        <?php
    }

    public function render_ai_settings() {
        $disabled_modules = get_option( 'eseo_modules_disabled', [] );
        if ( ! is_array( $disabled_modules ) ) {
            $disabled_modules = [];
        }
        ?>
        <div class="wrap">
            <h1>Mero Afno Premium SEO Settings</h1>
            <p>Configure your API keys and toggle plugin features.</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'eseo_ai_options' ); ?>
                <?php do_settings_sections( 'eseo_ai_options' ); ?>
                
                <h2>API Keys</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Preferred AI Engine</th>
                        <td>
                            <?php $preferred = get_option('eseo_preferred_ai_engine', 'smart'); ?>
                            <select name="eseo_preferred_ai_engine">
                                <option value="smart" <?php selected($preferred, 'smart'); ?>>Smart Routing (Recommended)</option>
                                <option value="openai" <?php selected($preferred, 'openai'); ?>>OpenAI Only</option>
                                <option value="gemini" <?php selected($preferred, 'gemini'); ?>>Gemini Only</option>
                            </select>
                            <p class="description">Select which AI engine to use for generating content. Smart routing uses Gemini for keywords and OpenAI for descriptions.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="password" name="eseo_openai_key" value="<?php echo esc_attr( get_option('eseo_openai_key') ); ?>" class="regular-text" />
                            <p class="description">Required if OpenAI is selected.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">OpenAI Model</th>
                        <td>
                            <?php $o_model = get_option('eseo_openai_model', 'gpt-3.5-turbo'); ?>
                            <select name="eseo_openai_model">
                                <option value="gpt-3.5-turbo" <?php selected($o_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Fast & Cheap)</option>
                                <option value="gpt-4o" <?php selected($o_model, 'gpt-4o'); ?>>GPT-4o (Most Powerful)</option>
                                <option value="gpt-4-turbo" <?php selected($o_model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Gemini API Key</th>
                        <td>
                            <input type="password" name="eseo_gemini_key" value="<?php echo esc_attr( get_option('eseo_gemini_key') ); ?>" class="regular-text" />
                            <p class="description">Required if Gemini is selected.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Gemini Model</th>
                        <td>
                            <?php $g_model = get_option('eseo_gemini_model', 'gemini-3.5-flash'); ?>
                            <select name="eseo_gemini_model">
                                <option value="gemini-3.5-flash" <?php selected($g_model, 'gemini-3.5-flash'); ?>>Gemini 3.5 Flash (Latest & Fast)</option>
                                <option value="gemini-3.1-pro" <?php selected($g_model, 'gemini-3.1-pro'); ?>>Gemini 3.1 Pro (Latest & Powerful)</option>
                                <option value="gemini-2.0-flash" <?php selected($g_model, 'gemini-2.0-flash'); ?>>Gemini 2.0 Flash</option>
                                <option value="gemini-2.0-pro" <?php selected($g_model, 'gemini-2.0-pro'); ?>>Gemini 2.0 Pro</option>
                                <option value="gemini-1.5-flash" <?php selected($g_model, 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash</option>
                                <option value="gemini-1.5-pro" <?php selected($g_model, 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2>Module Toggles</h2>
                <p>Check the box to <strong>disable</strong> a module you do not need.</p>
                <table class="form-table">
                    <?php 
                    $modules = [
                        'sitemap' => 'XML Sitemaps',
                        'schema' => 'JSON-LD Article Schema',
                        'redirects' => '404 Redirect Manager',
                        'content_audit' => 'Link Content Audit',
                        'social_seo' => 'Social SEO (OpenGraph)',
                        'local_seo' => 'Local SEO',
                        'woo_seo' => 'WooCommerce SEO',
                        'performance' => 'Performance Optimizer',
                        'indexing' => 'Indexing API',
                        'image_seo' => 'Image SEO Automation'
                    ];
                    
                    foreach ( $modules as $key => $label ) {
                        $checked = in_array( $key, $disabled_modules ) ? 'checked="checked"' : '';
                        echo '<tr valign="top">';
                        echo '<th scope="row">' . esc_html( $label ) . '</th>';
                        echo '<td><label><input type="checkbox" name="eseo_modules_disabled[]" value="' . esc_attr( $key ) . '" ' . $checked . ' /> Disable</label></td>';
                        echo '</tr>';
                    }
                    ?>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_indexing_settings() {
        ?>
        <div class="wrap">
            <h1>Indexing API Settings</h1>
            <p>Configure automatic fast indexing for Google and Bing. When you publish or update a post, this plugin will instantly ping search engines.</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'eseo_indexing_options' ); ?>
                <?php do_settings_sections( 'eseo_indexing_options' ); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Google Indexing API (JSON Key)</th>
                        <td>
                            <textarea name="eseo_google_indexing_key" rows="8" class="large-text code"><?php echo esc_textarea( get_option('eseo_google_indexing_key') ); ?></textarea>
                            <p class="description">Paste the entire contents of your Google Cloud Service Account JSON file here.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Bing Webmaster API Key</th>
                        <td>
                            <input type="password" name="eseo_bing_api_key" value="<?php echo esc_attr( get_option('eseo_bing_api_key') ); ?>" class="regular-text" />
                            <p class="description">Get this from your Bing Webmaster Tools -> Settings -> API Access.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Status</th>
                        <td>
                            <?php 
                            $last_google = get_option('eseo_last_google_ping');
                            $last_bing = get_option('eseo_last_bing_ping');
                            ?>
                            <p><strong>Last Google Ping:</strong> <?php echo $last_google ? date('Y-m-d H:i:s', $last_google) : 'Never'; ?></p>
                            <p><strong>Last Bing Ping:</strong> <?php echo $last_bing ? date('Y-m-d H:i:s', $last_bing) : 'Never'; ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
