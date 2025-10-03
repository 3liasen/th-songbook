(function( $ ) {
    'use strict';

    var invalidTimeMessage = '';
    if ( window.thSongbookGig && window.thSongbookGig.i18n && window.thSongbookGig.i18n.invalidTime ) {
        invalidTimeMessage = window.thSongbookGig.i18n.invalidTime;
    }

    var timePattern = /^(?:[01]\d|2[0-3]):[0-5]\d$/;

    function validateTimeField( field ) {
        var value = field.value.trim();

        if ( '' === value || timePattern.test( value ) ) {
            field.setCustomValidity( '' );
        } else {
            field.setCustomValidity( invalidTimeMessage );
        }
    }

    $( function() {
        var $timeFields = $( '.th-songbook-time-field' );

        if ( ! $timeFields.length ) {
            return;
        }

        $timeFields.on( 'input', function() {
            this.setCustomValidity( '' );
        } );

        $timeFields.on( 'blur', function() {
            validateTimeField( this );
        } );

        $( '#post' ).on( 'submit', function() {
            $timeFields.each( function() {
                validateTimeField( this );
            } );
        } );
    } );
})( jQuery );
