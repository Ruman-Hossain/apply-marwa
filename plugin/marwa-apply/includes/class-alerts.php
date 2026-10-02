<?php
/**
 * Job alerts.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Tell a candidate when work opens in their trade.
 *
 * Their version of this is Favourite Search — save a filter, come back to it.
 * On a board with 5,700 live vacancies that makes sense. On a board with a
 * dozen, saving a search is pointless: the useful thing is not to come back
 * and look, it is to be told.
 *
 * So there is nothing to configure. A candidate who has said what trade they
 * work in has already told us what to watch, and the alert follows that. One
 * fewer form, and it works for the candidate who filled in his profile and
 * then never returned — which is most of them.
 */
final class Alerts {

	const OPTION = 'ma_alerts_on';
	const SENT   = '_ma_alerted';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 3 );
	}

	/**
	 * Are alerts switched on?
	 *
	 * Off by default, deliberately: mail must be known to work before the
	 * portal starts sending to everybody on file.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) get_option( self::OPTION, 0 );
	}

	/**
	 * A vacancy went live.
	 *
	 * @param string   $new  New status.
	 * @param string   $old  Old status.
	 * @param \WP_Post $post Post.
	 */
	public static function on_publish( $new, $old, $post ) {
		if ( 'publish' !== $new || 'publish' === $old || 'job_listing' !== $post->post_type ) {
			return;
		}

		if ( ! self::enabled() || get_post_meta( $post->ID, self::SENT, true ) ) {
			return;
		}

		// Marked before sending rather than after: a half-finished run that
		// is retried would otherwise mail the same people twice, and being
		// told twice about one job reads as a broken site.
		update_post_meta( $post->ID, self::SENT, 1 );

		self::send( (int) $post->ID );
	}

	/**
	 * Who should hear about this vacancy, and tell them.
	 *
	 * @param int $job_id Job.
	 * @return int How many were mailed.
	 */
	public static function send( $job_id ) {
		global $wpdb;

		$terms = get_the_terms( $job_id, Sectors::TAXONOMY );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return 0;
		}

		$sector = (int) $terms[0]->term_id;
		$table  = Install::table( 'candidates' );

		// Only people who confirmed an email address and are in this trade.
		// Nobody is signed up to a marketing list here; they are told about
		// the thing they said they do.
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT id, user_id, full_name, email FROM {$table}
				 WHERE sector_id = %d AND email IS NOT NULL AND email <> '' AND status <> 'demo'
				 LIMIT 500",
				$sector
			)
		);

		$title = get_the_title( $job_id );
		$link  = (string) get_permalink( $job_id );
		$loc   = (string) get_post_meta( $job_id, '_job_location', true );
		$sent  = 0;

		foreach ( $rows as $r ) {
			if ( ! Accounts::is_verified( (int) $r->user_id ) ) {
				continue;
			}

			$name = trim( (string) $r->full_name );

			$body  = sprintf(
				/* translators: %s: candidate name */
				__( 'Assalamu alaikum %s,', 'marwa-apply' ),
				$name ? $name : __( 'there', 'marwa-apply' )
			) . "\n\n";

			$body .= __( 'A new vacancy has opened in your trade:', 'marwa-apply' ) . "\n\n";
			$body .= $title . "\n";
			$body .= $loc ? $loc . "\n" : '';
			$body .= "\n" . $link . "\n\n";
			$body .= __( 'Marwa Overseas never charges you to apply. If anybody asks you for money in connection with this vacancy, tell us.', 'marwa-apply' ) . "\n\n";
			$body .= __( 'To stop these emails, open your account and set your trade to none, or reply to this message.', 'marwa-apply' ) . "\n";

			if ( wp_mail( $r->email, sprintf( /* translators: %s: job title */ __( 'New vacancy: %s', 'marwa-apply' ), $title ), $body ) ) {
				++$sent;
			}
		}

		return $sent;
	}
}
