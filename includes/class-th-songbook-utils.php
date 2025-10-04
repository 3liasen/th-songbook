<?php
/**
 * Utility helpers for the TH Songbook plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TH_Songbook_Utils {
    /**
     * Update or remove a meta value based on contents.
     *
     * @param int    $post_id  Post ID.
     * @param string $meta_key Meta key.
     * @param mixed  $value    Sanitized value.
     */
    public static function update_meta_value( $post_id, $meta_key, $value ) {
        if ( '' === $value || ( is_array( $value ) && empty( $value ) ) ) {
            delete_post_meta( $post_id, $meta_key );
            return;
        }

        update_post_meta( $post_id, $meta_key, $value );
    }

    /**
     * Sanitize a song duration value (mm:ss).
     *
     * @param string $value Raw duration.
     *
     * @return string Sanitized value or empty string.
     */
    public static function sanitize_song_duration_value( $value ) {
        $seconds = self::parse_duration_to_seconds( $value );

        if ( null === $seconds ) {
            return '';
        }

        return self::format_seconds_to_duration( $seconds );
    }

    /**
     * Convert duration string to total seconds.
     *
     * @param string $value Duration string.
     *
     * @return int|null Total seconds or null if invalid.
     */
    public static function parse_duration_to_seconds( $value ) {
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
     * Format seconds to mm:ss.
     *
     * @param int $seconds Total seconds.
     *
     * @return string
     */
    public static function format_seconds_to_duration( $seconds ) {
        $seconds = (int) max( 0, $seconds );
        $minutes = (int) floor( $seconds / 60 );
        $remaining = $seconds % 60;

        return sprintf( '%02d:%02d', $minutes, $remaining );
    }

    /**
     * Calculate total duration for songs.
     *
     * @param array<int, array<string, mixed>> $songs Songs list.
     *
     * @return string Total duration formatted as mm:ss.
     */
    public static function calculate_set_total_duration( array $songs ) {
        $total_seconds = 0;

        foreach ( $songs as $song ) {
            if ( empty( $song['duration'] ) ) {
                continue;
            }

            $seconds = self::parse_duration_to_seconds( $song['duration'] );

            if ( null !== $seconds ) {
                $total_seconds += $seconds;
            }
        }

        return self::format_seconds_to_duration( $total_seconds );
    }

    /**
     * Sanitize time value (hh:mm 24-hour).
     *
     * @param string $value Raw input.
     *
     * @return string Sanitized value or empty string.
     */
    public static function sanitize_time_value( $value ) {
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
     * Sanitize a date value (YYYY-MM-DD).
     *
     * @param string $value Raw input.
     *
     * @return string Sanitized value or empty string.
     */
    public static function sanitize_date_value( $value ) {
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
