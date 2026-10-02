<?php
/**
 * Marwa Apply child theme.
 *
 * The portal shares marwaoverseas.com's design system rather than imitating
 * it. The shared assets live in /assets and are written by the sync tool, so
 * there is one source of truth and nothing to drift.
 *
 * @package MarwaApplyTheme
 */

defined( 'ABSPATH' ) || exit;

define( 'MOC_ASSETS_DIR', get_stylesheet_directory() . '/assets' );
define( 'MOC_ASSETS_URL', get_stylesheet_directory_uri() . '/assets' );

/**
 * Parent styles, then the synced design system, then this theme's own rules.
 * Order matters: the portal's form styling has to win over the marketing CSS.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'astra-parent', get_template_directory_uri() . '/style.css', array(), null );

		$deps = array( 'astra-parent' );

		foreach ( array( 'fonts', 'design' ) as $slug ) {
			$file = MOC_ASSETS_DIR . '/' . $slug . '.css';

			if ( is_readable( $file ) ) {
				wp_enqueue_style( 'mo-' . $slug, MOC_ASSETS_URL . '/' . $slug . '.css', $deps, filemtime( $file ) );
				$deps[] = 'mo-' . $slug;
			}
		}

		wp_enqueue_style( 'mo-apply', get_stylesheet_uri(), $deps, filemtime( get_stylesheet_directory() . '/style.css' ) );
	},
	20
);

/**
 * The design system is scoped under .mo, so the body carries it and every
 * descendant inherits the tokens.
 *
 * @param array $classes Body classes.
 * @return array
 */
add_filter(
	'body_class',
	function ( $classes ) {
		$classes[] = 'mo';

		return $classes;
	}
);

/**
 * Swap Astra's header and footer for the main site's.
 */
add_action(
	'wp',
	function () {
		remove_action( 'astra_header', 'astra_header_markup' );
		remove_action( 'astra_footer', 'astra_footer_markup' );
	}
);

add_action(
	'astra_header',
	function () {
		moc_partial( 'header' );
	}
);

add_action(
	'astra_footer',
	function () {
		moc_partial( 'footer' );
	}
);

/**
 * Print a synced markup partial.
 *
 * The file is written by the sync tool from the main site's own output, so it
 * is first-party markup rather than anything a visitor can influence.
 *
 * @param string $name Partial slug.
 */
function moc_partial( $name ) {
	$name = preg_replace( '/[^a-z]/', '', $name );
	$file = MOC_ASSETS_DIR . '/' . $name . '.html';

	if ( ! is_readable( $file ) ) {
		return;
	}

	echo file_get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions
}
