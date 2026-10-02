<?php
/**
 * The vacancy posting wizard.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Four steps, staff-side.
 *
 * The shape is borrowed, the content is not. Their fourth step is Billing;
 * here it is Review and publish, because nobody is paying to post a job.
 *
 * The reason for a wizard rather than one long screen is not tidiness. A
 * posting that declares its requirements in structured fields is what makes
 * the candidate's eligibility checklist possible, and staff will not fill in
 * twenty fields presented as a wall. Four steps of five fields they will.
 *
 * Every step saves as it goes, into a draft post. A vacancy half-entered when
 * somebody is called away is still there tomorrow.
 */
final class Posting {

	const SLUG = 'marwa-post-job';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 21 );
		add_action( 'admin_init', array( __CLASS__, 'handle' ) );
	}

	/**
	 * The steps.
	 *
	 * @return array
	 */
	public static function steps() {
		return array(
			1 => __( 'The vacancy', 'marwa-apply' ),
			2 => __( 'Pay and conditions', 'marwa-apply' ),
			3 => __( 'Who can apply', 'marwa-apply' ),
			4 => __( 'Review and publish', 'marwa-apply' ),
		);
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_submenu_page(
			'marwa-apply',
			__( 'Post a vacancy', 'marwa-apply' ),
			__( 'Post a vacancy', 'marwa-apply' ),
			'edit_posts',
			self::SLUG,
			array( __CLASS__, 'screen' )
		);
	}

	/**
	 * Save a step.
	 */
	public static function handle() {
		if ( empty( $_POST['ma_post_step'] ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		check_admin_referer( 'ma_posting' );

		$step   = (int) $_POST['ma_post_step'];
		$job_id = isset( $_POST['ma_job'] ) ? (int) $_POST['ma_job'] : 0;
		$post   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! $job_id ) {
			$job_id = (int) wp_insert_post(
				array(
					'post_type'   => 'job_listing',
					'post_status' => 'draft',
					'post_title'  => __( 'Untitled vacancy', 'marwa-apply' ),
				)
			);

			if ( ! $job_id ) {
				return;
			}

			update_post_meta( $job_id, '_ma_state', 'draft' );
		}

		switch ( $step ) {
			case 1:
				self::save_one( $job_id, $post );
				break;

			case 2:
				self::save_two( $job_id, $post );
				break;

			case 3:
				self::save_three( $job_id, $post );
				break;

			case 4:
				self::publish( $job_id, $post );
				break;
		}

		$next = min( 4, $step + 1 );

		if ( ! empty( $post['ma_save_only'] ) ) {
			$next = $step;
		}

		wp_safe_redirect(
			admin_url( 'admin.php?page=' . self::SLUG . '&job=' . $job_id . '&step=' . $next . '&saved=1' )
		);
		exit;
	}

	/**
	 * Step one: what the job is.
	 *
	 * @param int   $job_id Job.
	 * @param array $post   Raw post.
	 */
	private static function save_one( $job_id, $post ) {
		$title = sanitize_text_field( $post['ma_title'] ?? '' );

		wp_update_post(
			array(
				'ID'           => $job_id,
				'post_title'   => '' !== $title ? $title : __( 'Untitled vacancy', 'marwa-apply' ),
				'post_content' => wp_kses_post( $post['ma_body'] ?? '' ),
			)
		);

		update_post_meta( $job_id, '_company_name', sanitize_text_field( $post['ma_company'] ?? '' ) );
		update_post_meta( $job_id, '_job_location', sanitize_text_field( $post['ma_location'] ?? '' ) );
		update_post_meta( $job_id, '_ma_employer_id', (int) ( $post['ma_employer'] ?? 0 ) );
		update_post_meta( $job_id, '_ma_office', in_array( $post['ma_office'] ?? '', array( 'dhaka', 'athens' ), true ) ? $post['ma_office'] : 'dhaka' );
		update_post_meta( $job_id, '_positions', max( 0, (int) ( $post['ma_positions'] ?? 0 ) ) );

		$expires = sanitize_text_field( $post['ma_expires'] ?? '' );

		if ( $expires && strtotime( $expires ) ) {
			update_post_meta( $job_id, '_job_expires', gmdate( 'Y-m-d', strtotime( $expires ) ) );
		}

		$sector = (int) ( $post['ma_sector'] ?? 0 );

		if ( $sector ) {
			wp_set_object_terms( $job_id, $sector, Sectors::TAXONOMY );
		}

		$type = sanitize_title( $post['ma_type'] ?? '' );

		if ( $type ) {
			wp_set_object_terms( $job_id, $type, 'job_listing_type' );
		}
	}

	/**
	 * Step two: pay, contract, what the employer provides.
	 *
	 * @param int   $job_id Job.
	 * @param array $post   Raw post.
	 */
	private static function save_two( $job_id, $post ) {
		update_post_meta( $job_id, '_ma_contract_months', max( 0, (int) ( $post['ma_contract'] ?? 0 ) ) );
		update_post_meta( $job_id, '_ma_hours', sanitize_text_field( $post['ma_hours'] ?? '' ) );
		update_post_meta( $job_id, '_ma_salary_min', sanitize_text_field( $post['ma_smin'] ?? '' ) );
		update_post_meta( $job_id, '_ma_salary_max', sanitize_text_field( $post['ma_smax'] ?? '' ) );
		update_post_meta( $job_id, '_ma_currency', isset( Vacancy::currencies()[ $post['ma_currency'] ?? '' ] ) ? $post['ma_currency'] : '' );
		update_post_meta( $job_id, '_ma_salary_hide', empty( $post['ma_shide'] ) ? 0 : 1 );

		$chosen = array();

		foreach ( array_keys( Vacancy::benefits() ) as $key ) {
			if ( ! empty( $post[ 'ma_b_' . $key ] ) ) {
				$chosen[] = $key;
			}
		}

		update_post_meta( $job_id, '_ma_benefits', $chosen );
	}

	/**
	 * Step three: who can apply. This is what the checklist reads.
	 *
	 * @param int   $job_id Job.
	 * @param array $post   Raw post.
	 */
	private static function save_three( $job_id, $post ) {
		update_post_meta( $job_id, '_ma_min_age', max( 0, (int) ( $post['ma_minage'] ?? 0 ) ) );
		update_post_meta( $job_id, '_ma_max_age', max( 0, (int) ( $post['ma_maxage'] ?? 0 ) ) );
		update_post_meta( $job_id, '_ma_min_years', max( 0, (int) ( $post['ma_minyears'] ?? 0 ) ) );
		update_post_meta( $job_id, '_ma_no_experience', empty( $post['ma_noexp'] ) ? 0 : 1 );
		update_post_meta( $job_id, '_ma_req_passport', 'required' === ( $post['ma_passport'] ?? '' ) ? 'required' : '' );
		update_post_meta( $job_id, '_ma_req_jp', sanitize_text_field( $post['ma_jp'] ?? '' ) );
		update_post_meta( $job_id, '_ma_req_en', sanitize_text_field( $post['ma_en'] ?? '' ) );

		$exams = array();

		foreach ( (array) ( $post['ma_exams'] ?? array() ) as $exam ) {
			$exam = sanitize_text_field( $exam );

			if ( '' !== $exam ) {
				$exams[] = $exam;
			}
		}

		update_post_meta( $job_id, '_ma_req_exams', array_slice( $exams, 0, 8 ) );
	}

	/**
	 * Step four: set the state.
	 *
	 * @param int   $job_id Job.
	 * @param array $post   Raw post.
	 */
	private static function publish( $job_id, $post ) {
		$state  = sanitize_key( $post['ma_state'] ?? 'draft' );
		$states = Vacancy::states();

		if ( ! isset( $states[ $state ] ) ) {
			$state = 'draft';
		}

		update_post_meta( $job_id, '_ma_state', $state );

		wp_update_post(
			array(
				'ID'          => $job_id,
				'post_status' => in_array( $state, array( 'draft', 'archived' ), true ) ? 'draft' : 'publish',
			)
		);
	}

	/**
	 * The wizard screen.
	 */
	public static function screen() {
		$job_id = isset( $_GET['job'] ) ? (int) $_GET['job'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$step   = isset( $_GET['step'] ) ? max( 1, min( 4, (int) $_GET['step'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$job    = $job_id ? get_post( $job_id ) : null;

		if ( $job && 'job_listing' !== $job->post_type ) {
			$job    = null;
			$job_id = 0;
		}

		$spec = $job_id ? Vacancy::spec( $job_id ) : Vacancy::spec( 0 );

		echo '<div class="wrap ma-wizard"><h1>' . esc_html__( 'Post a vacancy', 'marwa-apply' ) . '</h1>';

		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'marwa-apply' ) . '</p></div>';
		}

		self::stepper( $step, $job_id );

		echo '<form method="post" class="ma-wiz-form">';
		wp_nonce_field( 'ma_posting' );
		echo '<input type="hidden" name="ma_post_step" value="' . esc_attr( (string) $step ) . '">';
		echo '<input type="hidden" name="ma_job" value="' . esc_attr( (string) $job_id ) . '">';

		switch ( $step ) {
			case 1:
				self::step_one( $job, $spec );
				break;

			case 2:
				self::step_two( $spec );
				break;

			case 3:
				self::step_three( $spec );
				break;

			default:
				self::step_four( $job, $spec );
		}

		echo '<p class="ma-wiz-actions">';
		echo '<button type="submit" class="button button-primary">' . esc_html( 4 === $step ? __( 'Save and finish', 'marwa-apply' ) : __( 'Save and continue', 'marwa-apply' ) ) . '</button> ';
		echo '<button type="submit" name="ma_save_only" value="1" class="button">' . esc_html__( 'Save and stay here', 'marwa-apply' ) . '</button>';
		echo '</p></form>';

		self::styles();

		echo '</div>';
	}

	/**
	 * The step rail.
	 *
	 * @param int $step   Current step.
	 * @param int $job_id Job.
	 */
	private static function stepper( $step, $job_id ) {
		echo '<ol class="ma-steps-rail">';

		foreach ( self::steps() as $n => $label ) {
			$class = $n === $step ? 'on' : ( $n < $step ? 'done' : '' );
			$url   = admin_url( 'admin.php?page=' . self::SLUG . '&step=' . $n . ( $job_id ? '&job=' . $job_id : '' ) );

			echo '<li class="' . esc_attr( $class ) . '">';

			// A step is only reachable once the vacancy exists; before that
			// there is nothing to attach the answers to.
			if ( $job_id ) {
				echo '<a href="' . esc_url( $url ) . '"><b>' . esc_html( (string) $n ) . '</b> ' . esc_html( $label ) . '</a>';
			} else {
				echo '<span><b>' . esc_html( (string) $n ) . '</b> ' . esc_html( $label ) . '</span>';
			}

			echo '</li>';
		}

		echo '</ol>';
	}

	/**
	 * A labelled row.
	 *
	 * @param string $label Label.
	 * @param string $field Control markup.
	 * @param string $hint  Hint.
	 */
	private static function row( $label, $field, $hint = '' ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped strings by callers.

		if ( $hint ) {
			echo '<p class="description">' . esc_html( $hint ) . '</p>';
		}

		echo '</td></tr>';
	}

	/**
	 * Step one.
	 *
	 * @param \WP_Post|null $job  Job.
	 * @param array         $spec Spec.
	 */
	private static function step_one( $job, $spec ) {
		global $wpdb;

		$employers = $wpdb->get_results( 'SELECT id, name, country, office FROM ' . Install::table( 'employers' ) . " WHERE status = 'active' ORDER BY name ASC" ); // phpcs:ignore WordPress.DB
		$current   = $job ? (int) get_post_meta( $job->ID, '_ma_employer_id', true ) : 0;

		echo '<table class="form-table"><tbody>';

		self::row(
			__( 'Job title', 'marwa-apply' ),
			'<input type="text" class="regular-text" name="ma_title" value="' . esc_attr( $job && __( 'Untitled vacancy', 'marwa-apply' ) !== $job->post_title ? $job->post_title : '' ) . '" required>',
			__( 'Write it the way a candidate would search for it. Include the Japanese field name in brackets where there is one.', 'marwa-apply' )
		);

		$opts = '<option value="">' . esc_html__( '— choose —', 'marwa-apply' ) . '</option>';

		foreach ( (array) $employers as $e ) {
			$opts .= '<option value="' . esc_attr( (string) $e->id ) . '"' . selected( $current, (int) $e->id, false ) . '>' . esc_html( $e->name . ' (' . $e->country . ')' ) . '</option>';
		}

		self::row( __( 'Employer', 'marwa-apply' ), '<select name="ma_employer">' . $opts . '</select>' );

		self::row(
			__( 'Company name shown', 'marwa-apply' ),
			'<input type="text" class="regular-text" name="ma_company" value="' . esc_attr( $job ? (string) get_post_meta( $job->ID, '_company_name', true ) : '' ) . '">',
			__( 'Leave empty to show the sector instead of the employer. Use that for any employer who has not agreed to be named publicly.', 'marwa-apply' )
		);

		self::row(
			__( 'Destination', 'marwa-apply' ),
			'<input type="text" class="regular-text" name="ma_location" value="' . esc_attr( $job ? (string) get_post_meta( $job->ID, '_job_location', true ) : '' ) . '">',
			__( 'For example: Japan (prefecture confirmed at offer stage).', 'marwa-apply' )
		);

		$sopts   = '<option value="">' . esc_html__( '— choose —', 'marwa-apply' ) . '</option>';
		$terms   = $job ? get_the_terms( $job->ID, Sectors::TAXONOMY ) : array();
		$current_sector = ( $terms && ! is_wp_error( $terms ) ) ? (int) $terms[0]->term_id : 0;

		foreach ( Sectors::options() as $id => $name ) {
			$sopts .= '<option value="' . esc_attr( (string) $id ) . '"' . selected( $current_sector, (int) $id, false ) . '>' . esc_html( $name ) . '</option>';
		}

		self::row( __( 'Sector', 'marwa-apply' ), '<select name="ma_sector">' . $sopts . '</select>' );

		$topts = '<option value="">' . esc_html__( '— choose —', 'marwa-apply' ) . '</option>';
		$ttype = $job ? get_the_terms( $job->ID, 'job_listing_type' ) : array();
		$tcur  = ( $ttype && ! is_wp_error( $ttype ) ) ? $ttype[0]->slug : '';

		foreach ( (array) get_terms( array( 'taxonomy' => 'job_listing_type', 'hide_empty' => false ) ) as $t ) {
			if ( ! is_wp_error( $t ) ) {
				$topts .= '<option value="' . esc_attr( $t->slug ) . '"' . selected( $tcur, $t->slug, false ) . '>' . esc_html( $t->name ) . '</option>';
			}
		}

		self::row( __( 'Contract type', 'marwa-apply' ), '<select name="ma_type">' . $topts . '</select>' );

		self::row(
			__( 'Positions', 'marwa-apply' ),
			'<input type="number" min="0" max="999" name="ma_positions" value="' . esc_attr( (string) $spec['positions'] ) . '">',
			__( 'How many workers the employer wants. Shown to candidates — a vacancy for forty is a different proposition from a vacancy for one.', 'marwa-apply' )
		);

		$office = $job ? (string) get_post_meta( $job->ID, '_ma_office', true ) : 'dhaka';

		self::row(
			__( 'Handled by', 'marwa-apply' ),
			'<select name="ma_office"><option value="dhaka"' . selected( $office, 'dhaka', false ) . '>' . esc_html__( 'Dhaka', 'marwa-apply' ) . '</option><option value="athens"' . selected( $office, 'athens', false ) . '>' . esc_html__( 'Athens', 'marwa-apply' ) . '</option></select>'
		);

		self::row(
			__( 'Applications close', 'marwa-apply' ),
			'<input type="date" name="ma_expires" value="' . esc_attr( $job ? (string) get_post_meta( $job->ID, '_job_expires', true ) : '' ) . '">'
		);

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'The work', 'marwa-apply' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'What the job actually involves, and anything a candidate should know before applying. Use a Requirements heading with a list under it — the jobs page reads that list for the card preview.', 'marwa-apply' ) . '</p>';

		wp_editor(
			$job ? $job->post_content : '',
			'ma_body',
			array( 'textarea_name' => 'ma_body', 'textarea_rows' => 14, 'media_buttons' => false )
		);
	}

	/**
	 * Step two.
	 *
	 * @param array $spec Spec.
	 */
	private static function step_two( $spec ) {
		echo '<table class="form-table"><tbody>';

		self::row(
			__( 'Contract length', 'marwa-apply' ),
			'<input type="number" min="0" max="120" name="ma_contract" value="' . esc_attr( (string) $spec['contract'] ) . '"> ' . esc_html__( 'months', 'marwa-apply' )
		);

		self::row(
			__( 'Working hours', 'marwa-apply' ),
			'<input type="text" class="regular-text" name="ma_hours" value="' . esc_attr( $spec['hours'] ) . '">',
			__( 'For example: 8 hours a day, 6 days a week, overtime available.', 'marwa-apply' )
		);

		$cur = '<select name="ma_currency"><option value="">' . esc_html__( '— currency —', 'marwa-apply' ) . '</option>';

		foreach ( Vacancy::currencies() as $code => $label ) {
			$cur .= '<option value="' . esc_attr( $code ) . '"' . selected( $spec['currency'], $code, false ) . '>' . esc_html( $label ) . '</option>';
		}

		$cur .= '</select>';

		self::row(
			__( 'Monthly pay', 'marwa-apply' ),
			$cur . ' <input type="text" name="ma_smin" placeholder="' . esc_attr__( 'from', 'marwa-apply' ) . '" value="' . esc_attr( $spec['salary_min'] ) . '" size="10"> '
				. '<input type="text" name="ma_smax" placeholder="' . esc_attr__( 'to', 'marwa-apply' ) . '" value="' . esc_attr( $spec['salary_max'] ) . '" size="10">'
		);

		self::row(
			__( 'Hide the pay from candidates', 'marwa-apply' ),
			'<label><input type="checkbox" name="ma_shide" value="1"' . checked( $spec['salary_hide'], true, false ) . '> ' . esc_html__( 'Record it here but do not show it on the posting', 'marwa-apply' ) . '</label>',
			__( 'Use this when the figure is not final. Recording it anyway means staff can answer the question when a candidate asks.', 'marwa-apply' )
		);

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'What the employer provides', 'marwa-apply' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Tick only what the contract actually states. These appear on the posting, and a worker will arrive expecting them.', 'marwa-apply' ) . '</p>';

		echo '<div class="ma-benefits">';

		foreach ( Vacancy::benefits() as $key => $label ) {
			echo '<label><input type="checkbox" name="ma_b_' . esc_attr( $key ) . '" value="1"' . checked( in_array( $key, $spec['benefits'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}

		echo '</div>';
	}

	/**
	 * Step three.
	 *
	 * @param array $spec Spec.
	 */
	private static function step_three( $spec ) {
		echo '<p class="description ma-wiz-lede">' . esc_html__( 'What you put here is compared against each candidate\'s profile and shown to them, free, before they apply — so they know what they still need rather than finding out after being rejected. Leave anything blank that this employer does not actually insist on.', 'marwa-apply' ) . '</p>';

		echo '<table class="form-table"><tbody>';

		self::row(
			__( 'Age', 'marwa-apply' ),
			'<input type="number" min="0" max="99" name="ma_minage" value="' . esc_attr( $spec['min_age'] ? (string) $spec['min_age'] : '' ) . '" size="4"> '
				. esc_html__( 'to', 'marwa-apply' ) . ' <input type="number" min="0" max="99" name="ma_maxage" value="' . esc_attr( $spec['max_age'] ? (string) $spec['max_age'] : '' ) . '" size="4">',
			__( 'Usually set by the destination\'s visa rules rather than by the employer.', 'marwa-apply' )
		);

		self::row(
			__( 'Experience', 'marwa-apply' ),
			'<input type="number" min="0" max="40" name="ma_minyears" value="' . esc_attr( $spec['min_years'] ? (string) $spec['min_years'] : '' ) . '" size="4"> ' . esc_html__( 'years minimum', 'marwa-apply' )
		);

		self::row(
			__( 'First-time workers', 'marwa-apply' ),
			'<label><input type="checkbox" name="ma_noexp" value="1"' . checked( $spec['no_experience'], true, false ) . '> ' . esc_html__( 'No previous experience needed', 'marwa-apply' ) . '</label>',
			__( 'Candidates can filter on this, and it is one of the most-used filters on any board.', 'marwa-apply' )
		);

		self::row(
			__( 'Passport', 'marwa-apply' ),
			'<label><input type="checkbox" name="ma_passport" value="required"' . checked( $spec['passport'], 'required', false ) . '> ' . esc_html__( 'Must already hold a valid passport', 'marwa-apply' ) . '</label>'
		);

		$jp = '<select name="ma_jp"><option value="">' . esc_html__( 'Not required', 'marwa-apply' ) . '</option>';

		foreach ( Profile::jp_levels() as $k => $label ) {
			if ( 'none' === $k ) {
				continue;
			}

			$jp .= '<option value="' . esc_attr( $k ) . '"' . selected( $spec['jp_level'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}

		$jp .= '</select>';

		self::row( __( 'Japanese', 'marwa-apply' ), $jp );

		$en = '<select name="ma_en"><option value="">' . esc_html__( 'Not required', 'marwa-apply' ) . '</option>';

		foreach ( Profile::en_levels() as $k => $label ) {
			if ( 'none' === $k ) {
				continue;
			}

			$en .= '<option value="' . esc_attr( $k ) . '"' . selected( $spec['en_level'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}

		$en .= '</select>';

		self::row( __( 'English', 'marwa-apply' ), $en );

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Certificates and exams required', 'marwa-apply' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Tick what this posting genuinely requires. A candidate who holds it sees it confirmed; one who does not sees exactly what to go and sit.', 'marwa-apply' ) . '</p>';

		echo '<div class="ma-benefits">';

		foreach ( Records::exams() as $exam ) {
			echo '<label><input type="checkbox" name="ma_exams[]" value="' . esc_attr( $exam ) . '"' . checked( in_array( $exam, $spec['exams'], true ), true, false ) . '> ' . esc_html( $exam ) . '</label>';
		}

		echo '</div>';
	}

	/**
	 * Step four.
	 *
	 * @param \WP_Post|null $job  Job.
	 * @param array         $spec Spec.
	 */
	private static function step_four( $job, $spec ) {
		if ( ! $job ) {
			echo '<p>' . esc_html__( 'Start at step one.', 'marwa-apply' ) . '</p>';

			return;
		}

		$missing = array();

		if ( ! get_post_meta( $job->ID, '_job_location', true ) ) {
			$missing[] = __( 'No destination', 'marwa-apply' );
		}

		if ( ! get_post_meta( $job->ID, '_job_expires', true ) ) {
			$missing[] = __( 'No closing date', 'marwa-apply' );
		}

		if ( ! has_term( '', Sectors::TAXONOMY, $job ) ) {
			$missing[] = __( 'No sector — it will not appear under any trade', 'marwa-apply' );
		}

		if ( ! trim( wp_strip_all_tags( $job->post_content ) ) ) {
			$missing[] = __( 'No description of the work', 'marwa-apply' );
		}

		if ( $missing ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Worth filling in before this goes live:', 'marwa-apply' ) . '</strong></p><ul style="list-style:disc;margin-left:20px">';

			foreach ( $missing as $m ) {
				echo '<li>' . esc_html( $m ) . '</li>';
			}

			echo '</ul></div>';
		}

		echo '<table class="form-table"><tbody>';

		$sel = '<select name="ma_state">';

		foreach ( Vacancy::states() as $k => $label ) {
			$sel .= '<option value="' . esc_attr( $k ) . '"' . selected( $spec['state'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}

		$sel .= '</select>';

		self::row(
			__( 'Status', 'marwa-apply' ),
			$sel,
			__( 'Closed keeps the posting readable but stops new applications — which is what you want while medicals and visas finish.', 'marwa-apply' )
		);

		echo '</tbody></table>';

		echo '<p><a class="button" href="' . esc_url( (string) get_permalink( $job->ID ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Preview the posting', 'marwa-apply' ) . '</a></p>';
	}

	/**
	 * Wizard styles.
	 */
	private static function styles() {
		?>
<style>
.ma-wizard .ma-steps-rail{display:flex;gap:8px;flex-wrap:wrap;list-style:none;margin:18px 0 22px;padding:0;counter-reset:w}
.ma-wizard .ma-steps-rail li{flex:1 1 180px}
.ma-wizard .ma-steps-rail a,.ma-wizard .ma-steps-rail span{display:block;padding:11px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff;text-decoration:none;color:#50575e;font-size:13.5px}
.ma-wizard .ma-steps-rail b{display:inline-flex;align-items:center;justify-content:center;width:21px;height:21px;border-radius:50%;background:#f0f0f1;color:#50575e;font-size:12px;margin-right:7px}
.ma-wizard .ma-steps-rail .on a{border-color:#0B2239;color:#0B2239;font-weight:600}
.ma-wizard .ma-steps-rail .on b{background:#0B2239;color:#fff}
.ma-wizard .ma-steps-rail .done a{border-color:#1F7A4D}
.ma-wizard .ma-steps-rail .done b{background:#1F7A4D;color:#fff}
.ma-wizard .ma-benefits{display:grid;grid-template-columns:repeat(auto-fill,minmax(285px,1fr));gap:7px 20px;margin:12px 0 20px;max-width:1000px}
.ma-wizard .ma-benefits label{display:block;font-size:13.5px;line-height:1.5}
.ma-wizard .ma-wiz-lede{max-width:72ch;font-size:13.5px}
.ma-wizard .ma-wiz-actions{margin-top:22px}
</style>
		<?php
	}
}
