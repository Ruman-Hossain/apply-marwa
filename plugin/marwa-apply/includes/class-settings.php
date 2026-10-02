<?php
/**
 * The few things that are genuinely settings.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Two decisions that cannot be made in code.
 *
 * Job alerts are off until somebody confirms mail works, because the failure
 * mode of turning them on blind is mailing everybody on file from a server
 * that silently drops it — or worse, from one that does not.
 *
 * Retention is the other. How long Marwa keeps a rejected candidate's
 * passport scan is a decision for Marwa, not for whoever wrote the plugin,
 * and the honest default is to keep everything and delete nothing until
 * somebody chooses a period on purpose.
 */
final class Settings {

	const SLUG = 'marwa-settings';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 40 );
		add_action( 'admin_init', array( __CLASS__, 'handle' ) );
		add_action( 'ma_daily_retention', array( __CLASS__, 'run_retention' ) );

		if ( ! wp_next_scheduled( 'ma_daily_retention' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ma_daily_retention' );
		}
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_submenu_page(
			'marwa-apply',
			__( 'Settings', 'marwa-apply' ),
			__( 'Settings', 'marwa-apply' ),
			'ma_manage_settings',
			self::SLUG,
			array( __CLASS__, 'screen' )
		);
	}

	/**
	 * Save.
	 */
	public static function handle() {
		if ( empty( $_POST['ma_settings'] ) || ! current_user_can( 'ma_manage_settings' ) ) {
			return;
		}

		check_admin_referer( 'ma_settings' );

		update_option( Alerts::OPTION, empty( $_POST['ma_alerts'] ) ? 0 : 1 );
		update_option( 'ma_retain_months', max( 0, min( 120, (int) ( $_POST['ma_retain'] ?? 0 ) ) ) );

		// The employer document list. Rows arrive parallel-indexed; a row
		// with no label is treated as deleted, which is how a list shrinks
		// without needing a separate delete control.
		if ( isset( $_POST['doc_label'] ) && is_array( $_POST['doc_label'] ) ) {
			$labels = wp_unslash( $_POST['doc_label'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$keys   = isset( $_POST['doc_key'] ) ? wp_unslash( $_POST['doc_key'] ) : array(); // phpcs:ignore WordPress.Security
			$hints  = isset( $_POST['doc_hint'] ) ? wp_unslash( $_POST['doc_hint'] ) : array(); // phpcs:ignore WordPress.Security
			$reqd   = isset( $_POST['doc_req'] ) ? (array) wp_unslash( $_POST['doc_req'] ) : array(); // phpcs:ignore WordPress.Security

			$list = array();

			foreach ( $labels as $i => $label ) {
				$label = trim( (string) $label );

				if ( '' === $label ) {
					continue;
				}

				$key = sanitize_key( $keys[ $i ] ?? '' );

				if ( '' === $key ) {
					$key = sanitize_key( substr( sanitize_title( $label ), 0, 40 ) );
				}

				$list[] = array(
					'key'      => $key,
					'label'    => $label,
					'hint'     => (string) ( $hints[ $i ] ?? '' ),
					'required' => in_array( (string) $i, array_map( 'strval', $reqd ), true ) ? 1 : 0,
				);
			}

			Vetting::save_requirements( $list );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&saved=1' ) );
		exit;
	}

	/**
	 * Delete documents belonging to files closed longer ago than the
	 * retention period.
	 *
	 * Only documents. The candidate record itself stays, because a worker who
	 * comes back in two years should not have to start again, and because the
	 * audit trail of what Marwa did is the thing a regulator would ask for.
	 * It is the passport scan sitting on a server that is the liability.
	 *
	 * @return int Files removed.
	 */
	public static function run_retention() {
		global $wpdb;

		$months = (int) get_option( 'ma_retain_months', 0 );

		if ( $months < 1 ) {
			return 0;
		}

		$apps = Install::table( 'applications' );
		$docs = Install::table( 'documents' );

		// Candidates whose every application is closed, and whose most recent
		// decision is older than the period.
		$ids = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT candidate_id FROM {$apps}
				 GROUP BY candidate_id
				 HAVING SUM( CASE WHEN status IN ('rejected','withdrawn','deployed') THEN 0 ELSE 1 END ) = 0
				    AND MAX( COALESCE( decided_at, applied_at ) ) < DATE_SUB( NOW(), INTERVAL %d MONTH )",
				$months
			)
		);

		if ( ! $ids ) {
			return 0;
		}

		$removed = 0;

		foreach ( array_map( 'intval', $ids ) as $cid ) {
			$files = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, stored_name FROM {$docs} WHERE candidate_id = %d", $cid ) ); // phpcs:ignore WordPress.DB

			foreach ( $files as $f ) {
				$path = Storage::candidate_dir( $cid ) . '/' . $f->stored_name;

				if ( is_readable( $path ) ) {
					wp_delete_file( $path );
				}

				$wpdb->delete( $docs, array( 'id' => (int) $f->id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
				++$removed;
			}

			if ( $files ) {
				Applications::log( 0, '', 'retention', sprintf( 'Documents removed for candidate %d under the %d month retention rule', $cid, $months ) );
			}
		}

		return $removed;
	}

	/**
	 * The screen.
	 */
	public static function screen() {
		$months = (int) get_option( 'ma_retain_months', 0 );

		echo '<div class="wrap"><h1>' . esc_html__( 'Settings', 'marwa-apply' ) . '</h1>';

		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'marwa-apply' ) . '</p></div>';
		}

		echo '<form method="post" style="max-width:760px">';
		wp_nonce_field( 'ma_settings' );
		echo '<input type="hidden" name="ma_settings" value="1">';

		echo '<h2>' . esc_html__( 'Job alerts', 'marwa-apply' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'When a vacancy is published, email every candidate who has confirmed their address and works in that trade. Nobody has to subscribe — the trade on their profile is what they already told us to watch.', 'marwa-apply' ) . '</p>';
		echo '<p><label><input type="checkbox" name="ma_alerts" value="1"' . checked( Alerts::enabled(), true, false ) . '> ' . esc_html__( 'Send job alerts', 'marwa-apply' ) . '</label></p>';
		echo '<p class="description"><strong>' . esc_html__( 'Leave this off until a test email has actually arrived.', 'marwa-apply' ) . '</strong> ' . esc_html__( 'Turning it on while mail is misconfigured means the first anyone knows is a vacancy nobody heard about.', 'marwa-apply' ) . '</p>';

		echo '<h2>' . esc_html__( 'Keeping candidate documents', 'marwa-apply' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'How long to keep uploaded passports, photographs and certificates after every application on a file has closed. The candidate record and the history of what Marwa did are always kept — it is the scanned documents sitting on a server that are the liability.', 'marwa-apply' ) . '</p>';
		echo '<p><input type="number" min="0" max="120" name="ma_retain" value="' . esc_attr( (string) $months ) . '" style="width:90px"> ' . esc_html__( 'months', 'marwa-apply' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Zero means keep everything indefinitely, which is the current state and is a decision nobody has made yet. Twenty-four months is a common choice. Whatever you pick, it should match what the privacy notice tells candidates.', 'marwa-apply' ) . '</p>';

		echo '<h2>' . esc_html__( 'Documents required from employers', 'marwa-apply' ) . '</h2>';
		echo '<p class="description" style="max-width:74ch">' . esc_html__( 'Marwa decides this list, not the plugin. What a Japanese care provider must produce is not what a Gulf contractor must produce, and the Ministry changes what it wants. Tick Required to make a document compulsory — an employer cannot be marked active, and cannot submit a vacancy, until every required document is accepted.', 'marwa-apply' ) . '</p>';
		echo '<p class="description"><strong>' . esc_html__( 'Clear a label to remove that row.', 'marwa-apply' ) . '</strong> ' . esc_html__( 'Add new ones in the blank rows at the bottom. The defaults shipped with the plugin are a common starting point, not legal advice — check them against what the Ministry currently requires.', 'marwa-apply' ) . '</p>';

		$reqs = Vetting::requirements();

		echo '<table class="wp-list-table widefat striped" style="max-width:920px"><thead><tr>';
		echo '<th style="width:30%">' . esc_html__( 'Document', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Note shown to the employer', 'marwa-apply' ) . '</th>';
		echo '<th style="width:90px">' . esc_html__( 'Required', 'marwa-apply' ) . '</th>';
		echo '</tr></thead><tbody>';

		$i = 0;

		foreach ( $reqs as $req ) {
			echo '<tr>';
			echo '<td><input type="text" name="doc_label[' . esc_attr( (string) $i ) . ']" value="' . esc_attr( $req['label'] ) . '" class="large-text">';
			echo '<input type="hidden" name="doc_key[' . esc_attr( (string) $i ) . ']" value="' . esc_attr( $req['key'] ) . '"></td>';
			echo '<td><input type="text" name="doc_hint[' . esc_attr( (string) $i ) . ']" value="' . esc_attr( (string) $req['hint'] ) . '" class="large-text"></td>';
			echo '<td style="text-align:center"><input type="checkbox" name="doc_req[]" value="' . esc_attr( (string) $i ) . '"' . checked( ! empty( $req['required'] ), true, false ) . '></td>';
			echo '</tr>';

			++$i;
		}

		for ( $n = 0; $n < 3; $n++ ) {
			$idx = $i + $n;

			echo '<tr>';
			echo '<td><input type="text" name="doc_label[' . esc_attr( (string) $idx ) . ']" value="" class="large-text" placeholder="' . esc_attr__( 'Add a document…', 'marwa-apply' ) . '">';
			echo '<input type="hidden" name="doc_key[' . esc_attr( (string) $idx ) . ']" value=""></td>';
			echo '<td><input type="text" name="doc_hint[' . esc_attr( (string) $idx ) . ']" value="" class="large-text"></td>';
			echo '<td style="text-align:center"><input type="checkbox" name="doc_req[]" value="' . esc_attr( (string) $idx ) . '"></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		submit_button();
		echo '</form></div>';
	}
}
