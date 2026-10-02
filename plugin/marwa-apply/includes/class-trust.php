<?php
/**
 * The anti-fraud notice.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * States, on every vacancy, that Marwa never asks a jobseeker for money.
 *
 * Overseas recruitment fraud in Bangladesh is endemic, and the common shape of
 * it is somebody using a licensed agency's name and licence number to collect
 * "processing fees" for work that does not exist. The victims are the people
 * this portal is for, and the agency whose name is used is the second victim.
 *
 * So the notice is not a disclaimer in the footer. It sits on the page where
 * somebody has just decided they want the job, which is the moment they are
 * most willing to pay somebody to get it.
 *
 * Every claim here is checkable: the licence number is public, and the contact
 * route is Marwa's own. Nothing is promised that Marwa cannot stand behind.
 */
final class Trust {

	const LICENCE = 'RL 2831';

	/**
	 * Hook up.
	 */
	public static function init() {
		// After the apply box, which is at priority 5: somebody reads this
		// having already decided to apply.
		add_action( 'single_job_listing_end', array( __CLASS__, 'notice' ), 20 );
		add_shortcode( 'ma_trust', array( __CLASS__, 'notice_html' ) );
	}

	/**
	 * Print it.
	 */
	public static function notice() {
		echo self::notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped strings below.
	}

	/**
	 * The block.
	 *
	 * @return string
	 */
	public static function notice_html() {
		$out  = self::styles();
		$out .= '<section class="ma-trust mo"><div class="ma-trust-in">';

		$out .= '<h2>' . esc_html__( 'Before you pay anyone, read this', 'marwa-apply' ) . '</h2>';

		$out .= '<ul class="ma-trust-list">';
		$out .= '<li><strong>' . esc_html__( 'Marwa Overseas never charges a jobseeker to open an account, to complete a profile, or to apply for a job on this site.', 'marwa-apply' ) . '</strong></li>';
		$out .= '<li>' . esc_html__( 'Nobody from Marwa will ever ask you to send money to a personal bKash, Nagad or bank account.', 'marwa-apply' ) . '</li>';
		$out .= '<li>' . esc_html__( 'Government fees — passport, medical, BMET registration — are paid by you directly to those offices, never to an agent on Marwa&#8217;s behalf.', 'marwa-apply' ) . '</li>';
		$out .= '<li>' . esc_html__( 'No job offer on this site is final until you have a signed contract and a visa. Anyone promising a guaranteed visa for a fee is lying to you.', 'marwa-apply' ) . '</li>';
		$out .= '</ul>';

		$out .= '<div class="ma-trust-check">';
		$out .= '<p><strong>' . esc_html__( 'Check the licence.', 'marwa-apply' ) . '</strong> ';
		$out .= esc_html(
			sprintf(
				/* translators: %s: licence number */
				__( 'Marwa Overseas holds recruiting licence %s, issued by the Ministry of Expatriates&#8217; Welfare and Overseas Employment. A licence number can be checked against the Ministry&#8217;s own register — a real agency will never object to you checking.', 'marwa-apply' ),
				self::LICENCE
			)
		) . '</p>';

		$out .= '<p>' . esc_html__( 'If anyone uses Marwa&#8217;s name to ask you for money, tell Marwa. It is the only way it gets stopped.', 'marwa-apply' ) . '</p>';
		$out .= '</div>';

		$out .= '</div></section>';

		return $out;
	}

	/**
	 * Styles. Loud on purpose — this is the one block on the page that should
	 * interrupt somebody.
	 *
	 * @return string
	 */
	private static function styles() {
		return '<style id="marwa-apply-trust">
.ma-trust{margin:30px 0 0;border:1px solid #D8B9B2;border-left:5px solid var(--accent,#A93A2C);border-radius:12px;background:#FDF6F4;color:#2B1512}
.ma-trust-in{padding:22px 24px}
.ma-trust h2{margin:0 0 12px!important;font-size:19px;line-height:1.3;color:#8E2D20!important}
.ma-trust-list{margin:0 0 16px;padding-left:20px}
.ma-trust-list li{margin:0 0 9px;font-size:15px;line-height:1.6;color:#2B1512}
.ma-trust-list li:last-child{margin-bottom:0}
.ma-trust-check{padding-top:14px;border-top:1px solid #E4CCC6}
.ma-trust-check p{margin:0 0 9px;font-size:14.5px;line-height:1.65;color:#4A2A24}
.ma-trust-check p:last-child{margin-bottom:0}
@media(max-width:600px){.ma-trust-in{padding:18px}.ma-trust h2{font-size:17px}}
</style>';
	}
}
