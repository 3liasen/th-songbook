(function( $ ) {
    'use strict';

    function normaliseSongs( rawSongs ) {
        if ( ! Array.isArray( rawSongs ) ) {
            return [];
        }

        return rawSongs
            .map( function( song ) {
                var candidate = song.id !== undefined ? song.id : song.ID;
                var id = parseInt( candidate, 10 );

                if ( Number.isNaN( id ) ) {
                    return null;
                }

                return {
                    id: id,
                    title: song.title || ''
                };
            } )
            .filter( Boolean );
    }

    function initSongManager( $manager, songs, i18n ) {
        var $searchInput = $manager.find( '.th-songbook-song-search__input' );
        var $results = $manager.find( '.th-songbook-song-search__results' );
        var $list = $manager.find( '.th-songbook-song-list' );
        var $emptyState = $manager.find( '.th-songbook-song-list__empty' );
        var searchEnabled = $searchInput.length && songs.length;

        if ( $searchInput.length ) {
            $searchInput.attr( 'placeholder', i18n.searchPlaceholder || $searchInput.attr( 'placeholder' ) || '' );

            if ( ! songs.length ) {
                $searchInput.prop( 'disabled', true );
            }
        }

        function getSelectedIds() {
            return $list.find( 'input[name="th_gig_songs[]"]' ).map( function() {
                var value = parseInt( this.value, 10 );
                return Number.isNaN( value ) ? null : value;
            } ).get().filter( function( value ) {
                return value !== null;
            } );
        }

        function ensureEmptyState() {
            if ( $list.children().length === 0 ) {
                if ( ! $emptyState.length ) {
                    $emptyState = $( '<p/>' )
                        .addClass( 'description th-songbook-song-list__empty' )
                        .text( i18n.noSongsAssigned || '' )
                        .appendTo( $manager );
                }
            } else if ( $emptyState.length ) {
                $emptyState.remove();
                $emptyState = $( [] );
            }
        }

        function buildMatches( query ) {
            var lowered = query.toLowerCase();
            var selected = getSelectedIds();
            var matches = [];

            songs.forEach( function( song ) {
                if ( selected.indexOf( song.id ) !== -1 ) {
                    return;
                }

                if ( song.title && song.title.toLowerCase().indexOf( lowered ) !== -1 ) {
                    matches.push( song );
                }
            } );

            return matches.slice( 0, 8 );
        }

        function clearResults() {
            $results.empty().removeClass( 'is-visible' );
        }

        function renderResults( matches ) {
            $results.empty();

            if ( ! matches.length ) {
                $( '<li/>' )
                    .addClass( 'th-songbook-song-search__no-results' )
                    .text( i18n.noMatches || '' )
                    .appendTo( $results );
            } else {
                matches.forEach( function( song ) {
                    $( '<li/>' )
                        .addClass( 'th-songbook-song-search__result' )
                        .attr( {
                            'data-song-id': song.id,
                            'data-song-title': song.title,
                            role: 'option',
                            tabindex: 0
                        } )
                        .text( song.title )
                        .appendTo( $results );
                } );
            }

            $results.addClass( 'is-visible' );
        }

        function addSong( id, title ) {
            if ( ! id || ! title ) {
                return;
            }

            var selected = getSelectedIds();
            if ( selected.indexOf( id ) !== -1 ) {
                return;
            }

            var $item = $( '<li/>' )
                .addClass( 'th-songbook-song-list__item' )
                .attr( 'data-song-id', id );

            $( '<span/>' )
                .addClass( 'th-songbook-song-list__title' )
                .text( title )
                .appendTo( $item );

            $( '<button/>' )
                .attr( 'type', 'button' )
                .addClass( 'button-link th-songbook-remove-song' )
                .text( i18n.removeSong || 'Remove' )
                .appendTo( $item );

            $( '<input/>' )
                .attr( {
                    type: 'hidden',
                    name: 'th_gig_songs[]',
                    value: id
                } )
                .appendTo( $item );

            $list.append( $item );
            ensureEmptyState();
        }

        function handleSelection( $choice ) {
            if ( ! $choice.length ) {
                return;
            }

            var id = parseInt( $choice.data( 'song-id' ), 10 );
            var title = $choice.data( 'song-title' ) || $choice.text();

            if ( Number.isNaN( id ) ) {
                return;
            }

            addSong( id, title );
            $searchInput.val( '' ).trigger( 'focus' );
            clearResults();
        }

        if ( searchEnabled ) {
            $searchInput.on( 'input', function() {
                var query = $( this ).val().trim();

                if ( query.length < 2 ) {
                    clearResults();
                    return;
                }

                renderResults( buildMatches( query ) );
            } );

            $searchInput.on( 'keydown', function( event ) {
                var key = event.key;

                if ( key === 'ArrowDown' || key === 'ArrowUp' ) {
                    event.preventDefault();

                    var $items = $results.find( '.th-songbook-song-search__result' );
                    if ( ! $items.length ) {
                        return;
                    }

                    var currentIndex = $items.index( $items.filter( '.is-highlighted' ) );

                    if ( key === 'ArrowDown' ) {
                        currentIndex = ( currentIndex + 1 ) % $items.length;
                    } else {
                        currentIndex = currentIndex <= 0 ? $items.length - 1 : currentIndex - 1;
                    }

                    $items.removeClass( 'is-highlighted' ).attr( 'aria-selected', 'false' );
                    $items.eq( currentIndex ).addClass( 'is-highlighted' ).attr( 'aria-selected', 'true' ).focus();
                } else if ( key === 'Enter' ) {
                    var $highlighted = $results.find( '.th-songbook-song-search__result.is-highlighted' ).first();
                    if ( $highlighted.length ) {
                        event.preventDefault();
                        handleSelection( $highlighted );
                    } else if ( $results.hasClass( 'is-visible' ) ) {
                        var $first = $results.find( '.th-songbook-song-search__result' ).first();
                        if ( $first.length ) {
                            event.preventDefault();
                            handleSelection( $first );
                        }
                    }
                } else if ( key === 'Escape' ) {
                    clearResults();
                }
            } );

            $results.on( 'mouseover focusin', '.th-songbook-song-search__result', function() {
                $( this )
                    .addClass( 'is-highlighted' )
                    .attr( 'aria-selected', 'true' )
                    .siblings()
                    .removeClass( 'is-highlighted' )
                    .attr( 'aria-selected', 'false' );
            } );

            $results.on( 'mousedown', '.th-songbook-song-search__result', function( event ) {
                event.preventDefault();
                handleSelection( $( this ) );
            } );

            $results.on( 'keydown', '.th-songbook-song-search__result', function( event ) {
                if ( event.key === 'Enter' || event.key === ' ' ) {
                    event.preventDefault();
                    handleSelection( $( this ) );
                } else if ( event.key === 'Escape' ) {
                    clearResults();
                    $searchInput.trigger( 'focus' );
                }
            } );

            $( document ).on( 'click', function( event ) {
                if ( ! $.contains( $results.get( 0 ), event.target ) && event.target !== $searchInput.get( 0 ) ) {
                    clearResults();
                }
            } );
        }

        $list.on( 'click', '.th-songbook-remove-song', function( event ) {
            event.preventDefault();
            $( this ).closest( '.th-songbook-song-list__item' ).remove();
            ensureEmptyState();
        } );

        ensureEmptyState();
    }

    var config = window.thSongbookGig || {};
    var songs = normaliseSongs( config.songs );
    var i18n = $.extend( {
        searchPlaceholder: 'Search songs...',
        noMatches: 'No matching songs found.',
        removeSong: 'Remove',
        invalidTime: 'Please enter a valid time in 24-hour format (hh:mm).',
        noSongsAssigned: 'No songs assigned yet.'
    }, config.i18n || {} );

    var invalidTimeMessage = i18n.invalidTime || '';
    var timePattern = /^(?:[01]\d|2[0-3]):[0-5]\d$/;

    function validateTimeField( field ) {
        var value = field.value.trim();

        if ( value === '' || timePattern.test( value ) ) {
            field.setCustomValidity( '' );
        } else {
            field.setCustomValidity( invalidTimeMessage );
        }
    }

    $( function() {
        var $timeFields = $( '.th-songbook-time-field' );

        if ( $timeFields.length ) {
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
        }

        $( '.th-songbook-song-manager' ).each( function() {
            initSongManager( $( this ), songs, i18n );
        } );
    } );
})( jQuery );
