<?php
/**
 * What Marwa actually has on the books.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * The screen that answers "can we accept this demand letter?"
 *
 * Bdjobs shows a recruiter their category grid with a CV count beside every
 * occupation. Pointed outward it sells database access. Pointed inward it is
 * the most operationally useful screen an agency can have: before anybody
 * promises an employer forty welders, somebody should be able to see how many
 * welders with valid passports are actually on file.
 *
 * Counted live rather than cached. It is read a few times a week by a handful
 * of staff, and a stale number here would be worse than a slow one.
 */
final class Pool {

	const SLUG = 'marwa-pool';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 18 );
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_submenu_page(
			'marwa-apply',
			__( 'Who we have', 'marwa-apply' ),
			__( 'Who we have', 'marwa-apply' ),
			'ma_view_candidates',
			self::SLUG,
			array( __CLASS__, 'screen' )
		);
	}

	/**
	 * The screen.
	 */
	public static function screen() {
		global $wpdb;

		$c = Install::table( 'candidates' );

		echo '<div class="wrap"><h1>' . esc_html__( 'Who we have', 'marwa-apply' ) . '</h1>';
		echo '<p class="description" style="max-width:70ch">' . esc_html__( 'Counted from the candidate files right now. Use it before accepting a demand letter: a promise of forty welders is only worth making if the people exist.', 'marwa-apply' ) . '</p>';

		// ------------------------------------------------------- headline
		$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c} WHERE status <> 'demo'" ); // phpcs:ignore WordPress.DB
		$passport  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c} WHERE status <> 'demo' AND passport_status IN ('has','valid')" ); // phpcs:ignore WordPress.DB
		$ready     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c} WHERE status <> 'demo' AND ( available_from IS NULL OR available_from <= CURDATE() )" ); // phpcs:ignore WordPress.DB
		$complete  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c} WHERE status <> 'demo' AND profile_completeness >= 80" ); // phpcs:ignore WordPress.DB

		echo '<div class="ma-pool-top">';

		foreach ( array(
			array( __( 'Candidates on file', 'marwa-apply' ), $total ),
			array( __( 'With a passport in hand', 'marwa-apply' ), $passport ),
			array( __( 'Ready to travel now', 'marwa-apply' ), $ready ),
			array( __( 'Profile 80% or better', 'marwa-apply' ), $complete ),
		) as $tile ) {
			echo '<div><b>' . esc_html( number_format_i18n( $tile[1] ) ) . '</b><span>' . esc_html( $tile[0] ) . '</span></div>';
		}

		echo '</div>';

		// --------------------------------------------------- by trade
		echo '<h2>' . esc_html__( 'By trade', 'marwa-apply' ) . '</h2>';
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Sector', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'On file', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'With passport', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Japanese N4 / JFT or better', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Ready now', 'marwa-apply' ) . '</th>';
		echo '<th>' . esc_html__( 'Vacancies open', 'marwa-apply' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( (array) get_terms( array( 'taxonomy' => Sectors::TAXONOMY, 'hide_empty' => false ) ) as $t ) {
			if ( is_wp_error( $t ) ) {
				continue;
			}

			$id = (int) $t->term_id;

			$on   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$c} WHERE status <> 'demo' AND sector_id = %d", $id ) ); // phpcs:ignore WordPress.DB
			$pp   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$c} WHERE status <> 'demo' AND sector_id = %d AND passport_status IN ('has','valid')", $id ) ); // phpcs:ignore WordPress.DB
			$jp   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$c} WHERE status <> 'demo' AND sector_id = %d AND jp_language_level IN ('jft-basic','n4','n3','n2')", $id ) ); // phpcs:ignore WordPress.DB
			$rdy  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$c} WHERE status <> 'demo' AND sector_id = %d AND ( available_from IS NULL OR available_from <= CURDATE() )", $id ) ); // phpcs:ignore WordPress.DB

			// Only open vacancies count — a closed one is not demand.
			$jobs = 0;

			foreach ( (array) get_posts(
				array(
					'post_type'      => 'job_listing',
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'fields'         => 'ids',
					'tax_query'      => array( array( 'taxonomy' => Sectors::TAXONOMY, 'field' => 'term_id', 'terms' => $id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			) as $job ) {
				if ( Vacancy::is_open( (int) $job ) ) {
					++$jobs;
				}
			}

			$gap = $jobs > 0 && $pp < $jobs;

			echo '<tr' . ( $gap ? ' style="background:#FDF6F4"' : '' ) . '>';
			echo '<td><strong>' . esc_html( $t->name ) . '</strong></td>';
			echo '<td>' . esc_html( number_format_i18n( $on ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $pp ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $jp ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $rdy ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $jobs ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		// ------------------------------------------------------ districts
		$districts = (array) $wpdb->get_results( "SELECT district, COUNT(*) n FROM {$c} WHERE status <> 'demo' AND district IS NOT NULL AND district <> '' GROUP BY district ORDER BY n DESC LIMIT 20" ); // phpcs:ignore WordPress.DB

		if ( $districts ) {
			echo '<h2>' . esc_html__( 'Where they are from', 'marwa-apply' ) . '</h2>';
			echo '<div class="ma-pool-dist">';

			foreach ( $districts as $d ) {
				echo '<span><b>' . esc_html( number_format_i18n( (int) $d->n ) ) . '</b> ' . esc_html( $d->district ) . '</span>';
			}

			echo '</div>';
		}

		?>
<style>
.ma-pool-top{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0 26px;max-width:900px}
.ma-pool-top div{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:16px 18px}
.ma-pool-top b{display:block;font-size:27px;line-height:1.15;color:#0B2239}
.ma-pool-top span{display:block;font-size:12.5px;color:#50575e;margin-top:2px}
.ma-pool-dist{display:flex;flex-wrap:wrap;gap:8px;max-width:1000px;margin-top:12px}
.ma-pool-dist span{background:#fff;border:1px solid #dcdcde;border-radius:20px;padding:5px 13px;font-size:13px}
.ma-pool-dist b{color:#0B2239}
</style>
		<?php

		echo '</div>';
	}
}
