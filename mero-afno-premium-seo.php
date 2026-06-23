<?php
/**
 * Plugin Name:       Mero Afno Premium SEO
 * Plugin URI:        https://www.rameskhadka.com.np
 * Description:       An enterprise-level, highly optimized SEO plugin with integrated AI, advanced Schema, XML Sitemaps, and Content Auditing.
 * Version:           1.1.12
 * Author:            Ramesh Khadka
 * Author URI:        https://www.rameskhadka.com.np
 * Text Domain:       mero-afno-premium-seo
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ESEO_VERSION', '1.1.12' );
define( 'ESEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ESEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader for plugin classes
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'ESEO\\';
    $base_dir = ESEO_PLUGIN_DIR . 'includes/';
    $module_dir = ESEO_PLUGIN_DIR . 'modules/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );

    // Determine if it's a core class or a module
    if ( strpos( $relative_class, 'Modules\\' ) === 0 ) {
        $relative_class = substr( $relative_class, 8 ); // remove 'Modules\'
        $file = $module_dir . str_replace( '\\', '/', $relative_class ) . '.php';
    } else {
        $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
    }

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

function activate_enterprise_seo() {
    require_once ESEO_PLUGIN_DIR . 'includes/Core/Activator.php';
    ESEO\Core\Activator::activate();
}
register_activation_hook( __FILE__, 'activate_enterprise_seo' );

/**
 * Initialize the GitHub Updater
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/updater/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/rameshkhadka61/mero-afno-premium-seo/',
	__FILE__,
	'mero-afno-premium-seo'
);
// Optional: If you use a specific branch for releases
$myUpdateChecker->setBranch('main');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
function run_enterprise_seo() {
	$plugin = new ESEO\Core\Plugin();
	$plugin->run();
}

run_enterprise_seo();
