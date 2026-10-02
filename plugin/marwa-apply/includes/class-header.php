<?php
/**
 * The portal's navigation.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites the synced header's navigation at render time.
 *
 * The header is a static partial copied from marwaoverseas.com by the sync
 * tool, which is right for the bar, the brand and the burger — that shared
 * design is the whole point. It is wrong for the menu: a frozen file cannot
 * know how many vacancies are open in Japan this morning, and it cannot know
 * whether the person reading it is signed in.
 *
 * So the markup is buffered on its way out and the navigation inside it is
 * replaced. The child theme is left alone, which matters: it is overwritten
 * on every sync, and anything clever put there would be lost.
 *
 * The menu itself follows the convention this audience already knows from the
 * big Bangladeshi boards — a short list of named, pre-filtered searches with a
 * count beside each, rather than a menu of pages. "Closing within 7 days (3)"
 * does a job that no amount of navigation design does: it answers the question
 * before the tap.
 *
 * <details> rather than a script. It opens on click on a phone exactly as it
 * does under a cursor, it is reachable from the keyboard without help, and
 * there is nothing to break on a slow connection.
 */
final class Header {

	const CACHE = 'ma_nav_counts';

	/**
	 * Hook up.
	 */
	public static function init() {
		// The child theme prints the partial on astra_header at the default
		// priority. Bracketing it is enough to get hold of the markup.
		add_action( 'astra_header', array( __CLASS__, 'open' ), 1 );
		add_action( 'astra_header', array( __CLASS__, 'close' ), 99 );

		add_action( 'save_post_job_listing', array( __CLASS__, 'forget' ) );
		add_action( 'deleted_post', array( __CLASS__, 'forget' ) );
	}

	/**
	 * Start capturing.
	 */
	public static function open() {
		ob_start();
	}

	/**
	 * Rewrite and print.
	 */
	public static function close() {
		$html = ob_get_clean();

		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return;
		}

		echo self::rewrite( $html ); // phpcs:ignore WordPress.Security.EscapeOutput -- first-party markup, rebuilt from our own escaped strings.
		echo self::strip(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped strings.
	}

	/**
	 * A thin reminder under the header while the file is incomplete.
	 *
	 * It disappears at 100%, which is the whole design: a banner that never
	 * goes away is one people stop seeing. It also names the next missing
	 * thing rather than nagging in general, because "add your district" is
	 * something somebody can finish in ten seconds and "complete your
	 * profile" is not.
	 *
	 * @return string
	 */
	private static function strip() {
		if ( ! is_user_logged_in() || ! class_exists( __NAMESPACE__ . '\\Shell' ) ) {
			return '';
		}

		$row = Profile::row();

		if ( ! $row ) {
			return '';
		}

		$pct = max( 0, min( 100, (int) $row->profile_completeness ) );

		if ( $pct >= 100 ) {
			return '';
		}

		$next = Shell::next_step( $row );

		$out  = '<div class="mo ma-strip-wrap"><div class="ma-cstrip">';
		$out .= '<div class="ma-cstrip-l">';
		$out .= '<strong>' . esc_html__( 'Your profile is not finished', 'marwa-apply' ) . '</strong>';
		$out .= '<span>' . esc_html( $next ? $next[0] : __( 'Employers see a complete file first.', 'marwa-apply' ) ) . '</span>';
		$out .= '</div>';

		$out .= '<div class="ma-cstrip-r">';
		$out .= '<div class="ma-cstrip-bar"><span style="width:' . esc_attr( (string) $pct ) . '%"></span></div>';
		$out .= '<b>' . esc_html( $pct . '%' ) . '</b>';
		$out .= '<a href="' . esc_url( $next ? $next[1] : Pages::url( 'profile' ) ) . '">' . esc_html__( 'Update', 'marwa-apply' ) . '</a>';
		$out .= '</div></div></div>';

		return $out;
	}

	/**
	 * Drop the cached counts.
	 */
	public static function forget() {
		delete_transient( self::CACHE );
	}

	/**
	 * How many vacancies sit behind each menu entry.
	 *
	 * Cached for five minutes. A count in a header is read on every page of
	 * every session; it does not need to be accurate to the second, and it
	 * does absolutely need to be cheap.
	 *
	 * @return array
	 */
	private static function counts() {
		$cached = get_transient( self::CACHE );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$posts = get_posts(
			array(
				'post_type'              => 'job_listing',
				'post_status'            => 'publish',
				'posts_per_page'         => 1000,
				'fields'                 => 'ids',
				'update_post_term_cache' => false,
				'update_post_meta_cache' => true,
			)
		);

		$now = time();

		$data = array(
			'all'       => count( $posts ),
			'fresh'     => 0,
			'closing'   => 0,
			'locations' => array(),
		);

		foreach ( $posts as $id ) {
			$expires = (string) get_post_meta( $id, '_job_expires', true );
			$closes  = $expires ? strtotime( $expires . ' 23:59:59' ) : 0;

			if ( $closes && $closes >= $now && $closes <= $now + ( 7 * DAY_IN_SECONDS ) ) {
				++$data['closing'];
			}

			if ( (int) get_post_time( 'U', true, $id ) >= $now - ( 7 * DAY_IN_SECONDS ) ) {
				++$data['fresh'];
			}

			$loc = JobList::short_location( get_post_meta( $id, '_job_location', true ) );

			if ( '' !== $loc ) {
				$data['locations'][ $loc ] = isset( $data['locations'][ $loc ] ) ? $data['locations'][ $loc ] + 1 : 1;
			}
		}

		arsort( $data['locations'] );

		set_transient( self::CACHE, $data, 5 * MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * The navigation, as HTML.
	 *
	 * @return string
	 */
	private static function menu() {
		$jobs = home_url( '/jobs/' );
		$data = self::counts();

		$out = '<a class="mo-nav-link" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'marwa-apply' ) . '</a>';

		// ------------------------------------------------------------ Jobs
		$find = array(
			array( $jobs, __( 'All vacancies', 'marwa-apply' ), $data['all'] ),
		);

		if ( $data['fresh'] ) {
			$find[] = array( add_query_arg( 'posted', '7', $jobs ), __( 'Posted this week', 'marwa-apply' ), $data['fresh'] );
		}

		if ( $data['closing'] ) {
			$find[] = array( add_query_arg( 'closing', '7', $jobs ), __( 'Closing within 7 days', 'marwa-apply' ), $data['closing'] );
		}

		$out .= '<details class="mo-drop"><summary>' . esc_html__( 'Jobs', 'marwa-apply' ) . '</summary><div class="mo-menu">';
		$out .= '<div class="mo-col"><strong>' . esc_html__( 'Find work', 'marwa-apply' ) . '</strong>';

		foreach ( $find as $item ) {
			$out .= self::item( $item[0], $item[1], $item[2] );
		}

		$out .= '</div>';

		if ( $data['locations'] ) {
			$out .= '<div class="mo-col"><strong>' . esc_html__( 'By destination', 'marwa-apply' ) . '</strong>';

			foreach ( array_slice( $data['locations'], 0, 6, true ) as $value => $n ) {
				$out .= self::item( add_query_arg( 'loc', rawurlencode( $value ), $jobs ), $value, $n );
			}

			$out .= '</div>';
		}

		$out .= '</div></details>';

		// ---------------------------------------------------------- Guides
		if ( class_exists( __NAMESPACE__ . '\\Guides' ) ) {
			$guides = Guides::all( 8 );

			if ( $guides ) {
				$out .= '<details class="mo-drop"><summary>' . esc_html__( 'Guides', 'marwa-apply' ) . '</summary><div class="mo-menu"><div class="mo-col">';
				$out .= '<strong>' . esc_html__( 'Before you go', 'marwa-apply' ) . '</strong>';

				foreach ( $guides as $guide ) {
					$out .= self::item( (string) get_permalink( $guide->ID ), get_the_title( $guide->ID ) );
				}

				$out .= '<a class="mo-all" href="' . esc_url( Pages::url( 'guides' ) ) . '"><span>' . esc_html__( 'All guides', 'marwa-apply' ) . '</span></a>';
				$out .= '</div></div></details>';
			}
		}

		// Employers get their own way in. Without one, an employer account has
		// no visible door and people conclude it does not exist.
		if ( ! is_user_logged_in() || ! current_user_can( 'ma_edit_own_profile' ) ) {
			$out .= '<details class="mo-drop"><summary>' . esc_html__( 'For employers', 'marwa-apply' ) . '</summary><div class="mo-menu mo-menu-right"><div class="mo-col">';
			$out .= self::item( Pages::url( 'employer-signup' ), __( 'Recruit from Bangladesh', 'marwa-apply' ) );
			$out .= self::item( Pages::url( 'employer' ), __( 'Employer sign in', 'marwa-apply' ) );
			$out .= '</div></div></details>';
		}

		$out .= '<a class="mo-nav-link mo-out" href="' . esc_url( 'https://marwaoverseas.com/' ) . '">' . esc_html__( 'Marwa Overseas Website', 'marwa-apply' ) . '</a>';

		// ------------------------------------------------------ My account
		// Led by the avatar rather than the words "My account": it is how
		// every site this audience uses marks "this is you", and it survives
		// not being able to read the label.
		if ( is_user_logged_in() ) {
			$row  = Profile::row();
			$name = $row ? trim( (string) $row->full_name ) : '';
			$name = '' !== $name ? $name : wp_get_current_user()->display_name;

			$out .= '<details class="mo-drop mo-me"><summary>';
			$out .= '<span class="mo-av">' . esc_html( self::initials( $name ) ) . '</span>';
			$out .= '<span class="mo-name">' . esc_html( self::short_name( $name ) ) . '</span>';
			$out .= '</summary><div class="mo-menu mo-menu-right">';

			$out .= '<div class="mo-col"><div class="mo-head">';
			$out .= '<span class="mo-av lg">' . esc_html( self::initials( $name ) ) . '</span>';
			$out .= '<div><strong>' . esc_html( $name ) . '</strong>';

			if ( $row ) {
				$out .= '<span>' . esc_html(
					sprintf(
						/* translators: %d: percentage complete */
						__( 'Profile %d%% complete', 'marwa-apply' ),
						(int) $row->profile_completeness
					)
				) . '</span>';
			}

			$out .= '</div></div>';

			$out .= self::item( Pages::url( 'account' ), __( 'Applications', 'marwa-apply' ) );
			$out .= self::item( Pages::url( 'profile' ), __( 'My profile', 'marwa-apply' ) );
			$out .= self::item( Pages::url( 'documents' ), __( 'My documents', 'marwa-apply' ) );

			if ( $row ) {
				$out .= self::item( CV::url( (int) $row->id ), __( 'My CV', 'marwa-apply' ) );
			}

			$out .= '<a class="mo-quit" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '"><span>' . esc_html__( 'Sign out', 'marwa-apply' ) . '</span></a>';
			$out .= '</div></div></details>';
		}

		return $out;
	}

	/**
	 * Initials for the avatar.
	 *
	 * @param string $name Full name.
	 * @return string
	 */
	private static function initials( $name ) {
		$parts = preg_split( '/\s+/u', trim( (string) $name ) );

		if ( ! $parts || '' === $parts[0] ) {
			return '?';
		}

		$cut   = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';
		$first = $cut( $parts[0], 0, 1 );

		if ( count( $parts ) < 2 ) {
			return $first;
		}

		return $first . $cut( $parts[ count( $parts ) - 1 ], 0, 1 );
	}

	/**
	 * A name short enough for a header bar.
	 *
	 * @param string $name Full name.
	 * @return string
	 */
	private static function short_name( $name ) {
		$name = trim( (string) $name );
		$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );

		if ( $len <= 16 ) {
			return $name;
		}

		$cut   = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';
		$parts = preg_split( '/\s+/u', $name );

		return $cut( $parts[0], 0, 15 );
	}

	/**
	 * One menu row.
	 *
	 * @param string   $url   Destination.
	 * @param string   $label Label.
	 * @param int|null $count Optional count.
	 * @return string
	 */
	private static function item( $url, $label, $count = null ) {
		$out = '<a href="' . esc_url( $url ) . '"><span>' . esc_html( $label ) . '</span>';

		if ( null !== $count ) {
			$out .= '<b>' . esc_html( number_format_i18n( (int) $count ) ) . '</b>';
		}

		return $out . '</a>';
	}

	/**
	 * Replace the nav and the call to action inside the synced header.
	 *
	 * @param string $html Buffered header markup.
	 * @return string
	 */
	private static function rewrite( $html ) {
		if ( false === stripos( $html, '<nav' ) ) {
			return $html;
		}

		$doc  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );

		// The leading XML declaration is the usual trick for getting
		// DOMDocument to treat a fragment as UTF-8; the NOIMPLIED/NODEFDTD
		// pair stops it wrapping the fragment in html/body.
		$ok = $doc->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! $ok ) {
			return $html;
		}

		$navs = $doc->getElementsByTagName( 'nav' );

		if ( ! $navs->length ) {
			return $html;
		}

		$nav = $navs->item( 0 );

		while ( $nav->firstChild ) {
			$nav->removeChild( $nav->firstChild );
		}

		// The menu is built from our own escaped strings, so it is safe to
		// parse back in as markup rather than assembled node by node — which
		// for a menu this shape would be forty lines of createElement.
		$frag = $doc->createDocumentFragment();

		if ( $frag->appendXML( self::xml( self::menu() ) ) ) {
			$nav->appendChild( $frag );
		} else {
			$nav->appendChild( $doc->createTextNode( '' ) );
		}

		$class = $nav->getAttribute( 'class' );
		$nav->setAttribute( 'class', trim( $class . ' mo-portal-nav' ) );

		// The main site's call to action is aimed at employers. Here it is
		// the candidate's way in, and what it should say depends on whether
		// they are already signed in.
		foreach ( iterator_to_array( $doc->getElementsByTagName( 'a' ) ) as $el ) {
			if ( false === strpos( $el->getAttribute( 'class' ), 'hd-cta' ) ) {
				continue;
			}

			while ( $el->firstChild ) {
				$el->removeChild( $el->firstChild );
			}

			if ( is_user_logged_in() ) {
				$el->setAttribute( 'href', Pages::url( 'account' ) );
				$el->appendChild( $doc->createTextNode( __( 'My account', 'marwa-apply' ) ) );
			} else {
				$el->setAttribute( 'href', Pages::url( 'register' ) );
				$el->appendChild( $doc->createTextNode( __( 'Create account', 'marwa-apply' ) ) );

				// A second, quieter control beside it: people who already have
				// an account should not have to read "Create account" and
				// wonder whether they are in the right place.
				$sign = $doc->createElement( 'a' );
				$sign->setAttribute( 'class', 'mo-signin' );
				$sign->setAttribute( 'href', Pages::url( 'sign-in' ) );
				$sign->appendChild( $doc->createTextNode( __( 'Sign in', 'marwa-apply' ) ) );

				if ( $el->parentNode ) {
					$el->parentNode->insertBefore( $sign, $el );
				}
			}

			break;
		}

		$out = $doc->saveHTML();

		return is_string( $out ) ? $out : $html;
	}

	/**
	 * Make our menu markup parseable by appendXML, which wants XML.
	 *
	 * @param string $html Menu markup.
	 * @return string
	 */
	private static function xml( $html ) {
		// Named entities esc_html() produces are not XML; numeric ones are.
		$map = array(
			'&nbsp;'   => '&#160;',
			'&amp;'    => '&#38;',
			'&quot;'   => '&#34;',
			'&#039;'   => '&#39;',
			'&apos;'   => '&#39;',
			'&lt;'     => '&#60;',
			'&gt;'     => '&#62;',
			'&hellip;' => '&#8230;',
			'&rarr;'   => '&#8594;',
			'&larr;'   => '&#8592;',
			'&mdash;'  => '&#8212;',
			'&ndash;'  => '&#8211;',
		);

		return '<span class="mo-nav-inner">' . strtr( $html, $map ) . '</span>';
	}

	/**
	 * Styling for the menu. Shipped with the plugin rather than the child
	 * theme for the same reason the markup is: the theme's assets are
	 * rewritten on every design sync.
	 */
	public static function styles() {
		?>
<style id="marwa-apply-nav">
.mo-nav-inner{display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.mo-portal-nav a{text-decoration:none}
.mo-nav-link,.mo-drop>summary{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:7px;font-size:14.5px;line-height:1.4;color:#EEF3F8;cursor:pointer;white-space:nowrap}
.mo-nav-link:hover,.mo-drop>summary:hover{background:rgba(255,255,255,.12)}
.mo-drop{position:relative}
.mo-drop>summary{list-style:none}
.mo-drop>summary::-webkit-details-marker{display:none}
.mo-drop>summary:after{content:"";width:6px;height:6px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg) translateY(-2px);opacity:.7}
.mo-drop[open]>summary{background:rgba(255,255,255,.14)}
.mo-drop[open]>summary:after{transform:rotate(-135deg) translateY(-2px)}
/* The panel is a light surface sitting inside a dark header. The synced
   header stylesheet colours every `nav a` for the dark bar, at a specificity
   this cannot beat by nesting alone, so the panel states its own colours
   outright — otherwise the menu renders white-on-white and looks empty. */
.mo-menu{position:absolute;top:calc(100% + 8px);left:0;z-index:120;display:flex;gap:26px;min-width:250px;padding:16px 18px;border-radius:12px;background:#fff;box-shadow:0 14px 40px rgba(11,34,57,.22);border:1px solid #E3E0D9}
.mo-col{min-width:198px;max-width:330px}
.mo-col strong{display:block;font-size:11.5px;letter-spacing:.09em;text-transform:uppercase;color:#5B6B7C!important;margin:0 0 7px}
.mo-menu a,.mo-col a{display:flex;justify-content:space-between;align-items:baseline;gap:12px;padding:7px 9px;margin:0 -9px;border-radius:7px;font-size:14.5px;line-height:1.45;color:#0B2239!important;text-decoration:none}
.mo-menu a:hover,.mo-col a:hover{background:#F3F1EC;color:#0B2239!important}
.mo-col .mo-all{margin-top:8px;padding-top:10px;border-top:1px solid #E3E0D9;color:#A93A2C!important}
.mo-col a b{flex:0 0 auto;font-size:12px;font-weight:700;color:#5B6B7C!important;background:#F3F1EC;border-radius:20px;padding:1px 8px}
.mo-col a:hover b{background:#fff}
.mo-out:after{content:"\2197";font-size:12px;opacity:.7}
.mo-me>summary{gap:9px;padding-left:7px}
.mo-av{flex:0 0 auto;width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.18);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.02em}
.mo-av.lg{width:42px;height:42px;font-size:16px;background:var(--navy,#0B2239)}
.mo-name{max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mo-menu-right{left:auto;right:0}
.mo-head{display:flex;align-items:center;gap:11px;padding:0 0 11px;margin-bottom:8px;border-bottom:1px solid #E3E0D9}
.mo-head strong{display:block;font-size:14.5px;line-height:1.35;color:#0B2239}
.mo-head span{display:block;font-size:12.5px;color:#5B6B7C}
.mo-quit{display:block;margin-top:8px;padding-top:10px;border-top:1px solid #E3E0D9;font-size:14px;color:#A93A2C!important;text-decoration:none}
.ma-strip-wrap{background:var(--surface-alt,#F3F1EC);border-bottom:1px solid var(--line,#E3E0D9)}
.ma-cstrip{max-width:1640px;margin:0 auto;padding:10px 34px;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}
.ma-cstrip-l strong{display:block;font-size:14px;line-height:1.4;color:var(--navy,#0B2239)}
.ma-cstrip-l span{display:block;font-size:13px;line-height:1.45;color:var(--muted,#5B6B7C)}
.ma-cstrip-r{display:flex;align-items:center;gap:12px}
.ma-cstrip-bar{width:160px;height:7px;border-radius:20px;background:#fff;border:1px solid var(--line,#E3E0D9);overflow:hidden}
.ma-cstrip-bar span{display:block;height:100%;background:var(--accent,#A93A2C)}
.ma-cstrip-r b{font-size:13.5px;font-variant-numeric:tabular-nums;color:var(--navy,#0B2239)}
.ma-cstrip-r a{font-size:13.5px;color:var(--accent,#A93A2C)}
@media(max-width:900px){.ma-cstrip{padding:10px 16px}.ma-cstrip-bar{width:96px}}
.mo-signin{display:inline-flex;align-items:center;padding:8px 14px;margin-right:6px;border-radius:7px;font-size:14.5px;line-height:1.4;color:#EEF3F8;text-decoration:none;white-space:nowrap}
.mo-signin:hover{background:rgba(255,255,255,.12)}
@media(max-width:900px){
.mo-nav-inner{align-items:stretch;flex-direction:column;gap:2px;width:100%}
.mo-menu-right{right:auto}
.mo-name{max-width:none}
.mo-drop{position:static}
.mo-nav-link,.mo-drop>summary{justify-content:space-between;width:100%;box-sizing:border-box}
.mo-menu{position:static;flex-direction:column;gap:14px;width:100%;box-sizing:border-box;margin:4px 0 8px;box-shadow:none}
.mo-col{min-width:0}
}
</style>
		<?php
	}
}
