<?php
/**
 * Plugin bootstrap.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin up. Deliberately thin — it registers things and gets out
 * of the way, so each concern stays testable on its own.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Boot once.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	private function hooks() {
		add_action( 'init', array( Sectors::class, 'register_taxonomy' ), 5 );
		add_action( 'init', array( Accounts::class, 'init' ) );
		add_action( 'init', array( Profile::class, 'init' ) );
		add_action( 'init', array( Applications::class, 'init' ) );
		add_action( 'init', array( Documents::class, 'init' ) );
		add_action( 'init', array( JobList::class, 'init' ) );
		add_action( 'init', array( CV::class, 'init' ) );
		add_action( 'init', array( Home::class, 'init' ) );
		add_action( 'init', array( Header::class, 'init' ) );
		add_action( 'init', array( Trust::class, 'init' ) );
		add_action( 'init', array( Guides::class, 'init' ), 4 );
		add_action( 'init', array( Employer::class, 'init' ) );
		add_action( 'init', array( Detail::class, 'init' ) );
		add_action( 'init', array( Alerts::class, 'init' ) );
		add_action( 'init', array( Settings::class, 'init' ) );
		add_action( 'init', array( Vetting::class, 'maybe_serve' ), 22 );
		add_action( 'admin_menu', array( Pool::class, 'menu' ), 18 );
		add_action( 'admin_init', array( Posting::class, 'handle' ) );
		add_action( 'admin_menu', array( Posting::class, 'menu' ), 21 );
		add_action( 'admin_init', array( EmployersAdmin::class, 'handle' ) );
		add_action( 'admin_menu', array( EmployersAdmin::class, 'menu' ), 19 );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_head', array( Pages::class, 'noindex' ) );
		add_action( 'wp_head', array( $this, 'form_styles' ), 100 );
		add_action( 'wp_head', array( Header::class, 'styles' ), 101 );
		add_action( 'wp_head', array( $this, 'wide_styles' ), 102 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'admin_init', array( Install::class, 'maybe_upgrade' ) );
		add_action( 'admin_init', array( Status::class, 'handle' ) );
		add_action( 'admin_init', array( Admin::class, 'handle' ) );
		add_action( 'show_user_profile', array( Admin::class, 'office_field' ) );
		add_action( 'edit_user_profile', array( Admin::class, 'office_field' ) );
		add_action( 'personal_options_update', array( Admin::class, 'save_office_field' ) );
		add_action( 'edit_user_profile_update', array( Admin::class, 'save_office_field' ) );
		add_action( 'admin_menu', array( Status::class, 'menu' ) );
		add_action( 'admin_menu', array( Admin::class, 'menu' ) );
		add_action( 'admin_menu', array( $this, 'hide_upsells' ), 99 );
		add_action( 'admin_notices', array( $this, 'storage_notice' ) );
	}

	/**
	 * Translations. Bengali is the primary interface language, so this is not
	 * an afterthought — ship bn_BD in /languages.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'marwa-apply', false, dirname( plugin_basename( MA_FILE ) ) . '/languages' );
	}

	/**
	 * Form styling, shipped with the plugin rather than the theme.
	 *
	 * The design system synced from marwaoverseas.com is written for a
	 * marketing site and sizes form controls for the occasional contact form:
	 * it squeezes 24px of padding and a 25.5px line-height into a 38px box
	 * with overflow:clip, which slices the bottom off every descender, and it
	 * drops labels to 11.5px. Unreadable on a phone.
	 *
	 * These rules live here, not in the child theme, for two reasons: the
	 * synced stylesheet is overwritten on every sync, and a plugin that draws
	 * forms should carry the CSS that makes them legible. !important is
	 * deliberate — it is overriding a stylesheet this plugin does not own.
	 */
	public function form_styles() {
		?>
<style id="marwa-apply-forms">
.mo .ap-field{margin-bottom:20px}
.mo .ap-field label{display:block;font-size:15px!important;line-height:1.45!important;font-weight:600;margin-bottom:7px;color:var(--ink,#0B2239);text-transform:none!important;letter-spacing:normal!important}
.mo .ap-field label .hint{display:block;font-size:13.5px!important;line-height:1.55!important;font-weight:400;color:var(--muted,#5B6B7C);margin-top:3px;text-transform:none!important;letter-spacing:normal!important}
.mo .ap-field input[type="text"],
.mo .ap-field input[type="tel"],
.mo .ap-field input[type="email"],
.mo .ap-field input[type="password"],
.mo .ap-field input[type="date"],
.mo .ap-field input[type="month"],
.mo .ap-field input[type="number"],
.mo .ap-field input[type="search"],
.mo .ap-field input[type="file"],
.mo .ap-field select,
.mo .ap-field textarea{
	display:block;width:100%;
	height:auto!important;min-height:50px;max-height:none!important;
	padding:13px 14px!important;
	font-size:16px!important;line-height:1.45!important;
	font-family:inherit!important;
	overflow:visible!important;
	box-sizing:border-box!important;
	border:1px solid var(--line,#E3E0D9)!important;border-radius:8px!important;
	background:#fff!important;color:var(--ink,#0B2239)!important;
}
.mo .ap-field textarea{min-height:96px;line-height:1.6!important}
.mo .ap-field select{appearance:none;-webkit-appearance:none;padding-right:38px!important;
	background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath fill='%235B6B7C' d='M1 1l5 5 5-5'/%3E%3C/svg%3E")!important;
	background-repeat:no-repeat!important;background-position:right 14px center!important}
.mo .ap-field input:focus,.mo .ap-field select:focus,.mo .ap-field textarea:focus{
	outline:2px solid var(--accent,#A93A2C)!important;outline-offset:1px;border-color:var(--accent,#A93A2C)!important}
.mo .ap-field input[type="checkbox"]{width:auto!important;min-height:0!important;height:auto!important;
	margin-right:8px;transform:scale(1.25);vertical-align:middle;padding:0!important}
.mo .ap-field.is-error input,.mo .ap-field.is-error select,.mo .ap-field.is-error textarea{border-color:#B4231A!important}
.mo .ap-field .err{display:block;font-size:13.5px;line-height:1.5;color:#B4231A;margin-top:6px}
.mo .ap-card .btn,.mo .ap-shell .btn{display:inline-block;line-height:1.4;padding:12px 22px;border-radius:8px;
	font-size:15px;text-decoration:none;cursor:pointer;border:1px solid var(--line,#E3E0D9);background:#fff;color:var(--ink,#0B2239)}
.mo .ap-card .btn.primary,.mo .ap-shell .btn.primary{background:var(--accent,#A93A2C);border-color:var(--accent,#A93A2C);color:#fff}
.mo .ap-card h2,.mo .ap-card h3{line-height:1.3;margin-top:0}
.mo .ap-note{line-height:1.65}
</style>
		<?php
	}

	/**
	 * Is this one of the rail / content / rail pages?
	 *
	 * Register, sign in and confirm-your-email are deliberately absent: a
	 * form read once wants a narrow measure, and there is no file to
	 * navigate around yet.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	private static function is_wide( $post ) {
		foreach ( array( 'ma_home', 'ma_jobs', 'ma_account', 'ma_profile', 'ma_documents' ) as $tag ) {
			if ( has_shortcode( (string) $post->post_content, $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mark the pages that are laid out rail / content / rail.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		$post = get_post();

		if ( $post && ( self::is_wide( $post ) ) ) {
			$classes[] = 'ma-wide';
		}

		return $classes;
	}

	/**
	 * Let the wide pages out of the theme's content container.
	 *
	 * Astra pins .ast-container to 1240px, which is right for an article and
	 * wrong for a three-column listing: no amount of width set further in can
	 * exceed it, so a rail / content / rail layout simply has nowhere to go.
	 * Released only on the two pages that need it, so every form page keeps
	 * the comfortable reading measure the theme intends.
	 */
	public function wide_styles() {
		if ( ! is_page() ) {
			return;
		}

		$post = get_post();

		if ( ! $post || ! self::is_wide( $post ) ) {
			return;
		}

		?>
<style id="marwa-apply-wide">
body.ma-wide .ast-container{max-width:none!important;width:100%!important;padding-left:0!important;padding-right:0!important}
body.ma-wide #primary,body.ma-wide .site-main,body.ma-wide .entry-content{max-width:none!important;width:100%!important;margin-left:0!important;margin-right:0!important}
body.ma-wide .entry-header,body.ma-wide .ast-single-post-order,body.ma-wide .page-title{display:none}
body.ma-wide #content{overflow-x:clip}
</style>
		<?php
	}

	/**
	 * Remove WP Job Manager's greyed-out "PRO" menu entries.
	 *
	 * Applications and Resumes are paid add-ons we deliberately replaced: this
	 * plugin's own tables do both jobs, and do them better for overseas
	 * recruitment. Leaving the upsells in the sidebar sends Marwa staff to a
	 * sales page for something they already have.
	 */
	public function hide_upsells() {
		$parent = 'edit.php?post_type=job_listing';

		if ( empty( $GLOBALS['submenu'][ $parent ] ) ) {
			return;
		}

		// Matched on the label rather than the slug: the slugs are the add-on
		// vendor's to change, the words "Pro" and "Upgrade" are what actually
		// identify a sales page.
		foreach ( $GLOBALS['submenu'][ $parent ] as $key => $item ) {
			$label = wp_strip_all_tags( isset( $item[0] ) ? $item[0] : '' );

			if ( preg_match( '/\b(Pro|Upgrade|Marketplace)\b/i', $label ) ) {
				unset( $GLOBALS['submenu'][ $parent ][ $key ] );
			}
		}
	}

	/**
	 * Warn loudly if candidate documents ended up somewhere web-reachable.
	 *
	 * This is the one failure mode worth shouting about: a passport scan under
	 * a guessable URL is a breach, not a bug to get to later.
	 */
	public function storage_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$state = Storage::health();

		if ( 'secure' === $state['status'] ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s"><p><strong>%2$s</strong> %3$s</p><p><code>%4$s</code></p></div>',
			'error' === $state['status'] ? 'error' : 'warning',
			esc_html__( 'Marwa Apply — document storage:', 'marwa-apply' ),
			esc_html( $state['message'] ),
			esc_html( $state['path'] )
		);
	}
}
