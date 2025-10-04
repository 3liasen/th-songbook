<?php
/**
 * Plugin Name: TH Songbook
 * Plugin URI: https://example.com/plugins/th-songbook
 * Description: Provides song management tools tailored for TH songbook workflows.
 * Version: 0.5.0
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
         * Cached front-end data used by shortcodes.
         *
         * @var array|null
         */
        private $frontend_data = null;

        /**
         * Flag to ensure front-end data is only localized once per request.
         *
         * @var bool
         */
        private $frontend_data_localized = false;

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
            add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
            add_shortcode( 'th_songbook_gig_list', array( $this, 'render_gig_list_shortcode' ) );
            add_shortcode( 'th_songbook_gig_detail', array( $this, 'render_gig_detail_shortcode' ) );
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
            $by       = get_post_meta( $post->ID, 'th_song_by', true );
            if ( '' === $by ) {
                $by = get_post_meta( $post->ID, 'th_song_composer', true );
            }

            $key      = get_post_meta( $post->ID, 'th_song_key', true );
            $duration = get_post_meta( $post->ID, 'th_song_duration', true );

            wp_nonce_field( 'th_songbook_save_song', 'th_songbook_song_meta_nonce' );
            ?>
            <div class="th-songbook-meta">
                <div class="th-songbook-field">
                    <label for="th_song_by"><?php esc_html_e( 'By', 'th-songbook' ); ?></label>
                    <input type="text" class="regular-text" id="th_song_by" name="th_song_by" value="<?php echo esc_attr( $by ); ?>" />
                </div>

                <div class="th-songbook-field">
                    <label for="th_song_key"><?php esc_html_e( 'Key', 'th-songbook' ); ?></label>
                    <input type="text" class="regular-text" id="th_song_key" name="th_song_key" value="<?php echo esc_attr( $key ); ?>" />
                </div>

                <div class="th-songbook-field">
                    <label for="th_song_duration"><?php esc_html_e( 'Time (mm:ss)', 'th-songbook' ); ?></label>
                    <input type="text" class="regular-text" id="th_song_duration" name="th_song_duration" value="<?php echo esc_attr( $duration ); ?>" pattern="^\d{1,3}:[0-5]\d$" placeholder="mm:ss" inputmode="numeric" />
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

            $by       = isset( $_POST['th_song_by'] ) ? sanitize_text_field( wp_unslash( $_POST['th_song_by'] ) ) : '';
            $key      = isset( $_POST['th_song_key'] ) ? sanitize_text_field( wp_unslash( $_POST['th_song_key'] ) ) : '';
            $duration = isset( $_POST['th_song_duration'] ) ? $this->sanitize_song_duration_value( wp_unslash( $_POST['th_song_duration'] ) ) : '';

            $this->update_meta_value( $post_id, 'th_song_by', $by );
            $this->update_meta_value( $post_id, 'th_song_key', $key );
            $this->update_meta_value( $post_id, 'th_song_duration', $duration );

            delete_post_meta( $post_id, 'th_song_composer' );
            delete_post_meta( $post_id, 'th_song_lyrics' );
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

            $stored_sets = get_post_meta( $post->ID, 'th_gig_sets', true );
            $selected_set_ids = array(
                'set1' => array(),
                'set2' => array(),
            );

            if ( is_array( $stored_sets ) ) {
                foreach ( $selected_set_ids as $set_key => $value ) {
                    if ( isset( $stored_sets[ $set_key ] ) ) {
                        $selected_set_ids[ $set_key ] = array_map( 'absint', (array) $stored_sets[ $set_key ] );
                    }
                }
            }

            if ( empty( $selected_set_ids['set1'] ) && empty( $selected_set_ids['set2'] ) ) {
                $legacy_songs = get_post_meta( $post->ID, 'th_gig_songs', true );
                if ( is_array( $legacy_songs ) ) {
                    $selected_set_ids['set1'] = array_map( 'absint', $legacy_songs );
                } elseif ( ! empty( $legacy_songs ) ) {
                    $selected_set_ids['set1'] = array( absint( $legacy_songs ) );
                }
            }

            foreach ( $selected_set_ids as $set_key => $ids ) {
                $selected_set_ids[ $set_key ] = array_values( array_unique( array_filter( $ids ) ) );
            }

            $available_song_choices = $this->get_available_song_choices();
            $available_song_map     = array();

            foreach ( $available_song_choices as $song_choice ) {
                $available_song_map[ $song_choice['id'] ] = $song_choice;
            }

            $selected_sets = array(
                'set1' => array(),
                'set2' => array(),
            );

            foreach ( $selected_set_ids as $set_key => $song_ids ) {
                foreach ( $song_ids as $song_id ) {
                    if ( isset( $available_song_map[ $song_id ] ) ) {
                        $selected_sets[ $set_key ][] = array(
                            'id'       => $song_id,
                            'title'    => $available_song_map[ $song_id ]['title'],
                            'duration' => $available_song_map[ $song_id ]['duration'],
                            'missing'  => false,
                        );
                    } else {
                        $selected_sets[ $set_key ][] = array(
                            'id'       => $song_id,
                            'title'    => sprintf( __( 'Song #%d (unavailable)', 'th-songbook' ), $song_id ),
                            'duration' => '',
                            'missing'  => true,
                        );
                    }
                }
            }

            $set_totals = array(
                'set1' => $this->calculate_set_total_duration( $selected_sets['set1'] ),
                'set2' => $this->calculate_set_total_duration( $selected_sets['set2'] ),
            );

            $set_configs = array(
                'set1' => array(
                    'heading'  => __( '1. Set', 'th-songbook' ),
                    'input_id' => 'th-songbook-song-search-set1-' . $post->ID,
                    'field'    => 'th_gig_set1_songs[]',
                ),
                'set2' => array(
                    'heading'  => __( '2. Set', 'th-songbook' ),
                    'input_id' => 'th-songbook-song-search-set2-' . $post->ID,
                    'field'    => 'th_gig_set2_songs[]',
                ),
            );

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

                <div class="th-songbook-field th-songbook-field--songs">
                    <label for="<?php echo esc_attr( $set_configs['set1']['input_id'] ); ?>"><?php esc_html_e( 'Songs', 'th-songbook' ); ?></label>
                    <div class="th-songbook-setlists">
                        <?php foreach ( $set_configs as $set_key => $config ) : ?>
                            <?php
                            $songs_in_set = isset( $selected_sets[ $set_key ] ) ? $selected_sets[ $set_key ] : array();
                            ?>
                            <section class="th-songbook-setlist" data-set-key="<?php echo esc_attr( $set_key ); ?>">
                                <h4 class="th-songbook-setlist__title"><?php echo esc_html( $config['heading'] ); ?></h4>
                                <div class="th-songbook-song-manager" data-song-search="<?php echo esc_attr( $post->ID ); ?>" data-set-key="<?php echo esc_attr( $set_key ); ?>" data-field-name="<?php echo esc_attr( $config['field'] ); ?>">
                                    <?php if ( ! empty( $available_song_choices ) ) : ?>
                                        <div class="th-songbook-song-search">
                                            <label class="screen-reader-text" for="<?php echo esc_attr( $config['input_id'] ); ?>"><?php esc_html_e( 'Search songs', 'th-songbook' ); ?></label>
                                            <input type="search" id="<?php echo esc_attr( $config['input_id'] ); ?>" class="th-songbook-song-search__input" placeholder="<?php echo esc_attr__( 'Search songs...', 'th-songbook' ); ?>" autocomplete="off" />
                                            <ul class="th-songbook-song-search__results" role="listbox"></ul>
                                        </div>
                                    <?php endif; ?>
                                    <ul class="th-songbook-song-list">
                                        <?php foreach ( $songs_in_set as $song ) : ?>
                                            <?php
                                            $duration_display = ! empty( $song['duration'] ) ? $song['duration'] : __( '--:--', 'th-songbook' );
                                            ?>
                                            <li class="th-songbook-song-list__item<?php echo ! empty( $song['missing'] ) ? ' is-missing' : ''; ?>" data-song-id="<?php echo esc_attr( $song['id'] ); ?>" data-song-duration="<?php echo esc_attr( $song['duration'] ); ?>">
                                                <span class="th-songbook-song-list__handle dashicons dashicons-move" aria-hidden="true" title="<?php echo esc_attr__( 'Drag to reorder', 'th-songbook' ); ?>"></span>
                                                <span class="th-songbook-song-list__title"><?php echo esc_html( $song['title'] ); ?></span>
                                                <span class="th-songbook-song-list__duration"><?php echo esc_html( $duration_display ); ?></span>
                                                <button type="button" class="button-link th-songbook-remove-song"><?php esc_html_e( 'Remove', 'th-songbook' ); ?></button>
                                                <input type="hidden" name="<?php echo esc_attr( $config['field'] ); ?>" value="<?php echo esc_attr( $song['id'] ); ?>" />
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if ( empty( $songs_in_set ) ) : ?>
                                        <p class="description th-songbook-song-list__empty"><?php esc_html_e( 'No songs assigned yet.', 'th-songbook' ); ?></p>
                                    <?php endif; ?>
                                </div>
                                <p class="th-songbook-set-total">
                                    <span class="th-songbook-set-total__label"><?php esc_html_e( 'Total time:', 'th-songbook' ); ?></span>
                                    <span class="th-songbook-set-total__value" data-th-songbook-set-total="<?php echo esc_attr( $set_key ); ?>"><?php echo esc_html( $set_totals[ $set_key ] ); ?></span>
                                </p>
                            </section>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( empty( $available_song_choices ) ) : ?>
                        <p class="description"><?php esc_html_e( 'No songs available yet. Add songs first, then return to this gig.', 'th-songbook' ); ?></p>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'Search for a song to add it to a set. Click a song in the list to remove it.', 'th-songbook' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
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

            $set1 = array();
            if ( isset( $_POST['th_gig_set1_songs'] ) ) {
                $set1 = array_map( 'absint', (array) wp_unslash( $_POST['th_gig_set1_songs'] ) );
            }

            $set2 = array();
            if ( isset( $_POST['th_gig_set2_songs'] ) ) {
                $set2 = array_map( 'absint', (array) wp_unslash( $_POST['th_gig_set2_songs'] ) );
            }

            $set1 = array_values( array_unique( array_filter( $set1 ) ) );
            $set2 = array_values( array_unique( array_filter( $set2 ) ) );

            $sets_payload = array(
                'set1' => $set1,
                'set2' => $set2,
            );

            if ( empty( $set1 ) && empty( $set2 ) ) {
                delete_post_meta( $post_id, 'th_gig_sets' );
            } else {
                update_post_meta( $post_id, 'th_gig_sets', $sets_payload );
            }

            $combined = array_merge( $set1, $set2 );

            if ( ! empty( $combined ) ) {
                update_post_meta( $post_id, 'th_gig_songs', $combined );
            } else {
                delete_post_meta( $post_id, 'th_gig_songs' );
            }
        }
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
                wp_enqueue_style( 'dashicons' );

                wp_enqueue_script(
                    'th-songbook-admin-gigs',
                    TH_SONGBOOK_PLUGIN_URL . 'assets/js/th-songbook-admin-gigs.js',
                    array( 'jquery', 'jquery-ui-sortable' ),
                    TH_SONGBOOK_VERSION,
                    true
                );

                wp_localize_script(
                    'th-songbook-admin-gigs',
                    'thSongbookGig',
                    array(
                        'songs' => $this->get_available_song_choices(),
                        'i18n'  => array(
                            'invalidTime'       => __( 'Please enter a valid time in 24-hour format (hh:mm).', 'th-songbook' ),
                            'searchPlaceholder' => __( 'Search songs...', 'th-songbook' ),
                            'noMatches'         => __( 'No matching songs found.', 'th-songbook' ),
                            'removeSong'        => __( 'Remove', 'th-songbook' ),
                            'dragHandle'        => __( 'Drag to reorder', 'th-songbook' ),
                            'noSongsAssigned'   => __( 'No songs assigned yet.', 'th-songbook' ),
                        ),
                    )
                );
            }
        }

        /**
         * Register front-end assets for shortcode rendering.
         */
        public function register_frontend_assets() {
            if ( ! wp_style_is( 'th-songbook-frontend', 'registered' ) ) {
                wp_register_style(
                    'th-songbook-frontend',
                    TH_SONGBOOK_PLUGIN_URL . 'assets/css/th-songbook-frontend.css',
                    array(),
                    TH_SONGBOOK_VERSION
                );
            }

            if ( ! wp_script_is( 'th-songbook-frontend', 'registered' ) ) {
                wp_register_script(
                    'th-songbook-frontend',
                    TH_SONGBOOK_PLUGIN_URL . 'assets/js/th-songbook-frontend.js',
                    array(),
                    TH_SONGBOOK_VERSION,
                    true
                );
            }
        }

        /**
         * Ensure front-end assets are enqueued and localized.
         *
         * @param array $data Localized data payload.
         */
        private function enqueue_frontend_assets( array $data ) {
            if ( ! wp_style_is( 'th-songbook-frontend', 'registered' ) || ! wp_script_is( 'th-songbook-frontend', 'registered' ) ) {
                $this->register_frontend_assets();
            }

            wp_enqueue_style( 'th-songbook-frontend' );
            wp_enqueue_script( 'th-songbook-frontend' );

            if ( ! $this->frontend_data_localized ) {
                wp_localize_script( 'th-songbook-frontend', 'thSongbookData', $data );
                $this->frontend_data_localized = true;
            }
        }

        /**
         * Shortcode callback for [th_songbook_gig_list].
         *
         * @param array<string, mixed> $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_gig_list_shortcode( $atts ) {
            unset( $atts );

            $data = $this->prepare_frontend_payload();
            $this->enqueue_frontend_assets( $data );

            $gig_ids = isset( $data['gigs']['ids'] ) ? $data['gigs']['ids'] : array();

            ob_start();

            echo '<div class="th-songbook-gig-list" data-songbook-gig-list>';

            if ( empty( $gig_ids ) ) {
                echo '<p class="th-songbook-gig-list__empty">' . esc_html__( 'No gigs scheduled.', 'th-songbook' ) . '</p>';
                echo '</div>';
                return ob_get_clean();
            }

            echo '<ul class="th-songbook-gig-list__items">';

            foreach ( $gig_ids as $gig_id ) {
                if ( ! isset( $data['gigs']['items'][ $gig_id ] ) ) {
                    continue;
                }

                $gig = $data['gigs']['items'][ $gig_id ];

                $summary_parts = array();

                if ( ! empty( $gig['dateDisplay'] ) ) {
                    $date_summary = $gig['dateDisplay'];
                    if ( ! empty( $gig['timeDisplay'] ) ) {
                        $date_summary .= ' @ ' . $gig['timeDisplay'];
                    }
                    $summary_parts[] = $date_summary;
                }

                if ( ! empty( $gig['venue'] ) ) {
                    $summary_parts[] = $gig['venue'];
                }

                if ( ! empty( $gig['address'] ) ) {
                    $summary_parts[] = $gig['address'];
                }

                $summary_parts[] = sprintf(
                    _n( '%d song', '%d songs', (int) $gig['totalSongs'], 'th-songbook' ),
                    (int) $gig['totalSongs']
                );

                $summary = implode( ' - ', array_filter( $summary_parts ) );

                echo '<li class="th-songbook-gig-list__item">';
                echo '<button type="button" class="th-songbook-gig-list__toggle" data-gig-trigger="' . esc_attr( $gig_id ) . '">';
                echo '<span class="th-songbook-gig-list__summary">' . esc_html( $summary ) . '</span>';
                echo '<span class="th-songbook-gig-list__arrow" aria-hidden="true">&rarr;</span>';
                echo '<span class="screen-reader-text">' . esc_html( sprintf( __( 'View set list for %s', 'th-songbook' ), $gig['title'] ) ) . '</span>';
                echo '</button>';
                echo '</li>';
            }

            echo '</ul>';
            echo '</div>';

            return ob_get_clean();
        }

        /**
         * Shortcode callback for [th_songbook_gig_detail].
         *
         * @param array<string, mixed> $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_gig_detail_shortcode( $atts ) {
            unset( $atts );

            $data = $this->prepare_frontend_payload();
            $this->enqueue_frontend_assets( $data );

            ob_start();
            ?>
            <div class="th-songbook-gig-detail" data-songbook-gig-detail>
                <div class="th-songbook-gig-detail__inner" data-songbook-gig-detail-body aria-live="polite">
                    <p class="th-songbook-gig-detail__placeholder"><?php echo esc_html__( 'Select a gig to view the set list.', 'th-songbook' ); ?></p>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Prepare the front-end data payload consumed by JavaScript.
         *
         * @return array<string, mixed>
         */
        private function prepare_frontend_payload() {
            if ( null !== $this->frontend_data ) {
                return $this->frontend_data;
            }

            $gigs = $this->get_gig_data_for_frontend();

            $this->frontend_data = array(
                'gigs'    => array(
                    'ids'   => array_keys( $gigs ),
                    'items' => $gigs,
                ),
                'strings' => array(
                    'selectGigPrompt' => __( 'Select a gig to view the set list.', 'th-songbook' ),
                    'homeTitle'       => __( 'Set List Overview', 'th-songbook' ),
                    'homeButton'      => __( 'Home', 'th-songbook' ),
                    'previousButton'  => __( 'Previous', 'th-songbook' ),
                    'nextButton'      => __( 'Next', 'th-songbook' ),
                    'missingSong'     => __( 'This song is no longer available.', 'th-songbook' ),
                    'notesLabel'      => __( 'Notes', 'th-songbook' ),
                    'byLabel'         => __( 'By', 'th-songbook' ),
                    'keyLabel'        => __( 'Key', 'th-songbook' ),
                    'durationLabel'   => __( 'Time', 'th-songbook' ),
                    'setTotalLabel'   => __( 'Total time', 'th-songbook' ),
                    'noSongs'         => __( 'No songs assigned yet.', 'th-songbook' ),
                    'noDuration'      => __( '--:--', 'th-songbook' ),
                ),
            );

            return $this->frontend_data;
        }

        /**
         * Retrieve gigs and their song data for front-end rendering.
         *
         * @return array<int|string, array<string, mixed>>
         */
        private function get_gig_data_for_frontend() {
            $gigs = array();

            $posts = get_posts(
                array(
                    'post_type'      => 'th_gig',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'meta_value',
                    'meta_key'       => 'th_gig_date',
                    'meta_type'      => 'DATE',
                    'order'          => 'DESC',
                )
            );

            foreach ( $posts as $gig ) {
                $gigs[ $gig->ID ] = $this->format_gig_for_frontend( $gig );
            }

            return $gigs;
        }

        /**
         * Format a gig with its songs and metadata for front-end consumption.
         *
         * @param WP_Post $gig Gig post object.
         *
         * @return array<string, mixed>
         */
        private function format_gig_for_frontend( WP_Post $gig ) {
            $gig_id = $gig->ID;

            $set_ids = $this->get_gig_setlists( $gig_id );
            $set_labels = array(
                'set1' => __( '1. Set', 'th-songbook' ),
                'set2' => __( '2. Set', 'th-songbook' ),
            );

            $sets = array();
            $order = array();
            $total_songs = 0;
            $combined_seconds = 0;

            foreach ( array( 'set1', 'set2' ) as $set_key ) {
                $song_ids = isset( $set_ids[ $set_key ] ) ? $set_ids[ $set_key ] : array();
                $songs = array();

                foreach ( $song_ids as $index => $song_id ) {
                    $song = $this->get_song_display_data( $song_id );
                    $songs[] = $song;

                    $seconds = $this->parse_duration_to_seconds( $song['duration'] );
                    if ( null !== $seconds ) {
                        $combined_seconds += $seconds;
                    }

                    $order[] = array(
                        'setKey'  => $set_key,
                        'index'   => $index,
                        'songId'  => $song['id'],
                        'missing' => ! empty( $song['missing'] ),
                    );
                }

                $sets[] = array(
                    'key'           => $set_key,
                    'label'         => isset( $set_labels[ $set_key ] ) ? $set_labels[ $set_key ] : ucfirst( $set_key ),
                    'songs'         => $songs,
                    'totalDuration' => $this->calculate_set_total_duration( $songs ),
                );

                $total_songs += count( $song_ids );
            }

            $date_value = get_post_meta( $gig_id, 'th_gig_date', true );
            $time_value = get_post_meta( $gig_id, 'th_gig_start_time', true );
            $datetime = $this->get_gig_datetime_display( $date_value, $time_value );

            $venue   = get_post_meta( $gig_id, 'th_gig_venue', true );
            $address = get_post_meta( $gig_id, 'th_gig_address', true );
            $subject = get_post_meta( $gig_id, 'th_gig_subject', true );
            $notes_html = $subject ? wpautop( esc_html( $subject ) ) : '';

            return array(
                'id'               => (int) $gig_id,
                'title'            => get_the_title( $gig_id ),
                'date'             => $date_value,
                'dateDisplay'      => $datetime['date'],
                'timeDisplay'      => $datetime['time'],
                'venue'            => $venue,
                'address'          => $address,
                'notes'            => $subject,
                'notesHtml'        => $notes_html,
                'totalSongs'       => $total_songs,
                'songCountLabel'  => sprintf( _n( '%d song', '%d songs', $total_songs, 'th-songbook' ), $total_songs ),
                'combinedDuration' => $this->format_seconds_to_duration( $combined_seconds ),
                'sets'             => $sets,
                'order'            => $order,
            );
        }

        /**
         * Retrieve normalized song IDs for each gig set.
         *
         * @param int $gig_id Gig post ID.
         *
         * @return array<string, array<int>>
         */
        private function get_gig_setlists( $gig_id ) {
            $sets = array(
                'set1' => array(),
                'set2' => array(),
            );

            $stored_sets = get_post_meta( $gig_id, 'th_gig_sets', true );

            if ( is_array( $stored_sets ) ) {
                foreach ( $sets as $set_key => $unused ) {
                    if ( isset( $stored_sets[ $set_key ] ) ) {
                        $sets[ $set_key ] = array_map( 'absint', (array) $stored_sets[ $set_key ] );
                    }
                }
            }

            if ( empty( $sets['set1'] ) && empty( $sets['set2'] ) ) {
                $legacy = get_post_meta( $gig_id, 'th_gig_songs', true );
                if ( is_array( $legacy ) ) {
                    $sets['set1'] = array_map( 'absint', $legacy );
                } elseif ( ! empty( $legacy ) ) {
                    $sets['set1'] = array( absint( $legacy ) );
                }
            }

            foreach ( $sets as $key => $song_ids ) {
                $sets[ $key ] = array_values( array_unique( array_filter( $song_ids ) ) );
            }

            return $sets;
        }

        /**
         * Retrieve formatted song data for front-end display.
         *
         * @param int $song_id Song ID.
         *
         * @return array<string, mixed>
         */
        private function get_song_display_data( $song_id ) {
            $post = get_post( $song_id );

            if ( ! $post instanceof WP_Post ) {
                return array(
                    'id'       => (int) $song_id,
                    'title'    => sprintf( __( 'Song #%d (unavailable)', 'th-songbook' ), $song_id ),
                    'by'       => '',
                    'key'      => '',
                    'duration' => '',
                    'content'  => '',
                    'missing'  => true,
                );
            }

            $by = get_post_meta( $song_id, 'th_song_by', true );
            if ( '' === $by ) {
                $by = get_post_meta( $song_id, 'th_song_composer', true );
            }

            $key      = get_post_meta( $song_id, 'th_song_key', true );
            $duration = $this->sanitize_song_duration_value( get_post_meta( $song_id, 'th_song_duration', true ) );
            $content  = apply_filters( 'the_content', $post->post_content );

            return array(
                'id'       => (int) $song_id,
                'title'    => get_the_title( $song_id ),
                'by'       => $by,
                'key'      => $key,
                'duration' => $duration,
                'content'  => wp_kses_post( $content ),
                'missing'  => false,
            );
        }

        /**
         * Produce formatted date/time strings for a gig using the site timezone.
         *
         * @param string $date Date string (Y-m-d).
         * @param string $time Time string (HH:MM).
         *
         * @return array{date:string,time:string}
         */
        private function get_gig_datetime_display( $date, $time ) {
            $timezone     = wp_timezone();
            $date_display = '';
            $time_display = '';

            if ( ! empty( $date ) ) {
                $date_obj = date_create_immutable_from_format( 'Y-m-d', $date, $timezone );
                if ( $date_obj instanceof \DateTimeImmutable ) {
                    $date_display = wp_date( get_option( 'date_format' ), $date_obj->getTimestamp(), $timezone );
                }
            }

            if ( ! empty( $time ) ) {
                $reference = ! empty( $date ) ? $date . ' ' . $time : '1970-01-01 ' . $time;
                $time_obj  = date_create_immutable_from_format( 'Y-m-d H:i', $reference, $timezone );
                if ( $time_obj instanceof \DateTimeImmutable ) {
                    $time_display = wp_date( get_option( 'time_format' ), $time_obj->getTimestamp(), $timezone );
                }
            }

            return array(
                'date' => $date_display,
                'time' => $time_display,
            );
        }


        /**
         * Retrieve all songs that can be attached to a gig.
         *
         * @return array<int, array{id:int,title:string,duration:string}> Songs formatted for UI use.
         */
        private function get_available_song_choices() {
            $song_ids = get_posts(
                array(
                    'post_type'      => 'th_song',
                    'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'no_found_rows'  => true,
                    'fields'         => 'ids',
                )
            );

            $choices = array();

            foreach ( $song_ids as $song_id ) {
                $choices[] = array(
                    'id'       => (int) $song_id,
                    'title'    => get_the_title( $song_id ),
                    'duration' => $this->sanitize_song_duration_value( get_post_meta( $song_id, 'th_song_duration', true ) ),
                );
            }

            return $choices;
        }

        /**
         * Update or delete a meta value depending on its contents.
         *
         * @param int    $post_id  Post ID.
         * @param string $meta_key Meta key to update.
         * @param mixed  $value    Sanitized value.
         */
        private function update_meta_value( $post_id, $meta_key, $value ) {
            if ( '' === $value || ( is_array( $value ) && empty( $value ) ) ) {
                delete_post_meta( $post_id, $meta_key );
                return;
            }

            update_post_meta( $post_id, $meta_key, $value );
        }

        /**
         * Sanitize a song duration field (mm:ss).
         *
         * @param string $value Raw input.
         *
         * @return string Sanitized value or empty string.
         */
        private function sanitize_song_duration_value( $value ) {
            $seconds = $this->parse_duration_to_seconds( $value );

            if ( null === $seconds ) {
                return '';
            }

            return $this->format_seconds_to_duration( $seconds );
        }
        /**
         * Convert a song duration string (mm:ss) into seconds.
         *
         * @param string $value Duration string.
         *
         * @return int|null Total seconds or null if invalid.
         */
        private function parse_duration_to_seconds( $value ) {
            if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
                return null;
            }

            $value = trim( (string) $value );

            if ( '' === $value ) {
                return null;
            }

            if ( ! preg_match( '/^(\d+):([0-5]\d)$/', $value, $matches ) ) {
                return null;
            }

            $minutes = (int) $matches[1];
            $seconds = (int) $matches[2];

            return ( $minutes * 60 ) + $seconds;
        }

        /**
         * Format seconds as a duration string (mm:ss).
         *
         * @param int $seconds Total seconds.
         *
         * @return string Duration formatted as mm:ss.
         */
        private function format_seconds_to_duration( $seconds ) {
            $seconds = (int) max( 0, $seconds );
            $minutes = (int) floor( $seconds / 60 );
            $remaining = $seconds % 60;

            return sprintf( '%02d:%02d', $minutes, $remaining );
        }

        /**
         * Calculate the total duration for a set of songs.
         *
         * @param array<int, array<string, mixed>> $songs Songs and their details.
         *
         * @return string Total duration formatted as mm:ss.
         */
        private function calculate_set_total_duration( array $songs ) {
            $total_seconds = 0;

            foreach ( $songs as $song ) {
                if ( empty( $song['duration'] ) ) {
                    continue;
                }

                $seconds = $this->parse_duration_to_seconds( $song['duration'] );

                if ( null !== $seconds ) {
                    $total_seconds += $seconds;
                }
            }

            return $this->format_seconds_to_duration( $total_seconds );
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
