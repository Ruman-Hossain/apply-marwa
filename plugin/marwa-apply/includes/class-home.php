<?php
/**
 * The portal landing page.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * What a candidate lands on.
 *
 * Shaped by the conventions of the job boards this audience already uses:
 * search first, then counted shortcuts into the list, then the newest
 * vacancies, then the process. The counted shortcut is the idea worth
 * borrowing — a man on a metered connection should be able to see that there
 * are four welding jobs and none in Romania before he spends anything finding
 * out.
 *
 * What is deliberately NOT borrowed is the counter strip full of impressive
 * numbers. Every figure here is counted from this database at render time.
 * There are no placement totals, no "thousands of happy workers", no partner
 * logos. Marwa's licence number is worth more than an invented statistic, and
 * a number that cannot be checked is the first thing a careful employer stops
 * trusting.
 */
final class Home {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_shortcode( 'ma_home', array( __CLASS__, 'render' ) );
	}

	/**
	 * Everything the page counts, in one pass over the open vacancies.
	 *
	 * @return array
	 */
	private static function survey() {
		$posts = get_posts(
			array(
				'post_type'              => 'job_listing',
				'post_status'            => 'publish',
				'posts_per_page'         => 1000,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'update_post_term_cache' => true,
				'update_post_meta_cache' => true,
			)
		);

		$now = time();

		$data = array(
			'open'      => count( $posts ),
			'latest'    => array_slice( $posts, 0, 9 ),
			'sectors'   => array(),
			'locations' => array(),
			'closing'   => 0,
			'fresh'     => 0,
			'soon'      => array(),
			'bydest'    => array(),
		);

		// Every sector term, including the empty ones: a trade with no vacancy
		// today is still something Marwa recruits for, and saying so honestly
		// is better than a candidate concluding we do not do it at all.
		foreach ( (array) get_terms( array( 'taxonomy' => Sectors::TAXONOMY, 'hide_empty' => false ) ) as $t ) {
			if ( is_wp_error( $t ) ) {
				continue;
			}

			$data['sectors'][ $t->term_id ] = array(
				'name'  => $t->name,
				'count' => 0,
				'prep'  => (bool) get_term_meta( $t->term_id, 'ma_in_preparation', true ),
				'jp'    => (string) get_term_meta( $t->term_id, 'ma_jp_label', true ),
			);
		}

		foreach ( $posts as $post ) {
			$terms = get_the_terms( $post->ID, Sectors::TAXONOMY );

			if ( $terms && ! is_wp_error( $terms ) ) {
				$id = (int) $terms[0]->term_id;

				if ( isset( $data['sectors'][ $id ] ) ) {
					++$data['sectors'][ $id ]['count'];
				}
			}

			$loc = JobList::short_location( get_post_meta( $post->ID, '_job_location', true ) );

			if ( '' !== $loc ) {
				$data['locations'][ $loc ] = isset( $data['locations'][ $loc ] ) ? $data['locations'][ $loc ] + 1 : 1;

				if ( ! isset( $data['bydest'][ $loc ] ) ) {
					$data['bydest'][ $loc ] = array( 'count' => 0, 'trades' => array() );
				}

				++$data['bydest'][ $loc ]['count'];

				if ( $terms && ! is_wp_error( $terms ) ) {
					$data['bydest'][ $loc ]['trades'][ $terms[0]->name ] = true;
				}
			}

			$expires = (string) get_post_meta( $post->ID, '_job_expires', true );
			$closes  = $expires ? strtotime( $expires . ' 23:59:59' ) : 0;

			if ( $closes && $closes >= $now && $closes <= $now + ( 7 * DAY_IN_SECONDS ) ) {
				++$data['closing'];
			}

			if ( $closes && $closes >= $now && $closes <= $now + ( 21 * DAY_IN_SECONDS ) ) {
				$data['soon'][ $closes . '-' . $post->ID ] = $post;
			}

			if ( get_post_time( 'U', true, $post ) >= $now - ( 7 * DAY_IN_SECONDS ) ) {
				++$data['fresh'];
			}
		}

		arsort( $data['locations'] );
		ksort( $data['soon'] );

		$data['soon'] = array_slice( array_values( $data['soon'] ), 0, 6 );

		uasort(
			$data['bydest'],
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		// Trades with work first, then the rest alphabetically — the list is
		// read top-down and the useful half belongs at the top.
		uasort(
			$data['sectors'],
			function ( $a, $b ) {
				if ( $a['count'] === $b['count'] ) {
					return strcasecmp( $a['name'], $b['name'] );
				}

				return $b['count'] <=> $a['count'];
			}
		);

		return $data;
	}

	/**
	 * The page.
	 *
	 * @return string
	 */
	public static function render() {
		$data   = self::survey();
		$jobs   = home_url( '/jobs/' );
		$signed = is_user_logged_in();

		$out  = self::styles();
		$out .= '<div class="mo">';

		// ------------------------------------------------------------ hero
		$out .= '<section class="ma-hero">';
		$out .= '<h1>' . esc_html__( 'Work abroad, through a licensed agency', 'marwa-apply' ) . '</h1>';
		$out .= '<p>' . esc_html__( 'Marwa Overseas places Bangladeshi workers with employers in Japan, the Gulf and Europe. Create one account, send your documents once, and follow every application from your phone.', 'marwa-apply' ) . '</p>';

		$out .= '<form class="ma-search" method="get" action="' . esc_url( $jobs ) . '">';
		$out .= '<input type="search" name="q" placeholder="' . esc_attr__( 'Trade or skill — welder, care, driver', 'marwa-apply' ) . '" aria-label="' . esc_attr__( 'Trade or skill', 'marwa-apply' ) . '">';

		if ( $data['locations'] ) {
			$out .= '<select name="loc" aria-label="' . esc_attr__( 'Destination', 'marwa-apply' ) . '"><option value="">' . esc_html__( 'Anywhere', 'marwa-apply' ) . '</option>';

			foreach ( $data['locations'] as $value => $n ) {
				$out .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $value ) . ' (' . esc_html( number_format_i18n( $n ) ) . ')</option>';
			}

			$out .= '</select>';
		}

		$out .= '<button type="submit">' . esc_html__( 'Search jobs', 'marwa-apply' ) . '</button>';
		$out .= '</form>';

		// Destination chips. The count is the whole point: it is the
		// difference between a guess and a decision.
		if ( $data['locations'] ) {
			$out .= '<div class="ma-chips">';

			foreach ( array_slice( $data['locations'], 0, 8, true ) as $value => $n ) {
				$out .= '<a href="' . esc_url( add_query_arg( 'loc', rawurlencode( $value ), $jobs ) ) . '">';
				$out .= esc_html( $value ) . ' <b>' . esc_html( number_format_i18n( $n ) ) . '</b></a>';
			}

			$out .= '</div>';
		}

		$out .= '</section>';

		// ----------------------------------------------------------- strip
		$out .= '<div class="ma-strip">';
		$out .= '<div><b>' . esc_html( number_format_i18n( $data['open'] ) ) . '</b><span>' . esc_html__( 'Jobs open now', 'marwa-apply' ) . '</span></div>';
		$out .= '<div><b>' . esc_html( number_format_i18n( count( $data['sectors'] ) ) ) . '</b><span>' . esc_html__( 'Trades we recruit for', 'marwa-apply' ) . '</span></div>';
		$out .= '<div><b>RL 2831</b><span>' . esc_html__( 'Government licence', 'marwa-apply' ) . '</span></div>';
		$out .= '<div><b>' . esc_html__( 'Dhaka &amp; Athens', 'marwa-apply' ) . '</b><span>' . esc_html__( 'Our offices', 'marwa-apply' ) . '</span></div>';
		$out .= '</div>';

		$out .= '<div class="ap-shell ma-home-wrap"><div class="ma-home">';

		// ------------------------------------------------------- left rail
		// Browse lives here rather than in the middle. It is navigation, it
		// is read by scanning rather than by reading, and in a rail it stays
		// in view while the vacancies beside it scroll.
		$browse = '<div class="ap-card ma-browse">';

		// ---------------------------------------------------------- trades
		// One block, two ways in. Which is showing is in the URL, so it is a
		// link somebody can send, and it needs no script to switch.
		// phpcs:ignore WordPress.Security.NonceVerification
		$mode = ( isset( $_GET['browse'] ) && 'destination' === $_GET['browse'] ) ? 'destination' : 'trade';
		$here   = home_url( '/' );

		$browse .= '<div class="ma-railhead"><h2>' . esc_html__( 'Discover jobs across every trade and destination', 'marwa-apply' ) . '</h2>';
		$browse .= '<span class="ma-tabs">';
		$browse .= '<a class="' . ( 'trade' === $mode ? 'on' : '' ) . '" href="' . esc_url( $here ) . '#browse">' . esc_html__( 'By trade', 'marwa-apply' ) . '</a>';
		$browse .= '<a class="' . ( 'destination' === $mode ? 'on' : '' ) . '" href="' . esc_url( add_query_arg( 'browse', 'destination', $here ) ) . '#browse">' . esc_html__( 'By destination', 'marwa-apply' ) . '</a>';
		$browse .= '</span></div>';

		$browse .= '<p class="ap-note ma-sub" id="browse">' . esc_html__( 'The number beside each row is how many vacancies are open today.', 'marwa-apply' ) . '</p>';
		$browse .= '<ul class="ma-trades">';

		if ( 'destination' === $mode ) {
			if ( $data['locations'] ) {
				foreach ( $data['locations'] as $value => $n ) {
					$browse .= '<li><a href="' . esc_url( add_query_arg( 'loc', rawurlencode( $value ), $jobs ) ) . '">';
					$browse .= '<span class="t">' . esc_html( $value ) . '</span>';
					$browse .= '<b>' . esc_html( number_format_i18n( $n ) ) . '</b></a></li>';
				}
			} else {
				$browse .= '<li><a class="is-quiet" href="' . esc_url( $jobs ) . '"><span class="t">' . esc_html__( 'No destinations open right now', 'marwa-apply' ) . '</span></a></li>';
			}
		} else {
			foreach ( $data['sectors'] as $id => $s ) {
				$browse .= '<li><a href="' . esc_url( add_query_arg( 'sector', $id, $jobs ) ) . '" class="' . ( $s['count'] ? '' : 'is-quiet' ) . '">';
				$browse .= '<span class="t">' . esc_html( $s['name'] );

				if ( $s['jp'] ) {
					$browse .= ' <i>' . esc_html( $s['jp'] ) . '</i>';
				}

				$browse .= '</span>';

				if ( $s['count'] ) {
					$browse .= '<b>' . esc_html( number_format_i18n( $s['count'] ) ) . '</b>';
				} else {
					$browse .= '<em>' . esc_html( $s['prep'] ? __( 'In preparation', 'marwa-apply' ) : __( 'None open', 'marwa-apply' ) ) . '</em>';
				}

				$browse .= '</a></li>';
			}
		}

		$browse .= '</ul></div>';

		// ----------------------------------------------------- middle column
		// ------------------------------------------------------- left rail
		// Quick links live here now. The browse grid moved into the middle,
		// because it is the thing people actually scan and it was wasted at
		// rail width — sixty counted trades want three columns, not one.
		$out .= '<aside class="ma-left">';
		$out .= self::quick_links( $data, $jobs );
		$out .= '</aside>';

		// ----------------------------------------------------- middle column
		$out .= '<div class="ma-main">';
		$out .= $browse;

		// -------------------------------------------------- latest vacancies
		if ( $data['latest'] ) {
			$out .= '<div class="ma-head ma-first"><h2>' . esc_html__( 'Latest vacancies', 'marwa-apply' ) . '</h2>';
			$out .= '<a href="' . esc_url( $jobs ) . '">' . esc_html__( 'See all jobs &rarr;', 'marwa-apply' ) . '</a></div>';
			$out .= '<div class="ma-latest">';

			foreach ( $data['latest'] as $post ) {
				$terms   = get_the_terms( $post->ID, Sectors::TAXONOMY );
				$sector  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
				$loc     = (string) get_post_meta( $post->ID, '_job_location', true );
				$expires = (string) get_post_meta( $post->ID, '_job_expires', true );

				$out .= '<a class="ma-mini" href="' . esc_url( (string) get_permalink( $post->ID ) ) . '">';
				$out .= '<strong>' . esc_html( get_the_title( $post->ID ) ) . '</strong>';

				$bits = array_filter( array( $sector, $loc ) );

				if ( $bits ) {
					$out .= '<span>' . esc_html( implode( ' · ', $bits ) ) . '</span>';
				}

				if ( $expires ) {
					$out .= '<em>' . esc_html(
						sprintf(
							/* translators: %s: closing date */
							__( 'Closes %s', 'marwa-apply' ),
							date_i18n( 'j M Y', strtotime( $expires ) )
						)
					) . '</em>';
				}

				$out .= '</a>';
			}

			$out .= '</div>';
		}

		// ------------------------------------------------------ closing soon
		if ( $data['soon'] ) {
			$out .= '<div class="ma-head"><h2>' . esc_html__( 'Closing soon', 'marwa-apply' ) . '</h2>';
			$out .= '<a href="' . esc_url( add_query_arg( 'closing', '7', $jobs ) ) . '">' . esc_html__( 'All closing this week &rarr;', 'marwa-apply' ) . '</a></div>';
			$out .= '<ul class="ma-soon">';

			foreach ( $data['soon'] as $post ) {
				$expires = (string) get_post_meta( $post->ID, '_job_expires', true );
				$closes  = $expires ? strtotime( $expires ) : 0;
				$days    = $closes ? (int) floor( ( $closes - time() ) / DAY_IN_SECONDS ) : 0;
				$loc     = JobList::short_location( get_post_meta( $post->ID, '_job_location', true ) );

				$out .= '<li><a href="' . esc_url( (string) get_permalink( $post->ID ) ) . '">';
				$out .= '<span class="t">' . esc_html( get_the_title( $post->ID ) ) . '</span>';
				$out .= '<span class="m">' . esc_html( $loc ) . '</span>';
				$out .= '<span class="d' . ( $days <= 7 ? ' urgent' : '' ) . '">' . esc_html(
					$days <= 0
						? __( 'Closes today', 'marwa-apply' )
						: sprintf(
							/* translators: %d: number of days */
							_n( '%d day left', '%d days left', $days, 'marwa-apply' ),
							$days
						)
				) . '</span></a></li>';
			}

			$out .= '</ul>';
		}

		// ------------------------------------------------------ destinations
		if ( $data['bydest'] ) {
			$out .= '<div class="ma-head"><h2>' . esc_html__( 'Where Marwa places people', 'marwa-apply' ) . '</h2></div>';
			$out .= '<div class="ma-dests">';

			foreach ( array_slice( $data['bydest'], 0, 6, true ) as $place => $info ) {
				$trades = array_slice( array_keys( $info['trades'] ), 0, 3 );

				$out .= '<a class="ma-dest" href="' . esc_url( add_query_arg( 'loc', rawurlencode( $place ), $jobs ) ) . '">';
				$out .= '<strong>' . esc_html( $place ) . '</strong>';
				$out .= '<b>' . esc_html(
					sprintf(
						/* translators: %d: number of vacancies */
						_n( '%d vacancy open', '%d vacancies open', $info['count'], 'marwa-apply' ),
						$info['count']
					)
				) . '</b>';

				if ( $trades ) {
					$out .= '<span>' . esc_html( implode( ' · ', $trades ) ) . '</span>';
				}

				$out .= '</a>';
			}

			$out .= '</div>';
		}

		// ------------------------------------------------------------ guides
		if ( class_exists( __NAMESPACE__ . '\\Guides' ) ) {
			$guides = Guides::all( 4 );

			if ( $guides ) {
				$out .= '<div class="ma-head"><h2>' . esc_html__( 'Before you go', 'marwa-apply' ) . '</h2>';
				$out .= '<a href="' . esc_url( Pages::url( 'guides' ) ) . '">' . esc_html__( 'All guides &rarr;', 'marwa-apply' ) . '</a></div>';
				$out .= '<div class="ma-latest">';

				foreach ( $guides as $guide ) {
					$excerpt = $guide->post_excerpt ? $guide->post_excerpt : wp_trim_words( wp_strip_all_tags( $guide->post_content ), 18 );

					$out .= '<a class="ma-mini" href="' . esc_url( (string) get_permalink( $guide->ID ) ) . '">';
					$out .= '<strong>' . esc_html( get_the_title( $guide->ID ) ) . '</strong>';
					$out .= '<span>' . esc_html( $excerpt ) . '</span></a>';
				}

				$out .= '</div>';
			}
		}

		// ----------------------------------------------------- how it works
		$out .= '<h2>' . esc_html__( 'How it works', 'marwa-apply' ) . '</h2>';
		$out .= '<div class="ma-steps">';

		$steps = array(
			array(
				'h' => __( 'Create your account', 'marwa-apply' ),
				'p' => __( 'Your mobile number and an email address. Free, and it takes a minute.', 'marwa-apply' ),
			),
			array(
				'h' => __( 'Fill in your profile', 'marwa-apply' ),
				'p' => __( 'Your trade, experience and languages. Marwa builds your CV from it — you do not need to write one.', 'marwa-apply' ),
			),
			array(
				'h' => __( 'Send your documents once', 'marwa-apply' ),
				'p' => __( 'Passport, photograph, certificates. They stay in your file; you will not be asked for them again.', 'marwa-apply' ),
			),
			array(
				'h' => __( 'Apply and follow it', 'marwa-apply' ),
				'p' => __( 'Every application shows its stage, and you get an email each time it moves.', 'marwa-apply' ),
			),
		);

		foreach ( $steps as $step ) {
			$out .= '<div class="ma-step"><h3>' . esc_html( $step['h'] ) . '</h3><p>' . esc_html( $step['p'] ) . '</p></div>';
		}

		$out .= '</div></div>';

		// ------------------------------------------------------------ rail
		$out .= '<aside class="ma-rail">';

		// The licence card. This is the portal's single strongest claim and
		// the only one a jobseeker can verify from outside, so it sits where
		// a commercial board would put an advertisement.
		$out .= '<div class="ap-card ma-box ma-lic">';
		$out .= '<h3>' . esc_html__( 'A licensed recruiter', 'marwa-apply' ) . '</h3>';
		$out .= '<p class="ap-note">' . esc_html__( 'Marwa Overseas holds recruiting licence RL 2831, issued by the Ministry of Expatriates\' Welfare and Overseas Employment. Offices in Dhaka and Athens.', 'marwa-apply' ) . '</p>';
		$out .= '<p class="ap-note"><strong>' . esc_html__( 'Marwa never charges a jobseeker to open an account or to apply.', 'marwa-apply' ) . '</strong></p>';
		$out .= '</div>';

		$out .= '<div class="ap-card ma-box">';

		if ( $signed ) {
			$out .= '<h3>' . esc_html__( 'You are signed in', 'marwa-apply' ) . '</h3>';
			$out .= '<p><a class="btn primary" href="' . esc_url( Pages::url( 'account' ) ) . '">' . esc_html__( 'Go to my account', 'marwa-apply' ) . '</a></p>';
			$out .= '<p><a class="btn" href="' . esc_url( Pages::url( 'profile' ) ) . '">' . esc_html__( 'Update my profile', 'marwa-apply' ) . '</a></p>';
		} else {
			$out .= '<h3>' . esc_html__( 'Ready to start?', 'marwa-apply' ) . '</h3>';
			$out .= '<p class="ap-note">' . esc_html__( 'One account covers every vacancy on this site.', 'marwa-apply' ) . '</p>';
			$out .= '<p><a class="btn primary" href="' . esc_url( Pages::url( 'register' ) ) . '">' . esc_html__( 'Create an account', 'marwa-apply' ) . '</a></p>';
			$out .= '<p><a class="btn" href="' . esc_url( Pages::url( 'sign-in' ) ) . '">' . esc_html__( 'Sign in', 'marwa-apply' ) . '</a></p>';
		}

		$out .= '</div></aside>';

		return $out . '</div></div></div>';
	}

	/**
	 * Counted shortcuts into the list.
	 *
	 * These are saved searches somebody already knows people run — the
	 * equivalent of the "Deadline Tomorrow" and "Overseas Jobs" links a big
	 * board keeps in its rail. Every count is read from this database.
	 *
	 * @param array  $data Survey.
	 * @param string $jobs Jobs page URL.
	 * @return string
	 */
	private static function quick_links( $data, $jobs ) {
		$quick = array();

		if ( $data['fresh'] ) {
			$quick[] = array( add_query_arg( 'posted', '7', $jobs ), __( 'Posted this week', 'marwa-apply' ), $data['fresh'] );
		}

		if ( $data['closing'] ) {
			$quick[] = array( add_query_arg( 'closing', '7', $jobs ), __( 'Closing within 7 days', 'marwa-apply' ), $data['closing'] );
		}

		foreach ( array_slice( $data['locations'], 0, 6, true ) as $value => $n ) {
			$quick[] = array(
				add_query_arg( 'loc', rawurlencode( $value ), $jobs ),
				sprintf(
					/* translators: %s: destination country */
					__( 'Jobs in %s', 'marwa-apply' ),
					$value
				),
				$n,
			);
		}

		if ( ! $quick ) {
			$quick[] = array( $jobs, __( 'All vacancies', 'marwa-apply' ), $data['open'] );
		}

		$out = '<div class="ap-card ma-box ma-quick"><h3>' . esc_html__( 'Quick links', 'marwa-apply' ) . '</h3><ul>';

		foreach ( $quick as $q ) {
			$out .= '<li><a href="' . esc_url( $q[0] ) . '"><span>' . esc_html( $q[1] ) . '</span><b>' . esc_html( number_format_i18n( $q[2] ) ) . '</b></a></li>';
		}

		return $out . '</ul></div>';
	}

	/**
	 * Styles for the landing page.
	 *
	 * @return string
	 */
	private static function styles() {
		return '<style id="marwa-apply-home">
.ma-hero{background:var(--navy,#0B2239);color:#fff;padding:52px 24px 44px;text-align:center}
.ma-hero h1{color:#fff;margin:0 0 12px;font-size:34px;line-height:1.2}
.ma-hero p{color:#CBD5E0;max-width:640px;margin:0 auto 26px;font-size:17px;line-height:1.6}
.ma-search{max-width:700px;margin:0 auto;display:flex;gap:8px;flex-wrap:wrap}
.ma-search input,.ma-search select{flex:1 1 180px;min-width:0;min-height:50px;padding:13px 14px;border:0;border-radius:8px;font-size:16px;font-family:inherit}
.ma-search button{flex:0 0 auto;min-height:50px;padding:13px 26px;border:0;border-radius:8px;background:var(--accent,#A93A2C);color:#fff;font-size:16px;cursor:pointer}
.ma-chips{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;max-width:760px;margin:20px auto 0}
.ma-chips a{display:inline-flex;align-items:center;gap:7px;padding:6px 13px;border-radius:30px;border:1px solid rgba(255,255,255,.28);color:#fff;text-decoration:none;font-size:14px;line-height:1.45}
.ma-chips a:hover{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.5)}
.ma-chips a b{font-size:12.5px;font-weight:700;background:rgba(255,255,255,.16);border-radius:20px;padding:1px 8px}
.ma-strip{display:flex;flex-wrap:wrap;justify-content:center;border-bottom:1px solid var(--line,#E3E0D9);background:#fff}
.ma-strip div{padding:20px 30px;text-align:center;border-right:1px solid var(--line,#E3E0D9)}
.ma-strip div:last-child{border-right:0}
.ma-strip b{display:block;font-size:26px;line-height:1.2;color:var(--navy,#0B2239)}
.ma-strip span{font-size:13px;color:var(--muted,#5B6B7C);text-transform:uppercase;letter-spacing:.07em}
/* Same specificity problem as the jobs page: the synced design system pins
   .mo .ap-shell to 760px, which a lone class cannot beat. */
.mo .ap-shell.ma-home-wrap{max-width:1640px}
@media(min-width:1100px){.mo .ap-shell.ma-home-wrap{padding-left:34px;padding-right:34px}}
/* Rail, content, rail — the shape the big boards use, and the reason the
   page was released from the 1240px theme container. */
.ma-home{display:grid;grid-template-columns:272px minmax(0,1fr) 288px;gap:28px;align-items:start}
.ma-left{position:sticky;top:88px;display:flex;flex-direction:column;gap:14px}
.ma-browse{margin-bottom:0}
.ma-railhead{display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap}
.ma-railhead h2{margin:0;font-size:19px;line-height:1.3}
.ma-main h2{margin:34px 0 6px;font-size:22px;line-height:1.3}
.ma-main>.ma-head.ma-first{margin-top:0}
.ma-main>h2:first-child{margin-top:0}
.ma-sub{margin:0 0 14px}
.ma-head{display:flex;justify-content:space-between;align-items:baseline;gap:14px;flex-wrap:wrap;margin:34px 0 14px}
.ma-head h2{margin:0}
.ma-head a{font-size:14.5px;white-space:nowrap}
.ma-tabs{display:inline-flex;border:1px solid var(--line,#E3E0D9);border-radius:8px;overflow:hidden;background:#fff}
.ma-tabs a{padding:7px 14px;font-size:13.5px;line-height:1.4;text-decoration:none;color:var(--muted,#5B6B7C);border-right:1px solid var(--line,#E3E0D9)}
.ma-tabs a:last-child{border-right:0}
.ma-tabs a.on{background:var(--navy,#0B2239);color:#fff}
.ma-trades{list-style:none;margin:14px 0 0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(252px,1fr));gap:0 26px}
.ma-trades li{margin:0}
.ma-trades a{display:flex;align-items:baseline;gap:8px;padding:8px 2px;text-decoration:none;color:var(--ink,#0B2239);font-size:14.5px;line-height:1.5;border-bottom:1px solid var(--line,#E3E0D9)}
.ma-trades li:last-child a{border-bottom:0}
.ma-trades a:before{content:"";flex:0 0 auto;width:5px;height:5px;border-right:1.5px solid var(--accent,#A93A2C);border-bottom:1.5px solid var(--accent,#A93A2C);transform:rotate(-45deg);margin-right:2px;position:relative;top:-2px}
.ma-trades a .t{flex:1 1 auto;min-width:0}
.ma-trades a:hover .t{text-decoration:underline}
.ma-trades a .t i{font-style:normal;color:var(--muted,#5B6B7C);font-size:13px}
.ma-trades a b{flex:0 0 auto;font-size:13px;font-weight:600;color:var(--muted,#5B6B7C)}
.ma-trades a em{flex:0 0 auto;font-style:normal;font-size:12.5px;color:var(--muted,#5B6B7C)}
.ma-trades a.is-quiet .t{color:var(--muted,#5B6B7C)}
.ma-latest{display:grid;grid-template-columns:repeat(auto-fill,minmax(272px,1fr));gap:14px}
.ma-mini{display:block;padding:15px 17px;border:1px solid var(--line,#E3E0D9);border-radius:11px;background:#fff;text-decoration:none;color:inherit;transition:border-color .15s ease,box-shadow .15s ease}
.ma-mini:hover{border-color:var(--navy,#0B2239);box-shadow:0 2px 14px rgba(11,34,57,.10)}
.ma-mini strong{display:block;font-size:15.5px;line-height:1.4;color:var(--navy,#0B2239);margin-bottom:5px}
.ma-mini span{display:block;font-size:13.5px;line-height:1.5;color:var(--muted,#5B6B7C)}
.ma-mini em{display:block;font-style:normal;font-size:12.5px;color:var(--muted,#5B6B7C);margin-top:6px}
.ma-soon{list-style:none;margin:0;padding:0;border:1px solid var(--line,#E3E0D9);border-radius:12px;overflow:hidden;background:#fff}
.ma-soon li{margin:0;border-top:1px solid var(--line,#E3E0D9)}
.ma-soon li:first-child{border-top:0}
.ma-soon a{display:flex;align-items:baseline;gap:14px;padding:12px 16px;text-decoration:none;color:inherit;flex-wrap:wrap}
.ma-soon a:hover{background:var(--surface-alt,#F3F1EC)}
.ma-soon .t{flex:1 1 240px;min-width:0;font-size:15px;line-height:1.45;color:var(--navy,#0B2239);font-weight:600}
.ma-soon .m{flex:0 0 auto;font-size:13.5px;color:var(--muted,#5B6B7C)}
.ma-soon .d{flex:0 0 auto;font-size:12.5px;font-weight:700;color:var(--muted,#5B6B7C);background:var(--surface-alt,#F3F1EC);border-radius:20px;padding:3px 10px}
.ma-soon .d.urgent{color:#fff;background:var(--accent,#A93A2C)}
.ma-dests{display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:12px}
.ma-dest{display:block;padding:17px 19px;border:1px solid var(--line,#E3E0D9);border-radius:11px;background:#fff;text-decoration:none;color:inherit;transition:border-color .15s ease,box-shadow .15s ease}
.ma-dest:hover{border-color:var(--navy,#0B2239);box-shadow:0 2px 14px rgba(11,34,57,.10)}
.ma-dest strong{display:block;font-size:16.5px;line-height:1.3;color:var(--navy,#0B2239)}
.ma-dest b{display:block;font-size:13px;color:var(--accent,#A93A2C);margin:3px 0 7px}
.ma-dest span{display:block;font-size:13px;line-height:1.5;color:var(--muted,#5B6B7C)}
.ma-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;counter-reset:s}
.ma-step{padding:20px;border:1px solid var(--line,#E3E0D9);border-radius:11px;background:#fff}
.ma-step:before{counter-increment:s;content:counter(s);display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:var(--navy,#0B2239);color:#fff;font-weight:700;margin-bottom:12px}
.ma-step h3{margin:0 0 6px;font-size:16px}
.ma-step p{margin:0;font-size:14.5px;line-height:1.6;color:var(--muted,#5B6B7C)}
.ma-rail{position:sticky;top:88px;display:flex;flex-direction:column;gap:14px}
.ma-box h3{margin:0 0 10px;font-size:16px}
.ma-box p{margin:0 0 10px}
.ma-box p:last-child{margin-bottom:0}
.ma-box ul{list-style:none;margin:0;padding:0}
.ma-box li{margin:0;border-top:1px solid var(--line,#E3E0D9)}
.ma-box li:first-child{border-top:0}
.ma-box li a{display:flex;justify-content:space-between;align-items:baseline;gap:10px;padding:9px 0;font-size:14px;line-height:1.45;text-decoration:none;color:var(--ink,#0B2239)}
.ma-box li a:hover span{text-decoration:underline}
.ma-box li a b{flex:0 0 auto;font-size:12.5px;font-weight:700;color:var(--muted,#5B6B7C);background:var(--surface-alt,#F3F1EC);border-radius:20px;padding:1px 8px}
.ma-lic{background:var(--surface-alt,#F3F1EC)}
.ma-box .btn{width:100%;text-align:center;box-sizing:border-box}
@media(max-width:1360px){
.ma-home{grid-template-columns:252px minmax(0,1fr)}
.ma-rail{grid-column:1 / -1;position:static;flex-direction:row;flex-wrap:wrap}
.ma-rail>*{flex:1 1 260px}
}
@media(max-width:980px){
.mo .ap-shell.ma-home-wrap{padding-left:16px;padding-right:16px}
.ma-home{grid-template-columns:1fr}
.ma-left{position:static;max-height:none;overflow:visible}
.ma-rail{position:static;flex-direction:column}
}
@media(max-width:600px){
.ma-hero{padding:38px 18px 32px}
.ma-hero h1{font-size:26px}
.ma-hero p{font-size:16px}
.ma-search button{width:100%}
.ma-strip div{padding:15px 16px;flex:1 1 50%;border-right:0;border-top:1px solid var(--line,#E3E0D9)}
.ma-strip b{font-size:21px}
}
</style>';
	}
}
