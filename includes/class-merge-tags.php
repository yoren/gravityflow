<?php
/**
 * Gravity Flow Merge Tags Functions
 *
 * @package   GravityFlow
 * @copyright Copyright (c) 2017, Steven Henty S.L.
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.7.1-dev
 */


if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Merge_Tags {

	/**
	 * @var Gravity_Flow_Step|false $_current_step The current step or false if not available.
	 *
	 * @since 1.7.1-dev
	 */
	private static $_current_step = false;

	/**
	 * @var bool $_url_encode Indicates if the replacement value should be URL encoded.
	 *
	 * @since 1.7.1-dev
	 */
	private static $_url_encode = false;

	/**
	 * @var bool $_esc_html Indicates if HTML found in the replacement value should be escaped.
	 *
	 * @since 1.7.1-dev
	 */
	private static $_esc_html = true;

	/**
	 * @var bool $_nl2br Indicates if newlines should be converted to html <br> tags.
	 *
	 * @since 1.7.1-dev
	 */
	private static $_nl2br = true;

	/**
	 * @var string $_format Determines how the value should be formatted. HTML or text.
	 *
	 * @since 1.7.1-dev
	 */
	private static $_format = 'html';

	/**
	 * Add the filter where merge tag replacement will occur.
	 *
	 * @since 1.7.1-dev
	 */
	public static function init() {
		if ( ! gravity_flow()->is_gravityforms_supported() ) {
			return;
		}

		add_filter( 'gform_pre_replace_merge_tags', array( __CLASS__, 'replace_merge_tags' ), 10, 7 );
	}

	/**
	 * Target for the gform_pre_replace_merge_tags filter. Replaces supported merge tags.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param string $text       The current text in which merge tags are being replaced.
	 * @param array  $form       The current form.
	 * @param array  $entry      The current entry.
	 * @param bool   $url_encode Indicates if the replacement value should be URL encoded.
	 * @param bool   $esc_html   Indicates if HTML found in the replacement value should be escaped.
	 * @param bool   $nl2br      Indicates if newlines should be converted to html <br> tags
	 * @param string $format     Determines how the value should be formatted. HTML or text.
	 *
	 * @return string
	 */
	public static function replace_merge_tags( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
		if ( strpos( $text, '{' ) === false || empty( $entry ) ) {
			return $text;
		}

		self::set_properties( func_get_args() );

		$text = self::replace_created_by( $text, $entry );
		$text = self::replace_workflow_timeline( $text, $entry );
		$text = self::replace_workflow_note( $text, $entry );

		$current_step = self::get_current_step( $form, $entry );

		if ( $current_step ) {
			$text = self::replace_assignees( $text, $current_step );
			$text = $current_step->replace_variables( $text, null );
		}

		return $text;
	}

	/**
	 * Set the formatting related class properties.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param array $args The arguments available to the gform_pre_replace_merge_tags filter.
	 */
	public static function set_properties( $args ) {
		self::$_url_encode = (bool) $args[3];
		self::$_esc_html   = (bool) $args[4];
		self::$_nl2br      = (bool) $args[5];
		self::$_format     = $args[6];
	}

	/**
	 * Get the current step.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param array $form  The current form.
	 * @param array $entry The current entry.
	 *
	 * @return Gravity_Flow_Step|false
	 */
	public static function get_current_step( $form, $entry ) {
		if ( ! isset( $entry['workflow_step'] ) ) {
			self::$_current_step = false;

			return false;
		}

		if ( ! self::$_current_step || self::$_current_step->get_id() != $entry['workflow_step'] ) {
			self::$_current_step = gravity_flow()->get_current_step( $form, $entry );
		}

		return self::$_current_step;
	}

	/**
	 * Formats the value which will replace the merge tag.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param string $value The value to be formatted.
	 *
	 * @return string
	 */
	public static function format_value( $value ) {
		return GFCommon::format_variable_value( $value, self::$_url_encode, self::$_esc_html, self::$_format, self::$_nl2br );
	}

	/**
	 * Retrieve attributes from a string (i.e. merge tag modifiers).
	 *
	 * @since 1.7.1-dev
	 *
	 * @param string $string   The string to retrieve the attributes from.
	 * @param array  $defaults The supported attributes and their defaults.
	 *
	 * @return array
	 */
	public static function get_attributes( $string, $defaults = array() ) {
		$attributes = shortcode_parse_atts( $string );

		if ( empty( $attributes ) ) {
			$attributes = array();
		}

		if ( ! empty( $defaults ) ) {
			$attributes = shortcode_atts( $defaults, $attributes );

			foreach ( $defaults as $attribute => $default ) {
				if ( $default === true ) {
					$attributes[ $attribute ] = strtolower( $attributes[ $attribute ] ) == 'false' ? false : true;
				} elseif ( $default === false ) {
					$attributes[ $attribute ] = strtolower( $attributes[ $attribute ] ) == 'true' ? true : false;
				}
			}
		}

		return $attributes;
	}

	/**
	 * Replace the {created_by} merge tags.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param string $text  The text to be processed.
	 * @param array  $entry The current entry.
	 *
	 * @return string
	 */
	public static function replace_created_by( $text, $entry ) {
		if ( empty( $entry['created_by'] ) ) {
			return $text;
		}

		preg_match_all( '/{created_by(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );

		if ( ! empty( $matches ) ) {
			$entry_creator = new WP_User( $entry['created_by'] );

			foreach ( $matches as $match ) {
				if ( ! isset( $match[2] ) ) {
					continue;
				}

				$full_tag = $match[0];
				$property = $match[2];

				if ( $property == 'roles' ) {
					$value = implode( ', ', $entry_creator->roles );
				} else {
					$value = $entry_creator->get( $property );
				}

				$text = str_replace( $full_tag, self::format_value( $value ), $text );
			}
		}

		return $text;
	}

	/**
	 * Replace the {workflow_timeline} merge tags with the entire timeline for the current entry.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param string $text  The text to be processed.
	 * @param array  $entry The current entry.
	 *
	 * @return string
	 */
	public static function replace_workflow_timeline( $text, $entry ) {
		preg_match_all( '/{workflow_timeline(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );

		if ( is_array( $matches ) && isset( $matches[0] ) ) {
			$full_tag = $matches[0][0];
			$timeline = self::get_timeline( $entry );
			$text     = str_replace( $full_tag, self::format_value( $timeline ), $text );
		}

		return $text;
	}

	/**
	 * Get the content which will replace the {workflow_timeline} merge tag.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param array $entry The current entry.
	 *
	 * @return string
	 */
	public static function get_timeline( $entry ) {
		$notes = Gravity_Flow_Common::get_timeline_notes( $entry );

		if ( empty( $notes ) ) {
			return '';
		}

		$return = array();

		foreach ( $notes as $note ) {
			$step = Gravity_Flow_Common::get_timeline_note_step( $note );
			$name = Gravity_Flow_Common::get_timeline_note_display_name( $note, $step );
			$date = Gravity_Flow_Common::format_date( $note->date_created );

			$return[] = self::format_note( $note->value, $name, $date );
		}

		return implode( "\n\n", $return );
	}

	/**
	 * Replace the {workflow_note} merge tags with the user submitted notes.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param string $text  The text to be processed.
	 * @param array  $entry The current entry.
	 *
	 * @return string
	 */
	public static function replace_workflow_note( $text, $entry ) {
		preg_match_all( '/{workflow_note(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );

		if ( ! empty( $matches ) ) {
			foreach ( $matches as $match ) {
				$full_tag  = $match[0];
				$modifiers = rgar( $match, 2 );

				$a = self::get_attributes( $modifiers, array(
					'step_id'      => null,
					'display_name' => false,
					'display_date' => false
				) );

				$replacement = '';
				$notes       = self::get_step_notes( $entry['id'], $a['step_id'] );

				if ( ! empty( $notes ) ) {
					$replacement_array = array();

					foreach ( $notes as $note ) {
						$name = $a['display_name'] ? self::get_assignee_display_name( $note['assignee_key'] ) : '';
						$date = $a['display_date'] ? Gravity_Flow_Common::format_date( $note['timestamp'] ) : '';

						$replacement_array[] = self::format_note( $note['value'], $name, $date );
					}

					$replacement = self::format_value( implode( "\n\n", $replacement_array ) );
				}

				$text = str_replace( $full_tag, $replacement, $text );
			}
		}

		return $text;
	}

	/**
	 * Format a note for output.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param string $note_value   The note value.
	 * @param string $display_name The note display name.
	 * @param string $date         The note creation date.
	 *
	 * @return string
	 */
	public static function format_note( $note_value, $display_name, $date ) {
		$separator = $display_name && $date ? ': ' : '';

		return sprintf( "%s%s%s\n%s", $display_name, $separator, $date, $note_value );
	}

	/**
	 * Get the assignee display name.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param string|Gravity_Flow_Assignee $assignee_or_key The assignee key or object.
	 *
	 * @return string
	 */
	public static function get_assignee_display_name( $assignee_or_key ) {
		if ( ! $assignee_or_key instanceof Gravity_Flow_Assignee ) {
			$assignee = new Gravity_Flow_Assignee( $assignee_or_key );
		} else {
			$assignee = $assignee_or_key;
		}

		return $assignee->get_display_name();
	}

	/**
	 * Get the user submitted notes for a specific step.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param int      $entry_id The current entry ID.
	 * @param int|null $step_id  The step ID or null to return the most recent note.
	 *
	 * @return array
	 */
	public static function get_step_notes( $entry_id, $step_id ) {
		$notes      = Gravity_Flow_Common::get_workflow_notes( $entry_id, true );
		$step_notes = array();

		foreach ( $notes as $note ) {
			if ( $step_id && $step_id != $note['step_id'] ) {
				continue;
			}

			$step_notes[] = $note;

			if ( is_null( $step_id ) ) {
				break;
			}
		}

		return $step_notes;
	}

	/**
	 * Replace the {assignees} merge tags.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param string            $text         The text being processed.
	 * @param Gravity_Flow_Step $current_step The current step for this entry.
	 *
	 * @return string
	 */
	public static function replace_assignees( $text, $current_step ) {
		preg_match_all( '/{assignees(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );

		if ( ! empty( $matches ) ) {
			foreach ( $matches as $match ) {
				$full_tag       = $match[0];
				$options_string = isset( $match[2] ) ? $match[2] : '';

				$a = self::get_attributes( $options_string, array(
					'status'       => true,
					'user_email'   => true,
					'display_name' => true,
				) );

				$assignees          = $current_step->get_assignees();
				$assignees_text_arr = array();

				/** @var Gravity_Flow_Assignee $step_assignee */
				foreach ( $assignees as $step_assignee ) {
					$assignee_line = '';
					if ( $a['display_name'] ) {
						$assignee_line .= $step_assignee->get_display_name();
					}
					if ( $a['user_email'] && $step_assignee->get_type() == 'user_id' ) {
						if ( $assignee_line ) {
							$assignee_line .= ', ';
						}
						$assignee_user = new WP_User( $step_assignee->get_id() );
						$assignee_line .= $assignee_user->user_email;
					}
					if ( $a['status'] ) {
						$assignee_line .= ' (' . $step_assignee->get_status() . ')';
					}
					$assignees_text_arr[] = $assignee_line;
				}

				$assignees_text = join( "\n", $assignees_text_arr );
				$text           = str_replace( $full_tag, self::format_value( $assignees_text ), $text );
			}
		}

		return $text;
	}

}

Gravity_Flow_Merge_Tags::init();