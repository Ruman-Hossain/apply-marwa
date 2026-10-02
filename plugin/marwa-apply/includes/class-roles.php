<?php
/**
 * Roles and capabilities.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Three roles in v1, plus a fourth reserved for phase 2.
 *
 * The employer role is created now but granted nothing, so the capability
 * names are settled before anything depends on them.
 */
final class Roles {

	const CANDIDATE = 'ma_candidate';
	const STAFF     = 'ma_staff';
	const MANAGER   = 'ma_manager';
	const EMPLOYER  = 'ma_employer';

	/**
	 * Capabilities by role.
	 *
	 * @return array
	 */
	public static function map() {
		$candidate = array(
			'read'                => true,
			'ma_edit_own_profile' => true,
			'ma_apply_to_jobs'    => true,
		);

		$staff = array(
			'read'                 => true,
			'ma_view_candidates'   => true,
			'ma_edit_candidates'   => true,
			'ma_change_status'     => true,
			'ma_add_notes'         => true,
			'ma_build_shortlists'  => true,
			'ma_export_pack'       => true,
			'ma_view_documents'    => true,
		);

		$manager = array_merge(
			$staff,
			array(
				'ma_manage_jobs'      => true,
				'ma_manage_employers' => true,
				'ma_manage_agencies'  => true,
				'ma_delete_candidates'=> true,
				'ma_view_sensitive'   => true,
				'ma_manage_settings'  => true,
			)
		);

		// An employer can see their own vacancies and the candidates Marwa has
		// put forward against them, and edit their own company description.
		// Nothing else: no candidate search, no contact details, no access to
		// anybody else's file.
		$employer = array(
			'read'                   => true,
			'ma_view_own_vacancies'  => true,
			'ma_edit_own_employer'   => true,
		);

		return array(
			self::CANDIDATE => $candidate,
			self::STAFF     => $staff,
			self::MANAGER   => $manager,
			self::EMPLOYER  => $employer,
		);
	}

	/**
	 * Create roles and give administrators the manager capabilities.
	 */
	public static function add() {
		$map = self::map();

		$labels = array(
			self::CANDIDATE => __( 'Candidate', 'marwa-apply' ),
			self::STAFF     => __( 'Marwa Staff', 'marwa-apply' ),
			self::MANAGER   => __( 'Marwa Manager', 'marwa-apply' ),
			self::EMPLOYER  => __( 'Employer', 'marwa-apply' ),
		);

		foreach ( $map as $role => $caps ) {
			remove_role( $role );
			add_role( $role, $labels[ $role ], $caps );
		}

		$admin = get_role( 'administrator' );

		if ( $admin ) {
			foreach ( array_keys( $map[ self::MANAGER ] ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Strip everything. Called from uninstall.php only.
	 */
	public static function remove() {
		$map   = self::map();
		$admin = get_role( 'administrator' );

		if ( $admin ) {
			foreach ( array_keys( $map[ self::MANAGER ] ) as $cap ) {
				if ( 'read' !== $cap ) {
					$admin->remove_cap( $cap );
				}
			}
		}

		foreach ( array_keys( $map ) as $role ) {
			remove_role( $role );
		}
	}
}
