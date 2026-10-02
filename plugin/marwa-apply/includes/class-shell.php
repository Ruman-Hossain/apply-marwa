<?php
/**
 * The signed-in page frame.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Rail, content, rail — for the pages a candidate works inside.
 *
 * Account, profile and documents are not three separate destinations as far
 * as the person using them is concerned; they are three views of one file.
 * Giving them a shared left rail says so, and means the way between them is
 * always one tap rather than a trip back through "My account".
 *
 * The rail also carries the thing most worth knowing at any moment: how
 * complete the file is, and what the next missing piece is. A candidate who
 * can see that will finish it; one who has to go looking will not.
 *
 * Deliberately NOT applied to register, sign in and confirm-your-email. A
 * form that must be read and filled in once wants a narrow measure and no
 * navigation competing with it — and there is no file to navigate yet.
 */
final class Shell {

	/**
	 * The pages in the rail.
	 *
	 * @return array
	 */
	public static function nav() {
		return array(
			'account'   => array(
				'label' => __( 'Overview', 'marwa-apply' ),
				'hint'  => __( 'Applications and their stage', 'marwa-apply' ),
				'url'   => Pages::url( 'account' ),
			),
			'profile'   => array(
				'label' => __( 'My profile', 'marwa-apply' ),
				'hint'  => __( 'Trade, experience, languages', 'marwa-apply' ),
				'url'   => Pages::url( 'profile' ),
			),
			'documents' => array(
				'label' => __( 'My documents', 'marwa-apply' ),
				'hint'  => __( 'Passport, photo, certificates', 'marwa-apply' ),
				'url'   => Pages::url( 'documents' ),
			),
		);
	}

	/**
	 * Open the frame.
	 *
	 * @param string $current Current page key.
	 * @return string
	 */
	public static function open( $current ) {
		$row = Profile::row();

		$out  = self::styles();
		$out .= '<div class="ap-shell ma-acct-wrap"><div class="ma-acct">';

		// ------------------------------------------------------- left rail
		$out .= '<aside class="ma-acct-nav">';

		if ( $row ) {
			$name = trim( (string) $row->full_name );
			$name = '' !== $name ? $name : __( 'Your file', 'marwa-apply' );
			$pct  = max( 0, min( 100, (int) $row->profile_completeness ) );

			$out .= '<div class="ma-who">';
			$out .= '<span class="ma-av">' . esc_html( self::initials( $name ) ) . '</span>';
			$out .= '<div><strong>' . esc_html( $name ) . '</strong>';

			if ( $row->phone ) {
				$out .= '<span>' . esc_html( Accounts::display_phone( $row->phone ) ) . '</span>';
			}

			$out .= '</div></div>';

			$out .= '<div class="ma-prog"><div class="ma-bar-track"><span style="width:' . esc_attr( (string) $pct ) . '%"></span></div>';
			$out .= '<small>' . esc_html(
				sprintf(
					/* translators: %d: percentage complete */
					__( '%d%% complete', 'marwa-apply' ),
					$pct
				)
			) . '</small></div>';
		}

		$out .= '<ul class="ma-acct-menu">';

		// phpcs:ignore WordPress.Security.NonceVerification
		$open_sec = isset( $_GET['sec'] ) ? sanitize_key( wp_unslash( $_GET['sec'] ) ) : 'about';

		foreach ( self::nav() as $key => $item ) {
			$out .= '<li><a class="' . ( $key === $current ? 'on' : '' ) . '" href="' . esc_url( $item['url'] ) . '">';
			$out .= '<span>' . esc_html( $item['label'] ) . '</span>';
			$out .= '<small>' . esc_html( $item['hint'] ) . '</small></a>';

			// The profile's own sections open out in the rail while you are
			// in it, so every part of the file is one tap away instead of
			// hiding behind a tab row you have to scroll back up to.
			if ( 'profile' === $key && 'profile' === $current && class_exists( __NAMESPACE__ . '\\Profile' ) ) {
				$out .= '<ul class="ma-sub">';

				foreach ( Profile::sections() as $sk => $spec ) {
					$out .= '<li><a class="' . ( $sk === $open_sec ? 'on' : '' ) . '" href="' . esc_url( add_query_arg( 'sec', $sk, $item['url'] ) ) . '">';
					$out .= esc_html( $spec['label'] ) . '</a></li>';
				}

				$out .= '</ul>';
			}

			$out .= '</li>';
		}

		if ( $row ) {
			$out .= '<li><a href="' . esc_url( CV::url( (int) $row->id ) ) . '" target="_blank" rel="noopener">';
			$out .= '<span>' . esc_html__( 'My CV', 'marwa-apply' ) . '</span>';
			$out .= '<small>' . esc_html__( 'Built from your profile — opens to print or save', 'marwa-apply' ) . '</small></a></li>';
		}

		$out .= '<li><a href="' . esc_url( home_url( '/jobs/' ) ) . '"><span>' . esc_html__( 'Browse jobs', 'marwa-apply' ) . '</span></a></li>';
		$out .= '<li><a class="ma-quit" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '"><span>' . esc_html__( 'Sign out', 'marwa-apply' ) . '</span></a></li>';
		$out .= '</ul></aside>';

		return $out . '<div class="ma-acct-main">';
	}

	/**
	 * Close the frame, with the right rail.
	 *
	 * @return string
	 */
	public static function close() {
		$row = Profile::row();

		$out = '</div><aside class="ma-rail">';

		if ( $row ) {
			$missing = self::missing( $row );

			if ( $missing ) {
				$out .= '<div class="ap-card ma-box"><h3>' . esc_html__( 'Next step', 'marwa-apply' ) . '</h3><ul class="ma-todo">';

				foreach ( $missing as $todo ) {
					$out .= '<li><a href="' . esc_url( $todo[1] ) . '">' . esc_html( $todo[0] ) . '</a></li>';
				}

				$out .= '</ul></div>';
			} else {
				$out .= '<div class="ap-card ma-box"><h3>' . esc_html__( 'Your file is complete', 'marwa-apply' ) . '</h3>';
				$out .= '<p class="ap-note">' . esc_html__( 'Nothing is missing. Apply to anything on the jobs page and Marwa already has what it needs.', 'marwa-apply' ) . '</p>';
				$out .= '<p><a class="btn primary" href="' . esc_url( home_url( '/jobs/' ) ) . '">' . esc_html__( 'Browse jobs', 'marwa-apply' ) . '</a></p></div>';
			}
		}

		$out .= '<div class="ap-card ma-box ma-lic"><h3>' . esc_html__( 'A licensed recruiter', 'marwa-apply' ) . '</h3>';
		$out .= '<p class="ap-note">' . esc_html__( 'Marwa Overseas holds recruiting licence RL 2831, issued by the Ministry of Expatriates&#8217; Welfare and Overseas Employment.', 'marwa-apply' ) . '</p>';
		$out .= '<p class="ap-note"><strong>' . esc_html__( 'Marwa never charges a jobseeker to open an account or to apply.', 'marwa-apply' ) . '</strong></p></div>';

		return $out . '</aside></div></div>';
	}

	/**
	 * The single next thing worth doing, for the header strip.
	 *
	 * @param object $row Candidate row.
	 * @return array|null array( label, url )
	 */
	public static function next_step( $row ) {
		$all = self::missing( $row );

		return $all ? $all[0] : null;
	}

	/**
	 * What is still missing, as things to go and do.
	 *
	 * Phrased as actions rather than as faults. "Add your passport status" is
	 * a task; "Passport status: missing" is a telling-off.
	 *
	 * @param object $row Candidate row.
	 * @return array
	 */
	private static function missing( $row ) {
		$profile   = Pages::url( 'profile' );
		$documents = Pages::url( 'documents' );
		$out       = array();

		if ( '' === trim( (string) $row->full_name ) ) {
			$out[] = array( __( 'Add your full name', 'marwa-apply' ), $profile );
		}

		if ( ! $row->district ) {
			$out[] = array( __( 'Add your district', 'marwa-apply' ), $profile );
		}

		if ( ! $row->sector_id || ! $row->trade ) {
			$out[] = array( __( 'Say what trade you work in', 'marwa-apply' ), $profile );
		}

		if ( ! $row->passport_status || 'unknown' === $row->passport_status ) {
			$out[] = array( __( 'Add your passport status', 'marwa-apply' ), $documents );
		}

		if ( ! Documents::latest_of_type( (int) $row->id, 'cv' ) && ! Documents::latest_of_type( (int) $row->id, 'passport' ) ) {
			$out[] = array( __( 'Upload your passport or a CV', 'marwa-apply' ), $documents );
		}

		return array_slice( $out, 0, 4 );
	}

	/**
	 * Initials for the little avatar.
	 *
	 * @param string $name Full name.
	 * @return string
	 */
	private static function initials( $name ) {
		$parts = preg_split( '/\s+/u', trim( $name ) );

		if ( ! $parts ) {
			return '?';
		}

		$first = function_exists( 'mb_substr' ) ? mb_substr( $parts[0], 0, 1 ) : substr( $parts[0], 0, 1 );

		if ( count( $parts ) < 2 ) {
			return $first;
		}

		$last = $parts[ count( $parts ) - 1 ];

		return $first . ( function_exists( 'mb_substr' ) ? mb_substr( $last, 0, 1 ) : substr( $last, 0, 1 ) );
	}

	/**
	 * Frame styles.
	 *
	 * @return string
	 */
	private static function styles() {
		return '<style id="marwa-apply-shell">
.mo .ap-shell.ma-acct-wrap{max-width:1640px}
@media(min-width:1100px){.mo .ap-shell.ma-acct-wrap{padding-left:34px;padding-right:34px}}
.ma-acct{display:grid;grid-template-columns:286px minmax(0,1fr) 300px;gap:30px;align-items:start}
.ma-acct-nav{position:sticky;top:88px;border:1px solid var(--line,#E3E0D9);border-radius:12px;background:#fff;overflow:hidden}
.ma-who{display:flex;align-items:center;gap:12px;padding:16px 16px 12px}
.ma-av{flex:0 0 auto;width:42px;height:42px;border-radius:50%;background:var(--navy,#0B2239);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;text-transform:uppercase}
.ma-who strong{display:block;font-size:15px;line-height:1.35}
.ma-who span{display:block;font-size:13px;color:var(--muted,#5B6B7C)}
.ma-prog{padding:0 16px 14px}
.ma-prog small{font-size:12.5px;color:var(--muted,#5B6B7C)}
.ma-bar-track{height:7px;border-radius:20px;background:var(--line,#E3E0D9);overflow:hidden;margin:0 0 6px}
.ma-bar-track span{display:block;height:100%;background:var(--accent,#A93A2C)}
.ma-acct-menu{list-style:none;margin:0;padding:0;border-top:1px solid var(--line,#E3E0D9)}
.ma-acct-menu li{margin:0;border-bottom:1px solid var(--line,#E3E0D9)}
.ma-acct-menu li:last-child{border-bottom:0}
.ma-acct-menu a{display:block;padding:12px 16px;text-decoration:none;color:var(--ink,#0B2239)}
.ma-acct-menu a:hover{background:var(--surface-alt,#F3F1EC)}
.ma-acct-menu a.on{background:var(--surface-alt,#F3F1EC);box-shadow:inset 3px 0 0 var(--accent,#A93A2C)}
.ma-acct-menu a span{display:block;font-size:14.5px;line-height:1.4;font-weight:600}
.ma-acct-menu a.on span{color:var(--accent,#A93A2C)}
.ma-acct-menu a small{display:block;font-size:12.5px;line-height:1.45;color:var(--muted,#5B6B7C);margin-top:2px}
.ma-acct-menu a.ma-quit span{font-weight:400;color:var(--muted,#5B6B7C)}
.ma-sub{list-style:none;margin:0;padding:0 0 8px;background:var(--surface-alt,#F3F1EC)}
.ma-sub li{border:0}
.ma-sub a{display:block;padding:7px 16px 7px 30px;font-size:13.5px;line-height:1.45;color:var(--muted,#5B6B7C);text-decoration:none;position:relative}
.ma-sub a:before{content:"";position:absolute;left:18px;top:14px;width:4px;height:4px;border-radius:50%;background:var(--line,#E3E0D9)}
.ma-sub a:hover{color:var(--navy,#0B2239)}
.ma-sub a.on{color:var(--accent,#A93A2C);font-weight:700}
.ma-sub a.on:before{background:var(--accent,#A93A2C)}
.ma-rail{position:sticky;top:88px;display:flex;flex-direction:column;gap:14px}
.ma-box h3{margin:0 0 10px;font-size:16px}
.ma-box p{margin:0 0 10px}
.ma-box p:last-child{margin-bottom:0}
.ma-box .btn{width:100%;text-align:center;box-sizing:border-box}
.ma-lic{background:var(--surface-alt,#F3F1EC)}
.ma-todo{list-style:none;margin:0;padding:0}
.ma-todo li{margin:0;padding:8px 0;border-top:1px solid var(--line,#E3E0D9);font-size:14px;line-height:1.5}
.ma-todo li:first-child{border-top:0;padding-top:0}
@media(max-width:1360px){
.ma-acct{grid-template-columns:272px minmax(0,1fr)}
.ma-rail{grid-column:1 / -1;position:static;flex-direction:row;flex-wrap:wrap}
.ma-rail>*{flex:1 1 280px}
}
@media(max-width:900px){
.mo .ap-shell.ma-acct-wrap{padding-left:16px;padding-right:16px}
.ma-acct{grid-template-columns:1fr}
.ma-acct-nav{position:static}
.ma-rail{position:static;flex-direction:column}
}
</style>';
	}
}
