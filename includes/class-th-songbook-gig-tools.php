<?php
/**
 * Gig utilities for PDF generation, printing, and email distribution.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TH_Songbook_Gig_Tools {
    /**
     * Main plugin instance.
     *
     * @var TH_Songbook
     */
    private $plugin;

    /**
     * Constructor.
     *
     * @param TH_Songbook $plugin Main plugin instance.
     */
    public function __construct( TH_Songbook $plugin ) {
        $this->plugin = $plugin;

        add_action( 'add_meta_boxes', array( $this, 'register_gig_tools_metabox' ) );
        add_action( 'wp_ajax_th_songbook_generate_gig_pdf', array( $this, 'ajax_generate_gig_pdf' ) );
        add_action( 'wp_ajax_th_songbook_list_recipients', array( $this, 'ajax_list_recipients' ) );
        add_action( 'wp_ajax_th_songbook_send_gig_email', array( $this, 'ajax_send_gig_email' ) );
    }

    /**
     * Register the gig tools meta box.
     */
    public function register_gig_tools_metabox() {
        add_meta_box(
            'th-songbook-gig-tools',
            __( 'Gig Tools', 'th-songbook' ),
            array( $this, 'render_gig_tools_metabox' ),
            'th_gig',
            'side',
            'high'
        );
    }

    /**
     * Render the gig tools meta box UI.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_gig_tools_metabox( $post ) {
        $gig_id = isset( $post->ID ) ? (int) $post->ID : 0;
        $status = get_post_status( $gig_id );
        $has_id = $gig_id > 0 && $status && 'auto-draft' !== $status;
        $pdf    = $this->get_gig_pdf_meta( $gig_id );
        ?>
        <div class="th-songbook-gig-tools" data-th-songbook-gig-tools>
            <?php if ( ! $has_id ) : ?>
                <p class="description"><?php esc_html_e( 'Save the gig before creating a PDF.', 'th-songbook' ); ?></p>
            <?php endif; ?>
            <button type="button" class="button button-primary th-songbook-gig-tools__generate" data-gig-tools-generate <?php disabled( ! $has_id ); ?>>
                <?php esc_html_e( 'Create PDF', 'th-songbook' ); ?>
            </button>
            <p class="th-songbook-gig-tools__status" data-gig-tools-status></p>
            <div class="th-songbook-gig-tools__actions" data-gig-tools-actions <?php echo $pdf ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <button type="button" class="button th-songbook-gig-tools__print" data-gig-tools-print><?php esc_html_e( 'Print', 'th-songbook' ); ?></button>
                <button type="button" class="button button-primary th-songbook-gig-tools__email" data-gig-tools-email><?php esc_html_e( 'Send Email', 'th-songbook' ); ?></button>
            </div>
            <?php if ( $pdf ) : ?>
                <p class="description th-songbook-gig-tools__info">
                    <?php
                    printf(
                        /* translators: %s: formatted date and time */
                        esc_html__( 'Last generated: %s', 'th-songbook' ),
                        esc_html( $this->format_timestamp_label( $pdf['generated_at'] ) )
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Prepare JSON-friendly data for the admin script.
     *
     * @param int $gig_id Gig ID.
     *
     * @return array<string, mixed>
     */
    public function get_tools_config( $gig_id ) {
        $gig_id = (int) $gig_id;
        $status = $gig_id ? get_post_status( $gig_id ) : false;
        $usable = $gig_id > 0 && $status && 'auto-draft' !== $status;

        $pdf_meta = $usable ? $this->get_gig_pdf_meta( $gig_id ) : null;

        return array(
            'enabled'     => $usable,
            'gigId'       => $usable ? $gig_id : 0,
            'pdf'         => $pdf_meta ? array(
                'url'         => $pdf_meta['url'],
                'path'        => $pdf_meta['path'],
                'filename'    => $pdf_meta['filename'],
                'generatedAt' => $pdf_meta['generated_at'],
            ) : null,
            'strings'     => array(
                'creating'        => __( 'Creating PDF…', 'th-songbook' ),
                'created'         => __( 'PDF created successfully.', 'th-songbook' ),
                'errorGeneric'    => __( 'Something went wrong. Please try again.', 'th-songbook' ),
                'printing'        => __( 'Opening PDF for printing…', 'th-songbook' ),
                'noRecipients'    => __( 'No recipients available. Create one first.', 'th-songbook' ),
                'selectRecipients'=> __( 'Select at least one recipient.', 'th-songbook' ),
                'sending'         => __( 'Sending email…', 'th-songbook' ),
                'sent'            => __( 'Email sent successfully.', 'th-songbook' ),
                'lastGenerated'   => __( 'Last generated: %s', 'th-songbook' ),
                'modalTitle'      => __( 'Send set list', 'th-songbook' ),
                'cancelLabel'     => __( 'Cancel', 'th-songbook' ),
                'sendEmailLabel'  => __( 'Send Email', 'th-songbook' ),
            ),
            'nonces'      => array(
                'generate' => wp_create_nonce( 'th_songbook_generate_gig_pdf' ),
                'recipients' => wp_create_nonce( 'th_songbook_list_recipients' ),
                'email' => wp_create_nonce( 'th_songbook_send_gig_email' ),
            ),
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        );
    }

    /**
     * Handle AJAX request for generating a gig PDF.
     */
    public function ajax_generate_gig_pdf() {
        check_ajax_referer( 'th_songbook_generate_gig_pdf', 'nonce' );

        $gig_id = isset( $_POST['gigId'] ) ? (int) $_POST['gigId'] : 0;

        if ( $gig_id < 1 || ! current_user_can( 'edit_post', $gig_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to generate this PDF.', 'th-songbook' ) ), 403 );
        }

        $result = $this->generate_gig_pdf( $gig_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'pdf' => $result ) );
    }

    /**
     * Handle AJAX request for listing recipients.
     */
    public function ajax_list_recipients() {
        check_ajax_referer( 'th_songbook_list_recipients', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to access recipients.', 'th-songbook' ) ), 403 );
        }

        $recipients = $this->plugin->post_types->get_all_recipients();

        wp_send_json_success( array( 'recipients' => $recipients ) );
    }

    /**
     * Handle AJAX request for emailing a gig PDF.
     */
    public function ajax_send_gig_email() {
        check_ajax_referer( 'th_songbook_send_gig_email', 'nonce' );

        $gig_id      = isset( $_POST['gigId'] ) ? (int) $_POST['gigId'] : 0;
        $recipient_ids = isset( $_POST['recipients'] ) ? wp_parse_id_list( (array) $_POST['recipients'] ) : array();

        if ( $gig_id < 1 || ! current_user_can( 'edit_post', $gig_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to send this email.', 'th-songbook' ) ), 403 );
        }

        if ( empty( $recipient_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Select at least one recipient.', 'th-songbook' ) ) );
        }

        $recipients = $this->plugin->post_types->get_recipients_by_id( $recipient_ids );

        if ( empty( $recipients ) ) {
            wp_send_json_error( array( 'message' => __( 'No valid recipients found.', 'th-songbook' ) ) );
        }

        $pdf = $this->get_gig_pdf_meta( $gig_id );

        if ( ! $pdf || empty( $pdf['path'] ) || ! file_exists( $pdf['path'] ) ) {
            // Rebuild PDF if the stored one is missing.
            $pdf = $this->generate_gig_pdf( $gig_id );
            if ( is_wp_error( $pdf ) ) {
                wp_send_json_error( array( 'message' => $pdf->get_error_message() ) );
            }
        }

        $email = $this->prepare_email_payload( $gig_id, $recipients, $pdf );

        if ( is_wp_error( $email ) ) {
            wp_send_json_error( array( 'message' => $email->get_error_message() ) );
        }

        $sent = wp_mail(
            $email['to'],
            $email['subject'],
            $email['body'],
            $email['headers'],
            $email['attachments']
        );

        if ( ! $sent ) {
            wp_send_json_error( array( 'message' => __( 'Unable to send the email. Please try again.', 'th-songbook' ) ) );
        }

        wp_send_json_success();
    }

    /**
     * Generate the PDF for a gig and return info for the client.
     *
     * @param int $gig_id Gig ID.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function generate_gig_pdf( $gig_id ) {
        $gig = get_post( $gig_id );

        if ( ! $gig || 'th_gig' !== $gig->post_type ) {
            return new WP_Error( 'invalid_gig', __( 'Gig not found.', 'th-songbook' ) );
        }

        $gig_data = $this->get_gig_snapshot( $gig_id );
        if ( empty( $gig_data['sets'] ) ) {
            return new WP_Error( 'no_songs', __( 'No songs found for this gig.', 'th-songbook' ) );
        }

        require_once TH_SONGBOOK_PLUGIN_DIR . 'includes/lib/fpdf.php';

        $pdf = new FPDF();
        $pdf->SetTitle( $this->encode_pdf_text( $gig_data['title'] ) );
        $pdf->SetAuthor( $this->encode_pdf_text( get_bloginfo( 'name' ) ) );
        $pdf->AddPage();
        $pdf->SetFont( 'Helvetica', 'B', 16 );
        $pdf->Cell( 0, 10, $this->encode_pdf_text( $gig_data['title'] ), 0, 1 );

        $pdf->SetFont( 'Helvetica', '', 12 );

        if ( $gig_data['date_label'] ) {
            $pdf->Cell( 0, 8, $this->encode_pdf_text( sprintf( __( 'Date: %s', 'th-songbook' ), $gig_data['date_label'] ) ), 0, 1 );
        }

        if ( $gig_data['time_label'] ) {
            $pdf->Cell( 0, 6, $this->encode_pdf_text( sprintf( __( 'Start: %s', 'th-songbook' ), $gig_data['time_label'] ) ), 0, 1 );
        }

        if ( $gig_data['venue'] ) {
            $pdf->Cell( 0, 6, $this->encode_pdf_text( sprintf( __( 'Venue: %s', 'th-songbook' ), $gig_data['venue'] ) ), 0, 1 );
        }

        $pdf->Ln( 4 );

        $set_index = 0;
        foreach ( $gig_data['sets'] as $set ) {
            $set_index++;
            $pdf->SetFont( 'Helvetica', 'B', 13 );
            $pdf->Cell( 0, 8, $this->encode_pdf_text( sprintf( __( '%d. Set', 'th-songbook' ), $set_index ) ), 0, 1 );
            $pdf->SetFont( 'Helvetica', '', 12 );

            $number = 1;
            foreach ( $set['songs'] as $song ) {
                $label = $number . '. ' . $song['title'];
                if ( $song['is_safe'] ) {
                    $label .= ' (' . __( 'SAFE', 'th-songbook' ) . ')';
                }
                $pdf->MultiCell( 0, 6, $this->encode_pdf_text( $label ) );
                $number++;
            }

            if ( ! empty( $set['encores'] ) ) {
                $pdf->SetFont( 'Helvetica', 'B', 12 );
                $pdf->Cell( 0, 7, $this->encode_pdf_text( __( 'Encores', 'th-songbook' ) ), 0, 1 );
                $pdf->SetFont( 'Helvetica', '', 12 );

                foreach ( $set['encores'] as $encore ) {
                    $pdf->MultiCell( 0, 6, $this->encode_pdf_text( $encore['title'] . ' (' . __( 'ENCORE', 'th-songbook' ) . ')' ) );
                }
            }

            if ( $set_index < count( $gig_data['sets'] ) ) {
                $pdf->Ln( 4 );
            }
        }

        $upload_dir = wp_upload_dir();
        if ( empty( $upload_dir['basedir'] ) || empty( $upload_dir['baseurl'] ) ) {
            return new WP_Error( 'upload_dir', __( 'Unable to determine upload directory.', 'th-songbook' ) );
        }

        $target_dir = trailingslashit( $upload_dir['basedir'] ) . 'th-songbook';
        if ( ! wp_mkdir_p( $target_dir ) ) {
            return new WP_Error( 'dir_create', __( 'Unable to create the PDF directory.', 'th-songbook' ) );
        }

        $filename = 'gig-' . $gig_id . '-' . time() . '.pdf';
        $filepath = trailingslashit( $target_dir ) . $filename;
        $pdf->Output( 'F', $filepath );

        $fileurl = trailingslashit( $upload_dir['baseurl'] ) . 'th-songbook/' . $filename;
        $meta    = array(
            'path'         => $filepath,
            'url'          => $fileurl,
            'filename'     => $filename,
            'generated_at' => current_time( 'timestamp', true ),
        );

        update_post_meta( $gig_id, '_th_songbook_gig_pdf', $meta );

        return array(
            'url'         => $fileurl,
            'path'        => $filepath,
            'filename'    => $filename,
            'generatedAt' => $meta['generated_at'],
        );
    }

    /**
     * Fetch stored PDF meta.
     *
     * @param int $gig_id Gig ID.
     *
     * @return array<string, mixed>|null
     */
    public function get_gig_pdf_meta( $gig_id ) {
        $meta = get_post_meta( $gig_id, '_th_songbook_gig_pdf', true );
        if ( ! is_array( $meta ) || empty( $meta['path'] ) || empty( $meta['url'] ) ) {
            return null;
        }

        $meta['generated_at'] = isset( $meta['generated_at'] ) ? (int) $meta['generated_at'] : 0;
        $meta['filename']     = isset( $meta['filename'] ) ? (string) $meta['filename'] : basename( $meta['path'] );

        return $meta;
    }

    /**
     * Retrieve a normalized snapshot of gig details for PDF/email usage.
     *
     * @param int $gig_id Gig ID.
     *
     * @return array<string, mixed>
     */
    private function get_gig_snapshot( $gig_id ) {
        $gig = get_post( $gig_id );

        $sets    = array();
        $set_map = $this->plugin->post_types->get_gig_setlists( $gig_id );
        $encores = $this->plugin->post_types->get_gig_encores( $gig_id );
        $safes   = $this->plugin->post_types->get_gig_safes( $gig_id );

        $ordered_set_keys = array_keys( $set_map );
        usort( $ordered_set_keys, function( $a, $b ) {
            $na = (int) preg_replace( '/[^\d]/', '', $a );
            $nb = (int) preg_replace( '/[^\d]/', '', $b );
            return $na <=> $nb;
        } );

        foreach ( $ordered_set_keys as $set_key ) {
            $song_ids   = isset( $set_map[ $set_key ] ) ? (array) $set_map[ $set_key ] : array();
            $safe_ids   = isset( $safes[ $set_key ] ) ? (array) $safes[ $set_key ] : array();
            $encore_ids = isset( $encores[ $set_key ] ) ? (array) $encores[ $set_key ] : array();

            $songs = array();
            foreach ( $song_ids as $id ) {
                $song = $this->plugin->post_types->get_song_display_data( $id );
                if ( ! empty( $song['missing'] ) ) {
                    continue;
                }

                $songs[] = array(
                    'id'      => $song['id'],
                    'title'   => $song['title'],
                    'is_safe' => false,
                );
            }

            foreach ( $safe_ids as $safe_id ) {
                $song = $this->plugin->post_types->get_song_display_data( $safe_id );
                if ( ! empty( $song['missing'] ) ) {
                    continue;
                }

                $songs[] = array(
                    'id'      => $song['id'],
                    'title'   => $song['title'],
                    'is_safe' => true,
                );
            }

            $encore_items = array();
            foreach ( $encore_ids as $encore_id ) {
                $song = $this->plugin->post_types->get_song_display_data( $encore_id );
                if ( ! empty( $song['missing'] ) ) {
                    continue;
                }

                $encore_items[] = array(
                    'id'    => $song['id'],
                    'title' => $song['title'],
                );
            }

            if ( ! empty( $songs ) || ! empty( $encore_items ) ) {
                $sets[] = array(
                    'songs'   => $songs,
                    'encores' => $encore_items,
                );
            }
        }

        $date = get_post_meta( $gig_id, 'th_gig_date', true );
        $time = get_post_meta( $gig_id, 'th_gig_start_time', true );

        $date_label = '';
        $time_label = '';

        if ( $date ) {
            $timestamp = strtotime( $date );
            if ( $timestamp ) {
                $date_label = date_i18n( get_option( 'date_format' ), $timestamp );
            }
        }

        if ( $time ) {
            $reference = $date ? $date . ' ' . $time : '1970-01-01 ' . $time;
            $timestamp = strtotime( $reference );
            if ( $timestamp ) {
                $time_label = date_i18n( get_option( 'time_format' ), $timestamp );
            }
        }

        $venue = get_post_meta( $gig_id, 'th_gig_venue', true );

        return array(
            'title'      => $gig ? get_the_title( $gig ) : __( 'Gig', 'th-songbook' ),
            'date_label' => $date_label,
            'time_label' => $time_label,
            'venue'      => $venue,
            'date_raw'   => $date,
            'sets'       => $sets,
        );
    }

    /**
     * Prepare email payload for wp_mail.
     *
     * @param int   $gig_id     Gig ID.
     * @param array $recipients Recipient entries.
     * @param array $pdf_meta   PDF meta data.
     *
     * @return array<string, mixed>|WP_Error
     */
    private function prepare_email_payload( $gig_id, $recipients, $pdf_meta ) {
        $gig_data = $this->get_gig_snapshot( $gig_id );

        if ( empty( $gig_data['sets'] ) ) {
            return new WP_Error( 'no_songs', __( 'No songs found for this gig.', 'th-songbook' ) );
        }

        $to = array();
        foreach ( $recipients as $recipient ) {
            if ( empty( $recipient['email'] ) ) {
                continue;
            }

            $to[] = $recipient['email'];
        }

        if ( empty( $to ) ) {
            return new WP_Error( 'no_email', __( 'No valid recipient email addresses were found.', 'th-songbook' ) );
        }

        $subject = sprintf(
            __( 'Sætliste - %s', 'th-songbook' ),
            $gig_data['date_label'] ? $gig_data['date_label'] : get_post_meta( $gig_id, 'th_gig_date', true )
        );

        $lines   = array();
        $lines[] = $gig_data['title'];
        if ( $gig_data['date_label'] ) {
            $lines[] = sprintf( __( 'Date: %s', 'th-songbook' ), $gig_data['date_label'] );
        }
        if ( $gig_data['time_label'] ) {
            $lines[] = sprintf( __( 'Start: %s', 'th-songbook' ), $gig_data['time_label'] );
        }
        if ( $gig_data['venue'] ) {
            $lines[] = sprintf( __( 'Venue: %s', 'th-songbook' ), $gig_data['venue'] );
        }

        $lines[] = '';
        $set_number = 0;
        foreach ( $gig_data['sets'] as $set ) {
            $set_number++;
            $lines[] = sprintf( __( '%d. Set', 'th-songbook' ), $set_number );
            $lines[] = str_repeat( '-', 24 );

            $counter = 1;
            foreach ( $set['songs'] as $song ) {
                $label = $counter . '. ' . $song['title'];
                if ( $song['is_safe'] ) {
                    $label .= ' (' . __( 'SAFE', 'th-songbook' ) . ')';
                }
                $lines[] = $label;
                $counter++;
            }

            if ( ! empty( $set['encores'] ) ) {
                $lines[] = '';
                $lines[] = __( 'Encores', 'th-songbook' );
                foreach ( $set['encores'] as $encore ) {
                    $lines[] = $encore['title'];
                }
            }

            $lines[] = '';
        }

        $body = implode( "\n", $lines );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . sprintf( '%s <%s>', 'Thera Hoeijmans', 'web@sevenyellowmonkeys.dk' ),
        );

        return array(
            'to'          => $to,
            'subject'     => $subject,
            'body'        => $body,
            'headers'     => $headers,
            'attachments' => array( $pdf_meta['path'] ),
        );
    }

    /**
     * Encode text for FPDF (expects ISO-8859-1).
     *
     * @param string $text Input text.
     *
     * @return string
     */
    private function encode_pdf_text( $text ) {
        $text = (string) $text;

        if ( function_exists( 'iconv' ) ) {
            $converted = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $text );
            if ( false !== $converted ) {
                return $converted;
            }
        }

        return utf8_decode( $text );
    }

    /**
     * Format timestamp for display within the admin UI.
     *
     * @param int $timestamp UTC timestamp.
     *
     * @return string
     */
    private function format_timestamp_label( $timestamp ) {
        if ( ! $timestamp ) {
            return '';
        }

        $local = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), 'Y-m-d H:i:s' );
        $time  = strtotime( $local );
        if ( false === $time ) {
            return gmdate( 'Y-m-d H:i', $timestamp );
        }

        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );
    }
}
