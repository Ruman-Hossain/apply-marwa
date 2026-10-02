<?php
/**
 * Plugin Name:       Marwa Apply
 * Plugin URI:        https://apply.marwaoverseas.com/
 * Description:       Candidate application platform for Marwa Overseas — profiles, applications, pipeline and protected document storage. Jobs are handled by WP Job Manager; everything candidate-side lives here.
 * Version:     1.2.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Md Ruman Hossain
 * Author URI:        https://github.com/Ruman-Hossain
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       marwa-apply
 * Domain Path:       /languages
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

define( 'MA_VERSION', '1.2.2' );
define( 'MA_FILE', __FILE__ );
define( 'MA_DIR', plugin_dir_path( __FILE__ ) );
define( 'MA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Map MarwaApply\Some_Class to includes/class-some-class.php.
 *
 * Deliberately simple: no Composer autoloader in the plugin root, so the
 * plugin stays installable as a plain zip on shared hosting.
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$relative = substr( $class, strlen( __NAMESPACE__ ) + 1 );
		$file     = MA_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\\Install', 'deactivate' ) );

// The demonstration-data build tool. Plain functions rather than a class, and
// required explicitly because the autoloader only resolves class-*.php. It
// registers an admin screen and nothing else; nothing in it runs on its own.
require_once MA_DIR . 'includes/demo.php';

add_action( 'plugins_loaded', array( __NAMESPACE__ . '\\Plugin', 'instance' ) );
