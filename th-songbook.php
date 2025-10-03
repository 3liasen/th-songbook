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
            add_action( 'init', array( $this, 'register_gig_post_type' ) );
            add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
            add_action( 'add_meta_boxes', array( $this, 'register_song_meta_boxes' ) );
            add_action( 'add_meta_boxes', array( $this, 'register_gig_meta_boxes' ) );
            add_action( 'save_post_th_song', array( $this, 'save_song_meta' ), 10, 2 );
            add_action( 'save_post_th_gig', array( $this, 'save_gig_meta' ), 10, 2 );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
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
            throw new Exception( 'Cannot unserialize singleton' );
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

            if ( ! defined( 'TH_SONGBOOK_PLUGIN_URL' ) ) {
                define( 'TH_SONGBOOK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
            }
        }

        /**
         * Register the custom post type that stores songs.
         */
        public function register_song_post_type() {
            $labels = array(
                'name'               => _x( 'Songs', 'post type general name', 'th-songbook' ),
                'singular_name'      => _x( 'Song', 'post type singular name', 'th-songbook' ),
                'menu_name'          => _x( 'Songs', 'admin menu text', 'th-songbook' ),
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
                'show_in_menu'       => 'th-songbook',
                'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
                'rewrite'            => array( 'slug' => 'songbook' ),
                'show_in_rest'       => true,
            );

            register_post_type( 'th_song', $args );
        }

        /**
         * Register the custom post type that stores gigs.
         */
        public function register_gig_post_type() {
            $labels = array(
                'name'               => _x( 'Gigs', 'post type general name', 'th-songbook' ),
                'singular_name'      => _x( 'Gig', 'post type singular name', 'th-songbook' ),
                'menu_name'          => _x( 'Gigs', 'admin menu text', 'th-songbook' ),
                'name_admin_bar'     => _x( 'Gig', 'add new on admin bar', 'th-songbook' ),
                'add_new'            => __( 'Add New', 'th-songbook' ),
                'add_new_item'       => __( 'Add New Gig', 'th-songbook' ),
                'new_item'           => __( 'New Gig', 'th-songbook' ),
                'edit_item'          => __( 'Edit Gig', 'th-songbook' ),
                'view_item'          => __( 'View Gig', 'th-songbook' ),
                'all_items'          => __( 'All Gigs', 'th-songbook' ),
                'search_items'       => __( 'Search Gigs', 'th-songbook' ),
                'not_found'          => __( 'No gigs found.', 'th-songbook' ),
                'not_found_in_trash' => __( 'No gigs found in Trash.', 'th-songbook' ),
            );

            $args = array(
                'labels'             => $labels,
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => 'th-songbook',
                'supports'           => array( 'title' ),
                'has_archive'        => false,
                'rewrite'            => false,
                'show_in_rest'       => false,
                'capability_type'    => 'post',
                'map_meta_cap'       => true,
            );

            register_post_type( 'th_gig', $args );
        }

        /**
         * Register the admin menu structure for the plugin.
         */
        public function register_admin_menu() {
            $capability = 'edit_posts';

            add_menu_page(
                __( 'TH Songbook', 'th-songbook' ),
                __( 'TH Songbook', 'th-songbook' ),
                $capability,
                'th-songbook',
                array( $this, 'render_admin_dashboard' ),
                'dashicons-playlist-audio',
                20
            );
        }

        /**
         * Render the overview page shown when visiting the Songbook top-level menu.
         */
        public function render_admin_dashboard() {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'TH Songbook', 'th-songbook' ) . '</h1>';
            echo '<p>' . esc_html__( 'Use the Gigs submenu to schedule performances and the Songs submenu to manage your repertoire.', 'th-songbook' ) . '</p>';
            echo '</div>';
        }

        /**
         * Register song meta boxes.
         */
        public function register_song_meta_boxes() {
            add_meta_box(
                'th-songbook-song-details',
                __( 'Song Details', 'th-songbook' ),
                array( $this, 'render_song_details_metabox' ),
                'th_song',
                'normal',
                'high'
            );
        }

        /**
         * Render the song details meta box.
         *
         * @param WP_Post $post Current song post.
         */
        public function render_song_details_metabox( $post ) {
            $composer = get_post_meta( $post->ID, 'th_song_composer', true );
            $lyrics   = get_post_meta( $post->ID, 'th_song_lyrics', true );
            $key      = get_post_meta( $post->ID, 'th_song_key', true );
            $keys     = $this->get_song_keys();

            wp_nonce_field( 'th_songbook_save_song', 'th_songbook_song_meta_nonce' );
            ?>
            <div class="th-songbook-meta">
                <div class="th-songbook-field">
                    <label for="th_song_composer"><?php esc_html_e( 'Composer', 'th-songbook' ); ?></label>
                    <input type="text" class="regular-text" id="th_song_composer" name="th_song_composer" value="<?php echo esc_attr( $composer ); ?>" />
                </div>

                <div class="th-songbook-field">
                    <label for="th_song_lyrics"><?php esc_html_e( 'Lyrics', 'th-songbook' ); ?></label>
                    <input type="text" class="regular-text" id="th_song_lyrics" name="th_song_lyrics" value="<?php echo esc_attr( $lyrics ); ?>" />
                </div>

                <div class="th-songbook-field">
                    <label for="th_song_key"><?php esc_html_e( 'Key', 'th-songbook' ); ?></label>
                    <select id="th_song_key" name="th_song_key" class="th-songbook-select">
                        <option value=""><?php esc_html_e( 'Select a key', 'th-songbook' ); ?></option>
                        <?php foreach ( $keys as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $key, $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php
        }

        /**
         * Persist song metadata when a song is saved.
         *
         * @param int     $post_id The post ID.
         * @param WP_Post $post    The post object.
         */
        public function save_song_meta( $post_id, $post ) {
            if ( ! isset( $_POST['th_songbook_song_meta_nonce'] ) || ! wp_verify_nonce( $_POST['th_songbook_song_meta_nonce'], 'th_songbook_save_song' ) ) {
                return;
            }

            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return;
            }

            if ( 'th_song' !== $post->post_type ) {
                return;
            }

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            $composer = isset( $_POST['th_song_composer'] ) ? sanitize_text_field( wp_unslash( $_POST['th_song_composer'] ) ) : '';
            $lyrics   = isset( $_POST['th_song_lyrics'] ) ? sanitize_text_field( wp_unslash( $_POST['th_song_lyrics'] ) ) : '';
            $key      = isset( $_POST['th_song_key'] ) ? $this->sanitize_song_key( wp_unslash( $_POST['th_song_key'] ) ) : '';

            $this->update_meta_value( $post_id, 'th_song_composer', $composer );
            $this->update_meta_value( $post_id, 'th_song_lyrics', $lyrics );
            $this->update_meta_value( $post_id, 'th_song_key', $key );
        }

        /**
         * Register gig meta boxes.
         */
        public function register_gig_meta_boxes() {
            add_meta_box(
                'th-songbook-gig-details',
                __( 'Gig Details', 'th-songbook' ),
                array( $this, 'render_gig_details_metabox' ),
                'th_gig',
                'normal',
                'high'
            );
        }

        /**
         * Render the gig details meta box.
         *
         * @param WP_Post $post Current gig post.
         */
        public function render_gig_details_metabox( $post ) {
            $venue      = get_post_meta( $post->ID, 'th_gig_venue', true );
            $date       = get_post_meta( $post->ID, 'th_gig_date', true );
            $start_time = get_post_meta( $post->ID, 'th_gig_start_time', true );
            $get_in     = get_post_meta( $post->ID, 'th_gig_get_in_time', true );
            $address    = get_post_meta( $post->ID, 'th_gig_address', true );
            $subject    = get_post_meta( $post->ID, 'th_gig_subject', true );

            wp_nonce_field( 'th_songbook_save_gig', 'th_songbook_gig_meta_nonce' );
            ?>
            <div class="th-songbook-meta">
                <div class="th-songbook-field">
                    <label for="th_gig_venue"><?php esc_html_e( 'Venue', 'th-songbook' ); ?></label>
                    <input type="text" class="regular-text" id="th_gig_venue" name="th_gig_venue" value="<?php echo esc_attr( $venue ); ?>" />
                </div>

                <div class="th-songbook-inline">
                    <div class="th-songbook-field">
                        <label for="th_gig_date"><?php esc_html_e( 'Date', 'th-songbook' ); ?></label>
                        <input type="date" id="th_gig_date" name="th_gig_date" value="<?php echo esc_attr( $date ); ?>" />
                    </div>

                    <div class="th-songbook-field">
                        <label for="th_gig_start_time"><?php esc_html_e( 'Start Time (hh:mm)', 'th-songbook' ); ?></label>
                        <input type="time" class="th-songbook-time-field" id="th_gig_start_time" name="th_gig_start_time" value="<?php echo esc_attr( $start_time ); ?>" placeholder="hh:mm" pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" inputmode="numeric" />
                    </div>

                    <div class="th-songbook-field">
                        <label for="th_gig_get_in_time"><?php esc_html_e( 'Get-in Time (hh:mm)', 'th-songbook' ); ?></label>
                        <input type="time" class="th-songbook-time-field" id="th_gig_get_in_time" name="th_gig_get_in_time" value="<?php echo esc_attr( $get_in ); ?>" placeholder="hh:mm" pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" inputmode="numeric" />
                    </div>
                </div>

                <div class="th-songbook-field">
                    <label for="th_gig_address"><?php esc_html_e( 'Address', 'th-songbook' ); ?></label>
                    <input type="text" class="regular-text" id="th_gig_address" name="th_gig_address" value="<?php echo esc_attr( $address ); ?>" />
                </div>

                <div class="th-songbook-field">
                    <label for="th_gig_subject"><?php esc_html_e( 'Subject', 'th-songbook' ); ?></label>
                    <textarea class="large-text" id="th_gig_subject" name="th_gig_subject" rows="5" placeholder="<?php echo esc_attr__( 'Add notes, set list focus, or other context for the gig.', 'th-songbook' ); ?>"><?php echo esc_textarea( $subject ); ?></textarea>
                </div>
            </div>
            <?php
        }

        /**
         * Persist gig metadata when a gig is saved.
         *
         * @param int     $post_id The post ID.
         * @param WP_Post $post    The post object.
         */
        public function save_gig_meta( $post_id, $post ) {
            if ( ! isset( $_POST['th_songbook_gig_meta_nonce'] ) || ! wp_verify_nonce( $_POST['th_songbook_gig_meta_nonce'], 'th_songbook_save_gig' ) ) {
                return;
            }

            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return;
            }

            if ( 'th_gig' !== $post->post_type ) {
                return;
            }

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            $venue      = isset( $_POST['th_gig_venue'] ) ? sanitize_text_field( wp_unslash( $_POST['th_gig_venue'] ) ) : '';
            $date       = isset( $_POST['th_gig_date'] ) ? $this->sanitize_date_value( wp_unslash( $_POST['th_gig_date'] ) ) : '';
            $start_time = isset( $_POST['th_gig_start_time'] ) ? $this->sanitize_time_value( wp_unslash( $_POST['th_gig_start_time'] ) ) : '';
            $get_in     = isset( $_POST['th_gig_get_in_time'] ) ? $this->sanitize_time_value( wp_unslash( $_POST['th_gig_get_in_time'] ) ) : '';
            $address    = isset( $_POST['th_gig_address'] ) ? sanitize_text_field( wp_unslash( $_POST['th_gig_address'] ) ) : '';
            $subject    = isset( $_POST['th_gig_subject'] ) ? sanitize_textarea_field( wp_unslash( $_POST['th_gig_subject'] ) ) : '';

            $this->update_meta_value( $post_id, 'th_gig_venue', $venue );
            $this->update_meta_value( $post_id, 'th_gig_date', $date );
            $this->update_meta_value( $post_id, 'th_gig_start_time', $start_time );
            $this->update_meta_value( $post_id, 'th_gig_get_in_time', $get_in );
            $this->update_meta_value( $post_id, 'th_gig_address', $address );
            $this->update_meta_value( $post_id, 'th_gig_subject', $subject );
        }

        /**
         * Enqueue admin assets for gig and song management screens.
         *
         * @param string $hook Current admin page hook suffix.
         */
        public function enqueue_admin_assets( $hook ) {
            if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
                return;
            }

            $screen = get_current_screen();
            if ( empty( $screen ) ) {
                return;
            }

            if ( in_array( $screen->post_type, array( 'th_song', 'th_gig' ), true ) ) {
                wp_enqueue_style(
                    'th-songbook-admin',
                    TH_SONGBOOK_PLUGIN_URL . 'assets/css/th-songbook-admin.css',
                    array(),
                    TH_SONGBOOK_VERSION
                );
            }

            if ( 'th_gig' === $screen->post_type ) {
                wp_enqueue_script(
                    'th-songbook-admin-gigs',
                    TH_SONGBOOK_PLUGIN_URL . 'assets/js/th-songbook-admin-gigs.js',
                    array( 'jquery' ),
                    TH_SONGBOOK_VERSION,
                    true
                );

                wp_localize_script(
                    'th-songbook-admin-gigs',
                    'thSongbookGig',
                    array(
                        'i18n' => array(
                            'invalidTime' => __( 'Please enter a valid time in 24-hour format (hh:mm).', 'th-songbook' ),
                        ),
                    )
                );
            }
        }

        /**
         * Provide the list of selectable song keys.
         *
         * @return array<string, string> Keys mapped to their labels.
         */
        private function get_song_keys() {
            return array(
                'C'  => 'C',
                'C#' => 'C# / Db',
                'D'  => 'D',
                'D#' => 'D# / Eb',
                'E'  => 'E',
                'F'  => 'F',
                'F#' => 'F# / Gb',
                'G'  => 'G',
                'G#' => 'G# / Ab',
                'A'  => 'A',
                'A#' => 'A# / Bb',
                'B'  => 'B',
            );
        }

        /**
         * Sanitize a submitted song key.
         *
         * @param string $value Raw input value.
         *
         * @return string Sanitized key or empty string.
         */
        private function sanitize_song_key( $value ) {
            $value = sanitize_text_field( $value );
            $value = trim( $value );

            if ( '' === $value ) {
                return '';
            }

            $allowed = array_keys( $this->get_song_keys() );

            if ( in_array( $value, $allowed, true ) ) {
                return $value;
            }

            return '';
        }

        /**
         * Update or delete a meta value depending on its contents.
         *
         * @param int    $post_id  Post ID.
         * @param string $meta_key Meta key to update.
         * @param string $value    Sanitized value.
         */
        private function update_meta_value( $post_id, $meta_key, $value ) {
            if ( '' === $value ) {
                delete_post_meta( $post_id, $meta_key );
                return;
            }

            update_post_meta( $post_id, $meta_key, $value );
        }

        /**
         * Sanitize a time field (hh:mm 24-hour format).
         *
         * @param string $value Raw input.
         *
         * @return string Sanitized value or empty string.
         */
        private function sanitize_time_value( $value ) {
            $value = trim( $value );

            if ( '' === $value ) {
                return '';
            }

            if ( preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) {
                return $value;
            }

            return '';
        }

        /**
         * Sanitize a date field (YYYY-MM-DD).
         *
         * @param string $value Raw input.
         *
         * @return string Sanitized value or empty string.
         */
        private function sanitize_date_value( $value ) {
            $value = trim( $value );

            if ( '' === $value ) {
                return '';
            }

            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
                return $value;
            }

            $timestamp = strtotime( $value );

            if ( false === $timestamp ) {
                return '';
            }

            return gmdate( 'Y-m-d', $timestamp );
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
