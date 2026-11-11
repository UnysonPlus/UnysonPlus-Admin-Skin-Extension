<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/** @var FW_Extension_Admin_Skin $ext */
$ext     = fw_ext( 'admin-skin' );
$choices = $ext ? $ext->registry()->choices() : [ 'default' => 'Default' ];

$bg_presets = [];
foreach ( FW_Extension_Admin_Skin::bg_presets() as $slug => $preset ) {
	$bg_presets[ $slug ] = $preset['label'];
}

$options = [
	'skin_box' => [
		'title'   => __( 'Skin', 'fw' ),
		'type'    => 'box',
		'options' => [
			'group_skin' => [
				'type'    => 'group',
				'options' => [
					'skin'         => [
						'label'   => __( 'Active skin', 'fw' ),
						'desc'    => __( 'The site-wide skin. Bundled skins ship with the extension; installed ones are picked up from the uploads folder.', 'fw' ),
						'type'    => 'select',
						'choices' => $choices,
						'value'   => 'default',
					],
					'default_mode' => [
						'label'   => __( 'Default mode', 'fw' ),
						'desc'    => __( 'What a user sees until they pick their own from the top bar.', 'fw' ),
						'type'    => 'select',
						'choices' => [
							'light'  => __( 'Light', 'fw' ),
							'dark'   => __( 'Dark', 'fw' ),
							'system' => __( 'Follow the operating system', 'fw' ),
						],
						'value'   => 'light',
					],
					'density'     => [
						'label'   => __( 'Density', 'fw' ),
						'desc'    => __( 'How much room the admin gives itself. Compact steps the type, control heights, table rows and sidebar width down from whatever the active skin declares &mdash; the same skin, tightened, not a different one. Most useful on long list tables. Each user can override this from the Appearance menu in the top bar.', 'fw' ),
						'type'    => 'select',
						'choices' => [
							'comfortable' => __( 'Comfortable', 'fw' ),
							'compact'     => __( 'Compact', 'fw' ),
						],
						'value'   => 'comfortable',
					],
					'accent'       => [
						'label' => __( 'Accent colour', 'fw' ),
						'desc'  => __( 'Overrides the skin\'s accent for everyone. Leave empty to keep the skin\'s own.', 'fw' ),
						'type'  => 'color-picker',
						'value' => '',
					],
					'allow_user_prefs' => [
						'label' => __( 'Let users choose', 'fw' ),
						'desc'  => __( 'Each user may pick light / dark / system, their own accent, or switch back to the classic wp-admin from the top bar and their profile.', 'fw' ),
						'type'  => 'switch',
						'value' => true,
					],
				],
			],
		],
	],
	'background_box' => [
		'title'   => __( 'Background', 'fw' ),
		'type'    => 'box',
		'options' => [
			'group_background' => [
				'type'    => 'group',
				'options' => [
					'bg_desc' => [
						'type'  => 'html-fixed',
						'label' => '',
						'html'  => '<p style="margin:0 0 .4em">' . esc_html__( 'The surface the skin sits on. This is not part of the skin, so every skin that accepts one can use it &mdash; and changing the skin keeps your background. Skins that are opaque by design (Console, Classic) ignore it.', 'fw' ) . '</p>',
					],
					/*
					 * One picker, not four always-visible rows. A background is a choice
					 * BETWEEN a preset, a colour and an image -- they are alternatives and
					 * never combined -- so listing all three at once asked the reader to
					 * work out which of them the 'Background' select was actually going to
					 * use. The multi-picker reveals only the chosen one.
					 *
					 * Inline picker (a plain select, no popover), so per the option-type
					 * contract label/desc live on the PICKER sub-option and the top level
					 * carries label:false.
					 *
					 * NOTE this changes the stored shape to
					 * { type: <choice>, <choice>: { ...revealed... } }, and only the
					 * selected branch is kept. bg_choice() in the extension class reads
					 * it and still understands the flat bg_type / bg_preset / bg_color /
					 * bg_image saved before 1.1.67, so an existing background survives
					 * the upgrade untouched.
					 */
					'bg' => [
						'type'  => 'multi-picker',
						'label' => false,
						'desc'  => false,
						'value' => [ 'type' => 'none' ],
						'show_borders' => false,
						'picker' => [
							'type' => [
								'label'   => __( 'Background', 'fw' ),
								'desc'    => __( 'A gradient preset needs nothing uploaded, weighs nothing, and has no detail to compete with text &mdash; it is the safest choice. An image is the most striking and the most risky: see the scrim below.', 'fw' ),
								'type'    => 'select',
								'choices' => [
									'none'   => __( 'None — the skin\'s own flat colour', 'fw' ),
									'preset' => __( 'Gradient preset', 'fw' ),
									'color'  => __( 'A colour of my own', 'fw' ),
									'image'  => __( 'An image', 'fw' ),
								],
								'value'   => 'none',
							],
						],
						'choices' => [
							/* 'none' reveals nothing, so it carries no entry at all. */
							'preset' => [
								'preset' => [
									'label'   => __( 'Preset', 'fw' ),
									'type'    => 'select',
									'choices' => $bg_presets,
									'value'   => 'mesh',
								],
							],
							'color' => [
								'color' => [
									'label' => __( 'Colour', 'fw' ),
									'desc'  => __( 'The flat colour the admin sits on.', 'fw' ),
									'type'  => 'color-picker',
									'value' => '',
								],
							],
							'image' => [
								'image' => [
									'label' => __( 'Image', 'fw' ),
									'desc'  => __( 'Shown full-bleed and held still while the page scrolls. Pick something calm and low-contrast: this sits behind screens people read all day, and a busy photograph is the one thing that can make an admin genuinely hard to use. Raise the scrim until the text below is comfortable.', 'fw' ),
									'type'  => 'upload',
									'images_only' => true,
									'value' => '',
								],
								'behind' => [
									'label' => __( 'Colour behind it', 'fw' ),
									'desc'  => __( 'Shown while the image loads, and anywhere the image does not reach. Something close to the image\'s overall tone keeps the page from flashing a different colour on the way in.', 'fw' ),
									'type'  => 'color-picker',
									'value' => '',
								],
							],
						],
					],
					'bg_scrim' => [
						'label' => __( 'Scrim', 'fw' ),
						'desc'  => __( 'A veil laid over the background to keep text readable &mdash; light in light mode, dark in dark mode. This is the guard rail: at 0 the background comes through at full strength, which almost no photograph survives. Raise it until the quietest text on screen is comfortable.', 'fw' ),
						'type'  => 'slider',
						'value' => 34,
						'properties' => [ 'min' => 0, 'max' => 80, 'step' => 1, 'postfix' => '%' ],
					],
					'bg_blur' => [
						'label' => __( 'Glass blur', 'fw' ),
						'desc'  => __( 'How far a frosted skin blurs what is behind it. Only skins that frost their panels use this &mdash; Glass does. Blur is drawn by the graphics card on every scroll, so lower it if the admin feels heavy on an older machine; 0 leaves the panels simply translucent.', 'fw' ),
						'type'  => 'slider',
						'value' => 18,
						'properties' => [ 'min' => 0, 'max' => 40, 'step' => 1, 'postfix' => 'px' ],
					],
					'allow_user_bg' => [
						'label' => __( 'Let users turn it off', 'fw' ),
						'desc'  => __( 'Adds a Plain background switch to each person\'s Appearance menu. Someone who finds a background distracting can drop back to the skin\'s flat colour without changing what anyone else sees.', 'fw' ),
						'type'  => 'switch',
						'value' => true,
					],
				],
			],
		],
	],
	'layout_box' => [
		'title'   => __( 'Layout', 'fw' ),
		'type'    => 'box',
		'options' => [
			'group_layout' => [
				'type'    => 'group',
				'options' => [
					'group_menu'     => [
						'label' => __( 'Group the sidebar', 'fw' ),
						'desc'  => __( 'Sort the admin menu into Workspace, Shop, Tools, Manage and Plugins headings instead of one long list. Order inside a group follows WordPress.', 'fw' ),
						'type'  => 'switch',
						'value' => true,
					],
					'sidebar_search' => [
						'label' => __( 'Sidebar search', 'fw' ),
						'desc'  => __( 'A filter field at the top of the sidebar that narrows the menu as you type.', 'fw' ),
						'type'  => 'switch',
						'value' => true,
					],
					'notice_tray'    => [
						'label' => __( 'Collect notices', 'fw' ),
						'desc'  => __( 'Gather plugin and core notices into one collapsible tray at the top of the page instead of a stack of coloured bars. Dismiss buttons keep working.', 'fw' ),
						'type'  => 'switch',
						'value' => true,
					],
					'apply_to_editor' => [
						'label' => __( 'Skin the block editor chrome', 'fw' ),
						'desc'  => __( 'Apply the sidebar and top bar on post editing screens. The editing canvas itself is never touched.', 'fw' ),
						'type'  => 'switch',
						'value' => true,
					],
					'account_placement' => [
						'label'   => __( 'Account menu', 'fw' ),
						'desc'    => __( 'Where the account avatar and its menu (profile, visit site, log out) live. WordPress puts it top-right; app-style admins put it bottom-left. Whichever you pick, items other plugins add to the account menu come along &mdash; they are mirrored, not dropped.', 'fw' ),
						'type'    => 'select',
						'choices' => [
							'sidebar' => __( 'Bottom left only (sidebar)', 'fw' ),
							'bar'     => __( 'Top right only (WordPress default position)', 'fw' ),
							'both'    => __( 'Both', 'fw' ),
						],
						'value'   => 'sidebar',
					],
					'skin_customizer' => [
						'label' => __( 'Skin the Customizer', 'fw' ),
						'desc'  => __( 'Apply the skin to the Customizer&rsquo;s left control pane. The preview beside it is never touched &mdash; it renders your front end, so it has to keep looking like the published page.', 'fw' ),
						'type'  => 'switch',
						'value' => true,
					],
					'skin_login'     => [
						'label' => __( 'Skin the login screen', 'fw' ),
						'desc'  => __( 'Apply the skin to wp-login.php, so signing in looks like the admin it leads to rather than stock WordPress. The mark above the form becomes your site icon, or your site name when there is no icon, and links to your home page instead of wordpress.org. Nobody is logged in on that screen, so it uses the site&rsquo;s default mode and accent above, never a per-user choice.', 'fw' ),
						'type'  => 'switch',
						'value' => true,
					],
					'dark_canvas'    => [
						'label' => __( 'Dark editor canvas', 'fw' ),
						'desc'  => __( 'In dark mode, tint the classic editor&rsquo;s writing area too. Off by default: that area is an iframe rendering your FRONT-END styles, so it previews how the post will really look &mdash; on a dark canvas the preview no longer matches the published page, and text colours picked there can turn out unreadable on the live site. Gutenberg and the front end are never touched.', 'fw' ),
						'type'  => 'switch',
						'value' => false,
					],
					'hide_design'    => [
						'label'   => __( 'Hide Appearance → Design', 'fw' ),
						'desc'    => __( 'The Site Editor. On a classic theme it can only edit Styles and Patterns, and its Styles panel writes global styles the theme ignores — styling lives in Theme Settings. Auto hides it only while a classic theme is active, so it returns by itself on a block theme. The URL is redirected too, not just the menu item.', 'fw' ),
						'type'    => 'select',
						'choices' => [
							'auto'  => __( 'Auto — hide on classic themes', 'fw' ),
							'yes'   => __( 'Always hide', 'fw' ),
							'no'    => __( 'Never hide', 'fw' ),
						],
						'value'   => 'auto',
					],
					'hide_fonts'     => [
						'label'   => __( 'Hide Appearance → Fonts', 'fw' ),
						'desc'    => __( 'The Font Library only applies to block themes; the theme loads its own fonts from Theme Settings → Typography.', 'fw' ),
						'type'    => 'select',
						'choices' => [
							'auto'  => __( 'Auto — hide on classic themes', 'fw' ),
							'yes'   => __( 'Always hide', 'fw' ),
							'no'    => __( 'Never hide', 'fw' ),
						],
						'value'   => 'auto',
					],
					'show_wp_logo'   => [
						'label' => __( 'Keep the WordPress logo', 'fw' ),
						'desc'  => __( 'Show the W logo menu in the top bar.', 'fw' ),
						'type'  => 'switch',
						'value' => true,
					],
				],
			],
		],
	],
];
