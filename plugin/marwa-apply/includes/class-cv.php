<?php
/**
 * The generated CV.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * One consistent CV, built from the profile rather than collected as a file.
 *
 * A Japanese employer reading forty candidates should read the same shape
 * forty times. That is the single biggest thing this system does for Marwa
 * that an inbox full of attachments cannot.
 *
 * It is rendered as a self-contained HTML page with print styles, not a PDF
 * built by a library. The browser's own "Save as PDF" produces a better file
 * than any free PHP PDF library, costs nothing, adds no dependency, and
 * handles Bangla and Japanese text correctly — which is exactly where the
 * cheap PDF libraries fall over.
 */
final class CV {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_render' ), 21 );
	}

	/**
	 * URL of a candidate's CV.
	 *
	 * @param int $candidate_id Candidate.
	 * @return string
	 */
	public static function url( $candidate_id ) {
		return add_query_arg( 'ma_cv', (int) $candidate_id, home_url( '/' ) );
	}

	/**
	 * May the current user see this CV?
	 *
	 * @param object $c Candidate row.
	 * @return bool
	 */
	private static function may_read( $c ) {
		if ( ! $c || ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'ma_view_candidates' ) ) {
			return true;
		}

		return (int) $c->user_id === get_current_user_id();
	}

	/**
	 * Serve the CV when asked for.
	 */
	public static function maybe_render() {
		if ( empty( $_GET['ma_cv'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		global $wpdb;

		$id = (int) $_GET['ma_cv']; // phpcs:ignore WordPress.Security.NonceVerification
		$c  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'candidates' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB

		if ( ! self::may_read( $c ) ) {
			wp_die( esc_html__( 'You cannot view that CV.', 'marwa-apply' ), '', array( 'response' => 403 ) );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		echo self::document( $c ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * One labelled fact.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return string
	 */
	private static function fact( $label, $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}

		return '<div class="f"><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
	}

	/**
	 * The whole page.
	 *
	 * @param object $c Candidate row.
	 * @return string
	 */
	public static function document( $c ) {
		$sectors   = Sectors::options();
		$passports = Profile::passport_states();
		$jps       = Profile::jp_levels();
		$ens       = Profile::en_levels();
		$countries = Experience::countries();
		$exp       = Experience::for_candidate( (int) $c->id );

		$sector = isset( $sectors[ $c->sector_id ] ) ? $sectors[ $c->sector_id ] : '';
		$age    = ( $c->dob && '0000-00-00' !== $c->dob ) ? (int) floor( ( time() - strtotime( $c->dob ) ) / YEAR_IN_SECONDS ) : 0;

		$facts  = '';
		$facts .= self::fact( __( 'Trade', 'marwa-apply' ), $c->trade );
		$facts .= self::fact( __( 'Sector', 'marwa-apply' ), $sector );
		$facts .= self::fact( __( 'Experience', 'marwa-apply' ), null === $c->years_experience ? '' : sprintf( /* translators: %d: years */ _n( '%d year', '%d years', (int) $c->years_experience, 'marwa-apply' ), (int) $c->years_experience ) );
		$facts .= self::fact( __( 'Age', 'marwa-apply' ), $age ? (string) $age : '' );
		$facts .= self::fact( __( 'Home district', 'marwa-apply' ), $c->district );
		$facts .= self::fact( __( 'Japanese', 'marwa-apply' ), isset( $jps[ $c->jp_language_level ] ) && $c->jp_language_level ? $jps[ $c->jp_language_level ] : '' );
		$facts .= self::fact( __( 'English', 'marwa-apply' ), isset( $ens[ $c->en_language_level ] ) && $c->en_language_level ? $ens[ $c->en_language_level ] : '' );
		$facts .= self::fact( __( 'Passport', 'marwa-apply' ), isset( $passports[ $c->passport_status ] ) ? $passports[ $c->passport_status ] : '' );

		$history = '';

		foreach ( $exp as $e ) {
			$country = isset( $countries[ $e->country ] ) ? $countries[ $e->country ] : $e->country;
			$meta    = trim( implode( ' · ', array_filter( array( $e->employer_name, $country, Experience::dates( $e ) ) ) ), ' ·' );

			$history .= '<div class="job">';
			$history .= '<h3>' . esc_html( $e->job_role ) . '</h3>';
			$history .= $meta ? '<p class="meta">' . esc_html( $meta ) . '</p>' : '';
			$history .= $e->description ? '<p>' . esc_html( $e->description ) . '</p>' : '';
			$history .= '</div>';
		}

		if ( ! $history ) {
			$history = '<p class="muted">' . esc_html__( 'No previous employment recorded.', 'marwa-apply' ) . '</p>';
		}

		$name_bn = $c->full_name_bn ? '<p class="bn">' . esc_html( $c->full_name_bn ) . '</p>' : '';
		$ref     = 'MO-' . str_pad( (string) $c->id, 5, '0', STR_PAD_LEFT );

		ob_start();
		?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $c->full_name . ' — ' . __( 'Candidate profile', 'marwa-apply' ) ); ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#F3F1EC;color:#0B2239;font:15px/1.6 "Noto Sans","Helvetica Neue",Arial,sans-serif}
.sheet{max-width:820px;margin:24px auto;background:#fff;padding:40px 44px;border:1px solid #E3E0D9}
.top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-bottom:3px solid #0B2239;padding-bottom:16px;margin-bottom:22px}
h1{margin:0;font-size:27px;letter-spacing:-.01em}
.bn{margin:4px 0 0;color:#5B6B7C}
.role{margin:6px 0 0;font-size:16px;color:#A93A2C;font-weight:600}
.brand{text-align:right;font-size:12px;line-height:1.5;color:#5B6B7C}
.brand b{display:block;font-size:15px;color:#0B2239;letter-spacing:.02em}
h2{font-size:13px;text-transform:uppercase;letter-spacing:.09em;color:#5B6B7C;margin:26px 0 10px;border-bottom:1px solid #E3E0D9;padding-bottom:5px}
dl{display:grid;grid-template-columns:repeat(2,1fr);gap:0 28px;margin:0}
.f{display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px dotted #E3E0D9}
dt{color:#5B6B7C;margin:0}
dd{margin:0;font-weight:600;text-align:right}
.job{padding:10px 0;border-bottom:1px solid #F3F1EC}
.job h3{margin:0;font-size:16px}
.job p{margin:3px 0 0}
.meta{color:#5B6B7C;font-size:14px}
.muted{color:#5B6B7C}
.foot{margin-top:28px;padding-top:14px;border-top:1px solid #E3E0D9;font-size:12px;color:#5B6B7C;display:flex;justify-content:space-between;gap:16px}
.noprint{max-width:820px;margin:0 auto;padding:0 44px}
.btn{display:inline-block;background:#0B2239;color:#fff;padding:9px 18px;border-radius:7px;text-decoration:none;font-size:14px;border:0;cursor:pointer}
@media print{
  body{background:#fff}
  .sheet{margin:0;border:0;padding:0;max-width:none}
  .noprint{display:none}
  @page{margin:16mm}
}
</style>
</head>
<body>
<div class="noprint" style="padding-top:20px">
	<button class="btn" onclick="window.print()"><?php esc_html_e( 'Print or save as PDF', 'marwa-apply' ); ?></button>
</div>
<div class="sheet">
	<div class="top">
		<div>
			<h1><?php echo esc_html( $c->full_name ); ?></h1>
			<?php echo $name_bn; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php if ( $c->trade ) : ?><p class="role"><?php echo esc_html( $c->trade ); ?></p><?php endif; ?>
		</div>
		<div class="brand">
			<b>MARWA OVERSEAS</b>
			<?php esc_html_e( 'Government Licence No. RL 2831', 'marwa-apply' ); ?><br>
			<?php esc_html_e( 'Dhaka, Bangladesh', 'marwa-apply' ); ?><br>
			<?php echo esc_html( sprintf( /* translators: %s: reference */ __( 'Ref %s', 'marwa-apply' ), $ref ) ); ?>
		</div>
	</div>

	<h2><?php esc_html_e( 'Summary', 'marwa-apply' ); ?></h2>
	<dl><?php echo $facts; // phpcs:ignore WordPress.Security.EscapeOutput ?></dl>

	<h2><?php esc_html_e( 'Work history', 'marwa-apply' ); ?></h2>
	<?php echo $history; // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<div class="foot">
		<span><?php esc_html_e( 'Prepared by Marwa Overseas from the candidate\'s own declared information.', 'marwa-apply' ); ?></span>
		<span><?php echo esc_html( mysql2date( 'j M Y', current_time( 'mysql' ) ) ); ?></span>
	</div>
</div>
<?php self::print_script(); ?>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Open the print dialog when the CV was asked for as a download.
	 *
	 * Generating a PDF server-side was the obvious alternative and is the
	 * wrong one: the cheap PHP PDF libraries mangle Bangla conjuncts and drop
	 * Japanese entirely, and this document has both. The browser's own print
	 * engine renders them correctly, so "Download as PDF" opens the real
	 * document with the dialog ready and lets the browser do it.
	 */
	private static function print_script() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( empty( $_GET['print'] ) ) {
			return;
		}

		echo '<script>window.addEventListener("load",function(){setTimeout(function(){window.print()},350)})</script>';
	}
}
