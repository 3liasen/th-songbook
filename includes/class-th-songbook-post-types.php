<?php
/**
 * Custom post type registration and meta handling for TH Songbook.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TH_Songbook_Post_Types {
    /**
     * Plugin bootstrap instance.
     *
     * @var TH_Songbook
     */
    private $plugin;

    /**
     * Cached map of latest gig dates per song.
     *
     * @var array<int|string, string>|null
     */
    private $song_last_used_map = null;

    /**
     * Constructor.
     *
     * @param TH_Songbook $plugin Main plugin instance.
     */
    public function __construct( TH_Songbook $plugin ) {
        $this->plugin = $plugin;

        add_action( 'init', array( $this, 'register_song_post_type' ) );
        add_action( 'init', array( $this, 'register_gig_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_song_meta_boxes' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_gig_meta_boxes' ) );
        add_action( 'save_post_th_song', array( $this, 'save_song_meta' ), 10, 2 );
        add_action( 'save_post_th_gig', array( $this, 'save_gig_meta' ), 10, 2 );
        add_filter( 'manage_th_song_posts_columns', array( $this, 'filter_song_admin_columns' ) );
        add_action( 'manage_th_song_posts_custom_column', array( $this, 'render_song_admin_column' ), 10, 2 );
        add_filter( 'manage_th_gig_posts_columns', array( $this, 'filter_gig_admin_columns' ) );
        add_action( 'manage_th_gig_posts_custom_column', array( $this, 'render_gig_admin_column' ), 10, 2 );
        add_filter( 'manage_edit-th_gig_sortable_columns', array( $this, 'make_gig_columns_sortable' ) );
        add_action( 'pre_get_posts', array( $this, 'adjust_admin_list_queries' ) );
    }

    /**
     * Register TH Song custom post type.
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
            'labels'       => $labels,
            'public'       => true,
            'has_archive'  => true,
            'show_in_menu' => 'th-songbook',
            'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
            'rewrite'      => array( 'slug' => 'songbook' ),
            'show_in_rest' => true,
        );

        register_post_type( 'th_song', $args );
    }

    /**
     * Register TH Gig custom post type.
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
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'th-songbook',
            'supports'        => array( 'title' ),
            'has_archive'     => false,
            'rewrite'         => false,
            'show_in_rest'    => false,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        );

        register_post_type( 'th_gig', $args );
    }

    /**
     * Register meta boxes for songs.
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
     * Ensure the Songs list defaults to title ASC ordering in wp-admin.
     *
     * @param WP_Query $query Current query instance.
     */
    public function adjust_admin_list_queries( $query ) {
        if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
            return;
        }

        $post_type = $query->get( 'post_type' );

        if ( empty( $post_type ) ) {
            return;
        }

        $is_song_list = ( is_array( $post_type ) && in_array( 'th_song', $post_type, true ) ) || 'th_song' === $post_type;
        $is_gig_list  = ( is_array( $post_type ) && in_array( 'th_gig', $post_type, true ) ) || 'th_gig' === $post_type;

        if ( $is_song_list && ! $query->get( 'orderby' ) ) {
            $query->set( 'orderby', 'title' );
            $query->set( 'order', 'ASC' );
        }

        if ( ! $is_gig_list ) {
            return;
        }

        $orderby = $query->get( 'orderby' );

        if ( empty( $orderby ) ) {
            $query->set( 'meta_key', 'th_gig_date' );
            $query->set( 'meta_type', 'DATE' );
            $query->set( 'orderby', 'meta_value' );
            $query->set( 'order', 'DESC' );
            return;
        }

        if ( 'th_gig_date' === $orderby ) {
            $query->set( 'meta_key', 'th_gig_date' );
            $query->set( 'meta_type', 'DATE' );
            $query->set( 'orderby', 'meta_value' );
        }
    }

    /**
     * Render song details meta box.
     *
     * @param WP_Post $post Current song post.
     */
    public function render_song_details_metabox( $post ) {
        $by       = get_post_meta( $post->ID, 'th_song_by', true );
        if ( '' === $by ) {
            $by = get_post_meta( $post->ID, 'th_song_composer', true );
        }

        $key           = get_post_meta( $post->ID, 'th_song_key', true );
        $duration      = get_post_meta( $post->ID, 'th_song_duration', true );
        $font_size     = get_post_meta( $post->ID, 'th_song_font_size', true );
        $line_height   = get_post_meta( $post->ID, 'th_song_line_height', true );
        $font_family   = get_post_meta( $post->ID, 'th_song_font_family', true );
        $font_weight   = get_post_meta( $post->ID, 'th_song_font_weight', true );
        $column_count  = get_post_meta( $post->ID, 'th_song_columns', true );

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
            <div class="th-songbook-inline">
                <div class="th-songbook-field">
                    <label for="th_song_font_size"><?php esc_html_e( 'Preferred font size (px)', 'th-songbook' ); ?></label>
                    <input type="number" class="small-text" id="th_song_font_size" name="th_song_font_size" value="<?php echo esc_attr( $font_size ); ?>" min="10" max="80" step="1" />
                    <p class="description"><?php esc_html_e( 'Leave empty to use the global sizing.', 'th-songbook' ); ?></p>
                </div>
                <div class="th-songbook-field">
                    <label for="th_song_line_height"><?php esc_html_e( 'Line height (e.g. 1.2)', 'th-songbook' ); ?></label>
                    <input type="number" class="small-text" id="th_song_line_height" name="th_song_line_height" value="<?php echo esc_attr( $line_height ); ?>" min="1" max="3" step="0.05" />
                    <p class="description"><?php esc_html_e( 'Optional. Controls vertical spacing between lines for this song only.', 'th-songbook' ); ?></p>
                </div>
                <div class="th-songbook-field">
                    <label for="th_song_font_weight"><?php esc_html_e( 'Font weight', 'th-songbook' ); ?></label>
                    <input type="number" class="small-text" id="th_song_font_weight" name="th_song_font_weight" value="<?php echo esc_attr( $font_weight ); ?>" min="100" max="900" step="100" />
                    <p class="description"><?php esc_html_e( 'Optional. Typical values: 400 (regular), 500, 600, 700. Leave empty for theme default.', 'th-songbook' ); ?></p>
                </div>
            </div>
            <div class="th-songbook-field">
                <label for="th_song_font_family"><?php esc_html_e( 'Font family (Google Fonts name)', 'th-songbook' ); ?></label>
                <input type="text" class="regular-text" id="th_song_font_family" name="th_song_font_family" value="<?php echo esc_attr( $font_family ); ?>" placeholder="e.g. Roboto or 'Open Sans'" />
                <p class="description"><?php esc_html_e( 'Enter the Google Font family name. The font is auto-loaded on the song page. You can include fallbacks, e.g. "Inter, sans-serif".', 'th-songbook' ); ?></p>
            </div>
            <div class="th-songbook-field">
                <label for="th_song_columns"><?php esc_html_e( 'Number of columns', 'th-songbook' ); ?></label>
                <select id="th_song_columns" name="th_song_columns">
                        <?php
                        $current_columns = (int) ( $column_count ?: 0 );
                        foreach ( array( 0 => __( 'Default', 'th-songbook' ), 1 => __( '1 Column', 'th-songbook' ), 2 => __( '2 Columns', 'th-songbook' ), 3 => __( '3 Columns', 'th-songbook' ) ) as $value => $label ) :
                            ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_columns, $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
        </div>
        <?php
    }

    /**
     * Adjust admin list columns for songs.
     *
     * @param array<string, string> $columns Existing columns.
     * @return array<string, string> Modified columns.
     */
    public function filter_song_admin_columns( $columns ) {
        $updated = array();

        if ( isset( $columns['cb'] ) ) {
            $updated['cb'] = $columns['cb'];
        }

        $updated['title']               = __( 'Title', 'th-songbook' );
        $updated['th_song_by']          = __( 'By', 'th-songbook' );
        $updated['th_song_key']         = __( 'Key', 'th-songbook' );
        $updated['th_song_duration']    = __( 'Song length', 'th-songbook' );
        $updated['th_song_last_used']   = __( 'Last used (Gig date)', 'th-songbook' );

        // Preserve any remaining registered columns (e.g., date, taxonomies).
        foreach ( $columns as $key => $label ) {
            if ( isset( $updated[ $key ] ) ) {
                continue;
            }
            $updated[ $key ] = $label;
        }

        return $updated;
    }

    /**
     * Customize the Gig admin columns.
     *
     * @param array<string, string> $columns Registered columns.
     *
     * @return array<string, string>
     */
    public function filter_gig_admin_columns( $columns ) {
        $updated = array();

        if ( isset( $columns['cb'] ) ) {
            $updated['cb'] = $columns['cb'];
        }

        if ( isset( $columns['title'] ) ) {
            $updated['title'] = $columns['title'];
        } else {
            $updated['title'] = __( 'Title', 'th-songbook' );
        }

        $updated['th_gig_date']    = __( 'Date', 'th-songbook' );
        $updated['th_gig_subject'] = __( 'Subject', 'th-songbook' );

        foreach ( $columns as $key => $label ) {
            if ( isset( $updated[ $key ] ) ) {
                continue;
            }

            $updated[ $key ] = $label;
        }

        return $updated;
    }

    /**
     * Render custom Gig column content.
     *
     * @param string $column  Column identifier.
     * @param int    $post_id Post ID.
     */
    public function render_gig_admin_column( $column, $post_id ) {
        switch ( $column ) {
            case 'th_gig_date':
                $raw = get_post_meta( $post_id, 'th_gig_date', true );
                if ( empty( $raw ) ) {
                    echo '&mdash;';
                    break;
                }

                $timestamp = strtotime( $raw );
                if ( false === $timestamp ) {
                    echo esc_html( $raw );
                    break;
                }

                echo esc_html( date_i18n( get_option( 'date_format' ), $timestamp ) );
                break;
            case 'th_gig_subject':
                $subject = get_post_meta( $post_id, 'th_gig_subject', true );
                if ( empty( $subject ) ) {
                    echo '&mdash;';
                    break;
                }

                echo esc_html( wp_trim_words( $subject, 18 ) );
                break;
        }
    }

    /**
     * Register sortable Gig columns.
     *
     * @param array<string, string> $columns Sortable columns.
     *
     * @return array<string, string>
     */
    public function make_gig_columns_sortable( $columns ) {
        $columns['th_gig_date'] = 'th_gig_date';

        return $columns;
    }

    /**
     * Render custom song column content.
     *
     * @param string $column  Column identifier.
     * @param int    $post_id Post ID.
     */
    public function render_song_admin_column( $column, $post_id ) {
        switch ( $column ) {
            case 'th_song_by':
                $by = get_post_meta( $post_id, 'th_song_by', true );
                if ( '' === $by ) {
                    $by = get_post_meta( $post_id, 'th_song_composer', true );
                }
                echo '' !== $by ? esc_html( $by ) : '&mdash;';
                break;
            case 'th_song_key':
                $key = get_post_meta( $post_id, 'th_song_key', true );
                echo '' !== $key ? esc_html( $key ) : '&mdash;';
                break;
            case 'th_song_duration':
                $duration = TH_Songbook_Utils::sanitize_song_duration_value( get_post_meta( $post_id, 'th_song_duration', true ) );
                echo '' !== $duration ? esc_html( $duration ) : '&mdash;';
                break;
            case 'th_song_last_used':
                $last_used = $this->get_song_last_used_display( $post_id );
                echo '' !== $last_used ? esc_html( $last_used ) : '&mdash;';
                break;
        }
    }

    /**
     * Register meta boxes for gigs.
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
     * Render gig details meta box.
     *
     * @param WP_Post $post Current gig post.
     */
    public function render_gig_details_metabox( $post ) {
        $venue       = get_post_meta( $post->ID, 'th_gig_venue', true );
        $date        = get_post_meta( $post->ID, 'th_gig_date', true );
        $start_time  = get_post_meta( $post->ID, 'th_gig_start_time', true );
        $get_in      = get_post_meta( $post->ID, 'th_gig_get_in_time', true );
        $address     = get_post_meta( $post->ID, 'th_gig_address', true );
        $subject     = get_post_meta( $post->ID, 'th_gig_subject', true );
        $set_count   = (int) get_post_meta( $post->ID, 'th_gig_set_count', true );
        $in_between  = TH_Songbook_Utils::sanitize_song_duration_value( get_post_meta( $post->ID, 'th_gig_in_between', true ) );

        if ( $set_count < 1 ) {
            $set_count = 2;
        }

        $stored_sets        = get_post_meta( $post->ID, 'th_gig_sets', true );
        $stored_encores     = get_post_meta( $post->ID, 'th_gig_encores', true );
        $selected_set_ids   = array();
        $selected_encores   = array();

        for ( $i = 1; $i <= $set_count; $i++ ) {
            $key                       = 'set' . $i;
            $selected_set_ids[ $key ]  = array();
            $selected_encores[ $key ]  = array();
        }

        if ( is_array( $stored_sets ) ) {
            foreach ( $selected_set_ids as $set_key => $unused ) {
                if ( isset( $stored_sets[ $set_key ] ) ) {
                    $selected_set_ids[ $set_key ] = array_map( 'absint', (array) $stored_sets[ $set_key ] );
                }
            }
        }

        if ( is_array( $stored_encores ) ) {
            foreach ( $selected_encores as $set_key => $unused ) {
                if ( isset( $stored_encores[ $set_key ] ) ) {
                    $raw_values = (array) $stored_encores[ $set_key ];
                    $selected_encores[ $set_key ] = array_values(
                        array_unique(
                            array_filter(
                                array_map( 'absint', $raw_values )
                            )
                        )
                    );
                }
            }
        }

        $has_any = false;
        foreach ( $selected_set_ids as $ids ) {
            if ( ! empty( $ids ) ) { $has_any = true; break; }
        }

        if ( ! $has_any ) {
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

        $selected_sets      = array();
        $encore_details     = array();
        $in_between_seconds = TH_Songbook_Utils::parse_duration_to_seconds( $in_between );

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

        foreach ( $selected_encores as $set_key => $encore_ids ) {
            $encore_details[ $set_key ] = array();
            foreach ( (array) $encore_ids as $encore_id ) {
                $encore_id = (int) $encore_id;
                if ( $encore_id < 1 ) {
                    continue;
                }

                if ( isset( $available_song_map[ $encore_id ] ) ) {
                    $encore_details[ $set_key ][] = array(
                        'id'       => $encore_id,
                        'title'    => $available_song_map[ $encore_id ]['title'],
                        'duration' => $available_song_map[ $encore_id ]['duration'],
                        'missing'  => false,
                    );
                } else {
                    $encore_details[ $set_key ][] = array(
                        'id'       => $encore_id,
                        'title'    => sprintf( __( 'Song #%d (unavailable)', 'th-songbook' ), $encore_id ),
                        'duration' => '',
                        'missing'  => true,
                    );
                }
            }
        }

        $set_totals = array();
        foreach ( $selected_set_ids as $set_key => $unused ) {
            $songs_in = isset( $selected_sets[ $set_key ] ) ? $selected_sets[ $set_key ] : array();
            if ( isset( $encore_details[ $set_key ] ) && ! empty( $encore_details[ $set_key ] ) ) {
                $songs_in = array_merge( $songs_in, $encore_details[ $set_key ] );
            }
            $set_totals[ $set_key ] = TH_Songbook_Utils::calculate_set_total_duration( $songs_in, $in_between_seconds );
        }

        $set_configs = array();
        for ( $i = 1; $i <= $set_count; $i++ ) {
            $key = 'set' . $i;
            $set_configs[ $key ] = array(
                'heading'  => sprintf( __( '%d. Set', 'th-songbook' ), $i ),
                'input_id' => 'th-songbook-song-search-' . $key . '-' . $post->ID,
                'field'    => 'th_gig_' . $key . '_songs[]',
            );
        }

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
                <label for="th_gig_set_count"><?php esc_html_e( 'Number of sets', 'th-songbook' ); ?></label>
                <input type="number" class="small-text" id="th_gig_set_count" name="th_gig_set_count" value="<?php echo esc_attr( $set_count ); ?>" min="1" max="6" step="1" />
                <p class="description"><?php esc_html_e( 'Displayed in the gig overview and front-end set list.', 'th-songbook' ); ?></p>
            </div>

            <div class="th-songbook-field">
                <label for="th_gig_in_between"><?php esc_html_e( 'In between song duration (mm:ss)', 'th-songbook' ); ?></label>
                <input type="text" class="small-text" id="th_gig_in_between" name="th_gig_in_between" value="<?php echo esc_attr( $in_between ); ?>" placeholder="00:30" pattern="^\d+:[0-5][0-9]$" inputmode="numeric" />
                <p class="description"><?php esc_html_e( 'Added between every song when calculating set totals.', 'th-songbook' ); ?></p>
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
                            <?php
                            $encore_songs    = isset( $encore_details[ $set_key ] ) ? $encore_details[ $set_key ] : array();
                            $encore_field    = 'th_gig_' . $set_key . '_encore[]';
                            $encore_input_id = 'th-songbook-encore-search-' . $set_key . '-' . $post->ID;
                            ?>
                            <div class="th-songbook-encore">
                                <label class="th-songbook-encore__label" for="<?php echo esc_attr( $encore_input_id ); ?>"><?php esc_html_e( 'EKSTRA', 'th-songbook' ); ?></label>
                                <div class="th-songbook-song-manager th-songbook-song-manager--encore" data-song-search="<?php echo esc_attr( $post->ID ); ?>" data-set-key="<?php echo esc_attr( $set_key ); ?>" data-field-name="<?php echo esc_attr( $encore_field ); ?>" data-empty-message="<?php echo esc_attr__( 'No encore songs assigned yet.', 'th-songbook' ); ?>">
                                    <?php if ( ! empty( $available_song_choices ) ) : ?>
                                        <div class="th-songbook-song-search">
                                            <label class="screen-reader-text" for="<?php echo esc_attr( $encore_input_id ); ?>"><?php esc_html_e( 'Search encore songs', 'th-songbook' ); ?></label>
                                            <input type="search" id="<?php echo esc_attr( $encore_input_id ); ?>" class="th-songbook-song-search__input" placeholder="<?php echo esc_attr__( 'Search songs...', 'th-songbook' ); ?>" autocomplete="off" />
                                            <ul class="th-songbook-song-search__results" role="listbox"></ul>
                                        </div>
                                    <?php endif; ?>
                                    <ul class="th-songbook-song-list">
                                        <?php foreach ( $encore_songs as $encore_song ) : ?>
                                            <?php
                                            $encore_duration_display = ! empty( $encore_song['duration'] ) ? $encore_song['duration'] : __( '--:--', 'th-songbook' );
                                            ?>
                                            <li class="th-songbook-song-list__item<?php echo ! empty( $encore_song['missing'] ) ? ' is-missing' : ''; ?>" data-song-id="<?php echo esc_attr( $encore_song['id'] ); ?>" data-song-duration="<?php echo esc_attr( $encore_song['duration'] ); ?>">
                                                <span class="th-songbook-song-list__handle dashicons dashicons-move" aria-hidden="true" title="<?php echo esc_attr__( 'Drag to reorder', 'th-songbook' ); ?>"></span>
                                                <span class="th-songbook-song-list__title"><?php echo esc_html( $encore_song['title'] ); ?></span>
                                                <span class="th-songbook-song-list__duration"><?php echo esc_html( $encore_duration_display ); ?></span>
                                                <button type="button" class="button-link th-songbook-remove-song"><?php esc_html_e( 'Remove', 'th-songbook' ); ?></button>
                                                <input type="hidden" name="<?php echo esc_attr( $encore_field ); ?>" value="<?php echo esc_attr( $encore_song['id'] ); ?>" />
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if ( empty( $encore_songs ) ) : ?>
                                        <p class="description th-songbook-song-list__empty"><?php esc_html_e( 'No encore songs assigned yet.', 'th-songbook' ); ?></p>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e( 'Optional EKSTRA numbers played after this set.', 'th-songbook' ); ?></p>
                                <?php
                                $has_missing_encore = false;
                                if ( isset( $encore_details[ $set_key ] ) ) {
                                    foreach ( $encore_details[ $set_key ] as $encore_song ) {
                                        if ( ! empty( $encore_song['missing'] ) ) {
                                            $has_missing_encore = true;
                                            break;
                                        }
                                    }
                                }
                                if ( $has_missing_encore ) :
                                    ?>
                                    <p class="description th-songbook-encore__warning"><?php esc_html_e( 'One or more selected EKSTRA songs are no longer available.', 'th-songbook' ); ?></p>
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

    /**
     * Persist song metadata when a song is saved.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
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

        $by        = isset( $_POST['th_song_by'] ) ? sanitize_text_field( wp_unslash( $_POST['th_song_by'] ) ) : '';
        $key       = isset( $_POST['th_song_key'] ) ? sanitize_text_field( wp_unslash( $_POST['th_song_key'] ) ) : '';
        $duration  = isset( $_POST['th_song_duration'] ) ? TH_Songbook_Utils::sanitize_song_duration_value( wp_unslash( $_POST['th_song_duration'] ) ) : '';
        $font_size = isset( $_POST['th_song_font_size'] ) ? absint( wp_unslash( $_POST['th_song_font_size'] ) ) : '';
        $columns     = isset( $_POST['th_song_columns'] ) ? (int) wp_unslash( $_POST['th_song_columns'] ) : 0;
        $font_family = isset( $_POST['th_song_font_family'] ) ? sanitize_text_field( wp_unslash( $_POST['th_song_font_family'] ) ) : '';
        $font_weight = isset( $_POST['th_song_font_weight'] ) ? absint( wp_unslash( $_POST['th_song_font_weight'] ) ) : '';
        $line_height = isset( $_POST['th_song_line_height'] ) ? floatval( wp_unslash( $_POST['th_song_line_height'] ) ) : '';

        if ( $font_size < 10 || $font_size > 80 ) {
            $font_size = '';
        }

        if ( $columns < 0 || $columns > 3 ) {
            $columns = 0;
        }
        if ( ! is_numeric( $line_height ) || $line_height < 1 || $line_height > 3 ) {
            $line_height = '';
        }

        TH_Songbook_Utils::update_meta_value( $post_id, 'th_song_by', $by );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_song_key', $key );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_song_duration', $duration );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_song_font_size', $font_size );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_song_font_family', $font_family );
        if ( $font_weight < 100 || $font_weight > 900 ) { $font_weight = ''; }
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_song_font_weight', $font_weight );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_song_line_height', $line_height );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_song_columns', $columns );

        delete_post_meta( $post_id, 'th_song_composer' );
        delete_post_meta( $post_id, 'th_song_lyrics' );
    }

    /**
     * Persist gig metadata when a gig is saved.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
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
        $date       = isset( $_POST['th_gig_date'] ) ? TH_Songbook_Utils::sanitize_date_value( wp_unslash( $_POST['th_gig_date'] ) ) : '';
        $start_time = isset( $_POST['th_gig_start_time'] ) ? TH_Songbook_Utils::sanitize_time_value( wp_unslash( $_POST['th_gig_start_time'] ) ) : '';
        $get_in     = isset( $_POST['th_gig_get_in_time'] ) ? TH_Songbook_Utils::sanitize_time_value( wp_unslash( $_POST['th_gig_get_in_time'] ) ) : '';
        $address    = isset( $_POST['th_gig_address'] ) ? sanitize_text_field( wp_unslash( $_POST['th_gig_address'] ) ) : '';
        $subject    = isset( $_POST['th_gig_subject'] ) ? sanitize_textarea_field( wp_unslash( $_POST['th_gig_subject'] ) ) : '';
        $set_count  = isset( $_POST['th_gig_set_count'] ) ? absint( wp_unslash( $_POST['th_gig_set_count'] ) ) : 0;
        $in_between = isset( $_POST['th_gig_in_between'] ) ? TH_Songbook_Utils::sanitize_song_duration_value( wp_unslash( $_POST['th_gig_in_between'] ) ) : '';

        if ( $set_count < 1 ) {
            $set_count = 0;
        } elseif ( $set_count > 6 ) {
            $set_count = 6;
        }

        TH_Songbook_Utils::update_meta_value( $post_id, 'th_gig_venue', $venue );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_gig_date', $date );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_gig_start_time', $start_time );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_gig_get_in_time', $get_in );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_gig_address', $address );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_gig_subject', $subject );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_gig_set_count', $set_count );
        TH_Songbook_Utils::update_meta_value( $post_id, 'th_gig_in_between', $in_between );

        // Collect dynamic sets submitted according to set_count.
        $sets_payload = array();
        $combined     = array();

        $effective_set_count = $set_count > 0 ? $set_count : 0;
        if ( 0 === $effective_set_count ) {
            // Fallback: detect any th_gig_setN_songs fields present.
            foreach ( $_POST as $key => $value ) {
                if ( preg_match( '/^th_gig_set(\d+)_songs$/', $key, $m ) ) {
                    $n = (int) $m[1];
                    if ( $n > $effective_set_count ) {
                        $effective_set_count = $n;
                    }
                }
            }
        }

        $encores_payload = array();

        for ( $i = 1; $i <= $effective_set_count; $i++ ) {
            $field = 'th_gig_set' . $i . '_songs';
            $ids   = array();
            if ( isset( $_POST[ $field ] ) ) {
                $ids = array_map( 'absint', (array) wp_unslash( $_POST[ $field ] ) );
            }
            $ids = array_values( array_unique( array_filter( $ids ) ) );
            $sets_payload[ 'set' . $i ] = $ids;
            $combined = array_merge( $combined, $ids );

            $encore_field = 'th_gig_set' . $i . '_encore';
            $encore_ids   = array();
            if ( isset( $_POST[ $encore_field ] ) ) {
                $encore_ids = array_map( 'absint', (array) wp_unslash( $_POST[ $encore_field ] ) );
                $encore_ids = array_values( array_unique( array_filter( $encore_ids ) ) );
            }

            if ( ! empty( $encore_ids ) ) {
                $encores_payload[ 'set' . $i ] = $encore_ids;
                $combined = array_merge( $combined, $encore_ids );
            }
        }

        if ( empty( $sets_payload ) ) {
            delete_post_meta( $post_id, 'th_gig_sets' );
        } else {
            update_post_meta( $post_id, 'th_gig_sets', $sets_payload );
        }

        if ( empty( $encores_payload ) ) {
            delete_post_meta( $post_id, 'th_gig_encores' );
        } else {
            update_post_meta( $post_id, 'th_gig_encores', $encores_payload );
        }

        if ( ! empty( $combined ) ) {
            update_post_meta( $post_id, 'th_gig_songs', $combined );
        } else {
            delete_post_meta( $post_id, 'th_gig_songs' );
        }
    }

    /**
     * Retrieve song choices for admin UI.
     *
     * @return array<int, array{id:int,title:string,duration:string}>
     */
    public function get_available_song_choices() {
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
                'duration' => TH_Songbook_Utils::sanitize_song_duration_value( get_post_meta( $song_id, 'th_song_duration', true ) ),
            );
        }

        return $choices;
    }

    /**
     * Retrieve formatted last used date for a song.
     *
     * @param int $song_id Song ID.
     * @return string Formatted date or empty string.
     */
    private function get_song_last_used_display( $song_id ) {
        $map = $this->get_song_last_used_map();

        if ( empty( $map[ $song_id ] ) ) {
            return '';
        }

        $date_value = $map[ $song_id ];
        $timezone   = wp_timezone();
        $date_obj   = date_create_immutable_from_format( 'Y-m-d', $date_value, $timezone );

        if ( $date_obj instanceof \DateTimeImmutable ) {
            return wp_date( get_option( 'date_format' ), $date_obj->getTimestamp(), $timezone );
        }

        $timestamp = strtotime( $date_value );
        if ( false !== $timestamp ) {
            return wp_date( get_option( 'date_format' ), $timestamp, $timezone );
        }

        return '';
    }

    /**
     * Build or return cached map of song IDs to latest gig dates.
     *
     * @return array<int|string, string>
     */
    private function get_song_last_used_map() {
        if ( null !== $this->song_last_used_map ) {
            return $this->song_last_used_map;
        }

        $map  = array();
        $gigs = get_posts(
            array(
                'post_type'      => 'th_gig',
                'post_status'    => array( 'publish', 'future', 'private', 'draft', 'pending' ),
                'posts_per_page' => -1,
                'orderby'        => 'meta_value',
                'order'          => 'DESC',
                'meta_key'       => 'th_gig_date',
                'meta_type'      => 'DATE',
                'fields'         => 'ids',
            )
        );

        foreach ( $gigs as $gig_id ) {
            $date_value = get_post_meta( $gig_id, 'th_gig_date', true );
            if ( empty( $date_value ) ) {
                continue;
            }

            $song_ids = array();

            $set_lists = $this->get_gig_setlists( $gig_id );
            if ( is_array( $set_lists ) ) {
                foreach ( $set_lists as $ids ) {
                    foreach ( (array) $ids as $id ) {
                        $id = (int) $id;
                        if ( $id > 0 ) {
                            $song_ids[] = $id;
                        }
                    }
                }
            }

            $encores = $this->get_gig_encores( $gig_id );
            if ( is_array( $encores ) ) {
                foreach ( $encores as $encore_list ) {
                    foreach ( (array) $encore_list as $encore_id ) {
                        $encore_id = (int) $encore_id;
                        if ( $encore_id > 0 ) {
                            $song_ids[] = $encore_id;
                        }
                    }
                }
            }

            if ( empty( $song_ids ) ) {
                continue;
            }

            $song_ids = array_unique( $song_ids );

            foreach ( $song_ids as $song_id ) {
                if ( isset( $map[ $song_id ] ) ) {
                    continue;
                }

                $map[ $song_id ] = $date_value;
            }
        }

        $this->song_last_used_map = $map;

        return $this->song_last_used_map;
    }

    /**
     * Retrieve normalized song IDs for each gig set.
     *
     * @param int $gig_id Gig post ID.
     *
     * @return array<string, array<int>>
     */
    public function get_gig_setlists( $gig_id ) {
        $stored_sets = get_post_meta( $gig_id, 'th_gig_sets', true );
        $sets        = array();

        if ( is_array( $stored_sets ) ) {
            // Keep keys in natural set order (set1, set2, ...)
            $keys = array_keys( $stored_sets );
            usort( $keys, function( $a, $b ) {
                $na = (int) preg_replace( '/[^\d]/', '', $a );
                $nb = (int) preg_replace( '/[^\d]/', '', $b );
                return $na <=> $nb;
            } );

            foreach ( $keys as $key ) {
                $ids       = isset( $stored_sets[ $key ] ) ? (array) $stored_sets[ $key ] : array();
                $sets[ $key ] = array_values( array_unique( array_map( 'absint', array_filter( $ids ) ) ) );
            }
        }

        if ( empty( $sets ) ) {
            $legacy = get_post_meta( $gig_id, 'th_gig_songs', true );
            if ( is_array( $legacy ) ) {
                $sets['set1'] = array_map( 'absint', $legacy );
            } elseif ( ! empty( $legacy ) ) {
                $sets['set1'] = array( absint( $legacy ) );
            }
        }

        return $sets;
    }

    /**
     * Retrieve encore song IDs keyed by set.
     *
     * @param int $gig_id Gig post ID.
     *
     * @return array<string, array<int>>
     */
    public function get_gig_encores( $gig_id ) {
        $stored_encores = get_post_meta( $gig_id, 'th_gig_encores', true );
        $encores        = array();

        if ( is_array( $stored_encores ) ) {
            foreach ( $stored_encores as $key => $value ) {
                $raw = (array) $value;
                $ids = array_values(
                    array_unique(
                        array_filter(
                            array_map( 'absint', $raw )
                        )
                    )
                );

                if ( ! empty( $ids ) ) {
                    $encores[ $key ] = $ids;
                }
            }
        }

        return $encores;
    }

    /**
     * Retrieve formatted song data for display.
     *
     * @param int $song_id Song ID.
     *
     * @return array<string, mixed>
     */
    public function get_song_display_data( $song_id ) {
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

        $key        = get_post_meta( $song_id, 'th_song_key', true );
        $duration   = TH_Songbook_Utils::sanitize_song_duration_value( get_post_meta( $song_id, 'th_song_duration', true ) );
        $content    = apply_filters( 'the_content', $post->post_content );
        $font_size   = get_post_meta( $song_id, 'th_song_font_size', true );
        $font_family = get_post_meta( $song_id, 'th_song_font_family', true );
        $font_weight = get_post_meta( $song_id, 'th_song_font_weight', true );
        $line_height = get_post_meta( $song_id, 'th_song_line_height', true );
        $columns     = get_post_meta( $song_id, 'th_song_columns', true );

        return array(
            'id'       => (int) $song_id,
            'title'    => get_the_title( $song_id ),
            'by'       => $by,
            'key'      => $key,
            'duration' => $duration,
            'content'  => wp_kses_post( $content ),
            'fontSize'  => $font_size ? (int) $font_size : null,
            'fontFamily'=> $font_family ? (string) $font_family : '',
            'fontWeight'=> $font_weight ? (int) $font_weight : null,
            'lineHeight'=> $line_height !== '' ? (float) $line_height : null,
            'columns'   => $columns !== '' ? (int) $columns : null,
            'missing'  => false,
        );
    }
}

