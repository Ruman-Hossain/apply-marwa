<?php
/**
 * Plugin Name: Marwa Apply — sample jobs (one-shot)
 * Description: Seeds a handful of clearly-marked sample job posts so the listing and detail layouts can be judged with real-shaped content. Build tool — remove the samples before launch.
 * Version:     0.1.0
 * Author:      Md Ruman Hossain
 *
 * Two rules shaped what is in here.
 *
 * First, the sector names, Japanese field names and qualification
 * requirements are real. A layout tested with "Job Title One / Lorem ipsum"
 * tells you nothing, because real postings are long, awkwardly worded and
 * full of exam names.
 *
 * Second, nothing here pretends to be an open vacancy. Every post carries a
 * sample banner and a placeholder employer. A jobseeker who finds one of
 * these through search must not be able to mistake it for work that exists.
 *
 * Usage, as an administrator:
 *   /wp-admin/?ma_seed_jobs=1       create (safe to re-run)
 *   /wp-admin/?ma_seed_jobs=delete  remove every post this tool created
 *
 * @package MarwaApplyTheme
 */

defined( 'ABSPATH' ) || exit;

const MA_SAMPLE_FLAG = '_ma_sample_job';

add_action( 'admin_init', 'ma_seed_jobs_maybe' );

/**
 * Entry point.
 */
function ma_seed_jobs_maybe() {
	$mode = isset( $_GET['ma_seed_jobs'] ) ? sanitize_text_field( wp_unslash( $_GET['ma_seed_jobs'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	if ( '' === $mode || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	set_transient( 'ma_seed_report', 'delete' === $mode ? ma_seed_jobs_delete() : ma_seed_jobs_run(), 300 );
}

add_action(
	'admin_notices',
	function () {
		$report = get_transient( 'ma_seed_report' );

		if ( ! $report ) {
			return;
		}

		delete_transient( 'ma_seed_report' );

		printf(
			'<div class="notice notice-%s"><p><strong>Marwa Apply sample jobs</strong></p><pre style="white-space:pre-wrap;margin:0">%s</pre></div>',
			esc_attr( empty( $report['errors'] ) ? 'success' : 'error' ),
			esc_html( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) )
		);
	}
);

/**
 * The sample set.
 *
 * Chosen to cover the shapes the layout has to survive: a long Japanese field
 * name, a sector with no Japanese route at all, a role with three separate
 * exam requirements, and a short simple one.
 *
 * @return array
 */
function ma_seed_jobs_data() {
	return array(
		array(
			'key'      => 'care-worker-jp',
			'title'    => 'Care Worker (介護) — Japan, Specified Skilled Worker',
			'sector'   => 'care-work',
			'location' => 'Japan (prefecture confirmed at offer stage)',
			'type'     => 'Full Time',
			'summary'  => 'Personal care and daily living support in a residential elderly care facility under the Specified Skilled Worker (特定技能) route.',
			'duties'   => array(
				'Assistance with bathing, meals, mobility and toileting',
				'Recording daily condition notes for the nursing team',
				'Recreation and communication activities with residents',
				'Working to the facility\'s care plan under a Japanese supervisor',
			),
			'require'  => array(
				'Care Skills Evaluation Test (介護技能評価試験) — pass required',
				'Care Japanese Language Evaluation Test (介護日本語評価試験) — pass required',
				'JFT-Basic A2 or JLPT N4 — pass required',
				'Age 18 or above, medically fit, no travel restrictions',
			),
			'notes'    => 'Care work is one of the nineteen designated Specified Skilled Worker fields. Since April 2025 the route also covers home-visit care (訪問介護) under additional conditions. Facilities are subject to a cap on the number of Specified Skilled Worker care staff they may employ relative to their Japanese staff.',
		),
		array(
			'key'      => 'formwork-jp',
			'title'    => 'Formwork Carpenter (型枠施工) — Japan, Construction',
			'sector'   => 'civil-construction',
			'location' => 'Japan (prefecture confirmed at offer stage)',
			'type'     => 'Full Time',
			'summary'  => 'Formwork assembly and stripping on reinforced concrete building sites, under the Construction (建設) Specified Skilled Worker field.',
			'duties'   => array(
				'Setting out, assembling and stripping formwork to drawings',
				'Working safely at height with the site\'s fall-protection system',
				'Daily tool and material checks',
			),
			'require'  => array(
				'Construction Skills Assessment Test in the relevant work category — pass required',
				'JFT-Basic A2 or JLPT N4 — pass required',
				'Verifiable formwork or general construction experience',
			),
			'notes'    => 'Construction employers in Japan must hold an accepted employment plan before a Specified Skilled Worker can start. Marwa handles the Bangladesh-side documentation and BMET clearance.',
		),
		array(
			'key'      => 'food-processing-jp',
			'title'    => 'Food Processing Operative (飲食料品製造業) — Japan',
			'sector'   => 'food-hospitality',
			'location' => 'Japan (prefecture confirmed at offer stage)',
			'type'     => 'Full Time',
			'summary'  => 'Production line work in a food manufacturing plant — preparation, processing, packing and hygiene control.',
			'duties'   => array(
				'Line work to a fixed cycle time',
				'Hygiene and allergen controls to the plant\'s HACCP procedures',
				'Weighing, packing and labelling',
				'Cleaning down at shift end',
			),
			'require'  => array(
				'Food Industry Skills Assessment Test — pass required',
				'JFT-Basic A2 or JLPT N4 — pass required',
				'Able to work rotating shifts and stand for a full shift',
			),
			'notes'    => 'This is one of the larger Specified Skilled Worker fields by intake and does not require prior sector experience, only the assessment test.',
		),
		array(
			'key'      => 'building-cleaning-jp',
			'title'    => 'Building Cleaning Operative (ビルクリーニング) — Japan',
			'sector'   => 'cleaning-facility',
			'location' => 'Japan (prefecture confirmed at offer stage)',
			'type'     => 'Full Time',
			'summary'  => 'Interior cleaning of offices, hotels and commercial buildings to a set schedule.',
			'duties'   => array(
				'Floor, washroom and common-area cleaning to a daily schedule',
				'Safe handling of cleaning chemicals and machinery',
				'Reporting damage and stock shortages',
			),
			'require'  => array(
				'Building Cleaning Management Skills Assessment Test — pass required',
				'JFT-Basic A2 or JLPT N4 — pass required',
			),
			'notes'    => 'A common first placement: the language bar is the same as other fields but the technical assessment is among the more approachable ones.',
		),
		array(
			'key'      => 'staff-nurse-gulf',
			'title'    => 'Staff Nurse — Gulf (Saudi Arabia)',
			'sector'   => 'health-sector',
			'location' => 'Saudi Arabia',
			'type'     => 'Full Time',
			'summary'  => 'Ward-based nursing in a Saudi hospital under a government-to-government recruitment arrangement.',
			'duties'   => array(
				'Patient assessment, medication administration and documentation',
				'Working to the hospital\'s nursing protocols under a charge nurse',
				'Shift rotation including nights',
			),
			'require'  => array(
				'Registered nurse qualification with BNMC registration',
				'Minimum three years\' post-registration hospital experience',
				'Working English — this is the clinical language on the ward',
				'Saudi Prometric examination — pass required',
				'Dataflow primary source verification of qualifications',
			),
			'notes'    => 'Nursing is a Gulf route, not a Japanese one. There is no Specified Skilled Worker field for nursing, and the EPA nursing route into Japan is open only to Indonesia, the Philippines and Vietnam — Bangladesh is not party to it. Recruitment of Bangladeshi nurses to Saudi Arabia follows the 2022 bilateral accord, with the first arrivals in late 2023.',
		),
	);
}

/**
 * Build the post body.
 *
 * @param array $job One row from the sample set.
 * @return string
 */
function ma_seed_jobs_body( $job ) {
	$li = static function ( $items ) {
		return '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li></ul>';
	};

	$out  = '<p><strong>SAMPLE POST — layout and testing only. This is not an open vacancy and no application will be processed.</strong></p>';
	$out .= '<p>' . esc_html( $job['summary'] ) . '</p>';
	$out .= '<h3>What the work involves</h3>' . $li( $job['duties'] );
	$out .= '<h3>Requirements</h3>' . $li( $job['require'] );
	$out .= '<h3>Pay and contract</h3><p>Confirmed by the employer at offer stage. Marwa does not publish a figure before an employer has issued one.</p>';
	$out .= '<h3>Background</h3><p>' . esc_html( $job['notes'] ) . '</p>';

	return $out;
}

/**
 * Create the samples. Safe to run repeatedly.
 *
 * @return array
 */
function ma_seed_jobs_run() {
	$report = array( 'errors' => array(), 'created' => array(), 'skipped' => array() );

	if ( ! post_type_exists( 'job_listing' ) ) {
		$report['errors'][] = 'WP Job Manager is not active — job_listing post type missing';

		return $report;
	}

	foreach ( ma_seed_jobs_data() as $job ) {
		$existing = get_posts(
			array(
				'post_type'      => 'job_listing',
				'post_status'    => 'any',
				'meta_key'       => MA_SAMPLE_FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $job['key'],    // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);

		if ( $existing ) {
			$report['skipped'][] = $job['key'];
			continue;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'job_listing',
				'post_status'  => 'publish',
				'post_title'   => $job['title'],
				'post_content' => ma_seed_jobs_body( $job ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$report['errors'][] = $job['key'] . ': ' . $post_id->get_error_message();
			continue;
		}

		update_post_meta( $post_id, MA_SAMPLE_FLAG, $job['key'] );
		update_post_meta( $post_id, '_company_name', 'Sample employer — not a real company' );
		update_post_meta( $post_id, '_job_location', $job['location'] );
		update_post_meta( $post_id, '_application', get_option( 'admin_email' ) );
		update_post_meta( $post_id, '_filled', 0 );
		update_post_meta( $post_id, '_featured', 0 );
		update_post_meta( $post_id, '_job_expires', gmdate( 'Y-m-d', strtotime( '+90 days' ) ) );

		if ( taxonomy_exists( 'job_listing_type' ) ) {
			wp_set_object_terms( $post_id, $job['type'], 'job_listing_type' );
		}

		if ( taxonomy_exists( 'ma_sector' ) ) {
			$term = get_term_by( 'slug', $job['sector'], 'ma_sector' );

			if ( $term ) {
				wp_set_object_terms( $post_id, array( (int) $term->term_id ), 'ma_sector' );
			} else {
				$report['errors'][] = $job['key'] . ': sector term ' . $job['sector'] . ' not found';
			}
		}

		$report['created'][ $job['key'] ] = $post_id;
	}

	return $report;
}

/**
 * Remove every post this tool created.
 *
 * Samples must be easy to get rid of, or they end up live.
 *
 * @return array
 */
function ma_seed_jobs_delete() {
	$ids = get_posts(
		array(
			'post_type'      => 'job_listing',
			'post_status'    => 'any',
			'meta_key'       => MA_SAMPLE_FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'         => 'ids',
			'posts_per_page' => -1,
		)
	);

	foreach ( $ids as $id ) {
		wp_trash_post( $id );
	}

	return array( 'errors' => array(), 'trashed' => $ids );
}
