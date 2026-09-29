/**
 * Admin Skin — commands for WordPress's own Command Palette (Ctrl/⌘ + K).
 *
 * Core ships the palette; the skin already styles it. What it does not do is
 * know anything about UnysonPlus, so the one shortcut that reaches every
 * screen could not reach the screens this plugin adds. This registers them,
 * rather than building a second palette next to core's.
 *
 * The commands are supplied by PHP (fwAdminSkinCommands), which is what keeps
 * capability checks and page slugs on the server where they belong: a screen
 * the viewer cannot open is never sent, so it can never be offered.
 */
( function () {
	'use strict';

	var cfg = window.fwAdminSkinCommands || {};
	var list = Array.isArray( cfg.commands ) ? cfg.commands : [];

	if ( ! window.wp || ! wp.data || ! wp.data.dispatch ) {
		return;
	}

	var store = wp.data.dispatch( 'core/commands' );

	// Core registered the store only from WP 6.3. Older admins simply get no
	// commands rather than a console error.
	if ( ! store || 'function' !== typeof store.registerCommand ) {
		return;
	}

	function go( url ) {
		return function () {
			window.location.href = url;
		};
	}

	list.forEach( function ( cmd ) {
		if ( ! cmd || ! cmd.name || ! cmd.label || ! cmd.url ) {
			return;
		}
		store.registerCommand( {
			name: cmd.name,
			label: cmd.label,
			searchLabel: cmd.searchLabel || cmd.label,
			icon: cmd.icon || undefined,
			callback: go( cmd.url )
		} );
	} );

	/* ---- Appearance commands -------------------------------------------
	 *
	 * These change the skin rather than navigate, so they are defined here
	 * and not in the list above. They reuse the same AJAX endpoint the
	 * Appearance menu uses, so a change made from the palette is saved the
	 * same way and survives the reload. */

	function savePref( data ) {
		if ( ! cfg.ajaxUrl || ! cfg.nonce ) {
			return Promise.resolve();
		}
		var body = new FormData();
		body.append( 'action', 'fw_admin_skin_prefs' );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return window.fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } );
	}

	if ( false !== cfg.userPrefs ) {
		var i18n = cfg.i18n || {};
		var html = document.documentElement;

		store.registerCommand( {
			name: 'unysonplus/toggle-mode',
			label: i18n.toggleMode || 'Toggle light / dark mode',
			callback: function ( args ) {
				var next = 'dark' === html.getAttribute( 'data-upa-mode' ) ? 'light' : 'dark';
				html.setAttribute( 'data-upa-mode', next );
				savePref( { mode: next } );
				if ( args && args.close ) {
					args.close();
				}
			}
		} );

		store.registerCommand( {
			name: 'unysonplus/toggle-density',
			label: i18n.toggleDensity || 'Toggle comfortable / compact density',
			callback: function ( args ) {
				var next = 'compact' === html.getAttribute( 'data-upa-density' ) ? 'comfortable' : 'compact';
				html.setAttribute( 'data-upa-density', next );
				savePref( { density: next } );
				if ( args && args.close ) {
					args.close();
				}
			}
		} );
	}
}() );
