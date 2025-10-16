<?php
/**
 * Front-end shortcodes, assets, and data preparation for TH Songbook.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TH_Songbook_Frontend {
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
     * Cached frontend payload.
     *
     * @var array<string, mixed>|null
     */
    private $frontend_data = null;

    /**
     * Tracks whether data has been localized.
     *
     * @var bool
     */
    private $frontend_data_localized = false;

    /**
     * Constructor.
     *
     * @param TH_Songbook $plugin Main plugin instance.
     */
    public function __construct( TH_Songbook $plugin ) {
        $this->plugin     = $plugin;
        $this->post_types = $plugin->post_types;

        add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
        add_shortcode( 'th_songbook_gig_list', array( $this, 'render_gig_list_shortcode' ) );
        add_shortcode( 'th_songbook_gig_detail', array( $this, 'render_gig_detail_shortcode' ) );
        // New: Single-song dedicated view.
        add_shortcode( 'th_songbook_song_view', array( $this, 'render_song_view_shortcode' ) );
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

        if ( ! wp_style_is( 'th-songbook-fontawesome', 'registered' ) ) {
            wp_register_style(
                'th-songbook-fontawesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
                array(),
                '6.5.1'
            );
        }

        wp_enqueue_style( 'th-songbook-fontawesome' );
        wp_enqueue_style( 'th-songbook-frontend' );
        wp_enqueue_script( 'th-songbook-frontend' );

        if ( isset( $data['settings']['custom_css'] ) ) {
            $custom_css = trim( (string) $data['settings']['custom_css'] );
            if ( '' !== $custom_css ) {
                wp_add_inline_style( 'th-songbook-frontend', $custom_css );
            }
        }

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

            if ( ! empty( $gig['setCountLabel'] ) ) {
                $summary_parts[] = $gig['setCountLabel'];
            }

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
     * Shortcode callback for [th_songbook_song_view]. Renders only the song view area.
     * This page is intended to be used on a dedicated "single-song" page.
     *
     * Accepts optional attributes or query args:
     * - gig: Gig ID
     * - song: Song pointer index within the gig order
     *
     * @param array<string,mixed> $atts Shortcode attributes.
     * @return string
     */
    public function render_song_view_shortcode( $atts ) {
        add_filter( 'body_class', array( $this, 'add_song_view_body_class' ) );
        $atts = shortcode_atts(
            array(
                'gig'  => isset( $_GET['gig'] ) ? sanitize_text_field( wp_unslash( $_GET['gig'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                'song' => isset( $_GET['song'] ) ? (int) $_GET['song'] : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ),
            is_array( $atts ) ? $atts : array()
        );

        $data = $this->prepare_frontend_payload();
        $this->enqueue_frontend_assets( $data );

        ob_start();
        ?>
        <div class="th-songbook-gig-detail is-song-view" data-songbook-gig-detail>
            <div class="th-songbook-gig-detail__inner" data-songbook-gig-detail-body aria-live="polite">
                <p class="th-songbook-gig-detail__placeholder"><?php echo esc_html__( 'Loading song…', 'th-songbook' ); ?></p>
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
                'setCountLabel'   => __( 'Sets', 'th-songbook' ),
                'encoreLabel'     => __( 'EKSTRA', 'th-songbook' ),
            ),
            'settings' => $this->plugin->get_display_settings(),
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

        $set_ids     = $this->post_types->get_gig_setlists( $gig_id );
        $encore_ids  = $this->post_types->get_gig_encores( $gig_id );
        $set_count   = (int) get_post_meta( $gig_id, 'th_gig_set_count', true );
        $in_between  = TH_Songbook_Utils::sanitize_song_duration_value( get_post_meta( $gig_id, 'th_gig_in_between', true ) );
        $in_between_seconds = TH_Songbook_Utils::parse_duration_to_seconds( $in_between );

        $sets            = array();
        $order           = array();
        $total_songs     = 0;
        $combined_seconds = 0;

        $ordered_keys = array_keys( $set_ids );
        // Natural order by numeric suffix.
        usort( $ordered_keys, function( $a, $b ) {
            $na = (int) preg_replace( '/[^\d]/', '', $a );
            $nb = (int) preg_replace( '/[^\d]/', '', $b );
            return $na <=> $nb;
        } );

        if ( $set_count > 0 && count( $ordered_keys ) > $set_count ) {
            $ordered_keys = array_slice( $ordered_keys, 0, $set_count );
        }

        $index_for_label = 0;
        foreach ( $ordered_keys as $set_key ) {
            $song_ids = isset( $set_ids[ $set_key ] ) ? (array) $set_ids[ $set_key ] : array();
            $songs    = array();
            $set_seconds = 0;

            foreach ( $song_ids as $song_id ) {
                $song             = $this->post_types->get_song_display_data( $song_id );
                $song['isEncore'] = false;
                $songs[]          = $song;

                $seconds = TH_Songbook_Utils::parse_duration_to_seconds( $song['duration'] );
                if ( null !== $seconds ) {
                    $combined_seconds += $seconds;
                    $set_seconds      += $seconds;
                }

                $order[] = array(
                    'setKey'   => $set_key,
                    'index'    => count( $songs ) - 1,
                    'songId'   => $song['id'],
                    'missing'  => ! empty( $song['missing'] ),
                    'isEncore' => false,
                    'type'     => 'song',
                );
            }

            $encore_songs = array();
            if ( isset( $encore_ids[ $set_key ] ) ) {
                foreach ( (array) $encore_ids[ $set_key ] as $encore_id ) {
                    $encore_id = (int) $encore_id;
                    if ( $encore_id < 1 ) {
                        continue;
                    }

                    $encore_song             = $this->post_types->get_song_display_data( $encore_id );
                    $encore_song['isEncore'] = true;
                    $songs[]                 = $encore_song;
                    $encore_songs[]          = $encore_song;

                    $seconds = TH_Songbook_Utils::parse_duration_to_seconds( $encore_song['duration'] );
                    if ( null !== $seconds ) {
                        $combined_seconds += $seconds;
                        $set_seconds      += $seconds;
                    }

                    $order[] = array(
                        'setKey'   => $set_key,
                        'index'    => count( $songs ) - 1,
                        'songId'   => $encore_song['id'],
                        'missing'  => ! empty( $encore_song['missing'] ),
                        'isEncore' => true,
                        'type'     => 'encore',
                    );
                }
            }

            if ( $in_between_seconds && count( $songs ) > 1 ) {
                $spacer_total = $in_between_seconds * ( count( $songs ) - 1 );
                $set_seconds += $spacer_total;
                $combined_seconds += $spacer_total;
            }

            $index_for_label++;
            $sets[] = array(
                'key'           => $set_key,
                'label'         => sprintf( __( '%d. Set', 'th-songbook' ), $index_for_label ),
                'songs'         => $songs,
                'encore'        => $encore_songs,
                'encores'       => $encore_songs,
                'totalDuration' => TH_Songbook_Utils::format_seconds_to_duration( $set_seconds ),
            );

            $total_songs += count( $songs );
        }

        $date_value = get_post_meta( $gig_id, 'th_gig_date', true );
        $time_value = get_post_meta( $gig_id, 'th_gig_start_time', true );
        $datetime   = $this->get_gig_datetime_display( $date_value, $time_value );

        $venue      = get_post_meta( $gig_id, 'th_gig_venue', true );
        $address    = get_post_meta( $gig_id, 'th_gig_address', true );
        $subject    = get_post_meta( $gig_id, 'th_gig_subject', true );
        $notes_html = $subject ? wpautop( esc_html( $subject ) ) : '';

        $non_empty_sets = array_filter(
            $set_ids,
            static function( $ids ) {
                return ! empty( $ids );
            }
        );

        if ( $set_count < 1 ) {
            $set_count = count( $non_empty_sets );
        }

        if ( $set_count < 1 ) {
            $set_count = 1;
        }

        $set_count_label = sprintf(
            _n( '%d set', '%d sets', $set_count, 'th-songbook' ),
            $set_count
        );

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
            'songCountLabel'   => sprintf( _n( '%d song', '%d songs', $total_songs, 'th-songbook' ), $total_songs ),
            'combinedDuration' => TH_Songbook_Utils::format_seconds_to_duration( $combined_seconds ),
            'sets'             => $sets,
            'order'            => $order,
            'setCount'         => $set_count,
            'setCountLabel'    => $set_count_label,
            'inBetweenDuration' => $in_between,
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
     * Add identifying body class on single-song page.
     *
     * @param string[] $classes Body classes.
     * @return string[]
     */
    public function add_song_view_body_class( $classes ) {
        $classes[] = 'th-songbook-song-view-page';
        return $classes;
    }
}
