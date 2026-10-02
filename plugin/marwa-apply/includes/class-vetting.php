<?php
/**
 * Employer paperwork: what is required, and whether it is in.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * The documents an employer has to produce before Marwa will work with them.
 *
 * The list is a setting rather than a constant, and that is the important
 * part. What a Japanese care provider must produce is not what a Saudi
 * contractor must produce, the Ministry changes what it wants, and the person
 * who knows is in the Dhaka office, not in this file. So Marwa defines the
 * list, marks each item compulsory or optional, and the portal enforces
 * whatever they decided.
 *
 * The defaults below are the documents that come up in nearly every overseas
 * recruitment file. They are a starting point for Marwa to edit, not a legal
 * opinion — the screen says so.
 */
final class Vetting {

	const OPTION = 'ma_employer_docs';

	/**
	 * The default list, shipped so the screen is not empty on day one.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			array(
				'key'      => 'registration',
				'label'    => __( 'Certificate of incorporation or company registration', 'marwa-apply' ),
				'hint'     => __( 'Proof the company legally exists in its own country.', 'marwa-apply' ),
				'required' => 1,
			),
			array(
				'key'      => 'trade_licence',
				'label'    => __( 'Trade or business licence', 'marwa-apply' ),
				'hint'     => __( 'Current, and showing the activity they are recruiting for.', 'marwa-apply' ),
				'required' => 1,
			),
			array(
				'key'      => 'demand_letter',
				'label'    => __( 'Demand letter', 'marwa-apply' ),
				'hint'     => __( 'On company letterhead, stating the roles, numbers, pay and contract length.', 'marwa-apply' ),
				'required' => 1,
			),
			array(
				'key'      => 'power_of_attorney',
				'label'    => __( 'Power of attorney', 'marwa-apply' ),
				'hint'     => __( 'Authorising Marwa Overseas to recruit on the employer&#8217;s behalf.', 'marwa-apply' ),
				'required' => 1,
			),
			array(
				'key'      => 'agreement',
				'label'    => __( 'Recruitment or service agreement', 'marwa-apply' ),
				'hint'     => __( 'Signed by both sides.', 'marwa-apply' ),
				'required' => 1,
			),
			array(
				'key'      => 'contract_specimen',
				'label'    => __( 'Specimen employment contract', 'marwa-apply' ),
				'hint'     => __( 'The contract a worker would actually sign. Marwa reads this before anybody is put forward.', 'marwa-apply' ),
				'required' => 1,
			),
			array(
				'key'      => 'attestation',
				'label'    => __( 'Attestation by the Bangladesh mission', 'marwa-apply' ),
				'hint'     => __( 'Where the destination requires it.', 'marwa-apply' ),
				'required' => 0,
			),
			array(
				'key'      => 'visa_approval',
				'label'    => __( 'Visa or work permit approval', 'marwa-apply' ),
				'hint'     => __( 'Where it is issued before recruitment begins.', 'marwa-apply' ),
				'required' => 0,
			),
			array(
				'key'      => 'id_signatory',
				'label'    => __( 'Identification of the signing officer', 'marwa-apply' ),
				'hint'     => __( 'Passport or national ID page of whoever signs for the company.', 'marwa-apply' ),
				'required' => 0,
			),
			array(
				'key'      => 'other',
				'label'    => __( 'Anything else Marwa has asked for', 'marwa-apply' ),
				'hint'     => '',
				'required' => 0,
			),
		);
	}

	/**
	 * The list in force.
	 *
	 * @return array
	 */
	public static function requirements() {
		$stored = get_option( self::OPTION, null );

		if ( ! is_array( $stored ) || ! $stored ) {
			return self::defaults();
		}

		return $stored;
	}

	/**
	 * Save a list.
	 *
	 * @param array $list Requirements.
	 */
	public static function save_requirements( $list ) {
		$clean = array();

		foreach ( (array) $list as $item ) {
			$key = sanitize_key( $item['key'] ?? '' );
			$lab = sanitize_text_field( $item['label'] ?? '' );

			if ( '' === $key || '' === $lab ) {
				continue;
			}

			$clean[] = array(
				'key'      => $key,
				'label'    => $lab,
				'hint'     => sanitize_text_field( $item['hint'] ?? '' ),
				'required' => empty( $item['required'] ) ? 0 : 1,
			);
		}

		update_option( self::OPTION, $clean );
	}

	/**
	 * What one employer has filed.
	 *
	 * @param int $employer_id Employer.
	 * @return array req_key => row
	 */
	public static function filed( $employer_id ) {
		global $wpdb;

		$table = Install::table( 'employer_docs' );

		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE employer_id = %d ORDER BY uploaded_at DESC", (int) $employer_id ) ); // phpcs:ignore WordPress.DB

		$out = array();

		foreach ( $rows as $r ) {
			// Newest per requirement wins; an employer who re-uploads has
			// replaced the old one as far as anybody reviewing is concerned.
			if ( ! isset( $out[ $r->req_key ] ) ) {
				$out[ $r->req_key ] = $r;
			}
		}

		return $out;
	}

	/**
	 * Is this employer's file complete enough to activate?
	 *
	 * @param int $employer_id Employer.
	 * @return array done, missing, pending, total_required
	 */
	public static function progress( $employer_id ) {
		$filed   = self::filed( $employer_id );
		$missing = array();
		$pending = array();
		$done    = 0;
		$total   = 0;

		foreach ( self::requirements() as $req ) {
			if ( empty( $req['required'] ) ) {
				continue;
			}

			++$total;

			$row = isset( $filed[ $req['key'] ] ) ? $filed[ $req['key'] ] : null;

			if ( ! $row || 'rejected' === $row->status ) {
				$missing[] = $req['label'];
			} elseif ( 'accepted' === $row->status ) {
				++$done;
			} else {
				$pending[] = $req['label'];
			}
		}

		return array(
			'done'    => $done,
			'missing' => $missing,
			'pending' => $pending,
			'total'   => $total,
			'ready'   => $total > 0 && $done === $total,
		);
	}

	/**
	 * Accept an uploaded file.
	 *
	 * Validation is the same shape as candidate documents: the extension and
	 * the file's actual contents have to agree, so a .pdf that is really an
	 * executable is refused rather than stored and served later.
	 *
	 * @param array  $file    One $_FILES entry.
	 * @param string $req_key Requirement.
	 * @param object $e       Employer row.
	 * @return string Error message, or '' on success.
	 */
	public static function store( $file, $req_key, $e ) {
		global $wpdb;

		if ( empty( $file['name'] ) || ! empty( $file['error'] ) ) {
			return __( 'No file arrived. Please try again.', 'marwa-apply' );
		}

		$limit = min( 10 * MB_IN_BYTES, (int) wp_max_upload_size() );

		if ( (int) $file['size'] > $limit ) {
			return sprintf(
				/* translators: %s: size */
				__( 'That file is too large. The limit is %s.', 'marwa-apply' ),
				size_format( $limit )
			);
		}

		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$type  = $check['type'] ? $check['type'] : '';

		$allowed = array( 'application/pdf', 'image/jpeg', 'image/png' );

		if ( ! in_array( $type, $allowed, true ) ) {
			return __( 'Please send a PDF, JPG or PNG. If you have a Word file, save it as a PDF first.', 'marwa-apply' );
		}

		$dir   = Storage::employer_dir( (int) $e->id );
		$name  = Storage::storage_name( $file['name'] );
		$path  = trailingslashit( $dir ) . $name;

		if ( ! @move_uploaded_file( $file['tmp_name'], $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return __( 'We could not save that file. Please try again.', 'marwa-apply' );
		}

		@chmod( $path, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'employer_docs' ),
			array(
				'employer_id'   => (int) $e->id,
				'req_key'       => sanitize_key( $req_key ),
				'stored_name'   => $name,
				'original_name' => sanitize_file_name( $file['name'] ),
				'mime'          => $type,
				'size_bytes'    => (int) $file['size'],
				'status'        => 'submitted',
				'uploaded_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return '';
	}

	/**
	 * Who may read an employer document.
	 *
	 * The employer's own people, and Marwa staff who are allowed to see
	 * documents. Nobody else, ever — these are commercial papers.
	 *
	 * @param object $doc Document row.
	 * @return bool
	 */
	public static function may_read( $doc ) {
		if ( current_user_can( 'ma_view_documents' ) || current_user_can( 'ma_manage_employers' ) ) {
			return true;
		}

		$mine = (int) get_user_meta( get_current_user_id(), '_ma_employer_id', true );

		return $mine && $mine === (int) $doc->employer_id;
	}

	/**
	 * Serve one, through a permission check.
	 */
	public static function maybe_serve() {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( empty( $_GET['ma_edoc'] ) ) {
			return;
		}

		$id    = (int) $_GET['ma_edoc']; // phpcs:ignore WordPress.Security.NonceVerification
		$table = Install::table( 'employer_docs' );
		$doc   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB

		if ( ! $doc || ! self::may_read( $doc ) ) {
			wp_die( esc_html__( 'You do not have permission to open that file.', 'marwa-apply' ), '', array( 'response' => 403 ) );
		}

		$path = trailingslashit( Storage::employer_dir( (int) $doc->employer_id ) ) . $doc->stored_name;

		if ( ! is_readable( $path ) ) {
			wp_die( esc_html__( 'That file is no longer on the server.', 'marwa-apply' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . $doc->mime );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( (string) $doc->original_name ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Review one document.
	 *
	 * @param int    $id     Document.
	 * @param string $status accepted or rejected.
	 * @param string $note   Why.
	 */
	public static function review( $id, $status, $note = '' ) {
		global $wpdb;

		if ( ! current_user_can( 'ma_manage_employers' ) ) {
			return;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB
			Install::table( 'employer_docs' ),
			array(
				'status'      => in_array( $status, array( 'accepted', 'rejected' ), true ) ? $status : 'submitted',
				'review_note' => $note ? sanitize_textarea_field( $note ) : null,
				'reviewed_by' => get_current_user_id(),
				'reviewed_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}
}
