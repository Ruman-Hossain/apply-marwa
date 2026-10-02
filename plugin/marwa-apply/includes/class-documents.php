<?php
/**
 * Candidate documents.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Passport scans, certificates, photographs and the candidate's own CV.
 *
 * Nothing here goes into wp-content/uploads. A passport scan under a
 * guessable URL is a breach, not a bug to get to later, so files land in a
 * directory above the web root under a random name, and the only way to read
 * one back is through a PHP endpoint that checks who is asking.
 *
 * The original filename is kept in the database, never on disk: "Rahim
 * passport.jpg" sitting in a directory listing tells you who it belongs to.
 */
final class Documents {

	const MAX_BYTES = 8388608; // 8 MB.
	const MAX_FILES = 12;

	/**
	 * What we accept, and what we call it.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'cv'          => array(
				'label' => __( 'CV / Résumé', 'marwa-apply' ),
				'hint'  => __( 'Your own CV in any layout. Marwa also builds one from your profile — this is as well as that, not instead of it.', 'marwa-apply' ),
			),
			'passport'    => array(
				'label' => __( 'Passport', 'marwa-apply' ),
				'hint'  => __( 'The photo page. Make sure all four corners are in the picture and the text is readable.', 'marwa-apply' ),
			),
			'photo'       => array(
				'label' => __( 'Passport-size photograph', 'marwa-apply' ),
				'hint'  => __( 'Plain background, face clearly visible.', 'marwa-apply' ),
			),
			'certificate' => array(
				'label' => __( 'Trade or training certificate', 'marwa-apply' ),
				'hint'  => __( 'Any certificate for the work you do. You can add more than one.', 'marwa-apply' ),
			),
			'language'    => array(
				'label' => __( 'Language test result', 'marwa-apply' ),
				'hint'  => __( 'JFT-Basic, JLPT, IELTS or Prometric result.', 'marwa-apply' ),
			),
			'other'       => array(
				'label' => __( 'Something else', 'marwa-apply' ),
				'hint'  => __( 'Experience letter, NID, medical report.', 'marwa-apply' ),
			),
		);
	}

	/**
	 * Accepted file types.
	 *
	 * Deliberately short. Everything a candidate needs to send is a photograph
	 * or a PDF; every other format is a way for something executable to arrive.
	 *
	 * @return array extension => mime
	 */
	public static function allowed() {
		return array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'pdf'  => 'application/pdf',
		);
	}

	/**
	 * The real ceiling on an upload here.
	 *
	 * PHP's own limits are usually lower than ours and are what a candidate
	 * will actually hit, so the figure shown on screen is the true one.
	 *
	 * @return int bytes
	 */
	public static function limit() {
		$php = wp_max_upload_size();

		return $php && $php < self::MAX_BYTES ? (int) $php : self::MAX_BYTES;
	}

	/**
	 * Hook up.
	 */
	public static function init() {
		add_shortcode( 'ma_documents', array( __CLASS__, 'render' ) );
		add_action( 'init', array( __CLASS__, 'maybe_serve' ), 20 );
	}

	// ---------------------------------------------------------------- read

	/**
	 * A candidate's documents.
	 *
	 * @param int $candidate_id Candidate.
	 * @return array
	 */
	public static function for_candidate( $candidate_id ) {
		global $wpdb;

		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'documents' ) . ' WHERE candidate_id = %d ORDER BY uploaded_at DESC', $candidate_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * The most recent document of one type.
	 *
	 * @param int    $candidate_id Candidate.
	 * @param string $doc_type     Type key.
	 * @return object|null
	 */
	public static function latest_of_type( $candidate_id, $doc_type ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'documents' ) . ' WHERE candidate_id = %d AND doc_type = %s ORDER BY uploaded_at DESC LIMIT 1', $candidate_id, $doc_type ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * One document.
	 *
	 * @param int $id Document id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'documents' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB
	}

	// --------------------------------------------------------------- write

	/**
	 * Take an uploaded file.
	 *
	 * @param array  $file     One entry from $_FILES.
	 * @param string $doc_type Document type key.
	 * @param object $row      Candidate row.
	 * @return true|\WP_Error
	 */
	public static function store( $file, $doc_type, $row ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'ma_nofile', __( 'No file arrived. Please choose a file and try again.', 'marwa-apply' ) );
		}

		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new \WP_Error( 'ma_uploaderr', __( 'The file did not upload completely. On a slow connection, try a smaller photo.', 'marwa-apply' ) );
		}

		if ( (int) $file['size'] > self::limit() ) {
			return new \WP_Error(
				'ma_toobig',
				sprintf(
					/* translators: %s: size */
					__( 'That file is larger than %s. Photograph the document again at a lower quality, or ask someone to reduce it for you.', 'marwa-apply' ),
					size_format( self::limit() )
				)
			);
		}

		if ( ! array_key_exists( $doc_type, self::types() ) ) {
			return new \WP_Error( 'ma_type', __( 'Please choose what this document is.', 'marwa-apply' ) );
		}

		if ( count( self::for_candidate( (int) $row->id ) ) >= self::MAX_FILES ) {
			return new \WP_Error( 'ma_toomany', __( 'You have reached the limit for uploaded files. Delete one you no longer need first.', 'marwa-apply' ) );
		}

		// The extension is what the sender chose to call it; the magic bytes
		// are what it actually is. Both have to agree, and both have to be on
		// the list.
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::allowed() );
		$ext     = $checked['ext'] ? strtolower( $checked['ext'] ) : '';
		$mime    = $checked['type'] ? $checked['type'] : '';
		$allowed = self::allowed();

		if ( ! $ext || ! $mime || ! isset( $allowed[ $ext ] ) || $allowed[ $ext ] !== $mime ) {
			return new \WP_Error( 'ma_filetype', __( 'Only JPG, PNG and PDF files can be accepted. If your CV is a Word file, open it and save or print it as PDF first — a Word file can carry things we cannot safely accept.', 'marwa-apply' ) );
		}

		$dir = Storage::candidate_dir( (int) $row->id );

		if ( ! $dir || ! is_dir( $dir ) ) {
			return new \WP_Error( 'ma_store', __( 'We could not store the file. Please tell Marwa Overseas.', 'marwa-apply' ) );
		}

		$stored = Storage::storage_name( $file['name'] );
		$target = trailingslashit( $dir ) . $stored;

		if ( ! @move_uploaded_file( $file['tmp_name'], $target ) ) { // phpcs:ignore WordPress.PHP
			return new \WP_Error( 'ma_move', __( 'We could not store the file. Please try again.', 'marwa-apply' ) );
		}

		@chmod( $target, 0600 ); // phpcs:ignore WordPress.PHP

		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'documents' ),
			array(
				'candidate_id'  => (int) $row->id,
				'doc_type'      => $doc_type,
				'stored_name'   => $stored,
				'original_name' => sanitize_file_name( $file['name'] ),
				'mime'          => $mime,
				'size_bytes'    => (int) $file['size'],
				'uploaded_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return true;
	}

	/**
	 * Handle a posted upload. Called from Accounts::handle().
	 */
	public static function do_upload() {
		$row = Profile::row();

		if ( ! $row ) {
			wp_safe_redirect( Pages::url( 'sign-in' ) );
			exit;
		}

		$doc_type = isset( $_POST['ma_doc_type'] ) ? sanitize_key( wp_unslash( $_POST['ma_doc_type'] ) ) : ''; // phpcs:ignore WordPress.Security
		$file     = isset( $_FILES['ma_file'] ) ? $_FILES['ma_file'] : array(); // phpcs:ignore WordPress.Security

		$result = self::store( $file, $doc_type, $row );

		if ( is_wp_error( $result ) ) {
			set_transient( 'ma_doc_err_' . get_current_user_id(), $result->get_error_message(), 120 );
			wp_safe_redirect( add_query_arg( 'up', '0', Pages::url( 'documents' ) ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'up', '1', Pages::url( 'documents' ) ) );
		exit;
	}

	/**
	 * Handle a posted delete. Called from Accounts::handle().
	 */
	public static function do_delete() {
		global $wpdb;

		$id  = isset( $_POST['ma_doc'] ) ? (int) $_POST['ma_doc'] : 0; // phpcs:ignore WordPress.Security
		$row = Profile::row();
		$doc = $id ? self::get( $id ) : null;

		if ( ! $row || ! $doc || (int) $doc->candidate_id !== (int) $row->id ) {
			wp_safe_redirect( Pages::url( 'documents' ) );
			exit;
		}

		$path = trailingslashit( Storage::candidate_dir( (int) $row->id ) ) . $doc->stored_name;

		if ( file_exists( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP
		}

		$wpdb->delete( Install::table( 'documents' ), array( 'id' => (int) $doc->id ), array( '%d' ) ); // phpcs:ignore WordPress.DB

		wp_safe_redirect( add_query_arg( 'del', '1', Pages::url( 'documents' ) ) );
		exit;
	}

	// --------------------------------------------------------------- serve

	/**
	 * May this user read this document?
	 *
	 * @param object $doc Document row.
	 * @return bool
	 */
	public static function may_read( $doc ) {
		if ( ! $doc || ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'ma_view_documents' ) ) {
			return true;
		}

		$row = Profile::row();

		return $row && (int) $row->id === (int) $doc->candidate_id;
	}

	/**
	 * Serve a file, if the asker is allowed it.
	 *
	 * The file never has a URL of its own — this is the only door.
	 */
	public static function maybe_serve() {
		if ( empty( $_GET['ma_doc'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$id  = (int) $_GET['ma_doc']; // phpcs:ignore WordPress.Security.NonceVerification
		$doc = self::get( $id );

		if ( ! self::may_read( $doc ) ) {
			wp_die( esc_html__( 'You cannot view that file.', 'marwa-apply' ), '', array( 'response' => 403 ) );
		}

		$path = trailingslashit( Storage::candidate_dir( (int) $doc->candidate_id ) ) . $doc->stored_name;

		if ( ! file_exists( $path ) ) {
			wp_die( esc_html__( 'That file is no longer on the server.', 'marwa-apply' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . $doc->mime );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: default-src \'none\'; sandbox' );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $doc->original_name ) . '"' );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * A preview of a document.
	 *
	 * Images are shown through the same permission-checked endpoint as the
	 * full file — there is no second, looser path to a thumbnail. A PDF gets
	 * an embedded viewer rather than a mystery link, because "is this the
	 * right page of my passport?" is the question a candidate actually has.
	 *
	 * @param object $doc    Document.
	 * @param int    $height Preview height in pixels.
	 * @return string
	 */
	public static function preview( $doc, $height = 150 ) {
		$url = self::url( $doc );

		if ( 0 === strpos( (string) $doc->mime, 'image/' ) ) {
			return sprintf(
				'<a href="%1$s" target="_blank" rel="noopener"><img src="%1$s" alt="%2$s" style="height:%3$dpx;width:auto;max-width:100%%;border:1px solid var(--line,#E3E0D9);border-radius:6px;background:#fff"></a>',
				esc_url( $url ),
				esc_attr( $doc->original_name ),
				(int) $height
			);
		}

		if ( 'application/pdf' === $doc->mime ) {
			return sprintf(
				'<object data="%1$s" type="application/pdf" style="width:100%%;height:%2$dpx;border:1px solid var(--line,#E3E0D9);border-radius:6px"><p><a href="%1$s" target="_blank" rel="noopener">%3$s</a></p></object>',
				esc_url( $url ),
				(int) $height * 2,
				esc_html__( 'Open the PDF', 'marwa-apply' )
			);
		}

		return '';
	}

	/**
	 * Link to a document.
	 *
	 * @param object $doc Document.
	 * @return string
	 */
	public static function url( $doc ) {
		return add_query_arg( 'ma_doc', (int) $doc->id, home_url( '/' ) );
	}

	// -------------------------------------------------------------- render

	/**
	 * The documents page.
	 *
	 * @return string
	 */
	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="ap-shell"><div class="ap-card"><h2>' . esc_html__( 'Sign in to continue', 'marwa-apply' ) . '</h2><p><a href="' . esc_url( Pages::url( 'sign-in' ) ) . '">' . esc_html__( 'Sign in', 'marwa-apply' ) . '</a></p></div></div>';
		}

		$row = Profile::row();

		if ( ! $row ) {
			return '<div class="ap-shell"><div class="ap-card"><p>' . esc_html__( 'We could not find your file. Please contact Marwa Overseas.', 'marwa-apply' ) . '</p></div></div>';
		}

		$err = get_transient( 'ma_doc_err_' . get_current_user_id() );

		if ( $err ) {
			delete_transient( 'ma_doc_err_' . get_current_user_id() );
		}

		$docs  = self::for_candidate( (int) $row->id );
		$types = self::types();

		$out = Shell::open( 'documents' );

		if ( isset( $_GET['up'] ) && '1' === $_GET['up'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out .= '<div class="ap-msg ok">' . esc_html__( 'Uploaded.', 'marwa-apply' ) . '</div>';
		}

		if ( isset( $_GET['del'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out .= '<div class="ap-msg ok">' . esc_html__( 'Deleted.', 'marwa-apply' ) . '</div>';
		}

		if ( $err ) {
			$out .= '<div class="ap-msg bad">' . esc_html( $err ) . '</div>';
		}

		// ------------------------------------------------------------ upload
		$out .= '<div class="ap-card">';
		$out .= '<h2>' . esc_html__( 'My documents', 'marwa-apply' ) . '</h2>';
		$out .= '<p class="ap-note">' . esc_html__( 'Your documents are stored where they cannot be reached from the internet, and are seen only by Marwa Overseas. They are never sent to an employer until you apply to that employer.', 'marwa-apply' ) . '</p>';

		$out .= '<form method="post" enctype="multipart/form-data">';
		$out .= wp_nonce_field( 'ma_upload', 'ma_nonce', true, false );
		$out .= '<input type="hidden" name="ma_action" value="upload">';

		$out .= '<div class="ap-field"><label for="ma_doc_type">' . esc_html__( 'What is this document?', 'marwa-apply' ) . '</label><select id="ma_doc_type" name="ma_doc_type">';

		foreach ( $types as $key => $spec ) {
			$out .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $spec['label'] ) . '</option>';
		}

		$out .= '</select>';

		foreach ( $types as $key => $spec ) {
			$out .= '<span class="hint" data-for="' . esc_attr( $key ) . '">' . esc_html( $spec['label'] ) . ' — ' . esc_html( $spec['hint'] ) . '</span>';
		}

		$out .= '</div>';

		$out .= '<div class="ap-field"><label for="ma_file">' . esc_html__( 'Choose the file', 'marwa-apply' );
		$out .= '<span class="hint">' . esc_html( sprintf( /* translators: %s: size */ __( 'JPG, PNG or PDF. Up to %s. A clear photo taken on your phone is fine.', 'marwa-apply' ), size_format( self::limit() ) ) ) . '</span>';
		$out .= '</label><input type="file" id="ma_file" name="ma_file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"></div>';

		$out .= '<button type="submit" class="btn primary">' . esc_html__( 'Upload', 'marwa-apply' ) . '</button>';
		$out .= '</form></div>';

		// ------------------------------------------------------------- list
		$out .= '<div class="ap-card"><h3>' . esc_html__( 'What you have sent us', 'marwa-apply' ) . '</h3>';

		if ( ! $docs ) {
			$out .= '<p class="ap-note">' . esc_html__( 'Nothing yet. Your passport photo page is the most useful one to start with.', 'marwa-apply' ) . '</p>';
		} else {
			$out .= '<table style="width:100%;border-collapse:collapse"><tbody>';

			foreach ( $docs as $doc ) {
				$label = isset( $types[ $doc->doc_type ]['label'] ) ? $types[ $doc->doc_type ]['label'] : $doc->doc_type;

				$out .= '<tr style="border-top:1px solid var(--line,#E3E0D9)">';
				$out .= '<td style="padding:10px 6px;width:120px">' . self::preview( $doc, 90 ) . '</td>';
				$out .= '<td style="padding:10px 6px"><strong>' . esc_html( $label ) . '</strong><br><span class="ap-note">' . esc_html( $doc->original_name ) . ' · ' . esc_html( size_format( (int) $doc->size_bytes ) ) . ' · ' . esc_html( mysql2date( get_option( 'date_format' ), $doc->uploaded_at ) ) . '</span></td>';
				$out .= '<td style="padding:10px 6px;text-align:right;white-space:nowrap">';
				$out .= '<a href="' . esc_url( self::url( $doc ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'marwa-apply' ) . '</a> &nbsp; ';
				$out .= '<form method="post" style="display:inline">';
				$out .= wp_nonce_field( 'ma_doc_delete', 'ma_nonce', true, false );
				$out .= '<input type="hidden" name="ma_action" value="doc_delete">';
				$out .= '<input type="hidden" name="ma_doc" value="' . esc_attr( $doc->id ) . '">';
				$out .= '<button type="submit" style="background:none;border:0;padding:0;text-decoration:underline;cursor:pointer;color:#B4231A;font:inherit">' . esc_html__( 'Delete', 'marwa-apply' ) . '</button>';
				$out .= '</form></td></tr>';
			}

			$out .= '</tbody></table>';
		}

		$out .= '<p style="margin-top:18px"><a href="' . esc_url( Pages::url( 'account' ) ) . '">' . esc_html__( 'Back to my account', 'marwa-apply' ) . '</a></p>';

		return $out . '</div>' . Shell::close();
	}
}
