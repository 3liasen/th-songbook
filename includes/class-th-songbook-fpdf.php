<?php
/**
 * Custom FPDF wrapper for TH Songbook.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once TH_SONGBOOK_PLUGIN_DIR . 'includes/lib/fpdf.php';

if ( ! class_exists( 'TH_Songbook_FPDF' ) ) {
    /**
     * FPDF subclass that throws exceptions to allow graceful handling.
     */
    class TH_Songbook_FPDF extends FPDF {
        /**
         * @inheritDoc
         */
        public function Error( $msg ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
            throw new \RuntimeException( $msg );
        }
    }
}
