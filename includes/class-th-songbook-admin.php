<?php
/**
 * Admin UI, settings, and asset handling for entire TH Songbook.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TH_Songbook_Admin {
    /**
     * Main plugin instance.
     *
     * @var TH_Songbook
     */
    private $plugin;

    /**
     * Post type manager.
     *
     * @var TH_Songbook_Post_Types
     */
    private $post_types;

    /**
     * Constructor.
     *
     * @param TH_Songbook $plugin Main plugin instance.
     */
    public function __construct( TH_Songbook $plugin ) {
        $this->plugin     = $plugin;
        $this->post_types = $plugin->post_types;

        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_display_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Register main admin menu and sub-pages.
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

        add_submenu_page(
            'th-songbook',
            __( 'Display Settings', 'th-songbook' ),
            __( 'Display Settings', 'th-songbook' ),
            $capability,
            'th-songbook-display-settings',
            array( $this, 'render_display_settings_page' )
        );
    }

    /**
     * Admin dashboard placeholder content.
     */
    public function render_admin_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'TH Songbook', 'th-songbook' ); ?></h1>
            <p><?php esc_html_e( 'Use the Gigs submenu to schedule performances and the Songs submenu to manage your repertoire.', 'th-songbook' ); ?></p>
        </div>
        <?php
    }

    /**
     * Register display settings and sections.
     */
    public function register_display_settings() {
        $defaults = $this->plugin->get_display_settings_defaults();

        register_setting(
            'th_songbook_display_group',
            'th_songbook_display',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_display_settings' ),
                'default'           => $defaults,
            )
        );

        add_settings_section(
            'th_songbook_display_section',
            __( 'Display', 'th-songbook' ),
            '__return_false',
            'th_songbook_display_page'
        );
    }

    /**
     * Sanitize display settings (moved from closure for PHP compatibility).
     *
     * @param mixed $value Raw option value.
     * @return array Sanitized settings.
     */
    public function sanitize_display_settings( $value ) {
        $defaults = $this->plugin->get_display_settings_defaults();

        $value = is_array( $value ) ? $value : array();
        $out   = array(
            'screen_width'      => isset( $value['screen_width'] ) ? (int) $value['screen_width'] : $defaults['screen_width'],
            'screen_height'     => isset( $value['screen_height'] ) ? (int) $value['screen_height'] : $defaults['screen_height'],
            'nav_background'    => isset( $value['nav_background'] ) ? sanitize_hex_color( $value['nav_background'] ) : $defaults['nav_background'],
            'nav_icon'          => isset( $value['nav_icon'] ) ? sanitize_hex_color( $value['nav_icon'] ) : $defaults['nav_icon'],
            'font_max'          => isset( $value['font_max'] ) ? (int) $value['font_max'] : $defaults['font_max'],
            'font_min'          => isset( $value['font_min'] ) ? (int) $value['font_min'] : $defaults['font_min'],
            'clock_font_family' => isset( $value['clock_font_family'] ) ? sanitize_text_field( $value['clock_font_family'] ) : $defaults['clock_font_family'],
            'clock_font_size'   => isset( $value['clock_font_size'] ) ? (int) $value['clock_font_size'] : $defaults['clock_font_size'],
            'clock_font_weight' => isset( $value['clock_font_weight'] ) ? (int) $value['clock_font_weight'] : $defaults['clock_font_weight'],
            'song_title_font_size' => isset( $value['song_title_font_size'] ) ? (int) $value['song_title_font_size'] : $defaults['song_title_font_size'],
            'song_key_font_size'   => isset( $value['song_key_font_size'] ) ? (int) $value['song_key_font_size'] : $defaults['song_key_font_size'],
            'song_page_url'     => isset( $value['song_page_url'] ) ? esc_url_raw( $value['song_page_url'] ) : $defaults['song_page_url'],
            'gigs_page_url'     => isset( $value['gigs_page_url'] ) ? esc_url_raw( $value['gigs_page_url'] ) : $defaults['gigs_page_url'],
            'footer_min_height' => isset( $value['footer_min_height'] ) ? (int) $value['footer_min_height'] : $defaults['footer_min_height'],
        );

        if ( empty( $out['nav_background'] ) ) {
            $out['nav_background'] = $defaults['nav_background'];
        }

        if ( empty( $out['nav_icon'] ) ) {
            $out['nav_icon'] = $defaults['nav_icon'];
        }

        if ( $out['font_min'] > $out['font_max'] ) {
            $tmp             = $out['font_min'];
            $out['font_min'] = $out['font_max'];
            $out['font_max'] = $tmp;
        }

        $out['clock_font_family'] = $out['clock_font_family'] ? $out['clock_font_family'] : $defaults['clock_font_family'];

        if ( $out['clock_font_size'] < 12 ) {
            $out['clock_font_size'] = 12;
        } elseif ( $out['clock_font_size'] > 96 ) {
            $out['clock_font_size'] = 96;
        }

        if ( $out['clock_font_weight'] < 100 ) {
            $out['clock_font_weight'] = 100;
        } elseif ( $out['clock_font_weight'] > 900 ) {
            $out['clock_font_weight'] = 900;
        }

        return $out;
    }

    /**
     * Render the Display Settings page.
     */
    public function render_display_settings_page() {
        $defaults = $this->plugin->get_display_settings_defaults();
        $settings = get_option( 'th_songbook_display', array() );

        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $settings = array_merge( $defaults, $settings );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Display Settings', 'th-songbook' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'th_songbook_display_group' ); ?>
                <?php do_settings_sections( 'th_songbook_display_page' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="screen_width"><?php esc_html_e( 'Screen width', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[screen_width]" id="screen_width" type="number" class="small-text" value="<?php echo esc_attr( (int) $settings['screen_width'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="screen_height"><?php esc_html_e( 'Screen height', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[screen_height]" id="screen_height" type="number" class="small-text" value="<?php echo esc_attr( (int) $settings['screen_height'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nav_background"><?php esc_html_e( 'Nav background', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[nav_background]" id="nav_background" type="text" class="regular-text" value="<?php echo esc_attr( $settings['nav_background'] ); ?>" placeholder="#rrggbb"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nav_icon"><?php esc_html_e( 'Nav icon', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[nav_icon]" id="nav_icon" type="text" class="regular-text" value="<?php echo esc_attr( $settings['nav_icon'] ); ?>" placeholder="#rrggbb"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="font_max"><?php esc_html_e( 'Max font size', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[font_max]" id="font_max" type="number" class="small-text" value="<?php echo esc_attr( (int) $settings['font_max'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="font_min"><?php esc_html_e( 'Min font size', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[font_min]" id="font_min" type="number" class="small-text" value="<?php echo esc_attr( (int) $settings['font_min'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="clock_font_family"><?php esc_html_e( 'Clock font family', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[clock_font_family]" id="clock_font_family" type="text" class="regular-text" value="<?php echo esc_attr( $settings['clock_font_family'] ); ?>" placeholder="e.g. 'Roboto, sans-serif'"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="clock_font_size"><?php esc_html_e( 'Clock font size (px)', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[clock_font_size]" id="clock_font_size" type="number" class="small-text" value="<?php echo esc_attr( (int) $settings['clock_font_size'] ); ?>" min="12" max="96" step="1"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="clock_font_weight"><?php esc_html_e( 'Clock font weight', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[clock_font_weight]" id="clock_font_weight" type="number" class="small-text" value="<?php echo esc_attr( (int) $settings['clock_font_weight'] ); ?>" min="100" max="900" step="100"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="song_title_font_size"><?php esc_html_e( 'Song title font size (px)', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[song_title_font_size]" id="song_title_font_size" type="number" class="small-text" value="<?php echo esc_attr( (int) $settings['song_title_font_size'] ); ?>" min="10" max="96" step="1"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="song_key_font_size"><?php esc_html_e( 'Key label font size (px)', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[song_key_font_size]" id="song_key_font_size" type="number" class="small-text" value="<?php echo esc_attr( (int) $settings['song_key_font_size'] ); ?>" min="10" max="96" step="1"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="song_page_url"><?php esc_html_e( 'Single song page URL', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[song_page_url]" id="song_page_url" type="url" class="regular-text" value="<?php echo esc_attr( $settings['song_page_url'] ); ?>" placeholder="https://example.com/single-song/"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gigs_page_url"><?php esc_html_e( 'Gigs page URL', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[gigs_page_url]" id="gigs_page_url" type="url" class="regular-text" value="<?php echo esc_attr( $settings['gigs_page_url'] ); ?>" placeholder="https://example.com/gigs/"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="footer_min_height"><?php esc_html_e( 'Footer min height (px)', 'th-songbook' ); ?></label></th>
                        <td><input name="th_songbook_display[footer_min_height]" id="footer_min_height" type="number" class="small-text" value="<?php echo esc_attr( (int) $settings['footer_min_height'] ); ?>" min="32" max="200" step="1"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets for relevant screens.
     *
     * @param string $hook Current admin hook.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            $screen = get_current_screen();
            if ( empty( $screen ) ) {
                return;
            }
        } else {
            $screen = get_current_screen();
            if ( empty( $screen ) ) {
                return;
            }
        }

        $screen_id           = $screen->id;
        $screen_post_type    = isset( $screen->post_type ) ? $screen->post_type : '';
        $songbook_screen_ids = array(
            'toplevel_page_th-songbook',
            'th-songbook_page_th-songbook-display-settings',
        );
        $is_songbook_screen = in_array( $screen_id, $songbook_screen_ids, true ) || in_array( $screen_post_type, array( 'th_song', 'th_gig' ), true );

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
                    'songs' => $this->post_types->get_available_song_choices(),
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
}
