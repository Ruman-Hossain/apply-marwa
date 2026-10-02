<?php
/**
 * Staff screens for employer records.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Where Marwa staff create and maintain the employer side of the business.
 *
 * This was the real gap: the portal could show an employer their vacancies
 * but nobody could create the employer in the first place except by running
 * the demo seeder. An agency's book of employers is the asset; it should not
 * live only in somebody's phone.
 *
 * The compliance fields — demand letter, mission attestation, recruitment
 * agreement — are here rather than on the employer's own screen, because they
 * are Marwa's record of the relationship. The employer sees them read-only.
 */
final class EmployersAdmin {

	const SLUG = 'marwa-employers';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 19 );
		add_action( 'admin_init', array( __CLASS__, 'handle' ) );
	}

	/**
	 * States a piece of paperwork can be in.
	 *
	 * @return array
	 */
	public static function paper_states() {
		return array(
			'none'     => __( 'Not started', 'marwa-apply' ),
			'pending'  => __( 'In progress', 'marwa-apply' ),
			'attested' => __( 'Attested', 'marwa-apply' ),
			'signed'   => __( 'Signed and on file', 'marwa-apply' ),
		);
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_submenu_page(
			'marwa-apply',
			__( 'Employers', 'marwa-apply' ),
			__( 'Employers', 'marwa-apply' ),
			'ma_manage_employers',
			self::SLUG,
			array( __CLASS__, 'screen' )
		);
	}

	/**
	 * Save.
	 */
	public static function handle() {
		global $wpdb;

		if ( ! current_user_can( 'ma_manage_employers' ) ) {
			return;
		}

		// Reviewing a document is its own submission.
		if ( ! empty( $_POST['ma_doc_review'] ) ) {
			check_admin_referer( 'ma_employers' );

			Vetting::review(
				(int) $_POST['ma_doc_id'], // phpcs:ignore WordPress.Security
				sanitize_key( wp_unslash( $_POST['ma_doc_review'] ) ), // phpcs:ignore WordPress.Security
				isset( $_POST['ma_doc_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ma_doc_note'] ) ) : '' // phpcs:ignore WordPress.Security
			);

			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&id=' . (int) ( $_POST['ma_id'] ?? 0 ) . '&saved=1' ) ); // phpcs:ignore WordPress.Security
			exit;
		}

		if ( empty( $_POST['ma_emp_save'] ) ) {
			return;
		}

		check_admin_referer( 'ma_employers' );

		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$id   = (int) ( $post['ma_id'] ?? 0 );

		$states = self::paper_states();

		$data = array(
			'name'            => sanitize_text_field( $post['name'] ?? '' ),
			'country'         => strtoupper( substr( sanitize_text_field( $post['country'] ?? '' ), 0, 2 ) ),
			'city'            => sanitize_text_field( $post['city'] ?? '' ),
			'registration_no' => sanitize_text_field( $post['registration_no'] ?? '' ),
			'website'         => esc_url_raw( $post['website'] ?? '' ),
			'about'           => sanitize_textarea_field( $post['about'] ?? '' ),
			'contact_name'    => sanitize_text_field( $post['contact_name'] ?? '' ),
			'contact_email'   => sanitize_email( $post['contact_email'] ?? '' ),
			'contact_phone'   => sanitize_text_field( $post['contact_phone'] ?? '' ),
			'demand_ref'      => sanitize_text_field( $post['demand_ref'] ?? '' ),
			'demand_date'     => ! empty( $post['demand_date'] ) && strtotime( $post['demand_date'] ) ? gmdate( 'Y-m-d', strtotime( $post['demand_date'] ) ) : null,
			'attestation'     => isset( $states[ $post['attestation'] ?? '' ] ) ? $post['attestation'] : 'none',
			'agreement'       => isset( $states[ $post['agreement'] ?? '' ] ) ? $post['agreement'] : 'none',
			'office'          => in_array( $post['office'] ?? '', array( 'dhaka', 'athens' ), true ) ? $post['office'] : 'dhaka',
			'name_public'     => empty( $post['name_public'] ) ? 0 : 1,
			'notes'           => sanitize_textarea_field( $post['notes'] ?? '' ),
			'status'          => in_array( $post['status'] ?? '', array( 'pending', 'active', 'paused', 'ended' ), true ) ? $post['status'] : 'pending',
		);

		if ( '' === $data['name'] ) {
			return;
		}

		// An employer cannot be made active while a required document is
		// missing or rejected. This is the one rule in the whole plugin that
		// overrides what a manager just clicked, and it does so because the
		// consequence of getting it wrong is a worker sent abroad on paperwork
		// that does not exist. The screen says why.
		if ( 'active' === $data['status'] && $id ) {
			$progress = Vetting::progress( $id );

			if ( ! $progress['ready'] ) {
				$data['status'] = 'pending';

				set_transient( 'ma_emp_blocked', $progress, 60 );
			}
		}

		$format = array_fill( 0, count( $data ) - 1, '%s' );
		$format = array();

		foreach ( $data as $k => $v ) {
			$format[] = 'name_public' === $k ? '%d' : '%s';
		}

		$table = Install::table( 'employers' );

		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) ); // phpcs:ignore WordPress.DB
		} else {
			$data['agency_id']  = Install::current_agency_id();
			$data['created_at'] = current_time( 'mysql' );
			$format[]           = '%d';
			$format[]           = '%s';

			$wpdb->insert( $table, $data, $format ); // phpcs:ignore WordPress.DB
			$id = (int) $wpdb->insert_id;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&id=' . $id . '&saved=1' ) );
		exit;
	}

	/**
	 * The screen.
	 */
	public static function screen() {
		global $wpdb;

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification

		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Employers', 'marwa-apply' ) . '</h1>';
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&id=new' ) ) . '" class="page-title-action">' . esc_html__( 'Add new', 'marwa-apply' ) . '</a>';

		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'marwa-apply' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( $id || ( isset( $_GET['id'] ) && 'new' === $_GET['id'] ) ) {
			self::form( $id );
		} else {
			self::table();
		}

		echo '</div>';
	}

	/**
	 * The list.
	 */
	private static function table() {
		global $wpdb;

		$table = Install::table( 'employers' );
		$rows  = (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC LIMIT 300" ); // phpcs:ignore WordPress.DB

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No employers yet. Add the first one.', 'marwa-apply' ) . '</p>';

			return;
		}

		$pending = 0;

		foreach ( $rows as $r ) {
			if ( 'pending' === $r->status ) {
				++$pending;
			}
		}

		if ( $pending ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html(
				sprintf(
					/* translators: %d: number of employers */
					_n( '%d employer is waiting to be verified.', '%d employers are waiting to be verified.', $pending, 'marwa-apply' ),
					$pending
				)
			) . '</strong> ' . esc_html__( 'They registered themselves and cannot submit vacancies until somebody confirms who they are and the paperwork is in place.', 'marwa-apply' ) . '</p></div>';
		}

		$states = self::paper_states();

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Employer', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Country', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Office', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Vacancies', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Demand letter', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Agreement', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Named publicly', 'marwa-apply' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $r ) {
			$jobs = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_ma_employer_id' AND meta_value = %s", (string) $r->id ) ); // phpcs:ignore WordPress.DB

			echo '<tr>';
			echo '<td><strong><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&id=' . (int) $r->id ) ) . '">' . esc_html( $r->name ) . '</a></strong>';

			if ( 'pending' === $r->status ) {
				echo ' <span style="color:#9A6B10;font-weight:600">' . esc_html__( '— awaiting verification', 'marwa-apply' ) . '</span>';
			} elseif ( 'active' !== $r->status ) {
				echo ' <span class="description">(' . esc_html( $r->status ) . ')</span>';
			}

			echo '</td>';
			echo '<td>' . esc_html( (string) $r->country ) . '</td>';
			echo '<td>' . esc_html( ucfirst( (string) $r->office ) ) . '</td>';
			echo '<td>' . esc_html( (string) $jobs ) . '</td>';
			echo '<td>' . esc_html( $r->demand_ref ? $r->demand_ref : '—' ) . '</td>';
			echo '<td>' . esc_html( isset( $states[ $r->agreement ] ) ? $states[ $r->agreement ] : '—' ) . '</td>';
			echo '<td>' . esc_html( $r->name_public ? __( 'Yes', 'marwa-apply' ) : __( 'No', 'marwa-apply' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * The edit form.
	 *
	 * @param int $id Employer, or 0 for new.
	 */
	private static function form( $id ) {
		global $wpdb;

		$table = Install::table( 'employers' );
		$e     = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) : null; // phpcs:ignore WordPress.DB

		$v = function ( $field, $default = '' ) use ( $e ) {
			return $e && isset( $e->$field ) && null !== $e->$field ? (string) $e->$field : $default;
		};

		echo '<form method="post" style="max-width:860px">';
		wp_nonce_field( 'ma_employers' );
		echo '<input type="hidden" name="ma_emp_save" value="1">';
		echo '<input type="hidden" name="ma_id" value="' . esc_attr( (string) $id ) . '">';

		echo '<h2>' . esc_html__( 'The company', 'marwa-apply' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		foreach ( array(
			'name'            => __( 'Company name', 'marwa-apply' ),
			'country'         => __( 'Country code', 'marwa-apply' ),
			'city'            => __( 'City', 'marwa-apply' ),
			'registration_no' => __( 'Registration number in their country', 'marwa-apply' ),
			'website'         => __( 'Website', 'marwa-apply' ),
		) as $field => $label ) {
			echo '<tr><th scope="row"><label for="f_' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td><input type="text" class="regular-text" id="f_' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $v( $field ) ) . '"' . ( 'name' === $field ? ' required' : '' ) . '></td></tr>';
		}

		$office = $v( 'office', 'dhaka' );

		echo '<tr><th scope="row">' . esc_html__( 'Handled by', 'marwa-apply' ) . '</th><td>';
		echo '<select name="office"><option value="dhaka"' . selected( $office, 'dhaka', false ) . '>' . esc_html__( 'Dhaka', 'marwa-apply' ) . '</option>';
		echo '<option value="athens"' . selected( $office, 'athens', false ) . '>' . esc_html__( 'Athens', 'marwa-apply' ) . '</option></select></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Named publicly', 'marwa-apply' ) . '</th><td>';
		echo '<label><input type="checkbox" name="name_public" value="1"' . checked( $v( 'name_public' ), '1', false ) . '> ' . esc_html__( 'This employer may be named and branded on the public jobs page', 'marwa-apply' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Leave unticked where the employer has not agreed in writing. Their vacancies then show the sector instead of the company, and carry a sector mark rather than a logo.', 'marwa-apply' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="f_about">' . esc_html__( 'About', 'marwa-apply' ) . '</label></th>';
		echo '<td><textarea id="f_about" name="about" rows="4" class="large-text">' . esc_textarea( $v( 'about' ) ) . '</textarea></td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Contact', 'marwa-apply' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		foreach ( array(
			'contact_name'  => __( 'Contact person', 'marwa-apply' ),
			'contact_email' => __( 'Email', 'marwa-apply' ),
			'contact_phone' => __( 'Phone', 'marwa-apply' ),
		) as $field => $label ) {
			echo '<tr><th scope="row"><label for="f_' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td><input type="text" class="regular-text" id="f_' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $v( $field ) ) . '"></td></tr>';
		}

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'The paperwork', 'marwa-apply' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'What makes a placement with this employer lawful. The employer sees this read-only on their own screen, so both sides are looking at the same file.', 'marwa-apply' ) . '</p>';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="f_demand_ref">' . esc_html__( 'Demand letter reference', 'marwa-apply' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="f_demand_ref" name="demand_ref" value="' . esc_attr( $v( 'demand_ref' ) ) . '"> ';
		echo '<input type="date" name="demand_date" value="' . esc_attr( $v( 'demand_date' ) !== '0000-00-00' ? $v( 'demand_date' ) : '' ) . '"></td></tr>';

		foreach ( array(
			'attestation' => __( 'Attestation at the Bangladesh mission', 'marwa-apply' ),
			'agreement'   => __( 'Recruitment agreement', 'marwa-apply' ),
		) as $field => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><select name="' . esc_attr( $field ) . '">';

			foreach ( self::paper_states() as $k => $slabel ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $v( $field, 'none' ), $k, false ) . '>' . esc_html( $slabel ) . '</option>';
			}

			echo '</select></td></tr>';
		}

		$status = $v( 'status', 'pending' );

		echo '<tr><th scope="row">' . esc_html__( 'Relationship', 'marwa-apply' ) . '</th><td><select name="status">';

		foreach ( array(
			'pending' => __( 'Pending — not yet verified', 'marwa-apply' ),
			'active'  => __( 'Active — may submit vacancies', 'marwa-apply' ),
			'paused'  => __( 'Paused', 'marwa-apply' ),
			'ended'   => __( 'Ended', 'marwa-apply' ),
		) as $k => $slabel ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $slabel ) . '</option>';
		}

		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="f_notes">' . esc_html__( 'Internal notes', 'marwa-apply' ) . '</label></th>';
		echo '<td><textarea id="f_notes" name="notes" rows="4" class="large-text">' . esc_textarea( $v( 'notes' ) ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Staff only. The employer never sees this.', 'marwa-apply' ) . '</p></td></tr>';

		echo '</tbody></table>';

		submit_button( $id ? __( 'Save employer', 'marwa-apply' ) : __( 'Create employer', 'marwa-apply' ) );
		echo '</form>';

		if ( $id ) {
			self::documents( $id );
		}
	}

	/**
	 * The document review panel.
	 *
	 * @param int $id Employer.
	 */
	private static function documents( $id ) {
		$reqs  = Vetting::requirements();
		$filed = Vetting::filed( $id );
		$prog  = Vetting::progress( $id );

		echo '<hr style="margin:28px 0"><h2>' . esc_html__( 'Documents', 'marwa-apply' ) . '</h2>';

		$blocked = get_transient( 'ma_emp_blocked' );

		if ( $blocked ) {
			delete_transient( 'ma_emp_blocked' );

			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Not activated.', 'marwa-apply' ) . '</strong> ';
			echo esc_html__( 'Required documents are still outstanding, so the status was left as pending. Accept the documents below first.', 'marwa-apply' ) . '</p></div>';
		}

		if ( $prog['total'] ) {
			$class = $prog['ready'] ? 'notice-success' : 'notice-warning';

			echo '<div class="notice ' . esc_attr( $class ) . ' inline"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: accepted 2: required */
					__( '%1$d of %2$d required documents accepted.', 'marwa-apply' ),
					$prog['done'],
					$prog['total']
				)
			);

			if ( $prog['missing'] ) {
				echo ' <strong>' . esc_html__( 'Missing:', 'marwa-apply' ) . '</strong> ' . esc_html( implode( ', ', $prog['missing'] ) ) . '.';
			}

			if ( $prog['pending'] ) {
				echo ' <strong>' . esc_html__( 'Waiting on your review:', 'marwa-apply' ) . '</strong> ' . esc_html( implode( ', ', $prog['pending'] ) ) . '.';
			}

			echo '</p></div>';
		}

		echo '<table class="wp-list-table widefat striped" style="max-width:920px"><thead><tr>';
		echo '<th style="width:28%">' . esc_html__( 'Document', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'File', 'marwa-apply' ) . '</th>';
		echo '<th style="width:36%">' . esc_html__( 'Decision', 'marwa-apply' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $reqs as $req ) {
			$row = isset( $filed[ $req['key'] ] ) ? $filed[ $req['key'] ] : null;

			echo '<tr><td><strong>' . esc_html( $req['label'] ) . '</strong>';
			echo empty( $req['required'] ) ? ' <span class="description">' . esc_html__( '(optional)', 'marwa-apply' ) . '</span>' : ' <span style="color:#A93A2C">*</span>';
			echo '</td>';

			if ( ! $row ) {
				echo '<td colspan="2"><span class="description">' . esc_html__( 'Not sent yet.', 'marwa-apply' ) . '</span></td></tr>';

				continue;
			}

			echo '<td><a href="' . esc_url( add_query_arg( 'ma_edoc', (int) $row->id, home_url( '/' ) ) ) . '" target="_blank" rel="noopener">' . esc_html( (string) $row->original_name ) . '</a><br>';
			echo '<span class="description">' . esc_html( size_format( (int) $row->size_bytes ) . ' · ' . mysql2date( get_option( 'date_format' ), $row->uploaded_at ) ) . '</span></td>';

			echo '<td>';

			$labels = array(
				'submitted' => __( 'Waiting for review', 'marwa-apply' ),
				'accepted'  => __( 'Accepted', 'marwa-apply' ),
				'rejected'  => __( 'Rejected', 'marwa-apply' ),
			);

			echo '<p style="margin:0 0 6px"><strong>' . esc_html( $labels[ $row->status ] ?? $row->status ) . '</strong>';

			if ( $row->review_note ) {
				echo '<br><span class="description">' . esc_html( $row->review_note ) . '</span>';
			}

			echo '</p>';

			echo '<form method="post">';
			wp_nonce_field( 'ma_employers' );
			echo '<input type="hidden" name="ma_id" value="' . esc_attr( (string) $id ) . '">';
			echo '<input type="hidden" name="ma_doc_id" value="' . esc_attr( (string) $row->id ) . '">';
			echo '<input type="text" name="ma_doc_note" class="regular-text" placeholder="' . esc_attr__( 'Why, if rejecting — the employer sees this', 'marwa-apply' ) . '" style="width:100%;margin-bottom:6px">';
			echo '<button class="button button-primary" name="ma_doc_review" value="accepted">' . esc_html__( 'Accept', 'marwa-apply' ) . '</button> ';
			echo '<button class="button" name="ma_doc_review" value="rejected">' . esc_html__( 'Reject', 'marwa-apply' ) . '</button>';
			echo '</form>';

			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Rejecting tells the employer why and lets them replace the file. The list itself is set under Settings.', 'marwa-apply' ) . '</p>';
	}
}
