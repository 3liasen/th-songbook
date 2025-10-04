(function() {
    'use strict';

    var data = window.thSongbookData || {};
    var gigItems = data.gigs && data.gigs.items ? data.gigs.items : {};
    var strings = data.strings || {};

    var listEl = document.querySelector('[data-songbook-gig-list]');
    var detailEl = document.querySelector('[data-songbook-gig-detail]');

    if ( ! listEl || ! detailEl ) {
        return;
    }

    var detailBody = detailEl.querySelector('[data-songbook-gig-detail-body]');

    if ( ! detailBody ) {
        return;
    }

    var state = {
        gigId: null,
        index: null
    };

    listEl.addEventListener('click', function(event) {
        var button = event.target.closest('[data-gig-trigger]');
        if ( ! button ) {
            return;
        }

        var gigId = button.getAttribute('data-gig-trigger');
        if ( ! gigItems[ gigId ] ) {
            return;
        }

        if ( state.gigId !== gigId ) {
            state.index = null;
        }

        state.gigId = gigId;
        updateSelectedGig();
        renderDetail();

        if ( typeof detailEl.scrollIntoView === 'function' ) {
            detailEl.scrollIntoView( { behavior: 'smooth', block: 'start' } );
        }
    });

    detailEl.addEventListener('click', function(event) {
        var songLink = event.target.closest('[data-songbook-song-link]');
        if ( songLink ) {
            var pointer = parseInt( songLink.getAttribute('data-songbook-song-link'), 10 );
            if ( ! Number.isNaN( pointer ) ) {
                state.index = pointer;
                renderDetail();
            }
            return;
        }

        var actionButton = event.target.closest('[data-songbook-action]');
        if ( ! actionButton || actionButton.disabled ) {
            return;
        }

        handleAction( actionButton.getAttribute('data-songbook-action') );
    });

    function updateSelectedGig() {
        var buttons = listEl.querySelectorAll('[data-gig-trigger]');
        Array.prototype.forEach.call( buttons, function( button ) {
            var isActive = button.getAttribute('data-gig-trigger') === state.gigId;
            button.classList.toggle( 'is-active', isActive );

            var item = button.closest('.th-songbook-gig-list__item');
            if ( item ) {
                item.classList.toggle( 'is-active', isActive );
            }
        } );
    }

    function handleAction( action ) {
        var gig = gigItems[ state.gigId ];
        if ( ! gig ) {
            return;
        }

        var order = Array.isArray( gig.order ) ? gig.order : [];

        switch ( action ) {
            case 'home':
                state.index = null;
                break;
            case 'prev':
                if ( state.index === null ) {
                    return;
                }

                if ( state.index <= 0 ) {
                    state.index = null;
                } else {
                    state.index -= 1;
                }
                break;
            case 'next':
                if ( order.length === 0 ) {
                    return;
                }

                if ( state.index === null ) {
                    state.index = 0;
                } else if ( state.index < order.length - 1 ) {
                    state.index += 1;
                }
                break;
            default:
                break;
        }

        renderDetail();
    }

    function renderDetail() {
        if ( ! state.gigId || ! gigItems[ state.gigId ] ) {
            detailBody.innerHTML = '<p class="th-songbook-gig-detail__placeholder">' + escapeHtml( strings.selectGigPrompt || 'Select a gig to view the set list.' ) + '</p>';
            detailEl.classList.remove( 'is-active' );
            detailEl.removeAttribute( 'data-current-gig' );
            return;
        }

        var gig = gigItems[ state.gigId ];
        detailEl.classList.add( 'is-active' );
        detailEl.setAttribute( 'data-current-gig', state.gigId );

        var isSongView = state.index !== null;
        var headerHtml = isSongView ? '' : renderHeader( gig );
        var metaHtml = isSongView ? '' : renderGigMeta( gig );
        var contentHtml = isSongView ? renderSongView( gig, state.index ) : renderHomeView( gig );
        var navHtml = renderNav( gig );

        detailBody.innerHTML = headerHtml + metaHtml + contentHtml + navHtml;
    }

    function renderHeader( gig ) {
        var parts = [];

        if ( gig.title ) {
            parts.push( gig.title );
        }

        if ( gig.dateDisplay ) {
            parts.push( gig.dateDisplay );
        }

        var headingText = parts.join( ' - ' );
        if ( ! headingText ) {
            headingText = gig.title || gig.dateDisplay || '';
        }

        var html = '<header class="th-songbook-gig-detail__header">';
        html += '<h3 class="th-songbook-gig-detail__title">' + escapeHtml( headingText ) + '</h3>';
        html += '</header>';

        return html;
    }

    function renderGigMeta( gig ) {
        var metaItems = [];

        if ( gig.dateDisplay ) {
            var dateLine = gig.dateDisplay;
            if ( gig.timeDisplay ) {
                dateLine += ' - ' + gig.timeDisplay;
            }
            metaItems.push( dateLine );
        } else if ( gig.timeDisplay ) {
            metaItems.push( gig.timeDisplay );
        }

        if ( gig.venue ) {
            metaItems.push( gig.venue );
        }

        if ( gig.address ) {
            metaItems.push( gig.address );
        }

        if ( gig.songCountLabel ) {
            metaItems.push( gig.songCountLabel );
        }

        if ( gig.combinedDuration ) {
            metaItems.push( ( strings.setTotalLabel || 'Total time' ) + ': ' + gig.combinedDuration );
        }

        if ( ! metaItems.length ) {
            return '';
        }

        var html = '<ul class="th-songbook-gig-detail__meta">';
        metaItems.forEach( function( item ) {
            html += '<li>' + escapeHtml( item ) + '</li>';
        } );
        html += '</ul>';

        return html;
    }

    function renderHomeView( gig ) {
        var html = '<section class="th-songbook-detail__section th-songbook-detail__section--home">';
        html += '<h4 class="th-songbook-detail__section-title">' + escapeHtml( strings.homeTitle || 'Set List Overview' ) + '</h4>';

        if ( gig.notesHtml ) {
            html += '<div class="th-songbook-detail__notes">' + gig.notesHtml + '</div>';
        }

        html += '<div class="th-songbook-detail__sets">';

        if ( Array.isArray( gig.sets ) && gig.sets.length ) {
            gig.sets.forEach( function( set ) {
                html += '<section class="th-songbook-detail__set">';
                html += '<div class="th-songbook-detail__set-header">';
                html += '<h5 class="th-songbook-detail__set-title">' + escapeHtml( set.label || '' ) + '</h5>';
                html += '<span class="th-songbook-detail__set-total">' + escapeHtml( ( strings.setTotalLabel || 'Total time' ) + ': ' + ( set.totalDuration || strings.noDuration || '--:--' ) ) + '</span>';
                html += '</div>';

                if ( Array.isArray( set.songs ) && set.songs.length ) {
                    html += '<ol class="th-songbook-detail__set-list">';
                    set.songs.forEach( function( song, songIndex ) {
                        var pointer = getPointerIndex( gig, set.key, songIndex );
                        var durationLabel = song.duration || strings.noDuration || '--:--';
                        html += '<li class="th-songbook-detail__set-item">';

                        if ( pointer >= 0 ) {
                            html += '<button type="button" class="th-songbook-detail__set-link" data-songbook-song-link="' + pointer + '">';
                            html += '<span class="th-songbook-detail__set-song-title">' + escapeHtml( song.title || strings.missingSong || '' ) + '</span>';
                            html += '<span class="th-songbook-detail__set-song-duration">' + escapeHtml( durationLabel ) + '</span>';
                            html += '</button>';
                        } else {
                            html += '<span class="th-songbook-detail__set-song-title">' + escapeHtml( song.title || strings.missingSong || '' ) + '</span>';
                            html += '<span class="th-songbook-detail__set-song-duration">' + escapeHtml( durationLabel ) + '</span>';
                        }

                        html += '</li>';
                    } );
                    html += '</ol>';
                } else {
                    html += '<p class="th-songbook-detail__empty">' + escapeHtml( strings.noSongs || 'No songs assigned yet.' ) + '</p>';
                }

                html += '</section>';
            } );
        } else {
            html += '<p class="th-songbook-detail__empty">' + escapeHtml( strings.noSongs || 'No songs assigned yet.' ) + '</p>';
        }

        html += '</div>';
        html += '</section>';

        return html;
    }

    function renderSongView( gig, pointerIndex ) {
        var pointer = getSongPointer( gig, pointerIndex );
        if ( ! pointer ) {
            return '<section class="th-songbook-detail__section"><p class="th-songbook-detail__empty">' + escapeHtml( strings.noSongs || 'No songs assigned yet.' ) + '</p></section>';
        }

        var song = pointer.song;
        var html = '<section class="th-songbook-detail__section th-songbook-detail__section--song">';

        html += '<header class="th-songbook-detail__song-header">';
        if ( pointer.setLabel ) {
            var contextText = pointer.setLabel;
            if ( typeof pointer.position === 'number' ) {
                contextText += ' - ' + ( pointer.position + 1 );
            }
            html += '<p class="th-songbook-detail__song-context">' + escapeHtml( contextText ) + '</p>';
        }
        html += '<h4 class="th-songbook-detail__song-title">' + escapeHtml( song.title || strings.missingSong || '' ) + '</h4>';
        html += '</header>';

        if ( song.missing ) {
            html += '<p class="th-songbook-detail__empty">' + escapeHtml( strings.missingSong || 'This song is no longer available.' ) + '</p>';
            html += '</section>';
            return html;
        }

        var details = [];

        if ( song.by ) {
            details.push( { label: strings.byLabel || 'By', value: song.by } );
        }

        if ( song.key ) {
            details.push( { label: strings.keyLabel || 'Key', value: song.key } );
        }

        details.push( { label: strings.durationLabel || 'Time', value: song.duration || strings.noDuration || '--:--' } );

        if ( details.length ) {
            html += '<dl class="th-songbook-detail__song-meta">';
            details.forEach( function( detail ) {
                html += '<div class="th-songbook-detail__song-meta-row"><dt>' + escapeHtml( detail.label ) + '</dt><dd>' + escapeHtml( detail.value ) + '</dd></div>';
            } );
            html += '</dl>';
        }

        var contentHtml = song.content || '<p>' + escapeHtml( strings.noSongs || '' ) + '</p>';
        html += '<div class="th-songbook-detail__song-content">' + contentHtml + '</div>';
        html += '</section>';

        return html;
    }

    function renderNav( gig ) {
        var order = Array.isArray( gig.order ) ? gig.order : [];
        var hasSongs = order.length > 0;

        var prevDisabled = state.index === null;
        var homeDisabled = state.index === null;
        var nextDisabled = ! hasSongs;

        if ( hasSongs && state.index !== null ) {
            nextDisabled = state.index >= order.length - 1;
        }

        var html = '<div class="th-songbook-detail__nav">';
        html += buildNavButton( 'prev', strings.previousButton || 'Previous', prevDisabled );
        html += buildNavButton( 'home', strings.homeButton || 'Home', homeDisabled );
        html += buildNavButton( 'next', strings.nextButton || 'Next', nextDisabled );
        html += '</div>';

        return html;
    }

    function buildNavButton( action, label, disabled ) {
        var attrs = 'type="button" class="th-songbook-detail__nav-btn" data-songbook-action="' + action + '"';
        if ( disabled ) {
            attrs += ' disabled';
        }
        return '<button ' + attrs + '>' + escapeHtml( label ) + '</button>';
    }

    function getPointerIndex( gig, setKey, songIndex ) {
        if ( ! gig || ! Array.isArray( gig.order ) ) {
            return -1;
        }

        for ( var i = 0; i < gig.order.length; i += 1 ) {
            var pointer = gig.order[ i ];
            if ( pointer && pointer.setKey === setKey && pointer.index === songIndex ) {
                return i;
            }
        }

        return -1;
    }

    function getSongPointer( gig, pointerIndex ) {
        if ( ! gig || ! Array.isArray( gig.order ) ) {
            return null;
        }

        if ( pointerIndex < 0 || pointerIndex >= gig.order.length ) {
            return null;
        }

        var pointer = gig.order[ pointerIndex ];
        if ( ! pointer ) {
            return null;
        }

        var set = findSet( gig, pointer.setKey );
        if ( ! set || ! Array.isArray( set.songs ) ) {
            return null;
        }

        var song = set.songs[ pointer.index ];
        if ( ! song ) {
            return null;
        }

        return {
            song: song,
            setLabel: set.label,
            position: pointer.index
        };
    }

    function findSet( gig, setKey ) {
        if ( ! gig || ! Array.isArray( gig.sets ) ) {
            return null;
        }

        for ( var i = 0; i < gig.sets.length; i += 1 ) {
            if ( gig.sets[ i ] && gig.sets[ i ].key === setKey ) {
                return gig.sets[ i ];
            }
        }

        return null;
    }

    function escapeHtml( value ) {
        if ( value === null || value === undefined ) {
            return '';
        }

        return String( value )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#39;' );
    }
})();
