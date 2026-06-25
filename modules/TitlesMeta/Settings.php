<?php

namespace ESEO\Modules\TitlesMeta;

class Settings {

    public function init() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'eseo_titles_meta_options', 'eseo_titles_meta_settings' );
    }

    public function render_settings_page() {
        $settings = get_option( 'eseo_titles_meta_settings', [] );

        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );

        ?>
        <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">
            <h1>Search Appearance & Title Templates</h1>
            <p>Define global SEO templates for all your content types.</p>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'eseo_titles_meta_options' ); ?>
                
                <h2 class="nav-tab-wrapper">
                    <a href="#tab-homepage" class="nav-tab nav-tab-active">Homepage</a>
                    <a href="#tab-post-types" class="nav-tab">Content Types</a>
                    <a href="#tab-taxonomies" class="nav-tab">Taxonomies</a>
                    <a href="#tab-archives" class="nav-tab">Archives</a>
                </h2>

                <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; margin-top:10px;">
                    <p class="description" style="margin-top:0;"><strong>Available Variables:</strong> %title%, %sitename%, %sitedesc%, %date%, %currentyear%, %currentmonth%, %category%, %excerpt%</p>
                
                    <!-- Homepage Tab -->
                    <div id="tab-homepage" class="tab-content" style="display:block;">
                        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                            <h3>Homepage SEO</h3>
                            <p>Customize the SEO meta title and description specifically for your site's homepage to avoid length issues with the default Site Title and Tagline.</p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label>Homepage SEO Title</label></th>
                                    <td><input type="text" name="eseo_titles_meta_settings[homepage_title]" value="<?php echo esc_attr(isset($settings['homepage_title']) ? $settings['homepage_title'] : '%sitename% - %sitedesc%'); ?>" class="regular-text" style="width:100%;"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label>Homepage Meta Description</label></th>
                                    <td><textarea name="eseo_titles_meta_settings[homepage_desc]" rows="2" style="width:100%;"><?php echo esc_textarea(isset($settings['homepage_desc']) ? $settings['homepage_desc'] : '%sitedesc%'); ?></textarea></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Post Types Tab -->
                    <div id="tab-post-types" class="tab-content" style="display:none;">
                        <?php foreach ( $post_types as $pt ) : 
                            if ( $pt->name === 'attachment' ) continue;
                            
                            $prefix = 'pt_' . $pt->name;
                            $title = isset($settings[$prefix.'_title']) ? $settings[$prefix.'_title'] : '%title% - %sitename%';
                            $desc = isset($settings[$prefix.'_desc']) ? $settings[$prefix.'_desc'] : '%excerpt%';
                            $robots = isset($settings[$prefix.'_robots']) ? $settings[$prefix.'_robots'] : 'index';
                        ?>
                            <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                                <h3><?php echo esc_html( $pt->labels->name ); ?></h3>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label>SEO Title</label></th>
                                        <td><input type="text" name="eseo_titles_meta_settings[<?php echo esc_attr($prefix); ?>_title]" value="<?php echo esc_attr($title); ?>" class="regular-text" style="width:100%;"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label>Meta Description</label></th>
                                        <td><textarea name="eseo_titles_meta_settings[<?php echo esc_attr($prefix); ?>_desc]" rows="2" style="width:100%;"><?php echo esc_textarea($desc); ?></textarea></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label>Robots Meta</label></th>
                                        <td>
                                            <select name="eseo_titles_meta_settings[<?php echo esc_attr($prefix); ?>_robots]">
                                                <option value="index" <?php selected($robots, 'index'); ?>>Index (Let search engines index)</option>
                                                <option value="noindex" <?php selected($robots, 'noindex'); ?>>NoIndex (Hide from search engines)</option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Taxonomies Tab -->
                    <div id="tab-taxonomies" class="tab-content" style="display:none;">
                        <?php foreach ( $taxonomies as $tax ) : 
                            if ( $tax->name === 'post_format' ) continue;

                            $prefix = 'tax_' . $tax->name;
                            $title = isset($settings[$prefix.'_title']) ? $settings[$prefix.'_title'] : '%title% Archives - %sitename%';
                            $desc = isset($settings[$prefix.'_desc']) ? $settings[$prefix.'_desc'] : '%excerpt%';
                            $robots = isset($settings[$prefix.'_robots']) ? $settings[$prefix.'_robots'] : 'index';
                        ?>
                            <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                                <h3><?php echo esc_html( $tax->labels->name ); ?></h3>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label>SEO Title</label></th>
                                        <td><input type="text" name="eseo_titles_meta_settings[<?php echo esc_attr($prefix); ?>_title]" value="<?php echo esc_attr($title); ?>" class="regular-text" style="width:100%;"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label>Meta Description</label></th>
                                        <td><textarea name="eseo_titles_meta_settings[<?php echo esc_attr($prefix); ?>_desc]" rows="2" style="width:100%;"><?php echo esc_textarea($desc); ?></textarea></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label>Robots Meta</label></th>
                                        <td>
                                            <select name="eseo_titles_meta_settings[<?php echo esc_attr($prefix); ?>_robots]">
                                                <option value="index" <?php selected($robots, 'index'); ?>>Index</option>
                                                <option value="noindex" <?php selected($robots, 'noindex'); ?>>NoIndex</option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Archives Tab -->
                    <div id="tab-archives" class="tab-content" style="display:none;">
                        <?php 
                            $prefix = 'arch_author';
                            $title = isset($settings[$prefix.'_title']) ? $settings[$prefix.'_title'] : '%title%, Author at %sitename%';
                            $desc = isset($settings[$prefix.'_desc']) ? $settings[$prefix.'_desc'] : '';
                            $robots = isset($settings[$prefix.'_robots']) ? $settings[$prefix.'_robots'] : 'noindex';
                        ?>
                        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                            <h3>Author Archives</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label>SEO Title</label></th>
                                    <td><input type="text" name="eseo_titles_meta_settings[<?php echo esc_attr($prefix); ?>_title]" value="<?php echo esc_attr($title); ?>" class="regular-text" style="width:100%;"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label>Meta Description</label></th>
                                    <td><textarea name="eseo_titles_meta_settings[<?php echo esc_attr($prefix); ?>_desc]" rows="2" style="width:100%;"><?php echo esc_textarea($desc); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label>Robots Meta</label></th>
                                    <td>
                                        <select name="eseo_titles_meta_settings[<?php echo esc_attr($prefix); ?>_robots]">
                                            <option value="index" <?php selected($robots, 'index'); ?>>Index</option>
                                            <option value="noindex" <?php selected($robots, 'noindex'); ?>>NoIndex</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <?php submit_button( 'Save Changes' ); ?>
            </form>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tabs = document.querySelectorAll('.nav-tab');
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    tabs.forEach(function(t) { t.classList.remove('nav-tab-active'); });
                    tab.classList.add('nav-tab-active');
                    
                    document.querySelectorAll('.tab-content').forEach(function(content) {
                        content.style.display = 'none';
                    });
                    var target = tab.getAttribute('href');
                    document.querySelector(target).style.display = 'block';
                });
            });
        });
        </script>
        <?php
    }
}
