<?php
/**
 * Protected document storage.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps candidate documents out of the web root.
 *
 * WordPress puts every upload under wp-content/uploads, which is reachable by
 * anyone who guesses the URL. That is fine for a photo of a building site and
 * completely unacceptable for a passport scan. This class picks a directory
 * outside the document root where it can, hardens it either way, and reports
 * honestly when it could not manage the first part.
 */
final class Storage {

	const DIR_OPTION = 'ma_storage_dir';

	/**
	 * Resolve, create and harden the data directory.
	 *
	 * @return string Absolute path, no trailing slash.
	 */
	public static function prepare() {
		$dir = self::resolve();

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		self::harden( $dir );
		update_option( self::DIR_OPTION, $dir );

		return $dir;
	}

	/**
	 * Current directory, resolving on first use.
	 *
	 * @return string
	 */
	public static function dir() {
		$stored = get_option( self::DIR_OPTION );

		return $stored ? $stored : self::prepare();
	}

	/**
	 * Choose the best available location.
	 *
	 * Preference order: a sibling of the document root, then the parent of
	 * ABSPATH, then — reluctantly — a hardened directory inside uploads.
	 *
	 * @return string
	 */
	private static function resolve() {
		// An explicit path in wp-config.php always wins. This is the escape
		// hatch for hosts where the guesswork below lands badly.
		if ( defined( 'MA_STORAGE_DIR' ) && MA_STORAGE_DIR ) {
			return untrailingslashit( wp_normalize_path( MA_STORAGE_DIR ) );
		}

		$candidates = array();
		$suffix     = '/marwa-apply-data';

		$docroot = self::docroot();

		if ( $docroot ) {
			$candidates[] = dirname( $docroot ) . $suffix;
		}

		$candidates[] = dirname( untrailingslashit( ABSPATH ) ) . $suffix;

		foreach ( $candidates as $candidate ) {
			if ( self::is_outside_webroot( $candidate ) && self::is_usable( $candidate ) ) {
				return $candidate;
			}
		}

		// Last resort. Hardened, but hardening is a second line of defence and
		// the admin notice will say so.
		$uploads = wp_get_upload_dir();

		return untrailingslashit( $uploads['basedir'] ) . '/marwa-apply-private';
	}

	/**
	 * Normalised document root, or empty string if it cannot be trusted.
	 *
	 * @return string
	 */
	private static function docroot() {
		$root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? wp_normalize_path( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ) : '';

		return $root ? untrailingslashit( $root ) : '';
	}

	/**
	 * Is this path outside anything the web server will serve?
	 *
	 * @param string $path Candidate path.
	 * @return bool
	 */
	private static function is_outside_webroot( $path ) {
		$path    = untrailingslashit( wp_normalize_path( $path ) );
		$docroot = self::docroot();
		$abspath = untrailingslashit( wp_normalize_path( ABSPATH ) );

		if ( $docroot && 0 === strpos( $path . '/', $docroot . '/' ) ) {
			return false;
		}

		if ( 0 === strpos( $path . '/', $abspath . '/' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Can we create and write here?
	 *
	 * @param string $path Candidate path.
	 * @return bool
	 */
	private static function is_usable( $path ) {
		if ( file_exists( $path ) ) {
			return is_dir( $path ) && wp_is_writable( $path );
		}

		$parent = dirname( $path );

		return is_dir( $parent ) && wp_is_writable( $parent ) && wp_mkdir_p( $path );
	}

	/**
	 * Drop the usual guards in, for whichever server is in front.
	 *
	 * @param string $dir Directory to harden.
	 */
	private static function harden( $dir ) {
		$files = array(
			'.htaccess'   => "# Marwa Apply — candidate documents. Never served directly.\nRequire all denied\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n",
			'web.config'  => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.php'   => "<?php\n// Silence is golden.\n",
			'index.html'  => '',
		);

		foreach ( $files as $name => $contents ) {
			$path = trailingslashit( $dir ) . $name;

			if ( ! file_exists( $path ) ) {
				@file_put_contents( $path, $contents ); // phpcs:ignore WordPress.PHP, WordPress.WP.AlternativeFunctions
			}
		}
	}

	/**
	 * Where one candidate's files live.
	 *
	 * @param int $candidate_id Candidate ID.
	 * @return string
	 */
	public static function candidate_dir( $candidate_id ) {
		$dir = trailingslashit( self::dir() ) . Install::current_agency_id() . '/' . (int) $candidate_id;

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		return $dir;
	}

	/**
	 * Where one employer's paperwork lives.
	 *
	 * Kept beside candidate files, under the same protected root — a trade
	 * licence and a demand letter are commercially sensitive, and a company
	 * handing them over is trusting Marwa with them exactly as a worker does
	 * with a passport scan.
	 *
	 * @param int $employer_id Employer ID.
	 * @return string
	 */
	public static function employer_dir( $employer_id ) {
		$dir = trailingslashit( self::dir() ) . Install::current_agency_id() . '/employers/' . (int) $employer_id;

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		return $dir;
	}

	/**
	 * An unguessable filename. The original name is kept in the database, not
	 * on disk, so nothing about the candidate leaks through the path.
	 *
	 * @param string $original Original filename.
	 * @return string
	 */
	public static function storage_name( $original ) {
		$ext = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
		$ext = preg_replace( '/[^a-z0-9]/', '', $ext );

		return wp_generate_password( 32, false, false ) . ( $ext ? '.' . $ext : '' );
	}

	/**
	 * Report on where documents ended up, for the admin notice.
	 *
	 * @return array{status:string,message:string,path:string}
	 */
	public static function health() {
		$dir = self::dir();

		if ( ! file_exists( $dir ) || ! wp_is_writable( $dir ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'the document directory does not exist or is not writable. Uploads will fail.', 'marwa-apply' ),
				'path'    => $dir,
			);
		}

		if ( ! self::is_outside_webroot( $dir ) ) {
			return array(
				'status'  => 'warning',
				'message' => __( 'documents are stored inside the web root. Access is blocked by .htaccess, but that is a second line of defence. Move this directory above the web root on the server and set MA_STORAGE_DIR in wp-config.php.', 'marwa-apply' ),
				'path'    => $dir,
			);
		}

		return array(
			'status'  => 'secure',
			'message' => '',
			'path'    => $dir,
		);
	}
}
