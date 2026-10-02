<?php
/**
 * The vacancy record: what a posting declares about itself.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Everything a job post carries beyond its title and body.
 *
 * WP Job Manager gives a posting a company, a location and an expiry date.
 * That is enough for office hiring in one country. An overseas placement turns
 * on things it has no field for: which exams the destination requires, what
 * the contract length is, who pays for the air ticket, whether a passport must
 * already be in hand.
 *
 * Declaring those on the posting does two jobs at once. It forces staff to
 * write a complete vacancy instead of a paragraph of prose, and it makes the
 * free eligibility checklist possible — a posting that states what it needs
 * can be compared against a profile that states what it has.
 */
final class Vacancy {

	/**
	 * Where a vacancy is in its life.
	 *
	 * WP Job Manager has published or expired, which is too coarse for a
	 * pipeline that runs for months: a vacancy closed to new applicants while
	 * medicals finish is neither.
	 *
	 * @return array
	 */
	public static function states() {
		return array(
			'draft'    => __( 'Draft — not visible', 'marwa-apply' ),
			'open'     => __( 'Open — accepting applications', 'marwa-apply' ),
			'closed'   => __( 'Closed to new applications', 'marwa-apply' ),
			'filled'   => __( 'Filled', 'marwa-apply' ),
			'archived' => __( 'Archived', 'marwa-apply' ),
		);
	}

	/**
	 * What an overseas employer provides.
	 *
	 * Nothing from their list survived. Provident fund, festival bonus, mobile
	 * bill and credit card are terms for an office job in Dhaka. What decides
	 * whether an overseas contract is worth taking is who pays for the room,
	 * the food, the flight and the recruitment itself.
	 *
	 * @return array
	 */
	public static function benefits() {
		return array(
			'accommodation'   => __( 'Accommodation provided', 'marwa-apply' ),
			'accom_shared'    => __( 'Shared accommodation', 'marwa-apply' ),
			'food'            => __( 'Food provided', 'marwa-apply' ),
			'food_allowance'  => __( 'Food allowance', 'marwa-apply' ),
			'transport'       => __( 'Transport to the workplace', 'marwa-apply' ),
			'ticket_in'       => __( 'Air ticket out paid by employer', 'marwa-apply' ),
			'ticket_return'   => __( 'Return ticket paid by employer', 'marwa-apply' ),
			'ticket_annual'   => __( 'Annual home leave ticket', 'marwa-apply' ),
			'overtime'        => __( 'Overtime paid at the statutory rate', 'marwa-apply' ),
			'medical_ins'     => __( 'Medical insurance', 'marwa-apply' ),
			'accident_ins'    => __( 'Accident insurance', 'marwa-apply' ),
			'annual_leave'    => __( 'Paid annual leave', 'marwa-apply' ),
			'end_of_service'  => __( 'End-of-service benefit', 'marwa-apply' ),
			'uniform'         => __( 'Uniform and safety equipment', 'marwa-apply' ),
			'visa_paid'       => __( 'Visa and work permit paid by employer', 'marwa-apply' ),
			'recruit_paid'    => __( 'Recruitment costs borne by the employer', 'marwa-apply' ),
			'language_help'   => __( 'Language training provided', 'marwa-apply' ),
		);
	}

	/**
	 * Currencies overseas pay is actually quoted in.
	 *
	 * @return array
	 */
	public static function currencies() {
		return array(
			'JPY' => __( 'Japanese yen (¥)', 'marwa-apply' ),
			'SAR' => __( 'Saudi riyal', 'marwa-apply' ),
			'AED' => __( 'UAE dirham', 'marwa-apply' ),
			'QAR' => __( 'Qatari riyal', 'marwa-apply' ),
			'KWD' => __( 'Kuwaiti dinar', 'marwa-apply' ),
			'OMR' => __( 'Omani rial', 'marwa-apply' ),
			'BHD' => __( 'Bahraini dinar', 'marwa-apply' ),
			'EUR' => __( 'Euro (€)', 'marwa-apply' ),
			'USD' => __( 'US dollar', 'marwa-apply' ),
			'BDT' => __( 'Bangladeshi taka', 'marwa-apply' ),
		);
	}

	/**
	 * Read every declared field off a posting.
	 *
	 * @param int $job_id Job.
	 * @return array
	 */
	public static function spec( $job_id ) {
		$job_id = (int) $job_id;

		$benefits = get_post_meta( $job_id, '_ma_benefits', true );
		$exams    = get_post_meta( $job_id, '_ma_req_exams', true );

		return array(
			'state'        => (string) ( get_post_meta( $job_id, '_ma_state', true ) ?: 'open' ),
			'positions'    => (int) get_post_meta( $job_id, '_positions', true ),
			'contract'     => (int) get_post_meta( $job_id, '_ma_contract_months', true ),
			'hours'        => (string) get_post_meta( $job_id, '_ma_hours', true ),
			'salary_min'   => (string) get_post_meta( $job_id, '_ma_salary_min', true ),
			'salary_max'   => (string) get_post_meta( $job_id, '_ma_salary_max', true ),
			'currency'     => (string) get_post_meta( $job_id, '_ma_currency', true ),
			'salary_hide'  => (bool) get_post_meta( $job_id, '_ma_salary_hide', true ),
			'benefits'     => is_array( $benefits ) ? $benefits : array(),
			'min_age'      => (int) get_post_meta( $job_id, '_ma_min_age', true ),
			'max_age'      => (int) get_post_meta( $job_id, '_ma_max_age', true ),
			'min_years'    => (int) get_post_meta( $job_id, '_ma_min_years', true ),
			'no_experience' => (bool) get_post_meta( $job_id, '_ma_no_experience', true ),
			'passport'     => (string) get_post_meta( $job_id, '_ma_req_passport', true ),
			'jp_level'     => (string) get_post_meta( $job_id, '_ma_req_jp', true ),
			'en_level'     => (string) get_post_meta( $job_id, '_ma_req_en', true ),
			'exams'        => is_array( $exams ) ? $exams : array(),
			'gender'       => (string) get_post_meta( $job_id, '_ma_req_gender', true ),
		);
	}

	/**
	 * Is this vacancy taking applications?
	 *
	 * @param int $job_id Job.
	 * @return bool
	 */
	public static function is_open( $job_id ) {
		$state = (string) get_post_meta( (int) $job_id, '_ma_state', true );

		// Postings made before this field existed are treated as open, which
		// is what they were.
		return '' === $state || 'open' === $state;
	}

	/**
	 * Pay, as a line somebody can read — or nothing, if it is withheld.
	 *
	 * @param array $spec Spec.
	 * @return string
	 */
	public static function pay_line( $spec ) {
		if ( $spec['salary_hide'] || ( '' === $spec['salary_min'] && '' === $spec['salary_max'] ) ) {
			return '';
		}

		$cur = $spec['currency'] ? $spec['currency'] : '';

		if ( '' !== $spec['salary_min'] && '' !== $spec['salary_max'] ) {
			$amount = $spec['salary_min'] . '–' . $spec['salary_max'];
		} else {
			$amount = $spec['salary_min'] ? $spec['salary_min'] : $spec['salary_max'];
		}

		return trim( $cur . ' ' . $amount ) . ' ' . __( 'per month', 'marwa-apply' );
	}
}
