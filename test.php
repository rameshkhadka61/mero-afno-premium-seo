<?php

define( 'ESEO_VERSION', '1.0.0' );
define( 'ESEO_PLUGIN_DIR', __DIR__ . '/' );
define( 'ESEO_PLUGIN_URL', 'http://example.com/' );

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

    echo "Trying to load $class from $file\n";

    if ( file_exists( $file ) ) {
        echo "File exists! Loading...\n";
        require $file;
    } else {
        echo "File DOES NOT exist: $file\n";
    }
} );

$plugin = new ESEO\Core\Plugin();
