<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Admin Skin: a token-driven skin over the real wp-admin.
 *
 * Nothing here replaces a screen. The extension enqueues three CSS layers
 * (tokens resolved from the active skin.json, the structure layer for the
 * sidebar / top bar / canvas, the primitives layer for tables, boxes, forms
 * and notices) plus one small vanilla script that regroups the menu, builds
 * the top bar, collects notices and persists the viewer's mode and accent.
 * Because the markup underneath is still core's, every screen — core,
 * UnysonPlus, WooCommerce, any plugin — keeps working and is restyled at once.
 */
class FW_Extension_Admin_Skin extends FW_Extension {

	/** User-meta key for per-user preferences (mode, accent, off). */
	const USER_META = 'fw_admin_skin_prefs';

	/** Colour-scheme id registered with wp_admin_css_color(). */
	const COLOR_SCHEME = 'unysonplus';

	/** @var FW_Admin_Skin_Registry */
	private $registry;

	/** @var bool|null memoised "does the skin apply to this request". */
	private $enabled = null;

	/**
	 * @internal
	 */
	public function _init() {
		require_once $this->get_declared_path( '/includes/class-fw-admin-skin-registry.php' );
		require_once $this->get_declared_path( '/includes/menu-groups.php' );

		$this->registry = new FW_Admin_Skin_Registry( $this );

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', [ $this, '_action_register_color_scheme' ] );
		add_filter( 'get_user_option_admin_color', [ $this, '_filter_admin_color' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, '_action_enqueue' ], 5 );
		add_action( 'admin_head', [ $this, '_action_head_mode_script' ], 0 );

		// The Customizer is skinned through its OWN path, not is_enabled().
		// It gets the tokens plus one scoped stylesheet and never the structure
		// layer: there is no #adminmenu or #wpadminbar in there to restyle, and
		// the preview iframe beside the controls is front-end output, so it is
		// left exactly as the visitor will see it.
		add_action( 'customize_controls_enqueue_scripts', [ $this, '_action_enqueue_customizer' ] );
		add_action( 'customize_controls_print_scripts', [ $this, '_action_customizer_mode_script' ], 0 );
		add_filter( 'admin_body_class', [ $this, '_filter_body_class' ] );
		add_action( 'wp_ajax_fw_admin_skin_prefs', [ $this, '_ajax_save_prefs' ] );

		// Core screens that do not apply to the active theme.
		add_action( 'admin_menu', [ $this, '_action_hide_menu_items' ], 999 );
		add_action( 'admin_init', [ $this, '_action_guard_hidden_screens' ] );
		add_action( 'admin_notices', [ $this, '_action_hidden_screen_notice' ] );
		add_action( 'admin_notices', [ $this, '_action_intro_notice' ] );
		add_action( 'wp_ajax_fw_admin_skin_dismiss_intro', [ $this, '_ajax_dismiss_intro' ] );
		add_filter( 'tiny_mce_before_init', [ $this, '_filter_tinymce_dark_canvas' ] );

		// The way back for a user who switched to the classic admin.
		add_action( 'personal_options', [ $this, '_action_profile_field' ] );
		add_action( 'personal_options_update', [ $this, '_action_profile_save' ] );
		add_action( 'admin_bar_menu', [ $this, '_action_admin_bar_restore' ], 90 );
	}

	/**
	 * Should a screen that only serves block themes be hidden?
	 *
	 * 'auto' (the default) hides it only while a CLASSIC theme is active, so
	 * it comes back by itself the moment the site switches to a block theme.
	 *
	 * @param string $which 'design' (Site Editor) or 'fonts' (Font Library)
	 */
	public function hides_screen( $which ) {
		$settings = $this->get_settings();
		$mode     = isset( $settings[ 'hide_' . $which ] ) ? $settings[ 'hide_' . $which ] : 'auto';

		if ( 'no' === $mode ) {
			$hide = false;
		} elseif ( 'yes' === $mode ) {
			$hide = true;
		} else {
			$hide = function_exists( 'wp_is_block_theme' ) ? ! wp_is_block_theme() : true;
		}

		/**
		 * Filters whether the admin skin hides a block-theme-only screen
		 * ('design' = the Site Editor, 'fonts' = the Font Library).
		 */
		return (bool) apply_filters( 'fw_ext_admin_skin_hide_screen', $hide, $which, $mode );
	}

	/**
	 * Remove the menu items for screens that do not apply to this theme.
	 */
	public function _action_hide_menu_items() {
		if ( $this->hides_screen( 'design' ) ) {
			remove_submenu_page( 'themes.php', 'site-editor.php' );
		}
		if ( $this->hides_screen( 'fonts' ) ) {
			remove_submenu_page( 'themes.php', 'font-library.php' );
		}
	}

	/**
	 * Hiding the menu item leaves the URL live, and a plugin may deep-link to
	 * it, so send those requests somewhere useful instead of a dead end.
	 */
	public function _action_guard_hidden_screens() {
		global $pagenow;

		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$map = [
			'site-editor.php'  => 'design',
			'font-library.php' => 'fonts',
		];

		if ( ! isset( $map[ $pagenow ] ) || ! $this->hides_screen( $map[ $pagenow ] ) ) {
			return;
		}

		$to = admin_url( 'themes.php?page=fw-settings' );

		/** Filters where a hidden block-theme-only screen redirects to. */
		$to = apply_filters( 'fw_ext_admin_skin_hidden_screen_redirect', $to, $map[ $pagenow ] );

		wp_safe_redirect( add_query_arg( 'fw-admin-skin-hidden', $map[ $pagenow ], $to ) );
		exit;
	}

	/**
	 * Opt-in: tint the classic editor's writing area in dark mode.
	 *
	 * That area is an IFRAME rendering the theme's editor styles, i.e. a
	 * preview of the published page, which is why this is off by default.
	 * The style goes in through TinyMCE's own content_style so it lands
	 * inside the iframe and nowhere else — Gutenberg and the front end are
	 * untouched. The viewer's mode is read server-side: "system" cannot be
	 * resolved in PHP, so it also gets the media query.
	 */
	public function _filter_tinymce_dark_canvas( $init ) {
		$settings = $this->get_settings();

		if ( empty( $settings['dark_canvas'] ) || ! $this->is_enabled() ) {
			return $init;
		}

		$mode = $this->get_mode();

		if ( 'light' === $mode ) {
			return $init;
		}

		$skin = $this->registry->resolve( $settings['skin'] );
		$dark = isset( $skin['tokens']['dark'] ) ? $skin['tokens']['dark'] : [];
		$bg   = isset( $dark['panel'] ) ? $dark['panel'] : '#161619';
		$fg   = isset( $dark['text'] ) ? $dark['text'] : '#ececee';
		$link = isset( $dark['accent2'] ) ? $dark['accent2'] : '#928afa';

		$css = sprintf(
			'html,body.mce-content-body{background:%1$s !important;color:%2$s !important;}'
			. 'body.mce-content-body a{color:%3$s;}'
			. 'body.mce-content-body h1,body.mce-content-body h2,body.mce-content-body h3,'
			. 'body.mce-content-body h4,body.mce-content-body h5,body.mce-content-body h6,'
			. 'body.mce-content-body strong,body.mce-content-body th{color:%2$s;}'
			. 'body.mce-content-body blockquote{color:%2$s;border-left-color:%3$s;}'
			. 'body.mce-content-body hr{border-color:rgba(255,255,255,.18);}'
			. 'body.mce-content-body code,body.mce-content-body pre{background:rgba(255,255,255,.07);color:%2$s;}',
			$bg,
			$fg,
			$link
		);

		if ( 'system' === $mode ) {
			$css = '@media (prefers-color-scheme: dark){' . $css . '}';
		}

		$init['content_style'] = ( isset( $init['content_style'] ) ? $init['content_style'] . ' ' : '' ) . $css;

		return $init;
	}

	/**
	 * Say why, once, after a redirect from a hidden screen.
	 */
	public function _action_hidden_screen_notice() {
		if ( empty( $_GET['fw-admin-skin-hidden'] ) ) {
			return;
		}

		$which = sanitize_key( wp_unslash( $_GET['fw-admin-skin-hidden'] ) );
		$label = 'fonts' === $which ? __( 'Fonts', 'fw' ) : __( 'Design', 'fw' );

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: %s: the hidden screen's name, e.g. "Design". */
				__( 'Appearance → %s only applies to block themes, so it is hidden while a classic theme is active. Styling for this theme lives here, in Theme Settings. You can change this in Unyson+ → Extensions → Admin Skin.', 'fw' ),
				$label
			) )
		);
	}

	/**
	 * Profile → Personal Options: a checkbox that mirrors the per-user "off".
	 */
	public function _action_profile_field( $user ) {
		if ( ! $this->get_settings()['allow_user_prefs'] || (int) $user->ID !== get_current_user_id() ) {
			return;
		}
		$prefs = $this->get_user_prefs( $user->ID );
		?>
		<tr class="fw-admin-skin-profile">
			<th scope="row"><?php esc_html_e( 'Admin skin', 'fw' ); ?></th>
			<td>
				<label for="fw_admin_skin_on">
					<input type="checkbox" name="fw_admin_skin_on" id="fw_admin_skin_on" value="1" <?php checked( empty( $prefs['off'] ) ); ?> />
					<?php esc_html_e( 'Use the UnysonPlus admin skin (untick for the classic WordPress admin)', 'fw' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	public function _action_profile_save( $user_id ) {
		if ( (int) $user_id !== get_current_user_id() || ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}
		if ( ! $this->get_settings()['allow_user_prefs'] ) {
			return;
		}
		$prefs = $this->get_user_prefs( $user_id );
		if ( empty( $_POST['fw_admin_skin_on'] ) ) {
			$prefs['off'] = 1;
		} else {
			unset( $prefs['off'] );
		}
		update_user_meta( $user_id, self::USER_META, $prefs );
	}

	/**
	 * When the viewer has opted out, keep a one-click way back in the bar.
	 */
	public function _action_admin_bar_restore( $wp_admin_bar ) {
		if ( ! is_admin() || $this->is_enabled() ) {
			return;
		}
		$prefs = $this->get_user_prefs();
		if ( empty( $prefs['off'] ) ) {
			return;
		}
		$wp_admin_bar->add_node( [
			'id'     => 'fw-admin-skin-restore',
			'parent' => 'top-secondary',
			'title'  => __( 'Turn on admin skin', 'fw' ),
			'href'   => wp_nonce_url( admin_url( 'admin-ajax.php?action=fw_admin_skin_prefs&off=0&redirect=1' ), 'fw_admin_skin_prefs', 'nonce' ),
		] );
	}

	/** @return FW_Admin_Skin_Registry */
	public function registry() {
		return $this->registry;
	}

	/**
	 * Extension settings with defaults applied.
	 */
	public function get_settings() {
		$values = (array) fw_get_db_ext_settings_option( $this->get_name() );

		$defaults = [
			'skin'             => 'default',
			'default_mode'     => 'light',
			'accent'           => '',
			'allow_user_prefs' => true,
			'group_menu'       => true,
			'notice_tray'      => true,
			'sidebar_search'   => true,
			'show_wp_logo'     => false,
			'apply_to_editor'  => true,
			'skin_customizer'  => true,
			'dark_canvas'      => false,
			'hide_design'      => 'auto',
			'hide_fonts'       => 'auto',
		];

		$out = [];
		foreach ( $defaults as $k => $d ) {
			$v = isset( $values[ $k ] ) ? $values[ $k ] : $d;
			$out[ $k ] = is_bool( $d ) ? (bool) $v : $v;
		}

		if ( 'default' !== $out['skin'] && null === $this->registry->resolve( $out['skin'] ) ) {
			$out['skin'] = 'default';
		}

		return $out;
	}

	/**
	 * The viewer's own preferences (mode / accent / off), all optional.
	 */
	public function get_user_prefs( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$prefs   = $user_id ? get_user_meta( $user_id, self::USER_META, true ) : [];
		return is_array( $prefs ) ? $prefs : [];
	}

	/**
	 * Does the skin apply to the current admin request? The Customizer and
	 * the Site Editor draw their own chrome; builder canvases are full-screen
	 * apps; a user may have switched the skin off for themselves.
	 */
	public function is_enabled() {
		if ( null !== $this->enabled ) {
			return $this->enabled;
		}

		global $pagenow;

		$enabled = is_admin() && is_user_logged_in();

		if ( $enabled && in_array( $pagenow, [ 'customize.php', 'site-editor.php' ], true ) ) {
			$enabled = false;
		}

		if ( $enabled && ( isset( $_GET['fw-live-editor'] ) || isset( $_GET['elementor-preview'] ) || isset( $_GET['bricks'] ) ) ) {
			$enabled = false;
		}

		if ( $enabled ) {
			$settings = $this->get_settings();
			if ( ! $settings['apply_to_editor'] && in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
				$enabled = false;
			}
			if ( $settings['allow_user_prefs'] ) {
				$prefs = $this->get_user_prefs();
				if ( ! empty( $prefs['off'] ) ) {
					$enabled = false;
				}
			}
		}

		/** Filters whether the admin skin applies to the current request. */
		$this->enabled = (bool) apply_filters( 'fw_ext_admin_skin_enabled', $enabled );

		return $this->enabled;
	}

	/**
	 * The mode the viewer sees: light | dark | system.
	 */
	public function get_mode() {
		$settings = $this->get_settings();
		$mode     = $settings['default_mode'];

		if ( $settings['allow_user_prefs'] ) {
			$prefs = $this->get_user_prefs();
			if ( ! empty( $prefs['mode'] ) ) {
				$mode = $prefs['mode'];
			}
		}

		return in_array( $mode, [ 'light', 'dark', 'system' ], true ) ? $mode : 'light';
	}

	/**
	 * The accent hex that overrides the skin's, or '' for the skin default.
	 */
	public function get_accent() {
		$settings = $this->get_settings();
		$accent   = $settings['accent'];

		if ( $settings['allow_user_prefs'] ) {
			$prefs = $this->get_user_prefs();
			if ( ! empty( $prefs['accent'] ) ) {
				$accent = $prefs['accent'];
			}
		}

		return preg_match( '/^#[0-9a-f]{6}$/i', (string) $accent ) ? strtolower( $accent ) : '';
	}

	/**
	 * Register one wp-admin colour scheme so core's scheme-aware chrome
	 * (admin bar, menu, Gutenberg's --wp-admin-theme-color) follows the skin.
	 * The scheme stylesheet is ours and reads the tokens, so it is tiny.
	 */
	public function _action_register_color_scheme() {
		$settings = $this->get_settings();
		$skin     = $this->registry->resolve( $settings['skin'] );
		$colors   = isset( $skin['wp_color_scheme']['light'] ) ? $skin['wp_color_scheme']['light'] : [ '#f5f5f7', '#ffffff', '#5b4fe6', '#7a70f2' ];

		wp_admin_css_color(
			self::COLOR_SCHEME,
			__( 'UnysonPlus Admin Skin', 'fw' ),
			fw_min_uri( $this->get_declared_URI( '/static/css/colors.css' ) ),
			$colors,
			[ 'base' => '#8d8d97', 'focus' => '#ffffff', 'current' => '#ffffff' ]
		);
	}

	/**
	 * While the skin applies, every user is on our scheme; their own choice
	 * is untouched in the database and returns the moment the skin is off.
	 */
	public function _filter_admin_color( $value ) {
		if ( $this->is_enabled() ) {
			return self::COLOR_SCHEME;
		}

		// The Customizer runs its own path, but core's chrome in there is
		// scheme-aware too, so without this its accent stays the stock blue
		// while everything around it is the skin's.
		if ( is_customize_preview() && $this->is_customizer_enabled() ) {
			return self::COLOR_SCHEME;
		}

		return $value;
	}

	public function _filter_body_class( $classes ) {
		if ( ! $this->is_enabled() ) {
			return $classes;
		}
		$settings = $this->get_settings();
		$classes .= ' upa upa-skin-' . sanitize_html_class( $settings['skin'] );
		if ( $settings['group_menu'] ) {
			$classes .= ' upa-grouped';
		}
		return $classes;
	}

	/**
	 * Set html[data-upa-mode] before anything paints. Runs at admin_head 0 so
	 * it precedes every stylesheet's first paint; the CSS keys off the
	 * attribute, and "system" is a media query, so there is no flash.
	 */
	public function _action_head_mode_script() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		printf(
			'<script>document.documentElement.setAttribute("data-upa-mode",%s);</script>' . "\n",
			wp_json_encode( $this->get_mode() )
		);
	}

	public function _action_enqueue( $hook ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$settings = $this->get_settings();
		$skin     = $this->registry->resolve( $settings['skin'] );
		$version  = $this->manifest->get_version();

		if ( ! $skin ) {
			return;
		}

		// 1. Tokens: inline, resolved from the skin (and the viewer's accent).
		wp_register_style( 'fw-admin-skin-tokens', false, [], $version );
		wp_enqueue_style( 'fw-admin-skin-tokens' );
		wp_add_inline_style( 'fw-admin-skin-tokens', $this->registry->css( $skin, $this->get_accent() ) );

		// 2. Structure + 3. primitives. Depend on the tokens so order is fixed,
		// and on 'colors' so they load after core's scheme sheet.
		wp_enqueue_style(
			'fw-admin-skin-structure',
			fw_min_uri( $this->get_declared_URI( '/static/css/structure.css' ) ),
			[ 'fw-admin-skin-tokens', 'colors' ],
			$version
		);
		wp_enqueue_style(
			'fw-admin-skin-primitives',
			fw_min_uri( $this->get_declared_URI( '/static/css/primitives.css' ) ),
			[ 'fw-admin-skin-structure' ],
			$version
		);

		// 4. UnysonPlus surfaces: remaps the framework's own --u-* admin tokens.
		wp_enqueue_style(
			'fw-admin-skin-surfaces',
			fw_min_uri( $this->get_declared_URI( '/static/css/surfaces.css' ) ),
			[ 'fw-admin-skin-primitives' ],
			$version
		);

		// 5. Per-plugin partials, only when that plugin is active.
		foreach ( $this->plugin_partials( $hook ) as $slug ) {
			wp_enqueue_style(
				'fw-admin-skin-plugin-' . $slug,
				fw_min_uri( $this->get_declared_URI( '/static/css/plugins/' . $slug . '.css' ) ),
				[ 'fw-admin-skin-surfaces' ],
				$version
			);
		}

		// 6. A skin's own extra CSS (structure changes), last.
		if ( file_exists( $skin['_dir'] . '/skin.css' ) ) {
			wp_enqueue_style(
				'fw-admin-skin-skin',
				$skin['_url'] . '/skin.css',
				[ 'fw-admin-skin-primitives' ],
				isset( $skin['version'] ) ? $skin['version'] : $version
			);
		}

		// 7. Behaviour.
		wp_enqueue_script(
			'fw-admin-skin',
			fw_min_uri( $this->get_declared_URI( '/static/js/admin-skin.js' ) ),
			[],
			$version,
			true
		);

		$user = wp_get_current_user();

		wp_localize_script( 'fw-admin-skin', 'fwAdminSkin', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'fw_admin_skin_prefs' ),
			'mode'      => $this->get_mode(),
			'accent'    => $this->get_accent(),
			'skin'      => $settings['skin'],
			'skinTitle' => isset( $skin['title'] ) ? $skin['title'] : $settings['skin'],
			'userPrefs' => $settings['allow_user_prefs'],
			'groupMenu' => $settings['group_menu'],
			'noticeTray' => $settings['notice_tray'],
			'sidebarSearch' => $settings['sidebar_search'],
			'showWpLogo' => $settings['show_wp_logo'],
			'groups'    => fw_ext_admin_skin_menu_groups(),
			'siteName'  => get_bloginfo( 'name' ),
			'siteUrl'   => home_url( '/' ),
			'adminUrl'  => admin_url(),
			'logo'      => $this->site_logo_url(),
			'user'      => [
				'name'   => $user->display_name,
				'role'   => $this->user_role_label( $user ),
				'avatar' => get_avatar_url( $user->ID, [ 'size' => 64 ] ),
				'profile' => get_edit_profile_url( $user->ID ),
				'logout' => wp_logout_url(),
			],
			'i18n'      => [
				'search'      => __( 'Search menu…', 'fw' ),
				'notices'     => __( 'notices', 'fw' ),
				'notice'      => __( 'notice', 'fw' ),
				'show'        => __( 'Show', 'fw' ),
				'hide'        => __( 'Hide', 'fw' ),
				'light'       => __( 'Light', 'fw' ),
				'dark'        => __( 'Dark', 'fw' ),
				'system'      => __( 'System', 'fw' ),
				'appearance'  => __( 'Appearance', 'fw' ),
				'accent'      => __( 'Accent', 'fw' ),
				'reset'       => __( 'Skin default', 'fw' ),
				'classic'     => __( 'Use classic wp-admin', 'fw' ),
				'viewSite'    => __( 'Visit site', 'fw' ),
				'collapse'    => __( 'Collapse sidebar', 'fw' ),
				'noMatch'     => __( 'No menu item matches', 'fw' ),
			],
		] );
	}

	/**
	 * Plugin partials to load for this screen, by active plugin + hook.
	 *
	 * @return string[] partial slugs (files in static/css/plugins/)
	 */
	private function plugin_partials( $hook ) {
		$partials = [];

		if ( class_exists( 'WooCommerce' ) ) {
			$partials[] = 'woocommerce';
		}
		if ( class_exists( 'ACF' ) ) {
			$partials[] = 'acf';
		}
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			$partials[] = 'seo';
		}

		$out = [];
		foreach ( array_unique( $partials ) as $slug ) {
			if ( file_exists( $this->get_declared_path( '/static/css/plugins/' . $slug . '.css' ) ) ) {
				$out[] = $slug;
			}
		}

		/** Filters the per-plugin CSS partial slugs loaded on the current admin screen. */
		return apply_filters( 'fw_ext_admin_skin_plugin_partials', $out, $hook );
	}

	/**
	 * The brand mark is 30px square, so only the (square) site icon fits;
	 * a custom logo is usually wide and unreadable at that size.
	 */
	private function site_logo_url() {
		$icon = get_site_icon_url( 128 );
		return $icon ? $icon : '';
	}

	private function user_role_label( $user ) {
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return __( 'Super Admin', 'fw' );
		}
		$roles = (array) $user->roles;
		if ( ! $roles ) {
			return '';
		}
		$wp_roles = wp_roles();
		$role     = reset( $roles );
		return isset( $wp_roles->role_names[ $role ] ) ? translate_user_role( $wp_roles->role_names[ $role ] ) : $role;
	}

	/**
	 * Save the viewer's mode / accent / off preference from the top bar.
	 */
	public function _ajax_save_prefs() {
		check_ajax_referer( 'fw_admin_skin_prefs', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Not logged in.', 'fw' ) ], 403 );
		}

		$settings = $this->get_settings();
		if ( ! $settings['allow_user_prefs'] ) {
			wp_send_json_error( [ 'message' => __( 'Per-user preferences are disabled.', 'fw' ) ], 403 );
		}

		$prefs = $this->get_user_prefs();

		// The "Turn on admin skin" bar link: a GET that flips "off" and goes back.
		if ( isset( $_GET['redirect'] ) && isset( $_GET['off'] ) ) {
			unset( $prefs['off'] );
			update_user_meta( get_current_user_id(), self::USER_META, $prefs );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
			exit;
		}

		if ( isset( $_POST['mode'] ) ) {
			$mode = sanitize_key( wp_unslash( $_POST['mode'] ) );
			if ( in_array( $mode, [ 'light', 'dark', 'system' ], true ) ) {
				$prefs['mode'] = $mode;
			}
		}

		if ( isset( $_POST['accent'] ) ) {
			$accent = strtolower( sanitize_text_field( wp_unslash( $_POST['accent'] ) ) );
			if ( preg_match( '/^#[0-9a-f]{6}$/', $accent ) ) {
				$prefs['accent'] = $accent;
			} else {
				unset( $prefs['accent'] );
			}
		}

		if ( isset( $_POST['off'] ) ) {
			if ( '1' === (string) $_POST['off'] ) {
				$prefs['off'] = 1;
			} else {
				unset( $prefs['off'] );
			}
		}

		update_user_meta( get_current_user_id(), self::USER_META, $prefs );

		wp_send_json_success( $prefs );
	}

	/**
	 * A one-time hello, shown after the extension is seeded active on a fresh
	 * install (see framework/includes/default-extensions.php).
	 *
	 * Restyling the whole admin without asking is a big visual change, so the
	 * notice exists to say what happened and — more importantly — to name the
	 * two ways back, before the user goes looking for a setting that is not
	 * where they would expect it. It is dismissed permanently, per user.
	 *
	 * @internal
	 */
	public function _action_intro_notice() {
		if ( ! get_option( 'unysonplus_admin_skin_intro_notice' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), 'fw_admin_skin_intro_dismissed', true ) ) {
			return;
		}

		// Built through the manager rather than guessed: the Extensions page slug
		// differs on a network admin, and the per-extension screen is a sub-page
		// of it, not a menu page of its own.
		// Built through the manager rather than guessed: the per-extension screen
		// is a SUB-PAGE of the Extensions page, and both resolve differently in a
		// network admin. get_link() is private, so the list URL comes from the
		// page slug the manager exposes.
		$manager        = fw()->extensions->manager;
		$extensions_url = menu_page_url( $manager->get_page_slug(), false );
		$settings_url   = $manager->get_extension_link( 'admin-skin' ) . '&tab=settings';

		echo '<div class="notice notice-info is-dismissible" data-fw-admin-skin-intro="1"><p><strong>'
			. esc_html__( 'The UnysonPlus Admin Skin is on.', 'fw' ) . '</strong> '
			. esc_html__( 'Your WordPress admin has a refreshed look: a grouped sidebar, a slim top bar, and light / dark / system modes you can switch from the palette icon in the top bar.', 'fw' )
			. '</p><p>'
			. sprintf(
				/* translators: 1: link to the extension settings, 2: link to the Extensions manager */
				wp_kses(
					__( 'Prefer the original WordPress admin? Switch just yourself back from your <a href="%1$s">profile</a>, or turn it off for the whole site in <a href="%2$s">Unyson+ &rarr; Extensions</a>.', 'fw' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_url( admin_url( 'profile.php' ) ),
				esc_url( $extensions_url )
			)
			. ' '
			. sprintf(
				/* translators: %s: link to the Admin Skin settings screen */
				wp_kses(
					__( 'Its own options live in <a href="%s">Admin Skin settings</a>.', 'fw' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_url( $settings_url )
			)
			. '</p></div>';

		// Dismissing a core notice is a client-side affair, so the X has to tell
		// us about it or the notice returns on the next page load.
		$nonce = wp_create_nonce( 'fw_admin_skin_intro' );
		echo '<script>jQuery(function($){$(document).on("click","[data-fw-admin-skin-intro] .notice-dismiss",function(){'
			. '$.post(ajaxurl,{action:"fw_admin_skin_dismiss_intro",nonce:"' . esc_js( $nonce ) . '"});});});</script>';
	}

	/**
	 * @internal
	 */
	public function _ajax_dismiss_intro() {
		check_ajax_referer( 'fw_admin_skin_intro', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), 'fw_admin_skin_intro_dismissed', 1 );
		wp_send_json_success();
	}


	/**
	 * Does the skin apply to the Customizer's control pane?
	 *
	 * Deliberately separate from is_enabled(), which stays false on
	 * customize.php. The full skin cannot run there: its structure layer
	 * rebuilds #adminmenu and #wpadminbar into a sidebar and top bar, and the
	 * Customizer has neither, so loading it would restyle nothing and risk
	 * breaking the pane's fixed layout. This path loads the tokens plus one
	 * scoped stylesheet instead.
	 *
	 * The per-user "off" switch still wins, so a user on the classic admin
	 * gets the classic Customizer too.
	 */
	public function is_customizer_enabled() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$settings = $this->get_settings();

		if ( empty( $settings['skin_customizer'] ) ) {
			return false;
		}

		if ( $settings['allow_user_prefs'] ) {
			$prefs = $this->get_user_prefs();
			if ( ! empty( $prefs['off'] ) ) {
				return false;
			}
		}

		/** Filters whether the skin applies to the Customizer's control pane. */
		return (bool) apply_filters( 'fw_ext_admin_skin_customizer_enabled', true );
	}

	/**
	 * Tokens + the scoped Customizer stylesheet, on the CONTROLS frame only.
	 *
	 * `customize_controls_enqueue_scripts` fires for the controls document and
	 * not for the preview iframe, which is what keeps the front-end preview
	 * untouched. There is deliberately no `customize_preview_init` counterpart.
	 *
	 * @internal
	 */
	public function _action_enqueue_customizer() {
		if ( ! $this->is_customizer_enabled() ) {
			return;
		}

		$settings = $this->get_settings();
		$skin     = $this->registry->resolve( $settings['skin'] );
		$version  = $this->manifest->get_version();

		if ( ! $skin ) {
			return;
		}

		wp_register_style( 'fw-admin-skin-customizer-tokens', false, [], $version );
		wp_enqueue_style( 'fw-admin-skin-customizer-tokens' );
		wp_add_inline_style( 'fw-admin-skin-customizer-tokens', $this->registry->css( $skin, $this->get_accent() ) );

		wp_enqueue_style(
			'fw-admin-skin-customizer',
			fw_min_uri( $this->get_declared_URI( '/static/css/customizer.css' ) ),
			[ 'fw-admin-skin-customizer-tokens' ],
			$version
		);
	}

	/**
	 * Set html[data-upa-mode] and the body class on the controls frame before
	 * it paints. The body class is `upa-customizer`, NOT `upa`: every rule in
	 * structure.css / primitives.css is scoped to `body.upa`, so this keeps
	 * the admin layers from reaching a screen they were not written for.
	 *
	 * @internal
	 */
	public function _action_customizer_mode_script() {
		if ( ! $this->is_customizer_enabled() ) {
			return;
		}

		$settings = $this->get_settings();

		// This hook prints in <head>, where document.body does not exist yet, so
		// the class is deferred while the MODE attribute is set immediately —
		// the CSS keys off the attribute plus core's own body.wp-customizer, so
		// it is fully styled on first paint. The class is for extenders only.
		printf(
			'<script>(function(d){d.documentElement.setAttribute("data-upa-mode",%s);' .
			'var f=function(){d.body.classList.add("upa-customizer","upa-skin-%s");};' .
			'if(d.body){f();}else{d.addEventListener("DOMContentLoaded",f);}})(document);</script>' . "
",
			wp_json_encode( $this->get_mode() ),
			esc_js( sanitize_html_class( $settings['skin'] ) )
		);
	}

}
