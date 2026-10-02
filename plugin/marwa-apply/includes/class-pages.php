<?php
/**
 * The portal's own pages.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and remembers the pages the plugin needs.
 *
 * Page IDs are stored rather than slugs looked up at render time: someone will
 * eventually rename "Sign in" to "Login", and every link in the plugin should
 * keep working when they do.
 */
final class Pages {

	const OPTION = 'ma_pages';

	/**
	 * Page slug => title and shortcode.
	 *
	 * @return array
	 */
	public static function map() {
		return array(
			'welcome'  => array(
				'title'     => __( 'Work abroad with Marwa Overseas', 'marwa-apply' ),
				'shortcode' => '[ma_home]',
			),
			'register' => array(
				'title'     => __( 'Create an account', 'marwa-apply' ),
				'shortcode' => '[ma_register]',
			),
			'sign-in'  => array(
				'title'     => __( 'Sign in', 'marwa-apply' ),
				'shortcode' => '[ma_sign_in]',
			),
			'verify'   => array(
				'title'     => __( 'Confirm your email', 'marwa-apply' ),
				'shortcode' => '[ma_verify]',
			),
			'account'  => array(
				'title'     => __( 'My account', 'marwa-apply' ),
				'shortcode' => '[ma_account]',
			),
			'profile'  => array(
				'title'     => __( 'My profile', 'marwa-apply' ),
				'shortcode' => '[ma_profile]',
			),
			'documents' => array(
				'title'     => __( 'My documents', 'marwa-apply' ),
				'shortcode' => '[ma_documents]',
			),
			'guides'   => array(
				'title'     => __( 'Guides', 'marwa-apply' ),
				'shortcode' => '[ma_guides]',
			),
			'employer' => array(
				'title'     => __( 'Employer area', 'marwa-apply' ),
				'shortcode' => '[ma_employer]',
			),
			'employer-signup' => array(
				'title'     => __( 'Recruit from Bangladesh', 'marwa-apply' ),
				'shortcode' => '[ma_employer_signup]',
			),
		);
	}

	/**
	 * Create anything missing. Safe to run repeatedly.
	 *
	 * A page the administrator has deliberately trashed is not resurrected —
	 * only genuinely absent ones are created.
	 */
	public static function ensure() {
		$stored = (array) get_option( self::OPTION, array() );

		foreach ( self::map() as $slug => $spec ) {
			if ( ! empty( $stored[ $slug ] ) && get_post_status( $stored[ $slug ] ) ) {
				continue;
			}

			$existing = get_page_by_path( $slug );

			if ( $existing ) {
				$stored[ $slug ] = $existing->ID;
				continue;
			}

			$id = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => $spec['title'],
					'post_name'      => $slug,
					'post_content'   => $spec['shortcode'],
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);

			if ( ! is_wp_error( $id ) ) {
				$stored[ $slug ] = $id;
			}
		}

		update_option( self::OPTION, $stored );
	}

	/**
	 * URL of one of our pages.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	public static function url( $slug ) {
		$stored = (array) get_option( self::OPTION, array() );

		if ( ! empty( $stored[ $slug ] ) ) {
			$link = get_permalink( $stored[ $slug ] );

			if ( $link ) {
				return $link;
			}
		}

		return home_url( '/' . $slug . '/' );
	}

	/**
	 * ID of one of our pages, or 0.
	 *
	 * @param string $slug Page slug.
	 * @return int
	 */
	public static function id( $slug ) {
		$stored = (array) get_option( self::OPTION, array() );

		return isset( $stored[ $slug ] ) ? (int) $stored[ $slug ] : 0;
	}

	/**
	 * Keep the portal's own pages out of search engines.
	 *
	 * A sign-in form ranking for "Marwa Overseas" would be a poor result for
	 * everyone. The landing page and the job posts are what should rank.
	 */
	public static function noindex() {
		if ( ! is_page() ) {
			return;
		}

		$stored = (array) get_option( self::OPTION, array() );
		unset( $stored['welcome'], $stored['guides'], $stored['employer-signup'] );

		$ours = array_map( 'intval', array_values( $stored ) );

		if ( in_array( get_queried_object_id(), $ours, true ) ) {
			echo '<meta name="robots" content="noindex,follow">' . "\n";
		}
	}
}
