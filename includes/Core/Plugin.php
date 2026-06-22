<?php

namespace ESEO\Core;

use ESEO\Modules\TitlesMeta\TitlesMeta;

class Plugin {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->plugin_name = 'enterprise-seo';
		$this->version = ESEO_VERSION;

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
        $this->load_modules();
	}

	private function load_dependencies() {
		$this->loader = new Loader();
	}

	private function define_admin_hooks() {
        $admin_menu = new \ESEO\Admin\Menu();
        $admin_menu->init();
	}

	private function define_public_hooks() {
        // Public hooks will go here
	}

    private function load_modules() {
        $disabled_modules = get_option( 'eseo_modules_disabled', [] );
        if ( ! is_array( $disabled_modules ) ) {
            $disabled_modules = [];
        }

        // Always load core meta
        $titles_meta = new TitlesMeta();
        $titles_meta->init();

        $ai_seo = new \ESEO\Modules\AiSEO\AiSEO();
        $ai_seo->init();

        $breadcrumbs = new \ESEO\Modules\Breadcrumbs\Breadcrumbs();
        $breadcrumbs->init();

        $robots = new \ESEO\Modules\Robots\Robots();
        $robots->init();

        if ( ! in_array( 'sitemap', $disabled_modules ) ) {
            $sitemap = new \ESEO\Modules\Sitemap\Sitemap();
            $sitemap->init();
        }

        if ( ! in_array( 'schema', $disabled_modules ) ) {
            $schema = new \ESEO\Modules\Schema\Schema();
            $schema->init();
        }

        if ( ! in_array( 'redirects', $disabled_modules ) ) {
            $redirects = new \ESEO\Modules\Redirects\Redirects();
            $redirects->init();
        }

        if ( ! in_array( 'content_audit', $disabled_modules ) ) {
            $content_audit = new \ESEO\Modules\ContentAudit\ContentAudit();
            $content_audit->init();
        }

        if ( ! in_array( 'social_seo', $disabled_modules ) ) {
            $social_seo = new \ESEO\Modules\SocialSEO\SocialSEO();
            $social_seo->init();
        }

        if ( ! in_array( 'local_seo', $disabled_modules ) ) {
            $local_seo = new \ESEO\Modules\LocalSEO\LocalSEO();
            $local_seo->init();
        }

        if ( ! in_array( 'woo_seo', $disabled_modules ) ) {
            $woo_seo = new \ESEO\Modules\WooCommerceSEO\WooCommerceSEO();
            $woo_seo->init();
        }

        // Always load Adsense audit for the dashboard
        $adsense = new \ESEO\Modules\AdsenseAudit\AdsenseAudit();
        $adsense->init();

        if ( ! in_array( 'performance', $disabled_modules ) ) {
            $performance = new \ESEO\Modules\Performance\Performance();
            $performance->init();
        }

        if ( ! in_array( 'indexing', $disabled_modules ) ) {
            $indexing = new \ESEO\Modules\Indexing\Indexing();
            $indexing->init();
            add_action( 'eseo_ping_search_engines', [ '\ESEO\Modules\Indexing\Indexing', 'execute_ping' ], 10, 2 );
        }

        if ( ! in_array( 'image_seo', $disabled_modules ) ) {
            $image_seo = new \ESEO\Modules\ImageSEO\ImageSEO();
            $image_seo->init();
        }

        // Always load tools for admin
        if ( is_admin() ) {
            $migration = new \ESEO\Modules\Tools\Migration();
            $migration->init();

            $analytics = new \ESEO\Modules\Analytics\Analytics();
            $analytics->init();
        }
    }

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}
}
