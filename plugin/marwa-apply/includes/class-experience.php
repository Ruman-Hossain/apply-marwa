<?php
/**
 * Work history.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Previous jobs, one row each.
 *
 * Kept separate from the profile form because it repeats: a man with four
 * previous employers should not meet a form with four fixed slots, and should
 * not lose the three he has typed because the fourth was wrong. Each entry is
 * added on its own and saved on its own.
 */
final class Experience {

	const MAX_ROWS = 12;

	/**
	 * Countries Marwa actually places into, plus home.
	 *
	 * A free-text country field produces "KSA", "Saudi", "saudi arabia" and
	 * "S.Arabia" in one afternoon.
	 *
	 * @return array code => label
	 */
	public static function countries() {
		return array(
			'BD' => __( 'Bangladesh (at home)', 'marwa-apply' ),
			'JP' => __( 'Japan', 'marwa-apply' ),
			'SA' => __( 'Saudi Arabia', 'marwa-apply' ),
			'AE' => __( 'United Arab Emirates', 'marwa-apply' ),
			'QA' => __( 'Qatar', 'marwa-apply' ),
			'KW' => __( 'Kuwait', 'marwa-apply' ),
			'OM' => __( 'Oman', 'marwa-apply' ),
			'BH' => __( 'Bahrain', 'marwa-apply' ),
			'MY' => __( 'Malaysia', 'marwa-apply' ),
			'SG' => __( 'Singapore', 'marwa-apply' ),
			'KR' => __( 'South Korea', 'marwa-apply' ),
			'GR' => __( 'Greece', 'marwa-apply' ),
			'RO' => __( 'Romania', 'marwa-apply' ),
			'XX' => __( 'Somewhere else', 'marwa-apply' ),
		);
	}

	/**
	 * Rows for a candidate, newest first.
	 *
	 * @param int $candidate_id Candidate.
	 * @return array
	 */
	public static function for_candidate( $candidate_id ) {
		global $wpdb;

		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'experience' ) . ' WHERE candidate_id = %d ORDER BY from_date DESC, id DESC', $candidate_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Handle a posted entry. Called from Accounts::handle().
	 */
	public static function do_add() {
		global $wpdb;

		$row = Profile::row();

		if ( ! $row ) {
			wp_safe_redirect( Pages::url( 'sign-in' ) );
			exit;
		}

		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security

		$employer = sanitize_text_field( $post['ma_employer'] ?? '' );
		$country  = sanitize_text_field( $post['ma_country'] ?? '' );
		$role     = sanitize_text_field( $post['ma_role'] ?? '' );
		$from     = sanitize_text_field( $post['ma_from'] ?? '' );
		$to       = sanitize_text_field( $post['ma_to'] ?? '' );
		$desc     = sanitize_textarea_field( $post['ma_desc'] ?? '' );

		$errors = array();

		if ( mb_strlen( $role ) < 2 ) {
			$errors[] = __( 'Please say what the job was.', 'marwa-apply' );
		}

		if ( ! array_key_exists( $country, self::countries() ) ) {
			$errors[] = __( 'Please choose the country.', 'marwa-apply' );
		}

		if ( $from && $to && strtotime( $to ) < strtotime( $from ) ) {
			$errors[] = __( 'The finish date is before the start date.', 'marwa-apply' );
		}

		if ( count( self::for_candidate( (int) $row->id ) ) >= self::MAX_ROWS ) {
			$errors[] = __( 'That is as many jobs as we can record. Delete one first.', 'marwa-apply' );
		}

		if ( $errors ) {
			set_transient( 'ma_exp_err_' . get_current_user_id(), implode( ' ', $errors ), 120 );
			wp_safe_redirect( add_query_arg( 'exp', '0', Pages::url( 'profile' ) ) . '#experience' );
			exit;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'experience' ),
			array(
				'candidate_id'  => (int) $row->id,
				'employer_name' => $employer,
				'country'       => $country,
				'job_role'      => $role,
				'from_date'     => $from ? gmdate( 'Y-m-d', strtotime( $from ) ) : null,
				'to_date'       => $to ? gmdate( 'Y-m-d', strtotime( $to ) ) : null,
				'description'   => $desc,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		wp_safe_redirect( add_query_arg( 'exp', '1', Pages::url( 'profile' ) ) . '#experience' );
		exit;
	}

	/**
	 * Handle a posted delete. Called from Accounts::handle().
	 */
	public static function do_delete() {
		global $wpdb;

		$row = Profile::row();
		$id  = isset( $_POST['ma_exp'] ) ? (int) $_POST['ma_exp'] : 0; // phpcs:ignore WordPress.Security

		if ( $row && $id ) {
			$wpdb->delete( // phpcs:ignore WordPress.DB
				Install::table( 'experience' ),
				array( 'id' => $id, 'candidate_id' => (int) $row->id ),
				array( '%d', '%d' )
			);
		}

		wp_safe_redirect( add_query_arg( 'exp', '2', Pages::url( 'profile' ) ) . '#experience' );
		exit;
	}

	/**
	 * How one entry reads.
	 *
	 * @param object $e Experience row.
	 * @return string
	 */
	public static function dates( $e ) {
		$fmt = static function ( $d ) {
			return ( $d && '0000-00-00' !== $d ) ? mysql2date( 'M Y', $d ) : '';
		};

		$from = $fmt( $e->from_date );
		$to   = $fmt( $e->to_date );

		if ( $from && $to ) {
			return $from . ' – ' . $to;
		}

		if ( $from ) {
			return $from . ' – ' . __( 'now', 'marwa-apply' );
		}

		return $to ? $to : '';
	}

	/**
	 * The experience section of the profile page.
	 *
	 * @param object $row Candidate row.
	 * @return string
	 */
	public static function section( $row ) {
		$rows      = self::for_candidate( (int) $row->id );
		$countries = self::countries();
		$err       = get_transient( 'ma_exp_err_' . get_current_user_id() );

		if ( $err ) {
			delete_transient( 'ma_exp_err_' . get_current_user_id() );
		}

		$out = '<div class="ap-card" id="experience"><h3>' . esc_html__( 'Work you have done', 'marwa-apply' ) . '</h3>';
		$out .= '<p class="ap-note">' . esc_html__( 'Add each job separately, most recent first. This is what an employer reads — a man with real site experience should say so.', 'marwa-apply' ) . '</p>';

		$notice = isset( $_GET['exp'] ) ? sanitize_key( wp_unslash( $_GET['exp'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( '1' === $notice ) {
			$out .= '<div class="ap-msg ok">' . esc_html__( 'Job added.', 'marwa-apply' ) . '</div>';
		} elseif ( '2' === $notice ) {
			$out .= '<div class="ap-msg ok">' . esc_html__( 'Job removed.', 'marwa-apply' ) . '</div>';
		}

		if ( $err ) {
			$out .= '<div class="ap-msg bad">' . esc_html( $err ) . '</div>';
		}

		if ( $rows ) {
			$out .= '<table style="width:100%;border-collapse:collapse;margin-bottom:18px"><tbody>';

			foreach ( $rows as $e ) {
				$country = isset( $countries[ $e->country ] ) ? $countries[ $e->country ] : $e->country;

				$out .= '<tr style="border-top:1px solid var(--line,#E3E0D9)">';
				$out .= '<td style="padding:10px 6px"><strong>' . esc_html( $e->job_role ) . '</strong>';

				if ( $e->employer_name ) {
					$out .= ' · ' . esc_html( $e->employer_name );
				}

				$out .= '<br><span class="ap-note">' . esc_html( trim( $country . ' · ' . self::dates( $e ), ' ·' ) ) . '</span>';

				if ( $e->description ) {
					$out .= '<br><span class="ap-note">' . esc_html( $e->description ) . '</span>';
				}

				$out .= '</td><td style="padding:10px 6px;text-align:right;vertical-align:top">';
				$out .= '<form method="post" style="display:inline">';
				$out .= wp_nonce_field( 'ma_exp_delete', 'ma_nonce', true, false );
				$out .= '<input type="hidden" name="ma_action" value="exp_delete">';
				$out .= '<input type="hidden" name="ma_exp" value="' . esc_attr( $e->id ) . '">';
				$out .= '<button type="submit" style="background:none;border:0;padding:0;text-decoration:underline;cursor:pointer;color:#B4231A;font:inherit">' . esc_html__( 'Remove', 'marwa-apply' ) . '</button>';
				$out .= '</form></td></tr>';
			}

			$out .= '</tbody></table>';
		}

		// --------------------------------------------------------- add form
		$out .= '<form method="post">';
		$out .= wp_nonce_field( 'ma_exp_add', 'ma_nonce', true, false );
		$out .= '<input type="hidden" name="ma_action" value="exp_add">';

		$out .= '<div class="ap-row two">';
		$out .= '<div class="ap-field"><label for="ma_role">' . esc_html__( 'What was the job?', 'marwa-apply' ) . '<span class="hint">' . esc_html__( 'For example: rebar fitter, cook, care assistant.', 'marwa-apply' ) . '</span></label><input type="text" id="ma_role" name="ma_role"></div>';
		$out .= '<div class="ap-field"><label for="ma_employer">' . esc_html__( 'Company or site', 'marwa-apply' ) . '<span class="hint">' . esc_html__( 'Leave blank if you cannot remember.', 'marwa-apply' ) . '</span></label><input type="text" id="ma_employer" name="ma_employer"></div>';
		$out .= '</div>';

		$out .= '<div class="ap-row two">';
		$out .= '<div class="ap-field"><label for="ma_country">' . esc_html__( 'Country', 'marwa-apply' ) . '</label><select id="ma_country" name="ma_country">';

		foreach ( $countries as $code => $label ) {
			$out .= '<option value="' . esc_attr( $code ) . '">' . esc_html( $label ) . '</option>';
		}

		$out .= '</select></div>';
		$out .= '<div class="ap-field"><label>' . esc_html__( 'When', 'marwa-apply' ) . '<span class="hint">' . esc_html__( 'Leave the finish date empty if you are still there.', 'marwa-apply' ) . '</span></label>';
		$out .= '<div style="display:flex;gap:8px"><input type="month" name="ma_from" style="flex:1"><input type="month" name="ma_to" style="flex:1"></div></div>';
		$out .= '</div>';

		$out .= '<div class="ap-field"><label for="ma_desc">' . esc_html__( 'What did you do there?', 'marwa-apply' ) . '<span class="hint">' . esc_html__( 'One or two lines. Machines you used, work you were trusted with.', 'marwa-apply' ) . '</span></label><textarea id="ma_desc" name="ma_desc" rows="2"></textarea></div>';

		$out .= '<button type="submit" class="btn">' . esc_html__( 'Add this job', 'marwa-apply' ) . '</button>';
		$out .= '</form>';

		return $out . '</div>';
	}
}
