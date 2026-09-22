<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Sidebar group map: which top-level admin menu items sit under which group
 * heading. Keys are matched (in order) against a menu item's <li> id, its
 * classes and its first link's href, so the same table covers core screens
 * ("menu-posts"), post types ("menu-posts-product"), and plugin pages
 * ("toplevel_page_wc-admin", "admin.php?page=fw-extensions").
 *
 * Items nothing matches land in the trailing "plugins" group in their
 * original order, so a plugin the map has never heard of is still reachable
 * and still grouped sensibly (it is, after all, a plugin). Post types not
 * listed are treated as content and go to Workspace.
 *
 * @return array{groups: array<string,string>, map: array<string,string>, cpt_group: string, fallback: string}
 */
function fw_ext_admin_skin_menu_groups() {
	$groups = [
		'workspace' => __( 'Workspace', 'fw' ),
		'shop'      => __( 'Shop', 'fw' ),
		'tools'     => __( 'Tools', 'fw' ),
		'manage'    => __( 'Manage', 'fw' ),
		'plugins'   => __( 'Plugins', 'fw' ),
	];

	$map = [
		// Workspace: the daily content screens.
		'menu-dashboard'             => 'workspace',
		'menu-posts'                 => 'workspace',
		'menu-pages'                 => 'workspace',
		'menu-media'                 => 'workspace',
		'menu-comments'              => 'workspace',
		'menu-posts-fw-portfolio'    => 'workspace',
		'edit.php?post_type=page'    => 'workspace',

		// Shop: WooCommerce and its satellites.
		'toplevel_page_woocommerce'  => 'shop',
		'menu-posts-product'         => 'shop',
		'wc-settings&tab=checkout'   => 'shop',
		'wc-admin&path=/analytics'   => 'shop',
		'toplevel_page_woocommerce-marketing' => 'shop',
		'wc-admin&path=/marketing'   => 'shop',
		'menu-posts-shop_order'      => 'shop',
		'menu-posts-shop_coupon'     => 'shop',

		// Tools: things you run, not things you edit.
		'menu-tools'                 => 'tools',
		'menu-posts-snippet'         => 'tools',
		'toplevel_page_snippets'     => 'tools',
		'toplevel_page_redirection'  => 'tools',
		'toplevel_page_simple_history' => 'tools',
		'toplevel_page_activity_log' => 'tools',
		'toplevel_page_wpcode'       => 'tools',
		'toplevel_page_wp-mail-smtp' => 'tools',
		'toplevel_page_fluent_smtp'  => 'tools',
		'toplevel_page_gf_edit_forms' => 'tools',
		'toplevel_page_wpforms-overview' => 'tools',
		'toplevel_page_fluent_forms' => 'tools',
		'toplevel_page_wpcf7'        => 'tools',
		'menu-posts-forms'           => 'tools',
		'toplevel_page_ai1wm_export' => 'tools',
		'toplevel_page_backwpup'     => 'tools',
		'toplevel_page_duplicator'   => 'tools',
		'toplevel_page_koko-analytics' => 'tools',
		'toplevel_page_wps_overview_page' => 'tools',
		'toplevel_page_googlesitekit-dashboard' => 'tools',
		'toplevel_page_wpseo_dashboard' => 'tools',
		'toplevel_page_rank-math'    => 'tools',

		// Manage: site-level configuration.
		'toplevel_page_fw-extensions' => 'manage',
		'toplevel_page_fw-theme-builder' => 'manage',
		'menu-appearance'            => 'manage',
		'menu-plugins'               => 'manage',
		'menu-users'                 => 'manage',
		'menu-settings'              => 'manage',
		'toplevel_page_elementor'    => 'manage',
		'toplevel_page_bricks'       => 'manage',
		'toplevel_page_acf-options'  => 'manage',
		'menu-posts-acf-field-group' => 'manage',
		'toplevel_page_edit-post_type-acf-field-group' => 'manage',
	];

	$config = [
		'groups'    => $groups,
		'map'       => $map,
		'cpt_group' => 'workspace',
		'fallback'  => 'plugins',
	];

	/**
	 * Filters the sidebar grouping config. `map` is matched by substring
	 * against each item's id, class list and href, in order, so a theme can
	 * route its own post type or a plugin page into a group.
	 */
	return apply_filters( 'fw_ext_admin_skin_menu_groups', $config );
}
