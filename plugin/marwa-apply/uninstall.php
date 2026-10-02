<?php
/**
 * Uninstall.
 *
 * Only runs when the plugin is deleted, never on deactivation.
 *
 * Candidate data is NOT dropped by default. Deleting a plugin should not
 * silently destroy several thousand people's records — if that is really the
 * intent, set MA_DELETE_DATA_ON_UNINSTALL in wp-config.php first.
 *
 * @package MarwaApply
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-roles.php';

\MarwaApply\Roles::remove();

delete_option( 'ma_needs_sector_seed' );

if ( ! defined( 'MA_DELETE_DATA_ON_UNINSTALL' ) || ! MA_DELETE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;

$ma_tables = array( 'status_log', 'applications', 'documents', 'experience', 'candidates', 'employers', 'agencies' );

foreach ( $ma_tables as $ma_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'ma_' . $ma_table ); // phpcs:ignore WordPress.DB
}

delete_option( 'ma_db_version' );
delete_option( 'ma_storage_dir' );
