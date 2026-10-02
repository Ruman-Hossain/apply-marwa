<?php
/**
 * Schema, install and upgrade.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and migrates the custom tables.
 *
 * Custom tables rather than post meta, because the admin list filters on
 * sector + trade + years + district + passport status together. In meta that
 * is one JOIN per field and it stops responding at a few thousand candidates.
 */
final class Install {

	const DB_VERSION_OPTION = 'ma_db_version';
	const DB_VERSION        = 11;

	/**
	 * Table name helper.
	 *
	 * @param string $name Bare table name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . 'ma_' . $name;
	}

	/**
	 * Run on activation.
	 */
	public static function activate() {
		self::create_tables();
		Roles::add();
		Storage::prepare();
		self::seed();

		// Deliberately store 0, not DB_VERSION.
		//
		// Pages, guides and front-page adoption all live in maybe_upgrade(),
		// because they need post types and taxonomies that are not registered
		// yet during activation. maybe_upgrade() returns early when the stored
		// version already matches, so writing the real version here would make
		// a FRESH install skip every one of them -- the portal would come up
		// with no Welcome, Jobs, Profile or Documents pages at all.
		//
		// Storing 0 makes the next admin request look like an upgrade from
		// nothing, which is exactly what a fresh install is. The real version
		// is written at the end of maybe_upgrade().
		update_option( self::DB_VERSION_OPTION, 0 );

		// Sector terms need the taxonomy registered, which has not happened yet
		// during activation. Defer to the next admin request.
		update_option( 'ma_needs_sector_seed', 1 );

		flush_rewrite_rules();
	}

	/**
	 * Run on deactivation. Roles and data are left alone — removing them here
	 * would punish anyone who deactivates to debug something.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Apply migrations when the plugin files are newer than the stored schema.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			if ( get_option( 'ma_needs_sector_seed' ) ) {
				Sectors::seed();
				delete_option( 'ma_needs_sector_seed' );
			}
			return;
		}

		self::create_tables();
		Roles::add();
		Storage::prepare();
		Sectors::seed();
		Pages::ensure();
		Guides::register();
		Guides::seed();
		self::adopt_jobs_page();
		self::adopt_front_page();

		// Seeded above, so the deferred flag has nothing left to do.
		delete_option( 'ma_needs_sector_seed' );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Use the portal's landing page as the front page.
	 *
	 * Only while the site is still showing the WordPress default (the blog
	 * roll, or the untouched "Sample Page"). If somebody has already chosen a
	 * front page, that choice stands.
	 */
	public static function adopt_front_page() {
		$welcome = Pages::id( 'welcome' );

		if ( ! $welcome ) {
			return;
		}

		$current = (int) get_option( 'page_on_front' );

		if ( $current && get_post_status( $current ) ) {
			$page = get_post( $current );

			if ( $page && 'sample-page' !== $page->post_name ) {
				return;
			}
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $welcome );
	}

	/**
	 * Point the Jobs page at our own listing.
	 *
	 * Only when the page still holds the bare [jobs] shortcode — if somebody
	 * has written a real page there, it is left alone.
	 */
	public static function adopt_jobs_page() {
		$page = get_page_by_path( 'jobs' );

		if ( ! $page ) {
			return;
		}

		$content = trim( $page->post_content );

		if ( '' !== $content && 0 !== strpos( $content, '[jobs' ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => '[ma_jobs]',
			)
		);
	}

	/**
	 * dbDelta schema.
	 *
	 * Every table carries agency_id from day one. It will only ever hold 1
	 * while this is a single-agency install, and that is the point: adding
	 * tenancy later would mean a migration and an auth audit.
	 *
	 * Applications and employers also carry an office. Marwa works out of two
	 * — Dhaka and Athens — and they do not share a book of work: a file opened
	 * against a European vacancy is not Dhaka's to read. Candidates are
	 * deliberately NOT scoped this way: there is one pool of jobseekers in
	 * Bangladesh, and it is the vacancy, not the person, that belongs to an
	 * office.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$sql     = array();

		$sql[] = 'CREATE TABLE ' . self::table( 'agencies' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			licence_no VARCHAR(60) DEFAULT NULL,
			country CHAR(2) NOT NULL DEFAULT 'BD',
			is_self TINYINT(1) NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . self::table( 'employers' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			agency_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			name VARCHAR(190) NOT NULL,
			country CHAR(2) DEFAULT NULL,
			sector_id BIGINT UNSIGNED DEFAULT NULL,
			office VARCHAR(20) NOT NULL DEFAULT 'dhaka',
			city VARCHAR(120) DEFAULT NULL,
			registration_no VARCHAR(80) DEFAULT NULL,
			website VARCHAR(190) DEFAULT NULL,
			logo_id BIGINT UNSIGNED DEFAULT NULL,
			about TEXT,
			contact_name VARCHAR(190) DEFAULT NULL,
			contact_email VARCHAR(190) DEFAULT NULL,
			contact_phone VARCHAR(40) DEFAULT NULL,
			demand_ref VARCHAR(80) DEFAULT NULL,
			demand_date DATE DEFAULT NULL,
			attestation VARCHAR(30) NOT NULL DEFAULT 'none',
			agreement VARCHAR(30) NOT NULL DEFAULT 'none',
			name_public TINYINT(1) NOT NULL DEFAULT 0,
			notes TEXT,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY agency_id (agency_id),
			KEY country (country)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . self::table( 'candidates' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			agency_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			user_id BIGINT UNSIGNED NOT NULL,
			full_name VARCHAR(190) NOT NULL DEFAULT '',
			full_name_bn VARCHAR(190) NOT NULL DEFAULT '',
			father_name VARCHAR(190) DEFAULT NULL,
			mother_name VARCHAR(190) DEFAULT NULL,
			gender VARCHAR(20) DEFAULT NULL,
			phone VARCHAR(20) NOT NULL,
			email VARCHAR(190) DEFAULT NULL,
			dob DATE DEFAULT NULL,
			district VARCHAR(60) DEFAULT NULL,
			sector_id BIGINT UNSIGNED DEFAULT NULL,
			trade VARCHAR(120) DEFAULT NULL,
			years_experience TINYINT UNSIGNED DEFAULT NULL,
			passport_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
			passport_no VARCHAR(40) DEFAULT NULL,
			jp_language_level VARCHAR(20) DEFAULT NULL,
			en_language_level VARCHAR(20) DEFAULT NULL,
			height_cm SMALLINT UNSIGNED DEFAULT NULL,
			weight_kg SMALLINT UNSIGNED DEFAULT NULL,
			blood_group VARCHAR(8) DEFAULT NULL,
			present_address TEXT,
			permanent_address TEXT,
			emergency_name VARCHAR(190) DEFAULT NULL,
			emergency_phone VARCHAR(20) DEFAULT NULL,
			available_from DATE DEFAULT NULL,
			visibility VARCHAR(20) NOT NULL DEFAULT 'agency',
			status VARCHAR(30) NOT NULL DEFAULT 'new',
			profile_completeness TINYINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY phone (phone),
			UNIQUE KEY user_id (user_id),
			KEY lookup (agency_id, sector_id, status),
			KEY passport_no (passport_no),
			KEY district (district)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . self::table( 'experience' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			candidate_id BIGINT UNSIGNED NOT NULL,
			employer_name VARCHAR(190) DEFAULT NULL,
			country CHAR(2) DEFAULT NULL,
			job_role VARCHAR(190) DEFAULT NULL,
			from_date DATE DEFAULT NULL,
			to_date DATE DEFAULT NULL,
			description TEXT,
			PRIMARY KEY  (id),
			KEY candidate_id (candidate_id)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . self::table( 'documents' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			candidate_id BIGINT UNSIGNED NOT NULL,
			doc_type VARCHAR(40) NOT NULL DEFAULT 'other',
			stored_name VARCHAR(255) NOT NULL,
			original_name VARCHAR(255) DEFAULT NULL,
			mime VARCHAR(100) DEFAULT NULL,
			size_bytes BIGINT UNSIGNED DEFAULT NULL,
			uploaded_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			delete_after DATE DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY candidate_id (candidate_id),
			KEY delete_after (delete_after)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . self::table( 'applications' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			agency_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			candidate_id BIGINT UNSIGNED NOT NULL,
			job_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'new',
			office VARCHAR(20) NOT NULL DEFAULT 'dhaka',
			source VARCHAR(20) NOT NULL DEFAULT 'self',
			consent_shared_with VARCHAR(190) DEFAULT NULL,
			consent_at DATETIME DEFAULT NULL,
			applied_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			decided_at DATETIME DEFAULT NULL,
			decided_by BIGINT UNSIGNED DEFAULT NULL,
			interview_at DATETIME DEFAULT NULL,
			interview_place VARCHAR(190) DEFAULT NULL,
			interview_note TEXT,
			PRIMARY KEY  (id),
			UNIQUE KEY cand_job (candidate_id, job_id),
			KEY job_status (job_id, status),
			KEY office_status (office, status),
			KEY agency_id (agency_id)
		) $charset;";

		// Employer paperwork. Separate from candidate documents on purpose:
		// different owner, different retention, different people allowed to
		// look, and a requirement key rather than a free-form type because
		// the whole point is checking a defined list is complete.
		$sql[] = 'CREATE TABLE ' . self::table( 'employer_docs' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employer_id BIGINT UNSIGNED NOT NULL,
			req_key VARCHAR(60) NOT NULL,
			stored_name VARCHAR(255) NOT NULL,
			original_name VARCHAR(255) DEFAULT NULL,
			mime VARCHAR(100) DEFAULT NULL,
			size_bytes BIGINT UNSIGNED DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'submitted',
			review_note TEXT,
			reviewed_by BIGINT UNSIGNED DEFAULT NULL,
			reviewed_at DATETIME DEFAULT NULL,
			uploaded_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY employer_id (employer_id),
			KEY req_key (req_key)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . self::table( 'status_log' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type VARCHAR(20) NOT NULL,
			entity_id BIGINT UNSIGNED NOT NULL,
			from_status VARCHAR(30) DEFAULT NULL,
			to_status VARCHAR(30) DEFAULT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			note TEXT,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id),
			KEY created_at (created_at)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . self::table( 'education' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			candidate_id BIGINT UNSIGNED NOT NULL,
			level VARCHAR(60) DEFAULT NULL,
			institution VARCHAR(190) DEFAULT NULL,
			subject VARCHAR(190) DEFAULT NULL,
			result VARCHAR(60) DEFAULT NULL,
			year_passed SMALLINT UNSIGNED DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY candidate_id (candidate_id)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . self::table( 'training' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			candidate_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(190) DEFAULT NULL,
			institution VARCHAR(190) DEFAULT NULL,
			duration VARCHAR(60) DEFAULT NULL,
			year_done SMALLINT UNSIGNED DEFAULT NULL,
			location VARCHAR(120) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY candidate_id (candidate_id)
		) $charset;";

		// Certificates and exams in one table. For overseas placement these
		// are the same kind of object: a thing an authority issued, with a
		// number and often an expiry, that an employer will ask to see.
		$sql[] = 'CREATE TABLE ' . self::table( 'certificates' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			candidate_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(190) DEFAULT NULL,
			authority VARCHAR(190) DEFAULT NULL,
			ref_no VARCHAR(90) DEFAULT NULL,
			issued_on DATE DEFAULT NULL,
			expires_on DATE DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY candidate_id (candidate_id),
			KEY expires_on (expires_on)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . self::table( 'skills' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			candidate_id BIGINT UNSIGNED NOT NULL,
			skill VARCHAR(150) DEFAULT NULL,
			level VARCHAR(30) DEFAULT NULL,
			years TINYINT UNSIGNED DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY candidate_id (candidate_id)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . self::table( 'referees' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			candidate_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(190) DEFAULT NULL,
			relation VARCHAR(120) DEFAULT NULL,
			organisation VARCHAR(190) DEFAULT NULL,
			phone VARCHAR(40) DEFAULT NULL,
			email VARCHAR(190) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY candidate_id (candidate_id)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Seed the single agency row that every other record points at.
	 */
	public static function seed() {
		global $wpdb;

		$table = self::table( 'agencies' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB

		if ( $count > 0 ) {
			return;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'name'       => 'Marwa Overseas',
				'licence_no' => 'RL 2831',
				'country'    => 'BD',
				'is_self'    => 1,
				'status'     => 'active',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * The agency this install belongs to.
	 *
	 * Everything calls this rather than hard-coding 1, so the day a second
	 * agency appears there is one function to change.
	 *
	 * @return int
	 */
	public static function current_agency_id() {
		global $wpdb;

		static $id = null;

		if ( null !== $id ) {
			return $id;
		}

		$table = self::table( 'agencies' );
		$id    = (int) $wpdb->get_var( "SELECT id FROM {$table} WHERE is_self = 1 ORDER BY id ASC LIMIT 1" ); // phpcs:ignore WordPress.DB

		return $id ? $id : 1;
	}
}
