<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$manifest = [];

$manifest['name']        = __( 'Admin Skin', 'fw' );
$manifest['slug']        = 'unysonplus-admin-skin';
$manifest['description'] = __(
	'A modern, token-driven skin for the whole WordPress admin: grouped sidebar, slim top bar, card tables, light / dark / system modes with a per-user accent, and a library of installable skins. Every screen keeps working because it is still wp-admin underneath.',
	'fw'
);

$manifest['thumbnail']   = 'thumbnail.svg';

$manifest['version']     = '1.1.5';
$manifest['display']     = true;
$manifest['standalone']  = true;

// Repository Info
$manifest['github_update'] = 'UnysonPlus/UnysonPlus-Admin-Skin-Extension';
$manifest['github_repo']   = 'https://github.com/UnysonPlus/UnysonPlus-Admin-Skin-Extension';
$manifest['github_branch'] = 'main';

// Author Info
$manifest['author']     = 'UnysonPlus';
$manifest['author_uri'] = 'https://www.lastimosa.com.ph/unysonplus';

// Meta
$manifest['license']      = 'GPL-2.0-or-later';
$manifest['text_domain']  = 'fw';
$manifest['requires_php'] = '7.4';
$manifest['requires_wp']  = '6.0';

/**
 * Changelog
 * -----------------------------------------------------------------------------
 * 1.0.0 - Initial release. A skin over the real wp-admin (not a replacement
 *         app): a design-token layer (neutral + accent ramps, semantic surface
 *         and text tokens, radius, density, two typefaces) resolved from a
 *         skin.json, a structure layer that turns #adminmenu into a grouped
 *         sidebar (Workspace / Shop / Tools / Manage / Plugins, with a live
 *         filter) and the admin bar into a slim top bar, and a primitives
 *         layer for list tables, postboxes, forms, buttons, tabs and notices
 *         (collected into a collapsible tray). Light, dark and system modes
 *         plus an accent colour are chosen per user from the top bar and
 *         stored in user meta; the site-wide skin, default mode and accent
 *         live in the extension settings. Skins are discovered from the
 *         bundled skins/ folder and from uploads/unysonplus/admin-skins/,
 *         inherit through a base key, and register a matching wp-admin colour
 *         scheme so core's scheme-aware chrome follows. The Customizer, the
 *         Site Editor and builder canvases are excluded. Ships Default (the
 *         modern look) and Classic (WordPress density) skins.
 */
