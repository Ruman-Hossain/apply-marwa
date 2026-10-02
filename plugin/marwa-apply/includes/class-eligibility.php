<?php
/**
 * Can this candidate actually take this job?
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * The honest version of a matching score.
 *
 * Bdjobs puts a "Matching Percentage" gauge on every posting and locks it
 * behind a paid tier. As a number it tells a candidate almost nothing; as a
 * locked number it exists to sell an upgrade.
 *
 * This does the same comparison and gives the answer away, as a list rather
 * than a score, because "you have the care certificate, you still need
 * JFT-Basic" is actionable and "67%" is not.
 *
 * It also pays for itself on the staff side. Most hopeless applications are
 * made by people who did not know what the posting required; telling them
 * before they apply is cheaper for everyone than screening them out after.
 *
 * Nothing here blocks an application. A candidate who wants to apply anyway
 * may — the posting may be wrong, an employer may waive something, and a man
 * who has decided to try is not helped by a locked door.
 */
final class Eligibility {

	/**
	 * Compare a candidate against a posting.
	 *
	 * @param object $row    Candidate row.
	 * @param int    $job_id Job.
	 * @return array ok, gaps, have
	 */
	public static function check( $row, $job_id ) {
		$spec = Vacancy::spec( $job_id );
		$gaps = array();
		$have = array();

		// ------------------------------------------------------------ age
		if ( $row->dob && '0000-00-00' !== $row->dob ) {
			$age = (int) floor( ( time() - strtotime( $row->dob ) ) / YEAR_IN_SECONDS );

			if ( $spec['min_age'] && $age < $spec['min_age'] ) {
				$gaps[] = array(
					'text' => sprintf(
						/* translators: %d: minimum age */
						__( 'This employer asks for %d or over.', 'marwa-apply' ),
						$spec['min_age']
					),
					'fix'  => '',
				);
			} elseif ( $spec['max_age'] && $age > $spec['max_age'] ) {
				$gaps[] = array(
					'text' => sprintf(
						/* translators: %d: maximum age */
						__( 'This employer asks for %d or under. Destination visa rules usually set this, not the employer.', 'marwa-apply' ),
						$spec['max_age']
					),
					'fix'  => '',
				);
			} elseif ( $spec['min_age'] || $spec['max_age'] ) {
				$have[] = __( 'Your age fits this posting', 'marwa-apply' );
			}
		} elseif ( $spec['min_age'] || $spec['max_age'] ) {
			$gaps[] = array(
				'text' => __( 'Add your date of birth — this posting has an age limit.', 'marwa-apply' ),
				'fix'  => add_query_arg( 'sec', 'about', Pages::url( 'profile' ) ),
			);
		}

		// ----------------------------------------------------- experience
		if ( $spec['min_years'] && ! $spec['no_experience'] ) {
			if ( null === $row->years_experience ) {
				$gaps[] = array(
					'text' => __( 'Say how many years you have worked in your trade.', 'marwa-apply' ),
					'fix'  => add_query_arg( 'sec', 'trade', Pages::url( 'profile' ) ),
				);
			} elseif ( (int) $row->years_experience < $spec['min_years'] ) {
				$gaps[] = array(
					'text' => sprintf(
						/* translators: %d: years required */
						__( 'This posting asks for %d years in the trade.', 'marwa-apply' ),
						$spec['min_years']
					),
					'fix'  => '',
				);
			} else {
				$have[] = __( 'You have the experience asked for', 'marwa-apply' );
			}
		}

		if ( $spec['no_experience'] ) {
			$have[] = __( 'No previous experience needed for this one', 'marwa-apply' );
		}

		// ------------------------------------------------------- passport
		if ( 'required' === $spec['passport'] ) {
			if ( in_array( (string) $row->passport_status, array( 'has', 'valid' ), true ) ) {
				$have[] = __( 'You have a passport', 'marwa-apply' );
			} else {
				$gaps[] = array(
					'text' => __( 'A valid passport is needed before this employer will interview. It is the thing that most often holds people up — start it now.', 'marwa-apply' ),
					'fix'  => add_query_arg( 'sec', 'travel', Pages::url( 'profile' ) ),
				);
			}
		}

		// ------------------------------------------------------ languages
		$gaps = array_merge( $gaps, self::language_gap( $row, $spec, $have ) );

		// ---------------------------------------------------------- exams
		if ( $spec['exams'] ) {
			$held = array();

			foreach ( Records::rows( 'certificates', (int) $row->id ) as $c ) {
				$held[] = strtolower( (string) $c->title );
			}

			foreach ( $spec['exams'] as $needed ) {
				$found = false;

				foreach ( $held as $h ) {
					// Loose matching on purpose: a candidate types "JLPT N4"
					// and the posting says "JLPT N4 or JFT-Basic". Demanding
					// an exact string would fail honest people.
					if ( false !== stripos( $h, strtolower( $needed ) ) || false !== stripos( $needed, $h ) ) {
						$found = true;
						break;
					}
				}

				if ( $found ) {
					$have[] = sprintf(
						/* translators: %s: exam name */
						__( 'You have %s on file', 'marwa-apply' ),
						$needed
					);
				} else {
					$gaps[] = array(
						'text' => sprintf(
							/* translators: %s: exam name */
							__( 'This posting requires %s.', 'marwa-apply' ),
							$needed
						),
						'fix'  => add_query_arg( 'sec', 'education', Pages::url( 'profile' ) ),
					);
				}
			}
		}

		return array(
			'ok'   => empty( $gaps ),
			'gaps' => $gaps,
			'have' => $have,
		);
	}

	/**
	 * Language comparison, kept separate because the ladder matters.
	 *
	 * @param object $row  Candidate.
	 * @param array  $spec Posting.
	 * @param array  $have Collected positives, by reference.
	 * @return array
	 */
	private static function language_gap( $row, $spec, &$have ) {
		$gaps = array();

		$ladder = array( 'none' => 0, 'studying' => 1, 'jft-basic' => 2, 'n5' => 2, 'n4' => 3, 'n3' => 4, 'n2' => 5 );

		if ( $spec['jp_level'] && isset( $ladder[ $spec['jp_level'] ] ) ) {
			$mine  = isset( $ladder[ (string) $row->jp_language_level ] ) ? $ladder[ (string) $row->jp_language_level ] : 0;
			$needs = $ladder[ $spec['jp_level'] ];

			if ( $mine >= $needs ) {
				$have[] = __( 'Your Japanese meets this posting', 'marwa-apply' );
			} else {
				$levels = Profile::jp_levels();
				$label  = isset( $levels[ $spec['jp_level'] ] ) ? $levels[ $spec['jp_level'] ] : $spec['jp_level'];

				$gaps[] = array(
					'text' => sprintf(
						/* translators: %s: required level */
						__( 'Japanese at %s is needed. This is the slowest thing to fix, so start before a vacancy opens rather than after.', 'marwa-apply' ),
						$label
					),
					'fix'  => add_query_arg( 'sec', 'travel', Pages::url( 'profile' ) ),
				);
			}
		}

		if ( $spec['en_level'] && 'none' !== $spec['en_level'] ) {
			$en = array( 'none' => 0, 'basic' => 1, 'good' => 2, 'fluent' => 3 );

			$mine  = isset( $en[ (string) $row->en_language_level ] ) ? $en[ (string) $row->en_language_level ] : 0;
			$needs = isset( $en[ $spec['en_level'] ] ) ? $en[ $spec['en_level'] ] : 0;

			if ( $mine >= $needs ) {
				$have[] = __( 'Your English meets this posting', 'marwa-apply' );
			} else {
				$gaps[] = array(
					'text' => __( 'More English is needed for this one.', 'marwa-apply' ),
					'fix'  => add_query_arg( 'sec', 'travel', Pages::url( 'profile' ) ),
				);
			}
		}

		return $gaps;
	}

	/**
	 * The panel, for the posting page.
	 *
	 * @param object $row    Candidate.
	 * @param int    $job_id Job.
	 * @return string
	 */
	public static function panel( $row, $job_id ) {
		$result = self::check( $row, $job_id );

		if ( ! $result['gaps'] && ! $result['have'] ) {
			return '';
		}

		$out  = '<section class="ma-elig' . ( $result['ok'] ? ' is-ok' : '' ) . '">';
		$out .= '<h3>' . esc_html(
			$result['ok']
				? __( 'You meet what this posting asks for', 'marwa-apply' )
				: __( 'What you still need for this one', 'marwa-apply' )
		) . '</h3>';

		if ( $result['gaps'] ) {
			$out .= '<ul class="ma-elig-gaps">';

			foreach ( $result['gaps'] as $gap ) {
				$out .= '<li>' . esc_html( $gap['text'] );

				if ( $gap['fix'] ) {
					$out .= ' <a href="' . esc_url( $gap['fix'] ) . '">' . esc_html__( 'Fix this', 'marwa-apply' ) . '</a>';
				}

				$out .= '</li>';
			}

			$out .= '</ul>';
			$out .= '<p class="ma-elig-note">' . esc_html__( 'You can still apply. Marwa will tell you honestly whether it is worth putting you forward, and what to do first if it is not.', 'marwa-apply' ) . '</p>';
		}

		if ( $result['have'] ) {
			$out .= '<ul class="ma-elig-have">';

			foreach ( $result['have'] as $line ) {
				$out .= '<li>' . esc_html( $line ) . '</li>';
			}

			$out .= '</ul>';
		}

		return $out . '</section>';
	}
}
