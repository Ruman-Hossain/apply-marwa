<?php
/**
 * Sectors.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Sectors are a shared taxonomy rather than a column of free text, so that a
 * job post and a candidate profile are filtering on the same thing. Jobs come
 * from WP Job Manager; attaching the taxonomy to its post type means one list
 * serves both sides.
 */
final class Sectors {

	const TAXONOMY = 'ma_sector';

	/**
	 * The eleven sectors on marwaoverseas.com, in the order they appear there.
	 *
	 * The two most recent — care work and health — are marked in_preparation,
	 * because Marwa has not placed in either yet and the interface should not
	 * imply otherwise.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			array( 'slug' => 'civil-construction', 'name' => 'Civil Construction', 'jp' => '建設', 'ssw' => 1 ),
			array( 'slug' => 'manufacturing', 'name' => 'Manufacturing', 'jp' => '工業製品製造業', 'ssw' => 1 ),
			array( 'slug' => 'technical-trades', 'name' => 'Mechanical, Electrical & Technical Trades', 'jp' => '工業製品製造業 / 自動車整備', 'ssw' => 1 ),
			array( 'slug' => 'logistics', 'name' => 'Logistics, Warehouse & Fulfillment', 'jp' => '物流倉庫', 'ssw' => 1, 'note' => 'SSW field approved January 2026, expected to open 2027' ),
			array( 'slug' => 'transportation', 'name' => 'Transportation & Driving', 'jp' => '自動車運送業', 'ssw' => 1 ),
			array( 'slug' => 'food-hospitality', 'name' => 'Food Processing & Hospitality', 'jp' => '飲食料品製造業 / 外食業 / 宿泊', 'ssw' => 1 ),
			array( 'slug' => 'marine-shipyard', 'name' => 'Marine & Shipyard Services', 'jp' => '造船・舶用工業', 'ssw' => 1 ),
			array( 'slug' => 'cleaning-facility', 'name' => 'Cleaning, Facility Management & Maintenance', 'jp' => 'ビルクリーニング', 'ssw' => 1 ),
			array( 'slug' => 'agriculture', 'name' => 'Agriculture & Farming', 'jp' => '農業', 'ssw' => 1 ),
			array( 'slug' => 'care-work', 'name' => 'Care Work & Elderly Support', 'jp' => '介護', 'ssw' => 1, 'in_preparation' => 1 ),
			array( 'slug' => 'health-sector', 'name' => 'Health Sector & Nursing', 'jp' => '', 'ssw' => 0, 'in_preparation' => 1, 'note' => 'No Japanese route. Gulf only.' ),
		);
	}

	/**
	 * Register the taxonomy against candidates and, when it exists, jobs.
	 */
	public static function register_taxonomy() {
		$object_types = array( 'ma_candidate_ref' );

		if ( post_type_exists( 'job_listing' ) ) {
			$object_types[] = 'job_listing';
		}

		register_taxonomy(
			self::TAXONOMY,
			$object_types,
			array(
				'labels'            => array(
					'name'          => __( 'Sectors', 'marwa-apply' ),
					'singular_name' => __( 'Sector', 'marwa-apply' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'sector' ),
			)
		);
	}

	/**
	 * Create any missing terms. Safe to run repeatedly — existing terms and
	 * any edits made in the admin are left alone.
	 */
	public static function seed() {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			self::register_taxonomy();
		}

		foreach ( self::defaults() as $sector ) {
			$existing = get_term_by( 'slug', $sector['slug'], self::TAXONOMY );

			if ( $existing ) {
				continue;
			}

			$term = wp_insert_term( $sector['name'], self::TAXONOMY, array( 'slug' => $sector['slug'] ) );

			if ( is_wp_error( $term ) ) {
				continue;
			}

			update_term_meta( $term['term_id'], 'ma_jp_label', isset( $sector['jp'] ) ? $sector['jp'] : '' );
			update_term_meta( $term['term_id'], 'ma_has_ssw_field', ! empty( $sector['ssw'] ) ? 1 : 0 );
			update_term_meta( $term['term_id'], 'ma_in_preparation', ! empty( $sector['in_preparation'] ) ? 1 : 0 );

			if ( ! empty( $sector['note'] ) ) {
				update_term_meta( $term['term_id'], 'ma_note', $sector['note'] );
			}
		}
	}

	/**
	 * Sectors for a dropdown.
	 *
	 * @param bool $include_in_preparation Whether to include sectors not yet live.
	 * @return array term_id => label
	 */
	public static function options( $include_in_preparation = true ) {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$out = array();

		foreach ( $terms as $term ) {
			if ( ! $include_in_preparation && get_term_meta( $term->term_id, 'ma_in_preparation', true ) ) {
				continue;
			}

			$out[ $term->term_id ] = $term->name;
		}

		return $out;
	}
}
