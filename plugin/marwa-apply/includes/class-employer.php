<?php
/**
 * What an employer sees.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * The employer's own view of their vacancies and the people sent to them.
 *
 * The hard design question here is how much of a candidate an employer gets
 * to see, and the answer is not "everything". On a listings marketplace the
 * candidate's phone number IS the product — that is literally what Bdjobs
 * sells, a fee per unlocked contact. A licensed agency works the other way
 * round: Marwa is the channel, and a worker's number staying inside Marwa is
 * one of the things he is getting in exchange for going through an agency at
 * all.
 *
 * So an employer sees the full professional file — trade, experience,
 * certificates, languages, the generated CV — and does not see the phone
 * number, the email or the home address. Interviews are arranged through
 * Marwa. This is not a limitation to apologise for; it is the product.
 *
 * An employer also only sees candidates Marwa has actually put forward.
 * Everything before "shortlisted" is Marwa's own screening and is none of the
 * employer's business.
 */
final class Employer {

	/**
	 * Stages an employer is allowed to see.
	 *
	 * @return array
	 */
	public static function visible_stages() {
		return array( 'shortlisted', 'interview', 'selected', 'medical', 'visa', 'deployed' );
	}

	/**
	 * The employer area, divided.
	 *
	 * @return array
	 */
	public static function sections() {
		return array(
			'overview'   => array(
				'label' => __( 'Vacancies', 'marwa-apply' ),
				'hint'  => __( 'Your open positions and who Marwa has put forward', 'marwa-apply' ),
			),
			'company'    => array(
				'label' => __( 'Company profile', 'marwa-apply' ),
				'hint'  => __( 'What candidates and Marwa staff see about you', 'marwa-apply' ),
			),
			'agreement'  => array(
				'label' => __( 'Our agreement', 'marwa-apply' ),
				'hint'  => __( 'Demand letter, attestation and recruitment agreement', 'marwa-apply' ),
			),
			'papers'     => array(
				'label' => __( 'Documents', 'marwa-apply' ),
				'hint'  => __( 'What Marwa needs from you before recruiting can start', 'marwa-apply' ),
			),
			'post'       => array(
				'label' => __( 'Submit a vacancy', 'marwa-apply' ),
				'hint'  => __( 'Draft a position for Marwa to review and publish', 'marwa-apply' ),
			),
		);
	}

	/**
	 * Hook up.
	 */
	public static function init() {
		add_shortcode( 'ma_employer', array( __CLASS__, 'render' ) );
		add_shortcode( 'ma_employer_signup', array( __CLASS__, 'signup' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'redirect' ), 10, 3 );
	}

	/**
	 * Send employers to their own page after signing in.
	 *
	 * @param string $to   Requested redirect.
	 * @param string $req  Requested URL.
	 * @param mixed  $user User or error.
	 * @return string
	 */
	public static function redirect( $to, $req, $user ) {
		if ( $user instanceof \WP_User && in_array( Roles::EMPLOYER, (array) $user->roles, true ) ) {
			return Pages::url( 'employer' );
		}

		return $to;
	}

	/**
	 * The employer record for the signed-in user.
	 *
	 * @return object|null
	 */
	public static function row() {
		global $wpdb;

		$id = (int) get_user_meta( get_current_user_id(), '_ma_employer_id', true );

		if ( ! $id ) {
			return null;
		}

		$table = Install::table( 'employers' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * This employer's vacancies.
	 *
	 * @param int $employer_id Employer.
	 * @return array
	 */
	private static function jobs( $employer_id ) {
		return get_posts(
			array(
				'post_type'      => 'job_listing',
				'post_status'    => array( 'publish', 'draft', 'expired' ),
				'posts_per_page' => 200,
				'meta_key'       => '_ma_employer_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => (string) $employer_id, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Candidates put forward against one vacancy.
	 *
	 * @param int $job_id Job.
	 * @return array
	 */
	private static function forwarded( $job_id ) {
		global $wpdb;

		$apps = Install::table( 'applications' );
		$cand = Install::table( 'candidates' );

		$stages = self::visible_stages();
		$in     = implode( ',', array_fill( 0, count( $stages ), '%s' ) );

		$sql = $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT a.id, a.status, a.applied_at, c.id AS cid, c.full_name, c.trade, c.years_experience,
			        c.district, c.jp_language_level, c.en_language_level, c.passport_status, c.available_from
			 FROM {$apps} a
			 INNER JOIN {$cand} c ON c.id = a.candidate_id
			 WHERE a.job_id = %d AND a.status IN ({$in})
			 ORDER BY FIELD(a.status, 'deployed','visa','medical','selected','interview','shortlisted'), a.applied_at ASC",
			array_merge( array( (int) $job_id ), $stages )
		);

		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
	}

	/**
	 * The page.
	 *
	 * @return string
	 */
	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="mo"><div class="ap-shell"><div class="ap-card"><h2>' . esc_html__( 'Sign in to continue', 'marwa-apply' ) . '</h2><p><a href="' . esc_url( Pages::url( 'sign-in' ) ) . '">' . esc_html__( 'Sign in', 'marwa-apply' ) . '</a></p></div></div></div>';
		}

		// Belt and braces: the record link is what identifies the employer,
		// and the capability is what says they may look at it.
		if ( ! current_user_can( 'ma_view_own_vacancies' ) && ! current_user_can( 'ma_manage_employers' ) ) {
			return '<div class="mo"><div class="ap-shell"><div class="ap-card"><h2>' . esc_html__( 'This area is for employers', 'marwa-apply' ) . '</h2><p class="ap-note">' . esc_html__( 'Your account does not have employer access. If you are a candidate, your own file is under My account.', 'marwa-apply' ) . '</p><p><a class="btn" href="' . esc_url( Pages::url( 'account' ) ) . '">' . esc_html__( 'Go to my account', 'marwa-apply' ) . '</a></p></div></div></div>';
		}

		$employer = self::row();

		if ( ! $employer ) {
			return '<div class="mo"><div class="ap-shell"><div class="ap-card"><h2>' . esc_html__( 'This account is not linked to an employer', 'marwa-apply' ) . '</h2><p class="ap-note">' . esc_html__( 'Marwa staff link an employer account to its company record. Please contact your Marwa representative.', 'marwa-apply' ) . '</p></div></div></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$sec      = isset( $_GET['sec'] ) ? sanitize_key( wp_unslash( $_GET['sec'] ) ) : 'overview';
		$sections = self::sections();
		$sec      = isset( $sections[ $sec ] ) ? $sec : 'overview';
		$base     = Pages::url( 'employer' );

		$jobs = self::jobs( (int) $employer->id );

		$out  = self::styles();
		$out .= '<div class="mo"><div class="ap-shell ma-emp-wrap">';

		$out .= '<div class="ma-emp-head"><div>';
		$out .= '<h1>' . esc_html( $employer->name ) . '</h1>';
		$out .= '<p class="ap-note">' . esc_html(
			sprintf(
				/* translators: 1: number of vacancies 2: office */
				__( '%1$d vacancies with Marwa Overseas · handled by the %2$s office', 'marwa-apply' ),
				count( $jobs ),
				ucfirst( (string) $employer->office )
			)
		) . '</p></div>';
		$out .= '<a class="btn" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Sign out', 'marwa-apply' ) . '</a>';
		$out .= '</div>';

		// Section tabs.
		$out .= '<nav class="ma-emp-tabs">';

		foreach ( $sections as $key => $spec ) {
			$out .= '<a class="' . ( $sec === $key ? 'on' : '' ) . '" href="' . esc_url( add_query_arg( 'sec', $key, $base ) ) . '">' . esc_html( $spec['label'] ) . '</a>';
		}

		$out .= '</nav>';

		if ( 'company' === $sec ) {
			return $out . self::company_form( $employer ) . '</div></div>';
		}

		if ( 'agreement' === $sec ) {
			return $out . self::agreement_panel( $employer ) . '</div></div>';
		}

		if ( 'papers' === $sec ) {
			return $out . self::papers_panel( $employer ) . '</div></div>';
		}

		if ( 'post' === $sec ) {
			return $out . self::post_panel( $employer ) . '</div></div>';
		}

		$out .= '<div class="ap-card ma-emp-note"><p>' . esc_html__( 'You are seeing the candidates Marwa has put forward to you. Their professional file and CV are here in full; their phone number, email and home address are not. Interviews are arranged through your Marwa representative.', 'marwa-apply' ) . '</p></div>';

		if ( ! $jobs ) {
			$out .= '<div class="ap-card"><p>' . esc_html__( 'No vacancies are recorded against your company yet.', 'marwa-apply' ) . '</p></div>';

			return $out . '</div></div>';
		}

		$stages = Applications::stages();

		foreach ( $jobs as $job ) {
			$people = self::forwarded( (int) $job->ID );

			$out .= '<section class="ap-card ma-emp-job">';
			$out .= '<div class="ma-emp-job-head">';
			$out .= '<h2><a href="' . esc_url( (string) get_permalink( $job->ID ) ) . '">' . esc_html( get_the_title( $job->ID ) ) . '</a></h2>';
			$out .= '<span class="ma-emp-count">' . esc_html(
				sprintf(
					/* translators: %d: number of candidates */
					_n( '%d candidate put forward', '%d candidates put forward', count( $people ), 'marwa-apply' ),
					count( $people )
				)
			) . '</span></div>';

			if ( ! $people ) {
				$out .= '<p class="ap-note">' . esc_html__( 'Marwa is still screening applicants for this vacancy. Candidates appear here once they have been put forward to you.', 'marwa-apply' ) . '</p></section>';

				continue;
			}

			$out .= '<div class="ma-table-wrap"><table class="ma-emp-table"><thead><tr>';
			$out .= '<th>' . esc_html__( 'Candidate', 'marwa-apply' ) . '</th>';
			$out .= '<th>' . esc_html__( 'Trade', 'marwa-apply' ) . '</th>';
			$out .= '<th>' . esc_html__( 'Experience', 'marwa-apply' ) . '</th>';
			$out .= '<th>' . esc_html__( 'Languages', 'marwa-apply' ) . '</th>';
			$out .= '<th>' . esc_html__( 'Available', 'marwa-apply' ) . '</th>';
			$out .= '<th>' . esc_html__( 'Stage', 'marwa-apply' ) . '</th>';
			$out .= '<th></th></tr></thead><tbody>';

			foreach ( $people as $p ) {
				$langs = array();

				if ( $p->jp_language_level && 'none' !== $p->jp_language_level ) {
					$levels  = Profile::jp_levels();
					$langs[] = isset( $levels[ $p->jp_language_level ] ) ? $levels[ $p->jp_language_level ] : $p->jp_language_level;
				}

				if ( $p->en_language_level && 'none' !== $p->en_language_level ) {
					$levels  = Profile::en_levels();
					$langs[] = isset( $levels[ $p->en_language_level ] ) ? $levels[ $p->en_language_level ] : $p->en_language_level;
				}

				$stage = isset( $stages[ $p->status ] ) ? $stages[ $p->status ]['staff'] : $p->status;

				$out .= '<tr>';
				$out .= '<td><strong>' . esc_html( $p->full_name ) . '</strong><span class="ma-ref">' . esc_html( sprintf( 'MO-%05d', (int) $p->cid ) ) . '</span></td>';
				$out .= '<td>' . esc_html( (string) $p->trade ) . '</td>';
				$out .= '<td>' . esc_html(
					null === $p->years_experience
						? '—'
						: sprintf(
							/* translators: %d: years */
							_n( '%d year', '%d years', (int) $p->years_experience, 'marwa-apply' ),
							(int) $p->years_experience
						)
				) . '</td>';
				$out .= '<td>' . esc_html( $langs ? implode( ' · ', $langs ) : '—' ) . '</td>';
				$out .= '<td>' . esc_html(
					( $p->available_from && '0000-00-00' !== $p->available_from )
						? date_i18n( 'M Y', strtotime( $p->available_from ) )
						: __( 'Now', 'marwa-apply' )
				) . '</td>';
				$out .= '<td><span class="ma-stage">' . esc_html( $stage ) . '</span></td>';
				$out .= '<td><a class="btn" href="' . esc_url( CV::url( (int) $p->cid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'CV', 'marwa-apply' ) . '</a></td>';
				$out .= '</tr>';
			}

			$out .= '</tbody></table></div></section>';
		}

		return $out . '</div></div>';
	}

	/**
	 * Styles.
	 *
	 * @return string
	 */
	private static function styles() {
		return '<style id="marwa-apply-employer">
.mo .ap-shell.ma-emp-wrap{max-width:1320px}
.ma-emp-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:18px}
.ma-emp-head h1{margin:0 0 4px;font-size:29px;line-height:1.2}
.ma-emp-note{background:var(--surface-alt,#F3F1EC)}
.ma-emp-note p{margin:0;font-size:14.5px;line-height:1.65}
.ma-emp-job{margin-bottom:16px}
.ma-emp-job-head{display:flex;align-items:baseline;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:12px}
.ma-emp-job-head h2{margin:0;font-size:19px;line-height:1.35}
.ma-emp-job-head h2 a{text-decoration:none;color:var(--navy,#0B2239)}
.ma-emp-count{font-size:13.5px;color:var(--muted,#5B6B7C)}
.ma-emp-table{width:100%;border-collapse:collapse;font-size:14.5px;min-width:760px}
.ma-emp-table th{text-align:left;font-size:11.5px;letter-spacing:.07em;text-transform:uppercase;color:var(--muted,#5B6B7C);padding:0 12px 8px 0;border-bottom:1px solid var(--line,#E3E0D9);font-weight:700}
.ma-emp-table td{padding:11px 12px 11px 0;border-bottom:1px solid var(--line,#E3E0D9);vertical-align:top;line-height:1.5}
.ma-emp-table tr:last-child td{border-bottom:0}
.ma-emp-table td strong{display:block;color:var(--navy,#0B2239)}
.ma-ref{display:block;font-size:12px;color:var(--muted,#5B6B7C);font-variant-numeric:tabular-nums}
.ma-stage{display:inline-block;font-size:12.5px;font-weight:600;background:var(--surface-alt,#F3F1EC);border-radius:20px;padding:3px 10px;color:var(--navy,#0B2239)}
.ma-emp-table .btn{padding:6px 14px;font-size:13.5px}
.ma-emp-tabs{display:flex;gap:2px;flex-wrap:wrap;border-bottom:2px solid var(--line,#E3E0D9);margin-bottom:18px}
.ma-emp-tabs a{padding:10px 16px;margin-bottom:-2px;border-bottom:2px solid transparent;font-size:14.5px;text-decoration:none;color:var(--muted,#5B6B7C)}
.ma-emp-tabs a.on{color:var(--accent,#A93A2C);border-bottom-color:var(--accent,#A93A2C);font-weight:700}
.ma-emp-facts{display:grid;grid-template-columns:auto 1fr;gap:9px 22px;margin:14px 0 0}
.ma-emp-facts dt{font-size:13.5px;color:var(--muted,#5B6B7C);line-height:1.5}
.ma-emp-facts dd{margin:0;font-size:14.5px;line-height:1.5;color:var(--ink,#0B2239)}
@media(max-width:600px){.ma-emp-facts{grid-template-columns:1fr;gap:2px 0}.ma-emp-facts dd{margin-bottom:10px}}
</style>';
	}

	/**
	 * What an employer may change about their own record.
	 *
	 * Everything else on the employer row — the company name, the country,
	 * the demand letter, the attestation, whether the company may be named
	 * publicly — is Marwa's record of the relationship rather than the
	 * employer's description of themselves, and an employer editing it would
	 * be editing a compliance file. Those are shown read-only.
	 *
	 * @return array
	 */
	private static function editable() {
		return array(
			'city'          => array( 'label' => __( 'City', 'marwa-apply' ), 'type' => 'text' ),
			'website'       => array( 'label' => __( 'Website', 'marwa-apply' ), 'type' => 'url' ),
			'contact_name'  => array( 'label' => __( 'Contact person', 'marwa-apply' ), 'type' => 'text' ),
			'contact_email' => array( 'label' => __( 'Contact email', 'marwa-apply' ), 'type' => 'email' ),
			'contact_phone' => array( 'label' => __( 'Contact phone', 'marwa-apply' ), 'type' => 'text' ),
			'about'         => array( 'label' => __( 'About the company', 'marwa-apply' ), 'type' => 'textarea', 'hint' => __( 'A few sentences a candidate would want to read before applying: what you do, how many people work there, what the site or facility is like.', 'marwa-apply' ) ),
		);
	}

	/**
	 * The company profile form.
	 *
	 * @param object $e Employer row.
	 * @return string
	 */
	private static function company_form( $e ) {
		$out = '';

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) {
			$out .= '<div class="ap-msg ok">' . esc_html__( 'Saved.', 'marwa-apply' ) . '</div>';
		}

		// ------------------------------------------------- Marwa's record
		$out .= '<div class="ap-card"><h2>' . esc_html__( 'Marwa&#8217;s record of your company', 'marwa-apply' ) . '</h2>';
		$out .= '<p class="ap-note">' . esc_html__( 'These are held by Marwa and cannot be edited here. If anything is wrong, tell your Marwa representative and they will correct it.', 'marwa-apply' ) . '</p>';
		$out .= '<dl class="ma-emp-facts">';

		$facts = array(
			__( 'Company name', 'marwa-apply' )        => $e->name,
			__( 'Country', 'marwa-apply' )             => $e->country,
			__( 'Registration number', 'marwa-apply' ) => $e->registration_no,
			__( 'Handled by', 'marwa-apply' )          => ucfirst( (string) $e->office ) . ' ' . __( 'office', 'marwa-apply' ),
			__( 'Named publicly on the jobs page', 'marwa-apply' ) => $e->name_public ? __( 'Yes', 'marwa-apply' ) : __( 'No — vacancies show the sector instead', 'marwa-apply' ),
		);

		foreach ( $facts as $label => $value ) {
			$out .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( '' !== (string) $value ? (string) $value : '—' ) . '</dd>';
		}

		$out .= '</dl></div>';

		// ------------------------------------------------------- the form
		$out .= '<form method="post" class="ap-form" novalidate>';
		$out .= wp_nonce_field( 'ma_emp_profile', 'ma_nonce', true, false );
		$out .= '<input type="hidden" name="ma_action" value="emp_profile">';
		$out .= '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input type="text" name="ma_website" tabindex="-1" autocomplete="off"></label></div>';

		$out .= '<div class="ap-card"><h2>' . esc_html__( 'What you can change', 'marwa-apply' ) . '</h2>';

		foreach ( self::editable() as $name => $field ) {
			$id  = 'e_' . $name;
			$val = (string) $e->$name;

			$out .= '<div class="ap-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] );

			if ( ! empty( $field['hint'] ) ) {
				$out .= '<span class="hint">' . esc_html( $field['hint'] ) . '</span>';
			}

			$out .= '</label>';

			if ( 'textarea' === $field['type'] ) {
				$out .= '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" rows="4">' . esc_textarea( $val ) . '</textarea>';
			} else {
				$out .= '<input type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( $val ) . '">';
			}

			$out .= '</div>';
		}

		$out .= '<button type="submit" class="btn primary">' . esc_html__( 'Save', 'marwa-apply' ) . '</button>';
		$out .= '</div></form>';

		return $out;
	}

	/**
	 * The compliance panel — read-only, because that is what it is.
	 *
	 * This is the paperwork that makes an overseas placement lawful. It is
	 * shown to the employer so both sides are looking at the same file, and
	 * it is not editable by either of them from here: a demand letter
	 * reference that can be typed over is not a record of anything.
	 *
	 * @param object $e Employer row.
	 * @return string
	 */
	private static function agreement_panel( $e ) {
		$states = array(
			'none'     => __( 'Not started', 'marwa-apply' ),
			'pending'  => __( 'With the mission', 'marwa-apply' ),
			'attested' => __( 'Attested', 'marwa-apply' ),
			'signed'   => __( 'Signed and on file', 'marwa-apply' ),
		);

		$rows = array(
			array(
				__( 'Demand letter', 'marwa-apply' ),
				$e->demand_ref ? $e->demand_ref : __( 'Not recorded', 'marwa-apply' ),
				$e->demand_date && '0000-00-00' !== $e->demand_date ? date_i18n( 'j M Y', strtotime( $e->demand_date ) ) : '',
			),
			array(
				__( 'Attestation at the Bangladesh mission', 'marwa-apply' ),
				isset( $states[ $e->attestation ] ) ? $states[ $e->attestation ] : $e->attestation,
				'',
			),
			array(
				__( 'Recruitment agreement', 'marwa-apply' ),
				isset( $states[ $e->agreement ] ) ? $states[ $e->agreement ] : $e->agreement,
				'',
			),
		);

		$out  = '<div class="ap-card"><h2>' . esc_html__( 'Our agreement', 'marwa-apply' ) . '</h2>';
		$out .= '<p class="ap-note">' . esc_html__( 'The paperwork that makes a placement lawful. Marwa maintains this record; it is shown here so both sides are looking at the same file. If something is missing or out of date, raise it with your Marwa representative.', 'marwa-apply' ) . '</p>';

		$out .= '<dl class="ma-emp-facts">';

		foreach ( $rows as $r ) {
			$out .= '<dt>' . esc_html( $r[0] ) . '</dt><dd>' . esc_html( $r[1] );
			$out .= $r[2] ? ' <span class="ap-note">· ' . esc_html( $r[2] ) . '</span>' : '';
			$out .= '</dd>';
		}

		$out .= '</dl></div>';

		$out .= '<div class="ap-card ma-emp-note"><p><strong>' . esc_html__( 'Marwa Overseas does not charge workers.', 'marwa-apply' ) . '</strong> ';
		$out .= esc_html__( 'No candidate put forward to you has paid Marwa a fee to be considered, and none will be asked to. If anybody approaches a candidate claiming otherwise in connection with your vacancies, tell Marwa immediately.', 'marwa-apply' ) . '</p></div>';

		return $out;
	}

	/**
	 * Save the editable part of the employer record.
	 */
	public static function save() {
		global $wpdb;

		$e = self::row();

		if ( ! $e ) {
			return;
		}

		if ( ! current_user_can( 'ma_edit_own_employer' ) ) {
			return;
		}

		$data   = array();
		$format = array();

		foreach ( self::editable() as $name => $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$raw = isset( $_POST[ 'e_' . $name ] ) ? wp_unslash( $_POST[ 'e_' . $name ] ) : '';
			$raw = is_string( $raw ) ? trim( $raw ) : '';

			switch ( $field['type'] ) {
				case 'textarea':
					$value = sanitize_textarea_field( $raw );
					break;

				case 'email':
					$value = sanitize_email( $raw );
					$value = is_email( $value ) ? $value : '';
					break;

				case 'url':
					$value = esc_url_raw( $raw );
					break;

				default:
					$value = sanitize_text_field( $raw );
			}

			$data[ $name ] = '' !== $value ? $value : null;
			$format[]      = '%s';
		}

		$wpdb->update( Install::table( 'employers' ), $data, array( 'id' => (int) $e->id ), $format, array( '%d' ) ); // phpcs:ignore WordPress.DB

		wp_safe_redirect( add_query_arg( array( 'sec' => 'company', 'saved' => '1' ), Pages::url( 'employer' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Registration
	 * ------------------------------------------------------------------ */

	/**
	 * The public employer enquiry form.
	 *
	 * Deliberately NOT self-serve posting. On a job board, an employer signs
	 * up and publishes the same afternoon. A licensed recruiting agency
	 * cannot work that way: placing a Bangladeshi worker overseas requires a
	 * demand letter, attestation at the Bangladesh mission, and a signed
	 * recruitment agreement, and Marwa's licence is what is at risk if a
	 * vacancy goes out before any of that exists.
	 *
	 * So this creates an enquiry, not an account with powers. The company can
	 * sign in and complete their profile immediately; they cannot submit a
	 * vacancy until Marwa has verified who they are and marked the record
	 * active. The form says so, rather than letting somebody discover it.
	 *
	 * @return string
	 */
	public static function signup() {
		if ( is_user_logged_in() ) {
			return '<div class="mo"><div class="ap-shell"><div class="ap-card"><p>' . esc_html__( 'You are already signed in.', 'marwa-apply' ) . ' <a href="' . esc_url( Pages::url( 'employer' ) ) . '">' . esc_html__( 'Go to your employer area', 'marwa-apply' ) . '</a></p></div></div></div>';
		}

		$errors = Accounts::$record_errors;

		$out  = '<div class="mo"><div class="ap-shell" style="max-width:760px">';
		$out .= '<h1 style="margin:0 0 10px">' . esc_html__( 'Recruit from Bangladesh with Marwa Overseas', 'marwa-apply' ) . '</h1>';
		$out .= '<p class="ap-note" style="margin:0 0 22px;max-width:62ch">' . esc_html__( 'Tell us about your company and the work you need filled. A Marwa representative will come back to you to confirm the paperwork — the demand letter, attestation and recruitment agreement — before any vacancy of yours is published.', 'marwa-apply' ) . '</p>';

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['sent'] ) ) {
			$out .= '<div class="ap-card"><div class="ap-msg ok">' . esc_html__( 'Thank you — your enquiry is with Marwa.', 'marwa-apply' ) . '</div>';
			$out .= '<p>' . esc_html__( 'You can sign in now and complete your company profile. Submitting vacancies becomes available once Marwa has verified your company, which is normally a conversation rather than a form.', 'marwa-apply' ) . '</p>';
			$out .= '<p><a class="btn primary" href="' . esc_url( Pages::url( 'sign-in' ) ) . '">' . esc_html__( 'Sign in', 'marwa-apply' ) . '</a></p></div></div></div>';

			return $out;
		}

		if ( ! empty( $errors['form'] ) ) {
			$out .= '<div class="ap-msg bad">' . esc_html( $errors['form'] ) . '</div>';
		}

		$out .= '<form method="post" class="ap-form" novalidate>';
		$out .= wp_nonce_field( 'ma_emp_register', 'ma_nonce', true, false );
		$out .= '<input type="hidden" name="ma_action" value="emp_register">';
		$out .= '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input type="text" name="ma_website" tabindex="-1" autocomplete="off"></label></div>';

		$out .= '<div class="ap-card"><h2>' . esc_html__( 'Your company', 'marwa-apply' ) . '</h2>';

		$fields = array(
			'company' => array( __( 'Company name', 'marwa-apply' ), 'text', '' ),
			'country' => array( __( 'Country', 'marwa-apply' ), 'text', __( 'Where the work is.', 'marwa-apply' ) ),
			'city'    => array( __( 'City', 'marwa-apply' ), 'text', '' ),
			'site'    => array( __( 'Website', 'marwa-apply' ), 'url', '' ),
		);

		foreach ( $fields as $name => $f ) {
			$out .= self::field( $name, $f[0], $f[1], $f[2], $errors );
		}

		$out .= '</div>';

		$out .= '<div class="ap-card"><h2>' . esc_html__( 'You', 'marwa-apply' ) . '</h2>';

		foreach ( array(
			'contact' => array( __( 'Your name', 'marwa-apply' ), 'text', '' ),
			'role'    => array( __( 'Your position', 'marwa-apply' ), 'text', '' ),
			'email'   => array( __( 'Email', 'marwa-apply' ), 'email', __( 'This becomes your sign-in.', 'marwa-apply' ) ),
			'phone'   => array( __( 'Phone', 'marwa-apply' ), 'text', __( 'With country code.', 'marwa-apply' ) ),
		) as $name => $f ) {
			$out .= self::field( $name, $f[0], $f[1], $f[2], $errors );
		}

		$out .= self::field( 'pass', __( 'Choose a password', 'marwa-apply' ), 'password', __( 'At least 10 characters.', 'marwa-apply' ), $errors );
		$out .= '</div>';

		$out .= '<div class="ap-card"><h2>' . esc_html__( 'What you need', 'marwa-apply' ) . '</h2>';
		$out .= '<div class="ap-field"><label for="f_need">' . esc_html__( 'The roles, how many, and when', 'marwa-apply' );
		$out .= '<span class="hint">' . esc_html__( 'For example: 20 formwork carpenters, starting March, 3-year contract. If you already hold a demand letter, say so.', 'marwa-apply' ) . '</span></label>';
		$out .= '<textarea id="f_need" name="f_need" rows="5"></textarea></div>';
		$out .= '<p class="ap-note">' . esc_html__( 'Marwa Overseas holds recruiting licence RL 2831 and does not charge workers any fee. Employers who expect recruitment costs to be borne by the worker should not apply.', 'marwa-apply' ) . '</p>';
		$out .= '<button type="submit" class="btn primary">' . esc_html__( 'Send this to Marwa', 'marwa-apply' ) . '</button>';
		$out .= '</div></form></div></div>';

		return $out;
	}

	/**
	 * One registration field.
	 *
	 * @param string $name   Field name.
	 * @param string $label  Label.
	 * @param string $type   Input type.
	 * @param string $hint   Hint.
	 * @param array  $errors Errors.
	 * @return string
	 */
	private static function field( $name, $label, $type, $hint, $errors ) {
		$id  = 'f_' . $name;
		$err = isset( $errors[ $name ] ) ? $errors[ $name ] : '';

		$out  = '<div class="ap-field' . ( $err ? ' is-error' : '' ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $label );
		$out .= $hint ? '<span class="hint">' . esc_html( $hint ) . '</span>' : '';
		$out .= '</label><input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '"'
			. ( 'password' === $type ? ' autocomplete="new-password"' : '' ) . '>';
		$out .= $err ? '<span class="err">' . esc_html( $err ) . '</span>' : '';

		return $out . '</div>';
	}

	/**
	 * Create the enquiry.
	 *
	 * @return array Errors.
	 */
	public static function do_register() {
		global $wpdb;

		if ( is_user_logged_in() ) {
			return array();
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$get = function ( $key ) {
			return isset( $_POST[ 'f_' . $key ] ) ? trim( (string) wp_unslash( $_POST[ 'f_' . $key ] ) ) : '';
		};
		// phpcs:enable

		$company = sanitize_text_field( $get( 'company' ) );
		$email   = sanitize_email( $get( 'email' ) );
		$pass    = (string) $get( 'pass' );
		$errors  = array();

		if ( '' === $company ) {
			$errors['company'] = __( 'Please give the company name.', 'marwa-apply' );
		}

		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Please give a working email address.', 'marwa-apply' );
		} elseif ( email_exists( $email ) ) {
			$errors['email'] = __( 'That address already has an account. Please sign in instead.', 'marwa-apply' );
		}

		if ( strlen( $pass ) < 10 ) {
			$errors['pass'] = __( 'Please choose a password of at least 10 characters.', 'marwa-apply' );
		}

		if ( $errors ) {
			return $errors;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => 'emp_' . substr( md5( $email . microtime() ), 0, 12 ),
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $company,
				'role'         => Roles::EMPLOYER,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return array( 'form' => __( 'We could not create the account. Please try again.', 'marwa-apply' ) );
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'employers' ),
			array(
				'agency_id'     => Install::current_agency_id(),
				'name'          => $company,
				'country'       => strtoupper( substr( sanitize_text_field( $get( 'country' ) ), 0, 2 ) ),
				'city'          => sanitize_text_field( $get( 'city' ) ),
				'website'       => esc_url_raw( $get( 'site' ) ),
				'contact_name'  => sanitize_text_field( $get( 'contact' ) ),
				'contact_email' => $email,
				'contact_phone' => sanitize_text_field( $get( 'phone' ) ),
				'notes'         => "Self-registered enquiry.\nPosition: " . sanitize_text_field( $get( 'role' ) ) . "\nWhat they need:\n" . sanitize_textarea_field( $get( 'need' ) ),
				'office'        => 'dhaka',
				'name_public'   => 0,
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		$employer_id = (int) $wpdb->insert_id;

		update_user_meta( $user_id, '_ma_employer_id', $employer_id );

		// Tell the office. If mail is down this is lost, which is one more
		// reason the mail fault is the thing to fix first.
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( /* translators: %s: company */ __( 'New employer enquiry: %s', 'marwa-apply' ), $company ),
			sprintf(
				/* translators: 1: company 2: contact 3: email 4: admin link */
				__( "%1\$s has registered as an employer.\n\nContact: %2\$s (%3\$s)\n\nReview and verify them here:\n%4\$s", 'marwa-apply' ),
				$company,
				sanitize_text_field( $get( 'contact' ) ),
				$email,
				admin_url( 'admin.php?page=marwa-employers&id=' . $employer_id )
			)
		);

		wp_safe_redirect( add_query_arg( 'sent', '1', Pages::url( 'employer-signup' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Submitting a vacancy
	 * ------------------------------------------------------------------ */

	/**
	 * The employer's own vacancy form.
	 *
	 * What an employer submits is a draft, never a live posting. Staff review
	 * it, complete the parts that decide eligibility, and publish. That is not
	 * bureaucracy for its own sake: a vacancy on this site carries Marwa's
	 * licence number, and the agency has to be able to stand behind every word
	 * of it.
	 *
	 * @param object $e Employer row.
	 * @return string
	 */
	private static function post_panel( $e ) {
		if ( 'active' !== $e->status ) {
			$out  = '<div class="ap-card"><h2>' . esc_html__( 'Not yet', 'marwa-apply' ) . '</h2>';
			$out .= '<p class="ap-note">' . esc_html__( 'Marwa has your company on file but has not yet completed verification. Submitting vacancies becomes available once the demand letter and recruitment agreement are in place — your Marwa representative will walk you through it.', 'marwa-apply' ) . '</p>';
			$out .= '<p class="ap-note">' . esc_html__( 'This is not a delay for its own sake. Every vacancy on this site carries Marwa&#8217;s licence number, and the agency has to be able to stand behind it.', 'marwa-apply' ) . '</p></div>';

			return $out;
		}

		$out = '';

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['submitted'] ) ) {
			$out .= '<div class="ap-msg ok">' . esc_html__( 'Sent to Marwa. A representative will review it and come back to you.', 'marwa-apply' ) . '</div>';
		}

		$out .= '<div class="ap-card"><h2>' . esc_html__( 'Submit a vacancy', 'marwa-apply' ) . '</h2>';
		$out .= '<p class="ap-note">' . esc_html__( 'This goes to Marwa as a draft. Staff will complete the language and certificate requirements — the things that decide who can lawfully take the job — and publish it. You will see it appear under Vacancies.', 'marwa-apply' ) . '</p>';

		$out .= '<form method="post" class="ap-form" novalidate>';
		$out .= wp_nonce_field( 'ma_emp_vacancy', 'ma_nonce', true, false );
		$out .= '<input type="hidden" name="ma_action" value="emp_vacancy">';
		$out .= '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input type="text" name="ma_website" tabindex="-1" autocomplete="off"></label></div>';

		$out .= self::field( 'vtitle', __( 'Job title', 'marwa-apply' ), 'text', __( 'For example: Formwork Carpenter.', 'marwa-apply' ), array() );
		$out .= self::field( 'vcount', __( 'How many workers', 'marwa-apply' ), 'number', '', array() );
		$out .= self::field( 'vstart', __( 'When you need them', 'marwa-apply' ), 'text', __( 'A month is enough.', 'marwa-apply' ), array() );

		$out .= '<div class="ap-field"><label for="f_vbody">' . esc_html__( 'The work', 'marwa-apply' );
		$out .= '<span class="hint">' . esc_html__( 'What the job involves, the hours, the pay, the contract length, and what you provide — accommodation, food, transport, tickets.', 'marwa-apply' ) . '</span></label>';
		$out .= '<textarea id="f_vbody" name="f_vbody" rows="8"></textarea></div>';

		$out .= '<button type="submit" class="btn primary">' . esc_html__( 'Send to Marwa', 'marwa-apply' ) . '</button>';
		$out .= '</form></div>';

		return $out;
	}

	/**
	 * Store a submitted vacancy as a draft.
	 */
	public static function do_vacancy() {
		$e = self::row();

		if ( ! $e || 'active' !== $e->status || ! current_user_can( 'ma_view_own_vacancies' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$title = isset( $_POST['f_vtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['f_vtitle'] ) ) : '';
		$count = isset( $_POST['f_vcount'] ) ? (int) $_POST['f_vcount'] : 0;
		$start = isset( $_POST['f_vstart'] ) ? sanitize_text_field( wp_unslash( $_POST['f_vstart'] ) ) : '';
		$body  = isset( $_POST['f_vbody'] ) ? sanitize_textarea_field( wp_unslash( $_POST['f_vbody'] ) ) : '';
		// phpcs:enable

		if ( '' === $title ) {
			return;
		}

		$content  = '<p><strong>' . esc_html__( 'Submitted by the employer — not yet reviewed by Marwa.', 'marwa-apply' ) . '</strong></p>';
		$content .= $start ? '<p>' . esc_html__( 'Needed from:', 'marwa-apply' ) . ' ' . esc_html( $start ) . '</p>' : '';
		$content .= wpautop( esc_html( $body ) );

		$job_id = wp_insert_post(
			array(
				'post_type'    => 'job_listing',
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);

		if ( is_wp_error( $job_id ) || ! $job_id ) {
			return;
		}

		update_post_meta( $job_id, '_ma_state', 'draft' );
		update_post_meta( $job_id, '_ma_employer_id', (int) $e->id );
		update_post_meta( $job_id, '_ma_office', $e->office );
		update_post_meta( $job_id, '_ma_from_employer', 1 );
		update_post_meta( $job_id, '_positions', max( 0, $count ) );
		update_post_meta( $job_id, '_company_name', $e->name_public ? $e->name : '' );

		wp_mail(
			get_option( 'admin_email' ),
			sprintf( /* translators: %s: company */ __( 'Vacancy submitted by %s', 'marwa-apply' ), $e->name ),
			sprintf(
				/* translators: 1: title 2: company 3: link */
				__( "%1\$s, submitted by %2\$s.\n\nReview and publish:\n%3\$s", 'marwa-apply' ),
				$title,
				$e->name,
				admin_url( 'admin.php?page=marwa-post-job&job=' . $job_id . '&step=1' )
			)
		);

		wp_safe_redirect( add_query_arg( array( 'sec' => 'post', 'submitted' => '1' ), Pages::url( 'employer' ) ) );
		exit;
	}

	/**
	 * The employer's document checklist.
	 *
	 * Written so a company can see at a glance exactly where they are, which
	 * is the thing that stops the back-and-forth. A rejected document says
	 * why; a missing compulsory one is marked as blocking rather than just
	 * absent.
	 *
	 * @param object $e Employer row.
	 * @return string
	 */
	private static function papers_panel( $e ) {
		$reqs  = Vetting::requirements();
		$filed = Vetting::filed( (int) $e->id );
		$prog  = Vetting::progress( (int) $e->id );

		$out = '';

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['uploaded'] ) ) {
			$out .= '<div class="ap-msg ok">' . esc_html__( 'Received. Marwa will review it.', 'marwa-apply' ) . '</div>';
		}

		$err = Accounts::$record_errors;

		if ( ! empty( $err['form'] ) ) {
			$out .= '<div class="ap-msg bad">' . esc_html( $err['form'] ) . '</div>';
		}

		// ------------------------------------------------------ where we are
		$out .= '<div class="ap-card ma-emp-note">';

		if ( 'active' === $e->status ) {
			$out .= '<p><strong>' . esc_html__( 'Your company is verified.', 'marwa-apply' ) . '</strong> ' . esc_html__( 'Keep these current — an expired licence or a superseded demand letter has to be replaced before new recruitment starts.', 'marwa-apply' ) . '</p>';
		} elseif ( $prog['total'] ) {
			$out .= '<p><strong>' . esc_html(
				sprintf(
					/* translators: 1: accepted count 2: total required */
					__( '%1$d of %2$d required documents accepted.', 'marwa-apply' ),
					$prog['done'],
					$prog['total']
				)
			) . '</strong> ';
			$out .= esc_html__( 'Marwa cannot begin recruiting, and you cannot submit a vacancy, until these are in and checked. It is not a formality — it is what makes a placement lawful and what protects the worker.', 'marwa-apply' ) . '</p>';

			$out .= '<div class="ma-prog"><div class="ma-bar-track"><span style="width:' . esc_attr( (string) ( $prog['total'] ? round( $prog['done'] / $prog['total'] * 100 ) : 0 ) ) . '%"></span></div></div>';
		}

		$out .= '</div>';

		// --------------------------------------------------------- the list
		foreach ( $reqs as $req ) {
			$row   = isset( $filed[ $req['key'] ] ) ? $filed[ $req['key'] ] : null;
			$state = $row ? $row->status : 'none';

			$badge = array(
				'none'      => array( __( 'Not sent', 'marwa-apply' ), 'none' ),
				'submitted' => array( __( 'With Marwa', 'marwa-apply' ), 'wait' ),
				'accepted'  => array( __( 'Accepted', 'marwa-apply' ), 'ok' ),
				'rejected'  => array( __( 'Needs replacing', 'marwa-apply' ), 'bad' ),
			);

			$b = isset( $badge[ $state ] ) ? $badge[ $state ] : $badge['none'];

			$out .= '<div class="ap-card ma-doc' . ( ! empty( $req['required'] ) && 'accepted' !== $state ? ' is-blocking' : '' ) . '">';
			$out .= '<div class="ma-doc-head"><div>';
			$out .= '<h3>' . esc_html( $req['label'] );

			if ( ! empty( $req['required'] ) ) {
				$out .= ' <em class="ma-req">' . esc_html__( 'Required', 'marwa-apply' ) . '</em>';
			} else {
				$out .= ' <em class="ma-opt">' . esc_html__( 'If you have it', 'marwa-apply' ) . '</em>';
			}

			$out .= '</h3>';

			if ( ! empty( $req['hint'] ) ) {
				$out .= '<p class="ap-note">' . esc_html( $req['hint'] ) . '</p>';
			}

			$out .= '</div><span class="ma-badge ' . esc_attr( $b[1] ) . '">' . esc_html( $b[0] ) . '</span></div>';

			if ( $row ) {
				$out .= '<p class="ap-note ma-doc-file">';
				$out .= '<a href="' . esc_url( add_query_arg( 'ma_edoc', (int) $row->id, home_url( '/' ) ) ) . '" target="_blank" rel="noopener">' . esc_html( (string) $row->original_name ) . '</a> ';
				$out .= esc_html( '· ' . size_format( (int) $row->size_bytes ) . ' · ' . mysql2date( get_option( 'date_format' ), $row->uploaded_at ) );
				$out .= '</p>';

				if ( 'rejected' === $row->status && $row->review_note ) {
					$out .= '<p class="ma-doc-why"><strong>' . esc_html__( 'Marwa says:', 'marwa-apply' ) . '</strong> ' . esc_html( $row->review_note ) . '</p>';
				}
			}

			if ( 'accepted' !== $state ) {
				$out .= '<form method="post" enctype="multipart/form-data" class="ma-doc-up">';
				$out .= wp_nonce_field( 'ma_emp_doc', 'ma_nonce', true, false );
				$out .= '<input type="hidden" name="ma_action" value="emp_doc">';
				$out .= '<input type="hidden" name="ma_req" value="' . esc_attr( $req['key'] ) . '">';
				$out .= '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input type="text" name="ma_website" tabindex="-1" autocomplete="off"></label></div>';
				$out .= '<input type="file" name="ma_file" accept=".pdf,.jpg,.jpeg,.png" required>';
				$out .= '<button type="submit" class="btn">' . esc_html( $row ? __( 'Replace', 'marwa-apply' ) : __( 'Send', 'marwa-apply' ) ) . '</button>';
				$out .= '</form>';
			}

			$out .= '</div>';
		}

		$out .= '<p class="ap-note">' . esc_html__( 'PDF, JPG or PNG, up to 10MB each. These are stored outside the public web and are visible only to Marwa staff and to you.', 'marwa-apply' ) . '</p>';

		$out .= '<style>
.ma-doc-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap}
.ma-doc h3{margin:0 0 4px;font-size:16px;line-height:1.4}
.ma-req{font-style:normal;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#A93A2C;margin-left:4px}
.ma-opt{font-style:normal;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#5B6B7C;margin-left:4px}
.ma-doc.is-blocking{border-left:3px solid #A93A2C}
.ma-badge{flex:0 0 auto;font-size:12px;font-weight:700;border-radius:20px;padding:4px 11px;white-space:nowrap}
.ma-badge.none{background:#F3F1EC;color:#5B6B7C}
.ma-badge.wait{background:#FDF9F1;color:#8A6410;border:1px solid #E0CBA8}
.ma-badge.ok{background:#F2F9F5;color:#135C38;border:1px solid #B6DCC6}
.ma-badge.bad{background:#FDF6F4;color:#8E2D20;border:1px solid #D8B9B2}
.ma-doc-file{margin:10px 0 0}
.ma-doc-why{margin:8px 0 0;padding:10px 12px;border-radius:8px;background:#FDF6F4;font-size:14px;line-height:1.55;color:#4A2A24}
.ma-doc-up{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}
.ma-doc-up input[type=file]{font-size:13.5px}
</style>';

		return $out;
	}

	/**
	 * Receive one.
	 *
	 * @return array Errors.
	 */
	public static function do_doc() {
		$e = self::row();

		if ( ! $e || ! current_user_can( 'ma_edit_own_employer' ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$req = isset( $_POST['ma_req'] ) ? sanitize_key( wp_unslash( $_POST['ma_req'] ) ) : '';

		$known = wp_list_pluck( Vetting::requirements(), 'key' );

		if ( ! in_array( $req, $known, true ) ) {
			return array( 'form' => __( 'Unknown document.', 'marwa-apply' ) );
		}

		if ( empty( $_FILES['ma_file'] ) ) {
			return array( 'form' => __( 'Please choose a file.', 'marwa-apply' ) );
		}

		$error = Vetting::store( $_FILES['ma_file'], $req, $e ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( $error ) {
			return array( 'form' => $error );
		}

		wp_safe_redirect( add_query_arg( array( 'sec' => 'papers', 'uploaded' => '1' ), Pages::url( 'employer' ) ) );
		exit;
	}
}
