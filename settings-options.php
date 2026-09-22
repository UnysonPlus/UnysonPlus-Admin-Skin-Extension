<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/** @var FW_Extension_Admin_Skin $ext */
$ext     = fw_ext( 'admin-skin' );
$choices = $ext ? $ext->registry()->choices() : [ 'default' => 'Default' ];

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
						'value' => false,
					],
				],
			],
		],
	],
];
