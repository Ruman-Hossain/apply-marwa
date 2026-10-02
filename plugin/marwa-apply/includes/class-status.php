<?php
/**
 * Status screen.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Shows what the plugin has actually created on this install.
 *
 * Most of this plugin's work is invisible — tables, roles, a directory above
 * the web root. Invisible work is work nobody can verify, which is how a
 * half-finished install reaches launch. This page is the answer to "is it
 * really set up?", for me now and for whoever maintains it later.
 */
final class Status {

	/**
	 * Register the menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Marwa Apply', 'marwa-apply' ),
			__( 'Marwa Apply', 'marwa-apply' ),
			'ma_view_candidates',
			'marwa-apply',
			array( __CLASS__, 'render' ),
			'dashicons-id-alt',
			26
		);

		add_submenu_page(
			'marwa-apply',
			__( 'System status', 'marwa-apply' ),
			__( 'System status', 'marwa-apply' ),
			'manage_options',
			'marwa-apply-status',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Tables and what each one is for, in plain language.
	 *
	 * @return array
	 */
	private static function tables() {
		return array(
			'candidates'  => __( 'One row per jobseeker — the structured profile that replaces an uploaded CV.', 'marwa-apply' ),
			'experience'  => __( 'Previous jobs, one row each.', 'marwa-apply' ),
			'documents'   => __( 'Passport scans and certificates. Files live outside the web root; this holds only the index.', 'marwa-apply' ),
			'applications'=> __( 'One row per candidate per job. Cannot be duplicated.', 'marwa-apply' ),
			'status_log'  => __( 'Who changed what, when, and why. The audit trail.', 'marwa-apply' ),
			'employers'   => __( 'Employers a job belongs to.', 'marwa-apply' ),
			'agencies'    => __( 'Marwa itself, plus any partner agency whose jobs Marwa posts as a medium.', 'marwa-apply' ),
		);
	}

	/**
	 * Build-time actions, for an administrator only.
	 *
	 * The demo row exists so the candidate-facing pages can be exercised on a
	 * real install without inventing a person: it is attached to the
	 * administrator's own account, carries status 'demo' so it can never be
	 * mistaken for an applicant, and the same screen removes it.
	 */
	public static function handle() {
		if ( empty( $_POST['ma_selftest'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! isset( $_POST['ma_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ma_nonce'] ) ), 'ma_selftest' ) ) {
			return;
		}

		global $wpdb;

		$what    = sanitize_key( wp_unslash( $_POST['ma_selftest'] ) ); // phpcs:ignore WordPress.Security
		$user_id = get_current_user_id();
		$table   = Install::table( 'candidates' );

		if ( 'create' === $what ) {
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB

			if ( ! $exists ) {
				$user = wp_get_current_user();

				$wpdb->insert( // phpcs:ignore WordPress.DB
					$table,
					array(
						'agency_id'  => Install::current_agency_id(),
						'user_id'    => $user_id,
						'full_name'  => $user->display_name,
						'phone'      => 'DEMO-' . $user_id,
						'email'      => $user->user_email,
						'status'     => 'demo',
						'created_at' => current_time( 'mysql' ),
						'updated_at' => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
				);
			}
		}

		if ( 'verify' === $what ) {
			update_user_meta( $user_id, Accounts::VERIFIED, current_time( 'mysql' ) );
		}

		if ( 'unverify' === $what ) {
			delete_user_meta( $user_id, Accounts::VERIFIED );
		}

		if ( 'remove' === $what ) {
			$wpdb->delete( $table, array( 'user_id' => $user_id, 'status' => 'demo' ), array( '%d', '%s' ) ); // phpcs:ignore WordPress.DB
		}

		wp_safe_redirect( admin_url( 'admin.php?page=marwa-apply&selftest=' . $what ) );
		exit;
	}

	/**
	 * Columns actually present on a table.
	 *
	 * dbDelta adds columns to an existing table when the plugin's schema
	 * grows, but it does so quietly and it is fussy about formatting. Printing
	 * what is really there turns "it should have worked" into "it did".
	 *
	 * @param string $name Bare table name.
	 * @return array
	 */
	private static function columns( $name ) {
		global $wpdb;

		$table = Install::table( $name );
		$rows  = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" ); // phpcs:ignore WordPress.DB

		return $rows ? wp_list_pluck( $rows, 'Field' ) : array();
	}

	/**
	 * Render.
	 */
	public static function render() {
		global $wpdb;

		echo '<div class="wrap"><h1>' . esc_html__( 'Marwa Apply — system status', 'marwa-apply' ) . '</h1>';
		echo '<p class="description">' . esc_html( sprintf( /* translators: %s: version */ __( 'Version %s. This page reports what the plugin has created on this install.', 'marwa-apply' ), MA_VERSION ) ) . '</p>';

		// ---------------------------------------------------------- tables
		echo '<h2>' . esc_html__( 'Database', 'marwa-apply' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Table', 'marwa-apply' ) . '</th><th>' . esc_html__( 'State', 'marwa-apply' ) . '</th><th>' . esc_html__( 'Rows', 'marwa-apply' ) . '</th><th>' . esc_html__( 'What it holds', 'marwa-apply' ) . '</th></tr></thead><tbody>';

		foreach ( self::tables() as $name => $purpose ) {
			$table  = Install::table( $name );
			$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB
			$rows   = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) : 0; // phpcs:ignore WordPress.DB

			printf(
				'<tr><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $table ),
				$exists ? '<span style="color:#1F5128">&#10003; ' . esc_html__( 'present', 'marwa-apply' ) . '</span>' : '<span style="color:#B4231A">&#10007; ' . esc_html__( 'MISSING', 'marwa-apply' ) . '</span>',
				esc_html( number_format_i18n( $rows ) ),
				esc_html( $purpose )
			);
		}

		echo '</tbody></table>';

		// ----------------------------------------------------------- roles
		echo '<h2>' . esc_html__( 'Roles', 'marwa-apply' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Role', 'marwa-apply' ) . '</th><th>' . esc_html__( 'State', 'marwa-apply' ) . '</th><th>' . esc_html__( 'Accounts', 'marwa-apply' ) . '</th><th>' . esc_html__( 'Capabilities', 'marwa-apply' ) . '</th></tr></thead><tbody>';

		$counts = count_users();

		foreach ( Roles::map() as $role => $caps ) {
			$exists = (bool) get_role( $role );
			$n      = isset( $counts['avail_roles'][ $role ] ) ? (int) $counts['avail_roles'][ $role ] : 0;

			printf(
				'<tr><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $role ),
				$exists ? '<span style="color:#1F5128">&#10003; ' . esc_html__( 'present', 'marwa-apply' ) . '</span>' : '<span style="color:#B4231A">&#10007; ' . esc_html__( 'MISSING', 'marwa-apply' ) . '</span>',
				esc_html( number_format_i18n( $n ) ),
				esc_html( count( $caps ) )
			);
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'The employer role is intentionally empty. It exists so the capability names are settled before the employer dashboard is built.', 'marwa-apply' ) . '</p>';

		// --------------------------------------------------------- storage
		$health = Storage::health();

		echo '<h2>' . esc_html__( 'Candidate documents', 'marwa-apply' ) . '</h2>';

		printf(
			'<p><strong>%s</strong><br><code>%s</code></p>',
			'secure' === $health['status']
				? '<span style="color:#1F5128">' . esc_html__( 'Stored outside the web root. Not reachable by URL.', 'marwa-apply' ) . '</span>'
				: '<span style="color:#B4231A">' . esc_html( $health['message'] ) . '</span>',
			esc_html( $health['path'] )
		);

		// --------------------------------------------------------- sectors
		$terms = get_terms(
			array(
				'taxonomy'   => Sectors::TAXONOMY,
				'hide_empty' => false,
			)
		);

		$count = is_wp_error( $terms ) ? 0 : count( $terms );

		echo '<h2>' . esc_html__( 'Sectors', 'marwa-apply' ) . '</h2>';
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: number of sectors */
					_n( '%d sector, shared between candidate profiles and job posts.', '%d sectors, shared between candidate profiles and job posts.', $count, 'marwa-apply' ),
					$count
				)
			)
		);

		// --------------------------------------------------------- columns
		echo '<h2>' . esc_html__( 'Schema detail', 'marwa-apply' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The columns actually present, read from the database rather than assumed from the plugin code.', 'marwa-apply' ) . '</p>';
		echo '<table class="widefat striped"><tbody>';

		foreach ( array_keys( self::tables() ) as $name ) {
			printf(
				'<tr><td style="width:18%%"><code>%s</code></td><td><code style="font-size:11px">%s</code></td></tr>',
				esc_html( $name ),
				esc_html( implode( ', ', self::columns( $name ) ) )
			);
		}

		echo '</tbody></table>';

		// ----------------------------------------------------- build tools
		if ( current_user_can( 'manage_options' ) ) {
			global $wpdb;

			$demo = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Install::table( 'candidates' ) . ' WHERE user_id = %d AND status = %s', get_current_user_id(), 'demo' ) ); // phpcs:ignore WordPress.DB

			echo '<h2>' . esc_html__( 'Build tools', 'marwa-apply' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'A demo candidate row attached to your own administrator account, so the candidate pages can be opened and filled in on this install without creating a person. It carries status "demo" and never appears as an applicant. Remove it before launch.', 'marwa-apply' ) . '</p>';
			echo '<form method="post">';
			echo wp_nonce_field( 'ma_selftest', 'ma_nonce', true, false );

			if ( $demo ) {
				$verified = Accounts::is_verified( get_current_user_id() );

				echo '<p><a href="' . esc_url( Pages::url( 'profile' ) ) . '" class="button button-primary" target="_blank" rel="noopener">' . esc_html__( 'Open my profile page', 'marwa-apply' ) . '</a> ';

				if ( $verified ) {
					echo '<button type="submit" name="ma_selftest" value="unverify" class="button">' . esc_html__( 'Mark my email unverified', 'marwa-apply' ) . '</button> ';
				} else {
					echo '<button type="submit" name="ma_selftest" value="verify" class="button">' . esc_html__( 'Mark my email verified', 'marwa-apply' ) . '</button> ';
				}

				echo '<button type="submit" name="ma_selftest" value="remove" class="button">' . esc_html__( 'Remove the demo row', 'marwa-apply' ) . '</button></p>';
				echo '<p class="description">' . esc_html__( 'Marking verified here skips the emailed code, so the apply flow can be exercised before Brevo is connected. It only ever affects your own account.', 'marwa-apply' ) . '</p>';
			} else {
				echo '<p><button type="submit" name="ma_selftest" value="create" class="button">' . esc_html__( 'Create a demo candidate row for my account', 'marwa-apply' ) . '</button></p>';
			}

			echo '</form>';
		}

		// ------------------------------------------------------- build log
		echo '<h2>' . esc_html__( 'Not built yet', 'marwa-apply' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Listed so nobody mistakes an unfinished install for a broken one.', 'marwa-apply' ) . '</p>';
		echo '<ol>';

		foreach ( array(
			__( 'Generated CV and shortlist pack', 'marwa-apply' ),
			__( 'Bangla translation of the candidate pages', 'marwa-apply' ),
			__( 'Data retention — deleting or anonymising old files', 'marwa-apply' ),
			__( 'Employer dashboard (phase 2)', 'marwa-apply' ),
		) as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}

		echo '</ol></div>';
	}
}
