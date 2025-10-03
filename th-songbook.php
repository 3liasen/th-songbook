<?php
/**
 * Plugin Name: TH Songbook
 * Plugin URI: https://example.com/plugins/th-songbook
 * Description: Provides song management tools tailored for TH songbook workflows.
 * Version: 0.1.0
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

if ( ! class_exists( 'TH_Songbook' ) ) {
    /**
     * Main plugin class for bootstrapping the TH Songbook functionality.
     */
    final class TH_Songbook {
        /**
         * The single instance of the class.
         *
         * @var TH_Songbook|null
         */
        private static $instance = null;

        /**
         * Retrieve the singleton instance.
         *
         * @return TH_Songbook
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Set up hooks.
         */
        private function __construct() {
            $this->define_constants();
            add_action( 'init', array( $this, 'register_song_post_type' ) );
        }

        /**
         * Prevent cloning.
         */
        private function __clone() {}

        /**
         * Prevent unserializing.
         */
        public function __wakeup() {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentionally blank to prevent unserializing.
            throw new 
                Exception( 'Cannot unserialize singleton' );
        }

        /**
         * Define plugin constants for reuse.
         */
        private function define_constants() {
            if ( ! defined( 'TH_SONGBOOK_VERSION' ) ) {
                define( 'TH_SONGBOOK_VERSION', '0.1.0' );
            }

            if ( ! defined( 'TH_SONGBOOK_PLUGIN_FILE' ) ) {
                define( 'TH_SONGBOOK_PLUGIN_FILE', __FILE__ );
            }

            if ( ! defined( 'TH_SONGBOOK_PLUGIN_DIR' ) ) {
                define( 'TH_SONGBOOK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
            }
        }

        /**
         * Register the custom post type that stores songs.
         */
        public function register_song_post_type() {
            $labels = array(
                'name'               => _x( 'Songs', 'post type general name', 'th-songbook' ),
                'singular_name'      => _x( 'Song', 'post type singular name', 'th-songbook' ),
                'menu_name'          => _x( 'Songbook', 'admin menu text', 'th-songbook' ),
                'name_admin_bar'     => _x( 'Song', 'add new on admin bar', 'th-songbook' ),
                'add_new'            => __( 'Add New', 'th-songbook' ),
                'add_new_item'       => __( 'Add New Song', 'th-songbook' ),
                'new_item'           => __( 'New Song', 'th-songbook' ),
                'edit_item'          => __( 'Edit Song', 'th-songbook' ),
                'view_item'          => __( 'View Song', 'th-songbook' ),
                'all_items'          => __( 'All Songs', 'th-songbook' ),
                'search_items'       => __( 'Search Songs', 'th-songbook' ),
                'parent_item_colon'  => __( 'Parent Songs:', 'th-songbook' ),
                'not_found'          => __( 'No songs found.', 'th-songbook' ),
                'not_found_in_trash' => __( 'No songs found in Trash.', 'th-songbook' ),
            );

            $args = array(
                'labels'             => $labels,
                'public'             => true,
                'has_archive'        => true,
                'show_in_menu'       => true,
                'menu_position'      => 20,
                'menu_icon'          => 'dashicons-playlist-audio',
                'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
                'rewrite'            => array( 'slug' => 'songbook' ),
                'show_in_rest'       => true,
            );

            register_post_type( 'th_song', $args );
        }
    }

    /**
     * Boot the plugin.
     */
    function th_songbook() {
        return TH_Songbook::get_instance();
    }

    // Ensure the plugin is loaded.
    th_songbook();
}

