<div class="wrap eseo-wrap">
    <h1>Global Site SEO Audit</h1>
    <p>We have automatically analyzed your WordPress installation's core settings to ensure your site is built on a solid SEO foundation.</p>

    <div class="eseo-dashboard-grid" style="display: block;">
        <?php
        $checks = [];

        // 1. Search Engine Visibility
        $blog_public = get_option('blog_public');
        $checks[] = [
            'name' => 'Search Engine Visibility',
            'pass' => $blog_public == '1',
            'success_msg' => 'Search engines are allowed to index this site.',
            'fail_msg' => 'CRITICAL: "Discourage search engines from indexing this site" is checked! Google will NOT index your site.',
            'fix_msg' => 'Go to <strong>Settings -> Reading</strong> and uncheck "Discourage search engines from indexing this site".'
        ];

        // 2. Permalink Structure
        $permalink = get_option('permalink_structure');
        $checks[] = [
            'name' => 'Permalink Structure',
            'pass' => ! empty( $permalink ),
            'success_msg' => 'You are using custom, SEO-friendly permalinks.',
            'fail_msg' => 'You are using the default "Plain" permalink structure (e.g. ?p=123). This is bad for SEO.',
            'fix_msg' => 'Go to <strong>Settings -> Permalinks</strong> and select "Post name".'
        ];

        // 3. Site Tagline
        $tagline = get_option('blogdescription');
        $checks[] = [
            'name' => 'Site Tagline',
            'pass' => ( $tagline !== 'Just another WordPress site' && ! empty( trim( $tagline ) ) ),
            'success_msg' => 'Your site tagline is customized.',
            'fail_msg' => empty( trim( $tagline ) ) ? 'Your site tagline is empty.' : 'Your site is still using the default "Just another WordPress site" tagline.',
            'fix_msg' => 'Go to <strong>Settings -> General</strong> and write a descriptive, keyword-rich Tagline.'
        ];

        // 4. SSL / HTTPS
        $siteurl = get_option('siteurl');
        $is_https = ( is_ssl() || strpos( $siteurl, 'https://' ) === 0 );
        $checks[] = [
            'name' => 'SSL / HTTPS Security',
            'pass' => $is_https,
            'success_msg' => 'Your site is securely served over HTTPS. Google prefers secure sites.',
            'fail_msg' => 'Your site URL uses HTTP. Google penalizes non-secure websites.',
            'fix_msg' => 'Go to <strong>Settings -> General</strong> and ensure your WordPress Address and Site Address start with https://. You may need to install an SSL certificate on your server.'
        ];

        // 5. XML Sitemap
        $sitemap_enabled = get_option('eseo_sitemap_enabled', '1');
        $checks[] = [
            'name' => 'XML Sitemap generation',
            'pass' => $sitemap_enabled == '1',
            'success_msg' => 'Mero Afno Premium SEO is actively generating an XML Sitemap.',
            'fail_msg' => 'XML Sitemaps are currently disabled in your SEO settings.',
            'fix_msg' => 'Go to the <strong>Enterprise SEO Dashboard</strong> and enable XML Sitemaps.'
        ];

        // 6. Homepage Display
        $show_on_front = get_option('show_on_front');
        $checks[] = [
            'name' => 'Homepage Configuration',
            'pass' => $show_on_front === 'page',
            'success_msg' => 'Your homepage is set to a Static Page, allowing for highly targeted SEO optimization.',
            'fail_msg' => 'Your homepage displays your latest posts. While fine for pure blogs, a Static Page usually ranks better for business keywords.',
            'fix_msg' => 'Consider creating a dedicated Homepage and setting it in <strong>Settings -> Reading -> Your homepage displays</strong>.'
        ];
        
        ?>

        <style>
            .eseo-audit-list { margin-top: 20px; }
            .eseo-audit-item { 
                background: #fff; 
                border: 1px solid #c3c4c7; 
                border-left: 4px solid #00a32a;
                border-radius: 4px; 
                padding: 15px 20px; 
                margin-bottom: 15px;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
            }
            .eseo-audit-item.failed {
                border-left-color: #d63638;
            }
            .eseo-audit-item.warning {
                border-left-color: #dba617;
            }
            .eseo-audit-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 8px;
            }
            .eseo-audit-header h3 {
                margin: 0;
                font-size: 16px;
            }
            .eseo-audit-status {
                font-size: 18px;
            }
            .eseo-audit-desc {
                margin: 0;
                color: #50575e;
                font-size: 14px;
            }
            .eseo-audit-fix {
                margin-top: 10px;
                padding: 10px;
                background: #f8f9fa;
                border: 1px solid #e2e4e7;
                border-radius: 4px;
                font-size: 13px;
            }
        </style>

        <div class="eseo-audit-list">
            <?php foreach ( $checks as $check ) : ?>
                <?php 
                $status_class = $check['pass'] ? 'passed' : 'failed';
                $status_icon = $check['pass'] ? '🟢' : '🔴';
                
                // Downgrade Homepage to a warning rather than a failure
                if ( $check['name'] === 'Homepage Configuration' && ! $check['pass'] ) {
                    $status_class = 'warning';
                    $status_icon = '🟡';
                }
                ?>
                <div class="eseo-audit-item <?php echo esc_attr( $status_class ); ?>">
                    <div class="eseo-audit-header">
                        <span class="eseo-audit-status"><?php echo $status_icon; ?></span>
                        <h3><?php echo esc_html( $check['name'] ); ?></h3>
                    </div>
                    <p class="eseo-audit-desc">
                        <?php echo $check['pass'] ? esc_html( $check['success_msg'] ) : esc_html( $check['fail_msg'] ); ?>
                    </p>
                    <?php if ( ! $check['pass'] ) : ?>
                        <div class="eseo-audit-fix">
                            <strong>How to fix:</strong> <?php echo wp_kses_post( $check['fix_msg'] ); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
