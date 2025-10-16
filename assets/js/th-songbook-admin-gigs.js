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

    function getInBetweenSeconds() {
        var $field = $( '#th_gig_in_between' );
        if ( ! $field.length ) {
            return 0;
        }

        var parsed = parseDuration( $field.val() );
        return parsed !== null ? parsed : 0;
    }

    function getSelectedSongIds( scope ) {
        var ids = [];
        var $items;

        if ( scope && scope.length ) {
            $items = scope.find( '.th-songbook-song-list__item' );
        } else {
            $items = $( '.th-songbook-song-manager .th-songbook-song-list__item' );
        }

        $items.each( function() {
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
        var $setlistContainer = $manager.closest( '.th-songbook-setlist' );
        var $totalTarget = $setlistContainer.find( '[data-th-songbook-set-total]' );
        var emptyMessage = $manager.data( 'emptyMessage' ) || i18n.noSongsAssigned || '';

        if ( $searchInput.length ) {
            $searchInput.attr( 'placeholder', i18n.searchPlaceholder || $searchInput.attr( 'placeholder' ) || '' );

            if ( ! songs.length ) {
                $searchInput.prop( 'disabled', true );
            }
        }

        function updateTotal() {
            var totalSeconds = 0;

            $setlistContainer.find( '.th-songbook-song-list__item' ).each( function() {
                var duration = $( this ).data( 'song-duration' );
                var seconds = parseDuration( duration );

                if ( Number.isFinite( seconds ) ) {
                    totalSeconds += seconds;
                }
            } );

            var inBetweenSeconds = getInBetweenSeconds();
            if ( inBetweenSeconds > 0 ) {
                var itemCount = $setlistContainer.find( '.th-songbook-song-list__item' ).length;
                if ( itemCount > 1 ) {
                    totalSeconds += ( itemCount - 1 ) * inBetweenSeconds;
                }
            }

            if ( $totalTarget.length ) {
                $totalTarget.text( formatDuration( totalSeconds ) );
            }
        }

        function ensureEmptyState() {
            if ( $list.children().length === 0 ) {
                if ( ! $emptyState.length ) {
                    $emptyState = $( '<p/>' )
                        .addClass( 'description th-songbook-song-list__empty' )
                        .text( emptyMessage )
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
            var selected = getSelectedSongIds( $list );
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

            var selected = getSelectedSongIds( $list );
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

        $( document ).on( 'thSongbookInBetweenUpdated', updateTotal );
    }
    function validateTimeField( field ) {
        var value = field.value.trim();

        if ( value === '' || timePattern.test( value ) ) {
            field.setCustomValidity( '' );
        } else {
            field.setCustomValidity( invalidTimeMessage );
        }
    }

    function initGigTools( config ) {
        var container = document.querySelector( '[data-th-songbook-gig-tools]' );
        if ( ! container ) {
            return;
        }

        var generateButton = container.querySelector( '[data-gig-tools-generate]' );
        var statusEl = container.querySelector( '[data-gig-tools-status]' );
        var actionsEl = container.querySelector( '[data-gig-tools-actions]' );
        var printButton = container.querySelector( '[data-gig-tools-print]' );
        var emailButton = container.querySelector( '[data-gig-tools-email]' );
        var infoEl = container.querySelector( '.th-songbook-gig-tools__info' );

        var strings = $.extend( {
            creating: 'Creating PDF…',
            created: 'PDF created successfully.',
            errorGeneric: 'Something went wrong. Please try again.',
            printing: 'Opening PDF for printing…',
            noRecipients: 'No recipients available. Create one first.',
            selectRecipients: 'Select at least one recipient.',
            sending: 'Sending email…',
            sent: 'Email sent successfully.',
            lastGenerated: 'Last generated: %s',
            modalTitle: 'Send set list',
            cancelLabel: 'Cancel',
            sendEmailLabel: 'Send Email'
        }, config.strings || {} );

        var state = {
            gigId: config.gigId || 0,
            enabled: !! config.enabled,
            pdf: config.pdf || null,
            nonces: config.nonces || {},
            ajaxUrl: config.ajaxUrl || ( window.ajaxurl || '' ),
            recipientsCache: null,
            recipientsLoading: null
        };

        function setStatus( message, type ) {
            if ( ! statusEl ) {
                return;
            }

            statusEl.textContent = message || '';
            statusEl.classList.remove( 'is-error', 'is-success' );
            if ( type ) {
                statusEl.classList.add( type );
            }
        }

        function toggleActions( visible ) {
            if ( ! actionsEl ) {
                return;
            }

            if ( visible ) {
                actionsEl.removeAttribute( 'hidden' );
            } else {
                actionsEl.setAttribute( 'hidden', 'hidden' );
            }
        }

        function formatTimestamp( timestamp ) {
            if ( ! timestamp ) {
                return '';
            }

            var date = new Date( timestamp * 1000 );
            if ( Number.isNaN( date.getTime() ) ) {
                return '';
            }

            return date.toLocaleString( undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            } );
        }

        function updatePdfState( pdf ) {
            state.pdf = pdf;
            if ( pdf ) {
                toggleActions( true );
                if ( ! infoEl ) {
                    infoEl = document.createElement( 'p' );
                    infoEl.className = 'th-songbook-gig-tools__info';
                    container.appendChild( infoEl );
                }
                var formatted = formatTimestamp( pdf.generatedAt );
                infoEl.textContent = strings.lastGenerated.replace( '%s', formatted || '' );
            }
        }

        function ensureEnabled() {
            if ( ! state.enabled || ! state.gigId ) {
                if ( generateButton ) {
                    generateButton.disabled = true;
                }
                setStatus( '', '' );
                toggleActions( !! state.pdf );
                return false;
            }

            return true;
        }

        function requestPdf() {
            if ( ! ensureEnabled() || ! generateButton ) {
                return;
            }

            setStatus( strings.creating, '' );
            generateButton.disabled = true;
            generateButton.classList.add( 'is-busy' );

            $.ajax( {
                url: state.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'th_songbook_generate_gig_pdf',
                    nonce: state.nonces ? state.nonces.generate : '',
                    gigId: state.gigId
                }
            } ).done( function( response ) {
                if ( response && response.success && response.data && response.data.pdf ) {
                    updatePdfState( response.data.pdf );
                    setStatus( strings.created, 'is-success' );
                } else {
                    setStatus( response && response.data && response.data.message ? response.data.message : strings.errorGeneric, 'is-error' );
                }
            } ).fail( function() {
                setStatus( strings.errorGeneric, 'is-error' );
            } ).always( function() {
                generateButton.disabled = false;
                generateButton.classList.remove( 'is-busy' );
            } );
        }

        function openPrint() {
            if ( ! state.pdf || ! state.pdf.url ) {
                setStatus( strings.errorGeneric, 'is-error' );
                return;
            }

            setStatus( strings.printing, '' );
            var win = window.open( state.pdf.url, '_blank' );
            if ( ! win ) {
                setStatus( strings.errorGeneric, 'is-error' );
                return;
            }
            win.focus();
            var triggerPrint = function() {
                try {
                    win.print();
                } catch ( e ) {}
            };
            if ( win.addEventListener ) {
                win.addEventListener( 'load', triggerPrint );
            } else {
                win.onload = triggerPrint;
            }
        }

        function fetchRecipients() {
            if ( state.recipientsCache ) {
                return $.Deferred().resolve( state.recipientsCache ).promise();
            }

            if ( state.recipientsLoading ) {
                return state.recipientsLoading;
            }

            state.recipientsLoading = $.ajax( {
                url: state.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'th_songbook_list_recipients',
                    nonce: state.nonces ? state.nonces.recipients : ''
                }
            } ).then( function( response ) {
                if ( response && response.success && response.data ) {
                    state.recipientsCache = response.data.recipients || [];
                    return state.recipientsCache;
                }

                return $.Deferred().reject( response && response.data && response.data.message ? response.data.message : strings.errorGeneric ).promise();
            }, function() {
                return $.Deferred().reject( strings.errorGeneric ).promise();
            } ).always( function() {
                state.recipientsLoading = null;
            } );

            return state.recipientsLoading;
        }

        var modalElements = null;

        function ensureModal() {
            if ( modalElements ) {
                return modalElements;
            }

            var overlay = document.createElement( 'div' );
            overlay.className = 'th-songbook-recipient-modal';

            var dialog = document.createElement( 'div' );
            dialog.className = 'th-songbook-recipient-modal__dialog';

            var closeButton = document.createElement( 'button' );
            closeButton.className = 'th-songbook-recipient-modal__close';
            closeButton.setAttribute( 'type', 'button' );
            closeButton.setAttribute( 'aria-label', 'Close' );
            closeButton.textContent = '×';

            var title = document.createElement( 'h2' );
            title.className = 'th-songbook-recipient-modal__title';
            title.textContent = strings.modalTitle;

            var status = document.createElement( 'p' );
            status.className = 'th-songbook-recipient-modal__status';

            var list = document.createElement( 'ul' );
            list.className = 'th-songbook-recipient-modal__list';

            var empty = document.createElement( 'p' );
            empty.className = 'th-songbook-recipient-modal__empty';
            empty.textContent = strings.noRecipients;
            empty.style.display = 'none';

            var actions = document.createElement( 'div' );
            actions.className = 'th-songbook-recipient-modal__actions';

            var cancelBtn = document.createElement( 'button' );
            cancelBtn.type = 'button';
            cancelBtn.className = 'button';
            cancelBtn.textContent = ( window.wp && window.wp.i18n ) ? window.wp.i18n.__( 'Cancel', 'th-songbook' ) : strings.cancelLabel;

            var sendBtn = document.createElement( 'button' );
            sendBtn.type = 'button';
            sendBtn.className = 'button button-primary';
            sendBtn.textContent = ( window.wp && window.wp.i18n ) ? window.wp.i18n.__( 'Send Email', 'th-songbook' ) : strings.sendEmailLabel;

            actions.appendChild( cancelBtn );
            actions.appendChild( sendBtn );

            dialog.appendChild( closeButton );
            dialog.appendChild( title );
            dialog.appendChild( status );
            dialog.appendChild( empty );
            dialog.appendChild( list );
            dialog.appendChild( actions );
            overlay.appendChild( dialog );

            modalElements = {
                overlay: overlay,
                dialog: dialog,
                status: status,
                list: list,
                empty: empty,
                sendBtn: sendBtn,
                cancelBtn: cancelBtn,
                closeBtn: closeButton
            };

            closeButton.addEventListener( 'click', closeModal );
            cancelBtn.addEventListener( 'click', closeModal );

            overlay.addEventListener( 'click', function( event ) {
                if ( event.target === overlay ) {
                    closeModal();
                }
            } );

            sendBtn.addEventListener( 'click', submitEmail );

            return modalElements;
        }

        function openModal() {
            if ( ! ensureEnabled() ) {
                return;
            }

            var elements = ensureModal();
            document.body.appendChild( elements.overlay );
            setModalStatus( '' );
            elements.sendBtn.disabled = true;
            elements.list.innerHTML = '';
            elements.empty.style.display = 'none';

            fetchRecipients().done( function( recipients ) {
                if ( ! recipients || ! recipients.length ) {
                    elements.empty.style.display = '';
                    elements.sendBtn.disabled = true;
                    setModalStatus( strings.noRecipients, 'is-error' );
                    return;
                }

                elements.list.innerHTML = '';
                recipients.forEach( function( recipient ) {
                    var li = document.createElement( 'li' );
                    li.className = 'th-songbook-recipient-modal__item';

                    var label = document.createElement( 'label' );
                    label.style.display = 'flex';
                    label.style.alignItems = 'center';
                    label.style.gap = '0.5rem';

                    var checkbox = document.createElement( 'input' );
                    checkbox.type = 'checkbox';
                    checkbox.value = recipient.id;

                    var nameSpan = document.createElement( 'span' );
                    nameSpan.textContent = recipient.name + ' (' + recipient.email + ')';

                    label.appendChild( checkbox );
                    label.appendChild( nameSpan );
                    li.appendChild( label );
                    elements.list.appendChild( li );
                } );

                elements.sendBtn.disabled = false;
                setModalStatus( '' );
            } ).fail( function( message ) {
                setModalStatus( message || strings.errorGeneric, 'is-error' );
                if ( modalElements ) {
                    modalElements.sendBtn.disabled = true;
                }
            } );
        }

        function closeModal() {
            if ( modalElements && modalElements.overlay.parentNode ) {
                modalElements.overlay.parentNode.removeChild( modalElements.overlay );
            }
        }

        function setModalStatus( message, type ) {
            if ( ! modalElements ) {
                return;
            }
            modalElements.status.textContent = message || '';
            modalElements.status.classList.remove( 'is-error', 'is-success' );
            if ( type ) {
                modalElements.status.classList.add( type );
            }
        }

        function submitEmail() {
            if ( ! modalElements || ! state.pdf ) {
                setModalStatus( strings.errorGeneric, 'is-error' );
                return;
            }

            var checkboxes = modalElements.list.querySelectorAll( 'input[type="checkbox"]:checked' );
            if ( ! checkboxes.length ) {
                setModalStatus( strings.selectRecipients, 'is-error' );
                return;
            }

            var recipients = Array.prototype.map.call( checkboxes, function( checkbox ) {
                return checkbox.value;
            } );

            modalElements.sendBtn.disabled = true;
            setModalStatus( strings.sending, '' );

            $.ajax( {
                url: state.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'th_songbook_send_gig_email',
                    nonce: state.nonces ? state.nonces.email : '',
                    gigId: state.gigId,
                    recipients: recipients
                }
            } ).done( function( response ) {
                if ( response && response.success ) {
                    setModalStatus( strings.sent, 'is-success' );
                    setTimeout( closeModal, 1500 );
                } else {
                    setModalStatus( response && response.data && response.data.message ? response.data.message : strings.errorGeneric, 'is-error' );
                }
            } ).fail( function() {
                setModalStatus( strings.errorGeneric, 'is-error' );
            } ).always( function() {
                modalElements.sendBtn.disabled = false;
            } );
        }

        if ( generateButton ) {
            generateButton.addEventListener( 'click', requestPdf );
        }

        if ( printButton ) {
            printButton.addEventListener( 'click', openPrint );
        }

        if ( emailButton ) {
            emailButton.addEventListener( 'click', openModal );
        }

        if ( state.pdf ) {
            updatePdfState( state.pdf );
        } else {
            toggleActions( false );
        }

        ensureEnabled();
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

        var $inBetweenField = $( '#th_gig_in_between' );
        if ( $inBetweenField.length ) {
            $inBetweenField.on( 'input change', function() {
                $( document ).trigger( 'thSongbookInBetweenUpdated' );
            } );
        }

        $( '.th-songbook-song-manager' ).each( function() {
            initSongManager( $( this ), songs, i18n, songIndex );
        } );

        if ( config.tools ) {
            initGigTools( config.tools );
        }
    } );
})( jQuery );
