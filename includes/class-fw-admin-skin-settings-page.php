<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * ONE Admin Skin screen, under Unyson+, with the settings and the menu editor
 * as tabs.
 *
 * Before this there were two places: the extension's settings form, three
 * clicks deep in the Extensions manager (Extensions → Admin Skin → Settings),
 * and a separate "Admin Menu" submenu entry. Two screens for one extension
 * invite the obvious question of which one a setting lives on, and the menu
 * editor in particular reads as a stray page rather than part of the skin.
 *
 * The settings are still the extension's own — the same `settings-options.php`
 * schema and the same `fw_get_db_ext_settings_option()` store — so nothing that
 * reads a setting needs to know this page exists. Only the way you reach them
 * changes, and the Extensions-manager card is pointed here too (via
 * `fw_ext_manager_settings_url`), so there is one settings screen rather than
 * two that can disagree.
 *
 * Shape copied from the SEO and WooCommerce settings pages — native WordPress
 * nav-tabs, the options rendered into a metabox-holder, saving on `load-`
 * before any output — because a settings screen that styles itself differently
 * from its neighbours reads as broken.
 */
class FW_Admin_Skin_Settings_Page {

	const PARENT_SLUG = 'fw-extensions';
	const PAGE_SLUG   = 'fw-admin-skin';
	const CAPABILITY  = 'manage_options';
	const NONCE       = 'fw_admin_skin_settings_save';

	/** @var FW_Extension_Admin_Skin */
	protected $ext;

	/** @var string|null */
	protected $hook_suffix = null;

	public function __construct( $ext ) {
		$this->ext = $ext;

		// 20: after the Unyson+ parent exists. The menu-layout class captures
		// the menu at 998 and applies its own removals at 999, so this sits
		// well clear of both.
		add_action( 'admin_menu', [ $this, '_action_admin_menu' ], 20 );
		add_filter( 'fw_ext_manager_settings_url', [ $this, '_filter_manager_url' ], 10, 2 );
	}

	public static function url( $tab = '' ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		return $tab ? $url . '#' . $tab : $url;
	}

	/**
	 * Send the Extensions-manager card's "Settings" link here.
	 *
	 * @internal
	 */
	public function _filter_manager_url( $url, $name ) {
		return 'admin-skin' === $name && current_user_can( self::CAPABILITY ) ? self::url() : $url;
	}

	/**
	 * @internal
	 */
	public function _action_admin_menu() {
		if ( ! $this->ext->is_enabled() || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$this->hook_suffix = add_submenu_page(
			self::PARENT_SLUG,
			__( 'Admin Skin', 'fw' ),
			__( 'Admin Skin', 'fw' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);

		if ( $this->hook_suffix ) {
			add_action( 'load-' . $this->hook_suffix, [ $this, '_action_maybe_save' ] );
			add_action( 'admin_enqueue_scripts', [ $this, '_action_enqueue' ] );
		}
	}

	/**
	 * The option types render nothing usable without their own JS/CSS, and the
	 * menu tab needs a sortable.
	 *
	 * @internal
	 */
	public function _action_enqueue( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		fw()->backend->enqueue_options_static( $this->ext->get_settings_options() );

		$version = $this->ext->manifest->get_version();

		wp_enqueue_style(
			'fw-admin-skin-menu-layout',
			fw_min_uri( $this->ext->get_declared_URI( '/static/css/menu-layout.css' ) ),
			[],
			$version
		);

		wp_enqueue_script(
			'fw-admin-skin-menu-layout',
			fw_min_uri( $this->ext->get_declared_URI( '/static/js/menu-layout.js' ) ),
			[ 'jquery', 'jquery-ui-sortable' ],
			$version,
			true
		);
	}

	/**
	 * Both tabs post to this screen, so both saves are handled here — each
	 * behind its own nonce, so neither form can be submitted through the
	 * other's.
	 *
	 * @internal
	 */
	public function _action_maybe_save() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// The Admin Menu tab keeps its own handler; it has a different shape
		// (per-role, row order from the DOM) and its own nonce.
		if ( isset( $_POST['fw_admin_skin_menu_nonce'] ) ) {
			$this->ext->menu_layout->maybe_save();
			return;
		}

		check_admin_referer( self::NONCE );

		$before = (array) fw_get_db_ext_settings_option( 'admin-skin' );

		// Merged over what is stored rather than written wholesale: a future
		// partial form must not silently drop the keys it never showed.
		$values = array_merge(
			$before,
			fw_get_options_values_from_input( $this->ext->get_settings_options() )
		);

		fw_set_db_ext_settings_option( 'admin-skin', null, $values );

		/**
		 * Preserved from the Extensions-manager save path, so anything already
		 * listening for these settings keeps working now the form lives here.
		 */
		do_action( 'fw_extension_settings_form_saved:admin-skin', $before );

		wp_safe_redirect( add_query_arg( 'fw-saved', '1', self::url() ) );
		exit;
	}

	/**
	 * @internal
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$values = (array) fw_get_db_ext_settings_option( 'admin-skin' );
		?>
		<div class="wrap fw-ext-admin-skin-settings">
			<h1><?php esc_html_e( 'Admin Skin', 'fw' ); ?></h1>

			<?php if ( ! empty( $_GET['fw-saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'fw' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Menu layout saved.', 'fw' ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper fw-ams-tabs" style="margin:.4em 0 1.4em">
				<a href="#settings_tab" class="nav-tab nav-tab-active" data-tab="settings_tab"><?php esc_html_e( 'Settings', 'fw' ); ?></a>
				<a href="#menu_tab" class="nav-tab" data-tab="menu_tab"><?php esc_html_e( 'Admin Menu', 'fw' ); ?></a>
			</h2>

			<div class="fw-ams-panel" data-panel="settings_tab">
				<form method="post">
					<?php wp_nonce_field( self::NONCE ); ?>
					<div class="metabox-holder">
						<?php echo fw()->backend->render_options( (array) $this->ext->get_settings_options(), $values ); ?>
					</div>
					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'fw' ); ?></button>
					</p>
				</form>
			</div>

			<div class="fw-ams-panel" data-panel="menu_tab" hidden>
				<?php $this->ext->menu_layout->render_tab(); ?>
			</div>
		</div>

		<script>
		( function () {
			var wrap = document.querySelector( '.fw-ext-admin-skin-settings' );
			if ( ! wrap ) { return; }

			function show( tab ) {
				wrap.querySelectorAll( '.fw-ams-tabs .nav-tab' ).forEach( function ( a ) {
					a.classList.toggle( 'nav-tab-active', a.getAttribute( 'data-tab' ) === tab );
				} );
				wrap.querySelectorAll( '.fw-ams-panel' ).forEach( function ( p ) {
					p.hidden = p.getAttribute( 'data-panel' ) !== tab;
				} );
			}

			wrap.querySelectorAll( '.fw-ams-tabs .nav-tab' ).forEach( function ( a ) {
				a.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					var tab = a.getAttribute( 'data-tab' );
					show( tab );
					// A hash, so a reload or a save redirect comes back to the
					// tab the person was on rather than to the first one.
					if ( window.history && history.replaceState ) {
						history.replaceState( null, '', '#' + tab );
					}
				} );
			} );

			var initial = ( window.location.hash || '' ).replace( '#', '' );
			if ( initial && wrap.querySelector( '.fw-ams-panel[data-panel="' + initial + '"]' ) ) {
				show( initial );
			}
		}() );
		</script>
		<?php
	}
}
