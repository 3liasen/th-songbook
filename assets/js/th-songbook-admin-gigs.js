(function( $ ) {
    'use strict';

    var i18nDefaults = {
        searchPlaceholder: 'Search songs...',
        noMatches: 'No matching songs found.',
        removeSong: 'Remove',
        invalidTime: 'Please enter a valid time in 24-hour format (hh:mm).',
        noSongsAssigned: 'No songs assigned yet.',
        dragHandle: 'Drag to reorder',
        noDuration: '--:--'
    };

    var invalidTimeMessage = '';
    var timePattern = /^(?:[01]\d|2[0-3]):[0-5]\d$/;

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
                    title: song.title || '',
                    duration: song.duration || ''
                };
            } )
            .filter( Boolean );
    }

    function buildSongIndex( songs ) {
        var index = {};

        songs.forEach( function( song ) {
            index[ song.id ] = song;
        } );

        return index;
    }

    function parseDuration( value ) {
        if ( value === null || value === undefined ) {
            return null;
        }

        var raw = typeof value === 'string' ? value.trim() : String( value ).trim();
        var match = raw.match( /^(\d+):([0-5]\d)$/ );

        if ( ! match ) {
            return null;
        }

        var minutes = parseInt( match[1], 10 );
        var seconds = parseInt( match[2], 10 );

        if ( Number.isNaN( minutes ) || Number.isNaN( seconds ) ) {
            return null;
        }

        return ( minutes * 60 ) + seconds;
    }

    function formatDuration( seconds ) {
        if ( ! Number.isFinite( seconds ) || seconds <= 0 ) {
            return '00:00';
        }

        var minutes = Math.floor( seconds / 60 );
        var remainder = seconds % 60;
        var minutesText = minutes < 10 ? '0' + minutes : String( minutes );
        var secondsText = remainder < 10 ? '0' + remainder : String( remainder );

        return minutesText + ':' + secondsText;
    }

    function getSelectedSongIds() {
        var ids = [];

        $( '.th-songbook-song-manager .th-songbook-song-list__item' ).each( function() {
            var value = parseInt( $( this ).data( 'song-id' ), 10 );

            if ( Number.isNaN( value ) || ids.indexOf( value ) !== -1 ) {
                return;
            }

            ids.push( value );
        } );

        return ids;
    }
    function initSongManager( $manager, songs, i18n, songIndex ) {
        var $searchInput = $manager.find( '.th-songbook-song-search__input' );
        var $results = $manager.find( '.th-songbook-song-search__results' );
        var $list = $manager.find( '.th-songbook-song-list' );
        var $emptyState = $manager.find( '.th-songbook-song-list__empty' );
        var searchEnabled = $searchInput.length && songs.length;
        var sortableEnabled = false;
        var fieldName = $manager.data( 'field-name' ) || 'th_gig_songs[]';
        var noDurationPlaceholder = i18n.noDuration || '--:--';
        var $totalTarget = $manager.closest( '.th-songbook-setlist' ).find( '[data-th-songbook-set-total]' );

        if ( $searchInput.length ) {
            $searchInput.attr( 'placeholder', i18n.searchPlaceholder || $searchInput.attr( 'placeholder' ) || '' );

            if ( ! songs.length ) {
                $searchInput.prop( 'disabled', true );
            }
        }

        function updateTotal() {
            var totalSeconds = 0;

            $list.children( '.th-songbook-song-list__item' ).each( function() {
                var duration = $( this ).data( 'song-duration' );
                var seconds = parseDuration( duration );

                if ( Number.isFinite( seconds ) ) {
                    totalSeconds += seconds;
                }
            } );

            if ( $totalTarget.length ) {
                $totalTarget.text( formatDuration( totalSeconds ) );
            }
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

        function setupSortable() {
            if ( sortableEnabled || ! $.fn.sortable ) {
                return;
            }

            $list.sortable( {
                axis: 'y',
                handle: '.th-songbook-song-list__handle',
                placeholder: 'th-songbook-song-list__item th-songbook-song-list__item--placeholder',
                forcePlaceholderSize: true,
                tolerance: 'pointer',
                start: function() {
                    clearResults();
                },
                update: function() {
                    ensureEmptyState();
                    updateTotal();
                }
            } );

            sortableEnabled = true;
        }

        function buildMatches( query ) {
            var lowered = query.toLowerCase();
            var selected = getSelectedSongIds();
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
                    var label = song.title;

                    if ( song.duration ) {
                        label += ' (' + song.duration + ')';
                    }

                    $( '<li/>' )
                        .addClass( 'th-songbook-song-search__result' )
                        .attr( {
                            'data-song-id': song.id,
                            'data-song-title': song.title,
                            'data-song-duration': song.duration || '',
                            role: 'option',
                            tabindex: 0
                        } )
                        .text( label )
                        .appendTo( $results );
                } );
            }

            $results.addClass( 'is-visible' );
        }

        function addSong( id, title, duration ) {
            if ( ! id || ! title ) {
                return;
            }

            var selected = getSelectedSongIds();
            if ( selected.indexOf( id ) !== -1 ) {
                return;
            }

            var songMeta = songIndex[ id ] || null;
            var resolvedDuration = duration || ( songMeta && songMeta.duration ) || '';

            var $item = $( '<li/>' )
                .addClass( 'th-songbook-song-list__item' )
                .attr( {
                    'data-song-id': id,
                    'data-song-duration': resolvedDuration
                } );

            $( '<span/>' )
                .addClass( 'th-songbook-song-list__handle dashicons dashicons-move' )
                .attr( {
                    'aria-hidden': 'true',
                    title: i18n.dragHandle || ''
                } )
                .appendTo( $item );

            $( '<span/>' )
                .addClass( 'th-songbook-song-list__title' )
                .text( title )
                .appendTo( $item );

            $( '<span/>' )
                .addClass( 'th-songbook-song-list__duration' )
                .text( resolvedDuration || noDurationPlaceholder )
                .appendTo( $item );

            $( '<button/>' )
                .attr( 'type', 'button' )
                .addClass( 'button-link th-songbook-remove-song' )
                .text( i18n.removeSong || 'Remove' )
                .appendTo( $item );

            $( '<input/>' )
                .attr( {
                    type: 'hidden',
                    name: fieldName,
                    value: id
                } )
                .appendTo( $item );

            $list.append( $item );
            setupSortable();

            if ( sortableEnabled ) {
                $list.sortable( 'refresh' );
            }

            ensureEmptyState();
            updateTotal();
        }

        function handleSelection( $choice ) {
            if ( ! $choice.length ) {
                return;
            }

            var id = parseInt( $choice.data( 'song-id' ), 10 );
            var title = $choice.data( 'song-title' ) || $choice.text();
            var duration = $choice.data( 'song-duration' ) || '';

            if ( Number.isNaN( id ) ) {
                return;
            }

            addSong( id, title, duration );
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
            updateTotal();

            if ( sortableEnabled ) {
                $list.sortable( 'refresh' );
            }
        } );

        ensureEmptyState();
        updateTotal();
        setupSortable();
    }
    function validateTimeField( field ) {
        var value = field.value.trim();

        if ( value === '' || timePattern.test( value ) ) {
            field.setCustomValidity( '' );
        } else {
            field.setCustomValidity( invalidTimeMessage );
        }
    }

    $( function() {
        var config = window.thSongbookGig || {};
        var songs = normaliseSongs( config.songs );
        var songIndex = buildSongIndex( songs );
        var i18n = $.extend( {}, i18nDefaults, config.i18n || {} );

        invalidTimeMessage = i18n.invalidTime || '';

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
            initSongManager( $( this ), songs, i18n, songIndex );
        } );
    } );
})( jQuery );
