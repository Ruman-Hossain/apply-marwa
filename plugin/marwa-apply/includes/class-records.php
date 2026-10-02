<?php
/**
 * Repeatable profile sections.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * Education, training, certificates, skills and referees.
 *
 * One engine rather than five near-identical classes. Each section is a row
 * in defs(): a table, a set of fields, and the columns to show back. Adding
 * "Driving licences" later is a dozen lines here, not a new file.
 *
 * Every section is its own form, posting on its own. That is not tidiness —
 * a candidate adding a fourth certificate on a phone must not be able to lose
 * the first three because one date was wrong.
 */
final class Records {

	/**
	 * Section definitions.
	 *
	 * @return array
	 */
	public static function defs() {
		return array(
			'education'    => array(
				'table'   => 'education',
				'title'   => __( 'Education', 'marwa-apply' ),
				'hint'    => __( 'Start with the highest you finished. If you did not finish school, leave this empty — most of the work Marwa places people in does not need it.', 'marwa-apply' ),
				'add'     => __( 'Add education', 'marwa-apply' ),
				'max'     => 8,
				'fields'  => array(
					'level'       => array( 'label' => __( 'Level', 'marwa-apply' ), 'type' => 'select', 'options' => 'levels', 'required' => true ),
					'institution' => array( 'label' => __( 'School, college or institute', 'marwa-apply' ), 'type' => 'text' ),
					'subject'     => array( 'label' => __( 'Group or subject', 'marwa-apply' ), 'type' => 'text' ),
					'result'      => array( 'label' => __( 'Result', 'marwa-apply' ), 'type' => 'text', 'hint' => __( 'GPA or division, as it appears on the certificate.', 'marwa-apply' ) ),
					'year_passed' => array( 'label' => __( 'Year passed', 'marwa-apply' ), 'type' => 'year' ),
				),
				'columns' => array( 'level', 'institution', 'subject', 'result', 'year_passed' ),
			),
			'training'     => array(
				'table'   => 'training',
				'title'   => __( 'Training', 'marwa-apply' ),
				'hint'    => __( 'Technical Training Centre courses, language courses, safety courses, anything an employer would count.', 'marwa-apply' ),
				'add'     => __( 'Add training', 'marwa-apply' ),
				'max'     => 10,
				'fields'  => array(
					'title'       => array( 'label' => __( 'Course', 'marwa-apply' ), 'type' => 'text', 'required' => true ),
					'institution' => array( 'label' => __( 'Where you did it', 'marwa-apply' ), 'type' => 'text' ),
					'duration'    => array( 'label' => __( 'How long', 'marwa-apply' ), 'type' => 'text', 'hint' => __( 'For example: 6 months, 240 hours.', 'marwa-apply' ) ),
					'year_done'   => array( 'label' => __( 'Year', 'marwa-apply' ), 'type' => 'year' ),
					'location'    => array( 'label' => __( 'District or country', 'marwa-apply' ), 'type' => 'text' ),
				),
				'columns' => array( 'title', 'institution', 'duration', 'year_done' ),
			),
			'certificates' => array(
				'table'   => 'certificates',
				'title'   => __( 'Certificates and exams', 'marwa-apply' ),
				'hint'    => __( 'JFT-Basic, JLPT, the Specified Skilled Worker skills test, BMET registration, a trade test, a driving licence, nursing registration. These decide which countries you can go to, so they are worth getting right.', 'marwa-apply' ),
				'add'     => __( 'Add a certificate', 'marwa-apply' ),
				'max'     => 14,
				'fields'  => array(
					'title'      => array( 'label' => __( 'Certificate or exam', 'marwa-apply' ), 'type' => 'text', 'required' => true, 'list' => 'exams' ),
					'authority'  => array( 'label' => __( 'Who issued it', 'marwa-apply' ), 'type' => 'text' ),
					'ref_no'     => array( 'label' => __( 'Certificate or roll number', 'marwa-apply' ), 'type' => 'text' ),
					'issued_on'  => array( 'label' => __( 'Issued', 'marwa-apply' ), 'type' => 'month' ),
					'expires_on' => array( 'label' => __( 'Expires', 'marwa-apply' ), 'type' => 'month', 'hint' => __( 'Leave empty if it does not expire.', 'marwa-apply' ) ),
				),
				'columns' => array( 'title', 'authority', 'ref_no', 'issued_on', 'expires_on' ),
			),
			'skills'       => array(
				'table'   => 'skills',
				'title'   => __( 'Skills', 'marwa-apply' ),
				'hint'    => __( 'The specific things you can do — arc welding, scaffolding, forklift, tiling. An employer reads these before anything else.', 'marwa-apply' ),
				'add'     => __( 'Add a skill', 'marwa-apply' ),
				'max'     => 20,
				'fields'  => array(
					'skill' => array( 'label' => __( 'Skill', 'marwa-apply' ), 'type' => 'text', 'required' => true ),
					'level' => array( 'label' => __( 'How well', 'marwa-apply' ), 'type' => 'select', 'options' => 'skill_levels' ),
					'years' => array( 'label' => __( 'Years doing it', 'marwa-apply' ), 'type' => 'number', 'max' => 50 ),
				),
				'columns' => array( 'skill', 'level', 'years' ),
			),
			'referees'     => array(
				'table'   => 'referees',
				'title'   => __( 'References', 'marwa-apply' ),
				'hint'    => __( 'Someone who can confirm your work. A previous supervisor is worth more than a relative. Marwa will not contact anyone without telling you first.', 'marwa-apply' ),
				'add'     => __( 'Add a reference', 'marwa-apply' ),
				'max'     => 4,
				'fields'  => array(
					'name'         => array( 'label' => __( 'Name', 'marwa-apply' ), 'type' => 'text', 'required' => true ),
					'relation'     => array( 'label' => __( 'How they know you', 'marwa-apply' ), 'type' => 'text' ),
					'organisation' => array( 'label' => __( 'Where they work', 'marwa-apply' ), 'type' => 'text' ),
					'phone'        => array( 'label' => __( 'Phone', 'marwa-apply' ), 'type' => 'tel' ),
					'email'        => array( 'label' => __( 'Email', 'marwa-apply' ), 'type' => 'email' ),
				),
				'columns' => array( 'name', 'relation', 'organisation', 'phone' ),
			),
		);
	}

	/**
	 * Education levels, in the order a Bangladeshi certificate ladder runs.
	 *
	 * @return array
	 */
	public static function levels() {
		return array(
			'none'       => __( 'No formal certificate', 'marwa-apply' ),
			'primary'    => __( 'Primary (Class 5)', 'marwa-apply' ),
			'jsc'        => __( 'JSC / Class 8', 'marwa-apply' ),
			'ssc'        => __( 'SSC / Dakhil', 'marwa-apply' ),
			'ssc-voc'    => __( 'SSC (Vocational)', 'marwa-apply' ),
			'hsc'        => __( 'HSC / Alim', 'marwa-apply' ),
			'hsc-voc'    => __( 'HSC (Vocational) / Diploma', 'marwa-apply' ),
			'diploma'    => __( 'Diploma in Engineering', 'marwa-apply' ),
			'bachelor'   => __( 'Bachelor degree', 'marwa-apply' ),
			'master'     => __( 'Master degree', 'marwa-apply' ),
			'nursing'    => __( 'Nursing diploma or BSc', 'marwa-apply' ),
		);
	}

	/**
	 * How well somebody does a thing.
	 *
	 * @return array
	 */
	public static function skill_levels() {
		return array(
			'learning' => __( 'Still learning', 'marwa-apply' ),
			'can-do'   => __( 'Can do it on my own', 'marwa-apply' ),
			'skilled'  => __( 'Skilled', 'marwa-apply' ),
			'teach'    => __( 'Can train others', 'marwa-apply' ),
		);
	}

	/**
	 * Suggestions for the certificate box.
	 *
	 * A datalist rather than a fixed dropdown: these are the exams that come
	 * up constantly, but a candidate with something not on the list must
	 * still be able to type it.
	 *
	 * @return array
	 */
	public static function exams() {
		return array(
			'JFT-Basic (国際交流基金日本語基礎テスト)',
			'JLPT N5',
			'JLPT N4',
			'JLPT N3',
			'JLPT N2',
			'Specified Skilled Worker skills evaluation test',
			'介護技能評価試験 (Care skills evaluation test)',
			'介護日本語評価試験 (Care Japanese test)',
			'BMET registration',
			'Technical Training Centre trade certificate',
			'National Skill Standard Basic (NSSB)',
			'Driving licence (professional)',
			'BNMC nursing registration',
			'Prometric examination',
			'Dataflow verification',
			'IELTS',
			'Food safety / HACCP',
			'Basic safety and first aid',
		);
	}

	/**
	 * Rows for one section.
	 *
	 * @param string $key          Section key.
	 * @param int    $candidate_id Candidate.
	 * @return array
	 */
	public static function rows( $key, $candidate_id ) {
		global $wpdb;

		$defs = self::defs();

		if ( empty( $defs[ $key ] ) ) {
			return array();
		}

		$table = Install::table( $defs[ $key ]['table'] );

		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE candidate_id = %d ORDER BY id DESC", (int) $candidate_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * How many rows a candidate has across every section.
	 *
	 * @param int $candidate_id Candidate.
	 * @return array
	 */
	public static function counts( $candidate_id ) {
		$out = array();

		foreach ( array_keys( self::defs() ) as $key ) {
			$out[ $key ] = count( self::rows( $key, $candidate_id ) );
		}

		return $out;
	}

	/**
	 * Handle an add.
	 *
	 * @return array Errors, keyed by field.
	 */
	public static function do_add() {
		global $wpdb;

		$row = Profile::row();

		if ( ! $row ) {
			return array( 'form' => __( 'We could not find your file.', 'marwa-apply' ) );
		}

		$key  = isset( $_POST['ma_section'] ) ? sanitize_key( wp_unslash( $_POST['ma_section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$defs = self::defs();

		if ( empty( $defs[ $key ] ) ) {
			return array( 'form' => __( 'Unknown section.', 'marwa-apply' ) );
		}

		$def = $defs[ $key ];

		if ( count( self::rows( $key, (int) $row->id ) ) >= $def['max'] ) {
			return array(
				'form' => sprintf(
					/* translators: %d: maximum rows */
					__( 'You can add up to %d of these. Remove one first.', 'marwa-apply' ),
					$def['max']
				),
			);
		}

		$data   = array( 'candidate_id' => (int) $row->id );
		$format = array( '%d' );
		$errors = array();

		foreach ( $def['fields'] as $name => $field ) {
			$raw = isset( $_POST[ 'f_' . $name ] ) ? wp_unslash( $_POST[ 'f_' . $name ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$raw = is_string( $raw ) ? trim( $raw ) : '';

			if ( ! empty( $field['required'] ) && '' === $raw ) {
				$errors[ $name ] = __( 'This one is needed.', 'marwa-apply' );
				continue;
			}

			if ( '' === $raw ) {
				$data[ $name ]  = null;
				$format[]       = '%s';
				continue;
			}

			switch ( $field['type'] ) {
				case 'year':
					$year = (int) preg_replace( '/\D/', '', $raw );

					if ( $year && ( $year < 1950 || $year > (int) gmdate( 'Y' ) + 1 ) ) {
						$errors[ $name ] = __( 'Check that year.', 'marwa-apply' );
						continue 2;
					}

					$data[ $name ] = $year ? $year : null;
					$format[]      = '%d';
					break;

				case 'number':
					$data[ $name ] = min( (int) $field['max'], max( 0, (int) $raw ) );
					$format[]      = '%d';
					break;

				case 'month':
					$time = strtotime( $raw . '-01' );

					if ( ! $time ) {
						$time = strtotime( $raw );
					}

					if ( ! $time ) {
						$errors[ $name ] = __( 'Please give a month and year.', 'marwa-apply' );
						continue 2;
					}

					$data[ $name ] = gmdate( 'Y-m-01', $time );
					$format[]      = '%s';
					break;

				case 'email':
					$clean = sanitize_email( $raw );

					if ( ! is_email( $clean ) ) {
						$errors[ $name ] = __( 'That email address does not look right.', 'marwa-apply' );
						continue 2;
					}

					$data[ $name ] = $clean;
					$format[]      = '%s';
					break;

				case 'select':
					$options = call_user_func( array( __CLASS__, $field['options'] ) );

					if ( ! isset( $options[ $raw ] ) ) {
						$errors[ $name ] = __( 'Please choose from the list.', 'marwa-apply' );
						continue 2;
					}

					$data[ $name ] = $raw;
					$format[]      = '%s';
					break;

				default:
					$data[ $name ] = sanitize_text_field( $raw );
					$format[]      = '%s';
			}
		}

		if ( $errors ) {
			return $errors;
		}

		$wpdb->insert( Install::table( $def['table'] ), $data, $format ); // phpcs:ignore WordPress.DB

		Profile::recalculate( (int) $row->id );

		return array();
	}

	/**
	 * Handle a delete.
	 */
	public static function do_delete() {
		global $wpdb;

		$row = Profile::row();

		if ( ! $row ) {
			return;
		}

		$key  = isset( $_POST['ma_section'] ) ? sanitize_key( wp_unslash( $_POST['ma_section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$id   = isset( $_POST['ma_row'] ) ? (int) $_POST['ma_row'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$defs = self::defs();

		if ( empty( $defs[ $key ] ) || ! $id ) {
			return;
		}

		// Scoped to the candidate, so a guessed id deletes nothing.
		$wpdb->delete( // phpcs:ignore WordPress.DB
			Install::table( $defs[ $key ]['table'] ),
			array( 'id' => $id, 'candidate_id' => (int) $row->id ),
			array( '%d', '%d' )
		);

		Profile::recalculate( (int) $row->id );
	}

	/**
	 * Render one section: what is there, and a form to add another.
	 *
	 * @param string $key    Section key.
	 * @param object $row    Candidate row.
	 * @param array  $errors Errors from a failed add.
	 * @return string
	 */
	public static function section( $key, $row, $errors = array() ) {
		$defs = self::defs();

		if ( empty( $defs[ $key ] ) ) {
			return '';
		}

		$def  = $defs[ $key ];
		$rows = self::rows( $key, (int) $row->id );

		$out  = '<div class="ap-card ma-sec" id="' . esc_attr( $key ) . '">';
		$out .= '<h2>' . esc_html( $def['title'] );
		$out .= $rows ? ' <span class="ma-count">' . esc_html( (string) count( $rows ) ) . '</span>' : '';
		$out .= '</h2>';
		$out .= '<p class="ap-note">' . esc_html( $def['hint'] ) . '</p>';

		if ( $rows ) {
			$out .= '<div class="ma-table-wrap"><table class="ma-rows"><tbody>';

			foreach ( $rows as $r ) {
				$out .= '<tr>';

				foreach ( $def['columns'] as $col ) {
					$out .= '<td>' . esc_html( self::show( $def['fields'][ $col ], $r->$col ) ) . '</td>';
				}

				$out .= '<td class="ma-rm"><form method="post">';
				$out .= wp_nonce_field( 'ma_rec_delete', 'ma_nonce', true, false );
				$out .= '<input type="hidden" name="ma_action" value="rec_delete">';
				$out .= '<input type="hidden" name="ma_section" value="' . esc_attr( $key ) . '">';
				$out .= '<input type="hidden" name="ma_row" value="' . esc_attr( (string) $r->id ) . '">';
				$out .= '<button type="submit" class="ma-x" aria-label="' . esc_attr__( 'Remove this entry', 'marwa-apply' ) . '">&times;</button>';
				$out .= '</form></td></tr>';
			}

			$out .= '</tbody></table></div>';
		}

		if ( count( $rows ) >= $def['max'] ) {
			$out .= '<p class="ap-note">' . esc_html__( 'That is as many as you can add here.', 'marwa-apply' ) . '</p></div>';

			return $out;
		}

		$out .= '<details class="ma-add"' . ( $errors ? ' open' : '' ) . '>';
		$out .= '<summary>' . esc_html( $def['add'] ) . '</summary>';

		$out .= '<form method="post" class="ma-add-form">';
		$out .= wp_nonce_field( 'ma_rec_add', 'ma_nonce', true, false );
		$out .= '<input type="text" name="ma_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">';
		$out .= '<input type="hidden" name="ma_action" value="rec_add">';
		$out .= '<input type="hidden" name="ma_section" value="' . esc_attr( $key ) . '">';

		if ( ! empty( $errors['form'] ) ) {
			$out .= '<div class="ap-msg bad">' . esc_html( $errors['form'] ) . '</div>';
		}

		$out .= '<div class="ma-grid-f">';

		foreach ( $def['fields'] as $name => $field ) {
			$out .= self::field( $key, $name, $field, isset( $errors[ $name ] ) ? $errors[ $name ] : '' );
		}

		$out .= '</div>';
		$out .= '<button type="submit" class="btn primary">' . esc_html( $def['add'] ) . '</button>';
		$out .= '</form></details></div>';

		return $out;
	}

	/**
	 * One input.
	 *
	 * @param string $key   Section key.
	 * @param string $name  Field name.
	 * @param array  $field Field spec.
	 * @param string $error Error for this field.
	 * @return string
	 */
	private static function field( $key, $name, $field, $error ) {
		$id  = 'f_' . $key . '_' . $name;
		$out = '<div class="ap-field' . ( $error ? ' is-error' : '' ) . '">';
		$out .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] );

		if ( ! empty( $field['hint'] ) ) {
			$out .= '<span class="hint">' . esc_html( $field['hint'] ) . '</span>';
		}

		$out .= '</label>';

		if ( 'select' === $field['type'] ) {
			$options = call_user_func( array( __CLASS__, $field['options'] ) );

			$out .= '<select id="' . esc_attr( $id ) . '" name="f_' . esc_attr( $name ) . '">';
			$out .= '<option value="">' . esc_html__( 'Choose…', 'marwa-apply' ) . '</option>';

			foreach ( $options as $value => $label ) {
				$out .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
			}

			$out .= '</select>';
		} else {
			$type = $field['type'];
			$type = 'year' === $type ? 'number' : $type;

			$attrs = '';

			if ( 'number' === $type ) {
				$attrs .= ' min="0"';
				$attrs .= ' max="' . esc_attr( (string) ( 'year' === $field['type'] ? (int) gmdate( 'Y' ) + 1 : $field['max'] ) ) . '"';
			}

			if ( ! empty( $field['list'] ) ) {
				$list   = 'list_' . $field['list'];
				$attrs .= ' list="' . esc_attr( $list ) . '"';
			}

			$out .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="f_' . esc_attr( $name ) . '"' . $attrs . '>';

			if ( ! empty( $field['list'] ) ) {
				$out .= '<datalist id="' . esc_attr( 'list_' . $field['list'] ) . '">';

				foreach ( call_user_func( array( __CLASS__, $field['list'] ) ) as $suggestion ) {
					$out .= '<option value="' . esc_attr( $suggestion ) . '"></option>';
				}

				$out .= '</datalist>';
			}
		}

		if ( $error ) {
			$out .= '<span class="err">' . esc_html( $error ) . '</span>';
		}

		return $out . '</div>';
	}

	/**
	 * A stored value, as a person should read it.
	 *
	 * @param array $field Field spec.
	 * @param mixed $value Stored value.
	 * @return string
	 */
	private static function show( $field, $value ) {
		if ( null === $value || '' === $value ) {
			return '—';
		}

		if ( 'select' === $field['type'] ) {
			$options = call_user_func( array( __CLASS__, $field['options'] ) );

			return isset( $options[ $value ] ) ? $options[ $value ] : (string) $value;
		}

		if ( 'month' === $field['type'] ) {
			$time = strtotime( (string) $value );

			return $time ? date_i18n( 'M Y', $time ) : (string) $value;
		}

		return (string) $value;
	}

	/**
	 * Styles shared by every section.
	 *
	 * @return string
	 */
	public static function styles() {
		return '<style id="marwa-apply-records">
.ma-sec h2{display:flex;align-items:center;gap:9px;margin-top:0;font-size:19px}
.ma-count{font-size:12.5px;font-weight:700;color:var(--muted,#5B6B7C);background:var(--surface-alt,#F3F1EC);border-radius:20px;padding:2px 9px}
.ma-table-wrap{overflow-x:auto;margin:14px 0}
.ma-rows{width:100%;border-collapse:collapse;font-size:14.5px}
.ma-rows td{padding:9px 12px 9px 0;border-top:1px solid var(--line,#E3E0D9);vertical-align:top;line-height:1.5}
.ma-rows tr:first-child td{border-top:0}
.ma-rows td:first-child{font-weight:600;color:var(--navy,#0B2239)}
.ma-rm{width:32px;padding-right:0!important;text-align:right}
.ma-x{border:0;background:none;color:var(--muted,#5B6B7C);font-size:20px;line-height:1;cursor:pointer;padding:0 4px;border-radius:5px}
.ma-x:hover{color:#B4231A;background:var(--surface-alt,#F3F1EC)}
.ma-add>summary{cursor:pointer;display:inline-block;padding:9px 16px;border:1px dashed var(--line,#E3E0D9);border-radius:8px;font-size:14.5px;color:var(--accent,#A93A2C);list-style:none}
.ma-add>summary::-webkit-details-marker{display:none}
.ma-add[open]>summary{margin-bottom:16px;border-style:solid}
.ma-add-form{padding-top:2px}
.ma-grid-f{display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:0 18px}
</style>';
	}
}
