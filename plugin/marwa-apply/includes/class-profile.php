<?php
/**
 * The candidate profile.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * The structured profile that replaces an uploaded CV.
 *
 * Three constraints drove the shape of this.
 *
 * It must work without JavaScript. A good number of candidates will fill this
 * in on a cheap Android phone on a patchy connection. Every control here is a
 * plain form control and every action is a normal POST.
 *
 * It saves in sections. A single long form that loses everything on a
 * validation error is how you get a half-finished database.
 *
 * It asks only what Marwa can act on. Every extra field is a reason for
 * somebody to give up part-way.
 */
final class Profile {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_shortcode( 'ma_profile', array( __CLASS__, 'render' ) );
	}

	/**
	 * The profile, divided the way a candidate thinks about it.
	 *
	 * Taken from the shape the big boards use, trimmed to what decides an
	 * overseas placement. What is NOT here is as considered as what is:
	 * religion, marital status and disability all sit on the form this
	 * pattern came from, and none of them is Marwa's to ask a jobseeker for.
	 *
	 * @return array
	 */
	public static function sections() {
		return array(
			'about'      => array(
				'label' => __( 'About me', 'marwa-apply' ),
				'hint'  => __( 'Name, date of birth, where you live, how to reach you', 'marwa-apply' ),
			),
			'trade'      => array(
				'label' => __( 'My trade', 'marwa-apply' ),
				'hint'  => __( 'What you do, and how long you have done it', 'marwa-apply' ),
			),
			'education'  => array(
				'label' => __( 'Education and training', 'marwa-apply' ),
				'hint'  => __( 'School, courses, certificates and exams', 'marwa-apply' ),
			),
			'employment' => array(
				'label' => __( 'Work history', 'marwa-apply' ),
				'hint'  => __( 'Where you have worked, here and abroad', 'marwa-apply' ),
			),
			'skills'     => array(
				'label' => __( 'Skills and references', 'marwa-apply' ),
				'hint'  => __( 'What you can do, and who can confirm it', 'marwa-apply' ),
			),
			'travel'     => array(
				'label' => __( 'Travel readiness', 'marwa-apply' ),
				'hint'  => __( 'Passport, languages, and when you can go', 'marwa-apply' ),
			),
			'cv'         => array(
				'label' => __( 'My CV', 'marwa-apply' ),
				'hint'  => __( 'What an employer sees. Built from everything above — click any part of it to go and change that part.', 'marwa-apply' ),
			),
		);
	}

	/**
	 * Recompute and store completeness for one candidate.
	 *
	 * Called whenever a section row is added or removed, so the percentage in
	 * the header strip never drifts away from what is actually on file.
	 *
	 * @param int $candidate_id Candidate.
	 */
	public static function recalculate( $candidate_id ) {
		global $wpdb;

		$table = Install::table( 'candidates' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $candidate_id ) ); // phpcs:ignore WordPress.DB

		if ( ! $row ) {
			return;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB
			$table,
			array( 'profile_completeness' => (int) self::completeness( $row ) ),
			array( 'id' => (int) $candidate_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	// ----------------------------------------------------------- vocabulary

	/**
	 * Who a candidate's file may be shown to.
	 *
	 * Their equivalent is a Public / hidden switch, which on a marketplace
	 * decides whether a paying recruiter can find you. Here the only party
	 * that can ever see the file is an employer Marwa is already working
	 * with, so the choice is narrower and the wording says so.
	 *
	 * @return array
	 */
	public static function visibilities() {
		return array(
			'agency'    => __( 'Marwa only, until I apply to something', 'marwa-apply' ),
			'employers' => __( 'Marwa may show my file to employers it is recruiting for', 'marwa-apply' ),
		);
	}

	/**
	 * Blood groups. Asked because every destination medical records one and
	 * employers abroad keep it on the site file, not because Marwa needs it.
	 *
	 * @return array
	 */
	public static function blood_groups() {
		$out = array( '' => __( 'Not sure', 'marwa-apply' ) );

		foreach ( array( 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-' ) as $g ) {
			$out[ $g ] = $g;
		}

		return $out;
	}

	/**
	 * The CV, shown in the page rather than hidden behind a link.
	 *
	 * Two things this gets right that a separate page does not. A candidate
	 * can see what an employer will see without leaving the form, and every
	 * block carries an Edit link back to the section that fills it — so
	 * finding a mistake and fixing it is one tap, not a hunt through six tabs.
	 *
	 * It is an iframe rather than the CV markup inlined, deliberately: the CV
	 * document carries its own print stylesheet and must stay a self-contained
	 * document, because that is what makes the browser's own Save as PDF
	 * produce a clean file. Inlining it here would mean maintaining the layout
	 * twice and printing the whole portal around it.
	 *
	 * @param object $row Candidate row.
	 * @return string
	 */
	private static function cv_panel( $row ) {
		$base = Pages::url( 'profile' );
		$url  = CV::url( (int) $row->id );

		$out  = '<div class="ap-card ma-cvtools">';
		$out .= '<div><strong>' . esc_html__( 'This is your CV', 'marwa-apply' ) . '</strong>';
		$out .= '<span class="ap-note">' . esc_html__( 'Marwa sends this to employers. You never have to write one.', 'marwa-apply' ) . '</span></div>';
		$out .= '<div class="ma-cvtools-b">';
		$out .= '<a class="btn" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open full size', 'marwa-apply' ) . '</a> ';
		$out .= '<a class="btn primary" href="' . esc_url( add_query_arg( 'print', '1', $url ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Download as PDF', 'marwa-apply' ) . '</a>';
		$out .= '</div></div>';

		$out .= '<p class="ap-note ma-pdfhint">' . esc_html__( 'Download opens the CV in a new tab with the print dialog ready — choose &#8220;Save as PDF&#8221; as the destination. Doing it this way keeps Bangla and Japanese text correct, which the usual PDF tools get wrong.', 'marwa-apply' ) . '</p>';

		// What to fix, and where.
		$jumps = array(
			array( __( 'Name, date of birth, district, contact', 'marwa-apply' ), 'about' ),
			array( __( 'Trade and years of experience', 'marwa-apply' ), 'trade' ),
			array( __( 'Education, training, certificates', 'marwa-apply' ), 'education' ),
			array( __( 'Work history', 'marwa-apply' ), 'employment' ),
			array( __( 'Skills and references', 'marwa-apply' ), 'skills' ),
			array( __( 'Languages, passport, availability', 'marwa-apply' ), 'travel' ),
		);

		$out .= '<div class="ap-card ma-jump"><h3>' . esc_html__( 'Something wrong? Go straight to it', 'marwa-apply' ) . '</h3><ul>';

		foreach ( $jumps as $jump ) {
			$out .= '<li><a href="' . esc_url( add_query_arg( 'sec', $jump[1], $base ) ) . '">' . esc_html( $jump[0] ) . '</a></li>';
		}

		$out .= '</ul></div>';

		$out .= '<div class="ma-cvframe"><iframe src="' . esc_url( $url ) . '" title="' . esc_attr__( 'Your CV', 'marwa-apply' ) . '" loading="lazy"></iframe></div>';

		return $out;
	}

	/**
	 * Styles for the tab row and the CV banner.
	 *
	 * @return string
	 */
	private static function tab_styles() {
		return '<style id="marwa-apply-ptabs">
.ma-cvbar{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;background:var(--surface-alt,#F3F1EC)}
.ma-cvbar-l{display:flex;align-items:center;gap:15px;min-width:0}
.ma-cvbar-l strong{display:block;font-size:16px;line-height:1.35}
.ma-cvbar-l span{display:block;font-size:13.5px;line-height:1.5;color:var(--muted,#5B6B7C)}
.ma-ring{flex:0 0 auto;width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;
	background:conic-gradient(var(--accent,#A93A2C) calc(var(--p) * 1%), #fff 0)}
.ma-ring b{display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;background:var(--surface-alt,#F3F1EC);font-size:12.5px;font-variant-numeric:tabular-nums}
.ma-tabrow{margin:18px 0 16px}
.ma-ptabs{display:flex;gap:2px;flex-wrap:wrap;border-bottom:2px solid var(--line,#E3E0D9)}
.ma-ptabs a{display:flex;align-items:center;gap:7px;padding:10px 15px;margin-bottom:-2px;border-bottom:2px solid transparent;
	font-size:14.5px;line-height:1.4;text-decoration:none;color:var(--muted,#5B6B7C);white-space:nowrap}
.ma-ptabs a:hover{color:var(--navy,#0B2239)}
.ma-ptabs a.on{color:var(--accent,#A93A2C);border-bottom-color:var(--accent,#A93A2C);font-weight:700}
.ma-ptabs a b{font-size:11.5px;font-weight:700;background:var(--surface-alt,#F3F1EC);border-radius:20px;padding:1px 7px}
.ma-tabhint{margin:10px 0 0}
.mo .ap-row.three{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0 18px}
.ma-cvtools{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.ma-cvtools strong{display:block;font-size:16px;line-height:1.35}
.ma-cvtools span{display:block;font-size:13.5px}
.ma-cvtools-b{display:flex;gap:8px;flex-wrap:wrap}
.ma-pdfhint{margin:-4px 0 16px;max-width:66ch}
.ma-jump ul{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:2px 18px}
.ma-jump li{margin:0;font-size:14.5px;line-height:1.6}
.ma-jump h3{margin-top:0;font-size:16px}
.ma-cvframe{border:1px solid var(--line,#E3E0D9);border-radius:12px;overflow:hidden;background:#fff;margin-top:16px}
.ma-cvframe iframe{display:block;width:100%;height:1180px;border:0;background:#fff}
@media(max-width:700px){.ma-cvframe iframe{height:800px}}
@media(max-width:600px){.ma-ptabs{overflow-x:auto;flex-wrap:nowrap}.ma-cvbar .btn{width:100%;text-align:center}.ma-cvtools-b{width:100%}.ma-cvtools-b .btn{flex:1 1 auto;text-align:center}}
</style>';
	}

	/**
	 * The 64 districts, grouped by division.
	 *
	 * A dropdown rather than free text because the staff list filters on it,
	 * and "Cumilla", "Comilla" and "comilla" are three districts to a database.
	 *
	 * @return array division => districts
	 */
	public static function districts() {
		return array(
			'Barishal'   => array( 'Barguna', 'Barishal', 'Bhola', 'Jhalokati', 'Patuakhali', 'Pirojpur' ),
			'Chattogram' => array( 'Bandarban', 'Brahmanbaria', 'Chandpur', 'Chattogram', 'Cumilla', "Cox's Bazar", 'Feni', 'Khagrachhari', 'Lakshmipur', 'Noakhali', 'Rangamati' ),
			'Dhaka'      => array( 'Dhaka', 'Faridpur', 'Gazipur', 'Gopalganj', 'Kishoreganj', 'Madaripur', 'Manikganj', 'Munshiganj', 'Narayanganj', 'Narsingdi', 'Rajbari', 'Shariatpur', 'Tangail' ),
			'Khulna'     => array( 'Bagerhat', 'Chuadanga', 'Jashore', 'Jhenaidah', 'Khulna', 'Kushtia', 'Magura', 'Meherpur', 'Narail', 'Satkhira' ),
			'Mymensingh' => array( 'Jamalpur', 'Mymensingh', 'Netrokona', 'Sherpur' ),
			'Rajshahi'   => array( 'Bogura', 'Chapai Nawabganj', 'Joypurhat', 'Naogaon', 'Natore', 'Pabna', 'Rajshahi', 'Sirajganj' ),
			'Rangpur'    => array( 'Dinajpur', 'Gaibandha', 'Kurigram', 'Lalmonirhat', 'Nilphamari', 'Panchagarh', 'Rangpur', 'Thakurgaon' ),
			'Sylhet'     => array( 'Habiganj', 'Moulvibazar', 'Sunamganj', 'Sylhet' ),
		);
	}

	/**
	 * Japanese levels, in the words the tests actually use.
	 *
	 * @return array
	 */
	public static function jp_levels() {
		return array(
			''           => __( 'None yet', 'marwa-apply' ),
			'studying'   => __( 'Studying, no test passed', 'marwa-apply' ),
			'jft-basic'  => __( 'JFT-Basic (A2) passed', 'marwa-apply' ),
			'n5'         => __( 'JLPT N5', 'marwa-apply' ),
			'n4'         => __( 'JLPT N4', 'marwa-apply' ),
			'n3'         => __( 'JLPT N3', 'marwa-apply' ),
			'n2'         => __( 'JLPT N2 or above', 'marwa-apply' ),
		);
	}

	/**
	 * English levels.
	 *
	 * @return array
	 */
	public static function en_levels() {
		return array(
			''               => __( 'None', 'marwa-apply' ),
			'basic'          => __( 'Basic', 'marwa-apply' ),
			'conversational' => __( 'Conversational', 'marwa-apply' ),
			'fluent'         => __( 'Fluent', 'marwa-apply' ),
			'ielts'          => __( 'IELTS taken', 'marwa-apply' ),
		);
	}

	/**
	 * Passport states.
	 *
	 * @return array
	 */
	public static function passport_states() {
		return array(
			'unknown' => __( 'Not told us yet', 'marwa-apply' ),
			'none'    => __( 'No passport yet', 'marwa-apply' ),
			'applied' => __( 'Applied for, waiting', 'marwa-apply' ),
			'valid'   => __( 'In hand and valid', 'marwa-apply' ),
			'expired' => __( 'In hand but expired', 'marwa-apply' ),
		);
	}

	// ---------------------------------------------------------------- data

	/**
	 * The current user's candidate row.
	 *
	 * @param int $user_id User, defaults to current.
	 * @return object|null
	 */
	public static function row( $user_id = 0 ) {
		global $wpdb;

		$user_id = $user_id ? $user_id : get_current_user_id();

		if ( ! $user_id ) {
			return null;
		}

		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'candidates' ) . ' WHERE user_id = %d', $user_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * How complete is this profile, as a percentage.
	 *
	 * Weighted by what actually decides whether Marwa can place somebody. A
	 * date of birth matters; a Bangla spelling of the name is a nicety.
	 *
	 * @param object $row Candidate row.
	 * @return int 0-100
	 */
	public static function completeness( $row ) {
		if ( ! $row ) {
			return 0;
		}

		$weights = array(
			'full_name'         => 10,
			'dob'               => 10,
			'district'          => 10,
			'sector_id'         => 15,
			'trade'             => 15,
			'years_experience'  => 10,
			'passport_status'   => 15,
			'jp_language_level' => 5,
			'en_language_level' => 5,
			'full_name_bn'      => 5,
		);

		$score = 0;

		foreach ( $weights as $field => $weight ) {
			$value = isset( $row->$field ) ? $row->$field : '';

			if ( 'passport_status' === $field ) {
				if ( $value && 'unknown' !== $value ) {
					$score += $weight;
				}
				continue;
			}

			if ( '' !== $value && null !== $value && '0000-00-00' !== $value ) {
				$score += $weight;
			}
		}

		return min( 100, $score );
	}

	// -------------------------------------------------------------- saving

	/**
	 * Handle a posted profile. Called from Accounts::handle().
	 */
	public static function save() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		global $wpdb;

		$post    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security
		$user_id = get_current_user_id();
		$row     = self::row( $user_id );

		if ( ! $row ) {
			return;
		}

		// The profile is saved one tab at a time, so most of these fields are
		// absent from any given POST. Falling back to what is already stored
		// is what stops saving "My trade" from wiping a name and a district
		// that were filled in on another tab — the single worst thing a form
		// like this can do to somebody.
		$fallback = array(
			'ma_name'            => $row->full_name,
			'ma_name_bn'         => $row->full_name_bn,
			'ma_dob'             => ( $row->dob && '0000-00-00' !== $row->dob ) ? $row->dob : '',
			'ma_district'        => $row->district,
			'ma_father'          => $row->father_name,
			'ma_mother'          => $row->mother_name,
			'ma_present'         => $row->present_address,
			'ma_permanent'       => $row->permanent_address,
			'ma_em_name'         => $row->emergency_name,
			'ma_em_phone'        => $row->emergency_phone,
			'ma_height'          => $row->height_cm,
			'ma_weight'          => $row->weight_kg,
			'ma_blood'           => $row->blood_group,
			'ma_sector'          => $row->sector_id,
			'ma_trade'           => $row->trade,
			'ma_years'           => $row->years_experience,
			'ma_jp'              => $row->jp_language_level,
			'ma_en'              => $row->en_language_level,
			'ma_passport_status' => $row->passport_status,
			'ma_passport_no'     => $row->passport_no,
			'ma_available'       => ( $row->available_from && '0000-00-00' !== $row->available_from ) ? $row->available_from : '',
			'ma_visibility'      => $row->visibility,
		);

		foreach ( $fallback as $key => $stored ) {
			if ( ! isset( $post[ $key ] ) ) {
				$post[ $key ] = null === $stored ? '' : (string) $stored;
			}
		}

		$name    = sanitize_text_field( $post['ma_name'] ?? '' );
		$name_bn = sanitize_text_field( $post['ma_name_bn'] ?? '' );
		$dob     = sanitize_text_field( $post['ma_dob'] ?? '' );
		$dist    = sanitize_text_field( $post['ma_district'] ?? '' );
		$sector  = (int) ( $post['ma_sector'] ?? 0 );
		$trade   = sanitize_text_field( $post['ma_trade'] ?? '' );
		$years   = (int) ( $post['ma_years'] ?? 0 );
		$jp      = sanitize_text_field( $post['ma_jp'] ?? '' );
		$en      = sanitize_text_field( $post['ma_en'] ?? '' );
		$pstat   = sanitize_text_field( $post['ma_passport_status'] ?? 'unknown' );
		$pno     = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $post['ma_passport_no'] ?? '' ) ) );

		$father    = sanitize_text_field( $post['ma_father'] ?? '' );
		$mother    = sanitize_text_field( $post['ma_mother'] ?? '' );
		$present   = sanitize_textarea_field( $post['ma_present'] ?? '' );
		$permanent = sanitize_textarea_field( $post['ma_permanent'] ?? '' );
		$em_name   = sanitize_text_field( $post['ma_em_name'] ?? '' );
		$em_phone  = sanitize_text_field( $post['ma_em_phone'] ?? '' );
		$height    = (int) ( $post['ma_height'] ?? 0 );
		$weight    = (int) ( $post['ma_weight'] ?? 0 );
		$blood     = sanitize_text_field( $post['ma_blood'] ?? '' );
		$available  = sanitize_text_field( $post['ma_available'] ?? '' );
		$visibility = sanitize_text_field( $post['ma_visibility'] ?? 'agency' );

		$sec    = isset( $post['ma_sec'] ) ? sanitize_key( $post['ma_sec'] ) : 'about';
		$sec    = isset( self::sections()[ $sec ] ) ? $sec : 'about';
		$errors = array();

		if ( mb_strlen( $name ) < 3 ) {
			$errors['ma_name'] = __( 'Please give your full name.', 'marwa-apply' );
		}

		if ( $dob ) {
			$ts = strtotime( $dob );

			if ( ! $ts ) {
				$errors['ma_dob'] = __( 'Please give the date as year-month-day.', 'marwa-apply' );
			} else {
				$age = (int) floor( ( time() - $ts ) / YEAR_IN_SECONDS );

				if ( $age < 18 ) {
					// Not a validation nicety: nobody under 18 can be placed
					// overseas, and holding their documents would be worse
					// than useless.
					$errors['ma_dob'] = __( 'You must be at least 18 to be placed in overseas employment.', 'marwa-apply' );
				} elseif ( $age > 70 ) {
					$errors['ma_dob'] = __( 'Please check the year — that date gives an age over 70.', 'marwa-apply' );
				}
			}
		}

		if ( $years < 0 || $years > 50 ) {
			$errors['ma_years'] = __( 'Please give years of experience between 0 and 50.', 'marwa-apply' );
		}

		$valid_districts = array_merge( ...array_values( self::districts() ) );

		if ( $dist && ! in_array( $dist, $valid_districts, true ) ) {
			$errors['ma_district'] = __( 'Please choose a district from the list.', 'marwa-apply' );
		}

		if ( ! array_key_exists( $jp, self::jp_levels() ) ) {
			$jp = '';
		}

		if ( ! array_key_exists( $en, self::en_levels() ) ) {
			$en = '';
		}

		if ( ! array_key_exists( $pstat, self::passport_states() ) ) {
			$pstat = 'unknown';
		}

		if ( $errors ) {
			set_transient( 'ma_profile_err_' . $user_id, $errors, 120 );
			wp_safe_redirect( add_query_arg( array( 'saved' => '0', 'sec' => $sec ), Pages::url( 'profile' ) ) );
			exit;
		}

		$data = array(
			'full_name'         => $name,
			'full_name_bn'      => $name_bn,
			'dob'               => $dob ? gmdate( 'Y-m-d', strtotime( $dob ) ) : null,
			'district'          => $dist,
			'sector_id'         => $sector ? $sector : null,
			'trade'             => $trade,
			'years_experience'  => $years,
			'jp_language_level' => $jp,
			'en_language_level' => $en,
			'passport_status'   => $pstat,
			'passport_no'       => $pno ? $pno : null,
			'father_name'       => $father ? $father : null,
			'mother_name'       => $mother ? $mother : null,
			'present_address'   => $present ? $present : null,
			'permanent_address' => $permanent ? $permanent : null,
			'emergency_name'    => $em_name ? $em_name : null,
			'emergency_phone'   => $em_phone ? $em_phone : null,
			'height_cm'         => ( $height >= 100 && $height <= 230 ) ? $height : null,
			'weight_kg'         => ( $weight >= 30 && $weight <= 200 ) ? $weight : null,
			'blood_group'       => isset( self::blood_groups()[ $blood ] ) ? $blood : null,
			'available_from'    => $available ? gmdate( 'Y-m-d', strtotime( $available ) ) : null,
			'visibility'        => isset( self::visibilities()[ $visibility ] ) ? $visibility : 'agency',
			'updated_at'        => current_time( 'mysql' ),
		);

		$wpdb->update( Install::table( 'candidates' ), $data, array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB

		$fresh = self::row( $user_id );

		$wpdb->update( // phpcs:ignore WordPress.DB
			Install::table( 'candidates' ),
			array( 'profile_completeness' => self::completeness( $fresh ) ),
			array( 'id' => $row->id )
		);

		if ( $name && $name !== wp_get_current_user()->display_name ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name,
				)
			);
		}

		wp_safe_redirect( add_query_arg( array( 'saved' => '1', 'sec' => $sec ), Pages::url( 'profile' ) ) );
		exit;
	}

	// -------------------------------------------------------------- render

	/**
	 * A select element.
	 *
	 * @param string $name    Field name.
	 * @param array  $options value => label, or group => array.
	 * @param string $current Selected value.
	 * @param bool   $grouped Whether options are grouped.
	 * @return string
	 */
	private static function select( $name, $options, $current, $grouped = false ) {
		$out = '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';

		if ( $grouped ) {
			$out .= '<option value="">' . esc_html__( '— choose —', 'marwa-apply' ) . '</option>';

			foreach ( $options as $group => $items ) {
				$out .= '<optgroup label="' . esc_attr( $group ) . '">';

				foreach ( $items as $item ) {
					$out .= '<option value="' . esc_attr( $item ) . '"' . selected( $current, $item, false ) . '>' . esc_html( $item ) . '</option>';
				}

				$out .= '</optgroup>';
			}
		} else {
			foreach ( $options as $value => $label ) {
				$out .= '<option value="' . esc_attr( $value ) . '"' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
		}

		return $out . '</select>';
	}

	/**
	 * Wrap a control in a field.
	 *
	 * @param string $name    Field name.
	 * @param string $label   Label.
	 * @param string $control Control markup.
	 * @param string $hint    Hint.
	 * @param array  $errors  Errors.
	 * @return string
	 */
	private static function wrap( $name, $label, $control, $hint = '', $errors = array() ) {
		$err = $errors[ $name ] ?? '';

		$out  = '<div class="ap-field' . ( $err ? ' is-error' : '' ) . '">';
		$out .= '<label for="' . esc_attr( $name ) . '">' . esc_html( $label );

		if ( $hint ) {
			$out .= '<span class="hint">' . esc_html( $hint ) . '</span>';
		}

		$out .= '</label>' . $control;

		if ( $err ) {
			$out .= '<span class="err">' . esc_html( $err ) . '</span>';
		}

		return $out . '</div>';
	}

	/**
	 * The profile page.
	 *
	 * @return string
	 */
	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="ap-shell"><div class="ap-card"><h2>' . esc_html__( 'Sign in to continue', 'marwa-apply' ) . '</h2><p><a href="' . esc_url( Pages::url( 'sign-in' ) ) . '">' . esc_html__( 'Sign in', 'marwa-apply' ) . '</a> ' . esc_html__( 'or', 'marwa-apply' ) . ' <a href="' . esc_url( Pages::url( 'register' ) ) . '">' . esc_html__( 'create an account', 'marwa-apply' ) . '</a>.</p></div></div>';
		}

		$user_id = get_current_user_id();
		$row     = self::row( $user_id );

		if ( ! $row ) {
			return '<div class="ap-shell"><div class="ap-card"><p>' . esc_html__( 'We could not find your file. Please contact Marwa Overseas.', 'marwa-apply' ) . '</p></div></div>';
		}

		$errors = get_transient( 'ma_profile_err_' . $user_id );
		$errors = is_array( $errors ) ? $errors : array();

		if ( $errors ) {
			delete_transient( 'ma_profile_err_' . $user_id );
		}

		$pct = self::completeness( $row );

		$out = Shell::open( 'profile' );

		if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out .= '<div class="ap-msg ok">' . esc_html__( 'Saved.', 'marwa-apply' ) . '</div>';
		}

		if ( $errors ) {
			$out .= '<div class="ap-msg bad">' . esc_html__( 'Nothing was saved — please check the fields marked below.', 'marwa-apply' ) . '</div>';
		}

		// Which section is open is in the URL, so a half-finished profile is a
		// link somebody can come back to — and the tabs need no script.
		// phpcs:ignore WordPress.Security.NonceVerification
		$sec      = isset( $_GET['sec'] ) ? sanitize_key( wp_unslash( $_GET['sec'] ) ) : 'about';
		$sections = self::sections();

		if ( ! isset( $sections[ $sec ] ) ) {
			$sec = 'about';
		}

		$base   = Pages::url( 'profile' );
		$counts = Records::counts( (int) $row->id );

		$out .= Records::styles();
		$out .= self::tab_styles();

		// The generated CV, announced rather than hidden behind a link. If a
		// candidate does not know it exists, it may as well not.
		$out .= '<div class="ap-card ma-cvbar">';
		$out .= '<div class="ma-cvbar-l"><span class="ma-ring" style="--p:' . esc_attr( (string) $pct ) . '"><b>' . esc_html( $pct . '%' ) . '</b></span>';
		$out .= '<div><strong>' . esc_html__( 'Marwa has built your CV from this profile', 'marwa-apply' ) . '</strong>';
		$out .= '<span>' . esc_html(
			sprintf(
				/* translators: %d: percentage complete */
				__( '%d%% complete. You do not need to write a CV — fill this in and the CV follows.', 'marwa-apply' ),
				$pct
			)
		) . '</span></div></div>';
		$out .= '<a class="btn primary" href="' . esc_url( add_query_arg( 'sec', 'cv', $base ) ) . '">' . esc_html__( 'View my CV', 'marwa-apply' ) . '</a>';
		$out .= '</div>';

		// Section tabs.
		$out .= '<div class="ma-tabrow"><nav class="ma-ptabs">';

		foreach ( $sections as $key => $spec ) {
			$badge = '';

			if ( 'education' === $key ) {
				$n     = $counts['education'] + $counts['training'] + $counts['certificates'];
				$badge = $n ? '<b>' . esc_html( (string) $n ) . '</b>' : '';
			} elseif ( 'skills' === $key ) {
				$n     = $counts['skills'] + $counts['referees'];
				$badge = $n ? '<b>' . esc_html( (string) $n ) . '</b>' : '';
			}

			$out .= '<a class="' . ( $sec === $key ? 'on' : '' ) . '" href="' . esc_url( add_query_arg( 'sec', $key, $base ) ) . '">';
			$out .= '<span>' . esc_html( $spec['label'] ) . '</span>' . $badge . '</a>';
		}

		$out .= '</nav><p class="ap-note ma-tabhint">' . esc_html( $sections[ $sec ]['hint'] ) . '</p></div>';

		// ------------------------------------------------------------ the CV
		if ( 'cv' === $sec ) {
			$out .= self::cv_panel( $row );

			return $out . Shell::close();
		}

		// ---------------------------------------------- the repeater sections
		if ( in_array( $sec, array( 'education', 'employment', 'skills' ), true ) ) {
			$errs = Accounts::$record_errors;

			if ( 'education' === $sec ) {
				$out .= Records::section( 'education', $row, $errs );
				$out .= Records::section( 'training', $row, $errs );
				$out .= Records::section( 'certificates', $row, $errs );
			} elseif ( 'employment' === $sec ) {
				$out .= Experience::section( $row );
			} else {
				$out .= Records::section( 'skills', $row, $errs );
				$out .= Records::section( 'referees', $row, $errs );
			}

			return $out . Shell::close();
		}

		$out .= '<form method="post" class="ap-form" novalidate>';
		$out .= '<input type="hidden" name="ma_sec" value="' . esc_attr( $sec ) . '">';
		$out .= wp_nonce_field( 'ma_profile', 'ma_nonce', true, false );
		$out .= '<input type="hidden" name="ma_action" value="profile">';
		$out .= '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input type="text" name="ma_website" tabindex="-1" autocomplete="off"></label></div>';

		// ------------------------------------------------------- who you are
		if ( 'about' === $sec ) {
		$out .= '<div class="ap-card"><h3>' . esc_html__( 'Who you are', 'marwa-apply' ) . '</h3>';
		$out .= self::wrap( 'ma_name', __( 'Full name', 'marwa-apply' ), '<input type="text" id="ma_name" name="ma_name" value="' . esc_attr( $row->full_name ) . '" autocomplete="name">', __( 'Exactly as on your passport or NID.', 'marwa-apply' ), $errors );
		$out .= self::wrap( 'ma_name_bn', __( 'Name in Bangla', 'marwa-apply' ), '<input type="text" id="ma_name_bn" name="ma_name_bn" value="' . esc_attr( $row->full_name_bn ) . '">', __( 'Optional.', 'marwa-apply' ), $errors );

		$out .= '<div class="ap-row two">';
		$out .= self::wrap( 'ma_dob', __( 'Date of birth', 'marwa-apply' ), '<input type="date" id="ma_dob" name="ma_dob" value="' . esc_attr( $row->dob && '0000-00-00' !== $row->dob ? $row->dob : '' ) . '">', '', $errors );
		$out .= self::wrap( 'ma_district', __( 'Home district', 'marwa-apply' ), self::select( 'ma_district', self::districts(), $row->district, true ), '', $errors );
		$out .= '</div>';

		$out .= '<div class="ap-row two">';
		$out .= self::wrap( 'ma_father', __( 'Father&#8217;s name', 'marwa-apply' ), '<input type="text" id="ma_father" name="ma_father" value="' . esc_attr( (string) $row->father_name ) . '">', __( 'As on your passport or NID — employers and the BMET forms both ask.', 'marwa-apply' ), $errors );
		$out .= self::wrap( 'ma_mother', __( 'Mother&#8217;s name', 'marwa-apply' ), '<input type="text" id="ma_mother" name="ma_mother" value="' . esc_attr( (string) $row->mother_name ) . '">', '', $errors );
		$out .= '</div></div>';

		// --------------------------------------------------------- contact
		$out .= '<div class="ap-card"><h3>' . esc_html__( 'Where you live, and who to call', 'marwa-apply' ) . '</h3>';
		$out .= self::wrap( 'ma_present', __( 'Present address', 'marwa-apply' ), '<textarea id="ma_present" name="ma_present" rows="2">' . esc_textarea( (string) $row->present_address ) . '</textarea>', __( 'Where you are living now.', 'marwa-apply' ), $errors );
		$out .= self::wrap( 'ma_permanent', __( 'Permanent address', 'marwa-apply' ), '<textarea id="ma_permanent" name="ma_permanent" rows="2">' . esc_textarea( (string) $row->permanent_address ) . '</textarea>', __( 'Your village or home address. Leave empty if it is the same.', 'marwa-apply' ), $errors );

		$out .= '<div class="ap-row two">';
		$out .= self::wrap( 'ma_em_name', __( 'Emergency contact — name', 'marwa-apply' ), '<input type="text" id="ma_em_name" name="ma_em_name" value="' . esc_attr( (string) $row->emergency_name ) . '">', __( 'Someone Marwa can reach while you are abroad.', 'marwa-apply' ), $errors );
		$out .= self::wrap( 'ma_em_phone', __( 'Emergency contact — phone', 'marwa-apply' ), '<input type="tel" id="ma_em_phone" name="ma_em_phone" value="' . esc_attr( (string) $row->emergency_phone ) . '">', '', $errors );
		$out .= '</div></div>';

		// ----------------------------------------------- for the medical
		$out .= '<div class="ap-card"><h3>' . esc_html__( 'For the medical and your kit', 'marwa-apply' ) . '</h3>';
		$out .= '<p class="ap-note">' . esc_html__( 'Every destination requires a medical, and employers order boots and uniforms before you arrive. None of this is used to judge your application.', 'marwa-apply' ) . '</p>';
		$out .= '<div class="ap-row three">';
		$out .= self::wrap( 'ma_height', __( 'Height (cm)', 'marwa-apply' ), '<input type="number" id="ma_height" name="ma_height" min="100" max="230" inputmode="numeric" value="' . esc_attr( null === $row->height_cm ? '' : $row->height_cm ) . '">', '', $errors );
		$out .= self::wrap( 'ma_weight', __( 'Weight (kg)', 'marwa-apply' ), '<input type="number" id="ma_weight" name="ma_weight" min="30" max="200" inputmode="numeric" value="' . esc_attr( null === $row->weight_kg ? '' : $row->weight_kg ) . '">', '', $errors );
		$out .= self::wrap( 'ma_blood', __( 'Blood group', 'marwa-apply' ), self::select( 'ma_blood', self::blood_groups(), $row->blood_group ), '', $errors );
		$out .= '</div></div>';
		}

		// ----------------------------------------------------- what you do
		if ( 'trade' === $sec ) {
		$sectors = Sectors::options( true );
		$sector_options = array( '' => __( '— choose —', 'marwa-apply' ) ) + $sectors;

		$out .= '<div class="ap-card"><h3>' . esc_html__( 'What you do', 'marwa-apply' ) . '</h3>';
		$out .= self::wrap( 'ma_sector', __( 'Sector', 'marwa-apply' ), self::select( 'ma_sector', $sector_options, $row->sector_id ), __( 'The kind of work you want, not only what you have done.', 'marwa-apply' ), $errors );

		$out .= '<div class="ap-row two">';
		$out .= self::wrap( 'ma_trade', __( 'Trade or job title', 'marwa-apply' ), '<input type="text" id="ma_trade" name="ma_trade" value="' . esc_attr( $row->trade ) . '">', __( 'For example: welder, rebar fitter, care assistant.', 'marwa-apply' ), $errors );
		$out .= self::wrap( 'ma_years', __( 'Years of experience', 'marwa-apply' ), '<input type="number" id="ma_years" name="ma_years" min="0" max="50" inputmode="numeric" value="' . esc_attr( null === $row->years_experience ? '' : $row->years_experience ) . '">', '', $errors );
		$out .= '</div></div>';
		}

		// ------------------------------------------------------- languages
		if ( 'travel' === $sec ) {
		$out .= '<div class="ap-card"><h3>' . esc_html__( 'Languages', 'marwa-apply' ) . '</h3>';
		$out .= '<div class="ap-row two">';
		$out .= self::wrap( 'ma_jp', __( 'Japanese', 'marwa-apply' ), self::select( 'ma_jp', self::jp_levels(), $row->jp_language_level ), __( 'Most Japanese jobs need JFT-Basic or JLPT N4.', 'marwa-apply' ), $errors );
		$out .= self::wrap( 'ma_en', __( 'English', 'marwa-apply' ), self::select( 'ma_en', self::en_levels(), $row->en_language_level ), __( 'Gulf nursing work is done in English.', 'marwa-apply' ), $errors );
		$out .= '</div></div>';

		// -------------------------------------------------------- passport
		$out .= '<div class="ap-card"><h3>' . esc_html__( 'Passport', 'marwa-apply' ) . '</h3>';
		$out .= '<div class="ap-row two">';
		$out .= self::wrap( 'ma_passport_status', __( 'Do you have a passport?', 'marwa-apply' ), self::select( 'ma_passport_status', self::passport_states(), $row->passport_status ), __( 'Say so honestly — not having one yet does not disqualify you.', 'marwa-apply' ), $errors );
		$out .= self::wrap( 'ma_passport_no', __( 'Passport number', 'marwa-apply' ), '<input type="text" id="ma_passport_no" name="ma_passport_no" value="' . esc_attr( $row->passport_no ) . '" autocomplete="off">', __( 'Leave blank if you do not have one yet.', 'marwa-apply' ), $errors );
		$out .= '</div>';
		$out .= '<p class="ap-note">' . esc_html__( 'Your passport number is visible only to Marwa management, never to employers or other staff.', 'marwa-apply' ) . '</p>';
		$out .= '</div>';

		// ---------------------------------------------- who can see this
		$out .= '<div class="ap-card"><h3>' . esc_html__( 'Who can see your file', 'marwa-apply' ) . '</h3>';
		$out .= '<p class="ap-note">' . esc_html__( 'Marwa staff always see your file — that is how you get put forward for work. This controls whether an employer Marwa is talking to may be shown it before you have applied to them.', 'marwa-apply' ) . '</p>';
		$out .= self::wrap(
			'ma_visibility',
			__( 'Showing your file to employers', 'marwa-apply' ),
			self::select( 'ma_visibility', self::visibilities(), $row->visibility ),
			__( 'Your phone number and address are never shown to an employer either way.', 'marwa-apply' ),
			$errors
		);
		$out .= '</div>';

		// ------------------------------------------------ when you can go
		$out .= '<div class="ap-card"><h3>' . esc_html__( 'When you can go', 'marwa-apply' ) . '</h3>';
		$out .= '<p class="ap-note">' . esc_html__( 'Employers ask this constantly, and an honest date is worth more than an eager one. Saying March does not push you down any list.', 'marwa-apply' ) . '</p>';
		$out .= self::wrap( 'ma_available', __( 'Ready to travel from', 'marwa-apply' ), '<input type="date" id="ma_available" name="ma_available" value="' . esc_attr( $row->available_from && '0000-00-00' !== $row->available_from ? $row->available_from : '' ) . '">', __( 'Leave empty if you are ready now.', 'marwa-apply' ), $errors );
		$out .= '</div>';
		}

		$out .= '<div class="ap-card"><button type="submit" class="btn primary">' . esc_html__( 'Save my profile', 'marwa-apply' ) . '</button> &nbsp; <a href="' . esc_url( Pages::url( 'account' ) ) . '">' . esc_html__( 'Back to my account', 'marwa-apply' ) . '</a></div>';
		$out .= '</form>';

		return $out . Shell::close();
	}
}
