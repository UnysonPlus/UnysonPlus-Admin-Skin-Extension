<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$manifest = [];

$manifest['name']        = __( 'Admin Skin', 'fw' );
$manifest['slug']        = 'unysonplus-admin-skin';
$manifest['description'] = __(
	'A modern, token-driven skin for the whole WordPress admin: grouped sidebar, slim top bar, card tables, a skinned login screen, and light / dark / system modes with a per-user accent and density. The sidebar itself is editable per role. Every screen keeps working because it is still wp-admin underneath.',
	'fw'
);

$manifest['thumbnail']   = 'thumbnail.svg';

$manifest['version']     = '1.1.51';
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
 * 1.1.50 - One Admin Skin screen instead of two, and a menu you cannot hide
 *         yourself behind. The settings lived three clicks deep in the
 *         Extensions manager while the menu editor was its own submenu entry,
 *         which invited the question of which screen a setting was on. Both
 *         are now tabs on a single page under Unyson+ (Settings / Admin Menu),
 *         with the Extensions-manager card pointed at it through
 *         fw_ext_manager_settings_url -- the same shape the SEO, Shortcodes,
 *         Site Converter and WooCommerce extensions already use, so there is
 *         one settings screen rather than two that can disagree. The settings
 *         are still the extension's own schema and store, so nothing that
 *         reads a setting had to change.
 *
 *         The second half matters more: the menu editor sits UNDER the Unyson+
 *         menu, so hiding that menu would have taken the editor away with it
 *         and left the database as the only way back. The Unyson+ row's Hide
 *         box is now refused -- but only for a role that could open this
 *         screen at all. Hiding it from an editor or an author is exactly what
 *         the feature is for and stays allowed. Enforced on save as well as in
 *         the markup, since a disabled checkbox is a hint, not a rule.
 *
 * 1.1.49 - An editor for the sidebar menu, per role (Unyson+ -> Admin Menu).
 *         The group map in includes/menu-groups.php was static with a filter,
 *         so a developer could rearrange the sidebar and a site owner could
 *         not. The screen lists every top-level item and sets its group, its
 *         order (drag) and whether it shows, saved per role -- which is what
 *         makes it useful to an agency: a client signs in to Pages, Posts and
 *         Media, while the administrator keeps the full menu. Group names can
 *         be renamed too. Grouping and order are merged into the config the
 *         existing client-side pass already consumes, so there is one
 *         implementation rather than two; hiding is server-side
 *         (remove_menu_page plus a redirect), because an item removed only in
 *         the browser is still a working screen. That redirect matches a menu
 *         slug in all three shapes WordPress uses -- a plugin page, a core
 *         file, and a core file whose query is part of its identity
 *         ("edit.php?post_type=snippet", where matching edit.php alone would
 *         take Posts down with it) -- and the slug is stored WITH the layout,
 *         since the guard runs on admin_init, before any menu exists to look
 *         one up in. The screen says plainly that hiding is not access
 *         control: a user who holds the capability can still reach a subpage
 *         by URL. New filter: the existing fw_ext_admin_skin_menu_groups now
 *         receives the saved layout.
 *
 * 1.1.48 - UnysonPlus entries in WordPress's own Command Palette. Core ships
 *         the palette and the skin already styled it, but it knew nothing
 *         about this plugin -- so the one shortcut that reaches every screen
 *         could not reach the screens UnysonPlus adds. Theme Settings,
 *         Extensions and the Admin Skin's own settings are registered as
 *         commands, plus two that act rather than navigate: toggle light /
 *         dark and toggle comfortable / compact, both saved through the same
 *         endpoint the Appearance menu uses so a change made from the palette
 *         survives the reload. The list is built in PHP behind capability
 *         checks -- a screen the viewer cannot open is never sent, so it can
 *         never be offered and then refused -- and is filterable via
 *         fw_ext_admin_skin_commands. Nothing registers before WP 6.3, which
 *         is where the palette's store arrived.
 *
 * 1.1.47 - Notices that stay dismissed. The tray collected notices but forgot
 *         everything on reload, so the same nag returned on every page load
 *         and the only escape was the plugin that sent it. Each notice in the
 *         tray now carries a mute control, and muted ones are kept out of the
 *         count and hidden until asked for. Keyed by a hash of the notice's
 *         own text -- there is nothing else stable to key on, since notices
 *         rarely carry an id and share their classes with every plugin -- with
 *         digits normalised so a counter ("3 updates available") does not mint
 *         a new key each time. Reworded text is a different notice and comes
 *         back, which is the safer direction for something a user asked never
 *         to see. Muted notices stay in the list rather than being deleted, so
 *         the footer can offer them back; stored per user (never site-wide)
 *         and capped so a noisy install cannot grow user meta without bound.
 *
 * 1.1.46 - Comfortable / compact density. Every skin already declared density
 *         tokens (font-size, control-h, row-h, sidebar-w, bar-h) and nothing
 *         let a user choose between them, so the admin had exactly one size.
 *         Compact is now a per-user choice in the Appearance menu, with a
 *         site default in the settings. The registry derives the compact set
 *         from whatever the skin declares (a skin may also spell out its own
 *         `density_compact` in skin.json and have the last word), and emits it
 *         as an `html[data-upa-density="compact"]` override block beside
 *         :root -- so switching is one attribute and needs no reload, the same
 *         trick the light/dark modes use. Floors keep a compact skin usable
 *         whatever a skin declares. One token had to be connected up on the
 *         way: `--upa-row-h` was declared by every skin and consumed by
 *         nothing, so list-table rows ignored density entirely -- the one
 *         place the setting earns its keep. Their vertical padding is now
 *         derived from it, at a value identical to the previous constant when
 *         comfortable.
 *
 * 1.1.45 - Skin the login screen. Until now the skin stopped at the door: a
 *         user signed in through stock WordPress and landed in a skinned
 *         admin, which made the login page the most visibly unskinned surface
 *         on the site. wp-login.php now loads the same resolved --upa-* tokens
 *         plus one scoped stylesheet, so the active skin, the accent and
 *         light / dark / system all arrive without a second palette. It runs
 *         through its own gate (is_login_enabled) rather than is_enabled(),
 *         which requires a logged-in user; for the same reason the screen uses
 *         the SITE default mode and accent, never a per-user preference, since
 *         there is no viewer yet to have one. Only the tokens and the login
 *         stylesheet load -- structure.css and primitives.css are written
 *         against #adminmenu / .postbox and would restyle shared classes for a
 *         layout they were never measured against. The mark above the form
 *         becomes the site icon, or the site name when there is none, and
 *         links home instead of to wordpress.org. Covers the interim-login
 *         modal too. New setting: Skin the login screen (on), plus the filter
 *         fw_ext_admin_skin_login_enabled.
 *
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
