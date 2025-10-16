(function() {
    'use strict';

    var data = window.thSongbookData || {};
    var gigItems = data.gigs && data.gigs.items ? data.gigs.items : {};
    var strings = data.strings || {};
    var displaySettings = data.settings || {};
    var root = document.documentElement;
    var layoutFrame = null;
    var SWIPE_HORIZONTAL_THRESHOLD = 60;
    var SWIPE_VERTICAL_TOLERANCE = 80;
    var SWIPE_MAX_DURATION = 800;

    var listEl = document.querySelector('[data-songbook-gig-list]');
    var detailEl = document.querySelector('[data-songbook-gig-detail]');
    var isSongOnlyMode = !listEl && !!detailEl;

    if ( ! detailEl ) {
        return;
    }

    var detailBody = detailEl.querySelector('[data-songbook-gig-detail-body]');

    if ( ! detailBody ) {
        return;
    }

    applyDisplaySettings();

    var state = {
        gigId: null,
        index: null
    };

    var layoutOverrideSettings = null;
    var clockTimer = null;
    initSwipeNavigation();

    window.addEventListener('resize', function() {
        updateFooterOffset();

        if ( state.index !== null ) {

            scheduleSongLayout();

        }

    });


    if ( listEl ) { listEl.addEventListener('click', function(event) {
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
    }); }

    detailEl.addEventListener('click', function(event) {
        var songLink = event.target.closest('[data-songbook-song-link]');
        if ( songLink ) {
            var pointer = parseInt( songLink.getAttribute('data-songbook-song-link'), 10 );
            if ( ! Number.isNaN( pointer ) ) {
                if ( displaySettings && displaySettings.song_page_url ) {
                    var url = String( displaySettings.song_page_url );
                    var gigId = state.gigId || ( detailEl.getAttribute('data-current-gig') || '' );
                    if ( gigId ) {
                        var joiner = url.indexOf('?') === -1 ? '?' : '&';
                        window.location.href = url + joiner + 'gig=' + encodeURIComponent( gigId ) + '&song=' + encodeURIComponent( pointer );
                        return;
                    }
                }

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

    function initSwipeNavigation() {
        if ( ! detailEl ) {
            return;
        }

        detailEl.style.touchAction = 'pan-y';

        if ( window.PointerEvent ) {
            setupPointerSwipe();
        } else {
            setupTouchSwipe();
        }

        function setupPointerSwipe() {
            var tracking = {
                pointerId: null,
                startX: 0,
                startY: 0,
                startTime: 0
            };

            detailEl.addEventListener( 'pointerdown', function( event ) {
                if ( ! shouldStartSwipe( event ) ) {
                    reset();
                    return;
                }

                tracking.pointerId = event.pointerId;
                tracking.startX = event.clientX;
                tracking.startY = event.clientY;
                tracking.startTime = Date.now();
            } );

            detailEl.addEventListener( 'pointerup', function( event ) {
                if ( tracking.pointerId !== event.pointerId ) {
                    return;
                }

                processSwipeAttempt( event.clientX - tracking.startX, event.clientY - tracking.startY, Date.now() - tracking.startTime );
                reset();
            } );

            detailEl.addEventListener( 'pointercancel', function( event ) {
                if ( tracking.pointerId === event.pointerId ) {
                    reset();
                }
            } );

            function reset() {
                tracking.pointerId = null;
                tracking.startX = 0;
                tracking.startY = 0;
                tracking.startTime = 0;
            }
        }

        function setupTouchSwipe() {
            var touchData = null;

            detailEl.addEventListener( 'touchstart', function( event ) {
                if ( event.touches.length !== 1 || shouldIgnoreSwipeTarget( event.target ) ) {
                    touchData = null;
                    return;
                }

                var touch = event.touches[ 0 ];
                touchData = {
                    startX: touch.clientX,
                    startY: touch.clientY,
                    startTime: Date.now()
                };
            }, { passive: true } );

            detailEl.addEventListener( 'touchend', function( event ) {
                if ( ! touchData ) {
                    return;
                }

                var touch = event.changedTouches[ 0 ];
                if ( ! touch ) {
                    touchData = null;
                    return;
                }

                var dx = touch.clientX - touchData.startX;
                var dy = touch.clientY - touchData.startY;
                var duration = Date.now() - touchData.startTime;

                processSwipeAttempt( dx, dy, duration );
                touchData = null;
            } );

            detailEl.addEventListener( 'touchcancel', function() {
                touchData = null;
            } );
        }
    }

    function shouldStartSwipe( event ) {
        if ( event.pointerType && event.pointerType !== 'touch' && event.pointerType !== 'pen' ) {
            return false;
        }

        return ! shouldIgnoreSwipeTarget( event.target );
    }

    function shouldIgnoreSwipeTarget( target ) {
        if ( ! target ) {
            return false;
        }

        return !! target.closest( 'a, button, input, textarea, select, [data-songbook-action]' );
    }

    function processSwipeAttempt( dx, dy, duration ) {
        if ( Math.abs( dx ) < SWIPE_HORIZONTAL_THRESHOLD ) {
            return;
        }

        if ( Math.abs( dy ) > SWIPE_VERTICAL_TOLERANCE || Math.abs( dx ) <= Math.abs( dy ) * 1.5 ) {
            return;
        }

        if ( duration > SWIPE_MAX_DURATION ) {
            return;
        }

        var direction = dx < 0 ? 'next' : 'prev';

        if ( ! canNavigateWithSwipe( direction ) ) {
            return;
        }

        handleAction( direction );
    }

    function canNavigateWithSwipe( direction ) {
        var gig = state.gigId ? gigItems[ state.gigId ] : null;

        if ( ! gig ) {
            return false;
        }

        var order = Array.isArray( gig.order ) ? gig.order : [];

        if ( order.length === 0 ) {
            return false;
        }

        if ( state.index === null ) {
            return false;
        }

        if ( direction === 'next' ) {
            return state.index < order.length - 1;
        }

        if ( direction === 'prev' ) {
            return true;
        }

        return false;
    }

    function updateSelectedGig() {
        if ( ! listEl ) { return; }
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
            layoutOverrideSettings = null;
            stopClock();
            detailBody.innerHTML = '<p class="th-songbook-gig-detail__placeholder">' + escapeHtml( strings.selectGigPrompt || 'Select a gig to view the set list.' ) + '</p>';
            detailEl.classList.remove( 'is-active' );
            detailEl.classList.remove( 'is-song-view' );
            detailEl.classList.add( 'is-overview' );
            detailEl.removeAttribute( 'data-current-gig' );
            return;
        }

        var gig = gigItems[ state.gigId ];
        detailEl.classList.add( 'is-active' );
        detailEl.setAttribute( 'data-current-gig', state.gigId );

        var isSongView = state.index !== null;
        detailEl.classList.toggle( 'is-song-view', isSongView );
        detailEl.classList.toggle( 'is-overview', ! isSongView );

        var headerHtml = isSongView ? '' : renderHeader( gig );
        var metaHtml = isSongView ? '' : renderGigMeta( gig );
        var songViewResult = null;
        var contentHtml;

        if ( isSongView ) {
            songViewResult = renderSongView( gig, state.index );
            contentHtml = songViewResult.html;
        } else {
            contentHtml = renderHomeView( gig );
        }

        var navHtml = renderNav( gig );
        var footerHtml = '<div class="th-songbook-detail__footer"><div class="th-songbook-detail__footer-inner"><div class="th-songbook-detail__footer-clock" data-songbook-clock></div>' + navHtml;

        if ( isSongView && songViewResult && songViewResult.by ) {
            footerHtml += '<p class="th-songbook-detail__footer-by"><span class="th-songbook-detail__footer-by-label">' + escapeHtml( strings.byLabel || 'By' ) + '</span>' + escapeHtml( songViewResult.by ) + '</p>';
        }

        footerHtml += '</div></div>';

        detailBody.innerHTML = headerHtml + metaHtml + contentHtml + footerHtml;
        updateFooterOffset();

        if ( isSongView ) {
            var layoutSettings = Object.assign( {}, displaySettings );
            if ( songViewResult ) {
                if ( Number.isFinite( songViewResult.fontSize ) ) {
                    layoutSettings.fontMax = songViewResult.fontSize;
                    layoutSettings.fontMin = songViewResult.fontSize;
                    layoutSettings.lockHeaderSizes = true;
                }

                if ( songViewResult.lockHeaderSizes === true ) {
                    layoutSettings.lockHeaderSizes = true;
                }
            }
            scheduleSongLayout( layoutSettings );
        } else if ( layoutFrame ) {
            layoutOverrideSettings = null;
            cancelAnimationFrame( layoutFrame );
            layoutFrame = null;
        } else {
            layoutOverrideSettings = null;
        }

        startClock();
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

        if ( Number.isFinite( gig.setCount ) && gig.setCount > 0 ) {
            metaItems.push( ( strings.setCountLabel || 'Sets' ) + ': ' + gig.setCount );
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
                        var isEncore = !!song.isEncore;
                        var pointer = getPointerIndex( gig, set.key, songIndex, isEncore ? 'encore' : 'song' );
                        var durationLabel = song.duration || strings.noDuration || '--:--';
                        var itemClass = 'th-songbook-detail__set-item';
                        if ( isEncore ) {
                            itemClass += ' is-encore';
                        }
                        html += '<li class="' + itemClass + '">';

                        if ( pointer >= 0 ) {
                            var linkClass = 'th-songbook-detail__set-link';
                            if ( isEncore ) {
                                linkClass += ' is-encore';
                            }
                            html += '<button type="button" class="' + linkClass + '" data-songbook-song-link="' + pointer + '">';
                            if ( isEncore ) {
                                html += '<span class="th-songbook-detail__set-song-flag">' + escapeHtml( strings.encoreLabel || 'EKSTRA' ) + '</span>';
                            }
                            html += '<span class="th-songbook-detail__set-song-title">' + escapeHtml( song.title || strings.missingSong || '' ) + '</span>';
                            html += '<span class="th-songbook-detail__set-song-duration">' + escapeHtml( durationLabel ) + '</span>';
                            html += '</button>';
                        } else {
                            if ( isEncore ) {
                                html += '<span class="th-songbook-detail__set-song-flag">' + escapeHtml( strings.encoreLabel || 'EKSTRA' ) + '</span>';
                            }
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
            return {
                html: '<section class="th-songbook-detail__section"><p class="th-songbook-detail__empty">' + escapeHtml( strings.noSongs || 'No songs assigned yet.' ) + '</p></section>',
                by: ''
            };
        }

        var song = pointer.song;
        var isEncoreSong = !!( pointer.isEncore || ( song && song.isEncore ) );
        var html = '<section class="th-songbook-detail__section th-songbook-detail__section--song">';

        html += '<header class="th-songbook-detail__song-header">';
        html += '<div class="th-songbook-detail__song-title-row">';
        html += '<h4 class="th-songbook-detail__song-title">' + escapeHtml( song.title || strings.missingSong || '' ) + '</h4>';
        if ( song.key ) {
            html += '<span class="th-songbook-detail__song-key" aria-label="' + escapeHtml( ( strings.keyLabel || 'Key' ) + ': ' + song.key ) + '">' + escapeHtml( song.key ) + '</span>';
        }
        if ( isEncoreSong ) {
            html += '<span class="th-songbook-detail__song-badge">' + escapeHtml( strings.encoreLabel || 'EKSTRA' ) + '</span>';
        }
        html += '</div>';
        html += '</header>';

        if ( song.missing ) {
            html += '<p class="th-songbook-detail__empty">' + escapeHtml( strings.missingSong || 'This song is no longer available.' ) + '</p>';
            html += '</section>';

            return {
                html: html,
                by: '',
                fontSize: null,
                columns: null,
                lockHeaderSizes: false
            };
        }

        var inlineStyles = [];
        var hasCustomFontSize = Number.isFinite( song.fontSize ) && song.fontSize > 0;

        if ( hasCustomFontSize ) {
            inlineStyles.push( '--th-songbook-preferred-font:' + song.fontSize + 'px' );
        }
        if ( Number.isFinite( song.columns ) && song.columns > 0 ) {
            inlineStyles.push( '--th-songbook-column-count:' + song.columns );
        }

        if ( song.fontWeight && Number.isFinite( song.fontWeight ) ) {
            inlineStyles.push( '--th-songbook-preferred-font-weight:' + song.fontWeight );
        }

        if ( song.fontFamily ) {
            var familyValue = String( song.fontFamily ).trim();
            // Wrap family with quotes if it contains spaces and no quotes provided
            if ( /\s/.test( familyValue ) && !/^['"].*['"]$/.test( familyValue ) ) {
                familyValue = '\'' + familyValue.replace(/'/g, "\\'") + '\'';
            }
            inlineStyles.push( '--th-songbook-preferred-font-family:' + familyValue );
        }

        var contentHtml = song.content || '<p>' + escapeHtml( strings.noSongs || '' ) + '</p>';
        if ( song.fontFamily ) {
            ensureGoogleFontLoaded( song.fontFamily, song.fontWeight );
        }
        if ( Number.isFinite( song.lineHeight ) && song.lineHeight > 0 ) {
            inlineStyles.push( '--th-songbook-preferred-line-height:' + song.lineHeight );
        }
        html += '<div class="th-songbook-detail__song-content"' + ( inlineStyles.length ? ' style="' + inlineStyles.join( '; ' ) + '"' : '' ) + '>' + contentHtml + '</div>';
        html += '</section>';

        return {
            html: html,
            by: song.by ? String( song.by ) : '',
            fontSize: hasCustomFontSize ? song.fontSize : null,
            columns: Number.isFinite( song.columns ) ? song.columns : null,
            lockHeaderSizes: hasCustomFontSize
        };
    }

    function ensureGoogleFontLoaded( family, weight ) {
        if ( ! family ) { return; }

        var fam = String( family ).trim().replace(/['"]/g, '');
        if ( ! fam ) { return; }

        var id = 'th-songbook-gfont-' + fam.replace(/\s+/g, '-').toLowerCase() + (weight ? '-' + String(weight) : '');
        if ( document.getElementById( id ) ) {
            return;
        }

        var urlFamily = fam.replace(/\s+/g, '+');
        var url = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(urlFamily) + (weight ? (':wght@' + encodeURIComponent(String(weight))) : '') + '&display=swap';
        var link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = url;
        document.head.appendChild( link );
    }

    function renderNav( gig ) {
        var order = Array.isArray( gig.order ) ? gig.order : [];
        var hasSongs = order.length > 0;

        var prevDisabled = state.index === null;
        var homeDisabled = ! state.gigId;
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

    function scheduleSongLayout( overrideSettings ) {
        if ( overrideSettings !== undefined ) {
            layoutOverrideSettings = overrideSettings;
        }

        if ( layoutFrame ) {
            cancelAnimationFrame( layoutFrame );
        }

        layoutFrame = requestAnimationFrame( function() {
            applySongLayout( layoutOverrideSettings || displaySettings );
        } );
    }

    function applySongLayout( settings ) {
        if ( ! detailBody ) {
            return;
        }

        var songSection = detailBody.querySelector('.th-songbook-detail__section--song');
        if ( ! songSection ) {
            return;
        }

        var footer = detailBody.querySelector('.th-songbook-detail__footer');
        var footerHeight = footer ? footer.offsetHeight : 0;
        var availableHeight = detailEl ? detailEl.clientHeight : detailBody.clientHeight;

        if ( footerHeight ) {
            availableHeight -= footerHeight;
        }

        availableHeight -= 32;

        if ( availableHeight <= 0 ) {
            availableHeight = detailBody.clientHeight;
        }

        if ( availableHeight <= 0 ) {
            return;
        }

        var maxFont = parseFloat( settings.fontMax );
        if ( ! Number.isFinite( maxFont ) || maxFont <= 0 ) {
            maxFont = 34;
        }

        var minFont = parseFloat( settings.fontMin );
        if ( ! Number.isFinite( minFont ) || minFont <= 0 ) {
            minFont = 18;
        }

        if ( minFont > maxFont ) {
            minFont = maxFont;
        }

        var ratios = {
            title: 1.6,
            key: 1.3,
            meta: 0.85,
            gap: 0.7
        };

        var lockHeaderSizes = !! settings.lockHeaderSizes;
        var baseHeaderSize = parseFloat( displaySettings && displaySettings.font_max );
        if ( ! Number.isFinite( baseHeaderSize ) || baseHeaderSize <= 0 ) {
            baseHeaderSize = maxFont;
        }

        function setHeaderSizes( baseSize ) {
            songSection.style.setProperty( '--th-songbook-dynamic-title', ( baseSize * ratios.title ) + 'px' );
            songSection.style.setProperty( '--th-songbook-dynamic-key', ( baseSize * ratios.key ) + 'px' );
            songSection.style.setProperty( '--th-songbook-dynamic-meta', ( baseSize * ratios.meta ) + 'px' );
        }

        function setFontSize( size ) {
            songSection.style.setProperty( '--th-songbook-dynamic-font', size + 'px' );
            songSection.style.setProperty( '--th-songbook-dynamic-gap', ( size * ratios.gap ) + 'px' );
            if ( ! lockHeaderSizes ) {
                setHeaderSizes( size );
            }
        }

        if ( lockHeaderSizes ) {
            setHeaderSizes( baseHeaderSize );
        }

        var targetSize = maxFont;
        setFontSize( targetSize );

        var guard = 0;
        while ( songSection.scrollHeight > availableHeight + 1 && targetSize > minFont ) {
            targetSize -= 0.5;
            if ( targetSize < minFont ) {
                targetSize = minFont;
            }
            setFontSize( targetSize );
            guard += 1;
            if ( guard > 160 ) {
                break;
            }
        }

        var fitted = targetSize;
        var maxAllowed = parseFloat( settings.fontMax );
        if ( ! Number.isFinite( maxAllowed ) || maxAllowed < fitted ) {
            maxAllowed = fitted;
        }

        guard = 0;
        while ( songSection.scrollHeight <= availableHeight - 24 && fitted < maxAllowed ) {
            fitted += 0.5;
            if ( fitted > maxAllowed ) {
                fitted = maxAllowed;
            }

            setFontSize( fitted );

            if ( songSection.scrollHeight > availableHeight + 1 ) {
                fitted -= 0.5;
                setFontSize( fitted );
                break;
            }

            guard += 1;
            if ( guard > 120 ) {
                break;
            }
        }
    }

    function buildNavButton( action, label, disabled ) {
        var attrs = 'type="button" class="th-songbook-detail__nav-btn" data-songbook-action="' + action + '"';
        if ( disabled ) {
            attrs += ' disabled';
        }

        var iconClass = 'fa-solid ';
        switch ( action ) {
            case 'prev':
                iconClass += 'fa-circle-chevron-left';
                break;
            case 'home':
                iconClass += 'fa-house';
                break;
            case 'next':
                iconClass += 'fa-circle-chevron-right';
                break;
            default:
                iconClass += 'fa-circle';
                break;
        }

        var iconHtml = '<span class="th-songbook-detail__nav-icon ' + iconClass + '" aria-hidden="true"></span>';
        var srText = '<span class="th-songbook-detail__nav-label">' + escapeHtml( label ) + '</span>';

        return '<button ' + attrs + '>' + iconHtml + srText + '</button>';
    }
    function getPointerIndex( gig, setKey, songIndex, type ) {
        if ( ! gig || ! Array.isArray( gig.order ) ) {
            return -1;
        }

        var targetType = type || 'song';

        for ( var i = 0; i < gig.order.length; i += 1 ) {
            var pointer = gig.order[ i ];
            if ( ! pointer ) {
                continue;
            }

            var pointerType = pointer.type || ( pointer.isEncore ? 'encore' : 'song' );

            if ( pointer.setKey === setKey && pointer.index === songIndex && pointerType === targetType ) {
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

        var pointerType = pointer.type || ( pointer.isEncore ? 'encore' : 'song' );

        return {
            song: song,
            setLabel: set.label,
            position: pointer.index,
            isEncore: pointerType === 'encore',
            type: pointerType
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

    function startClock() {
        stopClock();

        var clockEl = detailBody.querySelector('[data-songbook-clock]');
        if ( ! clockEl ) {
            return;
        }

        var update = function() {
            var now = new Date();
            var hours = String( now.getHours() ).padStart( 2, '0' );
            var minutes = String( now.getMinutes() ).padStart( 2, '0' );
            clockEl.textContent = hours + ':' + minutes;
            updateFooterOffset();
        };

        update();
        clockTimer = setInterval( update, 1000 );
    }

    function stopClock() {
        if ( clockTimer ) {
            clearInterval( clockTimer );
            clockTimer = null;
        }
    }

    function updateFooterOffset() {
        if ( ! detailEl ) {
            return;
        }

        var footer = detailBody.querySelector('.th-songbook-detail__footer');

        if ( footer ) {
            var offset = footer.offsetHeight + 24;
            detailEl.style.setProperty( '--th-songbook-footer-offset', offset + 'px' );
        } else {
            detailEl.style.removeProperty( '--th-songbook-footer-offset' );
        }
    }

    function applyDisplaySettings() {
        if ( ! root || ! displaySettings ) {
            return;
        }

        if ( displaySettings.screen_width ) {
            root.style.setProperty( '--th-songbook-screen-max-width', parseInt( displaySettings.screen_width, 10 ) + 'px' );
        }

        if ( displaySettings.screen_height ) {
            root.style.setProperty( '--th-songbook-screen-max-height', parseInt( displaySettings.screen_height, 10 ) + 'px' );
        }

        if ( displaySettings.nav_background ) {
            root.style.setProperty( '--th-songbook-nav-bg', String( displaySettings.nav_background ) );
        }

        if ( displaySettings.nav_icon ) {
            root.style.setProperty( '--th-songbook-nav-color', String( displaySettings.nav_icon ) );
        }

        if ( displaySettings.font_max ) {
            root.style.setProperty( '--th-songbook-font-max', parseInt( displaySettings.font_max, 10 ) + 'px' );
        }

        if ( displaySettings.font_min ) {
            root.style.setProperty( '--th-songbook-font-min', parseInt( displaySettings.font_min, 10 ) + 'px' );
        }

        if ( displaySettings.clock_font_family ) {
            root.style.setProperty( '--th-songbook-clock-font-family', String( displaySettings.clock_font_family ) );
        }

        if ( displaySettings.clock_font_size ) {
            root.style.setProperty( '--th-songbook-clock-font-size', parseInt( displaySettings.clock_font_size, 10 ) + 'px' );
        }

        if ( displaySettings.clock_font_weight ) {
            root.style.setProperty( '--th-songbook-clock-font-weight', String( displaySettings.clock_font_weight ) );
        }
        if ( displaySettings.footer_min_height ) {
            root.style.setProperty( '--th-songbook-footer-min-height', parseInt( displaySettings.footer_min_height, 10 ) + 'px' );
        }
        if ( displaySettings.song_title_font_size ) {
            root.style.setProperty( '--th-songbook-header-title-size', parseInt( displaySettings.song_title_font_size, 10 ) + 'px' );
        }
        if ( displaySettings.song_key_font_size ) {
            root.style.setProperty( '--th-songbook-header-key-size', parseInt( displaySettings.song_key_font_size, 10 ) + 'px' );
        }
        if ( displaySettings.column_rule_color ) {
            root.style.setProperty( '--th-songbook-column-rule-color', String( displaySettings.column_rule_color ) );
        }
        if ( displaySettings.song_title_font_weight ) {
            root.style.setProperty( '--th-songbook-title-font-weight', String( displaySettings.song_title_font_weight ) );
        }
        if ( displaySettings.song_title_font_family ) {
            root.style.setProperty( '--th-songbook-title-font-family', String( displaySettings.song_title_font_family ) );
            ensureGoogleFontLoaded( String( displaySettings.song_title_font_family ), displaySettings.song_title_font_weight );
        }
        if ( displaySettings.song_list_text_color ) {
            root.style.setProperty( '--th-songbook-song-list-color', String( displaySettings.song_list_text_color ) );
        }
        if ( displaySettings.song_list_text_size ) {
            root.style.setProperty( '--th-songbook-song-list-size', parseInt( displaySettings.song_list_text_size, 10 ) + 'px' );
        }
        if ( displaySettings.song_list_text_weight ) {
            root.style.setProperty( '--th-songbook-song-list-weight', String( displaySettings.song_list_text_weight ) );
        }
        if ( displaySettings.song_hover_color ) {
            root.style.setProperty( '--th-songbook-song-hover-color', String( displaySettings.song_hover_color ) );
        }
        if ( displaySettings.nav_hover_color ) {
            root.style.setProperty( '--th-songbook-nav-hover-color', String( displaySettings.nav_hover_color ) );
        }
    }

    // If we are on a single-song page, initialize from URL params.
    if ( isSongOnlyMode ) {
        try {
            var params = new URLSearchParams( window.location.search );
            var qGig = params.get('gig');
            var qSong = params.get('song');
            if ( qGig && gigItems[ qGig ] ) {
                state.gigId = qGig;
                if ( qSong !== null ) {
                    var idx = parseInt( qSong, 10 );
                    if ( ! Number.isNaN( idx ) ) {
                        state.index = idx;
                    }
                }
                renderDetail();
            }
        } catch (e) {}
    }
})();
