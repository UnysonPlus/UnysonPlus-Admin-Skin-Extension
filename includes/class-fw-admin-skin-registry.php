<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Skin registry: discovers skin packages, resolves inheritance and turns a
 * skin into the CSS custom properties the structure / primitives layers read.
 *
 * A skin is a folder holding a skin.json (see skins/default/skin.json for the
 * full shape) plus optional fonts/ and skin.css. Bundled skins live in the
 * extension's skins/ folder; installed ones (from the Skin Library) live in
 * uploads/unysonplus/admin-skins/<slug>/ and shadow a bundled skin of the same
 * slug. Every value in a skin is optional when `base` names another skin: the
 * child is merged over its parent key by key, so a skin can be nothing more
 * than a new accent.
 */
class FW_Admin_Skin_Registry {

	/** CSS custom-property prefix. */
	const PREFIX = '--upa-';

	/** @var array<string, array>|null slug => raw skin.json + _dir/_url */
	private $skins = null;

	/** @var FW_Extension_Admin_Skin */
	private $ext;

	public function __construct( FW_Extension_Admin_Skin $ext ) {
		$this->ext = $ext;
	}

	/**
	 * Every discoverable skin, keyed by slug. Installed skins shadow bundled
	 * ones so a Library update to "default" wins over the shipped copy.
	 *
	 * @return array<string, array>
	 */
	public function all() {
		if ( null !== $this->skins ) {
			return $this->skins;
		}

		$this->skins = [];

		$dirs = [
			[ $this->ext->get_declared_path( '/skins' ), $this->ext->get_declared_URI( '/skins' ) ],
		];

		if ( function_exists( 'fw_upw_uploads_dir' ) ) {
			$up = fw_upw_uploads_dir( 'admin-skins' );
			if ( ! empty( $up['path'] ) ) {
				$dirs[] = [ $up['path'], $up['url'] ];
			}
		}

		foreach ( $dirs as list( $dir, $url ) ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			foreach ( glob( rtrim( $dir, '/\\' ) . '/*/skin.json' ) as $file ) {
				$data = json_decode( (string) file_get_contents( $file ), true );
				if ( ! is_array( $data ) || empty( $data['slug'] ) ) {
					continue;
				}
				$slug = sanitize_key( $data['slug'] );
				$data['_dir'] = dirname( $file );
				$data['_url'] = rtrim( $url, '/' ) . '/' . basename( dirname( $file ) );
				$data['_bundled'] = ( $dir === $dirs[0][0] );
				$this->skins[ $slug ] = $data;
			}
		}

		/**
		 * Filters the discovered admin skins (slug => skin.json data) so a
		 * theme or extension can register one from its own folder.
		 */
		$this->skins = apply_filters( 'fw_ext_admin_skin_skins', $this->skins );

		return $this->skins;
	}

	/**
	 * Skin metadata for a select option: slug => title.
	 */
	public function choices() {
		$out = [];
		foreach ( $this->all() as $slug => $skin ) {
			$out[ $slug ] = isset( $skin['title'] ) ? $skin['title'] : $slug;
		}
		return $out;
	}

	/**
	 * A skin with its base chain merged in (child over parent, recursively).
	 *
	 * @return array|null
	 */
	public function resolve( $slug, $depth = 0 ) {
		$skins = $this->all();
		$slug  = sanitize_key( $slug );

		if ( ! isset( $skins[ $slug ] ) ) {
			return null;
		}

		$skin = $skins[ $slug ];

		if ( ! empty( $skin['base'] ) && $skin['base'] !== $slug && $depth < 5 ) {
			$base = $this->resolve( $skin['base'], $depth + 1 );
			if ( $base ) {
				// Assets (fonts / css) resolve against the skin that declares them.
				$skin = $this->merge( $base, $skin );
			}
		}

		return $skin;
	}

	private function merge( array $base, array $over ) {
		foreach ( $over as $k => $v ) {
			if ( is_array( $v ) && isset( $base[ $k ] ) && is_array( $base[ $k ] ) && ! isset( $v[0] ) ) {
				$base[ $k ] = $this->merge( $base[ $k ], $v );
			} else {
				$base[ $k ] = $v;
			}
		}
		return $base;
	}

	/**
	 * The inline <style> block for a resolved skin: @font-face rules, a
	 * :root block of mode-independent tokens, and one block per mode. The
	 * mode is selected by html[data-upa-mode]; "system" maps through a media
	 * query so there is no flash and no JS dependency for the colours.
	 *
	 * @param array       $skin   from resolve()
	 * @param string|null $accent optional per-user accent hex overriding both modes
	 */
	public function css( array $skin, $accent = null ) {
		$css = '';

		// Fonts.
		if ( ! empty( $skin['fonts']['files'] ) && is_array( $skin['fonts']['files'] ) ) {
			foreach ( $skin['fonts']['files'] as $f ) {
				if ( empty( $f['family'] ) || empty( $f['src'] ) ) {
					continue;
				}
				$src = preg_match( '#^https?://#', $f['src'] ) ? $f['src'] : $skin['_url'] . '/' . ltrim( $f['src'], '/' );
				$css .= sprintf(
					"@font-face{font-family:'%s';src:url('%s') format('woff2');font-weight:%s;font-style:%s;font-display:swap}\n",
					esc_attr( $f['family'] ),
					esc_url( $src ),
					esc_attr( isset( $f['weight'] ) ? $f['weight'] : '400' ),
					esc_attr( isset( $f['style'] ) ? $f['style'] : 'normal' )
				);
			}
		}

		// Mode-independent tokens.
		$root = [];
		$root['font-ui']   = isset( $skin['fonts']['ui'] ) ? $skin['fonts']['ui'] : 'system-ui, sans-serif';
		$root['font-mono'] = isset( $skin['fonts']['mono'] ) ? $skin['fonts']['mono'] : 'ui-monospace, monospace';
		foreach ( [ 'shape', 'density' ] as $group ) {
			if ( ! empty( $skin[ $group ] ) && is_array( $skin[ $group ] ) ) {
				foreach ( $skin[ $group ] as $k => $v ) {
					$root[ $k ] = $v;
				}
			}
		}
		$css .= ':root{' . $this->declarations( $root ) . "}\n";

		// Mode tokens.
		$light = $this->mode_tokens( $skin, 'light', $accent );
		$dark  = $this->mode_tokens( $skin, 'dark', $accent );

		$css .= ':root,html[data-upa-mode="light"]{' . $this->declarations( $light ) . "color-scheme:light}\n";
		$css .= 'html[data-upa-mode="dark"]{' . $this->declarations( $dark ) . "color-scheme:dark}\n";
		$css .= '@media (prefers-color-scheme:dark){html[data-upa-mode="system"]{' . $this->declarations( $dark ) . "color-scheme:dark}}\n";

		return $css;
	}

	private function mode_tokens( array $skin, $mode, $accent ) {
		$t = isset( $skin['tokens'][ $mode ] ) && is_array( $skin['tokens'][ $mode ] ) ? $skin['tokens'][ $mode ] : [];

		if ( $accent && preg_match( '/^#[0-9a-f]{6}$/i', $accent ) ) {
			$t['accent']  = $accent;
			$t['accent2'] = self::lighten( $accent, 'dark' === $mode ? 0.18 : 0.14 );
			$t['accent-fg'] = self::contrast_fg( $accent );
		}

		// Derived tokens so every skin gets consistent soft/ring/text variants
		// without spelling them out.
		if ( ! empty( $t['accent'] ) ) {
			$t['accent-soft'] = 'color-mix(in srgb, var(--upa-accent) 14%, transparent)';
			$t['accent-ring'] = 'color-mix(in srgb, var(--upa-accent) 32%, transparent)';
		}
		foreach ( [ 'green', 'amber', 'red', 'blue' ] as $c ) {
			if ( ! empty( $t[ $c ] ) && empty( $t[ $c . '-text' ] ) ) {
				$t[ $c . '-text' ] = $t[ $c ];
			}
			if ( ! empty( $t[ $c ] ) ) {
				$t[ $c . '-soft' ] = 'color-mix(in srgb, var(--upa-' . $c . ') 14%, transparent)';
			}
		}

		return $t;
	}

	private function declarations( array $vars ) {
		$out = '';
		foreach ( $vars as $k => $v ) {
			$k = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $k ) );
			$v = (string) $v;
			// Values are CSS fragments; keep them on one line and free of braces.
			$v = str_replace( [ '{', '}', ';', "\n", "\r" ], '', $v );
			$out .= self::PREFIX . $k . ':' . $v . ';';
		}
		return $out;
	}

	/** Mix a hex colour towards white by $amount (0..1). */
	public static function lighten( $hex, $amount ) {
		list( $r, $g, $b ) = sscanf( $hex, '#%02x%02x%02x' );
		$r = (int) round( $r + ( 255 - $r ) * $amount );
		$g = (int) round( $g + ( 255 - $g ) * $amount );
		$b = (int) round( $b + ( 255 - $b ) * $amount );
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/** Black or white text for a hex background, by WCAG relative luminance. */
	public static function contrast_fg( $hex ) {
		list( $r, $g, $b ) = sscanf( $hex, '#%02x%02x%02x' );
		$lin = function ( $c ) {
			$c = $c / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		$l = 0.2126 * $lin( $r ) + 0.7152 * $lin( $g ) + 0.0722 * $lin( $b );
		return $l > 0.4 ? '#111111' : '#ffffff';
	}
}
