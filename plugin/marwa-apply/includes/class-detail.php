<?php
/**
 * The posting page.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Everything the single-vacancy page shows around the description.
 *
 * Three pieces, each borrowed and each changed. A sticky action bar, because
 * overseas postings are long and putting Apply below two thousand words of
 * requirements loses people. A facts strip, because it forces every posting
 * into the same shape. And sharing, which matters more here than it does on
 * the board this came from: this audience passes vacancies to each other on
 * WhatsApp, and that is the cheapest channel Marwa has.
 */
final class Detail {

	/**
	 * Hook up.
	 */
	public static function init() {
		// Before the apply box at 5, so the facts are read first.
		add_action( 'single_job_listing_start', array( __CLASS__, 'top' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'styles' ), 104 );
	}

	/**
	 * The bar and the facts.
	 */
	public static function top() {
		global $post;

		if ( ! $post || 'job_listing' !== $post->post_type ) {
			return;
		}

		$spec = Vacancy::spec( $post->ID );
		$url  = (string) get_permalink( $post->ID );

		$expires = (string) get_post_meta( $post->ID, '_job_expires', true );
		$closes  = $expires ? strtotime( $expires . ' 23:59:59' ) : 0;
		$days    = $closes ? (int) floor( ( $closes - time() ) / DAY_IN_SECONDS ) : 0;

		echo '<div class="ma-det">';

		// ------------------------------------------------------- the bar
		echo '<div class="ma-det-bar">';

		if ( $closes ) {
			echo '<span class="ma-det-dl' . ( $days <= 7 ? ' urgent' : '' ) . '">';
			echo esc_html(
				sprintf(
					/* translators: %s: closing date */
					__( 'Applications close %s', 'marwa-apply' ),
					date_i18n( 'j M Y', $closes )
				)
			);
			echo '</span>';
		} else {
			echo '<span></span>';
		}

		echo '<span class="ma-det-acts">';

		if ( ! Vacancy::is_open( $post->ID ) ) {
			echo '<span class="ma-det-shut">' . esc_html__( 'Closed to new applications', 'marwa-apply' ) . '</span>';
		} else {
			echo '<a class="btn primary" href="#ma-apply">' . esc_html__( 'Apply', 'marwa-apply' ) . '</a>';
		}

		// Share. WhatsApp first on purpose — it is how this audience passes
		// work to each other, and Facebook is a distant second.
		$share = rawurlencode( get_the_title( $post->ID ) . ' — ' . $url );

		echo '<a class="btn" target="_blank" rel="noopener noreferrer" href="' . esc_url( 'https://wa.me/?text=' . $share ) . '">' . esc_html__( 'Share on WhatsApp', 'marwa-apply' ) . '</a>';
		echo '<a class="btn" target="_blank" rel="noopener noreferrer" href="' . esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ) ) . '">' . esc_html__( 'Share', 'marwa-apply' ) . '</a>';
		echo '</span></div>';

		// ----------------------------------------------------- the facts
		$facts = array();

		$loc = (string) get_post_meta( $post->ID, '_job_location', true );

		if ( $loc ) {
			$facts[ __( 'Destination', 'marwa-apply' ) ] = $loc;
		}

		if ( $spec['positions'] ) {
			$facts[ __( 'Positions', 'marwa-apply' ) ] = number_format_i18n( $spec['positions'] );
		}

		if ( $spec['contract'] ) {
			$facts[ __( 'Contract', 'marwa-apply' ) ] = sprintf(
				/* translators: %d: months */
				_n( '%d month', '%d months', $spec['contract'], 'marwa-apply' ),
				$spec['contract']
			);
		}

		$pay = Vacancy::pay_line( $spec );

		$facts[ __( 'Pay', 'marwa-apply' ) ] = $pay ? $pay : __( 'Confirmed at offer stage', 'marwa-apply' );

		if ( $spec['hours'] ) {
			$facts[ __( 'Hours', 'marwa-apply' ) ] = $spec['hours'];
		}

		if ( $spec['min_age'] || $spec['max_age'] ) {
			$facts[ __( 'Age', 'marwa-apply' ) ] = trim( ( $spec['min_age'] ? $spec['min_age'] : '' ) . '–' . ( $spec['max_age'] ? $spec['max_age'] : '' ), '–' );
		}

		if ( $spec['no_experience'] ) {
			$facts[ __( 'Experience', 'marwa-apply' ) ] = __( 'Not needed — first-time workers welcome', 'marwa-apply' );
		} elseif ( $spec['min_years'] ) {
			$facts[ __( 'Experience', 'marwa-apply' ) ] = sprintf(
				/* translators: %d: years */
				_n( '%d year minimum', '%d years minimum', $spec['min_years'], 'marwa-apply' ),
				$spec['min_years']
			);
		}

		if ( $facts ) {
			echo '<dl class="ma-det-facts">';

			foreach ( $facts as $label => $value ) {
				echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}

			echo '</dl>';
		}

		// --------------------------------------------------- what is given
		if ( $spec['benefits'] ) {
			$all = Vacancy::benefits();

			echo '<div class="ma-det-ben"><h3>' . esc_html__( 'What the employer provides', 'marwa-apply' ) . '</h3><ul>';

			foreach ( $spec['benefits'] as $key ) {
				if ( isset( $all[ $key ] ) ) {
					echo '<li>' . esc_html( $all[ $key ] ) . '</li>';
				}
			}

			echo '</ul></div>';
		}

		// -------------------------------------------- the free checklist
		if ( is_user_logged_in() ) {
			$row = Profile::row();

			if ( $row ) {
				echo Eligibility::panel( $row, (int) $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}

		echo '</div>';
	}

	/**
	 * Styles.
	 */
	public static function styles() {
		if ( ! is_singular( 'job_listing' ) ) {
			return;
		}

		?>
<style id="marwa-apply-detail">
.ma-det{margin:0 0 24px}
.ma-det-bar{position:sticky;top:76px;z-index:30;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
	padding:12px 16px;margin:0 0 18px;border:1px solid var(--line,#E3E0D9);border-radius:11px;background:#fff;box-shadow:0 2px 12px rgba(11,34,57,.07)}
.ma-det-dl{font-size:14.5px;font-weight:600;color:var(--navy,#0B2239)}
.ma-det-dl.urgent{color:#B4231A}
.ma-det-acts{display:flex;gap:8px;flex-wrap:wrap}
.ma-det-shut{font-size:14px;font-weight:700;color:#B4231A}
.ma-det-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1px;margin:0 0 18px;padding:0;
	background:var(--line,#E3E0D9);border:1px solid var(--line,#E3E0D9);border-radius:11px;overflow:hidden}
.ma-det-facts>div{background:#fff;padding:13px 16px}
.ma-det-facts dt{font-size:11.5px;letter-spacing:.07em;text-transform:uppercase;color:var(--muted,#5B6B7C);margin:0 0 3px}
.ma-det-facts dd{margin:0;font-size:15px;line-height:1.45;color:var(--navy,#0B2239);font-weight:600}
.ma-det-ben{border:1px solid var(--line,#E3E0D9);border-radius:11px;padding:16px 18px;margin:0 0 18px;background:#fff}
.ma-det-ben h3{margin:0 0 10px;font-size:15px}
.ma-det-ben ul{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(235px,1fr));gap:5px 20px}
.ma-det-ben li{position:relative;padding-left:20px;font-size:14.5px;line-height:1.55}
.ma-det-ben li:before{content:"";position:absolute;left:2px;top:6px;width:9px;height:5px;border-left:2px solid #1F7A4D;border-bottom:2px solid #1F7A4D;transform:rotate(-45deg)}
.ma-elig{border:1px solid #E0CBA8;border-left:4px solid #B07A12;border-radius:11px;padding:16px 18px;margin:0 0 18px;background:#FDF9F1}
.ma-elig.is-ok{border-color:#B6DCC6;border-left-color:#1F7A4D;background:#F2F9F5}
.ma-elig h3{margin:0 0 10px;font-size:16px;color:#6B4A08}
.ma-elig.is-ok h3{color:#135C38}
.ma-elig ul{margin:0 0 10px;padding-left:20px}
.ma-elig li{font-size:14.5px;line-height:1.6;margin-bottom:5px}
.ma-elig-have li{color:#135C38;list-style:none;position:relative}
.ma-elig-have{padding-left:20px}
.ma-elig-have li:before{content:"";position:absolute;left:-16px;top:7px;width:9px;height:5px;border-left:2px solid #1F7A4D;border-bottom:2px solid #1F7A4D;transform:rotate(-45deg)}
.ma-elig-note{margin:0;font-size:13.5px;line-height:1.6;color:#6B4A08}
@media(max-width:700px){.ma-det-bar{position:static}.ma-det-acts{width:100%}.ma-det-acts .btn{flex:1 1 auto;text-align:center}}
</style>
		<?php
	}
}
