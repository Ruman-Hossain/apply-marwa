<?php
/**
 * The job list and its filters.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces WP Job Manager's listing shortcode.
 *
 * Its own filter bar loads results over AJAX, which silently showed "no
 * listings" on this install. More to the point, a portal whose users are on
 * cheap Android phones on patchy connections should not need JavaScript to
 * show a list of jobs. Everything here is a plain GET request and a server-side
 * query: it works with JavaScript switched off, it is linkable, and the back
 * button behaves.
 *
 * The shape is taken from the job boards this audience already knows — a left
 * rail of collapsible facets, every option carrying a count, a full-width row
 * per job with the closing date visible without opening it. The counts are the
 * part worth copying: "Welder (6)" tells a man whether it is worth his data
 * allowance to tap, and a facet that would return nothing is not offered at
 * all. Every number here is counted from this database at render time.
 *
 * Faceting is done in PHP over the whole matching set rather than in SQL. That
 * is a deliberate trade: one licensed agency posts hundreds of vacancies, not
 * hundreds of thousands, and counting in PHP makes every count exact and
 * consistent with the filters actually applied. If this ever has to serve a
 * five-figure board, the facet loop is the thing to move into SQL.
 */
final class JobList {

	/**
	 * Page sizes a candidate may choose between.
	 *
	 * @var array
	 */
	const PAGE_SIZES = array( 10, 20, 50 );

	const PER_PAGE = 20;

	/**
	 * Above this many open jobs, stop counting facets in PHP.
	 *
	 * A guard rather than a limit: the list still works, the counts simply
	 * stop being shown. Better a sidebar without numbers than a page that
	 * times out.
	 */
	const FACET_LIMIT = 3000;

	/**
	 * Hook up.
	 */
	public static function init() {
		add_shortcode( 'ma_jobs', array( __CLASS__, 'render' ) );
		add_action( 'init', array( __CLASS__, 'handle_save' ), 11 );
	}

	/**
	 * Jobs this candidate has kept.
	 *
	 * User meta rather than a table: a bookmark is not a record of anything,
	 * it carries no audit obligation, and it disappears with the account.
	 *
	 * @param int $user_id User.
	 * @return array
	 */
	public static function saved( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();

		if ( ! $user_id ) {
			return array();
		}

		$ids = get_user_meta( $user_id, '_ma_saved_jobs', true );

		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Keep or drop one.
	 */
	public static function handle_save() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( empty( $_GET['ma_save_job'] ) || ! is_user_logged_in() ) {
			return;
		}

		$job_id = (int) $_GET['ma_save_job']; // phpcs:ignore WordPress.Security.NonceVerification
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ma_save_' . $job_id ) || 'job_listing' !== get_post_type( $job_id ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$saved   = self::saved( $user_id );

		if ( in_array( $job_id, $saved, true ) ) {
			$saved = array_values( array_diff( $saved, array( $job_id ) ) );
		} else {
			$saved[] = $job_id;
			$saved   = array_slice( array_unique( $saved ), -60 );
		}

		update_user_meta( $user_id, '_ma_saved_jobs', $saved );

		$back = wp_get_referer();

		wp_safe_redirect( $back ? remove_query_arg( array( 'ma_save_job', '_wpnonce' ), $back ) : home_url( '/jobs/' ) );
		exit;
	}

	/**
	 * The keep/drop control for one vacancy.
	 *
	 * @param int $job_id Job.
	 * @return string
	 */
	public static function save_link( $job_id ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$on  = in_array( (int) $job_id, self::saved(), true );
		$url = wp_nonce_url( add_query_arg( 'ma_save_job', (int) $job_id, home_url( '/' ) ), 'ma_save_' . (int) $job_id );

		return '<a class="ma-keep' . ( $on ? ' on' : '' ) . '" href="' . esc_url( $url ) . '">'
			. esc_html( $on ? __( 'Kept', 'marwa-apply' ) : __( 'Keep', 'marwa-apply' ) ) . '</a>';
	}

	/**
	 * Trim a location down to the destination a candidate thinks in.
	 *
	 * "Japan (prefecture confirmed at offer stage)" is one destination to a
	 * jobseeker, not a unique place.
	 *
	 * @param string $value Raw _job_location meta.
	 * @return string
	 */
	public static function short_location( $value ) {
		return trim( (string) preg_replace( '/\s*[\(\-,].*$/u', '', (string) $value ) );
	}

	/**
	 * Distinct destinations across open jobs, for the landing page dropdown.
	 *
	 * @return array
	 */
	public static function locations() {
		global $wpdb;

		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB
			"SELECT DISTINCT pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_job_location'
			   AND pm.meta_value <> ''
			   AND p.post_type = 'job_listing'
			   AND p.post_status = 'publish'
			 ORDER BY pm.meta_value ASC"
		);

		$out = array();

		foreach ( (array) $rows as $value ) {
			$short = self::short_location( $value );

			if ( '' !== $short ) {
				$out[ $short ] = $short;
			}
		}

		return $out;
	}

	/**
	 * Everything open, optionally narrowed by the keyword.
	 *
	 * The keyword is the one filter left to WP_Query, because full-text
	 * matching across title and body is its job and not worth reimplementing.
	 *
	 * @param string $keyword Search term.
	 * @return array Array of WP_Post.
	 */
	private static function pool( $keyword ) {
		$args = array(
			'post_type'              => 'job_listing',
			'post_status'            => 'publish',
			'posts_per_page'         => self::FACET_LIMIT,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
		);

		if ( '' !== $keyword ) {
			$args['s'] = $keyword;
		}

		$query = new \WP_Query( $args );
		$posts = $query->posts;

		wp_reset_postdata();

		return $posts;
	}

	/**
	 * Everything the list needs to know about one post, read once.
	 *
	 * @param \WP_Post $post Job post.
	 * @return array
	 */
	private static function facts( $post ) {
		$terms  = get_the_terms( $post->ID, Sectors::TAXONOMY );
		$sector = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;

		$types = get_the_terms( $post->ID, 'job_listing_type' );
		$type  = ( $types && ! is_wp_error( $types ) ) ? $types[0] : null;

		$expires = (string) get_post_meta( $post->ID, '_job_expires', true );
		$closes  = $expires ? strtotime( $expires . ' 23:59:59' ) : 0;

		return array(
			'post'        => $post,
			'sector_id'   => $sector ? (int) $sector->term_id : 0,
			'sector_name' => $sector ? $sector->name : '',
			'type_slug'   => $type ? $type->slug : '',
			'type_name'   => $type ? $type->name : '',
			'raw_loc'     => (string) get_post_meta( $post->ID, '_job_location', true ),
			'location'    => self::short_location( get_post_meta( $post->ID, '_job_location', true ) ),
			'company'     => (string) get_post_meta( $post->ID, '_company_name', true ),
			'featured'    => (bool) get_post_meta( $post->ID, '_featured', true ),
			'logo'        => (int) get_post_meta( $post->ID, '_company_logo', true ),
			'noexp'       => (bool) get_post_meta( $post->ID, '_ma_no_experience', true ),
			'minage'      => (int) get_post_meta( $post->ID, '_ma_min_age', true ),
			'maxage'      => (int) get_post_meta( $post->ID, '_ma_max_age', true ),
			'state'       => (string) get_post_meta( $post->ID, '_ma_state', true ),
			'closes'      => $closes,
			'posted'      => (int) get_post_time( 'U', true, $post ),
		);
	}

	/**
	 * Closing-date buckets, newest-urgency first.
	 *
	 * @return array
	 */
	private static function closing_buckets() {
		return array(
			'7'  => __( 'Closing within 7 days', 'marwa-apply' ),
			'14' => __( 'Closing within 14 days', 'marwa-apply' ),
			'30' => __( 'Closing within 30 days', 'marwa-apply' ),
		);
	}

	/**
	 * Age bands.
	 *
	 * Offered because destination visa rules impose real age limits, and a
	 * man of forty-five should not spend his data allowance discovering that
	 * two thirds of the board will not take him.
	 *
	 * @return array
	 */
	private static function age_bands() {
		return array(
			'22' => __( 'Under 25', 'marwa-apply' ),
			'30' => __( '25 to 34', 'marwa-apply' ),
			'38' => __( '35 to 44', 'marwa-apply' ),
			'48' => __( '45 and over', 'marwa-apply' ),
		);
	}

	/**
	 * Posted-date buckets.
	 *
	 * @return array
	 */
	private static function posted_buckets() {
		return array(
			'1'  => __( 'Posted today', 'marwa-apply' ),
			'7'  => __( 'Posted this week', 'marwa-apply' ),
			'30' => __( 'Posted this month', 'marwa-apply' ),
		);
	}

	/**
	 * Does one job satisfy one dimension of the filter?
	 *
	 * Split out so facet counting can ask "would this match if I ignored the
	 * dimension I am currently counting?" — which is what makes a count
	 * trustworthy: the number beside an option is what you will actually get
	 * when you tap it.
	 *
	 * @param array  $fact      Job facts.
	 * @param string $dimension Dimension name.
	 * @param mixed  $value     Filter value.
	 * @param int    $now       Timestamp.
	 * @return bool
	 */
	private static function matches( $fact, $dimension, $value, $now ) {
		if ( '' === $value || 0 === $value ) {
			return true;
		}

		switch ( $dimension ) {
			case 'sector':
				return (int) $fact['sector_id'] === (int) $value;

			case 'loc':
				return $fact['location'] === $value;

			case 'type':
				return $fact['type_slug'] === $value;

			case 'closing':
				return $fact['closes'] > 0
					&& $fact['closes'] >= $now
					&& $fact['closes'] <= $now + ( (int) $value * DAY_IN_SECONDS );

			case 'posted':
				return $fact['posted'] >= $now - ( (int) $value * DAY_IN_SECONDS );

			case 'noexp':
				return (bool) $fact['noexp'];

			case 'age':
				// A band matches when the posting's own limits would admit
				// somebody of that age. A posting with no limits admits
				// everybody, which is the common case and must not be hidden.
				$age = (int) $value;

				if ( ! $fact['minage'] && ! $fact['maxage'] ) {
					return true;
				}

				if ( $fact['minage'] && $age < $fact['minage'] ) {
					return false;
				}

				if ( $fact['maxage'] && $age > $fact['maxage'] ) {
					return false;
				}

				return true;
		}

		return true;
	}

	/**
	 * Apply every dimension except one.
	 *
	 * @param array  $fact   Job facts.
	 * @param array  $args   Filter values.
	 * @param string $except Dimension to ignore, or ''.
	 * @param int    $now    Timestamp.
	 * @return bool
	 */
	private static function passes( $fact, $args, $except, $now ) {
		foreach ( array( 'sector', 'loc', 'type', 'closing', 'posted', 'noexp', 'age' ) as $dimension ) {
			if ( $dimension === $except ) {
				continue;
			}

			if ( ! self::matches( $fact, $dimension, $args[ $dimension ], $now ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * A small inline icon. Inline rather than a font or sprite: one extra
	 * request on a slow connection is one too many, and these are four shapes.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	private static function icon( $name ) {
		$paths = array(
			'pin'      => '<path d="M8 1.6A4.6 4.6 0 0 0 3.4 6.2C3.4 9.6 8 14.4 8 14.4s4.6-4.8 4.6-8.2A4.6 4.6 0 0 0 8 1.6zm0 6.2a1.7 1.7 0 1 1 0-3.4 1.7 1.7 0 0 1 0 3.4z"/>',
			'building' => '<path d="M2.6 14V3.2c0-.4.3-.7.7-.7h5.4c.4 0 .7.3.7.7V14H2.6zm1.8-9.3v1.5h1.5V4.7H4.4zm2.8 0v1.5h1.5V4.7H7.2zM4.4 7.5V9h1.5V7.5H4.4zm2.8 0V9h1.5V7.5H7.2zm-2.8 2.8v1.5h1.5v-1.5H4.4zm2.8 0v1.5h1.5v-1.5H7.2zM10.4 14V6.4h2.3c.4 0 .7.3.7.7V14h-3z"/>',
			'calendar' => '<path d="M5 1.4v1.2H3.6c-.6 0-1.1.5-1.1 1.1v9.2c0 .6.5 1.1 1.1 1.1h8.8c.6 0 1.1-.5 1.1-1.1V3.7c0-.6-.5-1.1-1.1-1.1H11V1.4H9.8v1.2H6.2V1.4H5zM3.7 6h8.6v6.8H3.7V6z"/>',
			'tag'      => '<path d="M7.3 1.8H2.6c-.5 0-.8.4-.8.8v4.7c0 .2.1.4.2.6l6.1 6.1c.3.3.8.3 1.1 0l4.7-4.7c.3-.3.3-.8 0-1.1L7.9 2.1a.8.8 0 0 0-.6-.3zM4.7 5.5a.9.9 0 1 1 0-1.8.9.9 0 0 1 0 1.8z"/>',
		);

		if ( empty( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="ma-i" viewBox="0 0 16 16" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * A facet group: a heading that folds, and a list of counted links.
	 *
	 * <details> rather than a script. It remembers nothing between page loads,
	 * which is why the groups holding an active choice are opened server-side.
	 *
	 * @param string $title   Group heading.
	 * @param array  $options value => array( label, count ).
	 * @param string $key     Query argument.
	 * @param mixed  $active  Current value.
	 * @param string $base    Base URL.
	 * @param array  $keep    Other query arguments to preserve.
	 * @param int    $cut     Show this many before "Show all".
	 * @return string
	 */
	/**
	 * Is this query argument worth carrying in a URL?
	 *
	 * Used instead of 'strlen' as an array_filter callback: these values are a
	 * mix of strings and integers, and strlen() on anything but a string is a
	 * deprecation waiting to happen.
	 *
	 * @param mixed $value Candidate value.
	 * @return bool
	 */
	private static function present( $value ) {
		return '' !== (string) $value;
	}

	private static function facet( $title, $options, $key, $active, $base, $keep, $cut = 8 ) {
		// An option that would return nothing is not worth a tap. The one
		// exception is the option already chosen — it has to stay visible so
		// it can be cleared.
		$options = array_filter(
			$options,
			function ( $o, $value ) use ( $active ) {
				return $o['count'] > 0 || (string) $value === (string) $active;
			},
			ARRAY_FILTER_USE_BOTH
		);

		if ( count( $options ) < 2 && '' === (string) $active ) {
			return '';
		}

		$open = ( '' !== (string) $active ) || count( $options ) <= $cut;

		$out  = '<details class="ma-facet" data-options="' . esc_attr( (string) count( $options ) ) . '"' . ( $open ? ' open' : '' ) . '>';
		$out .= '<summary>' . esc_html( $title ) . '</summary><ul class="ma-opts">';

		$n       = 0;
		$overflow = '';

		foreach ( $options as $value => $o ) {
			$on   = ( (string) $value === (string) $active );
			$args = $keep;

			// Tapping the active option again clears it. On a phone that is
			// the gesture people reach for, and it saves a trip to "Clear".
			$args[ $key ] = $on ? '' : $value;
			$args['jp']   = '';

			$li  = '<li><a' . ( $on ? ' class="on"' : '' ) . ' href="' . esc_url( add_query_arg( array_filter( $args, array( __CLASS__, 'present' ) ), $base ) ) . '"';
			$li .= $on ? ' aria-current="true"' : '';
			$li .= '><i class="ma-r" aria-hidden="true"></i>';
			$li .= '<span>' . esc_html( $o['label'] ) . '</span>';
			$li .= '<b>' . esc_html( number_format_i18n( $o['count'] ) ) . '</b></a></li>';

			if ( $n < $cut ) {
				$out .= $li;
			} else {
				$overflow .= $li;
			}

			++$n;
		}

		$out .= '</ul>';

		if ( '' !== $overflow ) {
			$out .= '<details class="ma-more"><summary>' . esc_html(
				sprintf(
					/* translators: %d: number of further options */
					__( 'Show %d more', 'marwa-apply' ),
					$n - $cut
				)
			) . '</summary><ul class="ma-opts">' . $overflow . '</ul></details>';
		}

		return $out . '</details>';
	}

	/**
	 * The list.
	 *
	 * @return string
	 */
	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification -- a public, linkable, read-only search.
		$args = array(
			'keyword' => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
			'sector'  => isset( $_GET['sector'] ) ? (int) $_GET['sector'] : 0,
			'type'    => isset( $_GET['type'] ) ? sanitize_title( wp_unslash( $_GET['type'] ) ) : '',
			'loc'     => isset( $_GET['loc'] ) ? sanitize_text_field( wp_unslash( $_GET['loc'] ) ) : '',
			'closing' => isset( $_GET['closing'] ) ? preg_replace( '/\D/', '', wp_unslash( $_GET['closing'] ) ) : '',
			'posted'  => isset( $_GET['posted'] ) ? preg_replace( '/\D/', '', wp_unslash( $_GET['posted'] ) ) : '',
			'noexp'   => ! empty( $_GET['noexp'] ) ? '1' : '',
			'age'     => isset( $_GET['age'] ) ? preg_replace( '/\D/', '', wp_unslash( $_GET['age'] ) ) : '',
			'paged'   => isset( $_GET['jp'] ) ? max( 1, (int) $_GET['jp'] ) : 1,
			'mine'    => ! empty( $_GET['mine'] ),
			'kept'    => ! empty( $_GET['kept'] ),
		);

		$sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'new';
		$per  = isset( $_GET['per'] ) ? (int) $_GET['per'] : self::PER_PAGE;
		$view = ( isset( $_GET['view'] ) && 'grid' === $_GET['view'] ) ? 'grid' : 'list';
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! in_array( $per, self::PAGE_SIZES, true ) ) {
			$per = self::PER_PAGE;
		}

		if ( ! in_array( $sort, array( 'new', 'closing', 'az' ), true ) ) {
			$sort = 'new';
		}

		$row = is_user_logged_in() ? Profile::row() : null;

		// "Jobs in my sector" is a one-click version of the sector facet,
		// using what the candidate already told us rather than asking twice.
		if ( $args['mine'] && $row && $row->sector_id ) {
			$args['sector'] = (int) $row->sector_id;
		}

		$now  = time();
		$base = get_permalink();

		$pool  = self::pool( $args['keyword'] );
		$facts = array_map( array( __CLASS__, 'facts' ), $pool );

		// ------------------------------------------------------ facet counts
		$count = array(
			'sector'  => array(),
			'loc'     => array(),
			'type'    => array(),
			'closing' => array(),
			'posted'  => array(),
			'noexp'   => array(),
			'age'     => array(),
		);

		$labels = array(
			'sector' => array(),
			'loc'    => array(),
			'type'   => array(),
		);

		foreach ( $facts as $fact ) {
			if ( $fact['sector_id'] && self::passes( $fact, $args, 'sector', $now ) ) {
				$id = $fact['sector_id'];

				$count['sector'][ $id ]  = isset( $count['sector'][ $id ] ) ? $count['sector'][ $id ] + 1 : 1;
				$labels['sector'][ $id ] = $fact['sector_name'];
			}

			if ( '' !== $fact['location'] && self::passes( $fact, $args, 'loc', $now ) ) {
				$l = $fact['location'];

				$count['loc'][ $l ]  = isset( $count['loc'][ $l ] ) ? $count['loc'][ $l ] + 1 : 1;
				$labels['loc'][ $l ] = $l;
			}

			if ( '' !== $fact['type_slug'] && self::passes( $fact, $args, 'type', $now ) ) {
				$t = $fact['type_slug'];

				$count['type'][ $t ]  = isset( $count['type'][ $t ] ) ? $count['type'][ $t ] + 1 : 1;
				$labels['type'][ $t ] = $fact['type_name'];
			}

			foreach ( array_keys( self::closing_buckets() ) as $days ) {
				if ( self::passes( $fact, $args, 'closing', $now ) && self::matches( $fact, 'closing', $days, $now ) ) {
					$count['closing'][ $days ] = isset( $count['closing'][ $days ] ) ? $count['closing'][ $days ] + 1 : 1;
				}
			}

			if ( $fact['noexp'] && self::passes( $fact, $args, 'noexp', $now ) ) {
				$count['noexp']['1'] = isset( $count['noexp']['1'] ) ? $count['noexp']['1'] + 1 : 1;
			}

			foreach ( array_keys( self::age_bands() ) as $band ) {
				if ( self::passes( $fact, $args, 'age', $now ) && self::matches( $fact, 'age', $band, $now ) ) {
					$count['age'][ $band ] = isset( $count['age'][ $band ] ) ? $count['age'][ $band ] + 1 : 1;
				}
			}

			foreach ( array_keys( self::posted_buckets() ) as $days ) {
				if ( self::passes( $fact, $args, 'posted', $now ) && self::matches( $fact, 'posted', $days, $now ) ) {
					$count['posted'][ $days ] = isset( $count['posted'][ $days ] ) ? $count['posted'][ $days ] + 1 : 1;
				}
			}
		}

		arsort( $count['sector'] );
		arsort( $count['loc'] );
		arsort( $count['type'] );

		// ---------------------------------------------------------- results
		$matched = array();

		foreach ( $facts as $fact ) {
			// A vacancy closed to new applications is still readable at its
			// own URL — somebody mid-process needs to see it — but it has no
			// business sitting in a list of things you can apply to.
			if ( '' !== $fact['state'] && 'open' !== $fact['state'] ) {
				continue;
			}

			if ( $args['kept'] && ! in_array( (int) $fact['post']->ID, self::saved(), true ) ) {
				continue;
			}

			if ( self::passes( $fact, $args, '', $now ) ) {
				$matched[] = $fact;
			}
		}

		usort(
			$matched,
			function ( $a, $b ) use ( $sort ) {
				// A featured vacancy still sorts with everything else once a
				// candidate has chosen an order. It is given a band of its own
				// above the list instead, so nobody has to wonder why the
				// newest job is not at the top.
				if ( 'closing' === $sort ) {
					$x = $a['closes'] ? $a['closes'] : PHP_INT_MAX;
					$y = $b['closes'] ? $b['closes'] : PHP_INT_MAX;

					return $x <=> $y;
				}

				if ( 'az' === $sort ) {
					return strcasecmp( get_the_title( $a['post']->ID ), get_the_title( $b['post']->ID ) );
				}

				return $b['posted'] <=> $a['posted'];
			}
		);

		$found = count( $matched );
		$pages = max( 1, (int) ceil( $found / $per ) );
		$paged = min( $args['paged'], $pages );
		$page  = array_slice( $matched, ( $paged - 1 ) * $per, $per );

		$featured = array();

		if ( 1 === $paged ) {
			foreach ( $matched as $fact ) {
				if ( $fact['featured'] && count( $featured ) < 4 ) {
					$featured[] = $fact;
				}
			}
		}

		// Which applications this candidate already has, in one query.
		$applied = array();

		if ( $row ) {
			global $wpdb;

			$ids = array();

			foreach ( array_merge( $featured, $page ) as $fact ) {
				$ids[] = (int) $fact['post']->ID;
			}

			if ( $ids ) {
				$in      = implode( ',', array_map( 'intval', array_unique( $ids ) ) );
				$applied = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT job_id FROM ' . Install::table( 'applications' ) . " WHERE candidate_id = %d AND job_id IN ({$in})", (int) $row->id ) ); // phpcs:ignore WordPress.DB
				$applied = array_map( 'intval', $applied );
			}
		}

		// Everything a link must carry forward.
		$keep = array(
			'q'       => $args['keyword'],
			'sector'  => $args['sector'] ? $args['sector'] : '',
			'loc'     => $args['loc'],
			'type'    => $args['type'],
			'closing' => $args['closing'],
			'posted'  => $args['posted'],
			'noexp'   => $args['noexp'],
			'age'     => $args['age'],
			'sort'    => 'new' === $sort ? '' : $sort,
			'per'     => self::PER_PAGE === $per ? '' : $per,
			'view'    => 'list' === $view ? '' : $view,
		);

		$link = function ( $changes ) use ( $keep, $base ) {
			$a = array_merge( $keep, $changes );

			return add_query_arg( array_filter( $a, array( __CLASS__, 'present' ) ), $base );
		};

		$active = array_filter(
			array(
				'q'       => $args['keyword'],
				'sector'  => $args['sector'] ? $args['sector'] : '',
				'loc'     => $args['loc'],
				'type'    => $args['type'],
				'closing' => $args['closing'],
				'posted'  => $args['posted'],
				'noexp'   => $args['noexp'],
				'age'     => $args['age'],
			),
			array( __CLASS__, 'present' )
		);

		$out = self::styles();

		$out .= '<div class="mo"><div class="ap-shell ma-wrap">';
		$out .= '<div class="ma-jobs">';

		// -------------------------------------------------------- the rail
		$out .= '<aside class="ma-side">';

		$out .= '<form method="get" class="ma-find" action="' . esc_url( $base ) . '">';

		foreach ( array( 'sector', 'loc', 'type', 'closing', 'posted', 'noexp', 'age', 'sort', 'per', 'view' ) as $hidden ) {
			if ( ! empty( $keep[ $hidden ] ) ) {
				$out .= '<input type="hidden" name="' . esc_attr( $hidden ) . '" value="' . esc_attr( $keep[ $hidden ] ) . '">';
			}
		}

		$out .= '<label class="screen-reader-text" for="q">' . esc_html__( 'Search jobs', 'marwa-apply' ) . '</label>';
		$out .= '<div class="ma-find-row">';
		$out .= '<input type="search" id="q" name="q" value="' . esc_attr( $args['keyword'] ) . '" placeholder="' . esc_attr__( 'Welder, care, driver…', 'marwa-apply' ) . '">';
		$out .= '<button type="submit">' . esc_html__( 'Search', 'marwa-apply' ) . '</button>';
		$out .= '</div></form>';

		if ( $active ) {
			$out .= '<p class="ma-clear"><a href="' . esc_url( $base ) . '">' . esc_html(
				sprintf(
					/* translators: %d: number of filters applied */
					_n( 'Clear %d filter', 'Clear all %d filters', count( $active ), 'marwa-apply' ),
					count( $active )
				)
			) . '</a></p>';
		}

		$kept = self::saved();

		if ( $kept ) {
			$out .= '<p class="ma-mine"><a href="' . esc_url( add_query_arg( 'kept', '1', $base ) ) . '">' . esc_html(
				sprintf(
					/* translators: %d: number of kept jobs */
					__( 'Jobs I kept (%d)', 'marwa-apply' ),
					count( $kept )
				)
			) . '</a></p>';
		}

		if ( $row && $row->sector_id && (int) $row->sector_id !== (int) $args['sector'] ) {
			$out .= '<p class="ma-mine"><a href="' . esc_url( add_query_arg( 'mine', '1', $base ) ) . '">' . esc_html__( 'Only jobs in my trade', 'marwa-apply' ) . '</a></p>';
		}

		$sector_opts = array();

		foreach ( $count['sector'] as $id => $n ) {
			$sector_opts[ $id ] = array( 'label' => $labels['sector'][ $id ], 'count' => $n );
		}

		if ( $args['sector'] && ! isset( $sector_opts[ $args['sector'] ] ) ) {
			$term = get_term( $args['sector'], Sectors::TAXONOMY );

			if ( $term && ! is_wp_error( $term ) ) {
				$sector_opts[ $args['sector'] ] = array( 'label' => $term->name, 'count' => 0 );
			}
		}

		$loc_opts = array();

		foreach ( $count['loc'] as $value => $n ) {
			$loc_opts[ $value ] = array( 'label' => $value, 'count' => $n );
		}

		$type_opts = array();

		foreach ( $count['type'] as $slug => $n ) {
			$type_opts[ $slug ] = array( 'label' => $labels['type'][ $slug ], 'count' => $n );
		}

		$closing_opts = array();

		foreach ( self::closing_buckets() as $days => $label ) {
			$closing_opts[ $days ] = array( 'label' => $label, 'count' => isset( $count['closing'][ $days ] ) ? $count['closing'][ $days ] : 0 );
		}

		$posted_opts = array();

		foreach ( self::posted_buckets() as $days => $label ) {
			$posted_opts[ $days ] = array( 'label' => $label, 'count' => isset( $count['posted'][ $days ] ) ? $count['posted'][ $days ] : 0 );
		}

		$out .= self::facet( __( 'Destination', 'marwa-apply' ), $loc_opts, 'loc', $args['loc'], $base, $keep );
		$out .= self::facet( __( 'Trade', 'marwa-apply' ), $sector_opts, 'sector', $args['sector'], $base, $keep, 10 );
		$out .= self::facet( __( 'Contract', 'marwa-apply' ), $type_opts, 'type', $args['type'], $base, $keep );
		$out .= self::facet( __( 'Closing date', 'marwa-apply' ), $closing_opts, 'closing', $args['closing'], $base, $keep );
		$out .= self::facet( __( 'Posted', 'marwa-apply' ), $posted_opts, 'posted', $args['posted'], $base, $keep );

		$age_opts = array();

		foreach ( self::age_bands() as $band => $label ) {
			$age_opts[ $band ] = array( 'label' => $label, 'count' => isset( $count['age'][ $band ] ) ? $count['age'][ $band ] : 0 );
		}

		$out .= self::facet( __( 'Your age', 'marwa-apply' ), $age_opts, 'age', $args['age'], $base, $keep );

		if ( ! empty( $count['noexp']['1'] ) ) {
			$out .= self::facet(
				__( 'Experience', 'marwa-apply' ),
				array( '1' => array( 'label' => __( 'No experience needed', 'marwa-apply' ), 'count' => $count['noexp']['1'] ) ),
				'noexp',
				$args['noexp'],
				$base,
				$keep
			);
		}

		$out .= '</aside>';

		// The third column. Deliberately not advertising, which is what a
		// commercial board puts here: it carries the one claim a jobseeker
		// can check from outside, and whatever this particular visitor has
		// left undone.
		$rail  = '<aside class="ma-rail">';

		if ( ! $row ) {
			$rail .= '<div class="ap-card ma-box"><h3>' . esc_html__( 'Create a free account', 'marwa-apply' ) . '</h3>';
			$rail .= '<p class="ap-note">' . esc_html__( 'You need one to apply. It also keeps your documents on file, so you are never asked for your passport twice.', 'marwa-apply' ) . '</p>';
			$rail .= '<p><a class="btn primary" href="' . esc_url( Pages::url( 'register' ) ) . '">' . esc_html__( 'Create an account', 'marwa-apply' ) . '</a></p>';
			$rail .= '<p class="ap-note">' . esc_html__( 'Already registered?', 'marwa-apply' ) . ' <a href="' . esc_url( Pages::url( 'sign-in' ) ) . '">' . esc_html__( 'Sign in', 'marwa-apply' ) . '</a></p></div>';
		} elseif ( (int) $row->profile_completeness < 100 ) {
			$pct = max( 0, min( 100, (int) $row->profile_completeness ) );

			$rail .= '<div class="ap-card ma-box"><h3>' . esc_html__( 'Finish your profile', 'marwa-apply' ) . '</h3>';
			$rail .= '<p class="ap-note">' . esc_html__( 'Employers see a complete file first. Yours is:', 'marwa-apply' ) . '</p>';
			$rail .= '<div class="ma-bar-track"><span style="width:' . esc_attr( (string) $pct ) . '%"></span></div>';
			$rail .= '<p class="ap-note"><strong>' . esc_html( $pct . '%' ) . '</strong></p>';
			$rail .= '<p><a class="btn primary" href="' . esc_url( Pages::url( 'profile' ) ) . '">' . esc_html__( 'Update my profile', 'marwa-apply' ) . '</a></p></div>';
		} else {
			$rail .= '<div class="ap-card ma-box"><h3>' . esc_html__( 'Your file is complete', 'marwa-apply' ) . '</h3>';
			$rail .= '<p class="ap-note">' . esc_html__( 'Apply to anything here and Marwa already has what it needs.', 'marwa-apply' ) . '</p>';
			$rail .= '<p><a class="btn" href="' . esc_url( Pages::url( 'account' ) ) . '">' . esc_html__( 'My applications', 'marwa-apply' ) . '</a></p></div>';
		}

		$rail .= '<div class="ap-card ma-box ma-lic"><h3>' . esc_html__( 'A licensed recruiter', 'marwa-apply' ) . '</h3>';
		$rail .= '<p class="ap-note">' . esc_html__( 'Marwa Overseas holds recruiting licence RL 2831, issued by the Ministry of Expatriates&#8217; Welfare and Overseas Employment. Offices in Dhaka and Athens.', 'marwa-apply' ) . '</p>';
		$rail .= '<p class="ap-note"><strong>' . esc_html__( 'Marwa never charges a jobseeker to open an account or to apply.', 'marwa-apply' ) . '</strong></p></div>';

		$rail .= '</aside>';

		// ------------------------------------------------------- the results
		$out .= '<div class="ma-results">';

		$out .= '<div class="ma-bar"><div class="ma-found">';
		$out .= '<strong>' . esc_html( number_format_i18n( $found ) ) . '</strong> ';
		$out .= esc_html( _n( 'job open', 'jobs open', $found, 'marwa-apply' ) );

		if ( $args['keyword'] ) {
			$out .= ' <span class="ap-note">' . esc_html(
				sprintf(
					/* translators: %s: search keyword */
					__( 'for “%s”', 'marwa-apply' ),
					$args['keyword']
				)
			) . '</span>';
		}

		$out .= '</div><div class="ma-tools">';

		$sorts = array(
			'new'     => __( 'Newest', 'marwa-apply' ),
			'closing' => __( 'Closing soonest', 'marwa-apply' ),
			'az'      => __( 'A–Z', 'marwa-apply' ),
		);

		$out .= '<span class="ma-seg" role="group" aria-label="' . esc_attr__( 'Sort', 'marwa-apply' ) . '">';

		foreach ( $sorts as $value => $label ) {
			$out .= '<a class="' . ( $sort === $value ? 'on' : '' ) . '" href="' . esc_url( $link( array( 'sort' => 'new' === $value ? '' : $value, 'jp' => '' ) ) ) . '">' . esc_html( $label ) . '</a>';
		}

		$out .= '</span>';

		$out .= '<span class="ma-seg" role="group" aria-label="' . esc_attr__( 'Layout', 'marwa-apply' ) . '">';
		$out .= '<a class="' . ( 'list' === $view ? 'on' : '' ) . '" href="' . esc_url( $link( array( 'view' => '' ) ) ) . '">' . esc_html__( 'List', 'marwa-apply' ) . '</a>';
		$out .= '<a class="' . ( 'grid' === $view ? 'on' : '' ) . '" href="' . esc_url( $link( array( 'view' => 'grid' ) ) ) . '">' . esc_html__( 'Grid', 'marwa-apply' ) . '</a>';
		$out .= '</span>';

		$out .= '</div></div>';

		if ( ! $found ) {
			$out .= '<div class="ap-card"><p><strong>' . esc_html__( 'Nothing matches that search at the moment.', 'marwa-apply' ) . '</strong></p>';
			$out .= '<p class="ap-note">' . esc_html__( 'Try a wider trade, or clear the filters. New vacancies are posted as employers confirm them — create an account and your trade will be on file when one opens.', 'marwa-apply' ) . '</p>';

			if ( $active ) {
				$out .= '<p><a class="btn" href="' . esc_url( $base ) . '">' . esc_html__( 'Clear filters', 'marwa-apply' ) . '</a></p>';
			}

			$out .= '</div></div>' . $rail . '</div></div></div>';

			return $out . self::script();
		}

		if ( $featured ) {
			$out .= '<h2 class="ma-band">' . esc_html__( 'Featured vacancies', 'marwa-apply' ) . '</h2>';
			$out .= '<div class="ma-feat">';

			foreach ( $featured as $fact ) {
				$out .= self::card( $fact, $applied, $now, true );
			}

			$out .= '</div>';
			$out .= '<h2 class="ma-band">' . esc_html__( 'All vacancies', 'marwa-apply' ) . '</h2>';
		}

		$out .= '<div class="' . ( 'grid' === $view ? 'ma-grid' : 'ma-list' ) . '">';

		foreach ( $page as $fact ) {
			$out .= self::card( $fact, $applied, $now, 'grid' === $view );
		}

		$out .= '</div>';

		// ------------------------------------------------------- pagination
		$out .= '<div class="ma-foot">';

		if ( $pages > 1 ) {
			$out .= '<div class="ma-pages">';

			if ( $paged > 1 ) {
				$out .= '<a href="' . esc_url( $link( array( 'jp' => $paged - 1 ) ) ) . '">' . esc_html__( '&larr; Previous', 'marwa-apply' ) . '</a>';
			}

			$out .= '<span>' . esc_html(
				sprintf(
					/* translators: 1: current page 2: total pages */
					__( 'Page %1$d of %2$d', 'marwa-apply' ),
					$paged,
					$pages
				)
			) . '</span>';

			if ( $paged < $pages ) {
				$out .= '<a href="' . esc_url( $link( array( 'jp' => $paged + 1 ) ) ) . '">' . esc_html__( 'Next &rarr;', 'marwa-apply' ) . '</a>';
			}

			$out .= '</div>';
		} else {
			$out .= '<div></div>';
		}

		$out .= '<div class="ma-per"><span>' . esc_html__( 'Per page', 'marwa-apply' ) . '</span><span class="ma-seg">';

		foreach ( self::PAGE_SIZES as $size ) {
			$out .= '<a class="' . ( $per === $size ? 'on' : '' ) . '" href="' . esc_url( $link( array( 'per' => self::PER_PAGE === $size ? '' : $size, 'jp' => '' ) ) ) . '">' . esc_html( (string) $size ) . '</a>';
		}

		$out .= '</span></div></div>';

		return $out . '</div>' . $rail . '</div></div></div>' . self::script();
	}

	/**
	 * Type-to-filter inside a long facet group.
	 *
	 * Added by script rather than printed in the markup, deliberately: with
	 * JavaScript off there is no box at all, instead of a box that looks like
	 * a search and does nothing. Everything that decides what you see is still
	 * a link and still in the URL — this only hides rows you are not looking
	 * at, and the list comes back the moment the field is cleared.
	 *
	 * @return string
	 */
	private static function script() {
		return '<script id="marwa-apply-jobs-js">
(function(){
	var groups = document.querySelectorAll( ".ma-facet[data-options]" );

	Array.prototype.forEach.call( groups, function ( group ) {
		if ( parseInt( group.getAttribute( "data-options" ), 10 ) < 8 ) {
			return;
		}

		var box = document.createElement( "input" );

		box.type = "search";
		box.className = "ma-fsearch";
		box.setAttribute( "aria-label", group.querySelector( "summary" ).textContent.trim() );
		box.placeholder = ' . wp_json_encode( __( 'Type to narrow…', 'marwa-apply' ) ) . ';

		var list = group.querySelector( "ul.ma-opts" );

		if ( ! list ) {
			return;
		}

		list.parentNode.insertBefore( box, list );

		box.addEventListener( "input", function () {
			var needle = box.value.toLowerCase().trim();
			var shown  = 0;

			Array.prototype.forEach.call( group.querySelectorAll( "ul.ma-opts li" ), function ( li ) {
				var label = li.textContent.toLowerCase();
				var hit   = ! needle || label.indexOf( needle ) !== -1;

				li.hidden = ! hit;

				if ( hit ) {
					shown++;
				}
			} );

			// A group whose overflow is hidden behind "Show more" would
			// otherwise appear to have no matches at all.
			var more = group.querySelector( "details.ma-more" );

			if ( more ) {
				more.open = !! needle;
			}

			group.classList.toggle( "is-empty", shown === 0 );
		} );
	} );
})();
</script>';
	}

	/**
	 * One vacancy.
	 *
	 * The closing date is on the card rather than behind a click. It is the
	 * single fact that decides whether a man bothers to open a posting at all,
	 * and hiding it wastes his time and his data.
	 *
	 * @param array $fact    Job facts.
	 * @param array $applied Job IDs this candidate has applied to.
	 * @param int   $now     Timestamp.
	 * @param bool  $compact Grid or featured card.
	 * @return string
	 */
	private static function card( $fact, $applied, $now, $compact = false ) {
		$post = $fact['post'];
		$url  = (string) get_permalink( $post->ID );
		$done = in_array( (int) $post->ID, $applied, true );

		$out = '<article class="ma-card' . ( $compact ? ' is-compact' : '' ) . '">';

		$out .= '<div class="ma-card-top">';
		$out .= '<div class="ma-card-head">';
		$out .= '<h3><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post->ID ) ) . '</a></h3>';

		if ( $fact['company'] ) {
			$out .= '<p class="ma-co">' . esc_html( $fact['company'] ) . '</p>';
		}

		$out .= '</div>' . self::mark( $fact ) . '</div>';

		$out .= '<ul class="ma-meta">';

		if ( $fact['raw_loc'] ) {
			$out .= '<li>' . self::icon( 'pin' ) . esc_html( $fact['raw_loc'] ) . '</li>';
		}

		if ( $fact['sector_name'] ) {
			$out .= '<li>' . self::icon( 'tag' ) . esc_html( $fact['sector_name'] ) . '</li>';
		}

		if ( $fact['type_name'] ) {
			$out .= '<li>' . self::icon( 'building' ) . esc_html( $fact['type_name'] ) . '</li>';
		}

		if ( $fact['noexp'] ) {
			$out .= '<li class="ma-noexp">' . esc_html__( 'No experience needed', 'marwa-apply' ) . '</li>';
		}

		$out .= '</ul>';

		if ( ! $compact ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 30 );

			if ( $excerpt ) {
				$out .= '<p class="ma-ex">' . esc_html( $excerpt ) . '</p>';
			}
		}

		$peek = self::requirements( $post );

		if ( $peek ) {
			$out .= '<div class="ma-peek"><strong>' . esc_html__( 'Requirements', 'marwa-apply' ) . '</strong><ul>';

			foreach ( $peek as $line ) {
				$out .= '<li>' . esc_html( $line ) . '</li>';
			}

			$out .= '</ul></div>';
		}

		$out .= '<div class="ma-cta">';

		if ( $fact['closes'] ) {
			$days  = (int) floor( ( $fact['closes'] - $now ) / DAY_IN_SECONDS );
			$soon  = $days >= 0 && $days <= 7;
			$class = $soon ? 'ma-dl is-soon' : 'ma-dl';

			$out .= '<span class="' . $class . '">' . self::icon( 'calendar' );
			$out .= esc_html(
				sprintf(
					/* translators: %s: date the vacancy closes */
					__( 'Closes %s', 'marwa-apply' ),
					date_i18n( 'j M Y', $fact['closes'] )
				)
			);
			$out .= '</span>';
		} else {
			$out .= '<span></span>';
		}

		$out .= '<span class="ma-cta-r">' . self::save_link( (int) $post->ID );

		if ( $done ) {
			$out .= '<span class="ma-done">' . esc_html__( 'Applied', 'marwa-apply' ) . '</span>';
		} else {
			$out .= '<a class="btn" href="' . esc_url( $url ) . '">' . esc_html__( 'Read and apply', 'marwa-apply' ) . '</a>';
		}

		$out .= '</span>';

		return $out . '</div></article>';
	}

	/**
	 * The employer's mark.
	 *
	 * A logo where one has been uploaded, otherwise the company initials on a
	 * tinted square. The fallback matters more than the logo: on the board
	 * this pattern was taken from, showing a logo is a paid upsell, so half
	 * the cards are blank. Marwa charges nobody, so no card here is blank.
	 *
	 * @param array $fact Job facts.
	 * @return string
	 */
	private static function mark( $fact ) {
		if ( $fact['logo'] ) {
			$img = wp_get_attachment_image(
				$fact['logo'],
				'thumbnail',
				false,
				array(
					'class'   => 'ma-logo',
					'alt'     => $fact['company'] ? $fact['company'] : '',
					'loading' => 'lazy',
				)
			);

			if ( $img ) {
				return '<div class="ma-mark">' . $img . '</div>';
			}
		}

		$name = trim( (string) $fact['company'] );

		if ( '' === $name ) {
			return '';
		}

		$parts = preg_split( '/\s+/u', $name );
		$cut   = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';
		$mark  = $cut( $parts[0], 0, 1 ) . ( isset( $parts[1] ) ? $cut( $parts[1], 0, 1 ) : '' );

		// Hue from the name, so an employer keeps the same colour everywhere
		// without anybody having to choose one.
		$hue = crc32( $name ) % 360;

		return '<div class="ma-mark"><span class="ma-letter" style="background:hsl(' . (int) $hue . ',34%,32%)">' . esc_html( $mark ) . '</span></div>';
	}

	/**
	 * The first few requirement lines from a posting.
	 *
	 * Pulled from the posting's own Requirements list rather than from a
	 * separate field, so it stays true when somebody edits the posting and
	 * forgets this exists.
	 *
	 * @param \WP_Post $post Job post.
	 * @return array
	 */
	private static function requirements( $post ) {
		$html = (string) $post->post_content;

		if ( ! preg_match( '~Requirements\s*</h[1-6]>(.*?)(?:<h[1-6]|$)~si', $html, $m ) ) {
			return array();
		}

		if ( ! preg_match_all( '~<li[^>]*>(.*?)</li>~si', $m[1], $items ) ) {
			return array();
		}

		$out = array();

		foreach ( $items[1] as $item ) {
			$text = trim( wp_strip_all_tags( $item ) );

			if ( '' !== $text ) {
				$out[] = wp_trim_words( $text, 18 );
			}

			if ( count( $out ) >= 4 ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Styles for the listing. Scoped to .ma-jobs so nothing here can reach
	 * the rest of the site.
	 *
	 * @return string
	 */
	private static function styles() {
		return '<style id="marwa-apply-jobs">
/* The synced design system sizes .ap-shell for a marketing page at 760px.
   A faceted list needs the room, and .mo .ap-shell out-specifies a lone
   class, so this has to be at least as specific to land. */
.mo .ap-shell.ma-wrap{max-width:1640px}
@media(min-width:1100px){.mo .ap-shell.ma-wrap{padding-left:34px;padding-right:34px}}
/* Rail, results, rail. */
.ma-jobs{display:grid;grid-template-columns:286px minmax(0,1fr) 300px;gap:30px;align-items:start}
.ma-rail{position:sticky;top:88px;display:flex;flex-direction:column;gap:14px}
.ma-box h3{margin:0 0 10px;font-size:16px}
.ma-box p{margin:0 0 10px}
.ma-box p:last-child{margin-bottom:0}
.ma-box .btn{width:100%;text-align:center;box-sizing:border-box}
.ma-lic{background:var(--surface-alt,#F3F1EC)}
.ma-bar-track{height:7px;border-radius:20px;background:var(--line,#E3E0D9);overflow:hidden;margin:0 0 8px}
.ma-bar-track span{display:block;height:100%;background:var(--accent,#A93A2C)}
.ma-side{position:sticky;top:88px;border:1px solid var(--line,#E3E0D9);border-radius:12px;background:#fff;overflow:hidden}
.ma-find{padding:14px}
.ma-find-row{display:flex;gap:6px}
.ma-find-row input{flex:1 1 auto;min-width:0;min-height:44px;padding:10px 12px;font-size:15px;border:1px solid var(--line,#E3E0D9);border-radius:8px;background:#fff}
.ma-find-row button{flex:0 0 auto;min-height:44px;padding:10px 14px;border:0;border-radius:8px;background:var(--navy,#0B2239);color:#fff;font-size:14px;cursor:pointer}
.ma-clear,.ma-mine{margin:0;padding:0 14px 12px;font-size:14px}
.ma-clear a{color:var(--accent,#A93A2C)}
.ma-facet{border-top:1px solid var(--line,#E3E0D9)}
.ma-facet>summary{list-style:none;cursor:pointer;padding:13px 14px;font-size:14px;font-weight:700;color:var(--navy,#0B2239);display:flex;justify-content:space-between;align-items:center}
.ma-facet>summary::-webkit-details-marker{display:none}
.ma-facet>summary:after{content:"";width:8px;height:8px;border-right:2px solid var(--muted,#5B6B7C);border-bottom:2px solid var(--muted,#5B6B7C);transform:rotate(45deg);margin-left:8px;flex:0 0 auto}
.ma-facet[open]>summary:after{transform:rotate(-135deg)}
.ma-facet ul{list-style:none;margin:0;padding:0 0 8px}
.ma-facet li{margin:0}
.ma-facet li[hidden]{display:none}
.ma-facet li a{display:flex;gap:9px;align-items:center;padding:7px 14px;font-size:14px;line-height:1.45;text-decoration:none;color:var(--ink,#0B2239)}
.ma-facet li a span{flex:1 1 auto;min-width:0}
.ma-r{flex:0 0 auto;width:15px;height:15px;border:1.5px solid var(--line,#E3E0D9);border-radius:50%;background:#fff;position:relative}
.ma-facet li a:hover .ma-r{border-color:var(--muted,#5B6B7C)}
.ma-facet li a.on .ma-r{border-color:var(--accent,#A93A2C)}
.ma-facet li a.on .ma-r:after{content:"";position:absolute;inset:2.5px;border-radius:50%;background:var(--accent,#A93A2C)}
.ma-fsearch{display:block;width:calc(100% - 28px);margin:0 14px 8px;min-height:36px;padding:7px 10px;font-size:13.5px;font-family:inherit;border:1px solid var(--line,#E3E0D9);border-radius:7px;background:#fff;box-sizing:border-box}
.ma-facet.is-empty ul.ma-opts:after{content:"";display:block}
.ma-facet li a:hover{background:var(--surface-alt,#F3F1EC)}
.ma-facet li a.on{background:var(--surface-alt,#F3F1EC);font-weight:700}
.ma-facet li a b{flex:0 0 auto;font-weight:600;font-size:12.5px;color:var(--muted,#5B6B7C);background:var(--surface-alt,#F3F1EC);border-radius:20px;padding:1px 8px}
.ma-facet li a.on b{background:#fff}
.ma-more>summary{cursor:pointer;padding:4px 14px 10px;font-size:13.5px;color:var(--accent,#A93A2C);list-style:none}
.ma-more>summary::-webkit-details-marker{display:none}
.ma-bar{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px}
.ma-found{font-size:16px;line-height:1.4}
.ma-found strong{font-size:20px;color:var(--navy,#0B2239)}
.ma-tools{display:flex;gap:10px;flex-wrap:wrap}
.ma-seg{display:inline-flex;border:1px solid var(--line,#E3E0D9);border-radius:8px;overflow:hidden;background:#fff}
.ma-seg a{padding:7px 12px;font-size:13.5px;line-height:1.4;text-decoration:none;color:var(--muted,#5B6B7C);border-right:1px solid var(--line,#E3E0D9)}
.ma-seg a:last-child{border-right:0}
.ma-seg a.on{background:var(--navy,#0B2239);color:#fff}
.ma-band{font-size:15px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted,#5B6B7C);margin:22px 0 10px}
.ma-band:first-of-type{margin-top:0}
.ma-feat{display:grid;grid-template-columns:repeat(auto-fill,minmax(272px,1fr));gap:14px}
.ma-feat .ma-card{border-color:var(--navy,#0B2239);background:var(--surface-alt,#F3F1EC)}
.ma-list{display:flex;flex-direction:column;gap:12px}
.ma-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(272px,1fr));gap:14px}
/* Hover is a shadow, never a border width or a transform: in a grid, a card
   that grows on hover nudges its neighbours and the row jitters under the
   cursor. This changes nothing that occupies space. */
.ma-card{border:1px solid var(--line,#E3E0D9);border-radius:12px;background:#fff;padding:18px 20px;display:flex;flex-direction:column;transition:border-color .15s ease,box-shadow .15s ease}
.ma-card:hover,.ma-card:focus-within{border-color:var(--navy,#0B2239);box-shadow:0 2px 14px rgba(11,34,57,.10)}
.ma-card-top{display:flex;gap:14px;align-items:flex-start}
.ma-card-head{flex:1 1 auto;min-width:0}
.ma-mark{flex:0 0 auto;width:52px;height:52px;border-radius:9px;overflow:hidden;background:var(--surface-alt,#F3F1EC);display:flex;align-items:center;justify-content:center}
.ma-mark img,.ma-logo{width:100%;height:100%;object-fit:contain;display:block}
.ma-letter{display:flex;width:100%;height:100%;align-items:center;justify-content:center;color:#fff;font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.02em}
.ma-card.is-compact .ma-mark{width:42px;height:42px}
.ma-card h3{margin:0 0 4px;font-size:17.5px;line-height:1.35}
.ma-card.is-compact h3{font-size:16px}
.ma-card h3 a{text-decoration:none;color:var(--navy,#0B2239)}
.ma-card h3 a:hover{text-decoration:underline}
.ma-co{margin:0 0 9px;font-size:14.5px;line-height:1.4;color:var(--muted,#5B6B7C)}
.ma-meta{list-style:none;margin:0 0 10px;padding:0;display:flex;flex-wrap:wrap;gap:6px 16px}
.ma-meta li{display:flex;align-items:center;gap:6px;font-size:13.5px;line-height:1.45;color:var(--muted,#5B6B7C)}
.ma-noexp{color:#1F7A4D!important;font-weight:600}
.ma-i{width:14px;height:14px;flex:0 0 auto;fill:currentColor;opacity:.75}
.ma-ex{margin:0 0 12px;font-size:14.5px;line-height:1.6;color:var(--muted,#5B6B7C)}
/* Expand-in-place. On a pointer device the extra block overlays downward so
   it never moves the cards beside it — a row that reflows under the cursor is
   worse than no preview at all. Where there is no hover, it is simply part of
   the card and always visible. */
.ma-peek{border-top:1px solid var(--line,#E3E0D9);margin-top:10px;padding-top:10px}
.ma-peek strong{display:block;font-size:12px;letter-spacing:.07em;text-transform:uppercase;color:var(--muted,#5B6B7C);margin-bottom:6px}
.ma-peek ul{list-style:none;margin:0;padding:0}
.ma-peek li{position:relative;padding-left:15px;font-size:13.5px;line-height:1.55;color:var(--muted,#5B6B7C);margin-bottom:4px}
.ma-peek li:before{content:"";position:absolute;left:0;top:8px;width:5px;height:5px;border-radius:50%;background:var(--line,#E3E0D9)}
@media(hover:hover){
.ma-card{position:relative}
.ma-peek{position:absolute;left:-1px;right:-1px;top:100%;z-index:4;margin:0;padding:12px 20px 16px;
	background:#fff;border:1px solid var(--navy,#0B2239);border-top:0;border-radius:0 0 12px 12px;
	box-shadow:0 14px 28px rgba(11,34,57,.14);
	opacity:0;visibility:hidden;transform:translateY(-6px);transition:opacity .14s ease,transform .14s ease,visibility .14s}
.ma-card:hover,.ma-card:focus-within{z-index:5;border-bottom-left-radius:0;border-bottom-right-radius:0}
.ma-card:hover .ma-peek,.ma-card:focus-within .ma-peek{opacity:1;visibility:visible;transform:translateY(0)}
}
.ma-cta{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:auto;padding-top:10px;border-top:1px solid var(--line,#E3E0D9)}
.ma-dl{display:flex;align-items:center;gap:6px;font-size:13.5px;color:var(--muted,#5B6B7C)}
.ma-dl.is-soon{color:#B4231A;font-weight:600}
.ma-done{font-size:14px;font-weight:700;color:#1F7A4D}
.ma-cta-r{display:flex;align-items:center;gap:12px}
.ma-keep{font-size:13.5px;text-decoration:none;color:var(--muted,#5B6B7C);white-space:nowrap}
.ma-keep:before{content:"\2606";margin-right:4px;font-size:15px}
.ma-keep.on{color:var(--accent,#A93A2C);font-weight:600}
.ma-keep.on:before{content:"\2605"}
.ma-foot{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-top:18px;padding-top:14px;border-top:1px solid var(--line,#E3E0D9)}
.ma-pages{display:flex;align-items:center;gap:14px;font-size:14.5px}
.ma-per{display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--muted,#5B6B7C)}
@media(max-width:1360px){
.ma-jobs{grid-template-columns:286px minmax(0,1fr)}
.ma-rail{grid-column:1 / -1;position:static;flex-direction:row;flex-wrap:wrap}
.ma-rail>*{flex:1 1 280px}
}
@media(max-width:900px){
.mo .ap-shell.ma-wrap{padding-left:16px;padding-right:16px}
.ma-jobs{grid-template-columns:1fr}
.ma-rail{position:static;flex-direction:column}
.ma-side{position:static}
.ma-facet:not([open]) ul{display:none}
.ma-bar{margin-bottom:12px}
.ma-tools{width:100%}
.ma-card{padding:16px}
}
</style>';
	}
}
