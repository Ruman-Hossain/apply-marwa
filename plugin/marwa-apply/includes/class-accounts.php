<?php
/**
 * Candidate accounts — registration, sign-in, email verification.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Everything a candidate needs to get an account and come back to it.
 *
 * Two decisions shape this file.
 *
 * The phone number is the identity. Most Bangladeshi applicants have a phone
 * they keep for years and an email address they opened once to register for
 * something. So the phone is the WordPress username, it is normalised to a
 * single canonical form before anything touches the database, and it is
 * UNIQUE — which also stops the same person entering the pipeline twice under
 * two spellings of their name.
 *
 * Verification goes by email, not SMS. SMS in Bangladesh costs money per
 * message and Marwa is running this on a free stack. Email verification is
 * free, proves the address works, and the code path is identical if an SMS
 * gateway is bought later — only send_code() changes.
 */
final class Accounts {

	const CODE_META    = '_ma_verify_code';
	const CODE_EXPIRES = '_ma_verify_expires';
	const VERIFIED     = '_ma_email_verified';

	/**
	 * Errors from a failed section add, so the form can reopen showing them.
	 *
	 * @var array
	 */
	public static $record_errors = array();
	const CODE_TTL     = 1800; // 30 minutes.

	/**
	 * Hook up.
	 */
	public static function init() {
		add_shortcode( 'ma_register', array( __CLASS__, 'render_register' ) );
		add_shortcode( 'ma_sign_in', array( __CLASS__, 'render_sign_in' ) );
		add_shortcode( 'ma_verify', array( __CLASS__, 'render_verify' ) );
		add_shortcode( 'ma_account', array( __CLASS__, 'render_account' ) );

		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
	}

	// ---------------------------------------------------------------- input

	/**
	 * Reduce a Bangladeshi mobile number to one canonical form.
	 *
	 * The same number arrives as 01712345678, 8801712345678, +8801712345678
	 * and 01712-345678. Stored as typed, the UNIQUE constraint is worthless
	 * and the same person registers four times.
	 *
	 * @param string $raw As typed.
	 * @return string Canonical +8801XXXXXXXXX, or '' if it is not one.
	 */
	public static function normalise_phone( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw );

		if ( '' === $digits ) {
			return '';
		}

		// Strip the country code in whichever form it arrived.
		if ( 0 === strpos( $digits, '880' ) ) {
			$digits = substr( $digits, 3 );
		} elseif ( 0 === strpos( $digits, '00880' ) ) {
			$digits = substr( $digits, 5 );
		}

		// Local form is 11 digits starting 01; some people drop the leading 0.
		if ( 10 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = '0' . $digits;
		}

		if ( ! preg_match( '/^01[3-9]\d{8}$/', $digits ) ) {
			return '';
		}

		return '+880' . substr( $digits, 1 );
	}

	/**
	 * Readable form for display.
	 *
	 * @param string $phone Canonical phone.
	 * @return string
	 */
	public static function display_phone( $phone ) {
		return preg_replace( '/^\+880/', '0', (string) $phone );
	}

	// --------------------------------------------------------------- router

	/**
	 * Dispatch posted forms.
	 */
	public static function handle() {
		if ( empty( $_POST['ma_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['ma_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! isset( $_POST['ma_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ma_nonce'] ) ), 'ma_' . $action ) ) {
			self::fail( __( 'That form expired. Please try again.', 'marwa-apply' ) );

			return;
		}

		// Bots fill in every field they find, including one that is hidden and
		// asked for nothing. A human never touches it.
		if ( ! empty( $_POST['ma_website'] ) ) {
			return;
		}

		switch ( $action ) {
			case 'register':
				self::do_register();
				break;
			case 'sign_in':
				self::do_sign_in();
				break;
			case 'verify':
				self::do_verify();
				break;
			case 'resend':
				self::do_resend();
				break;
			case 'profile':
				Profile::save();
				break;
			case 'apply':
				Applications::do_apply();
				break;
			case 'withdraw':
				Applications::do_withdraw();
				break;
			case 'upload':
				Documents::do_upload();
				break;
			case 'doc_delete':
				Documents::do_delete();
				break;
			case 'exp_add':
				Experience::do_add();
				break;
			case 'exp_delete':
				Experience::do_delete();
				break;
			case 'rec_add':
				self::$record_errors = Records::do_add();
				break;
			case 'rec_delete':
				Records::do_delete();
				break;
			case 'emp_profile':
				Employer::save();
				break;
			case 'emp_register':
				self::$record_errors = Employer::do_register();
				break;
			case 'emp_vacancy':
				Employer::do_vacancy();
				break;
			case 'emp_doc':
				self::$record_errors = Employer::do_doc();
				break;
		}
	}

	// ------------------------------------------------------------- register

	/**
	 * Create the account.
	 */
	private static function do_register() {
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security

		$name    = sanitize_text_field( $post['ma_name'] ?? '' );
		$email   = sanitize_email( $post['ma_email'] ?? '' );
		$phone   = self::normalise_phone( $post['ma_phone'] ?? '' );
		$pass    = (string) ( $post['ma_pass'] ?? '' );
		$pass2   = (string) ( $post['ma_pass2'] ?? '' );
		$consent = ! empty( $post['ma_consent'] );

		$errors = array();

		if ( mb_strlen( $name ) < 3 ) {
			$errors['ma_name'] = __( 'Please enter your full name as it appears on your passport or NID.', 'marwa-apply' );
		}

		if ( ! is_email( $email ) ) {
			$errors['ma_email'] = __( 'Please enter a working email address — your verification code goes there.', 'marwa-apply' );
		}

		if ( '' === $phone ) {
			$errors['ma_phone'] = __( 'Please enter a Bangladeshi mobile number, for example 01712345678.', 'marwa-apply' );
		}

		if ( strlen( $pass ) < 8 ) {
			$errors['ma_pass'] = __( 'Your password must be at least 8 characters.', 'marwa-apply' );
		} elseif ( $pass !== $pass2 ) {
			$errors['ma_pass2'] = __( 'The two passwords do not match.', 'marwa-apply' );
		}

		if ( ! $consent ) {
			$errors['ma_consent'] = __( 'We cannot create your file without your agreement.', 'marwa-apply' );
		}

		if ( ! $errors ) {
			if ( email_exists( $email ) ) {
				$errors['ma_email'] = __( 'That email address already has an account. Please sign in instead.', 'marwa-apply' );
			}

			if ( username_exists( $phone ) ) {
				$errors['ma_phone'] = __( 'That mobile number already has an account. Please sign in instead.', 'marwa-apply' );
			}
		}

		if ( $errors ) {
			self::fail( __( 'Please check the fields marked below.', 'marwa-apply' ), $errors, compact( 'name', 'email' ) + array( 'phone' => self::display_phone( $phone ) ) );

			return;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $phone,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name,
				'first_name'   => $name,
				'role'         => Roles::CANDIDATE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			self::fail( __( 'We could not create the account. Please try again in a moment.', 'marwa-apply' ) );

			return;
		}

		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB
			Install::table( 'candidates' ),
			array(
				'agency_id'  => Install::current_agency_id(),
				'user_id'    => $user_id,
				'full_name'  => $name,
				'phone'      => $phone,
				'email'      => $email,
				'status'     => 'new',
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		update_user_meta( $user_id, '_ma_consent_at', current_time( 'mysql' ) );

		self::send_code( $user_id );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		wp_safe_redirect( add_query_arg( 'sent', '1', Pages::url( 'verify' ) ) );
		exit;
	}

	// -------------------------------------------------------------- sign in

	/**
	 * Sign in with either the phone or the email.
	 */
	private static function do_sign_in() {
		$post  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security
		$who   = sanitize_text_field( $post['ma_login'] ?? '' );
		$pass  = (string) ( $post['ma_pass'] ?? '' );
		$throt = 'ma_fail_' . md5( self::client_ip() );

		if ( (int) get_transient( $throt ) >= 8 ) {
			self::fail( __( 'Too many attempts. Please wait fifteen minutes and try again.', 'marwa-apply' ) );

			return;
		}

		$phone = self::normalise_phone( $who );
		$login = $phone ? $phone : $who;

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $pass,
				'remember'      => ! empty( $post['ma_remember'] ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			set_transient( $throt, (int) get_transient( $throt ) + 1, 15 * MINUTE_IN_SECONDS );

			// Deliberately vague: saying which half was wrong tells an attacker
			// which accounts exist.
			self::fail( __( 'We could not sign you in. Please check the number or email and the password.', 'marwa-apply' ), array(), array( 'login' => $who ) );

			return;
		}

		delete_transient( $throt );

		wp_safe_redirect( self::after_login_url( $user ) );
		exit;
	}

	/**
	 * Where a signed-in candidate lands.
	 *
	 * @param \WP_User $user User.
	 * @return string
	 */
	private static function after_login_url( $user ) {
		// An employer landing on the candidate account page would see an empty
		// jobseeker file and conclude the portal is broken.
		if ( in_array( Roles::EMPLOYER, (array) $user->roles, true ) ) {
			return Pages::url( 'employer' );
		}

		if ( ! get_user_meta( $user->ID, self::VERIFIED, true ) && in_array( Roles::CANDIDATE, (array) $user->roles, true ) ) {
			return Pages::url( 'verify' );
		}

		return Pages::url( 'account' );
	}

	// --------------------------------------------------------- verification

	/**
	 * Issue a fresh code and email it.
	 *
	 * The code is stored hashed. A leaked database row should not hand over a
	 * working code any more than it hands over a working password.
	 *
	 * @param int $user_id User.
	 * @return bool Whether the mail was accepted for delivery.
	 */
	public static function send_code( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		$code = (string) wp_rand( 100000, 999999 );

		update_user_meta( $user_id, self::CODE_META, wp_hash_password( $code ) );
		update_user_meta( $user_id, self::CODE_EXPIRES, time() + self::CODE_TTL );

		$subject = __( 'Your Marwa Overseas verification code', 'marwa-apply' );

		$lines = array(
			sprintf( /* translators: %s: name */ __( 'Hello %s,', 'marwa-apply' ), $user->display_name ),
			'',
			__( 'Your verification code is:', 'marwa-apply' ),
			'',
			'    ' . $code,
			'',
			__( 'It expires in 30 minutes. If you did not create an account with Marwa Overseas, you can ignore this email.', 'marwa-apply' ),
			'',
			__( 'Marwa Overseas — Government Licence No. RL 2831', 'marwa-apply' ),
		);

		return wp_mail( $user->user_email, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Check a submitted code.
	 */
	private static function do_verify() {
		if ( ! is_user_logged_in() ) {
			self::fail( __( 'Please sign in first.', 'marwa-apply' ) );

			return;
		}

		$user_id = get_current_user_id();
		$code    = preg_replace( '/\D+/', '', (string) ( $_POST['ma_code'] ?? '' ) ); // phpcs:ignore WordPress.Security
		$hash    = get_user_meta( $user_id, self::CODE_META, true );
		$expires = (int) get_user_meta( $user_id, self::CODE_EXPIRES, true );

		if ( ! $hash || $expires < time() ) {
			self::fail( __( 'That code has expired. Send yourself a new one below.', 'marwa-apply' ) );

			return;
		}

		$throt = 'ma_code_' . $user_id;

		if ( (int) get_transient( $throt ) >= 6 ) {
			self::fail( __( 'Too many attempts. Please request a new code.', 'marwa-apply' ) );

			return;
		}

		if ( ! wp_check_password( $code, $hash ) ) {
			set_transient( $throt, (int) get_transient( $throt ) + 1, 15 * MINUTE_IN_SECONDS );
			self::fail( __( 'That code is not right. Please check the email and try again.', 'marwa-apply' ) );

			return;
		}

		update_user_meta( $user_id, self::VERIFIED, current_time( 'mysql' ) );
		delete_user_meta( $user_id, self::CODE_META );
		delete_user_meta( $user_id, self::CODE_EXPIRES );
		delete_transient( $throt );

		wp_safe_redirect( add_query_arg( 'verified', '1', Pages::url( 'account' ) ) );
		exit;
	}

	/**
	 * Send another code.
	 */
	private static function do_resend() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$throt = 'ma_resend_' . get_current_user_id();

		if ( get_transient( $throt ) ) {
			self::fail( __( 'A code was just sent. Please wait a minute before asking for another.', 'marwa-apply' ) );

			return;
		}

		set_transient( $throt, 1, MINUTE_IN_SECONDS );
		self::send_code( get_current_user_id() );

		wp_safe_redirect( add_query_arg( 'sent', '1', Pages::url( 'verify' ) ) );
		exit;
	}

	// ---------------------------------------------------------------- state

	/**
	 * Stash errors for the next render.
	 *
	 * @param string $message Headline message.
	 * @param array  $fields  Field slug => message.
	 * @param array  $values  Values to put back in the form.
	 */
	private static function fail( $message, $fields = array(), $values = array() ) {
		set_transient(
			self::state_key(),
			array(
				'message' => $message,
				'fields'  => $fields,
				'values'  => $values,
			),
			120
		);
	}

	/**
	 * Read and clear the stashed state.
	 *
	 * @return array
	 */
	private static function state() {
		$state = get_transient( self::state_key() );

		if ( $state ) {
			delete_transient( self::state_key() );
		}

		return is_array( $state ) ? $state : array();
	}

	/**
	 * A key tied to this visitor, so one person's errors never reach another.
	 *
	 * @return string
	 */
	private static function state_key() {
		return 'ma_state_' . md5( self::client_ip() . '|' . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' ) );
	}

	/**
	 * Best available client address.
	 *
	 * @return string
	 */
	private static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}

	// --------------------------------------------------------------- render

	/**
	 * Open a form.
	 *
	 * @param string $action Action slug.
	 * @return string
	 */
	private static function form_open( $action ) {
		return '<form method="post" class="ap-form" novalidate>'
			. wp_nonce_field( 'ma_' . $action, 'ma_nonce', true, false )
			. '<input type="hidden" name="ma_action" value="' . esc_attr( $action ) . '">'
			. '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input type="text" name="ma_website" tabindex="-1" autocomplete="off"></label></div>';
	}

	/**
	 * One field.
	 *
	 * @param array $args Field spec.
	 * @param array $state Error state.
	 * @return string
	 */
	private static function field( $args, $state ) {
		$name  = $args['name'];
		$slug  = str_replace( 'ma_', '', $name );
		$err   = $state['fields'][ $name ] ?? '';
		$value = $args['value'] ?? ( $state['values'][ $slug ] ?? '' );

		$out  = '<div class="ap-field' . ( $err ? ' is-error' : '' ) . '">';
		$out .= '<label for="' . esc_attr( $name ) . '">' . esc_html( $args['label'] );

		if ( ! empty( $args['hint'] ) ) {
			$out .= '<span class="hint">' . esc_html( $args['hint'] ) . '</span>';
		}

		$out .= '</label>';
		$out .= sprintf(
			'<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" autocomplete="%4$s"%5$s>',
			esc_attr( $args['type'] ?? 'text' ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $args['autocomplete'] ?? 'off' ),
			! empty( $args['inputmode'] ) ? ' inputmode="' . esc_attr( $args['inputmode'] ) . '"' : ''
		);

		if ( $err ) {
			$out .= '<span class="err">' . esc_html( $err ) . '</span>';
		}

		return $out . '</div>';
	}

	/**
	 * Headline message block.
	 *
	 * @param array $state State.
	 * @return string
	 */
	private static function notice( $state ) {
		if ( empty( $state['message'] ) ) {
			return '';
		}

		return '<div class="ap-msg bad">' . esc_html( $state['message'] ) . '</div>';
	}

	/**
	 * Registration form.
	 *
	 * @return string
	 */
	public static function render_register() {
		if ( is_user_logged_in() ) {
			return '<div class="ap-shell"><div class="ap-card"><p>' . esc_html__( 'You are already signed in.', 'marwa-apply' ) . ' <a href="' . esc_url( Pages::url( 'account' ) ) . '">' . esc_html__( 'Go to your account', 'marwa-apply' ) . '</a></p></div></div>';
		}

		$state = self::state();

		$out  = '<div class="ap-shell"><div class="ap-card">';
		$out .= '<h2>' . esc_html__( 'Create your account', 'marwa-apply' ) . '</h2>';
		$out .= '<p class="ap-note">' . esc_html__( 'One account, one file. Everything you send us stays in it — you will not be asked for the same document twice.', 'marwa-apply' ) . '</p>';
		$out .= self::notice( $state );
		$out .= self::form_open( 'register' );

		$out .= self::field(
			array(
				'name'         => 'ma_name',
				'label'        => __( 'Full name', 'marwa-apply' ),
				'hint'         => __( 'Exactly as written on your passport or NID.', 'marwa-apply' ),
				'autocomplete' => 'name',
			),
			$state
		);

		$out .= '<div class="ap-row two">';
		$out .= self::field(
			array(
				'name'         => 'ma_phone',
				'label'        => __( 'Mobile number', 'marwa-apply' ),
				'hint'         => __( 'For example 01712345678.', 'marwa-apply' ),
				'type'         => 'tel',
				'inputmode'    => 'numeric',
				'autocomplete' => 'tel',
			),
			$state
		);
		$out .= self::field(
			array(
				'name'         => 'ma_email',
				'label'        => __( 'Email address', 'marwa-apply' ),
				'hint'         => __( 'Your verification code is sent here.', 'marwa-apply' ),
				'type'         => 'email',
				'autocomplete' => 'email',
			),
			$state
		);
		$out .= '</div>';

		$out .= '<div class="ap-row two">';
		$out .= self::field(
			array(
				'name'         => 'ma_pass',
				'label'        => __( 'Password', 'marwa-apply' ),
				'hint'         => __( 'At least 8 characters.', 'marwa-apply' ),
				'type'         => 'password',
				'autocomplete' => 'new-password',
			),
			$state
		);
		$out .= self::field(
			array(
				'name'         => 'ma_pass2',
				'label'        => __( 'Repeat password', 'marwa-apply' ),
				'type'         => 'password',
				'autocomplete' => 'new-password',
			),
			$state
		);
		$out .= '</div>';

		$consent_err = $state['fields']['ma_consent'] ?? '';

		$out .= '<div class="ap-field' . ( $consent_err ? ' is-error' : '' ) . '">';
		$out .= '<label style="font-weight:400"><input type="checkbox" name="ma_consent" value="1"> ';
		$out .= esc_html__( 'I agree that Marwa Overseas may hold my details and documents in order to consider me for overseas employment, and may share them with an employer only when I apply to that employer.', 'marwa-apply' );
		$out .= '</label>';

		if ( $consent_err ) {
			$out .= '<span class="err">' . esc_html( $consent_err ) . '</span>';
		}

		$out .= '</div>';

		$out .= '<button type="submit" class="btn primary">' . esc_html__( 'Create account', 'marwa-apply' ) . '</button>';
		$out .= '</form>';
		$out .= '<p class="ap-note" style="margin-top:18px">' . esc_html__( 'Already registered?', 'marwa-apply' ) . ' <a href="' . esc_url( Pages::url( 'sign-in' ) ) . '">' . esc_html__( 'Sign in', 'marwa-apply' ) . '</a></p>';
		$out .= '</div></div>';

		return $out;
	}

	/**
	 * Sign-in form.
	 *
	 * @return string
	 */
	public static function render_sign_in() {
		if ( is_user_logged_in() ) {
			return '<div class="ap-shell"><div class="ap-card"><p>' . esc_html__( 'You are signed in.', 'marwa-apply' ) . ' <a href="' . esc_url( Pages::url( 'account' ) ) . '">' . esc_html__( 'Go to your account', 'marwa-apply' ) . '</a></p></div></div>';
		}

		$state = self::state();

		$out  = '<div class="ap-shell"><div class="ap-card">';
		$out .= '<h2>' . esc_html__( 'Sign in', 'marwa-apply' ) . '</h2>';
		$out .= self::notice( $state );
		$out .= self::form_open( 'sign_in' );

		$out .= self::field(
			array(
				'name'         => 'ma_login',
				'label'        => __( 'Mobile number or email', 'marwa-apply' ),
				'autocomplete' => 'username',
			),
			$state
		);

		$out .= self::field(
			array(
				'name'         => 'ma_pass',
				'label'        => __( 'Password', 'marwa-apply' ),
				'type'         => 'password',
				'autocomplete' => 'current-password',
			),
			$state
		);

		$out .= '<div class="ap-field"><label style="font-weight:400"><input type="checkbox" name="ma_remember" value="1" checked> ' . esc_html__( 'Keep me signed in on this device', 'marwa-apply' ) . '</label></div>';
		$out .= '<button type="submit" class="btn primary">' . esc_html__( 'Sign in', 'marwa-apply' ) . '</button>';
		$out .= '</form>';

		$out .= '<p class="ap-note" style="margin-top:18px">';
		$out .= '<a href="' . esc_url( wp_lostpassword_url( Pages::url( 'sign-in' ) ) ) . '">' . esc_html__( 'Forgotten your password?', 'marwa-apply' ) . '</a><br>';
		$out .= esc_html__( 'No account yet?', 'marwa-apply' ) . ' <a href="' . esc_url( Pages::url( 'register' ) ) . '">' . esc_html__( 'Create one', 'marwa-apply' ) . '</a>';
		$out .= '</p></div></div>';

		return $out;
	}

	/**
	 * Verification form.
	 *
	 * @return string
	 */
	public static function render_verify() {
		if ( ! is_user_logged_in() ) {
			return '<div class="ap-shell"><div class="ap-card"><p>' . esc_html__( 'Please sign in first.', 'marwa-apply' ) . ' <a href="' . esc_url( Pages::url( 'sign-in' ) ) . '">' . esc_html__( 'Sign in', 'marwa-apply' ) . '</a></p></div></div>';
		}

		$user_id = get_current_user_id();

		if ( get_user_meta( $user_id, self::VERIFIED, true ) ) {
			return '<div class="ap-shell"><div class="ap-card"><div class="ap-msg ok">' . esc_html__( 'Your email address is already confirmed.', 'marwa-apply' ) . '</div><p><a href="' . esc_url( Pages::url( 'account' ) ) . '">' . esc_html__( 'Go to your account', 'marwa-apply' ) . '</a></p></div></div>';
		}

		$state = self::state();
		$user  = wp_get_current_user();

		$out  = '<div class="ap-shell"><div class="ap-card">';
		$out .= '<h2>' . esc_html__( 'Confirm your email address', 'marwa-apply' ) . '</h2>';

		if ( ! empty( $_GET['sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out .= '<div class="ap-msg ok">' . esc_html( sprintf( /* translators: %s: email address */ __( 'We sent a six-digit code to %s. It can take a minute to arrive — check your spam folder too.', 'marwa-apply' ), $user->user_email ) ) . '</div>';
		}

		$out .= self::notice( $state );
		$out .= self::form_open( 'verify' );
		$out .= self::field(
			array(
				'name'      => 'ma_code',
				'label'     => __( 'Six-digit code', 'marwa-apply' ),
				'inputmode' => 'numeric',
			),
			$state
		);
		$out .= '<button type="submit" class="btn primary">' . esc_html__( 'Confirm', 'marwa-apply' ) . '</button>';
		$out .= '</form>';

		$out .= self::form_open( 'resend' );
		$out .= '<button type="submit" class="btn" style="background:none;border:0;padding:0;text-decoration:underline;cursor:pointer">' . esc_html__( 'Send me another code', 'marwa-apply' ) . '</button>';
		$out .= '</form>';

		$out .= '<p class="ap-note" style="margin-top:18px">' . esc_html__( 'You can look around while this is pending, but you cannot apply to a job until your email is confirmed.', 'marwa-apply' ) . '</p>';
		$out .= '</div></div>';

		return $out;
	}

	/**
	 * The account page.
	 *
	 * Thin on purpose: the profile form and the applications list land here
	 * next, and this is the shape they slot into.
	 *
	 * @return string
	 */
	public static function render_account() {
		if ( ! is_user_logged_in() ) {
			return '<div class="ap-shell"><div class="ap-card"><h2>' . esc_html__( 'Sign in to continue', 'marwa-apply' ) . '</h2><p><a href="' . esc_url( Pages::url( 'sign-in' ) ) . '">' . esc_html__( 'Sign in', 'marwa-apply' ) . '</a> ' . esc_html__( 'or', 'marwa-apply' ) . ' <a href="' . esc_url( Pages::url( 'register' ) ) . '">' . esc_html__( 'create an account', 'marwa-apply' ) . '</a>.</p></div></div>';
		}

		global $wpdb;

		$user = wp_get_current_user();
		$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'candidates' ) . ' WHERE user_id = %d', $user->ID ) ); // phpcs:ignore WordPress.DB

		$out = Shell::open( 'account' );

		if ( ! empty( $_GET['verified'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out .= '<div class="ap-msg ok">' . esc_html__( 'Email confirmed. Your account is active.', 'marwa-apply' ) . '</div>';
		}

		if ( ! empty( $_GET['withdrawn'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out .= '<div class="ap-msg ok">' . esc_html__( 'Application withdrawn. You can apply again later if the job is still open.', 'marwa-apply' ) . '</div>';
		}

		$out .= '<div class="ap-card">';
		$out .= '<h2>' . esc_html( sprintf( /* translators: %s: name */ __( 'Hello, %s', 'marwa-apply' ), $user->display_name ) ) . '</h2>';

		$out .= '<p class="ap-note">';
		$out .= esc_html__( 'Mobile:', 'marwa-apply' ) . ' <strong>' . esc_html( $row ? self::display_phone( $row->phone ) : '—' ) . '</strong><br>';
		$out .= esc_html__( 'Email:', 'marwa-apply' ) . ' <strong>' . esc_html( $user->user_email ) . '</strong>';
		$out .= '</p>';

		if ( ! self::is_verified( $user->ID ) ) {
			$out .= '<div class="ap-msg bad">' . esc_html__( 'Your email address is not confirmed yet. You cannot apply to a job until it is.', 'marwa-apply' ) . ' <a href="' . esc_url( Pages::url( 'verify' ) ) . '">' . esc_html__( 'Confirm now', 'marwa-apply' ) . '</a></div>';
		}

		$out .= '</div>';

		$out .= '<div class="ap-card">';
		$pct = Profile::completeness( $row );
		$out .= '<h3>' . esc_html__( 'Your profile', 'marwa-apply' ) . '</h3>';
		$out .= '<ul class="ap-steps">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$out .= '<li class="' . ( $pct >= $i * 20 ? 'done' : '' ) . '"></li>';
		}
		$out .= '</ul>';
		$out .= '<p class="ap-note">' . esc_html( sprintf( /* translators: %d: percentage */ __( '%d%% complete. Employers see the profile, not the account.', 'marwa-apply' ), $pct ) ) . '</p>';
		$out .= '<p><a class="btn primary" href="' . esc_url( Pages::url( 'profile' ) ) . '">' . esc_html( $pct > 0 ? __( 'Continue my profile', 'marwa-apply' ) : __( 'Start my profile', 'marwa-apply' ) ) . '</a> &nbsp; <a class="btn" href="' . esc_url( Pages::url( 'documents' ) ) . '">' . esc_html__( 'My documents', 'marwa-apply' ) . '</a> &nbsp; <a class="btn" href="' . esc_url( CV::url( (int) $row->id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'My CV', 'marwa-apply' ) . '</a> &nbsp; <a class="btn" href="' . esc_url( home_url( '/jobs/' ) ) . '">' . esc_html__( 'Browse jobs', 'marwa-apply' ) . '</a> &nbsp; <a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Sign out', 'marwa-apply' ) . '</a></p>';
		$out .= '</div>';
		$out .= Applications::mine( $row );

		return $out . Shell::close();
	}

	/**
	 * Has this user confirmed their email?
	 *
	 * @param int $user_id User, defaults to current.
	 * @return bool
	 */
	public static function is_verified( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();

		return $user_id && (bool) get_user_meta( $user_id, self::VERIFIED, true );
	}
}
