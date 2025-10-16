<?php
/**
 * Primary bootstrap class for TH Songbook.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TH_Songbook {
    /**
     * Singleton instance.
     *
     * @var TH_Songbook|null
     */
    private static $instance = null;

    /**
     * Post type manager.
     *
     * @var TH_Songbook_Post_Types
     */
    public $post_types;

    /**
     * Admin manager.
     *
     * @var TH_Songbook_Admin
     */
    public $admin;

    /**
     * Front-end manager.
     *
     * @var TH_Songbook_Frontend
     */
    public $frontend;

    /**
     * Default display settings.
     *
     * @var array<string, mixed>
     */
    private $display_settings_defaults = array(
        'screen_width'        => 1200,
        'screen_height'       => 1900,
        'nav_background'      => '#ffd319',
        'nav_icon'            => '#000000',
        'font_max'            => 34,
        'font_min'            => 18,
        'clock_font_family'   => 'Courier New, Courier, monospace',
        'clock_font_size'     => 32,
        'clock_font_weight'   => 600,
        // Global header sizes for single-song view (lyrics unaffected)
        'song_title_font_size'  => 32,
        'song_key_font_size'    => 26,
        'song_title_font_weight'=> 700,
        'song_title_font_family'=> '',
        'column_rule_color'     => '#e0e0e0',
        'song_list_text_color'  => '#1d2327',
        'song_list_text_size'   => 16,
        'song_list_text_weight' => 500,
        'song_hover_color'      => '#2271b1',
        'nav_hover_color'       => '#ffe268',
        'safe_badge_color'      => '#cde9ff',
        'last_song_badge_background' => '#fff1cc',
        'last_song_badge_border'     => '#f0b429',
        'last_song_badge_text'       => '#5b3b00',
        'gig_header_font_size'      => 28,
        'gig_header_font_weight'    => 700,
        'gig_header_color'          => '#000000',
        'gig_header_summary_size'   => 16,
        'gig_header_summary_color'  => '#000000',
        'gig_header_box_background' => '#ffffff',
        'gig_header_box_border'     => '#000000',
        'gig_header_box_text'       => '#000000',
        // New: URLs for dedicated pages and footer sizing.
        'song_page_url'       => '',
        'gigs_page_url'       => '',
        'footer_min_height'   => 56,
        'custom_css'          => '',
    );

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
     * Constructor.
     */
    private function __construct() {
        $this->post_types = new TH_Songbook_Post_Types( $this );
        $this->admin      = new TH_Songbook_Admin( $this );
        $this->frontend   = new TH_Songbook_Frontend( $this );
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserializing.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Retrieve display settings defaults.
     *
     * @return array<string, mixed>
     */
    public function get_display_settings_defaults() {
        return $this->display_settings_defaults;
    }

    /**
     * Retrieve merged display settings (options + defaults).
     *
     * @return array<string, mixed>
     */
    public function get_display_settings() {
        $options = get_option( 'th_songbook_display', array() );

        return wp_parse_args( is_array( $options ) ? $options : array(), $this->display_settings_defaults );
    }
}
