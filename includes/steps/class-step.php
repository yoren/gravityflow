<?php
/**
 * Gravity Flow Step
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * An abstract class used as the base for all Steps.
 *
 * Class Gravity_Flow_Step
 *
 *
 * @since		1.0
 */
abstract class Gravity_Flow_Step extends stdClass {

	/**
	 * The ID of the Step
	 *
	 * @var int
	 */
	private $_id;

	/**
	 * The Feed meta on which this step is based.
	 *
	 * @var array
	 */
	private $_meta;

	/**
	 * Step is active
	 *
	 * @var bool
	 */
	private $_is_active;

	/**
	 * The Form ID for this step.
	 *
	 * @var int
	 */
	private $_form_id;

	/**
	 * The entry for this step.
	 *
	 * @var array|null
	 */
	private $_entry;

	/**
	 * The assignee emails for which notifications have been processed.
	 *
	 * @var array
	 */
	private $_assignees_emailed = array();

	/**
	 * A unique key for this step type. This property must be overridden by extending classes.
	 *
	 * @var string
	 */
	protected $_step_type;

	/**
	 * The next step. This could be either a step ID (integer) or one of the following:
	 * - next
	 * - complete
	 *
	 * @var int|string
	 */
	protected $_next_step_id;

	/**
	 * The constructor for the Step. Provide an entry object to perform and entry-specific tasks.
	 *
	 * @param array $feed Required. The Feed on which this step is based.
	 * @param null|array $entry Optional. Instantiate with an entry to perform entry related tasks.
	 */
	public function __construct( $feed = array(), $entry = null ) {

		if ( empty( $feed ) ) {
			return;
		}
		$this->_id = absint( $feed['id'] );
		$this->_is_active = (bool) $feed['is_active'];
		$this->_form_id = absint( $feed['form_id'] );

		$this->_step_type = $feed['meta']['step_type'];

		$this->_meta = $feed['meta'];

		$this->_entry = $entry;
	}

	/**
	 * Magic method to allow direct access to the settings as properties.
	 * Returns an empty string for undefined properties allowing for graceful backward compatibility where new settings may not have been defined in stored settings.
	 *
	 * @param $name
	 *
	 * @return mixed
	 */
	public function &__get( $name ) {
		if ( ! isset( $this->_meta[ $name ] ) ) {
			$this->_meta[ $name ] = '';
		}

		return $this->_meta[ $name ];
	}

	public function __set( $key, $value ) {
		$this->_meta[ $key ] = $value;
		$this->$key = $value;
	}

	public function __isset( $key ) {
		return isset( $this->_meta[ $key ] );
	}

	public function __unset( $key ) {
		unset( $this->$key );
	}

	/**
	 * Returns an array of the configuration of the status options for this step.
	 * These options will appear in the step settings.
	 * Override this method to add status options.
	 *
	 * For example, a status configuration may look like this:
	 * array(
	 *    'status' => 'complete',
	 *    'status_label' => __( 'Complete', 'gravityflow' ),
	 *    'destination_setting_label' => __( 'Next Step', 'gravityflow' ),
	 *    'default_destination' => 'next',
	 *    )
	 *
	 *
	 * @return array An array of arrays
	 */
	public function get_status_config() {
		return array(
			array(
				'status' => 'complete',
				'status_label' => __( 'Complete', 'gravityflow' ),
				'destination_setting_label' => __( 'Next Step', 'gravityflow' ),
				'default_destination' => 'next',
			),
		);
	}

    /**
     * @deprecated
     *
     * @return array
     */
    public function get_final_status_config() {
        return $this->get_status_config();
    }


    /**
	 * Returns the translated label for a status key.
	 *
	 * @param $status
	 *
	 * @return string
	 */
	public function get_status_label( $status ) {
		if ( $status == 'pending' ) {
			return __( 'Pending', 'gravityflow' );
		}
		$status_configs = $this->get_status_config();
		foreach ( $status_configs as $status_config ) {
			if ( strtolower( $status ) == rgar( $status_config, 'status' ) ) {
				return isset( $status_config['status_label'] ) ? $status_config['status_label'] : $status;
			}
		}
		return $status;
	}

	/**
	 * Returns the label for the step.
	 *
	 * Override this method to return a custom label.
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->get_type();
	}

	/**
	 * If set, returns the entry for this step.
	 *
	 * @return array|null
	 */
	public function get_entry() {
		if ( empty( $this->_entry ) ) {
			$this->refresh_entry();
		}
		return $this->_entry;
	}

	/**
	 * Flushes and reloads the cached entry for this step.
	 *
	 * @return array|mixed|null
	 */
	public function refresh_entry() {
		$entry_id = $this->get_entry_id();
		if ( ! empty( $entry_id ) ) {
			$this->_entry = GFAPI::get_entry( $entry_id );
		}

		return $this->_entry;
	}

	/**
	 * Returns the Form object for this step.
	 *
	 * @return mixed
	 */
	public function get_form() {
		$entry = $this->get_entry();
		if ( $entry ) {
			$form_id = $entry['form_id'];
		} else {
			$form_id = $this->get_form_id();
		}

		$form = GFAPI::get_form( $form_id );
		return $form;
	}

	/**
	 * Returns the ID for the current entry object. If not set the lid query arg is returned.
	 *
	 * @return int
	 */
	public function get_entry_id() {
		if ( empty( $this->_entry ) ) {
			return rgget( 'lid' );
		}
		$id = absint( $this->_entry['id'] );
		return $id;
	}

	/**
	 * Returns the step type.
	 *
	 * @return string
	 */
	public function get_type() {

		return $this->_step_type;
	}

	/**
	 * Returns the Step ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->_id;
	}

	/**
	 * Is the step active? The step may have been deactivated by the user in the list of steps.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->_is_active;
	}

    /**
     * Is this step supported on this server? Override to hide this step in the list of step types if the requirements are not met.
     *
     * @return bool
     */
    public function is_supported() {
        return true;
    }

	/**
	 * Returns the ID of the Form object for the step.
	 *
	 * @return int
	 */
	public function get_form_id() {
		if ( empty( $this->_form_id ) ) {

			$this->_form_id = absint( rgget( 'id' ) );
		}

		return $this->_form_id;
	}

	/**
	 * Returns the user-defined name of the step.
	 *
	 * @return mixed
	 */
	public function get_name() {
		return $this->step_name;
	}

	/**
	 * Override this method to add settings to the step. Use the Gravity Forms Add-On Framework Settings API.
	 *
	 * @return array
	 */
	public function get_settings() {
		return array();
	}

	/**
	 * Override this method to set a custom icon in the step settings.
	 * 32px x 32px
	 *
	 * @return string
	 */
	public function get_icon_url() {
		return $this->get_base_url() . '/images/gravityflow-icon-blue.svg';
	}

	/**
	 * Returns the Gravity Flow base URL
	 *
	 * @return string
	 */
	public function get_base_url() {
		return gravity_flow()->get_base_url();
	}

	/**
	 * Returns the Gravity Flow base path
	 *
	 * @return string
	 */
	public function get_base_path() {
		return gravity_flow()->get_base_path();
	}

	/**
	 * Returns the ID of the next step.
	 *
	 * @return int|mixed|string
	 */
	public function get_next_step_id() {
		if ( isset( $this->_next_step_id ) ) {
			return $this->_next_step_id;
		}
		$status = $this->evaluate_status();
		$destination_status_key = 'destination_' . $status;
		if ( isset( $this->{$destination_status_key} ) ) {
			$next_step_id = $this->{$destination_status_key};
		} else {
			$next_step_id = 'next';
		}

		$this->set_next_step_id( $next_step_id );
		return $next_step_id;
	}

	/**
	 * Sets the next step.
	 *
	 * @param $id
	 */
	public function set_next_step_id( $id ) {
		$this->_next_step_id = $id;
	}

	/**
	 * Attempts to start this step for the current entry. If the step is scheduled then the entry will be queued.
	 *
	 * @return bool Is the step complete?
	 */
	public function start() {

		$entry_id = $this->get_entry_id();

		$this->log_debug( __METHOD__ . '() - triggered step: ' . $this->get_name() . ' for entry id ' .$entry_id );

		$step_id = $this->get_id();

		gform_update_meta( $entry_id , 'workflow_step', $step_id );

		$step_timestamp = $this->get_step_timestamp();
		if ( empty( $step_timestamp ) ) {
			$this->log_debug( __METHOD__ . '() - No timestamp, adding one' );
			gform_update_meta( $entry_id, 'workflow_step_' . $this->get_id() . '_timestamp', time() );
			$this->refresh_entry();
		}

		$status = $this->evaluate_status();
		$this->log_debug( __METHOD__ . '() - Step status before processing: ' . $status );


		if ( $this->scheduled && ! $this->validate_schedule() ) {
			if ( $status == 'queued' ) {
				$this->log_debug( __METHOD__ . '() - Step still queued: ' . $this->get_name() );
			} else {
				$this->update_step_status( 'queued' );
				$this->refresh_entry();
				$this->log_event( 'queued' );
				$this->log_debug( __METHOD__ . '() - Step queued: ' . $this->get_name() );
			}
			$complete = false;
		} else {
			$this->log_debug( __METHOD__ . '() - Starting step: ' . $this->get_name() );
			gform_update_meta( $entry_id, 'workflow_step_' . $this->get_id() . '_timestamp', time() );

			$this->update_step_status();

			$this->refresh_entry();

			$this->log_event( 'started' );

			$complete = $this->process();

			$log_is_complete = $complete ? 'yes' : 'no';
			$this->log_debug( __METHOD__ . '() - step complete: ' . $log_is_complete );
		}

		return $complete;
	}

	/**
	 * Is the step currently in the queue waiting for the scheduled start time?
	 *
	 * @return bool
	 */
	public function is_queued() {
		$entry = $this->get_entry();

		return rgar( $entry, 'workflow_step_status_' . $this->get_id() ) == 'queued';
	}

	/**
	 * Validates the step schedule.
	 *
	 * @return bool Returns true if step is ready to proceed.
	 */
	public function validate_schedule() {
		if ( ! $this->scheduled ) {
			return true;
		}

		$this->log_debug( __METHOD__ . '() step is scheduled' );

		$schedule_timestamp = $this->get_schedule_timestamp();

		$this->log_debug( __METHOD__ . '() schedule_timestamp: ' . $schedule_timestamp );
		$this->log_debug( __METHOD__ . '() schedule_timestamp formatted: ' . date( 'Y-m-d H:i:s', $schedule_timestamp ) );

		// Schedule delay is relative to UTC. Schedule date is relative to timezone of the site.
		$current_time = in_array( $this->schedule_type, array( 'date', 'date_field' ) ) ? current_time( 'timestamp' ) : time();

		$this->log_debug( __METHOD__ . '() current_time: ' . $current_time );
		$this->log_debug( __METHOD__ . '() current_time formatted: ' . date( 'Y-m-d H:i:s', $current_time ) );

		return $current_time >= $schedule_timestamp;
	}

	/**
	 * Returns the schedule timestamp (UTC) calculated from the schedule settings.
	 *
	 * @return int
	 */
	public function get_schedule_timestamp() {

		if ( $this->schedule_type == 'date' ) {

			$this->log_debug( __METHOD__ . '() schedule_date: ' . $this->schedule_date );
			$schedule_datetime = strtotime( $this->schedule_date );

			return $schedule_datetime;
		}

		$entry = $this->get_entry();

		if ( $this->schedule_type == 'date_field' ) {

			$this->log_debug( __METHOD__ . '() schedule_date_field: ' . $this->schedule_date_field );
			$schedule_date = $entry[ (string) $this->schedule_date_field ];
			$this->log_debug( __METHOD__ . '() schedule_date: ' . $schedule_date );

			$schedule_datetime = strtotime( $schedule_date );

			return $schedule_datetime;
		}

		$entry_timestamp = $this->get_step_timestamp();

		$schedule_timestamp = $entry_timestamp;

		switch ( $this->schedule_delay_unit ) {
			case 'minutes' :
				$schedule_timestamp += ( MINUTE_IN_SECONDS * $this->schedule_delay_offset );
				break;
			case 'hours' :
				$schedule_timestamp += ( HOUR_IN_SECONDS * $this->schedule_delay_offset );
				break;
			case 'days' :
				$schedule_timestamp += ( DAY_IN_SECONDS * $this->schedule_delay_offset );
				break;
			case 'weeks' :
				$schedule_timestamp += ( WEEK_IN_SECONDS * $this->schedule_delay_offset );
				break;
		}
		return $schedule_timestamp;
	}

	public function is_expired() {
		if ( ! $this->supports_expiration() ) {
			return false;
		}

		if ( ! $this->expiration ) {
			return false;
		}

		$this->log_debug( __METHOD__ . '() step is scheduled for expiration' );

		$expiration_timestamp = $this->get_expiration_timestamp();

		$this->log_debug( __METHOD__ . '() expiration_timestamp UTC: ' . $expiration_timestamp );
		$this->log_debug( __METHOD__ . '() expiration_timestamp formatted UTC: ' . date( 'Y-m-d H:i:s', $expiration_timestamp ) );

		// Schedule delay is relative to UTC. Schedule date is relative to timezone of the site.
		$current_time = time();

		$this->log_debug( __METHOD__ . '() current_time UTC: ' . $current_time );
		$this->log_debug( __METHOD__ . '() current_time formatted UTC: ' . date( 'Y-m-d H:i:s', $current_time ) );

		$is_expired = $current_time >= $expiration_timestamp;

		$this->log_debug( __METHOD__ . '() is expired? ' . ( $is_expired ? 'yes' : 'no' ) );

		return $is_expired;
	}

	/**
	 * Returns the schedule timestamp calculated from the schedule settings.
	 *
	 * @return bool|int|mixed
	 */
	public function get_expiration_timestamp() {

		if ( ! $this->expiration ) {
			return false;
		}

		if ( $this->expiration_type == 'date' ) {

			$this->log_debug( __METHOD__ . '() expiration_date: ' . $this->expiration_date );
			$expiration_datetime = strtotime( $this->expiration_date );
			$expiration_date = date( 'Y-m-d H:i:s', $expiration_datetime );
			$expiration_date_gmt = get_gmt_from_date( $expiration_date );
			$expiration_datetime = strtotime( $expiration_date_gmt );

			return $expiration_datetime;
		}

		$entry_timestamp = $this->get_step_timestamp();

		$expiration_timestamp = $entry_timestamp;

		switch ( $this->expiration_delay_unit ) {
			case 'minutes' :
				$expiration_timestamp += ( MINUTE_IN_SECONDS * $this->expiration_delay_offset );
				break;
			case 'hours' :
				$expiration_timestamp += ( HOUR_IN_SECONDS * $this->expiration_delay_offset );
				break;
			case 'days' :
				$expiration_timestamp += ( DAY_IN_SECONDS * $this->expiration_delay_offset );
				break;
			case 'weeks' :
				$expiration_timestamp += ( WEEK_IN_SECONDS * $this->expiration_delay_offset );
				break;
		}
		return $expiration_timestamp;
	}

	public function get_entry_timestamp() {
		$entry = $this->get_entry();

		return $entry['workflow_timestamp'];
	}
	public function get_step_timestamp() {
		$timestamp = gform_get_meta( $this->get_entry_id(), 'workflow_step_' . $this->get_id() . '_timestamp' );

		return $timestamp;
	}

	/**
	 * Process the step. For example, assign to a user, send to a service, send a notification or do nothing. Return (bool) $complete.
	 *
	 * @return bool Is the step complete?
	 */
	public function process() {
		return true;
	}

	public function assign() {
		$complete = $this->is_complete();

		$assignees = $this->get_assignees();

		if ( empty( $assignees ) ) {
			$note = sprintf( __( '%s: not required', 'gravityflow' ), $this->get_name() );
			$this->add_note( $note, 0 , 'gravityflow' );
		} else {
			foreach ( $assignees as $assignee ) {
				$assignee->update_status( 'pending' );
				// send notification
				$this->maybe_send_assignee_notification( $assignee );
				$complete = false;
			}
		}
		return $complete;
	}

	/**
	 * Sends the assignee email if the assignee_notification_setting is enabled.
	 *
	 * @param Gravity_Flow_Assignee $assignee
	 */
	public function maybe_send_assignee_notification( $assignee ) {
		if ( $this->assignee_notification_enabled ) {
			$this->send_assignee_notification( $assignee );
		}
	}

	/**
	 * Sends the assignee email.
	 *
	 * @param Gravity_Flow_Assignee $assignee
	 */
	public function send_assignee_notification( $assignee ) {
		$this->log_debug( __METHOD__ . '() starting. assignee: ' . $assignee->get_key() );

		$form                                       = $this->get_form();
		$notification['workflow_notification_type'] = 'assignee';
		$notification['fromName']                   = empty( $this->assignee_notification_from_name ) ? get_bloginfo() : $this->assignee_notification_from_name;
		$notification['from']                       = empty( $this->assignee_notification_from_email ) ? get_bloginfo( 'admin_email' ) : $this->assignee_notification_from_email;
		$notification['replyTo']                    = $this->assignee_notification_reply_to;
		$notification['bcc']                        = $this->assignee_notification_bcc;
		$notification['subject']                    = empty( $this->assignee_notification_subject ) ? $form['title'] . ': ' . $this->get_name() : $this->assignee_notification_subject;
		$notification['message']                    = $this->assignee_notification_message;
		$notification['disableAutoformat']          = $this->assignee_notification_disable_autoformat;

		if ( defined( 'PDF_EXTENDED_VERSION' ) && version_compare( PDF_EXTENDED_VERSION, '4.0-RC2' , '>=' ) ) {
			if ( $this->assignee_notification_gpdfEnable ) {
				$gpdf_id = $this->assignee_notification_gpdfValue;
				$notification = $this->gpdf_add_notification_attachment( $notification, $gpdf_id );
			}
		}

		$assignee_type = $assignee->get_type();

		$assignee_id = $assignee->get_id();

		if ( $assignee_type == 'email' ) {
			$email = $assignee_id;
			$notification['id']      = 'workflow_step_' . $this->get_id() . '_user_' . $email;
			$notification['name']    = $notification['id'];
			$notification['to']      = $email;
			$notification['message'] = $this->replace_variables( $this->assignee_notification_message, $assignee );
			$this->send_notification( $notification );
			return;
		}

		if ( $assignee_type == 'role' ) {
			$users = get_users( array( 'role' => $assignee_id ) );
		} else {
			$users = get_users( array( 'include' => array( $assignee_id ) ) );
		}

		$this->log_debug( __METHOD__ . sprintf( '() sending assignee notifications to %d users', count( $users ) ) );

		foreach ( $users as $user ) {
			$notification['id']      = 'workflow_step_' . $this->get_id() . '_user_' . $user->ID;
			$notification['name']    = $notification['id'];
			$notification['to']      = $user->user_email;
			$notification['message'] = $this->replace_variables( $this->assignee_notification_message, $assignee );
			$this->send_notification( $notification );
		}
	}

	/**
	 * Override this method to replace merge tags.
	 * Important: call the parent method first.
	 * $text = parent::replace_variables( $text, $user_id );
	 *
	 * @param $text
	 * @param Gravity_Flow_Assignee $assignee
	 *
	 * @return mixed
	 */
	public function replace_variables( $text, $assignee ) {

		preg_match_all( '/{workflow_entry_url(:(.*?))?}/', $text, $entry_url_matches, PREG_SET_ORDER );
		if ( is_array( $entry_url_matches ) ) {
			foreach ( $entry_url_matches as $entry_url_match ) {
				$full_tag       = $entry_url_match[0];
				$options_string = isset( $entry_url_match[2] ) ? $entry_url_match[2] : '';
				$options        = shortcode_parse_atts( $options_string );

				$a = shortcode_atts(
					array(
						'page_id' => 'admin',
						'token' => false,
					), $options
				);

				$force_token = strtolower( $a['token'] ) == 'true' ? true : false;

				$entry_token = '';

				if ( $assignee && $force_token ) {
					$token_lifetime_days = apply_filters( 'gravityflow_entry_token_expiration_days', 30, $assignee );
					$token_expiration_timestamp = strtotime( '+' . (int) $token_lifetime_days . ' days' );
					$entry_token = gravity_flow()->generate_access_token( $assignee, null, $token_expiration_timestamp );
				}

				$entry_url = $this->get_entry_url( $a['page_id'], $assignee, $entry_token );

				$text = str_replace( $full_tag, $entry_url, $text );
			}
		}

		preg_match_all( '/{workflow_entry_link(:(.*?))?}/', $text, $entry_link_matches, PREG_SET_ORDER );
		if ( is_array( $entry_link_matches ) ) {
			foreach ( $entry_link_matches as $entry_link_match ) {
				$full_tag       = $entry_link_match[0];
				$options_string = isset( $entry_link_match[2] ) ? $entry_link_match[2] : '';
				$options        = shortcode_parse_atts( $options_string );

				$a = shortcode_atts(
					array(
						'page_id' => 'admin',
						'text' => esc_html__( 'Entry', 'gravityflow' ),
						'token' => false,
					), $options
				);

				$force_token = strtolower( $a['token'] ) == 'true' ? true : false;

				$entry_token = '';

				if ( $assignee && $force_token ) {
					$token_lifetime_days = apply_filters( 'gravityflow_entry_token_expiration_days', 30, $assignee );
					$token_expiration_timestamp = strtotime( '+' . (int) $token_lifetime_days . ' days' );
					$entry_token = gravity_flow()->generate_access_token( $assignee, null, $token_expiration_timestamp );
				}

				$entry_url = $this->get_entry_url( $a['page_id'], $assignee, $entry_token );

				$entry_link = sprintf( '<a href="%s">%s</a>', $entry_url, $a['text'] );
				$text = str_replace( $full_tag, $entry_link, $text );
			}
		}

		$expiration_days = apply_filters( 'gravityflow_cancel_token_expiration_days', 2 );

		$expiration_str = '+' . (int) $expiration_days . ' days';

		$expiration_timestamp = strtotime( $expiration_str );

		$scopes = array(
			'pages' => array( 'inbox' ),
			'entry_id' => $this->get_entry_id(),
			'action' => 'cancel_workflow',
		);

		if ( $assignee ) {
			$cancel_token = gravity_flow()->generate_access_token( $assignee, $scopes, $expiration_timestamp );
			preg_match_all( '/{workflow_cancel_url(:(.*?))?}/', $text, $cancel_url_matches, PREG_SET_ORDER );
			if ( is_array( $cancel_url_matches ) ) {
				foreach ( $cancel_url_matches as $cancel_url_match ) {
					$full_tag       = $cancel_url_match[0];
					$options_string = isset( $cancel_url_match[2] ) ? $cancel_url_match[2] : '';
					$options        = shortcode_parse_atts( $options_string );

					$a = shortcode_atts(
						array(
							'page_id' => 'admin',
						), $options
					);

					$entry_url = $this->get_entry_url( $a['page_id'], $assignee, $cancel_token );

					$text = str_replace( $full_tag, $entry_url, $text );
				}
			}

			preg_match_all( '/{workflow_cancel_link(:(.*?))?}/', $text, $cancel_link_matches, PREG_SET_ORDER );
			if ( is_array( $cancel_link_matches ) ) {
				foreach ( $cancel_link_matches as $cancel_link_match ) {
					$full_tag       = $cancel_link_match[0];
					$options_string = isset( $cancel_link_match[2] ) ? $cancel_link_match[2] : '';
					$options        = shortcode_parse_atts( $options_string );

					$a = shortcode_atts(
						array(
							'page_id' => 'admin',
							'text' => esc_html__( 'Cancel Workflow', 'gravityflow' ),
						), $options
					);

					$entry_url = $this->get_entry_url( $a['page_id'], $assignee, $cancel_token );

					$entry_link = sprintf( '<a href="%s">%s</a>', $entry_url, $a['text'] );
					$text = str_replace( $full_tag, $entry_link, $text );
				}
			}
		}

		preg_match_all( '/{assignees(:(.*?))?}/', $text, $assignees_matches, PREG_SET_ORDER );
		if ( is_array( $assignees_matches ) ) {
			foreach ( $assignees_matches as $assignees_match ) {
				$full_tag       = $assignees_match[0];
				$options_string = isset( $assignees_match[2] ) ? $assignees_match[2] : '';
				$options        = shortcode_parse_atts( $options_string );

				$a = shortcode_atts(
					array(
						'status' => true,
						'user_email' => true,
						'display_name' => true,
					), $options
				);
				$a['status'] = strtolower( $a['status'] ) == 'false' ? false : true;
				$a['user_email'] = strtolower( $a['user_email'] ) == 'false' ? false : true;
				$a['display_name'] = strtolower( $a['display_name'] ) == 'false' ? false : true;

				$assignees_text_arr = array();
				$assignees = $this->get_assignees();
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
				$text = str_replace( $full_tag, $assignees_text, $text );
			}
		}

		return $text;
	}

	/**
	 * @param int|null $page_id
	 * @param Gravity_Flow_Assignee $assignee
	 * @param string $access_token
	 *
	 * @return string
	 */
	public function get_entry_url( $page_id = null, $assignee = null, $access_token = '' ) {

		if ( $assignee && $assignee->get_type() == 'email' ) {
			$token_lifetime_days = apply_filters( 'gravityflow_entry_token_expiration_days', 30, $assignee );
			$token_expiration_timestamp = strtotime( '+' . (int) $token_lifetime_days . ' days' );
			$access_token = $access_token ? $access_token : gravity_flow()->generate_access_token( $assignee, null, $token_expiration_timestamp );
		}

		if ( empty( $page_id ) || $page_id == 'admin' ) {
			$base_url = admin_url( 'admin.php' );
		} else {
			$base_url = get_permalink( $page_id );
		}
		$url = add_query_arg( array(
			'page' => 'gravityflow-inbox',
			'view' => 'entry',
			'id' => $this->get_form_id(),
			'lid' => $this->get_entry_id(),
		), $base_url);

		if ( ! empty( $access_token ) ) {
			$url = add_query_arg( array( 'gflow_access_token' => $access_token ), $url );
		}

		return $url;
	}

	/**
	 * Updates the status for this step.
	 *
	 * @param string|bool $status
	 */
	public function update_step_status( $status = false ) {
		if ( empty( $status ) ) {
			$status = 'pending';
		}
		$entry_id = $this->get_entry_id();
		$step_id = $this->get_id();
		gform_update_meta( $entry_id , 'workflow_step_status_' . $step_id, $status );
		gform_update_meta( $entry_id , 'workflow_step_status_' . $step_id . '_timestamp', time() );
	}

	/**
	 * Ends the step if it's complete.
	 *
	 * @return bool Is the step complete?
	 */
	public function end_if_complete() {

		$id = $this->get_next_step_id();
		$this->set_next_step_id( $id );

		$complete = $this->is_complete();
		if ( $complete ) {
			$this->end();
		}

		return $complete;
	}

	/**
	 * Optionally override this method to add additional entry meta. See the Gravity Forms Add-On Framework for details on the return array.
	 *
	 * @param array $entry_meta
	 * @param int $form_id
	 *
	 * @return array
	 */
	public function get_entry_meta( $entry_meta, $form_id ) {
		return array();
	}

	/**
	 * Returns the status key
	 *
	 * @param $assignee
	 * @param bool $type
	 *
	 * @return string
	 */
	public function get_status_key( $assignee, $type = false ) {

		if ( $type === false ) {
			list( $type, $value ) = rgexplode( '|', $assignee, 2 );
		} else {
			$value = $assignee;
		}

		$key = 'workflow_' . $type . '_' . $value;
		return $key;
	}

	/**
	 * Returns the status timestamp key
	 *
	 * @param $assignee_key
	 * @param bool $type
	 *
	 * @return string
	 */
	public function get_status_timestamp_key( $assignee_key, $type = false ) {

		if ( $type === false ) {
			list( $type, $value ) = rgexplode( '|', $assignee_key, 2 );
		} else {
			$value = $assignee_key;
		}

		$key = 'workflow_' . $type . '_' . $value . '_timestamp';
		return $key;
	}

	public function get_status() {
		$status_key = 'workflow_step_status_' . $this->get_id();
		$status = gform_get_meta( $this->get_entry_id(), $status_key );
		return $status;
	}

	/**
	 * Evaluates the status for the step.
	 * Override this method for interactive or long running steps.
	 *
	 * @return string 'queued' or 'complete'
	 */
	public function evaluate_status() {
		if ( $this->is_queued() ) {
			return 'queued';
		}

		if ( $this->is_expired() ) {
			return $this->get_expiration_status_key();
		}

		return 'complete';
	}

	public function get_expiration_status_key() {
		$status_expiration = $this->status_expiration ? $this->status_expiration : 'complete';

		return $status_expiration;
	}

	/**
	 * Processes the conditional logic for the entry in this step.
	 *
	 * @param $form
	 *
	 * @return bool
	 */
	public function is_condition_met( $form ) {
		$feed_meta            = $this->_meta;
		$is_condition_enabled = rgar( $feed_meta, 'feed_condition_conditional_logic' ) == true;
		$logic                = rgars( $feed_meta, 'feed_condition_conditional_logic_object/conditionalLogic' );

		if ( ! $is_condition_enabled || empty( $logic ) ) {
			return true;
		}
		$entry = $this->get_entry();
		return GFCommon::evaluate_conditional_logic( $logic, $form, $entry );
	}

	/**
	 * Returns the status for a user. Defaults to current WordPress user or authenticated email address.
	 *
	 * @param int|string|bool $user_id
	 *
	 * @return bool|mixed
	 */
	public function get_user_status( $user_id = false ) {

		global $current_user;

		$type = 'user_id';

		if ( empty( $user_id ) ) {
			if ( $token = gravity_flow()->decode_access_token() ) {
				$assignee_key = sanitize_text_field( $token['sub'] );
				list( $type, $user_id ) = rgexplode( '|', $assignee_key, 2 );
			} else {
				$user_id = $current_user->ID;
			}
		}

		$key = $this->get_status_key( $user_id, $type );

		return gform_get_meta( $this->get_entry_id(), $key );
	}

	/**
	 * Returns the status for the given role.
	 *
	 * @param string $role
	 *
	 * @return bool|mixed
	 */
	public function get_role_status( $role ) {

		if ( empty( $role ) ) {
			return false;
		}
		$key = $this->get_status_key( $role, 'role' );
		return gform_get_meta( $this->get_entry_id(), $key );
	}

	/**
	 * Updates the status for the given user.
	 *
	 * @param bool $user_id
	 * @param bool $new_assignee_status
	 */
	public function update_user_status( $user_id = false, $new_assignee_status = false ) {
		if ( $user_id === false ) {
			global $current_user;
			$user_id = $current_user->ID;
		}

		$key = $this->get_status_key( $user_id, 'user_id' );
		gform_update_meta( $this->get_entry_id(), $key, $new_assignee_status );
	}

	/**
	 * Updates the status for the given role.
	 *
	 * @param string|bool $role
	 * @param bool $new_assignee_status
	 */
	public function update_role_status( $role = false, $new_assignee_status = false ) {
		if ( $role == false ) {
			$roles = gravity_flow()->get_user_roles( $role );
			$role = current( $roles );
		}
		$entry_id = $this->get_entry_id();
		$key = $this->get_status_key( $role, 'role' );
		$timestamp = gform_get_meta( $entry_id, $key . '_timestamp' );
		$duration = $timestamp ? time() - $timestamp : 0;

		gform_update_meta( $entry_id, $key, $new_assignee_status );
		gform_update_meta( $entry_id, $key . '_timestamp', time() );
		gravity_flow()->log_event( 'assignee', 'status', $this->get_form_id(), $entry_id, $new_assignee_status, $this->get_id(), $duration, $role, 'role', $role );
	}

	/**
	 * Override this method to return assignees for this step. If the entry is set then the assignees must be for the current entry.
	 *
	 * @return Gravity_Flow_Assignee[]
	 */
	public function get_assignees() {
		return array();
	}

	/**
	 * Removes assignee from the step. This is only used for maintenance when the assignee settings change.
	 *
	 * @param Gravity_Flow_Assignee|bool $assignee
	 */
	public function remove_assignee( $assignee = false ) {
		if ( $assignee === false ) {
			global $current_user;
			$assignee = new Gravity_Flow_Assignee( 'user_id|' . $current_user->ID, $this );
		}

		$assignee->remove();
	}

	/**
	 * Handles POSTed values from the workflow detail page.
	 *
	 * @param $form
	 * @param $entry
	 *
	 * @return string|bool|WP_Error Return a success feedback message or a WP_Error instance with an error.
	 */
	public function maybe_process_status_update( $form, $entry ) {
		return false;
	}

	/**
	 * Displays content inside the Workflow metabox on the workflow detail page.
	 *
	 * @param $form
	 */
	public function workflow_detail_status_box( $form ) {

	}

	/**
	 * Displays content inside the Workflow metabox on the Gravity Forms Entry Detail page.
	 *
	 * @param $form
	 */
	public function entry_detail_status_box( $form ) {

	}

	/**
	 * Override to return an array of editable fields for the current user.
	 * @return array
	 */
	public function get_editable_fields() {
		return array();
	}

	/**
	 * Sends an email.
	 *
	 * @param $notification
	 */
	public function send_notification( $notification ) {

		$entry = $this->get_entry();

		$form = $this->get_form();

		$notification = apply_filters( 'gravityflow_notification', $notification, $form, $entry, $this );

		$to = rgar( $notification, 'to' );

		if ( in_array( $to, $this->_assignees_emailed ) ) {
			$this->log_debug( __METHOD__ . '() - aborting. assignee has already been sent a notification.' );

			return;
		}

		$this->_assignees_emailed[] = $to;

		$this->log_debug( __METHOD__ . '() - sending notification: ' . print_r( $notification, true ) );

		GFCommon::send_notification( $notification, $form, $entry );

	}

	/**
	 * If Gravity PDF is enabled we'll generate the appropriate PDF and attach it to the current notification
	 *
	 * @param array $notification The notification array currently being sent
	 * @param string $gpdf_id      The Gravity PDF ID
	 *
	 * @return array
	 */
	public function gpdf_add_notification_attachment( $notification, $gpdf_id ) {

		if ( ! class_exists( 'GPDFAPI' ) ) {
			return $notification;
		}

		/* Generate and save the PDF */
		$entry_id = $this->get_entry_id();
		$pdf_path = GPDFAPI::create_pdf( $entry_id, $gpdf_id );

		if ( ! is_wp_error( $pdf_path ) ) {
			/* Ensure our notification has an array setup for the attachments key */
			$notification['attachments']  = ( isset( $notification['attachments'] ) ) ? $notification['attachments'] : array();
			$notification['attachments'][] = $pdf_path;
		}

		return $notification;
	}

	/**
	 * Ends the step cleanly and wraps up loose ends.
	 * Sets the next step. Deletes assignee status entry meta.
	 */
	public function end() {
		$next_step_id = $this->get_next_step_id();
		$this->set_next_step_id( $next_step_id );
		$status = $this->evaluate_status();
		$started = $this->get_step_timestamp();
		$duration = time() - $started;
		$this->update_step_status( $status );

		$assignees = $this->get_assignees();

		foreach ( $assignees as $assignee ) {
			$assignee->remove();
		}

		$entry_id = $this->get_entry_id();
		$step_id = $this->get_id();

		if ( $this->can_set_workflow_status() ) {

			gform_update_meta( $entry_id , 'workflow_current_status', $status );
			gform_update_meta( $entry_id , 'workflow_current_status_timestamp', time() );
		}

		do_action( 'gravityflow_step_complete', $step_id, $entry_id, $this->get_form_id(), $status, $this );
		$this->log_debug( __METHOD__ . '() - ending step ' . $step_id );
		$this->log_event( 'ended', $status, $duration );
	}

	/**
	 * Returns TRUE if this step can alter the current and final status.
	 * If the only status option available for this step is 'complete' then, by default, the step will not set the status.
	 * The default final status for the workflow is 'complete'.
	 *
	 * @return bool
	 */
	public function can_set_workflow_status() {
		$status_config = $this->get_status_config();
		return ! ( count( $status_config ) === 1 && $status_config[0]['status'] = 'complete' );
	}

	/**
	 * Override this method to check whether the step is complete in interactive and long running steps.
	 *
	 * @return bool
	 */
	public function is_complete() {
		$status = $this->evaluate_status();
		return $status == 'complete';
	}

	/**
	 * Adds a note to the timeline. The timeline is a filtered subset of the Gravity Forms Entry notes.
	 *
	 * @param $note
	 * @param bool $user_id
	 * @param bool $user_name
	 */
	public function add_note( $note, $user_id = false, $user_name = false ) {
		global $current_user;

		if ( empty( $user_id ) ) {
			$type = '';
			if ( $token = gravity_flow()->decode_access_token() ) {
				$assignee_key = sanitize_text_field( $token['sub'] );
				list( $type, $user_id ) = rgexplode( '|', $assignee_key, 2 );
			} elseif ( is_user_logged_in() ) {
				$user_id = $current_user->ID;
				$type = 'user_id';
			}
			if ( $type == 'user_id' ) {
				$user = get_user_by( 'id', $user_id );
				$user_name = $user ? $user->display_name : '';
			}
		}

		if ( empty( $user_name ) ) {
			$user_name = 'gravityflow';
		}

		GFFormsModel::add_note( $this->get_entry_id(), $user_id, $user_name, $note, 'gravityflow' );
	}

	/**
	 * Evaluates a routing rule.
	 *
	 * @param $routing_rule
	 *
	 * @return bool Is the routing rule a match?
	 */
	public function evaluate_routing_rule( $routing_rule ) {

		gravity_flow()->log_debug( __METHOD__ . '(): rule:' . print_r( $routing_rule, true ) );

		$entry = $this->get_entry();

		$form_id = $this->get_form_id();

		$entry_meta_keys = array_keys( GFFormsModel::get_entry_meta( $form_id ) );

		$form = GFAPI::get_form( $form_id );

		if ( in_array( $routing_rule['fieldId'], $entry_meta_keys ) ) {
			$is_value_match = GFFormsModel::is_value_match( rgar( $entry, $routing_rule['fieldId'] ), $routing_rule['value'], $routing_rule['operator'], null, $routing_rule, $form );
		} else {
			$source_field   = GFFormsModel::get_field( $form, $routing_rule['fieldId'] );
			$field_value    = empty( $entry ) ? GFFormsModel::get_field_value( $source_field, array() ) : GFFormsModel::get_lead_field_value( $entry, $source_field );
			$is_value_match = GFFormsModel::is_value_match( $field_value, $routing_rule['value'], $routing_rule['operator'], $source_field, $routing_rule, $form );
		}

		gravity_flow()->log_debug( __METHOD__ . '(): is_match:' . print_r( $is_value_match, true ) );

		return $is_value_match;
	}

	/**
	 * Sends a notification to an array of assignees.
	 *
	 * @param array $assignees
	 * @param array $notification
	 */
	public function send_notifications( $assignees, $notification ) {
		if ( empty( $assignees ) ) {
			return;
		}
		$form = $this->get_form();
		if ( empty( $notification['subject'] ) ) {
			$notification['subject'] = $form['title'] . ': ' . $this->get_name();
		} else {
			$notification['subject'] = $this->replace_variables( $notification['subject'], null );
		}

		foreach ( $assignees as $assignee ) {
			/* @var Gravity_Flow_Assignee $assignee */
			$assignee_type = $assignee->get_type();
			$assignee_id = $assignee->get_id();

			if ( $assignee_type == 'email' ) {
				$email = $assignee_id;
				$notification['to']      = $email;
				$notification['id']      = 'workflow_step_' . $this->get_id() . '_email_' . $email;
				$notification['name']    = $notification['id'];
				$notification['message'] = $this->replace_variables( $notification['message'], $assignee );
				$this->send_notification( $notification );
				return;
			}


			if ( $assignee_type == 'role' ) {
				$users = get_users( array( 'role' => $assignee_id ) );
			} else {
				$users = get_users( array( 'include' => array( $assignee_id ) ) );
			}

			foreach ( $users as $user ) {
				$notification['id']      = 'workflow_step_' . $this->get_id() . '_user_' . $user->ID;
				$notification['name']    = $notification['id'];
				$notification['to']      = $user->user_email;
				$notification['message'] = $this->replace_variables( $notification['message'], $assignee );
				$this->send_notification( $notification );
			}
		}
	}

	/**
	 * Returns the number of entries on this step.
	 *
	 * @return int|mixed
	 */
	public function entry_count() {
		if ( isset( $this->_entry_count ) ) {
			return $this->_entry_count;
		}
		$form_id = $this->get_form_id();
		$search_criteria = array(
			'status' => 'active',
			'field_filters' => array(
				array(
					'key' => 'workflow_step',
					'value' => $this->get_id(),
				),
			),
		);
		$this->_entry_count = GFAPI::count_entries( $form_id, $search_criteria );
		return $this->_entry_count;
	}

	/**
	 * Logs debug messages to the Gravity Flow log file generated by the Gravity Forms Logging Add-On.
	 *
	 * @param string $message
	 */
	public function log_debug( $message ) {
		gravity_flow()->log_debug( $message );
	}

	public function get_feed_meta() {
		return $this->_meta;
	}

	/**
	 * Process token action if conditions are satisfied.
	 *
	 * @param $action
	 * @param $token
	 * @param $form
	 * @param $entry
	 *
	 * @return bool|WP_Error
	 */
	public function maybe_process_token_action( $action, $token, $form, $entry ) {
		return false;
	}

	public function log_event( $step_event, $step_status = '', $duration = 0 ) {

		gravity_flow()->log_event( 'step', $step_event, $this->get_form_id(), $this->get_entry_id(), $step_status, $this->get_id(), $duration );

	}

	public function supports_expiration() {
		return false;
	}

	/**
	 * Returns the correct value for the step setting for the current context - either step settings or step processing.
	 *
	 * @param $setting
	 *
	 * @return array|mixed|string
	 */
	public function get_setting( $setting ) {
		$meta = $this->get_feed_meta();

		if ( empty( $meta ) ) {
			$value = gravity_flow()->get_setting( $setting );
		} else {
			$value = $this->{$setting};
		}
		return $value;
	}

	/**
	 * Process a status change for an assignee.
	 *
	 * @param Gravity_Flow_Assignee $assignee
	 * @param string $new_status
	 * @param array $form
	 *
	 * @return string|bool If processed return a message to be displayed to the user.
	 */
	public function process_assignee_status( $assignee, $new_status, $form ) {
		$assignee->update_status( $new_status );
		$note = $this->get_name() . ': ' . esc_html__( 'Processed', 'gravityflow' );
		$this->add_note( $note );
		return $note;
	}

	public function is_assignee( $assignee_key ) {
		$assignees = $this->get_assignees();
		$current_user = wp_get_current_user();
		foreach ( $assignees as $assignee ) {
			$key = $assignee->get_key();
			if ( $key == $assignee_key ) {
				return true;
			}
			if ( $assignee->get_type() == 'role' && in_array( $assignee->get_id(), (array) $current_user->roles ) ) {
				return true;
			}
		}
	}

	/**
	 * Removes assignees from and/or adds assignees to a step. Call after updating entry values.
	 * Make sure you call get_assignees() to get the assignees before you update the entry before you update the entry or the previous assignees may not get removed.
	 *
	 * @param Gravity_Flow_Assignee[] $previous_assignees The previous assignees.
	 */
	public function maybe_adjust_assignment( $previous_assignees ) {

		gravity_flow()->log_debug( __METHOD__ . '(): Starting' );

		$new_assignees = $this->get_assignees();
		$new_assignees_keys = array();
		foreach ( $new_assignees  as $new_assignee ) {
			$new_assignees_keys[] = $new_assignee->get_key();
		}
		$previous_assignees_keys = array();
		foreach ( $previous_assignees  as $previous_assignee ) {
			$previous_assignees_keys[] = $previous_assignee->get_key();
		}

		$assignee_keys_to_add = array_diff( $new_assignees_keys, $previous_assignees_keys );
		$assignee_keys_to_remove = array_diff( $previous_assignees_keys, $new_assignees_keys );

		foreach ( $assignee_keys_to_add as $assignee_key_to_add ) {
			$assignee_to_add = new Gravity_Flow_Assignee( $assignee_key_to_add, $this );
			$assignee_to_add->update_status( 'pending' );
		}

		foreach ( $assignee_keys_to_remove as $assignee_key_to_remove ) {
			$assignee_to_remove = new Gravity_Flow_Assignee( $assignee_key_to_remove, $this );
			$assignee_to_remove->remove();
		}
	}
}

