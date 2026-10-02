<?php
/**
 * Applications — the pipeline.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * One row per candidate per job, and every move it makes.
 *
 * The thing this replaces is an email address on a job post. That is how the
 * dead admin@ address quietly swallowed submissions for months: nobody could
 * tell the difference between "no applicants" and "nothing arrived".
 */
final class Applications {

	/**
	 * The pipeline.
	 *
	 * Two labels per stage on purpose. Staff need the operational word;
	 * the candidate needs to understand where they stand without being
	 * shouted at. A man who sold land to pay for this should not read
	 * "REJECTED" — he should read that he was not selected this time, and
	 * know it is finished rather than be left hanging.
	 *
	 * @return array
	 */
	public static function stages() {
		return array(
			'new'         => array(
				'staff'     => __( 'New', 'marwa-apply' ),
				'candidate' => __( 'Received', 'marwa-apply' ),
				'open'      => true,
			),
			'screened'    => array(
				'staff'     => __( 'Screened', 'marwa-apply' ),
				'candidate' => __( 'Under review by Marwa', 'marwa-apply' ),
				'open'      => true,
			),
			'shortlisted' => array(
				'staff'     => __( 'Shortlisted', 'marwa-apply' ),
				'candidate' => __( 'Shortlisted — sent to the employer', 'marwa-apply' ),
				'open'      => true,
			),
			'interview'   => array(
				'staff'     => __( 'Employer interview', 'marwa-apply' ),
				'candidate' => __( 'Interview with the employer', 'marwa-apply' ),
				'open'      => true,
			),
			'selected'    => array(
				'staff'     => __( 'Selected', 'marwa-apply' ),
				'candidate' => __( 'Selected by the employer', 'marwa-apply' ),
				'open'      => true,
			),
			'medical'     => array(
				'staff'     => __( 'Medical', 'marwa-apply' ),
				'candidate' => __( 'Medical check', 'marwa-apply' ),
				'open'      => true,
			),
			'visa'        => array(
				'staff'     => __( 'Visa / COE', 'marwa-apply' ),
				'candidate' => __( 'Visa and documents in progress', 'marwa-apply' ),
				'open'      => true,
			),
			'deployed'    => array(
				'staff'     => __( 'Deployed', 'marwa-apply' ),
				'candidate' => __( 'Deployed', 'marwa-apply' ),
				'open'      => false,
			),
			'rejected'    => array(
				'staff'     => __( 'Rejected', 'marwa-apply' ),
				'candidate' => __( 'Not selected this time', 'marwa-apply' ),
				'open'      => false,
			),
			'withdrawn'   => array(
				'staff'     => __( 'Withdrawn', 'marwa-apply' ),
				'candidate' => __( 'Withdrawn by you', 'marwa-apply' ),
				'open'      => false,
			),
		);
	}

	/**
	 * Label for a stage.
	 *
	 * @param string $stage Stage key.
	 * @param string $who   'staff' or 'candidate'.
	 * @return string
	 */
	public static function label( $stage, $who = 'staff' ) {
		$stages = self::stages();

		return isset( $stages[ $stage ][ $who ] ) ? $stages[ $stage ][ $who ] : $stage;
	}

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'single_job_listing_end', array( __CLASS__, 'apply_box' ), 5 );
		add_action( 'wp_head', array( __CLASS__, 'hide_default_application_box' ), 99 );
	}

	/**
	 * WP Job Manager's own application box reveals an email address and
	 * invites the candidate to write to it. That is the behaviour being
	 * replaced, so it is hidden rather than left as a second, worse path.
	 */
	public static function hide_default_application_box() {
		if ( ! is_singular( 'job_listing' ) ) {
			return;
		}

		echo "<style>.single_job_listing .job_application.application{display:none!important}</style>\n";
	}

	// ----------------------------------------------------------- readiness

	/**
	 * What is stopping this candidate from applying?
	 *
	 * Returns an empty array when nothing is. Each entry is a sentence the
	 * candidate can act on — never a bare "incomplete profile".
	 *
	 * @param object|null $row Candidate row.
	 * @return array
	 */
	public static function blockers( $row ) {
		$out = array();

		if ( ! $row ) {
			$out[] = __( 'Create an account first.', 'marwa-apply' );

			return $out;
		}

		if ( ! Accounts::is_verified( (int) $row->user_id ) ) {
			$out[] = __( 'Confirm your email address.', 'marwa-apply' );
		}

		$needed = array(
			'full_name' => __( 'your full name', 'marwa-apply' ),
			'district'  => __( 'your home district', 'marwa-apply' ),
			'sector_id' => __( 'the sector you want to work in', 'marwa-apply' ),
			'trade'     => __( 'your trade or job title', 'marwa-apply' ),
		);

		$missing = array();

		foreach ( $needed as $field => $label ) {
			if ( empty( $row->$field ) ) {
				$missing[] = $label;
			}
		}

		if ( $missing ) {
			$out[] = sprintf(
				/* translators: %s: list of missing fields */
				__( 'Add %s to your profile.', 'marwa-apply' ),
				implode( ', ', $missing )
			);
		}

		return $out;
	}

	// --------------------------------------------------------------- write

	/**
	 * Create an application.
	 *
	 * @param int $candidate_id Candidate.
	 * @param int $job_id       Job post.
	 * @return int|\WP_Error Application id.
	 */
	public static function create( $candidate_id, $job_id ) {
		global $wpdb;

		$table = Install::table( 'applications' );

		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE candidate_id = %d AND job_id = %d", $candidate_id, $job_id ) ); // phpcs:ignore WordPress.DB

		if ( $existing ) {
			return new \WP_Error( 'ma_duplicate', __( 'You have already applied to this job.', 'marwa-apply' ) );
		}

		$office = get_post_meta( $job_id, '_ma_office', true );
		$office = $office ? $office : 'dhaka';

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'agency_id'    => Install::current_agency_id(),
				'candidate_id' => $candidate_id,
				'job_id'       => $job_id,
				'status'       => 'new',
				'office'       => $office,
				'source'       => 'self',
				'applied_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return new \WP_Error( 'ma_insert', __( 'We could not record your application. Please try again.', 'marwa-apply' ) );
		}

		$id = (int) $wpdb->insert_id;

		self::log( $id, '', 'new', __( 'Applied through the portal.', 'marwa-apply' ) );
		self::notify_office( $id );

		return $id;
	}

	/**
	 * Move an application to a new stage, recording who did it and why.
	 *
	 * @param int    $id    Application id.
	 * @param string $stage New stage.
	 * @param string $note  Reason.
	 * @return bool
	 */
	/**
	 * Record when and where an interview is.
	 *
	 * Three fields that remove a round of phone calls per candidate. The
	 * stage already said "interview"; what it never said was when, which is
	 * the only part the candidate needed.
	 *
	 * @param int    $id    Application.
	 * @param string $when  Datetime-local value.
	 * @param string $place Where, or a link.
	 * @param string $note  Anything to bring.
	 */
	public static function set_interview( $id, $when, $place, $note = '' ) {
		global $wpdb;

		$stamp = $when && strtotime( $when ) ? gmdate( 'Y-m-d H:i:s', strtotime( $when ) ) : null;

		$wpdb->update( // phpcs:ignore WordPress.DB
			Install::table( 'applications' ),
			array(
				'interview_at'    => $stamp,
				'interview_place' => $place ? sanitize_text_field( $place ) : null,
				'interview_note'  => $note ? sanitize_textarea_field( $note ) : null,
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( $stamp ) {
			self::log( (int) $id, '', 'interview_set', sprintf( 'Interview set for %s', $stamp ) );

			// Tell them, using whatever stage the file is actually at — the
			// notice is about the appointment, not about a stage change.
			$table   = Install::table( 'applications' );
			$current = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB

			if ( $current ) {
				self::notify_candidate( (int) $id, $current );
			}
		}
	}

	public static function set_stage( $id, $stage, $note = '' ) {
		global $wpdb;

		$stages = self::stages();

		if ( ! isset( $stages[ $stage ] ) ) {
			return false;
		}

		$table = Install::table( 'applications' );
		$from  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB

		if ( $from === $stage ) {
			return true;
		}

		$data = array( 'status' => $stage );

		if ( empty( $stages[ $stage ]['open'] ) ) {
			$data['decided_at'] = current_time( 'mysql' );
			$data['decided_by'] = get_current_user_id();
		}

		$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB

		self::log( $id, $from, $stage, $note );

		if ( 'withdrawn' !== $stage ) {
			self::notify_candidate( $id, $stage );
		}

		return true;
	}

	/**
	 * Tell the candidate their application moved.
	 *
	 * Written in the candidate's words, not the operational ones, and it
	 * always says what happens next — an update that leaves someone guessing
	 * is why people ring the office.
	 *
	 * @param int    $id    Application id.
	 * @param string $stage New stage.
	 */
	public static function notify_candidate( $id, $stage ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT a.*, c.full_name, c.email FROM ' . Install::table( 'applications' ) . ' a LEFT JOIN ' . Install::table( 'candidates' ) . ' c ON c.id = a.candidate_id WHERE a.id = %d', $id ) ); // phpcs:ignore WordPress.DB

		if ( ! $row || ! $row->email || ! is_email( $row->email ) ) {
			return;
		}

		$job    = get_the_title( $row->job_id );
		$stages = self::stages();
		$closed = empty( $stages[ $stage ]['open'] );

		$next = $closed
			? __( 'This application is now closed. You can apply to other jobs at any time.', 'marwa-apply' )
			: __( 'There is nothing you need to do right now. We will write again when it moves.', 'marwa-apply' );

		$lines = array(
			sprintf( /* translators: %s: name */ __( 'Dear %s,', 'marwa-apply' ), $row->full_name ),
			'',
			sprintf( /* translators: %s: job title */ __( 'Your application for "%s" has been updated.', 'marwa-apply' ), $job ),
			'',
			sprintf( /* translators: %s: stage */ __( 'Current stage: %s', 'marwa-apply' ), self::label( $stage, 'candidate' ) ),
			'',
			$next,
			'',
			sprintf( /* translators: %s: url */ __( 'You can see all your applications here: %s', 'marwa-apply' ), Pages::url( 'account' ) ),
			'',
			__( 'Marwa Overseas — Government Licence No. RL 2831', 'marwa-apply' ),
		);

		wp_mail(
			$row->email,
			sprintf( /* translators: %s: job title */ __( 'Update on your application — %s', 'marwa-apply' ), $job ),
			implode( "\n", $lines )
		);
	}

	/**
	 * Tell Marwa somebody applied.
	 *
	 * Without this, a new application is only visible to whoever happens to
	 * open the admin that day.
	 *
	 * @param int $id Application id.
	 */
	public static function notify_office( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT a.*, c.full_name, c.phone, c.trade FROM ' . Install::table( 'applications' ) . ' a LEFT JOIN ' . Install::table( 'candidates' ) . ' c ON c.id = a.candidate_id WHERE a.id = %d', $id ) ); // phpcs:ignore WordPress.DB

		if ( ! $row ) {
			return;
		}

		$lines = array(
			__( 'A new application has arrived.', 'marwa-apply' ),
			'',
			sprintf( /* translators: %s: job title */ __( 'Job: %s', 'marwa-apply' ), get_the_title( $row->job_id ) ),
			sprintf( /* translators: %s: name */ __( 'Candidate: %s', 'marwa-apply' ), $row->full_name ),
			sprintf( /* translators: %s: trade */ __( 'Trade: %s', 'marwa-apply' ), $row->trade ),
			sprintf( /* translators: %s: phone */ __( 'Mobile: %s', 'marwa-apply' ), Accounts::display_phone( $row->phone ) ),
			sprintf( /* translators: %s: office */ __( 'Office: %s', 'marwa-apply' ), ucfirst( $row->office ) ),
			'',
			admin_url( 'admin.php?page=marwa-applications' ),
		);

		wp_mail( get_option( 'admin_email' ), __( 'New application — Marwa Apply', 'marwa-apply' ), implode( "\n", $lines ) );
	}

	/**
	 * Append to the audit trail.
	 *
	 * @param int    $id   Application id.
	 * @param string $from From stage.
	 * @param string $to   To stage.
	 * @param string $note Note.
	 */
	public static function log( $id, $from, $to, $note = '' ) {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'status_log' ),
			array(
				'entity_type' => 'application',
				'entity_id'   => $id,
				'from_status' => $from,
				'to_status'   => $to,
				'user_id'     => get_current_user_id(),
				'note'        => $note,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	// -------------------------------------------------------------- posted

	/**
	 * Handle Apply. Called from Accounts::handle().
	 */
	public static function do_apply() {
		$job_id = isset( $_POST['ma_job'] ) ? (int) $_POST['ma_job'] : 0; // phpcs:ignore WordPress.Security
		$back   = $job_id ? get_permalink( $job_id ) : home_url( '/jobs/' );

		if ( ! is_user_logged_in() || ! $job_id ) {
			wp_safe_redirect( Pages::url( 'sign-in' ) );
			exit;
		}

		$row = Profile::row();

		if ( self::blockers( $row ) ) {
			wp_safe_redirect( add_query_arg( 'applied', 'blocked', $back ) );
			exit;
		}

		// An attached CV is handled before the application is created. If the
		// file is rejected we stop entirely rather than recording an
		// application the candidate believes carries their CV.
		if ( ! empty( $_FILES['ma_file']['name'] ) ) { // phpcs:ignore WordPress.Security
			$stored = Documents::store( $_FILES['ma_file'], 'cv', $row ); // phpcs:ignore WordPress.Security

			if ( is_wp_error( $stored ) ) {
				set_transient( 'ma_apply_err_' . get_current_user_id(), $stored->get_error_message(), 120 );
				wp_safe_redirect( add_query_arg( 'applied', 'cvfail', $back ) );
				exit;
			}
		}

		$result = self::create( (int) $row->id, $job_id );

		wp_safe_redirect( add_query_arg( 'applied', is_wp_error( $result ) ? 'dup' : '1', $back ) );
		exit;
	}

	/**
	 * Handle Withdraw. Called from Accounts::handle().
	 */
	public static function do_withdraw() {
		global $wpdb;

		$id  = isset( $_POST['ma_application'] ) ? (int) $_POST['ma_application'] : 0; // phpcs:ignore WordPress.Security
		$row = Profile::row();

		if ( ! $row || ! $id ) {
			wp_safe_redirect( Pages::url( 'account' ) );
			exit;
		}

		// Ownership check: a candidate may only withdraw their own file.
		$owner = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT candidate_id FROM ' . Install::table( 'applications' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB

		if ( $owner !== (int) $row->id ) {
			wp_safe_redirect( Pages::url( 'account' ) );
			exit;
		}

		self::set_stage( $id, 'withdrawn', __( 'Withdrawn by the candidate.', 'marwa-apply' ) );

		wp_safe_redirect( add_query_arg( 'withdrawn', '1', Pages::url( 'account' ) ) );
		exit;
	}

	// -------------------------------------------------------------- render

	/**
	 * The Apply box on a single job post.
	 */
	public static function apply_box() {
		global $post;

		if ( ! $post || 'job_listing' !== $post->post_type ) {
			return;
		}

		$job_id = (int) $post->ID;
		$notice = isset( $_GET['applied'] ) ? sanitize_key( wp_unslash( $_GET['applied'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		echo '<div class="mo"><div class="ap-card" style="margin-top:24px">';

		if ( '1' === $notice ) {
			echo '<div class="ap-msg ok">' . esc_html__( 'Your application has been received. You can follow it from your account.', 'marwa-apply' ) . '</div>';
		} elseif ( 'dup' === $notice ) {
			echo '<div class="ap-msg bad">' . esc_html__( 'You have already applied to this job.', 'marwa-apply' ) . '</div>';
		} elseif ( 'blocked' === $notice ) {
			echo '<div class="ap-msg bad">' . esc_html__( 'Your file is not ready yet — see below.', 'marwa-apply' ) . '</div>';
		} elseif ( 'cvfail' === $notice ) {
			$why = get_transient( 'ma_apply_err_' . get_current_user_id() );
			delete_transient( 'ma_apply_err_' . get_current_user_id() );

			echo '<div class="ap-msg bad">' . esc_html__( 'Your application was NOT sent, because the CV could not be accepted:', 'marwa-apply' ) . ' ' . esc_html( $why ? $why : '' ) . '</div>';
		}

		if ( ! is_user_logged_in() ) {
			echo '<h3>' . esc_html__( 'Apply for this job', 'marwa-apply' ) . '</h3>';
			echo '<p class="ap-note">' . esc_html__( 'You need an account so that your documents are kept in one place and you are not asked for them again.', 'marwa-apply' ) . '</p>';
			printf(
				'<p><a class="btn primary" href="%s">%s</a> &nbsp; <a href="%s">%s</a></p>',
				esc_url( Pages::url( 'register' ) ),
				esc_html__( 'Create an account', 'marwa-apply' ),
				esc_url( Pages::url( 'sign-in' ) ),
				esc_html__( 'I already have one', 'marwa-apply' )
			);
			echo '</div></div>';

			return;
		}

		$row      = Profile::row();
		$blockers = self::blockers( $row );

		if ( $blockers ) {
			echo '<h3>' . esc_html__( 'Before you can apply', 'marwa-apply' ) . '</h3>';
			echo '<ul class="ap-note">';

			foreach ( $blockers as $b ) {
				echo '<li>' . esc_html( $b ) . '</li>';
			}

			echo '</ul>';
			printf(
				'<p><a class="btn primary" href="%s">%s</a></p>',
				esc_url( Pages::url( 'profile' ) ),
				esc_html__( 'Finish my profile', 'marwa-apply' )
			);
			echo '</div></div>';

			return;
		}

		global $wpdb;

		$mine = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'applications' ) . ' WHERE candidate_id = %d AND job_id = %d', (int) $row->id, $job_id ) ); // phpcs:ignore WordPress.DB

		if ( $mine ) {
			echo '<h3>' . esc_html__( 'You have applied for this job', 'marwa-apply' ) . '</h3>';
			printf(
				'<p class="ap-note">%s <strong>%s</strong></p>',
				esc_html__( 'Current stage:', 'marwa-apply' ),
				esc_html( self::label( $mine->status, 'candidate' ) )
			);
			printf( '<p><a href="%s">%s</a></p>', esc_url( Pages::url( 'account' ) ), esc_html__( 'See all my applications', 'marwa-apply' ) );
			echo '</div></div>';

			return;
		}

		$cv = Documents::latest_of_type( (int) $row->id, 'cv' );

		echo '<h3>' . esc_html__( 'Apply for this job', 'marwa-apply' ) . '</h3>';
		echo '<p class="ap-note">' . esc_html__( 'Marwa will see your profile as it stands now. You can keep improving it afterwards.', 'marwa-apply' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data">';
		echo wp_nonce_field( 'ma_apply', 'ma_nonce', true, false );
		echo '<input type="hidden" name="ma_action" value="apply">';
		printf( '<input type="hidden" name="ma_job" value="%d">', esc_attr( $job_id ) );

		echo '<div class="ap-field"><label for="ma_file">';

		if ( $cv ) {
			echo esc_html__( 'Replace your CV for this application (optional)', 'marwa-apply' );
			echo '<span class="hint">' . esc_html( sprintf( /* translators: %s: file name */ __( 'On file already: %s. Leave this empty to use it.', 'marwa-apply' ), $cv->original_name ) ) . '</span>';
		} else {
			echo esc_html__( 'Attach your CV (optional)', 'marwa-apply' );
			echo '<span class="hint">' . esc_html( sprintf( /* translators: %s: size */ __( 'JPG, PNG or PDF, up to %s. Marwa also builds a CV from your profile, so this is not required.', 'marwa-apply' ), size_format( Documents::limit() ) ) ) . '</span>';
		}

		echo '</label><input type="file" id="ma_file" name="ma_file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"></div>';

		echo '<button type="submit" class="btn primary">' . esc_html__( 'Apply now', 'marwa-apply' ) . '</button>';
		echo '</form></div></div>';
	}

	/**
	 * The candidate's own list of applications.
	 *
	 * @param object $row Candidate row.
	 * @return string
	 */
	/**
	 * The interview line a candidate sees, if one is booked.
	 *
	 * @param object $app Application row.
	 * @return string
	 */
	public static function interview_line( $app ) {
		if ( empty( $app->interview_at ) || '0000-00-00 00:00:00' === $app->interview_at ) {
			return '';
		}

		$when = strtotime( $app->interview_at );

		$out  = '<div class="ma-iv"><strong>' . esc_html__( 'Your interview', 'marwa-apply' ) . '</strong>';
		$out .= '<span>' . esc_html( date_i18n( 'l j F Y, g:ia', $when ) ) . '</span>';

		if ( ! empty( $app->interview_place ) ) {
			$out .= '<span>' . esc_html( $app->interview_place ) . '</span>';
		}

		if ( ! empty( $app->interview_note ) ) {
			$out .= '<span class="ap-note">' . esc_html( $app->interview_note ) . '</span>';
		}

		return $out . '</div>';
	}

	public static function mine( $row ) {
		global $wpdb;

		if ( ! $row ) {
			return '';
		}

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'applications' ) . ' WHERE candidate_id = %d ORDER BY applied_at DESC', (int) $row->id ) ); // phpcs:ignore WordPress.DB

		$out = '<div class="ap-card"><h3>' . esc_html__( 'My applications', 'marwa-apply' ) . '</h3>';

		if ( ! $rows ) {
			$out .= '<p class="ap-note">' . esc_html__( 'You have not applied to anything yet.', 'marwa-apply' ) . '</p>';
			$out .= '<p><a class="btn" href="' . esc_url( home_url( '/jobs/' ) ) . '">' . esc_html__( 'Browse jobs', 'marwa-apply' ) . '</a></p>';

			return $out . '</div>';
		}

		$stages = self::stages();

		$out .= '<table style="width:100%;border-collapse:collapse"><thead><tr>';
		$out .= '<th style="text-align:left;padding:8px 6px">' . esc_html__( 'Job', 'marwa-apply' ) . '</th>';
		$out .= '<th style="text-align:left;padding:8px 6px">' . esc_html__( 'Applied', 'marwa-apply' ) . '</th>';
		$out .= '<th style="text-align:left;padding:8px 6px">' . esc_html__( 'Stage', 'marwa-apply' ) . '</th>';
		$out .= '<th></th></tr></thead><tbody>';

		foreach ( $rows as $app ) {
			$title = get_the_title( $app->job_id );
			$open  = ! empty( $stages[ $app->status ]['open'] );

			$iv = self::interview_line( $app );

			$out .= '<tr style="border-top:1px solid var(--line,#E3E0D9)">';
			$out .= '<td style="padding:10px 6px"><a href="' . esc_url( (string) get_permalink( $app->job_id ) ) . '">' . esc_html( $title ? $title : __( 'Job post removed', 'marwa-apply' ) ) . '</a>' . $iv . '</td>';
			$out .= '<td style="padding:10px 6px">' . esc_html( mysql2date( get_option( 'date_format' ), $app->applied_at ) ) . '</td>';
			$out .= '<td style="padding:10px 6px"><strong>' . esc_html( self::label( $app->status, 'candidate' ) ) . '</strong></td>';
			$out .= '<td style="padding:10px 6px;text-align:right">';

			if ( $open ) {
				$out .= '<form method="post" style="display:inline">';
				$out .= wp_nonce_field( 'ma_withdraw', 'ma_nonce', true, false );
				$out .= '<input type="hidden" name="ma_action" value="withdraw">';
				$out .= '<input type="hidden" name="ma_application" value="' . esc_attr( $app->id ) . '">';
				$out .= '<button type="submit" style="background:none;border:0;padding:0;text-decoration:underline;cursor:pointer;color:inherit;font:inherit">' . esc_html__( 'Withdraw', 'marwa-apply' ) . '</button>';
				$out .= '</form>';
			}

			$out .= '</td></tr>';
		}

		return $out . '</tbody></table></div>';
	}
}
