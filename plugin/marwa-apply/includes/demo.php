<?php
/**
 * Demonstration data.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/*
 * A build tool that ships inside the plugin.
 *
 * It lives here rather than in a separate plugin so there is one thing to
 * install and one button to press. That convenience has a cost worth being
 * explicit about: seed code is now in the production plugin. Three things
 * keep that honest.
 *
 * Nothing runs unless an administrator presses a button — there is no hook
 * that fires on its own. Every record it writes is indexed, so "Remove
 * everything" removes exactly what it added. And the screen itself is the
 * launch checklist: it will not let you forget the data is there.
 */


const MA_DEMO_INDEX  = 'ma_demo_index';
const MA_DEMO_MARK   = '_ma_demo';
const MA_DEMO_BANNER = 'DEMONSTRATION POSTING — this is test data for layout review. It is not an open vacancy, the employer is fictional, and no application made against it will be processed.';

/* -------------------------------------------------------------------------
 * Index — what this tool has created
 * ---------------------------------------------------------------------- */

/**
 * Keep the seeder off the mail queue, and give it room to run.
 *
 * Creating a user fires hooks that other plugins answer by sending mail. With
 * SMTP misconfigured each of those blocks until it times out, which is how a
 * run of 28 candidates stops after nine. Nothing generated here has a real
 * mailbox behind it anyway, so the mail is pure waste.
 */
function ma_demo_quiet() {
	add_filter( 'pre_wp_mail', '__return_true', 99 );
	add_filter( 'send_password_change_email', '__return_false', 99 );
	add_filter( 'send_email_change_email', '__return_false', 99 );

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	// Every builder skips what already exists, so a run that is cut short is
	// resumed simply by pressing the same button again.
	wp_defer_term_counting( true );
	wp_suspend_cache_invalidation( false );
}

/**
 * Read the index.
 *
 * @return array
 */
function ma_demo_index() {
	$index = get_option( MA_DEMO_INDEX, array() );

	return wp_parse_args(
		is_array( $index ) ? $index : array(),
		array(
			'users'        => array(),
			'posts'        => array(),
			'attachments'  => array(),
			'employers'    => array(),
			'candidates'   => array(),
			'applications' => array(),
			'experience'   => array(),
			'education'    => array(),
			'training'     => array(),
			'certificates' => array(),
			'skills'       => array(),
			'referees'     => array(),
			'terms'        => array(),
		)
	);
}

/**
 * Record one created thing.
 *
 * @param string $type Bucket.
 * @param int    $id   Identifier.
 */
function ma_demo_remember( $type, $id ) {
	$index = ma_demo_index();

	$index[ $type ][] = (int) $id;
	$index[ $type ]   = array_values( array_unique( $index[ $type ] ) );

	update_option( MA_DEMO_INDEX, $index, false );
}

/* -------------------------------------------------------------------------
 * Source data — all fictional
 * ---------------------------------------------------------------------- */

/**
 * The invented employers.
 *
 * @return array
 */
function ma_demo_employers() {
	return array(
		array( 'Hokuto Care Group', 'JP', 'care-work', 'dhaka', '#1F6F8B' ),
		array( 'Shinwa Construction KK', 'JP', 'civil-construction', 'dhaka', '#B8621B' ),
		array( 'Tsurumi Food Industries', 'JP', 'food-hospitality', 'dhaka', '#9A1F3C' ),
		array( 'Midori Building Services', 'JP', 'cleaning-facility', 'dhaka', '#2E7D53' ),
		array( 'Asahi Precision Works', 'JP', 'manufacturing', 'dhaka', '#44506B' ),
		array( 'Kaizen Auto Service', 'JP', 'technical-trades', 'dhaka', '#1B4F8A' ),
		array( 'Setouchi Shipyard', 'JP', 'marine-shipyard', 'dhaka', '#17566B' ),
		array( 'Nagano Agri Partners', 'JP', 'agriculture', 'dhaka', '#4B7A2B' ),
		array( 'Yamazato Logistics', 'JP', 'logistics', 'dhaka', '#6B4C9A' ),
		array( 'Al Rayan Medical Centre', 'SA', 'health-sector', 'dhaka', '#0E6B5C' ),
		array( 'Gulf Horizon Contracting', 'SA', 'civil-construction', 'dhaka', '#A8601C' ),
		array( 'Desert Rose Facilities', 'AE', 'cleaning-facility', 'dhaka', '#8A2B4C' ),
		array( 'Emirates Logistics Hub', 'AE', 'logistics', 'dhaka', '#2A5C8F' ),
		array( 'Doha Transport Services', 'QA', 'transportation', 'dhaka', '#6B2D4F' ),
		array( 'Kuwait Catering Group', 'KW', 'food-hospitality', 'dhaka', '#8C5A14' ),
		array( 'Hellenic Olive Farms', 'GR', 'agriculture', 'athens', '#5C7A2B' ),
		array( 'Aegean Hotel Services', 'GR', 'food-hospitality', 'athens', '#1E5F8C' ),
		array( 'Attica Build Partners', 'GR', 'civil-construction', 'athens', '#9A5A1F' ),
	);
}

/**
 * Destination label for a country code.
 *
 * @param string $cc Country code.
 * @return string
 */
function ma_demo_location( $cc ) {
	$map = array(
		'JP' => 'Japan (prefecture confirmed at offer stage)',
		'SA' => 'Saudi Arabia',
		'AE' => 'United Arab Emirates',
		'QA' => 'Qatar',
		'KW' => 'Kuwait',
		'GR' => 'Greece',
	);

	return isset( $map[ $cc ] ) ? $map[ $cc ] : 'Overseas';
}

/**
 * Role titles by sector.
 *
 * @return array
 */
function ma_demo_roles() {
	return array(
		'care-work'          => array( 'Care Worker (介護)', 'Elderly Support Assistant (介護)', 'Residential Care Aide (介護)', 'Day Service Care Worker (介護)' ),
		'civil-construction' => array( 'Formwork Carpenter (型枠施工)', 'Steel Fixer (鉄筋施工)', 'Scaffolder (とび)', 'Concrete Finisher (左官)', 'Site Labourer', 'Mason', 'Plasterer' ),
		'food-hospitality'   => array( 'Food Processing Operative (飲食料品製造業)', 'Kitchen Assistant (外食業)', 'Hotel Housekeeping Attendant (宿泊)', 'Bakery Production Operative', 'Catering Assistant' ),
		'cleaning-facility'  => array( 'Building Cleaning Operative (ビルクリーニング)', 'Facility Maintenance Assistant', 'Industrial Cleaner', 'Housekeeping Supervisor' ),
		'manufacturing'      => array( 'Machine Operator (工業製品製造業)', 'Assembly Line Operative', 'Quality Inspector', 'CNC Machine Operator', 'Metal Fabricator' ),
		'technical-trades'   => array( 'Automotive Service Technician (自動車整備)', 'Electrician', 'Industrial Welder', 'HVAC Technician', 'Plumber and Pipefitter' ),
		'marine-shipyard'    => array( 'Shipyard Welder (造船・舶用工業)', 'Hull Fitter (造船・舶用工業)', 'Marine Painter', 'Shipyard Rigger' ),
		'agriculture'        => array( 'Greenhouse Operative (農業)', 'Field Crop Worker (農業)', 'Livestock Assistant (農業)', 'Orchard Harvest Worker', 'Packhouse Operative' ),
		'logistics'          => array( 'Warehouse Operative (物流倉庫)', 'Forklift Operator', 'Order Picker and Packer', 'Goods Inward Assistant' ),
		'transportation'     => array( 'Heavy Vehicle Driver (自動車運送業)', 'Bus Driver', 'Light Delivery Driver', 'Tanker Driver' ),
		'health-sector'      => array( 'Staff Nurse', 'Senior Staff Nurse', 'Operating Theatre Nurse', 'Intensive Care Nurse' ),
	);
}

/**
 * Candidate names. Common Bangladeshi names, invented combinations.
 *
 * @return array
 */
function ma_demo_people() {
	return array(
		array( 'Mohammad Rafiqul Islam', 'm' ), array( 'Abdul Karim Sheikh', 'm' ),
		array( 'Shahidul Alam Mia', 'm' ), array( 'Jahangir Hossain', 'm' ),
		array( 'Nazmul Haque Patwary', 'm' ), array( 'Tanvir Ahmed Chowdhury', 'm' ),
		array( 'Mizanur Rahman', 'm' ), array( 'Shakil Mahmud', 'm' ),
		array( 'Rubel Mollah', 'm' ), array( 'Delwar Hossain Khan', 'm' ),
		array( 'Aminul Islam Sarker', 'm' ), array( 'Jashim Uddin', 'm' ),
		array( 'Monir Hossain Bhuiyan', 'm' ), array( 'Saiful Islam Talukder', 'm' ),
		array( 'Rakibul Hasan', 'm' ), array( 'Imran Kabir', 'm' ),
		array( 'Forhad Mia', 'm' ), array( 'Alamgir Hossain', 'm' ),
		array( 'Sumon Chandra Das', 'm' ), array( 'Babul Akter Gazi', 'm' ),
		array( 'Taslima Begum', 'f' ), array( 'Rahima Khatun', 'f' ),
		array( 'Shirin Sultana', 'f' ), array( 'Nasrin Akter', 'f' ),
		array( 'Morsheda Parvin', 'f' ), array( 'Rozina Begum', 'f' ),
		array( 'Jesmin Nahar', 'f' ), array( 'Salma Akter', 'f' ),
	);
}

/**
 * Trades a candidate might name, by sector.
 *
 * @return array
 */
function ma_demo_trades() {
	return array(
		'care-work'          => array( 'Caregiver', 'Nursing assistant', 'Elderly care' ),
		'civil-construction' => array( 'Mason', 'Steel fixer', 'Carpenter', 'Scaffolder', 'Painter' ),
		'food-hospitality'   => array( 'Cook', 'Kitchen helper', 'Baker', 'Housekeeping' ),
		'cleaning-facility'  => array( 'Cleaner', 'Facility maintenance', 'Janitor' ),
		'manufacturing'      => array( 'Machine operator', 'Garments operator', 'Quality checker' ),
		'technical-trades'   => array( 'Welder', 'Electrician', 'Auto mechanic', 'Plumber', 'AC technician' ),
		'marine-shipyard'    => array( 'Ship welder', 'Fitter', 'Marine painter' ),
		'agriculture'        => array( 'Farm worker', 'Greenhouse worker', 'Poultry worker' ),
		'logistics'          => array( 'Warehouse helper', 'Forklift operator', 'Loader' ),
		'transportation'     => array( 'Heavy vehicle driver', 'Light driver', 'Bus driver' ),
		'health-sector'      => array( 'Staff nurse', 'Senior staff nurse', 'Midwife' ),
	);
}

/* -------------------------------------------------------------------------
 * Logos
 * ---------------------------------------------------------------------- */

/**
 * Draw a lettermark and put it in the media library.
 *
 * Drawn rather than downloaded: a fictional employer has no logo to fetch,
 * and pulling a real company's mark onto a test record is exactly the thing
 * this tool must not do.
 *
 * @param string $name Employer name.
 * @param string $hex  Background colour.
 * @return int Attachment ID, or 0.
 */
function ma_demo_logo( $name, $hex ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return 0;
	}

	$size = 320;
	$img  = imagecreatetruecolor( $size, $size );

	list( $r, $g, $b ) = sscanf( $hex, '#%02x%02x%02x' );

	$bg = imagecolorallocate( $img, (int) $r, (int) $g, (int) $b );
	$fg = imagecolorallocate( $img, 255, 255, 255 );

	imagefilledrectangle( $img, 0, 0, $size, $size, $bg );

	// Initials from the first two words.
	$words = preg_split( '/\s+/', trim( $name ) );
	$mark  = strtoupper( substr( $words[0], 0, 1 ) . ( isset( $words[1] ) ? substr( $words[1], 0, 1 ) : '' ) );

	// The bundled bitmap font is small, so it is scaled up by drawing to a
	// tile and resampling. Crude, but it needs no font file on the server.
	$tile = imagecreatetruecolor( 40, 20 );
	$tbg  = imagecolorallocate( $tile, (int) $r, (int) $g, (int) $b );
	$tfg  = imagecolorallocate( $tile, 255, 255, 255 );

	imagefilledrectangle( $tile, 0, 0, 40, 20, $tbg );
	imagestring( $tile, 5, 8, 2, $mark, $tfg );
	imagecopyresampled( $img, $tile, 60, 100, 0, 0, 200, 110, 40, 20 );
	imagedestroy( $tile );

	// A rule under the mark, so it reads as a device rather than as text.
	imagefilledrectangle( $img, 110, 232, 210, 238, $fg );

	ob_start();
	imagepng( $img );
	$data = ob_get_clean();

	imagedestroy( $img );

	$file = wp_upload_bits( sanitize_title( $name ) . '-demo-logo.png', null, $data );

	if ( ! empty( $file['error'] ) ) {
		return 0;
	}

	$id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => $name . ' (demonstration mark)',
			'post_status'    => 'inherit',
		),
		$file['file']
	);

	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file['file'] ) );

	update_post_meta( $id, MA_DEMO_MARK, 1 );
	ma_demo_remember( 'attachments', $id );

	return (int) $id;
}

/* -------------------------------------------------------------------------
 * Builders
 * ---------------------------------------------------------------------- */

/**
 * The shared password for generated accounts.
 *
 * Deliberately not written into this file. These are real WordPress accounts
 * on a public hostname, and a known password committed to a plugin outlives
 * the afternoon it was useful for.
 *
 * @return string
 */
function ma_demo_password() {
	return defined( 'MA_DEMO_PASSWORD' ) ? (string) MA_DEMO_PASSWORD : '';
}

/**
 * A fake Bangladeshi mobile number, unique per index.
 *
 * The 017000000xx pattern is obviously not a real subscriber number, which is
 * the point: nobody should be able to ring one of these by accident.
 *
 * @param int $n Sequence.
 * @return string
 */
function ma_demo_phone( $n ) {
	return '+8801700' . str_pad( (string) $n, 6, '0', STR_PAD_LEFT );
}

/**
 * Create the employer records and their accounts.
 *
 * @return array Report.
 */
function ma_demo_build_employers() {
	global $wpdb;

	$password = ma_demo_password();

	if ( '' === $password ) {
		return array( 'error' => 'MA_DEMO_PASSWORD is not defined in wp-config.php. No accounts were created.' );
	}

	$table      = Install::table( 'employers' );
	$agency     = Install::current_agency_id();
	$report     = array( 'created' => 0, 'skipped' => 0, 'logos' => 0 );
	$states     = array( 'none', 'pending', 'attested', 'attested' );
	$agreements = array( 'none', 'pending', 'signed', 'signed' );

	mt_srand( 5150 );

	foreach ( ma_demo_employers() as $i => $spec ) {
		list( $name, $country, $sector_slug, $office, $hex ) = $spec;

		$email = 'emp.' . str_replace( '-', '', sanitize_title( $name ) ) . '@marwa.com';

		if ( get_user_by( 'email', $email ) ) {
			++$report['skipped'];
			continue;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => 'emp_' . sanitize_key( str_replace( '-', '', sanitize_title( $name ) ) ),
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $name,
				'first_name'   => $name,
				'role'         => Roles::EMPLOYER,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			continue;
		}

		update_user_meta( $user_id, MA_DEMO_MARK, 1 );
		update_user_meta( $user_id, Accounts::VERIFIED, 1 );
		update_user_meta( $user_id, '_ma_office', $office );
		ma_demo_remember( 'users', $user_id );

		$logo = ma_demo_logo( $name, $hex );

		if ( $logo ) {
			++$report['logos'];
			update_user_meta( $user_id, '_ma_logo', $logo );
		}

		$term      = get_term_by( 'slug', $sector_slug, Sectors::TAXONOMY );
		$sector_id = ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : null;

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'agency_id'     => $agency,
				'name'          => $name,
				'country'       => $country,
				'sector_id'     => $sector_id,
				'office'        => $office,
				'contact_name'  => 'Demonstration contact',
				'contact_email' => $email,
				'contact_phone' => '+880' . mt_rand( 1300000000, 1999999999 ),
				'city'          => 'Demonstration city',
				'registration_no' => 'REG-' . mt_rand( 100000, 999999 ),
				'website'       => '',
				'about'         => 'Demonstration employer record created by the build tool. This company does not exist.',
				'demand_ref'    => 'DL-' . gmdate( 'Y' ) . '-' . mt_rand( 1000, 9999 ),
				'demand_date'   => gmdate( 'Y-m-d', strtotime( '-' . mt_rand( 20, 300 ) . ' days' ) ),
				'attestation'   => $states[ array_rand( $states ) ],
				'agreement'     => $agreements[ array_rand( $agreements ) ],
				'name_public'   => 1,
				'notes'         => 'DEMONSTRATION RECORD — fictional employer created by the demo data build tool.',
				'status'        => 'active',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		$employer_id = (int) $wpdb->insert_id;

		ma_demo_remember( 'employers', $employer_id );
		update_user_meta( $user_id, '_ma_employer_id', $employer_id );

		++$report['created'];
	}

	return $report;
}

/**
 * Create the vacancies.
 *
 * @param int $count How many.
 * @return array Report.
 */
function ma_demo_build_jobs( $count = 100 ) {
	global $wpdb;

	$employers = $wpdb->get_results( 'SELECT * FROM ' . Install::table( 'employers' ) . " WHERE notes LIKE 'DEMONSTRATION RECORD%'" ); // phpcs:ignore WordPress.DB

	if ( ! $employers ) {
		return array( 'error' => 'Create the employers first.' );
	}

	$roles   = ma_demo_roles();
	$spec_by = array();

	foreach ( ma_demo_employers() as $spec ) {
		$spec_by[ $spec[0] ] = $spec;
	}

	$types = get_terms( array( 'taxonomy' => 'job_listing_type', 'hide_empty' => false ) );
	$types = is_wp_error( $types ) ? array() : $types;

	$report = array( 'created' => 0 );

	mt_srand( 20261001 );

	for ( $i = 0; $i < $count; $i++ ) {
		$employer = $employers[ $i % count( $employers ) ];
		$spec     = isset( $spec_by[ $employer->name ] ) ? $spec_by[ $employer->name ] : null;

		if ( ! $spec ) {
			continue;
		}

		$sector_slug = $spec[2];
		$titles      = isset( $roles[ $sector_slug ] ) ? $roles[ $sector_slug ] : array( 'General Operative' );
		$title       = $titles[ $i % count( $titles ) ];

		$destination = ma_demo_location( $employer->country );
		$full        = $title . ' — ' . preg_replace( '/\s*\(.*$/', '', $destination );

		$posted  = gmdate( 'Y-m-d H:i:s', strtotime( '-' . mt_rand( 0, 40 ) . ' days' ) );
		$expires = gmdate( 'Y-m-d', strtotime( '+' . mt_rand( 3, 95 ) . ' days' ) );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'job_listing',
				'post_status'  => 'publish',
				'post_title'   => $full,
				'post_date_gmt' => $posted,
				'post_date'    => get_date_from_gmt( $posted ),
				'post_content' => ma_demo_job_body( $title, $employer, $spec ),
			)
		);

		if ( is_wp_error( $post_id ) ) {
			continue;
		}

		update_post_meta( $post_id, MA_DEMO_MARK, 1 );
		update_post_meta( $post_id, '_company_name', $employer->name );
		update_post_meta( $post_id, '_job_location', $destination );
		update_post_meta( $post_id, '_application', get_option( 'admin_email' ) );
		update_post_meta( $post_id, '_filled', 0 );
		update_post_meta( $post_id, '_featured', ( 0 === $i % 13 ) ? 1 : 0 );
		update_post_meta( $post_id, '_job_expires', $expires );
		update_post_meta( $post_id, '_ma_office', $employer->office );
		update_post_meta( $post_id, '_ma_employer_id', (int) $employer->id );
		update_post_meta( $post_id, '_positions', mt_rand( 1, 25 ) );

		$logo = ma_demo_logo_for( $employer->name );

		if ( $logo ) {
			update_post_meta( $post_id, '_company_logo', $logo );
			set_post_thumbnail( $post_id, $logo );
		}

		$term = get_term_by( 'slug', $sector_slug, Sectors::TAXONOMY );

		if ( $term && ! is_wp_error( $term ) ) {
			wp_set_object_terms( $post_id, (int) $term->term_id, Sectors::TAXONOMY );
		}

		if ( $types ) {
			$type = $types[ $i % count( $types ) ];
			wp_set_object_terms( $post_id, (int) $type->term_id, 'job_listing_type' );
		}

		ma_demo_remember( 'posts', $post_id );
		++$report['created'];
	}

	return $report;
}

/**
 * Find the logo already made for an employer.
 *
 * @param string $name Employer name.
 * @return int
 */
function ma_demo_logo_for( $name ) {
	static $cache = array();

	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}

	$user = get_user_by( 'email', 'emp.' . str_replace( '-', '', sanitize_title( $name ) ) . '@marwa.com' );

	$cache[ $name ] = $user ? (int) get_user_meta( $user->ID, '_ma_logo', true ) : 0;

	return $cache[ $name ];
}

/**
 * Body text for a generated vacancy.
 *
 * The requirements are written the way a real overseas posting is written —
 * named exams, real contract shapes — because a layout tested against "Lorem
 * ipsum" tells you nothing about how it holds a posting full of exam names.
 *
 * @param string $title    Role title.
 * @param object $employer Employer row.
 * @param array  $spec     Employer spec.
 * @return string
 */
function ma_demo_job_body( $title, $employer, $spec ) {
	$country = $employer->country;

	$out  = '<p><strong>' . MA_DEMO_BANNER . '</strong></p>';
	$out .= '<h3>The work</h3>';
	$out .= '<p>' . esc_html( $employer->name ) . ' is recruiting for the role of ' . esc_html( $title ) . '. The posting covers a full-time position with accommodation and transport arranged by the employer. Working hours and overtime are set out in the contract issued at offer stage.</p>';

	$out .= '<h3>Requirements</h3><ul>';
	$out .= '<li>Age 18 to 40 at the time of deployment.</li>';
	$out .= '<li>Valid Bangladeshi passport with at least 18 months remaining.</li>';
	$out .= '<li>Medically fit, confirmed by the panel medical centre.</li>';

	if ( 'JP' === $country ) {
		$out .= '<li>JFT-Basic or JLPT N4 Japanese language certificate.</li>';
		$out .= '<li>Specified Skilled Worker skills evaluation test for this field.</li>';
		$out .= '<li>Candidates without the language certificate may apply; Marwa will advise on preparation.</li>';
	} elseif ( 'GR' === $country ) {
		$out .= '<li>Basic English or Greek. Seasonal and annual contracts both available.</li>';
		$out .= '<li>Work permit processed under the Bangladesh&#8211;Greece labour mobility arrangement.</li>';
	} else {
		$out .= '<li>Basic English sufficient for site instructions.</li>';
		$out .= '<li>Trade test at the Technical Training Centre where the employer requires one.</li>';
	}

	if ( 'health-sector' === $spec[2] ) {
		$out .= '<li>BNMC registration, Prometric examination and Dataflow verification.</li>';
	}

	$out .= '</ul>';

	$out .= '<h3>What Marwa handles</h3><ul>';
	$out .= '<li>Submission to the employer and interview scheduling.</li>';
	$out .= '<li>Medical, police clearance and BMET registration.</li>';
	$out .= '<li>Visa processing and pre-departure briefing.</li>';
	$out .= '</ul>';

	$out .= '<p><em>Marwa Overseas never charges a jobseeker to open an account or to apply.</em></p>';

	return $out;
}

/**
 * Create the candidate accounts and their files.
 *
 * Every generated candidate is created email-confirmed and with a complete
 * profile, because the point of the fixture is to see the portal as it looks
 * when it is working, not as it looks mid-signup.
 *
 * @param int $count How many.
 * @return array Report.
 */
function ma_demo_build_candidates( $count = 28 ) {
	global $wpdb;

	$password = ma_demo_password();

	if ( '' === $password ) {
		return array( 'error' => 'MA_DEMO_PASSWORD is not defined in wp-config.php. No accounts were created.' );
	}

	$people = array_slice( ma_demo_people(), 0, $count );
	$trades = ma_demo_trades();

	$districts = array();

	foreach ( Profile::districts() as $list ) {
		$districts = array_merge( $districts, array_values( $list ) );
	}

	$jp_levels = array_keys( Profile::jp_levels() );
	$en_levels = array_keys( Profile::en_levels() );
	$passports = array_keys( Profile::passport_states() );

	$sectors = array();

	foreach ( get_terms( array( 'taxonomy' => Sectors::TAXONOMY, 'hide_empty' => false ) ) as $t ) {
		if ( ! is_wp_error( $t ) ) {
			$sectors[ $t->slug ] = (int) $t->term_id;
		}
	}

	$table  = Install::table( 'candidates' );
	$agency = Install::current_agency_id();
	$report = array( 'created' => 0, 'skipped' => 0 );

	mt_srand( 777 );

	foreach ( $people as $i => $person ) {
		list( $name ) = $person;

		$slug  = str_replace( '-', '', sanitize_title( $name ) );
		$email = 'user.' . $slug . '@marwa.com';

		if ( get_user_by( 'email', $email ) ) {
			++$report['skipped'];
			continue;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => 'usr_' . sanitize_key( $slug ),
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $name,
				'role'         => Roles::CANDIDATE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			continue;
		}

		update_user_meta( $user_id, MA_DEMO_MARK, 1 );
		update_user_meta( $user_id, Accounts::VERIFIED, 1 );
		ma_demo_remember( 'users', $user_id );

		$sector_slugs = array_keys( $trades );
		$sector_slug  = $sector_slugs[ $i % count( $sector_slugs ) ];
		$trade_list   = $trades[ $sector_slug ];

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'agency_id'            => $agency,
				'user_id'              => $user_id,
				'full_name'            => $name,
				'full_name_bn'         => '',
				'phone'                => ma_demo_phone( $i + 1 ),
				'email'                => $email,
				'dob'                  => gmdate( 'Y-m-d', strtotime( '-' . mt_rand( 21, 44 ) . ' years -' . mt_rand( 0, 300 ) . ' days' ) ),
				'district'             => $districts[ array_rand( $districts ) ],
				'sector_id'            => isset( $sectors[ $sector_slug ] ) ? $sectors[ $sector_slug ] : null,
				'trade'                => $trade_list[ $i % count( $trade_list ) ],
				'years_experience'     => mt_rand( 0, 14 ),
				'passport_status'      => $passports[ array_rand( $passports ) ],
				'passport_no'          => 'BX' . mt_rand( 1000000, 9999999 ),
				'jp_language_level'    => $jp_levels[ array_rand( $jp_levels ) ],
				'en_language_level'    => $en_levels[ array_rand( $en_levels ) ],
				'status'               => 'new',
				'profile_completeness' => 0,
				'created_at'           => current_time( 'mysql' ),
				'updated_at'           => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		$candidate_id = (int) $wpdb->insert_id;

		if ( ! $candidate_id ) {
			continue;
		}

		ma_demo_remember( 'candidates', $candidate_id );

		ma_demo_build_experience( $candidate_id, $sector_slug, $trade_list );
		ma_demo_build_profile_sections( $candidate_id, $sector_slug, $trade_list );

		// Completeness is recomputed from the row rather than assumed, so the
		// fixture exercises the same code a real profile save does.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $candidate_id ) ); // phpcs:ignore WordPress.DB

		if ( $row ) {
			$wpdb->update( // phpcs:ignore WordPress.DB
				$table,
				array( 'profile_completeness' => (int) Profile::completeness( $row ) ),
				array( 'id' => $candidate_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		++$report['created'];
	}

	return $report;
}

/**
 * A short work history for one candidate.
 *
 * @param int    $candidate_id Candidate.
 * @param string $sector_slug  Sector.
 * @param array  $trades       Trade names.
 */
function ma_demo_build_experience( $candidate_id, $sector_slug, $trades ) {
	global $wpdb;

	$table     = Install::table( 'experience' );
	$countries = array( 'BD', 'BD', 'BD', 'SA', 'AE', 'QA', 'MY', 'OM' );
	$firms     = array( 'Local contractor', 'Private workshop', 'Family business', 'District garments unit', 'Overseas site contractor' );

	$rows = mt_rand( 1, 3 );
	$end  = strtotime( '-' . mt_rand( 1, 20 ) . ' months' );

	for ( $n = 0; $n < $rows; $n++ ) {
		$months = mt_rand( 8, 40 );
		$start  = strtotime( '-' . $months . ' months', $end );

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'candidate_id'  => $candidate_id,
				'employer_name' => $firms[ array_rand( $firms ) ],
				'country'       => $countries[ array_rand( $countries ) ],
				'job_role'      => $trades[ array_rand( $trades ) ],
				'from_date'     => gmdate( 'Y-m-01', $start ),
				'to_date'       => gmdate( 'Y-m-01', $end ),
				'description'   => 'Demonstration record created by the demo data build tool.',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $wpdb->insert_id ) {
			ma_demo_remember( 'experience', (int) $wpdb->insert_id );
		}

		$end = strtotime( '-' . mt_rand( 2, 10 ) . ' months', $start );
	}
}

/**
 * Education, training, certificates, skills and referees for one candidate.
 *
 * Without these the profile tabs are empty and the generated CV is a third of
 * the length it should be, which makes both impossible to judge.
 *
 * @param int    $candidate_id Candidate.
 * @param string $sector_slug  Sector.
 * @param array  $trades       Trade names.
 */
function ma_demo_build_profile_sections( $candidate_id, $sector_slug, $trades ) {
	global $wpdb;

	$levels = array_keys( Records::levels() );
	$skl    = array_keys( Records::skill_levels() );
	$exams  = Records::exams();

	$schools = array( 'Government High School', 'Adarsha High School', 'Technical School & College', 'Rangpur Polytechnic Institute', 'Model Madrasah' );
	$ttcs    = array( 'Technical Training Centre, Dhaka', 'Technical Training Centre, Rangpur', 'Technical Training Centre, Chattogram', 'BMET approved centre' );

	// -------------------------------------------------------- education
	$rows = mt_rand( 1, 2 );

	for ( $n = 0; $n < $rows; $n++ ) {
		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'education' ),
			array(
				'candidate_id' => $candidate_id,
				'level'        => $levels[ array_rand( $levels ) ],
				'institution'  => $schools[ array_rand( $schools ) ],
				'subject'      => mt_rand( 0, 1 ) ? 'Science' : 'Business Studies',
				'result'       => 'GPA ' . number_format( mt_rand( 250, 500 ) / 100, 2 ),
				'year_passed'  => mt_rand( 2006, 2022 ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $wpdb->insert_id ) {
			ma_demo_remember( 'education', (int) $wpdb->insert_id );
		}
	}

	// --------------------------------------------------------- training
	for ( $n = 0; $n < mt_rand( 1, 3 ); $n++ ) {
		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'training' ),
			array(
				'candidate_id' => $candidate_id,
				'title'        => $trades[ array_rand( $trades ) ] . ' course',
				'institution'  => $ttcs[ array_rand( $ttcs ) ],
				'duration'     => mt_rand( 1, 6 ) . ' months',
				'year_done'    => mt_rand( 2015, 2026 ),
				'location'     => mt_rand( 0, 1 ) ? 'Dhaka' : 'Rangpur',
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( $wpdb->insert_id ) {
			ma_demo_remember( 'training', (int) $wpdb->insert_id );
		}
	}

	// ----------------------------------------------------- certificates
	$picked = (array) array_rand( $exams, min( 3, count( $exams ) ) );

	foreach ( $picked as $k ) {
		$issued = strtotime( '-' . mt_rand( 2, 48 ) . ' months' );

		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'certificates' ),
			array(
				'candidate_id' => $candidate_id,
				'title'        => $exams[ $k ],
				'authority'    => 'Demonstration issuing body',
				'ref_no'       => 'DX' . mt_rand( 100000, 999999 ),
				'issued_on'    => gmdate( 'Y-m-01', $issued ),
				'expires_on'   => mt_rand( 0, 1 ) ? gmdate( 'Y-m-01', strtotime( '+3 years', $issued ) ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $wpdb->insert_id ) {
			ma_demo_remember( 'certificates', (int) $wpdb->insert_id );
		}
	}

	// ------------------------------------------------------------ skills
	foreach ( (array) array_rand( array_flip( $trades ), min( 3, count( $trades ) ) ) as $skill ) {
		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'skills' ),
			array(
				'candidate_id' => $candidate_id,
				'skill'        => $skill,
				'level'        => $skl[ array_rand( $skl ) ],
				'years'        => mt_rand( 1, 12 ),
			),
			array( '%d', '%s', '%s', '%d' )
		);

		if ( $wpdb->insert_id ) {
			ma_demo_remember( 'skills', (int) $wpdb->insert_id );
		}
	}

	// ---------------------------------------------------------- referees
	for ( $n = 0; $n < mt_rand( 1, 2 ); $n++ ) {
		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'referees' ),
			array(
				'candidate_id' => $candidate_id,
				'name'         => 'Demonstration referee ' . ( $n + 1 ),
				'relation'     => 'Previous supervisor',
				'organisation' => 'Demonstration employer',
				'phone'        => ma_demo_phone( 900 + $candidate_id + $n ),
				'email'        => 'ref' . $candidate_id . $n . '@marwa.com',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $wpdb->insert_id ) {
			ma_demo_remember( 'referees', (int) $wpdb->insert_id );
		}
	}

	Profile::recalculate( $candidate_id );
}

/**
 * Create applications across the generated candidates and vacancies.
 *
 * Stages are spread deliberately rather than randomly: a pipeline view is
 * only worth looking at if every column has something in it.
 *
 * @param int $count How many.
 * @return array Report.
 */
function ma_demo_build_applications( $count = 58 ) {
	global $wpdb;

	$candidates = ma_demo_index()['candidates'];
	$jobs       = ma_demo_index()['posts'];

	if ( ! $candidates || ! $jobs ) {
		return array( 'error' => 'Create the candidates and vacancies first.' );
	}

	$table  = Install::table( 'applications' );
	$log    = Install::table( 'status_log' );
	$agency = Install::current_agency_id();

	// Weighted so the early stages hold most of the volume, which is what a
	// real pipeline looks like.
	$stages = array(
		'new', 'new', 'new', 'new', 'new', 'new',
		'screened', 'screened', 'screened', 'screened',
		'shortlisted', 'shortlisted', 'shortlisted',
		'interview', 'interview',
		'selected', 'medical', 'visa', 'deployed',
		'rejected', 'rejected', 'withdrawn',
	);

	$report = array( 'created' => 0, 'duplicates' => 0 );
	$made   = array();

	mt_srand( 4242 );

	for ( $i = 0; $i < $count * 3 && $report['created'] < $count; $i++ ) {
		$candidate_id = $candidates[ array_rand( $candidates ) ];
		$job_id       = $jobs[ array_rand( $jobs ) ];
		$key          = $candidate_id . ':' . $job_id;

		if ( isset( $made[ $key ] ) ) {
			continue;
		}

		$made[ $key ] = true;

		$stage   = $stages[ array_rand( $stages ) ];
		$applied = gmdate( 'Y-m-d H:i:s', strtotime( '-' . mt_rand( 1, 60 ) . ' days' ) );
		$office  = (string) get_post_meta( $job_id, '_ma_office', true );

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'agency_id'    => $agency,
				'candidate_id' => $candidate_id,
				'job_id'       => $job_id,
				'status'       => $stage,
				'office'       => $office ? $office : 'dhaka',
				'source'       => 'self',
				'applied_at'   => $applied,
				'decided_at'   => in_array( $stage, array( 'rejected', 'deployed', 'withdrawn' ), true ) ? current_time( 'mysql' ) : null,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			++$report['duplicates'];
			continue;
		}

		$application_id = (int) $wpdb->insert_id;
		ma_demo_remember( 'applications', $application_id );

		// Walk the file through the stages it must have passed to get here,
		// so the candidate's history reads as a sequence rather than a jump.
		$order = array( 'new', 'screened', 'shortlisted', 'interview', 'selected', 'medical', 'visa', 'deployed' );
		$upto  = array_search( $stage, $order, true );
		$path  = ( false === $upto ) ? array( 'new', $stage ) : array_slice( $order, 0, $upto + 1 );
		$when  = strtotime( $applied );
		$from  = null;

		foreach ( $path as $step ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB
				$log,
				array(
					'entity_type' => 'application',
					'entity_id'   => $application_id,
					'from_status' => $from,
					'to_status'   => $step,
					'user_id'     => get_current_user_id(),
					'note'        => 'Demonstration record.',
					'created_at'  => gmdate( 'Y-m-d H:i:s', $when ),
				),
				array( '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
			);

			$from  = $step;
			$when += mt_rand( 1, 6 ) * DAY_IN_SECONDS;
		}

		++$report['created'];
	}

	return $report;
}

/**
 * Fill the Guides section so the index and the menu can be judged at volume.
 *
 * These are generated from a template and say so in their first line. They are
 * there to test pagination, the card grid and the menu — not to be read, and
 * absolutely not to be left published. The four real guides the plugin ships
 * are untouched by this and by the removal.
 *
 * @param int $count How many.
 * @return array Report.
 */
function ma_demo_build_guides( $count = 100 ) {
	if ( ! class_exists( __NAMESPACE__ . '\\Guides' ) ) {
		return array( 'error' => 'The Marwa Apply plugin must be active and at version 0.18 or later.' );
	}

	$topics = array(
		'What to pack for %s',
		'Cost of living in %s, explained simply',
		'Your first week in %s',
		'Sending money home from %s',
		'Weather and clothing in %s',
		'Food and cooking in %s',
		'Getting a local SIM in %s',
		'Public transport in %s',
		'Rest days and what people do in %s',
		'Keeping in touch with family from %s',
	);

	$places = array( 'Japan', 'Saudi Arabia', 'the United Arab Emirates', 'Qatar', 'Kuwait', 'Greece', 'Romania', 'Oman', 'Malaysia', 'Singapore' );

	$report = array( 'created' => 0, 'skipped' => 0 );

	mt_srand( 9090 );

	for ( $i = 0; $i < $count; $i++ ) {
		$title = sprintf( $topics[ $i % count( $topics ) ], $places[ (int) floor( $i / count( $topics ) ) % count( $places ) ] );
		$title = $title . ' (' . ( $i + 1 ) . ')';
		$slug  = 'demo-' . sanitize_title( $title );

		if ( get_page_by_path( $slug, OBJECT, Guides::CPT ) ) {
			++$report['skipped'];
			continue;
		}

		$body  = '<p><strong>DEMONSTRATION ARTICLE — placeholder text for layout testing. This is not advice and must be deleted before launch.</strong></p>';
		$body .= '<p>' . esc_html( $title ) . ' would be explained here, in the same plain register the real guides use: short paragraphs, no jargon, and nothing a reader has to take on trust.</p>';
		$body .= '<h2>What this would cover</h2><ul><li>The practical question somebody actually arrives with.</li><li>What it costs, where a cost is involved.</li><li>What Marwa handles and what the worker handles.</li></ul>';
		$body .= '<p>Written out, a guide of this kind runs to six or seven hundred words — long enough to test how the page holds a real article.</p>';

		$id = wp_insert_post(
			array(
				'post_type'    => Guides::CPT,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_excerpt' => 'Demonstration article for layout testing — not advice.',
				'post_content' => $body,
				'menu_order'   => 50 + $i,
				'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-' . mt_rand( 1, 400 ) . ' days' ) ),
			)
		);

		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}

		update_post_meta( $id, MA_DEMO_MARK, 1 );
		ma_demo_remember( 'posts', $id );

		++$report['created'];
	}

	return $report;
}

/* -------------------------------------------------------------------------
 * Removal
 * ---------------------------------------------------------------------- */

/**
 * Remove everything this tool created, and nothing else.
 *
 * Driven entirely by the index rather than by pattern matching on names: a
 * real candidate who happens to be called Rahima Khatun must survive this.
 *
 * @return array Report.
 */
function ma_demo_remove_all() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/user.php';

	$index  = ma_demo_index();
	$report = array();

	foreach ( array(
		'applications' => Install::table( 'applications' ),
		'experience'   => Install::table( 'experience' ),
		'education'    => Install::table( 'education' ),
		'training'     => Install::table( 'training' ),
		'certificates' => Install::table( 'certificates' ),
		'skills'       => Install::table( 'skills' ),
		'referees'     => Install::table( 'referees' ),
		'candidates'   => Install::table( 'candidates' ),
		'employers'    => Install::table( 'employers' ),
	) as $bucket => $table ) {
		$ids = array_map( 'intval', $index[ $bucket ] );

		if ( ! $ids ) {
			$report[ $bucket ] = 0;
			continue;
		}

		if ( 'applications' === $bucket ) {
			$in = implode( ',', $ids );
			$wpdb->query( "DELETE FROM " . Install::table( 'status_log' ) . " WHERE entity_type = 'application' AND entity_id IN ({$in})" ); // phpcs:ignore WordPress.DB
		}

		$in                = implode( ',', $ids );
		$report[ $bucket ] = (int) $wpdb->query( "DELETE FROM {$table} WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB
	}

	$report['posts'] = 0;

	foreach ( array_map( 'intval', $index['posts'] ) as $id ) {
		if ( wp_delete_post( $id, true ) ) {
			++$report['posts'];
		}
	}

	$report['attachments'] = 0;

	foreach ( array_map( 'intval', $index['attachments'] ) as $id ) {
		if ( wp_delete_attachment( $id, true ) ) {
			++$report['attachments'];
		}
	}

	$report['users'] = 0;

	foreach ( array_map( 'intval', $index['users'] ) as $id ) {
		if ( $id === get_current_user_id() ) {
			continue;
		}

		if ( wp_delete_user( $id ) ) {
			++$report['users'];
		}
	}

	// ---------------------------------------------------------------- sweep
	// The index is the primary record, but a run cut short by a timeout can
	// write a row and die before recording it. So anything still hanging off
	// a candidate id this tool created is removed too, and whatever survives
	// that is reported rather than left silently behind.
	$orphans = 0;

	foreach ( array_map( 'intval', $index['candidates'] ) as $cid ) {
		foreach ( array( 'experience', 'education', 'training', 'certificates', 'skills', 'referees', 'documents' ) as $child ) {
			$table    = Install::table( $child );
			$orphans += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE candidate_id = %d", $cid ) ); // phpcs:ignore WordPress.DB
		}
	}

	$report['orphans_swept'] = $orphans;

	// Posts and users carry a marker of their own, so a second pass can find
	// anything created before an index entry was written.
	$stragglers = get_posts(
		array(
			'post_type'      => array( 'job_listing', Guides::CPT, 'attachment' ),
			'post_status'    => 'any',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'meta_key'       => MA_DEMO_MARK, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	foreach ( $stragglers as $id ) {
		wp_delete_post( (int) $id, true );
	}

	$report['posts'] += count( $stragglers );

	$marked = get_users(
		array(
			'meta_key'   => MA_DEMO_MARK, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'     => 'ID',
			'number'     => 500,
		)
	);

	foreach ( $marked as $id ) {
		if ( (int) $id !== get_current_user_id() ) {
			wp_delete_user( (int) $id );
			++$report['users'];
		}
	}

	delete_option( MA_DEMO_INDEX );

	$report['remaining'] = ma_demo_audit();

	return $report;
}

/**
 * What, if anything, is still here.
 *
 * Printed after a removal so the result is checkable rather than taken on
 * trust. Every number should be zero.
 *
 * @return array
 */
function ma_demo_audit() {
	global $wpdb;

	$out = array();

	$out['marked_posts'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", MA_DEMO_MARK ) ); // phpcs:ignore WordPress.DB
	$out['marked_users'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", MA_DEMO_MARK ) ); // phpcs:ignore WordPress.DB

	$employers            = Install::table( 'employers' );
	$out['demo_employers'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$employers} WHERE notes LIKE 'DEMONSTRATION RECORD%'" ); // phpcs:ignore WordPress.DB

	foreach ( array( 'candidates', 'applications', 'experience', 'education', 'training', 'certificates', 'skills', 'referees' ) as $t ) {
		$table         = Install::table( $t );
		$out[ $t ]     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
	}

	return $out;
}

/* -------------------------------------------------------------------------
 * Admin screen
 * ---------------------------------------------------------------------- */

/**
 * Is the old standalone build-tool plugin still active?
 *
 * This file used to ship as its own plugin. Its functions were global and
 * these are namespaced, so having both installed does not fatal — it just
 * quietly puts the same menu entry in twice, which looks like a bug and is
 * one. The copy inside the plugin stands aside until the old one is gone.
 *
 * @return bool
 */
function ma_demo_standalone_present() {
	return function_exists( '\\ma_demo_index' );
}

add_action(
	'admin_menu',
	function () {
		if ( ma_demo_standalone_present() ) {
			return;
		}

		add_submenu_page(
			'marwa-apply',
			__( 'Demo data', 'marwa-apply' ),
			__( 'Demo data', 'marwa-apply' ),
			'manage_options',
			'marwa-demo-data',
			__NAMESPACE__ . '\\ma_demo_screen'
		);
	},
	20
);

// These callbacks live in this file's namespace, so they must be passed
// fully qualified. An unqualified string is looked up in the global
// namespace, where they do not exist, and WordPress fatals on the next
// admin page load.
add_action( 'admin_init', __NAMESPACE__ . '\\ma_demo_handle' );

/**
 * Run whichever button was pressed.
 */
function ma_demo_handle() {
	if ( empty( $_POST['ma_demo_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Let the standalone plugin own the whole flow while it is still there,
	// so a submitted form is never handled twice.
	if ( ma_demo_standalone_present() ) {
		return;
	}

	check_admin_referer( 'ma_demo' );

	if ( ! class_exists( __NAMESPACE__ . '\\Install' ) ) {
		set_transient( 'ma_demo_report', array( 'error' => 'The Marwa Apply plugin must be active.' ), 120 );
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['ma_demo_action'] ) );

	ma_demo_quiet();

	switch ( $action ) {
		case 'employers':
			$report = ma_demo_build_employers();
			break;

		case 'jobs':
			$report = ma_demo_build_jobs( 100 );
			break;

		case 'candidates':
			$report = ma_demo_build_candidates( 28 );
			break;

		case 'applications':
			$report = ma_demo_build_applications( 58 );
			break;

		case 'guides':
			$report = ma_demo_build_guides( 100 );
			break;

		case 'everything':
			$report = array(
				'employers'    => ma_demo_build_employers(),
				'jobs'         => ma_demo_build_jobs( 100 ),
				'candidates'   => ma_demo_build_candidates( 28 ),
				'applications' => ma_demo_build_applications( 58 ),
				'guides'       => ma_demo_build_guides( 100 ),
			);
			break;

		case 'remove':
			$report = ma_demo_remove_all();
			break;

		default:
			return;
	}

	set_transient( 'ma_demo_report', $report, 300 );

	wp_safe_redirect( admin_url( 'admin.php?page=marwa-demo-data' ) );
	exit;
}

/**
 * The page.
 */
function ma_demo_screen() {
	$index = ma_demo_index();
	$have  = array_sum( array_map( 'count', $index ) );

	echo '<div class="wrap"><h1>' . esc_html__( 'Demo data', 'marwa-apply' ) . '</h1>';

	if ( ma_demo_standalone_present() ) {
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Two copies of this tool are installed.', 'marwa-apply' ) . '</strong> ';
		echo esc_html__( 'The separate "Marwa Apply — demo data" plugin is still active and is handling this screen. Deactivate and delete that plugin; this one carries the same tool.', 'marwa-apply' ) . '</p></div>';
	}

	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'This is a build tool.', 'marwa-apply' ) . '</strong> ';
	echo esc_html__( 'Everything it creates is fictional and is marked as a demonstration record. Remove the data and delete this plugin before the site goes public.', 'marwa-apply' ) . '</p></div>';

	$report = get_transient( 'ma_demo_report' );

	if ( $report ) {
		delete_transient( 'ma_demo_report' );

		printf(
			'<div class="notice notice-%s"><p><strong>Result</strong></p><pre style="white-space:pre-wrap;margin:0">%s</pre></div>',
			isset( $report['error'] ) ? 'error' : 'success',
			esc_html( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) )
		);
	}

	if ( ! defined( 'MA_DEMO_PASSWORD' ) ) {
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Set a password first.', 'marwa-apply' ) . '</strong> ';
		echo esc_html__( 'Add this line to wp-config.php, above the "stop editing" comment, then reload:', 'marwa-apply' ) . '</p>';
		echo '<p><code>define( \'MA_DEMO_PASSWORD\', \'your-throwaway-password\' );</code></p>';
		echo '<p>' . esc_html__( 'The password is read from there rather than stored in this plugin, so it is not sitting in a file on the server after the fixture is gone.', 'marwa-apply' ) . '</p></div>';
	}

	echo '<table class="widefat striped" style="max-width:560px;margin:18px 0"><tbody>';

	foreach ( array(
		'employers'    => __( 'Employer records', 'marwa-apply' ),
		'posts'        => __( 'Vacancies and articles', 'marwa-apply' ),
		'candidates'   => __( 'Candidate files', 'marwa-apply' ),
		'experience'   => __( 'Work history rows', 'marwa-apply' ),
		'applications' => __( 'Applications', 'marwa-apply' ),
		'users'        => __( 'Accounts', 'marwa-apply' ),
		'attachments'  => __( 'Logo images', 'marwa-apply' ),
	) as $key => $label ) {
		printf( '<tr><td>%s</td><td><strong>%d</strong></td></tr>', esc_html( $label ), count( $index[ $key ] ) );
	}

	echo '</tbody></table>';

	// Sectors and job types are the plugin's own vocabulary, not demo data.
	// Offering a one-click delete for them would be a trap: real vacancies
	// are filed against those terms, and removing them orphans the lot.
	$sectors = get_terms( array( 'taxonomy' => Sectors::TAXONOMY, 'hide_empty' => false ) );
	$types   = get_terms( array( 'taxonomy' => 'job_listing_type', 'hide_empty' => false ) );

	echo '<h2>' . esc_html__( 'Sectors and job categories', 'marwa-apply' ) . '</h2>';
	echo '<p>' . esc_html(
		sprintf(
			/* translators: 1: sector count 2: job type count */
			__( '%1$d sectors and %2$d job categories exist. These are NOT demo data — they are the vocabulary the plugin ships with, and real vacancies are filed against them.', 'marwa-apply' ),
			is_wp_error( $sectors ) ? 0 : count( $sectors ),
			is_wp_error( $types ) ? 0 : count( $types )
		)
	) . '</p>';
	echo '<p class="description">' . esc_html__( 'Removing the demo data deliberately leaves them alone. Deleting a sector would orphan every genuine vacancy filed under it, so there is no one-click button for it here — remove individual terms from the Sectors screen if you really mean to.', 'marwa-apply' ) . '</p>';

	echo '<form method="post">';
	wp_nonce_field( 'ma_demo' );

	echo '<p>';
	echo '<button class="button button-primary" name="ma_demo_action" value="everything">' . esc_html__( 'Create everything', 'marwa-apply' ) . '</button> ';
	echo '</p><p>';
	echo '<button class="button" name="ma_demo_action" value="employers">' . esc_html__( '1. Employers and logos', 'marwa-apply' ) . '</button> ';
	echo '<button class="button" name="ma_demo_action" value="jobs">' . esc_html__( '2. 100 vacancies', 'marwa-apply' ) . '</button> ';
	echo '<button class="button" name="ma_demo_action" value="candidates">' . esc_html__( '3. 28 candidates', 'marwa-apply' ) . '</button> ';
	echo '<button class="button" name="ma_demo_action" value="applications">' . esc_html__( '4. 58 applications', 'marwa-apply' ) . '</button> ';
	echo '<button class="button" name="ma_demo_action" value="guides">' . esc_html__( '5. 100 articles', 'marwa-apply' ) . '</button>';
	echo '</p>';

	if ( $have ) {
		echo '<hr><p><button class="button button-link-delete" name="ma_demo_action" value="remove">' . esc_html__( 'Remove everything this tool created', 'marwa-apply' ) . '</button>';
		echo '<br><span class="description">' . esc_html__( 'Driven by an index of exactly what was added, so anything a real candidate created afterwards is untouched.', 'marwa-apply' ) . '</span></p>';
	}

	echo '</form>';

	echo '<p class="description">' . esc_html__( 'Every step skips what it has already made, so if a run is cut short by a timeout, press the same button again and it carries on from where it stopped.', 'marwa-apply' ) . '</p>';

	echo '<h2>' . esc_html__( 'Signing in as a generated account', 'marwa-apply' ) . '</h2>';
	echo '<p class="description">' . esc_html__( 'After a removal the result includes a "remaining" count for every table. Every number in it should be zero; anything else means something was left behind and is worth telling the developer about.', 'marwa-apply' ) . '</p>';
	echo '<p>' . esc_html__( 'Employers use emp.<name>@marwa.com and candidates use user.<name>@marwa.com, both with the password you defined. The addresses do not receive mail, so every generated account is created already confirmed — the emailed code is skipped.', 'marwa-apply' ) . '</p>';

	echo '</div>';
}
