<?php
/**
 * Plugin Name: TH Songbook
 * Plugin URI: https://example.com/plugins/th-songbook
 * Description: Provides song management tools tailored for TH songbook workflows.
 * Version: 1.7.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Jan Eliasen
 * Author URI: https://example.com/
 * Text Domain: th-songbook
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'TH_SONGBOOK_VERSION' ) ) {
    define( 'TH_SONGBOOK_VERSION', '1.6.0' );
}

if ( ! defined( 'TH_SONGBOOK_PLUGIN_FILE' ) ) {
    define( 'TH_SONGBOOK_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'TH_SONGBOOK_PLUGIN_DIR' ) ) {
    define( 'TH_SONGBOOK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'TH_SONGBOOK_PLUGIN_URL' ) ) {
    define( 'TH_SONGBOOK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once TH_SONGBOOK_PLUGIN_DIR . 'includes/class-th-songbook-utils.php';
require_once TH_SONGBOOK_PLUGIN_DIR . 'includes/class-th-songbook-post-types.php';
require_once TH_SONGBOOK_PLUGIN_DIR . 'includes/class-th-songbook-admin.php';
require_once TH_SONGBOOK_PLUGIN_DIR . 'includes/class-th-songbook-frontend.php';
require_once TH_SONGBOOK_PLUGIN_DIR . 'includes/class-th-songbook.php';

if ( ! function_exists( 'th_songbook' ) ) {
    /**
     * Helper to access the plugin instance.
     *
     * @return TH_Songbook
     */
    function th_songbook() {
        return TH_Songbook::get_instance();
    }
}

th_songbook();
