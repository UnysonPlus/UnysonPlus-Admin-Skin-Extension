<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Admin Menu layout — a UI for the thing the skin already does.
 *
 * `includes/menu-groups.php` decides which group each top-level menu item
 * sits under. It is a static map with a filter, so a developer could always
 * rearrange the sidebar; a site owner could not. This adds the missing half:
 * a screen where the grouping, the order and the visibility of every item can
 * be set, PER ROLE, and stored.
 *
 * Two things are deliberately split:
 *
 * - **Grouping and order are presentation**, applied by the same client-side
 *   pass that already groups the menu (the saved values are merged into the
 *   config handed to the script, so there is one implementation, not two).
 * - **Hiding is server-side**, with `remove_menu_page()` plus a redirect for
 *   anyone who reaches the URL anyway. A menu item removed in the browser is
 *   still a working screen, and pretending otherwise would be the kind of
 *   security theatre this plugin should not ship.
 *
 * Even so, hiding is **not** access control, and the screen says so: a user
 * who still holds the capability can reach a subpage by URL. Roles and
 * capabilities are what actually restrict; this decides what is on show.
 */
class FW_Admin_Skin_Menu_Layout {

	const OPTION     = 'fw_admin_skin_menu_layout';
	const CAPABILITY = 'manage_options';

	/** @var FW_Extension_Admin_Skin */
	private $ext;

	/** @var array|null Top-level items as they were before we touched them. */
	private $snapshot = null;

	public function __construct( $ext ) {
		$this->ext = $ext;

		// 998: after plugins have registered their menus, and before the
		// skin's own removals at 999 — so the screen can list an item it is
		// currently hiding, which is the only way to get it back.
		add_action( 'admin_menu', [ $this, '_capture_menu' ], 998 );
		add_action( 'admin_menu', [ $this, '_apply_hidden' ], 999 );
		add_action( 'admin_init', [ $this, '_guard_hidden' ] );
		add_filter( 'fw_ext_admin_skin_menu_groups', [ $this, '_filter_groups' ] );
	}

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------- */

	/**
	 * The whole saved layout: role => [ map, order, hidden, labels ].
	 */
	public function get_all() {
		$saved = get_option( self::OPTION, [] );
		return is_array( $saved ) ? $saved : [];
	}

	/**
	 * The layout that applies to one role (default: the current user's).
	 *
	 * A user with several roles gets the FIRST of their roles that has a
	 * layout saved. Merging two layouts would produce an order nobody chose,
	 * and silently picking the most restrictive would hide things without
	 * anyone having asked for that.
	 */
	public function get_for_role( $role = null ) {
		$all = $this->get_all();

		if ( null !== $role ) {
			return isset( $all[ $role ] ) ? $this->normalise( $all[ $role ] ) : $this->normalise( [] );
		}

		$user = wp_get_current_user();
		foreach ( (array) $user->roles as $r ) {
			if ( isset( $all[ $r ] ) ) {
				return $this->normalise( $all[ $r ] );
			}
		}

		return $this->normalise( [] );
	}

	private function normalise( $layout ) {
		$layout = is_array( $layout ) ? $layout : [];
		return [
			'map'    => isset( $layout['map'] ) && is_array( $layout['map'] ) ? $layout['map'] : [],
			'order'  => isset( $layout['order'] ) && is_array( $layout['order'] ) ? $layout['order'] : [],
			'hidden' => isset( $layout['hidden'] ) && is_array( $layout['hidden'] ) ? array_values( $layout['hidden'] ) : [],
			'labels' => isset( $layout['labels'] ) && is_array( $layout['labels'] ) ? $layout['labels'] : [],
			'slugs'  => isset( $layout['slugs'] ) && is_array( $layout['slugs'] ) ? $layout['slugs'] : [],
		];
	}

	/* ---------------------------------------------------------------------
	 * Reading the live menu
	 * ------------------------------------------------------------------- */

	/**
	 * The key an item is known by.
	 *
	 * This has to match what the browser sees, because the grouping pass runs
	 * there against the rendered <li>. WordPress builds that id the same way:
	 * the menu row's own id when it has one, otherwise a slug derived from the
	 * page.
	 */
	public static function item_key( $item ) {
		if ( ! empty( $item[5] ) ) {
			return $item[5];
		}
		$slug = isset( $item[2] ) ? $item[2] : '';
		return 'toplevel_page_' . sanitize_title_with_dashes( remove_accents( $slug ) );
	}

	/**
	 * @internal
	 */
	public function _capture_menu() {
		global $menu;

		$out  = [];
		$rows = (array) $menu;

		// $menu is keyed by POSITION, and plugins insert at arbitrary keys, so
		// its natural array order is registration order rather than the order
		// the sidebar actually shows. Sorting by key is what makes this screen
		// list items the way the menu does.
		ksort( $rows, SORT_NUMERIC );

		foreach ( $rows as $item ) {
			if ( empty( $item[0] ) || ! isset( $item[2] ) ) {
				continue; // separators
			}
			if ( false !== strpos( (string) $item[4], 'wp-menu-separator' ) ) {
				continue;
			}

			// The title carries a count bubble as markup, and the bubble nests
			// its own screen-reader span -- so a non-greedy match to the first
			// </span> leaves the tail behind ("Comments 0 Comments in
			// moderation"). Menu titles put the bubble last, so everything
			// from it onwards goes.
			$title = preg_replace(
				'#<span[^>]*class="[^"]*(?:update-plugins|awaiting-mod|update-count|plugin-count)[^"]*".*$#s',
				'',
				(string) $item[0]
			);
			$title = trim( wp_strip_all_tags( $title ) );

			$out[] = [
				'key'   => self::item_key( $item ),
				'title' => $title,
				'slug'  => (string) $item[2],
			];
		}

		$this->snapshot = $out;
	}

	/**
	 * Every top-level item, with anything this screen is hiding folded back
	 * in so it can be un-hidden.
	 */
	public function get_items() {
		return null === $this->snapshot ? [] : $this->snapshot;
	}

	/* ---------------------------------------------------------------------
	 * Applying
	 * ------------------------------------------------------------------- */

	/**
	 * @internal
	 */
	public function _apply_hidden() {
		if ( ! $this->ext->is_enabled() ) {
			return;
		}

		$layout = $this->get_for_role();

		foreach ( $layout['hidden'] as $key ) {
			foreach ( (array) $GLOBALS['menu'] as $item ) {
				if ( self::item_key( $item ) === $key && isset( $item[2] ) ) {
					remove_menu_page( $item[2] );
					break;
				}
			}
		}
	}

	/**
	 * A hidden screen reached by URL goes to the dashboard rather than
	 * rendering. Only the top-level slug is matched: a subpage of a hidden
	 * section is a different screen, and guessing at those would start
	 * blocking things nobody chose to hide.
	 *
	 * @internal
	 */
	public function _guard_hidden() {
		global $pagenow;

		if ( wp_doing_ajax() || ! $this->ext->is_enabled() ) {
			return;
		}

		$layout = $this->get_for_role();
		if ( ! $layout['hidden'] ) {
			return;
		}

		foreach ( $layout['hidden'] as $key ) {
			$slug = isset( $layout['slugs'][ $key ] ) ? $layout['slugs'][ $key ] : '';
			if ( $slug && $this->request_matches( $slug, $pagenow ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}
	}

	/**
	 * Does the current request BE the screen this menu slug points at?
	 *
	 * Menu slugs come in three shapes and a single string comparison only ever
	 * handled one of them: a plugin page ("fw-extensions"), a core file
	 * ("upload.php"), and a core file with a query that is part of its
	 * identity ("edit.php?post_type=snippet" -- where matching edit.php alone
	 * would take Posts down with it).
	 */
	private function request_matches( $slug, $pagenow ) {
		if ( false !== strpos( $slug, '?' ) ) {
			list( $file, $query ) = explode( '?', $slug, 2 );

			if ( $file !== $pagenow ) {
				return false;
			}

			$args = [];
			parse_str( $query, $args );

			foreach ( $args as $k => $v ) {
				if ( ! isset( $_GET[ $k ] ) || (string) $v !== (string) wp_unslash( $_GET[ $k ] ) ) {
					return false;
				}
			}

			return true;
		}

		if ( '.php' === substr( $slug, -4 ) ) {
			// A bare core file is only that screen when no page= rides on it.
			return $slug === $pagenow && ! isset( $_GET['page'] );
		}

		return isset( $_GET['page'] ) && $slug === sanitize_text_field( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Merge the saved layout into the group config the script receives, so
	 * the browser keeps its single grouping implementation and simply gets a
	 * better map.
	 *
	 * @internal
	 */
	public function _filter_groups( $config ) {
		$layout = $this->get_for_role();

		if ( $layout['map'] ) {
			// The saved assignments go FIRST: menu-groups.php matches its keys
			// in order, so an override has to be seen before the default it
			// replaces.
			$config['map'] = $layout['map'] + ( isset( $config['map'] ) ? $config['map'] : [] );
		}

		foreach ( $layout['labels'] as $group => $label ) {
			if ( isset( $config['groups'][ $group ] ) && '' !== trim( (string) $label ) ) {
				$config['groups'][ $group ] = $label;
			}
		}

		$config['order'] = $layout['order'];

		return $config;
	}

	/**
	 * Is this the menu that holds the screen doing the hiding?
	 *
	 * The Admin Skin's own page lives under the Unyson+ menu, so hiding that
	 * menu for a role that can edit this layout would take the editor away
	 * with it -- and the only way back would be the database. Hiding it for a
	 * role that could never open this screen anyway (an editor, an author) is
	 * exactly what the feature is for, so that stays allowed.
	 */
	private function is_lifeline( $item, $role ) {
		$key  = isset( $item['key'] ) ? $item['key'] : '';
		$slug = isset( $item['slug'] ) ? $item['slug'] : '';

		$is_parent = ( 'toplevel_page_fw-extensions' === $key || 'fw-extensions' === $slug );

		if ( ! $is_parent ) {
			return false;
		}

		$role_obj = get_role( $role );

		return $role_obj && $role_obj->has_cap( self::CAPABILITY );
	}

	/* ---------------------------------------------------------------------
	 * The screen
	 * ------------------------------------------------------------------- */

	/**
	 * @internal
	 */
	public function maybe_save() {
		if ( empty( $_POST['fw_admin_skin_menu_nonce'] ) ) {
			return;
		}

		check_admin_referer( 'fw_admin_skin_menu', 'fw_admin_skin_menu_nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'fw' ) );
		}

		$role   = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		$roles  = wp_roles()->get_names();

		if ( ! isset( $roles[ $role ] ) ) {
			return;
		}

		$groups = fw_ext_admin_skin_menu_groups();
		$valid  = array_keys( $groups['groups'] );

		$map    = [];
		$slugs  = [];
		$order  = [];
		$hidden = [];
		$labels = [];

		$posted = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : [];
		$i      = 0;

		foreach ( $posted as $key => $row ) {
			$key = sanitize_text_field( $key );
			if ( '' === $key ) {
				continue;
			}

			$group = isset( $row['group'] ) ? sanitize_key( $row['group'] ) : '';
			if ( in_array( $group, $valid, true ) ) {
				$map[ $key ] = $group;
			}

			$order[ $key ] = $i++;

			if ( ! empty( $row['slug'] ) ) {
				$slugs[ $key ] = sanitize_text_field( $row['slug'] );
			}

			if ( ! empty( $row['hidden'] ) && ! $this->is_lifeline( [ 'key' => $key, 'slug' => isset( $row['slug'] ) ? $row['slug'] : '' ], $role ) ) {
				$hidden[] = $key;
			}
		}

		$posted_labels = isset( $_POST['labels'] ) && is_array( $_POST['labels'] ) ? wp_unslash( $_POST['labels'] ) : [];
		foreach ( $posted_labels as $group => $label ) {
			$group = sanitize_key( $group );
			$label = sanitize_text_field( $label );
			if ( in_array( $group, $valid, true ) && '' !== $label && $label !== $groups['groups'][ $group ] ) {
				$labels[ $group ] = $label;
			}
		}

		$all = $this->get_all();

		if ( isset( $_POST['reset'] ) ) {
			unset( $all[ $role ] );
		} else {
			$all[ $role ] = [
				'map'    => $map,
				'order'  => $order,
				'hidden' => $hidden,
				'labels' => $labels,
				// The slug travels with the layout because the guard runs on
				// admin_init, which is BEFORE admin_menu -- so at guard time
				// there is no live menu to look a slug up in.
				'slugs'  => $slugs,
			];
		}

		update_option( self::OPTION, $all, false );

		wp_safe_redirect( add_query_arg(
			[ 'page' => FW_Admin_Skin_Settings_Page::PAGE_SLUG, 'role' => $role, 'updated' => 1 ],
			admin_url( 'admin.php' )
		) . '#menu_tab' );
		exit;
	}

	/**
	 * @internal
	 */
	public function render_tab() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$roles  = wp_roles()->get_names();
		$role   = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : 'administrator';
		if ( ! isset( $roles[ $role ] ) ) {
			$role = key( $roles );
		}

		$groups = fw_ext_admin_skin_menu_groups();
		$layout = $this->get_for_role( $role );
		$items  = $this->get_items();

		// Saved order first, then anything the map has never seen (a plugin
		// installed since the layout was saved) in its own order, so a new
		// menu item appears rather than vanishing.
		usort( $items, function ( $a, $b ) use ( $layout ) {
			$ai = isset( $layout['order'][ $a['key'] ] ) ? $layout['order'][ $a['key'] ] : PHP_INT_MAX;
			$bi = isset( $layout['order'][ $b['key'] ] ) ? $layout['order'][ $b['key'] ] : PHP_INT_MAX;
			return $ai === $bi ? 0 : ( $ai < $bi ? -1 : 1 );
		} );
		?>
		<div class="fw-ams-menu">
			<p class="fw-ams-lede">
				<?php esc_html_e( 'Choose which group each menu item sits under, drag to reorder, and hide what a role does not need. Each role keeps its own layout.', 'fw' ); ?>
			</p>

			<form method="get" class="fw-ams-rolebar">
				<input type="hidden" name="page" value="<?php echo esc_attr( FW_Admin_Skin_Settings_Page::PAGE_SLUG ); ?>" />
				<label for="fw-ams-role"><?php esc_html_e( 'Editing layout for', 'fw' ); ?></label>
				<select name="role" id="fw-ams-role" onchange="this.form.submit()">
					<?php foreach ( $roles as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $role ); ?>>
							<?php echo esc_html( translate_user_role( $name ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<noscript><button class="button"><?php esc_html_e( 'Switch', 'fw' ); ?></button></noscript>
			</form>

			<form method="post">
				<?php wp_nonce_field( 'fw_admin_skin_menu', 'fw_admin_skin_menu_nonce' ); ?>
				<input type="hidden" name="role" value="<?php echo esc_attr( $role ); ?>" />

				<h2><?php esc_html_e( 'Group names', 'fw' ); ?></h2>
				<div class="fw-ams-labels">
					<?php foreach ( $groups['groups'] as $key => $label ) : ?>
						<label>
							<span><?php echo esc_html( $label ); ?></span>
							<input type="text" name="labels[<?php echo esc_attr( $key ); ?>]"
								value="<?php echo esc_attr( isset( $layout['labels'][ $key ] ) ? $layout['labels'][ $key ] : '' ); ?>"
								placeholder="<?php echo esc_attr( $label ); ?>" />
						</label>
					<?php endforeach; ?>
				</div>

				<h2><?php esc_html_e( 'Items', 'fw' ); ?></h2>
				<ul class="fw-ams-list">
					<?php foreach ( $items as $item ) :
						$key    = $item['key'];
						$group  = isset( $layout['map'][ $key ] ) ? $layout['map'][ $key ] : '';
						$is_hid = in_array( $key, $layout['hidden'], true );
						$locked = $this->is_lifeline( $item, $role );
						?>
						<li class="fw-ams-item<?php echo $is_hid ? ' is-hidden' : ''; ?>">
							<span class="fw-ams-handle" aria-hidden="true">⋮⋮</span>
							<span class="fw-ams-title"><?php echo esc_html( $item['title'] ); ?></span>
							<code class="fw-ams-slug"><?php echo esc_html( $item['slug'] ); ?></code>
							<input type="hidden" name="items[<?php echo esc_attr( $key ); ?>][slug]" value="<?php echo esc_attr( $item['slug'] ); ?>" />
							<select name="items[<?php echo esc_attr( $key ); ?>][group]">
								<option value=""><?php esc_html_e( '— default —', 'fw' ); ?></option>
								<?php foreach ( $groups['groups'] as $gkey => $glabel ) : ?>
									<option value="<?php echo esc_attr( $gkey ); ?>" <?php selected( $gkey, $group ); ?>>
										<?php echo esc_html( $glabel ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<label class="fw-ams-hide<?php echo $locked ? ' is-locked' : ''; ?>"
								<?php if ( $locked ) : ?>title="<?php esc_attr_e( 'This menu holds the screen you are on. Hiding it for a role that can edit this layout would leave no way back.', 'fw' ); ?>"<?php endif; ?>>
								<input type="checkbox" name="items[<?php echo esc_attr( $key ); ?>][hidden]" value="1" <?php checked( $is_hid ); ?> <?php disabled( $locked ); ?> />
								<?php esc_html_e( 'Hide', 'fw' ); ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>

				<p class="fw-ams-note">
					<strong><?php esc_html_e( 'Hiding is not access control.', 'fw' ); ?></strong>
					<?php esc_html_e( 'A hidden item is removed from the menu and its top-level URL redirects to the dashboard, but a user who still holds the capability can reach a subpage directly. Use roles and capabilities to restrict what someone may do; use this to decide what they see.', 'fw' ); ?>
				</p>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save layout', 'fw' ); ?></button>
					<button type="submit" name="reset" value="1" class="button"><?php esc_html_e( 'Reset this role', 'fw' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}
}
