<?php
/**
 * Plugin Name: Marwa Apply — design sync (one-shot)
 * Description: Pulls the design system, header and footer from marwaoverseas.com into the child theme's /assets. Build-time tool; delete once the assets are in place.
 * Version:     0.1.0
 * Author:      Md Ruman Hossain
 *
 * Why a sync rather than a hand-copied stylesheet: the main site's design
 * system is one WPCode snippet that will keep changing. Copying it by hand
 * guarantees the two sites drift apart. Re-running this picks up whatever the
 * main site looks like today.
 *
 * @package MarwaApplyTheme
 */

defined( 'ABSPATH' ) || exit;

const MA_SYNC_SOURCE = 'https://marwaoverseas.com/';

add_action( 'admin_init', 'ma_sync_maybe_run' );

/**
 * Run when an administrator asks for it: /wp-admin/?ma_sync_design=1
 */
function ma_sync_maybe_run() {
	if ( empty( $_GET['ma_sync_design'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	set_transient( 'ma_sync_report', ma_sync_run(), 300 );
}

add_action(
	'admin_notices',
	function () {
		$report = get_transient( 'ma_sync_report' );

		if ( ! $report ) {
			return;
		}

		delete_transient( 'ma_sync_report' );

		printf(
			'<div class="notice notice-%s"><p><strong>Marwa Apply design sync</strong></p><pre style="white-space:pre-wrap;margin:0">%s</pre></div>',
			esc_attr( empty( $report['errors'] ) ? 'success' : 'error' ),
			esc_html( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) )
		);
	}
);

/**
 * Do the work.
 *
 * @return array Report.
 */
function ma_sync_run() {
	$report = array( 'errors' => array(), 'written' => array() );

	$response = wp_remote_get( MA_SYNC_SOURCE, array( 'timeout' => 25 ) );

	if ( is_wp_error( $response ) ) {
		$report['errors'][] = 'fetch failed: ' . $response->get_error_message();

		return $report;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( 200 !== $code ) {
		$report['errors'][] = 'fetch returned HTTP ' . $code;

		return $report;
	}

	$html = wp_remote_retrieve_body( $response );
	$report['source_bytes'] = strlen( $html );

	$dir = get_stylesheet_directory() . '/assets';

	if ( ! wp_mkdir_p( $dir ) ) {
		$report['errors'][] = 'could not create ' . $dir;

		return $report;
	}

	// ---------------------------------------------------------------- styles

	$styles = array();

	if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $html, $m ) ) {
		$styles = $m[1];
	}

	$design = '';
	$fonts  = '';

	foreach ( $styles as $css ) {
		// The design system is the block that defines the palette tokens and
		// styles the hand-built header — nothing else on the page does both.
		if ( '' === $design && false !== strpos( $css, '--surface-alt' ) && false !== strpos( $css, '.mo-hd' ) ) {
			$design = $css;
			continue;
		}

		if ( '' === $fonts && false !== strpos( $css, 'fonts.googleapis.com' ) ) {
			$fonts = $css;
		}
	}

	if ( '' === $design ) {
		$report['errors'][] = 'design system block not found in the page — the main site markup may have changed';
	} else {
		$report['written']['design.css'] = ma_sync_put( $dir . '/design.css', $design );
	}

	if ( '' === $fonts ) {
		$report['errors'][] = 'font block not found';
	} else {
		$report['written']['fonts.css'] = ma_sync_put( $dir . '/fonts.css', $fonts );
	}

	// ------------------------------------------------------------- partials

	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();

	$xpath = new DOMXPath( $doc );

	foreach ( array( 'header' => 'mo-hd', 'footer' => 'mo-ft' ) as $name => $class ) {
		$nodes = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]" );

		if ( ! $nodes || 0 === $nodes->length ) {
			$report['errors'][] = "{$name} (.{$class}) not found";
			continue;
		}

		$node = $nodes->item( 0 );

		// Scripts belong to the main site's own behaviour; the portal supplies
		// its own. Carrying them across would duplicate analytics and chat.
		foreach ( iterator_to_array( $node->getElementsByTagName( 'script' ) ) as $script ) {
			$script->parentNode->removeChild( $script );
		}

		ma_sync_absolutise( $node, $xpath );

		ma_sync_strip_translate( $node );
		$report['images'][ $name ] = ma_sync_localise_images( $node, $dir );

		if ( 'header' === $name ) {
			ma_sync_adapt_header( $doc, $node );
		} else {
			ma_sync_adapt_footer( $doc, $node );
		}

		$markup = $doc->saveHTML( $node );
		$report['written'][ $name . '.html' ] = ma_sync_put( $dir . '/' . $name . '.html', $markup );
	}

	return $report;
}

/**
 * Copy every image the header and footer reference into the portal's own
 * theme, and point the markup at the local copy.
 *
 * Without this the portal would hotlink the main site's uploads: the logo
 * would break the moment that file is renamed or the main site is down, and
 * the portal would not actually be independent. After this runs, nothing the
 * portal serves is fetched from marwaoverseas.com at request time.
 *
 * @param DOMNode $node Node to rewrite in place.
 * @param string  $dir  Theme assets directory.
 * @return array Report of what was copied.
 */
function ma_sync_localise_images( $node, $dir ) {
	$out = array();
	$img_dir = $dir . '/img';

	if ( ! wp_mkdir_p( $img_dir ) ) {
		return array( 'error' => 'could not create ' . $img_dir );
	}

	foreach ( $node->getElementsByTagName( 'img' ) as $img ) {
		$src = $img->getAttribute( 'src' );

		if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
			continue;
		}

		$name = sanitize_file_name( basename( wp_parse_url( $src, PHP_URL_PATH ) ) );

		if ( '' === $name ) {
			continue;
		}

		$target = $img_dir . '/' . $name;

		if ( ! file_exists( $target ) ) {
			$res = wp_remote_get( $src, array( 'timeout' => 20 ) );

			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				$out[ $name ] = 'FETCH FAILED — still pointing at the main site';
				continue;
			}

			$bytes = file_put_contents( $target, wp_remote_retrieve_body( $res ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions

			if ( false === $bytes ) {
				$out[ $name ] = 'WRITE FAILED';
				continue;
			}

			$out[ $name ] = $bytes . ' bytes';
		} else {
			$out[ $name ] = 'already local';
		}

		$img->setAttribute( 'src', get_stylesheet_directory_uri() . '/assets/img/' . $name );
		$img->removeAttribute( 'srcset' );
		$img->removeAttribute( 'sizes' );
	}

	return $out;
}

/**
 * Links the portal keeps. Everything else from the main site's nav goes.
 *
 * The portal borrows the main site's design, not its content. A candidate
 * half-way through an application does not need eight marketing pages in
 * front of them, and every one of those links would take them off the portal
 * mid-form. One clearly labelled way back to the company site is enough.
 *
 * @return array label => url
 */
function ma_sync_portal_links() {
	return array(
		__( 'Home', 'marwa-apply-theme' )                   => home_url( '/' ),
		__( 'Jobs', 'marwa-apply-theme' )                   => home_url( '/jobs/' ),
		__( 'Marwa Overseas Website', 'marwa-apply-theme' ) => untrailingslashit( MA_SYNC_SOURCE ) . '/',
	);
}

/**
 * Remove the translation widget.
 *
 * The main site runs GTranslate; the portal does not. A language switcher
 * that silently does nothing is worse than no switcher.
 *
 * @param DOMNode $node Node to clean in place.
 */
function ma_sync_strip_translate( $node ) {
	$doc = $node->ownerDocument;
	$xp  = new DOMXPath( $doc );

	$junk = $xp->query( ".//*[contains(@class,'mo-lang') or contains(@class,'glink') or contains(@class,'gtranslate') or contains(@class,'gt_')]", $node );

	foreach ( iterator_to_array( $junk ) as $el ) {
		if ( $el->parentNode ) {
			$el->parentNode->removeChild( $el );
		}
	}

	// The footer's language pair carries no class of its own — it is just two
	// links, one of them to the main site's /jp/ tree. Match it by what it
	// points at rather than by styling that may not be there.
	foreach ( iterator_to_array( $node->getElementsByTagName( 'p' ) ) as $para ) {
		$links = $para->getElementsByTagName( 'a' );

		if ( ! $links->length || $links->length !== ma_sync_element_children( $para ) ) {
			continue;
		}

		foreach ( $links as $link ) {
			if ( false !== strpos( $link->getAttribute( 'href' ), '/jp/' ) ) {
				$para->parentNode->removeChild( $para );
				break 2;
			}
		}
	}
}

/**
 * Count element children, ignoring text nodes.
 *
 * @param DOMNode $node Node.
 * @return int
 */
function ma_sync_element_children( $node ) {
	$count = 0;

	foreach ( $node->childNodes as $child ) {
		if ( XML_ELEMENT_NODE === $child->nodeType ) {
			$count++;
		}
	}

	return $count;
}

/**
 * Make the main site's header work as the portal's header.
 *
 * Keeps the bar, the brand and the burger exactly as they are — that is the
 * design the portal is meant to share. Replaces what is inside: the marketing
 * nav becomes the portal's own two links, and "Partnership Discussion", which
 * is aimed at employers, becomes the candidate's sign-in.
 *
 * Done here, at sync time, rather than on every page load.
 *
 * @param DOMDocument $doc  Document.
 * @param DOMNode     $node The header node.
 */
function ma_sync_adapt_header( $doc, $node ) {
	// Call to action -> sign in.
	//
	// Plain attribute matching rather than XPath: the class list here is
	// whatever the main site happens to emit, and a selector that silently
	// matches nothing is worse than a loop that obviously does.
	foreach ( iterator_to_array( $node->getElementsByTagName( 'a' ) ) as $el ) {
		if ( false === strpos( $el->getAttribute( 'class' ), 'hd-cta' ) ) {
			continue;
		}

		$el->setAttribute( 'href', home_url( '/sign-in/' ) );

		while ( $el->firstChild ) {
			$el->removeChild( $el->firstChild );
		}

		$el->appendChild( $doc->createTextNode( __( 'Sign in', 'marwa-apply-theme' ) ) );
	}

	// Portal nav in place of the marketing nav.
	$navs = $node->getElementsByTagName( 'nav' );

	if ( $navs->length ) {
		$nav = $navs->item( 0 );

		while ( $nav->firstChild ) {
			$nav->removeChild( $nav->firstChild );
		}

		foreach ( ma_sync_portal_links() as $label => $url ) {
			$link = $doc->createElement( 'a' );
			$link->appendChild( $doc->createTextNode( $label ) );
			$link->setAttribute( 'href', $url );
			$nav->appendChild( $link );
		}
	}

	// The brand goes to the portal's own front page, not the company site —
	// the company site already has its own link in the nav.
	foreach ( $node->getElementsByTagName( 'a' ) as $el ) {
		if ( false !== strpos( $el->getAttribute( 'class' ), 'brand' ) ) {
			$el->setAttribute( 'href', home_url( '/' ) );
			break;
		}
	}
}

/**
 * Trim the footer to what belongs on an application portal.
 *
 * Kept: the brand block with the government licence number, and the contact
 * block with both offices and opening hours. Those are facts about Marwa that
 * a candidate genuinely needs, and the licence number is the single most
 * important credibility signal on the page.
 *
 * Replaced: the "Company" column becomes the portal's own links. Removed: the
 * eleven sector links, which are main-site content and would every one of
 * them take a candidate off the portal.
 *
 * @param DOMDocument $doc  Document.
 * @param DOMNode     $node The footer node.
 */
function ma_sync_adapt_footer( $doc, $node ) {
	$xp  = new DOMXPath( $doc );
	$top = $xp->query( ".//div[contains(concat(' ', normalize-space(@class), ' '), ' top ')]", $node );

	if ( ! $top || ! $top->length ) {
		return;
	}

	foreach ( iterator_to_array( $top->item( 0 )->childNodes ) as $col ) {
		if ( XML_ELEMENT_NODE !== $col->nodeType ) {
			continue;
		}

		$headings = $col->getElementsByTagName( 'h3' );

		if ( ! $headings->length ) {
			continue; // The brand column — left alone.
		}

		$heading = trim( $headings->item( 0 )->textContent );

		if ( 0 === strcasecmp( $heading, 'Sectors' ) ) {
			$col->parentNode->removeChild( $col );
			continue;
		}

		if ( 0 !== strcasecmp( $heading, 'Company' ) ) {
			continue; // Contact column — left alone.
		}

		$headings->item( 0 )->nodeValue = esc_html__( 'Portal', 'marwa-apply-theme' );

		$lists = $col->getElementsByTagName( 'ul' );

		if ( ! $lists->length ) {
			continue;
		}

		$list = $lists->item( 0 );

		while ( $list->firstChild ) {
			$list->removeChild( $list->firstChild );
		}

		$links = ma_sync_portal_links();
		$links = array_merge(
			array( __( 'Create an account', 'marwa-apply-theme' ) => home_url( '/register/' ) ),
			$links
		);
		$links[ __( 'Sign in', 'marwa-apply-theme' ) ] = home_url( '/sign-in/' );

		foreach ( $links as $label => $url ) {
			$li = $doc->createElement( 'li' );
			$a  = $doc->createElement( 'a' );
			$a->appendChild( $doc->createTextNode( $label ) );
			$a->setAttribute( 'href', $url );
			$li->appendChild( $a );
			$list->appendChild( $li );
		}
	}
}

/**
 * Point relative links and images back at the main site.
 *
 * Without this, every nav link on the portal would 404 against the portal's
 * own routes.
 *
 * @param DOMNode  $node  Node to rewrite in place.
 * @param DOMXPath $xpath XPath bound to the same document.
 */
function ma_sync_absolutise( $node, $xpath ) {
	$attrs = array( 'a' => 'href', 'img' => 'src', 'source' => 'srcset', 'use' => 'href' );

	foreach ( $attrs as $tag => $attr ) {
		foreach ( $node->getElementsByTagName( $tag ) as $el ) {
			$value = $el->getAttribute( $attr );

			if ( '' === $value ) {
				continue;
			}

			// Delimiter is ~ on purpose: a # delimiter would be terminated by
			// the literal # in the alternation, silently matching nothing and
			// prefixing every already-absolute URL a second time.
			if ( preg_match( '~^(https?:|//|\#|mailto:|tel:|data:)~i', $value ) ) {
				continue;
			}

			$el->setAttribute( $attr, untrailingslashit( MA_SYNC_SOURCE ) . '/' . ltrim( $value, '/' ) );
		}
	}
}

/**
 * Write a file and report the outcome.
 *
 * @param string $path     Absolute path.
 * @param string $contents Contents.
 * @return string
 */
function ma_sync_put( $path, $contents ) {
	$bytes = file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	return false === $bytes ? 'FAILED' : $bytes . ' bytes';
}
