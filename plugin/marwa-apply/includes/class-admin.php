<?php
/**
 * The staff screens.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Candidates and applications, for Marwa's own staff.
 *
 * Hand-rolled tables rather than WP_List_Table: the filters here are the
 * point of the screen — sector, district, passport, Japanese level together —
 * and bending the core class to do that costs more code than writing it.
 */
final class Admin {

	const PER_PAGE = 50;

	/**
	 * Menus.
	 */
	public static function menu() {
		add_submenu_page(
			'marwa-apply',
			__( 'Candidates', 'marwa-apply' ),
			__( 'Candidates', 'marwa-apply' ),
			'ma_view_candidates',
			'marwa-candidates',
			array( __CLASS__, 'candidates' )
		);

		add_submenu_page(
			'marwa-apply',
			__( 'Applications', 'marwa-apply' ),
			__( 'Applications', 'marwa-apply' ),
			'ma_view_candidates',
			'marwa-applications',
			array( __CLASS__, 'applications' )
		);
	}

	/**
	 * Which office is this user allowed to see?
	 *
	 * Managers see both. Staff see the office on their account, defaulting to
	 * Dhaka. The two offices do not share a book of work.
	 *
	 * @return string '' for all.
	 */
	public static function scope() {
		if ( current_user_can( 'ma_manage_settings' ) ) {
			return '';
		}

		$office = get_user_meta( get_current_user_id(), '_ma_office', true );

		return $office ? $office : 'dhaka';
	}

	/**
	 * Handle a posted stage change.
	 */
	public static function handle() {
		if ( empty( $_POST['ma_stage_change'] ) || ! current_user_can( 'ma_change_status' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! isset( $_POST['ma_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ma_nonce'] ) ), 'ma_stage' ) ) {
			return;
		}

		$id    = (int) $_POST['ma_application']; // phpcs:ignore WordPress.Security
		$stage = sanitize_key( wp_unslash( $_POST['ma_stage_change'] ) ); // phpcs:ignore WordPress.Security
		$note  = sanitize_text_field( wp_unslash( $_POST['ma_note'] ?? '' ) ); // phpcs:ignore WordPress.Security

		Applications::set_stage( $id, $stage, $note );

		// Set in the same submission as the stage, because the moment staff
		// move a file to "interview" is the moment they know when it is.
		if ( isset( $_POST['ma_iv_when'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			Applications::set_interview(
				$id,
				sanitize_text_field( wp_unslash( $_POST['ma_iv_when'] ) ), // phpcs:ignore WordPress.Security
				isset( $_POST['ma_iv_place'] ) ? sanitize_text_field( wp_unslash( $_POST['ma_iv_place'] ) ) : '', // phpcs:ignore WordPress.Security
				isset( $_POST['ma_iv_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ma_iv_note'] ) ) : '' // phpcs:ignore WordPress.Security
			);
		}

		wp_safe_redirect( add_query_arg( 'moved', '1', wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=marwa-applications' ) ) );
		exit;
	}

	/**
	 * Which office a staff account belongs to.
	 *
	 * Without somewhere to set this, every staff account silently defaults to
	 * Dhaka and the separation between the two offices is theoretical. Only a
	 * manager can change it — otherwise a Dhaka officer could simply reassign
	 * themselves to Athens.
	 *
	 * @param \WP_User $user User being edited.
	 */
	public static function office_field( $user ) {
		$roles = (array) $user->roles;

		if ( ! array_intersect( $roles, array( Roles::STAFF, Roles::MANAGER, 'administrator' ) ) ) {
			return;
		}

		$current  = get_user_meta( $user->ID, '_ma_office', true );
		$current  = $current ? $current : 'dhaka';
		$editable = current_user_can( 'ma_manage_settings' );

		echo '<h2>' . esc_html__( 'Marwa Apply', 'marwa-apply' ) . '</h2>';
		echo '<table class="form-table"><tr>';
		echo '<th><label for="ma_office">' . esc_html__( 'Office', 'marwa-apply' ) . '</label></th><td>';

		if ( $editable ) {
			echo '<select name="ma_office" id="ma_office">';

			foreach ( array( 'dhaka' => __( 'Dhaka', 'marwa-apply' ), 'athens' => __( 'Athens', 'marwa-apply' ) ) as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
			}

			echo '</select>';
			echo '<p class="description">' . esc_html__( 'This account sees applications for this office only. Managers see both.', 'marwa-apply' ) . '</p>';
		} else {
			echo '<strong>' . esc_html( ucfirst( $current ) ) . '</strong>';
			echo '<p class="description">' . esc_html__( 'Only a manager can change which office an account belongs to.', 'marwa-apply' ) . '</p>';
		}

		echo '</td></tr></table>';
	}

	/**
	 * Save it.
	 *
	 * @param int $user_id User being saved.
	 */
	public static function save_office_field( $user_id ) {
		if ( ! current_user_can( 'ma_manage_settings' ) || empty( $_POST['ma_office'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$office = sanitize_key( wp_unslash( $_POST['ma_office'] ) ); // phpcs:ignore WordPress.Security

		if ( in_array( $office, array( 'dhaka', 'athens' ), true ) ) {
			update_user_meta( $user_id, '_ma_office', $office );
		}
	}

	/**
	 * A <select> of options.
	 *
	 * @param string $name    Field name.
	 * @param array  $options value => label.
	 * @param string $current Current.
	 * @param string $any     Label for the empty option.
	 * @return string
	 */
	private static function filter_select( $name, $options, $current, $any ) {
		$out = '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html( $any ) . '</option>';

		foreach ( $options as $value => $label ) {
			$out .= '<option value="' . esc_attr( $value ) . '"' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		return $out . '</select>';
	}

	// ----------------------------------------------------------- candidates

	/**
	 * The candidate list.
	 */
	public static function candidates() {
		global $wpdb;

		if ( ! empty( $_GET['candidate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			self::candidate_detail( (int) $_GET['candidate'] ); // phpcs:ignore WordPress.Security.NonceVerification

			return;
		}

		$table = Install::table( 'candidates' );

		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$sector   = isset( $_GET['sector'] ) ? (int) $_GET['sector'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$district = isset( $_GET['district'] ) ? sanitize_text_field( wp_unslash( $_GET['district'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$passport = isset( $_GET['passport'] ) ? sanitize_key( wp_unslash( $_GET['passport'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$jp       = isset( $_GET['jp'] ) ? sanitize_text_field( wp_unslash( $_GET['jp'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$years    = isset( $_GET['years'] ) ? (int) $_GET['years'] : 0; // phpcs:ignore WordPress.Security.NonceVerification

		$where  = array( "status <> 'demo'" );
		$params = array();

		if ( $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(full_name LIKE %s OR phone LIKE %s OR email LIKE %s OR trade LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( $sector ) {
			$where[]  = 'sector_id = %d';
			$params[] = $sector;
		}

		if ( $district ) {
			$where[]  = 'district = %s';
			$params[] = $district;
		}

		if ( $passport ) {
			$where[]  = 'passport_status = %s';
			$params[] = $passport;
		}

		if ( $jp ) {
			$where[]  = 'jp_language_level = %s';
			$params[] = $jp;
		}

		if ( $years ) {
			$where[]  = 'years_experience >= %d';
			$params[] = $years;
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC LIMIT ' . self::PER_PAGE;
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB

		$districts = array();

		foreach ( Profile::districts() as $division => $items ) {
			foreach ( $items as $item ) {
				$districts[ $item ] = $item;
			}
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Candidates', 'marwa-apply' ) . '</h1>';

		echo '<form method="get" style="margin:16px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
		echo '<input type="hidden" name="page" value="marwa-candidates">';
		printf( '<input type="search" name="s" value="%s" placeholder="%s" style="min-width:220px">', esc_attr( $search ), esc_attr__( 'Name, phone, email or trade', 'marwa-apply' ) );
		echo self::filter_select( 'sector', Sectors::options(), $sector, __( 'Any sector', 'marwa-apply' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::filter_select( 'district', $districts, $district, __( 'Any district', 'marwa-apply' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::filter_select( 'passport', Profile::passport_states(), $passport, __( 'Any passport state', 'marwa-apply' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::filter_select( 'jp', array_filter( Profile::jp_levels() ), $jp, __( 'Any Japanese level', 'marwa-apply' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::filter_select( 'years', array( 1 => '1+ years', 3 => '3+ years', 5 => '5+ years', 10 => '10+ years' ), $years, __( 'Any experience', 'marwa-apply' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<button class="button">' . esc_html__( 'Filter', 'marwa-apply' ) . '</button>';
		printf( '<a class="button-link" href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=marwa-candidates' ) ), esc_html__( 'Reset', 'marwa-apply' ) );
		echo '</form>';

		printf( '<p class="description">%s</p>', esc_html( sprintf( /* translators: %d: count */ _n( '%d candidate matches.', '%d candidates match.', $total, 'marwa-apply' ), $total ) ) );

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Contact', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Sector / trade', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Years', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'District', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Passport', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Japanese', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Profile', 'marwa-apply' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( ! $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'Nobody matches those filters yet.', 'marwa-apply' ) . '</td></tr>';
		}

		$sectors   = Sectors::options();
		$passports = Profile::passport_states();
		$jps       = Profile::jp_levels();

		foreach ( (array) $rows as $c ) {
			$sector_label = isset( $sectors[ $c->sector_id ] ) ? $sectors[ $c->sector_id ] : '—';

			$link = add_query_arg( array( 'page' => 'marwa-candidates', 'candidate' => (int) $c->id ), admin_url( 'admin.php' ) );

			echo '<tr>';
			echo '<td><strong><a href="' . esc_url( $link ) . '">' . esc_html( $c->full_name ) . '</a></strong>';

			if ( $c->full_name_bn ) {
				echo '<br><span class="description">' . esc_html( $c->full_name_bn ) . '</span>';
			}

			echo '</td>';
			echo '<td>' . esc_html( Accounts::display_phone( $c->phone ) ) . '<br><span class="description">' . esc_html( $c->email ) . '</span></td>';
			echo '<td>' . esc_html( $sector_label ) . '<br><span class="description">' . esc_html( $c->trade ) . '</span></td>';
			echo '<td>' . esc_html( null === $c->years_experience ? '—' : $c->years_experience ) . '</td>';
			echo '<td>' . esc_html( $c->district ? $c->district : '—' ) . '</td>';
			echo '<td>' . esc_html( $passports[ $c->passport_status ] ?? $c->passport_status ) . '</td>';
			echo '<td>' . esc_html( $jps[ $c->jp_language_level ] ?? '—' ) . '</td>';
			echo '<td>' . esc_html( $c->profile_completeness . '%' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<style>.ma-stage-form .ma-stage-row{display:flex;gap:6px;align-items:center}.ma-stage-form label{font-size:12px;color:#50575e}.ma-stage-form input[type=datetime-local],.ma-stage-form input[type=text]{font-size:13px}</style>';
		echo '<p class="description">' . esc_html__( 'Passport numbers are deliberately not shown on this screen. Only a manager can see them, on the candidate record.', 'marwa-apply' ) . '</p>';
		echo '</div>';
	}

	/**
	 * One candidate, in full.
	 *
	 * This is the screen a staff member works from: the profile, the
	 * documents they can actually look at, and every application with its
	 * history. The passport number appears only for a manager.
	 *
	 * @param int $id Candidate id.
	 */
	public static function candidate_detail( $id ) {
		global $wpdb;

		$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'candidates' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB

		echo '<div class="wrap">';
		printf( '<p><a href="%s">&larr; %s</a></p>', esc_url( admin_url( 'admin.php?page=marwa-candidates' ) ), esc_html__( 'All candidates', 'marwa-apply' ) );

		if ( ! $c ) {
			echo '<h1>' . esc_html__( 'Candidate not found', 'marwa-apply' ) . '</h1></div>';

			return;
		}

		echo '<h1>' . esc_html( $c->full_name ) . '</h1>';

		if ( $c->full_name_bn ) {
			echo '<p class="description">' . esc_html( $c->full_name_bn ) . '</p>';
		}

		$sectors   = Sectors::options();
		$passports = Profile::passport_states();
		$jps       = Profile::jp_levels();
		$ens       = Profile::en_levels();

		$rows = array(
			__( 'Mobile', 'marwa-apply' )            => Accounts::display_phone( $c->phone ),
			__( 'Email', 'marwa-apply' )             => $c->email,
			__( 'Email confirmed', 'marwa-apply' )   => Accounts::is_verified( (int) $c->user_id ) ? __( 'Yes', 'marwa-apply' ) : __( 'No', 'marwa-apply' ),
			__( 'Date of birth', 'marwa-apply' )     => ( $c->dob && '0000-00-00' !== $c->dob ) ? $c->dob : '—',
			__( 'Home district', 'marwa-apply' )     => $c->district ? $c->district : '—',
			__( 'Sector', 'marwa-apply' )            => isset( $sectors[ $c->sector_id ] ) ? $sectors[ $c->sector_id ] : '—',
			__( 'Trade', 'marwa-apply' )             => $c->trade ? $c->trade : '—',
			__( 'Years of experience', 'marwa-apply' ) => ( null === $c->years_experience ) ? '—' : $c->years_experience,
			__( 'Japanese', 'marwa-apply' )          => isset( $jps[ $c->jp_language_level ] ) ? $jps[ $c->jp_language_level ] : '—',
			__( 'English', 'marwa-apply' )           => isset( $ens[ $c->en_language_level ] ) ? $ens[ $c->en_language_level ] : '—',
			__( 'Passport', 'marwa-apply' )          => isset( $passports[ $c->passport_status ] ) ? $passports[ $c->passport_status ] : $c->passport_status,
			__( 'Profile complete', 'marwa-apply' )  => $c->profile_completeness . '%',
		);

		if ( current_user_can( 'ma_view_sensitive' ) ) {
			$rows[ __( 'Passport number', 'marwa-apply' ) ] = $c->passport_no ? $c->passport_no : '—';
		}

		echo '<table class="widefat striped" style="max-width:720px"><tbody>';

		foreach ( $rows as $label => $value ) {
			printf( '<tr><th style="width:200px">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( (string) $value ) );
		}

		echo '</tbody></table>';

		printf(
			'<p><a href="%s" target="_blank" rel="noopener" class="button button-primary">%s</a></p>',
			esc_url( CV::url( (int) $c->id ) ),
			esc_html__( 'Open the generated CV', 'marwa-apply' )
		);

		if ( ! current_user_can( 'ma_view_sensitive' ) ) {
			echo '<p class="description">' . esc_html__( 'The passport number is visible to managers only.', 'marwa-apply' ) . '</p>';
		}

		// ------------------------------------------------------- documents
		echo '<h2>' . esc_html__( 'Documents', 'marwa-apply' ) . '</h2>';

		if ( ! current_user_can( 'ma_view_documents' ) ) {
			echo '<p class="description">' . esc_html__( 'You do not have permission to view candidate documents.', 'marwa-apply' ) . '</p>';
		} else {
			$docs  = Documents::for_candidate( (int) $c->id );
			$types = Documents::types();

			if ( ! $docs ) {
				echo '<p class="description">' . esc_html__( 'Nothing uploaded yet.', 'marwa-apply' ) . '</p>';
			} else {
				echo '<div style="display:flex;flex-wrap:wrap;gap:16px">';

				foreach ( $docs as $doc ) {
					$label = isset( $types[ $doc->doc_type ]['label'] ) ? $types[ $doc->doc_type ]['label'] : $doc->doc_type;

					echo '<div style="border:1px solid #dcdcde;border-radius:6px;padding:12px;background:#fff;max-width:320px">';
					echo '<p style="margin:0 0 8px"><strong>' . esc_html( $label ) . '</strong></p>';
					echo wp_kses_post( Documents::preview( $doc, 170 ) );
					echo '<p style="margin:8px 0 0;font-size:12px;color:#646970">' . esc_html( $doc->original_name . ' · ' . size_format( (int) $doc->size_bytes ) ) . '<br>';
					echo '<a href="' . esc_url( Documents::url( $doc ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open full size', 'marwa-apply' ) . '</a></p>';
					echo '</div>';
				}

				echo '</div>';
			}
		}

		// -------------------------------------------------- work history
		$exp       = Experience::for_candidate( (int) $c->id );
		$countries = Experience::countries();

		echo '<h2>' . esc_html__( 'Work history', 'marwa-apply' ) . '</h2>';

		if ( ! $exp ) {
			echo '<p class="description">' . esc_html__( 'None recorded.', 'marwa-apply' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:860px"><thead><tr>';
			echo '<th>' . esc_html__( 'Role', 'marwa-apply' ) . '</th>';
			echo '<th>' . esc_html__( 'Employer', 'marwa-apply' ) . '</th>';
			echo '<th>' . esc_html__( 'Country', 'marwa-apply' ) . '</th>';
			echo '<th>' . esc_html__( 'When', 'marwa-apply' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $exp as $e ) {
				printf(
					'<tr><td><strong>%s</strong>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( $e->job_role ),
					$e->description ? '<br><span class="description">' . esc_html( $e->description ) . '</span>' : '',
					esc_html( $e->employer_name ? $e->employer_name : '—' ),
					esc_html( isset( $countries[ $e->country ] ) ? $countries[ $e->country ] : $e->country ),
					esc_html( Experience::dates( $e ) )
				);
			}

			echo '</tbody></table>';
		}

		// ----------------------------------------------------- applications
		$apps = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'applications' ) . ' WHERE candidate_id = %d ORDER BY applied_at DESC', (int) $c->id ) ); // phpcs:ignore WordPress.DB

		echo '<h2>' . esc_html__( 'Applications', 'marwa-apply' ) . '</h2>';

		if ( ! $apps ) {
			echo '<p class="description">' . esc_html__( 'None yet.', 'marwa-apply' ) . '</p>';
		}

		foreach ( (array) $apps as $a ) {
			$log = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'status_log' ) . ' WHERE entity_type = %s AND entity_id = %d ORDER BY created_at ASC', 'application', (int) $a->id ) ); // phpcs:ignore WordPress.DB

			echo '<table class="widefat striped" style="max-width:860px;margin-bottom:18px"><tbody>';
			printf(
				'<tr><th style="width:200px">%s</th><td><strong>%s</strong> — %s</td></tr>',
				esc_html__( 'Job', 'marwa-apply' ),
				esc_html( (string) get_the_title( $a->job_id ) ),
				esc_html( Applications::label( $a->status, 'staff' ) )
			);

			foreach ( (array) $log as $entry ) {
				$who = $entry->user_id ? get_userdata( $entry->user_id ) : null;

				printf(
					'<tr><td>%s</td><td>%s &rarr; <strong>%s</strong>%s%s</td></tr>',
					esc_html( mysql2date( 'j M Y, H:i', $entry->created_at ) ),
					esc_html( $entry->from_status ? Applications::label( $entry->from_status, 'staff' ) : '—' ),
					esc_html( Applications::label( $entry->to_status, 'staff' ) ),
					$who ? ' · ' . esc_html( $who->display_name ) : '',
					$entry->note ? ' · <em>' . esc_html( $entry->note ) . '</em>' : ''
				);
			}

			echo '</tbody></table>';
		}

		echo '</div>';
	}

	// --------------------------------------------------------- applications

	/**
	 * The pipeline.
	 */
	public static function applications() {
		global $wpdb;

		$apps = Install::table( 'applications' );
		$cand = Install::table( 'candidates' );

		$stage  = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( $_GET['stage'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$job    = isset( $_GET['job'] ) ? (int) $_GET['job'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$office = self::scope();

		$where  = array( '1=1' );
		$params = array();

		if ( $stage ) {
			$where[]  = 'a.status = %s';
			$params[] = $stage;
		}

		if ( $job ) {
			$where[]  = 'a.job_id = %d';
			$params[] = $job;
		}

		if ( $office ) {
			$where[]  = 'a.office = %s';
			$params[] = $office;
		}

		$sql = "SELECT a.*, c.full_name, c.phone, c.trade, c.district, c.profile_completeness
			FROM {$apps} a LEFT JOIN {$cand} c ON c.id = a.candidate_id
			WHERE " . implode( ' AND ', $where ) . ' ORDER BY a.applied_at DESC LIMIT ' . self::PER_PAGE;

		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB

		$stages      = Applications::stages();
		$stage_names = array();

		foreach ( $stages as $key => $spec ) {
			$stage_names[ $key ] = $spec['staff'];
		}

		$jobs    = get_posts( array( 'post_type' => 'job_listing', 'posts_per_page' => 100, 'post_status' => 'any' ) );
		$job_map = array();

		foreach ( $jobs as $j ) {
			$job_map[ $j->ID ] = $j->post_title;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Applications', 'marwa-apply' ) . '</h1>';

		if ( $office ) {
			printf( '<p class="description">%s</p>', esc_html( sprintf( /* translators: %s: office */ __( 'Showing the %s office only. A manager sees both.', 'marwa-apply' ), ucfirst( $office ) ) ) );
		}

		if ( isset( $_GET['moved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Stage updated and recorded in the log.', 'marwa-apply' ) . '</p></div>';
		}

		echo '<form method="get" style="margin:16px 0;display:flex;gap:8px;align-items:center">';
		echo '<input type="hidden" name="page" value="marwa-applications">';
		echo self::filter_select( 'stage', $stage_names, $stage, __( 'Any stage', 'marwa-apply' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::filter_select( 'job', $job_map, $job, __( 'Any job', 'marwa-apply' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<button class="button">' . esc_html__( 'Filter', 'marwa-apply' ) . '</button>';
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Candidate', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Job', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Applied', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Office', 'marwa-apply' ) . '</th>';
		echo '<th style="width:38%">' . esc_html__( 'Stage', 'marwa-apply' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( ! $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No applications yet.', 'marwa-apply' ) . '</td></tr>';
		}

		foreach ( (array) $rows as $a ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( $a->full_name ) . '</strong><br><span class="description">' . esc_html( Accounts::display_phone( $a->phone ) . ' · ' . $a->trade . ' · ' . $a->district . ' · ' . $a->profile_completeness . '%' ) . '</span></td>';
			echo '<td>' . esc_html( $job_map[ $a->job_id ] ?? __( 'Job post removed', 'marwa-apply' ) ) . '</td>';
			echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $a->applied_at ) ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $a->office ) ) . '</td>';
			echo '<td>';

			if ( current_user_can( 'ma_change_status' ) ) {
				echo '<form method="post" class="ma-stage-form">';
				echo wp_nonce_field( 'ma_stage', 'ma_nonce', true, false );
				echo '<input type="hidden" name="ma_application" value="' . esc_attr( $a->id ) . '">';
				// Only where the file has reached, or is about to reach, an
				// interview. Showing these on a brand-new application is
				// noise on every row of a long list.
				$show_iv = in_array( $a->status, array( 'shortlisted', 'interview', 'selected' ), true ) || ! empty( $a->interview_at );

				if ( $show_iv ) {
				echo '<p style="margin:0 0 6px"><label>' . esc_html__( 'Interview date and time', 'marwa-apply' ) . ' ';
				echo '<input type="datetime-local" name="ma_iv_when" value="' . esc_attr( ! empty( $a->interview_at ) && '0000-00-00 00:00:00' !== $a->interview_at ? gmdate( 'Y-m-d\TH:i', strtotime( $a->interview_at ) ) : '' ) . '"></label> ';
				echo '<label>' . esc_html__( 'Where', 'marwa-apply' ) . ' <input type="text" name="ma_iv_place" value="' . esc_attr( (string) ( $a->interview_place ?? '' ) ) . '" placeholder="' . esc_attr__( 'Office, or a video link', 'marwa-apply' ) . '"></label></p>';
				echo '<p style="margin:0 0 8px"><input type="text" name="ma_iv_note" class="large-text" value="' . esc_attr( (string) ( $a->interview_note ?? '' ) ) . '" placeholder="' . esc_attr__( 'Anything to bring or prepare', 'marwa-apply' ) . '"></p>';
				}

				echo '<div class="ma-stage-row">';
				echo '<select name="ma_stage_change">';

				foreach ( $stage_names as $key => $label ) {
					echo '<option value="' . esc_attr( $key ) . '"' . selected( $a->status, $key, false ) . '>' . esc_html( $label ) . '</option>';
				}

				echo '</select>';
				printf( '<input type="text" name="ma_note" placeholder="%s" style="flex:1">', esc_attr__( 'Why (recorded in the log)', 'marwa-apply' ) );
				echo '<button class="button button-primary">' . esc_html__( 'Move', 'marwa-apply' ) . '</button>';
				echo '</div></form>';
			} else {
				echo esc_html( Applications::label( $a->status, 'staff' ) );
			}

			echo '</td></tr>';
		}

		echo '</tbody></table></div>';
	}
}
