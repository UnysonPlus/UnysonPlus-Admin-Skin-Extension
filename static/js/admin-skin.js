/**
 * UnysonPlus Admin Skin — behaviour.
 *
 * Vanilla, no build step. Everything here is additive DOM work on core's
 * markup: a brand block and search field in the sidebar, group headings in
 * #adminmenu, a page title and an appearance popover in the admin bar, a
 * tray that collects notices, and the per-user mode / accent preference.
 * Nothing is removed from the page; anything core or a plugin binds to keeps
 * working because the elements are only moved, never re-created.
 */
( function () {
	'use strict';

	var cfg = window.fwAdminSkin || {};
	var i18n = cfg.i18n || {};
	var doc = document;
	var html = doc.documentElement;

	if ( ! doc.body.classList.contains( 'upa' ) ) {
		return;
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                              */
	/* ------------------------------------------------------------------ */

	function el( tag, attrs, children ) {
		var node = doc.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( k ) {
				if ( 'class' === k ) {
					node.className = attrs[ k ];
				} else if ( 'text' === k ) {
					node.textContent = attrs[ k ];
				} else if ( 'html' === k ) {
					node.innerHTML = attrs[ k ];
				} else if ( 0 === k.indexOf( 'on' ) ) {
					node.addEventListener( k.slice( 2 ), attrs[ k ] );
				} else {
					node.setAttribute( k, attrs[ k ] );
				}
			} );
		}
		( children || [] ).forEach( function ( c ) {
			if ( c ) {
				node.appendChild( 'string' === typeof c ? doc.createTextNode( c ) : c );
			}
		} );
		return node;
	}

	var ICONS = {
		search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
		sun: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/></svg>',
		moon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>',
		system: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>',
		palette: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 0 0 0 18c1.1 0 2-.9 2-2v-1a2 2 0 0 1 2-2h1a4 4 0 0 0 4-4 9 9 0 0 0-9-9Z"/><circle cx="7.5" cy="11.5" r="1"/><circle cx="10.5" cy="7.5" r="1"/><circle cx="15.5" cy="7.5" r="1"/></svg>',
		external: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 13v6H4V5h6"/></svg>',
		logout: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"/></svg>'
	};

	function svg( name ) {
		var wrap = el( 'span' );
		wrap.innerHTML = ICONS[ name ] || '';
		return wrap.firstChild;
	}

	function savePrefs() {
		// Always send the whole preference set: two quick clicks would
		// otherwise race each other's read-modify-write on the server.
		return post( { mode: state.mode, accent: state.accent } );
	}

	function post( data ) {
		var body = new FormData();
		body.append( 'action', 'fw_admin_skin_prefs' );
		body.append( 'nonce', cfg.nonce || '' );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.catch( function () { return null; } );
	}

	function hexToRgb( hex ) {
		var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec( hex || '' );
		return m ? [ parseInt( m[ 1 ], 16 ), parseInt( m[ 2 ], 16 ), parseInt( m[ 3 ], 16 ) ] : null;
	}

	function lighten( hex, amount ) {
		var c = hexToRgb( hex );
		if ( ! c ) {
			return hex;
		}
		return '#' + c.map( function ( v ) {
			return ( '0' + Math.round( v + ( 255 - v ) * amount ).toString( 16 ) ).slice( -2 );
		} ).join( '' );
	}

	function contrastFg( hex ) {
		var c = hexToRgb( hex );
		if ( ! c ) {
			return '#ffffff';
		}
		var lin = c.map( function ( v ) {
			v = v / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		var l = 0.2126 * lin[ 0 ] + 0.7152 * lin[ 1 ] + 0.0722 * lin[ 2 ];
		return l > 0.4 ? '#111111' : '#ffffff';
	}

	/* ------------------------------------------------------------------ */
	/* Mode + accent                                                        */
	/* ------------------------------------------------------------------ */

	var state = {
		mode: cfg.mode || 'light',
		accent: cfg.accent || ''
	};

	function applyMode( mode ) {
		state.mode = mode;
		html.setAttribute( 'data-upa-mode', mode );
		doc.querySelectorAll( '.upa-seg button[data-mode]' ).forEach( function ( b ) {
			b.classList.toggle( 'is-active', b.getAttribute( 'data-mode' ) === mode );
		} );
	}

	function applyAccent( hex ) {
		state.accent = hex || '';
		var isDark = 'dark' === state.mode || ( 'system' === state.mode && window.matchMedia( '(prefers-color-scheme: dark)' ).matches );
		if ( hex ) {
			html.style.setProperty( '--upa-accent', hex );
			html.style.setProperty( '--upa-accent2', lighten( hex, isDark ? 0.18 : 0.14 ) );
			html.style.setProperty( '--upa-accent-fg', contrastFg( hex ) );
		} else {
			html.style.removeProperty( '--upa-accent' );
			html.style.removeProperty( '--upa-accent2' );
			html.style.removeProperty( '--upa-accent-fg' );
		}
		doc.querySelectorAll( '.upa-swatch[data-accent]' ).forEach( function ( b ) {
			b.classList.toggle( 'is-active', ( b.getAttribute( 'data-accent' ) || '' ) === ( hex || '' ) );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Sidebar                                                              */
	/* ------------------------------------------------------------------ */

	var menuWrap = doc.getElementById( 'adminmenuwrap' );
	var menu = doc.getElementById( 'adminmenu' );

	function buildBrand() {
		if ( ! menuWrap ) {
			return;
		}
		var name = cfg.siteName || '';
		var mark = el( 'span', { class: 'upa-brand-mark' + ( cfg.logo ? ' has-icon' : '' ) } );
		if ( cfg.logo ) {
			mark.appendChild( el( 'img', { src: cfg.logo, alt: '' } ) );
		} else {
			mark.textContent = ( name.trim().charAt( 0 ) || 'W' ).toUpperCase();
		}
		var brand = el( 'div', { class: 'upa-brand' }, [
			el( 'a', { class: 'upa-brand-link', href: cfg.adminUrl || '#', title: name }, [
				mark,
				el( 'span', { class: 'upa-brand-name', text: name } )
			] ),
			el( 'a', { class: 'upa-icon-btn upa-brand-visit', href: cfg.siteUrl || '/', title: i18n.viewSite || 'Visit site', 'aria-label': i18n.viewSite || 'Visit site' }, [ svg( 'external' ) ] )
		] );
		menuWrap.insertBefore( brand, menuWrap.firstChild );
	}

	function buildSearch() {
		if ( ! menuWrap || ! menu || false === cfg.sidebarSearch ) {
			return;
		}
		var input = el( 'input', { type: 'search', placeholder: i18n.search || 'Search menu…', 'aria-label': i18n.search || 'Search menu' } );
		var box = el( 'div', { class: 'upa-search' }, [ svg( 'search' ), input, el( 'kbd', { text: '/' } ) ] );
		var empty = el( 'div', { class: 'upa-search-empty', text: i18n.noMatch || 'No menu item matches' } );
		menuWrap.insertBefore( box, menu );
		menuWrap.insertBefore( empty, menu );

		function filter() {
			var q = input.value.trim().toLowerCase();
			var any = false;
			menu.querySelectorAll( 'li.menu-top' ).forEach( function ( li ) {
				var text = ( li.textContent || '' ).toLowerCase();
				var hit = ! q || text.indexOf( q ) !== -1;
				li.classList.toggle( 'upa-hidden', ! hit );
				li.style.display = hit ? '' : 'none';
				if ( hit ) {
					any = true;
				}
			} );
			menu.querySelectorAll( 'li.upa-group-label' ).forEach( function ( label ) {
				var n = label.nextElementSibling;
				var visible = false;
				while ( n && ! n.classList.contains( 'upa-group-label' ) ) {
					if ( n.classList.contains( 'menu-top' ) && 'none' !== n.style.display ) {
						visible = true;
						break;
					}
					n = n.nextElementSibling;
				}
				label.style.display = visible ? '' : 'none';
			} );
			menu.classList.toggle( 'upa-filtering', !! q );
			empty.style.display = ( q && ! any ) ? 'block' : 'none';
		}

		input.addEventListener( 'input', filter );
		input.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				input.value = '';
				filter();
				input.blur();
			}
			if ( 'Enter' === e.key ) {
				var first = menu.querySelector( 'li.menu-top:not([style*="none"]) > a' );
				if ( first && input.value.trim() ) {
					first.click();
				}
			}
		} );

		doc.addEventListener( 'keydown', function ( e ) {
			if ( '/' !== e.key || e.ctrlKey || e.metaKey || e.altKey ) {
				return;
			}
			var t = e.target;
			var tag = t && t.tagName ? t.tagName.toLowerCase() : '';
			if ( 'input' === tag || 'textarea' === tag || 'select' === tag || ( t && t.isContentEditable ) ) {
				return;
			}
			e.preventDefault();
			input.focus();
			input.select();
		} );
	}

	function classify( li, groups ) {
		var id = li.id || '';
		var cls = li.className || '';
		var a = li.querySelector( 'a' );
		var href = a ? ( a.getAttribute( 'href' ) || '' ) : '';
		var hay = id + ' ' + cls + ' ' + href;
		var map = groups.map || {};
		var keys = Object.keys( map );
		for ( var i = 0; i < keys.length; i++ ) {
			if ( hay.indexOf( keys[ i ] ) !== -1 ) {
				return map[ keys[ i ] ];
			}
		}
		if ( /^menu-posts(-|$)/.test( id ) ) {
			return groups.cpt_group || 'workspace';
		}
		return groups.fallback || 'plugins';
	}

	function groupMenu() {
		if ( ! menu || false === cfg.groupMenu || ! cfg.groups ) {
			return;
		}
		var groups = cfg.groups;
		var order = Object.keys( groups.groups || {} );
		var buckets = {};
		order.forEach( function ( k ) { buckets[ k ] = []; } );

		var collapse = doc.getElementById( 'collapse-menu' );
		var items = Array.prototype.slice.call( menu.children );

		items.forEach( function ( li ) {
			if ( li === collapse ) {
				return;
			}
			if ( li.classList.contains( 'wp-menu-separator' ) ) {
				li.parentNode.removeChild( li );
				return;
			}
			if ( ! li.classList.contains( 'menu-top' ) ) {
				return;
			}
			var g = classify( li, groups );
			if ( ! buckets[ g ] ) {
				g = groups.fallback || order[ order.length - 1 ];
			}
			buckets[ g ].push( li );
		} );

		order.forEach( function ( key ) {
			if ( ! buckets[ key ].length ) {
				return;
			}
			var label = el( 'li', { class: 'upa-group-label', text: groups.groups[ key ] } );
			menu.insertBefore( label, collapse );
			buckets[ key ].forEach( function ( li ) {
				menu.insertBefore( li, collapse );
			} );
		} );
	}

	function buildUser() {
		if ( ! menuWrap || ! cfg.user ) {
			return;
		}
		var u = cfg.user;
		var block = el( 'div', { class: 'upa-user' }, [
			u.avatar ? el( 'img', { src: u.avatar, alt: '' } ) : null,
			el( 'div', { class: 'upa-user-meta' }, [
				el( 'a', { class: 'upa-user-name', href: u.profile || '#', text: u.name || '' } ),
				el( 'div', { class: 'upa-user-role', text: u.role || '' } )
			] ),
			el( 'div', { class: 'upa-user-actions' }, [
				el( 'a', { class: 'upa-icon-btn', href: cfg.siteUrl || '/', title: i18n.viewSite || 'View site', target: '_blank', rel: 'noopener' }, [ svg( 'external' ) ] ),
				el( 'a', { class: 'upa-icon-btn', href: u.logout || '#', title: 'Log out' }, [ svg( 'logout' ) ] )
			] )
		] );
		menuWrap.appendChild( block );
	}

	/* ------------------------------------------------------------------ */
	/* Hover flyouts                                                        */
	/* ------------------------------------------------------------------ */

	// The sidebar scrolls internally, which clips core's absolutely
	// positioned flyouts. So a parent's submenu is mirrored into a floating
	// card on <body>, opened on hover / focus like core's and closed when
	// the pointer leaves both the item and the card.
	function buildFlyouts() {
		if ( ! menu ) {
			return;
		}
		var panel = null;
		var owner = null;
		var timer = null;

		function close() {
			if ( panel ) {
				panel.parentNode.removeChild( panel );
				panel = null;
			}
			if ( owner ) {
				owner.classList.remove( 'upa-open' );
				owner = null;
			}
		}

		function schedule() {
			clearTimeout( timer );
			timer = setTimeout( close, 160 );
		}

		function open( li ) {
			if ( doc.body.classList.contains( 'folded' ) ) {
				return; // folded uses core's own flyout
			}
			var sub = li.querySelector( '.wp-submenu' );
			if ( ! sub || li.classList.contains( 'wp-has-current-submenu' ) ) {
				return;
			}
			clearTimeout( timer );
			if ( owner === li ) {
				return;
			}
			close();
			var name = li.querySelector( '.wp-menu-name' );
			panel = el( 'div', { class: 'upa-flyout', role: 'menu' } );
			if ( name ) {
				panel.appendChild( el( 'div', { class: 'upa-flyout-head', text: ( name.childNodes[ 0 ] ? name.childNodes[ 0 ].textContent : name.textContent ).trim() } ) );
			}
			var list = sub.cloneNode( true );
			list.removeAttribute( 'id' );
			list.className = 'upa-flyout-list';
			Array.prototype.forEach.call( list.querySelectorAll( '.wp-submenu-head' ), function ( h ) {
				h.parentNode.removeChild( h );
			} );
			panel.appendChild( list );
			panel.addEventListener( 'mouseenter', function () { clearTimeout( timer ); } );
			panel.addEventListener( 'mouseleave', schedule );
			doc.body.appendChild( panel );
			owner = li;
			li.classList.add( 'upa-open' );

			place();
		}

		function place() {
			if ( ! panel || ! owner ) {
				return;
			}
			var r = owner.getBoundingClientRect();
			var wrapR = menuWrap.getBoundingClientRect();
			var top = r.top - 6;
			var maxTop = window.innerHeight - panel.offsetHeight - 8;
			if ( top > maxTop ) {
				top = Math.max( 8, maxTop );
			}
			panel.style.top = top + 'px';
			panel.style.left = ( wrapR.right + 4 ) + 'px';
		}

		menu.addEventListener( 'mouseover', function ( e ) {
			var li = e.target.closest && e.target.closest( 'li.menu-top' );
			if ( li && menu.contains( li ) ) {
				if ( li.classList.contains( 'wp-has-submenu' ) ) {
					open( li );
				} else {
					schedule();
				}
			}
		} );
		menu.addEventListener( 'mouseleave', schedule );
		menu.addEventListener( 'focusin', function ( e ) {
			var li = e.target.closest && e.target.closest( 'li.menu-top' );
			if ( li && li.classList.contains( 'wp-has-submenu' ) && e.target.classList.contains( 'menu-top' ) ) {
				open( li );
			}
		} );
		doc.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				close();
			}
		} );
		menu.addEventListener( 'scroll', place );
		window.addEventListener( 'resize', place );
	}

	/* ------------------------------------------------------------------ */
	/* Top bar                                                              */
	/* ------------------------------------------------------------------ */

	function pageTitle() {
		var h = doc.querySelector( '#wpbody-content .wrap h1.wp-heading-inline, #wpbody-content .wrap > h1, #wpbody-content > .wrap > h2:first-child, #wpbody-content h1' );
		var title = '';
		if ( h ) {
			// Only the heading's own text: skip the "Add New" action anchors.
			Array.prototype.forEach.call( h.childNodes, function ( n ) {
				if ( 3 === n.nodeType ) {
					title += n.textContent;
				} else if ( 1 === n.nodeType && ! n.classList.contains( 'page-title-action' ) && 'a' !== n.tagName.toLowerCase() ) {
					title += n.textContent;
				}
			} );
		}
		title = title.replace( /\s+/g, ' ' ).trim();
		if ( ! title ) {
			title = ( doc.title || '' ).split( ' ‹ ' )[ 0 ].trim();
		}
		return title;
	}

	function currentContext() {
		var top = menu ? menu.querySelector( 'li.wp-has-current-submenu > a .wp-menu-name, li.current > a .wp-menu-name' ) : null;
		var topName = top ? top.childNodes[ 0 ] && top.childNodes[ 0 ].textContent.trim() : '';
		return topName || '';
	}

	// Screens that draw their own page header (wc-admin's fixed header, the
	// block editor's own bar). Showing the title in the top bar too just
	// repeats what is already on screen a few pixels below.
	var OWN_HEADER = '.woocommerce-layout__header-heading, .edit-post-header, .interface-interface-skeleton__header, .edit-site-header';

	function buildTitle() {
		var root = doc.getElementById( 'wp-admin-bar-root-default' );
		if ( ! root ) {
			return;
		}
		if ( doc.querySelector( OWN_HEADER ) ) {
			return;
		}
		var title = pageTitle();
		var ctx = currentContext();
		var item = el( 'div', { class: 'ab-item', 'aria-hidden': 'true' }, [ title ] );
		if ( ctx && ctx.toLowerCase() !== title.toLowerCase() ) {
			item.appendChild( el( 'span', { class: 'upa-chip', text: ctx } ) );
		}
		var li = el( 'li', { id: 'wp-admin-bar-upa-title' }, [ item ] );
		root.insertBefore( li, root.firstChild );
	}

	function buildAppearance() {
		var secondary = doc.getElementById( 'wp-admin-bar-top-secondary' );
		if ( ! secondary || false === cfg.userPrefs ) {
			return;
		}

		var pop = el( 'div', { class: 'upa-appearance', role: 'dialog', 'aria-label': i18n.appearance || 'Appearance' } );

		var seg = el( 'div', { class: 'upa-seg' } );
		[ [ 'light', 'sun' ], [ 'dark', 'moon' ], [ 'system', 'system' ] ].forEach( function ( pair ) {
			var b = el( 'button', { type: 'button', 'data-mode': pair[ 0 ] }, [ svg( pair[ 1 ] ), i18n[ pair[ 0 ] ] || pair[ 0 ] ] );
			b.addEventListener( 'click', function () {
				applyMode( pair[ 0 ] );
				applyAccent( state.accent );
				savePrefs();
			} );
			seg.appendChild( b );
		} );

		var swatches = el( 'div', { class: 'upa-swatches' } );
		var reset = el( 'button', { type: 'button', class: 'upa-swatch upa-swatch-reset', 'data-accent': '', title: i18n.reset || 'Skin default', html: '&times;' } );
		reset.addEventListener( 'click', function () {
			applyAccent( '' );
			savePrefs();
		} );
		swatches.appendChild( reset );
		[ '#5b4fe6', '#2f74e0', '#0f9d8a', '#1f9d61', '#c9791a', '#d2413f', '#c0509f', '#5c6370', '#111111' ].forEach( function ( hex ) {
			var b = el( 'button', { type: 'button', class: 'upa-swatch', 'data-accent': hex, title: hex, style: 'background:' + hex } );
			b.addEventListener( 'click', function () {
				applyAccent( hex );
				savePrefs();
			} );
			swatches.appendChild( b );
		} );
		var custom = el( 'label', { class: 'upa-swatch upa-swatch-custom', title: i18n.accent || 'Accent' } );
		var picker = el( 'input', { type: 'color', value: state.accent || '#5b4fe6' } );
		picker.addEventListener( 'input', function () {
			applyAccent( picker.value );
		} );
		picker.addEventListener( 'change', function () {
			applyAccent( picker.value );
			savePrefs();
		} );
		custom.appendChild( picker );
		swatches.appendChild( custom );

		var classic = el( 'a', { href: '#', text: i18n.classic || 'Use classic wp-admin' } );
		classic.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			post( { off: '1' } ).then( function () {
				window.location.reload();
			} );
		} );

		pop.appendChild( el( 'h4', { text: i18n.appearance || 'Appearance' } ) );
		pop.appendChild( seg );
		pop.appendChild( el( 'h4', { text: i18n.accent || 'Accent' } ) );
		pop.appendChild( swatches );
		pop.appendChild( el( 'div', { class: 'upa-appearance-foot' }, [
			el( 'span', { text: cfg.skinTitle || '' } ),
			classic
		] ) );
		doc.body.appendChild( pop );

		var btn = el( 'a', { class: 'ab-item', href: '#', role: 'button', 'aria-haspopup': 'true', 'aria-expanded': 'false', title: i18n.appearance || 'Appearance' }, [ svg( 'palette' ) ] );
		var li = el( 'li', { id: 'wp-admin-bar-upa-appearance' }, [ btn ] );
		secondary.insertBefore( li, secondary.firstChild );

		function close() {
			pop.classList.remove( 'is-open' );
			btn.setAttribute( 'aria-expanded', 'false' );
		}

		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var open = ! pop.classList.contains( 'is-open' );
			pop.classList.toggle( 'is-open', open );
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
		doc.addEventListener( 'click', function ( e ) {
			if ( pop.classList.contains( 'is-open' ) && ! pop.contains( e.target ) && ! li.contains( e.target ) ) {
				close();
			}
		} );
		doc.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				close();
			}
		} );

		applyMode( state.mode );
		applyAccent( state.accent );
	}

	/* ------------------------------------------------------------------ */
	/* Notice tray                                                          */
	/* ------------------------------------------------------------------ */

	var tray = null;
	var trayList = null;
	var trayToggle = null;

	function isTrayable( n ) {
		if ( ! n || 1 !== n.nodeType ) {
			return false;
		}
		if ( ! n.matches( '.notice, div.updated, div.error, .update-nag' ) ) {
			return false;
		}
		if ( n.matches( '.inline, .below-h2, .hidden, .upa-keep, .notice-alt, .woocommerce-message, .woocommerce-store-alerts, .wc-admin-layout__notice' ) ) {
			return false;
		}
		if ( n.closest( '.postbox, .wp-list-table, form table, #screen-meta, .media-modal, .upa-notices, .metabox-holder, .fw-backend-option, .inside, #side-sortables, #normal-sortables' ) ) {
			return false;
		}
		var p = n.parentNode;
		return p && ( p.id === 'wpbody-content' || p.classList.contains( 'wrap' ) );
	}

	function isFeedback( n ) {
		return n.matches( '.notice-success, .notice-error, div.updated, div.error, #message, .settings-error' );
	}

	function updateTray() {
		if ( ! tray ) {
			return;
		}
		var items = Array.prototype.filter.call( trayList.children, function ( n ) {
			return 'none' !== n.style.display && ! n.classList.contains( 'hidden' );
		} );
		var count = items.length;
		tray.style.display = count ? '' : 'none';
		var hasError = items.some( function ( n ) { return n.matches( '.notice-error, div.error' ); } );
		var hasSuccess = items.some( function ( n ) { return n.matches( '.notice-success, div.updated' ); } );
		var dot = trayToggle.querySelector( '.upa-dot' );
		dot.className = 'upa-dot' + ( hasError ? ' is-error' : ( hasSuccess ? ' is-success' : '' ) );
		trayToggle.querySelector( '.upa-notices-count' ).textContent = count + ' ' + ( 1 === count ? ( i18n.notice || 'notice' ) : ( i18n.notices || 'notices' ) );
		trayToggle.querySelector( '.upa-notices-action' ).textContent = tray.classList.contains( 'is-collapsed' ) ? ( i18n.show || 'Show' ) : ( i18n.hide || 'Hide' );
	}

	function adopt( n ) {
		if ( ! tray ) {
			var anchor = doc.querySelector( '#wpbody-content .wrap .wp-header-end' ) || doc.querySelector( '#wpbody-content .wrap > h1' ) || doc.querySelector( '#wpbody-content .wrap > h2:first-child' ) || doc.querySelector( '#wpbody-content .wrap' );
			tray = el( 'div', { class: 'upa-notices is-collapsed' } );
			trayToggle = el( 'button', { type: 'button', class: 'upa-notices-toggle', 'aria-expanded': 'false' }, [
				el( 'span', { class: 'upa-dot' } ),
				el( 'span', { class: 'upa-notices-count' } ),
				el( 'span', { class: 'upa-notices-action' } )
			] );
			trayList = el( 'div', { class: 'upa-notices-list' } );
			tray.appendChild( trayToggle );
			tray.appendChild( trayList );
			trayToggle.addEventListener( 'click', function () {
				tray.classList.toggle( 'is-collapsed' );
				trayToggle.setAttribute( 'aria-expanded', tray.classList.contains( 'is-collapsed' ) ? 'false' : 'true' );
				updateTray();
			} );
			if ( anchor && anchor.parentNode ) {
				if ( anchor.classList.contains( 'wp-header-end' ) || 'H1' === anchor.tagName || 'H2' === anchor.tagName ) {
					anchor.parentNode.insertBefore( tray, anchor.nextSibling );
				} else {
					anchor.insertBefore( tray, anchor.firstChild );
				}
			} else {
				doc.getElementById( 'wpbody-content' ).insertBefore( tray, doc.getElementById( 'wpbody-content' ).firstChild );
			}
		}
		if ( isFeedback( n ) ) {
			tray.classList.remove( 'is-collapsed' );
			trayToggle.setAttribute( 'aria-expanded', 'true' );
		}
		trayList.appendChild( n );
		updateTray();
	}

	function buildTray() {
		if ( false === cfg.noticeTray ) {
			return;
		}
		var body = doc.getElementById( 'wpbody-content' );
		if ( ! body ) {
			return;
		}
		var sweep = function () {
			Array.prototype.slice.call( body.querySelectorAll( '#wpbody-content > .notice, #wpbody-content > div.updated, #wpbody-content > div.error, #wpbody-content > .update-nag, #wpbody-content > .wrap > .notice, #wpbody-content > .wrap > div.updated, #wpbody-content > .wrap > div.error, #wpbody-content > .wrap > .update-nag' ) ).forEach( function ( n ) {
				if ( isTrayable( n ) ) {
					adopt( n );
				}
			} );
		};
		sweep();
		// Core moves notices after .wp-header-end on jQuery ready and plugins
		// add theirs later; keep adopting new ones without re-touching old.
		var obs = new MutationObserver( function ( muts ) {
			var dirty = false;
			muts.forEach( function ( m ) {
				Array.prototype.forEach.call( m.addedNodes, function ( n ) {
					if ( isTrayable( n ) ) {
						dirty = true;
					}
				} );
				if ( 'attributes' === m.type && m.target && trayList && trayList.contains( m.target ) ) {
					dirty = true;
				}
			} );
			if ( dirty ) {
				sweep();
				updateTray();
			}
		} );
		obs.observe( body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'style', 'class' ] } );
		// A dismissed notice is removed by core; recount when that happens.
		body.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.closest && e.target.closest( '.notice-dismiss' ) ) {
				setTimeout( updateTray, 350 );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* List-table guard: keep the primary column readable                   */
	/* ------------------------------------------------------------------ */

	function guardTables() {
		// table-layout: fixed hands the primary column whatever percentage
		// the other columns leave over; with several plugin columns that is
		// nothing. Give it a floor only when it has actually been starved.
		doc.querySelectorAll( '.wp-list-table.fixed' ).forEach( function ( t ) {
			var th = t.querySelector( 'thead th.column-primary, thead th.column-title, thead th.column-name' );
			if ( ! th ) {
				return;
			}
			var w = th.getBoundingClientRect().width;
			if ( w < 180 ) {
				th.style.width = '28%';
			}
			// A plugin column with no declared width collapses to nothing too.
			t.querySelectorAll( 'thead th:not(.check-column)' ).forEach( function ( c ) {
				if ( c !== th && c.getBoundingClientRect().width < 24 ) {
					c.style.width = '80px';
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Untitled postboxes: collapse the empty header to just its toggle     */
	/* ------------------------------------------------------------------ */

	// The framework masks the last option row's hairline with a white line
	// pinned above the box bottom, which cuts across a rounded card. The row
	// is often not :last-child (later siblings are hidden), so tag the last
	// VISIBLE one and let the CSS drop its border instead.
	function markLastOptionRows() {
		// Any container whose last child row ends at its own bottom edge: a
		// framework postbox, a plain metabox holding fw options, or one tab.
		doc.querySelectorAll( '.postbox > .inside, .fw-postbox > .inside, .postbox-with-fw-options > .inside, .fw-options-tab' ).forEach( function ( box ) {
			var rows = box.querySelectorAll( '.fw-backend-option-design-default' );
			var last = null;
			Array.prototype.forEach.call( rows, function ( r ) {
				r.classList.remove( 'upa-last-row' );
				// Only rows at this container's own level, not ones nested
				// inside another row (multi / addable-box children).
				if ( r.parentElement.closest( '.fw-backend-option-design-default' ) ) {
					return;
				}
				if ( r.offsetParent !== null && r.getBoundingClientRect().height > 0 ) {
					last = r;
				}
			} );
			if ( last ) {
				last.classList.add( 'upa-last-row' );
				// The group that row belongs to draws its own hairline, and it
				// is not :last-child either (the framework appends hider divs
				// after it), so tag it the same way.
				var grp = last.closest( '.fw-backend-options-group' );
				doc.querySelectorAll( '.upa-last-group' ).forEach( function ( g ) {
					if ( box.contains( g ) ) {
						g.classList.remove( 'upa-last-group' );
					}
				} );
				while ( grp && box.contains( grp ) ) {
					grp.classList.add( 'upa-last-group' );
					grp = grp.parentElement ? grp.parentElement.closest( '.fw-backend-options-group' ) : null;
				}
			}
		} );
	}

	function markUntitledBoxes() {
		doc.querySelectorAll( '.postbox' ).forEach( function ( box ) {
			var h = box.querySelector( '.postbox-header .hndle, h2.hndle, h3.hndle' );
			if ( h && ! h.textContent.trim() ) {
				box.classList.add( 'upa-untitled' );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                 */
	/* ------------------------------------------------------------------ */

	if ( cfg.showWpLogo ) {
		doc.body.classList.add( 'upa-wp-logo' );
	}

	buildBrand();
	buildSearch();
	groupMenu();
	buildUser();
	buildFlyouts();
	// buildTitle() is deliberately NOT called: every classic screen already
	// prints its own <h1> a few pixels below the bar, so the injected title
	// just repeated it. Kept for skins that hide the page heading instead.
	// wc-admin mounts its header after boot, so re-check and drop ours.
	setTimeout( function () {
		var mine = doc.getElementById( 'wp-admin-bar-upa-title' );
		if ( mine && doc.querySelector( OWN_HEADER ) ) {
			mine.parentNode.removeChild( mine );
		}
	}, 1200 );
	buildAppearance();

	// After jQuery's ready handlers (core relocates notices there).
	function afterReady( fn ) {
		if ( 'loading' === doc.readyState ) {
			doc.addEventListener( 'DOMContentLoaded', function () { setTimeout( fn, 0 ); } );
		} else {
			setTimeout( fn, 0 );
		}
	}
	afterReady( buildTray );
	afterReady( guardTables );
	afterReady( markUntitledBoxes );
	afterReady( markLastOptionRows );
	// The framework renders its options after DOM ready, so re-run once the
	// page has settled and again on load.
	setTimeout( markLastOptionRows, 400 );
	setTimeout( markLastOptionRows, 1200 );
	window.addEventListener( 'load', function () { setTimeout( markLastOptionRows, 200 ); } );
	doc.addEventListener( 'click', function ( e ) {
		// Tab switches and repeatable rows change which row is last.
		if ( e.target.closest && e.target.closest( '.fw-options-tabs-list, .nav-tab, .fw-backend-option' ) ) {
			setTimeout( markLastOptionRows, 60 );
		}
	}, true );
	window.addEventListener( 'resize', guardTables );

	if ( window.matchMedia ) {
		window.matchMedia( '(prefers-color-scheme: dark)' ).addEventListener( 'change', function () {
			if ( 'system' === state.mode ) {
				applyAccent( state.accent );
			}
		} );
	}
} )();
