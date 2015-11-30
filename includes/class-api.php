<?php
/**
 * Gravity Flow API
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/API
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */


if ( ! class_exists( 'GFForms' ) ) {
	die();
}
/**
 * The future-proof way to interact with the high level functions in Gravity Flow.
 *
 * Class Gravity_Flow_API
 *
 *
 * @since		1.0
 */
class Gravity_Flow_API {

	/**
	 * The ID of the Form to be used throughout the Gravity Flow API.
	 *
	 * @var int
	 */
	public $form_id = null;

	/**
	 * The constructor for the API. Requires a Form ID.
	 *
	 * @param $form_id
	 */
	public function __construct( $form_id ) {
		$this->form_id = $form_id;
	}

	/**
	 * Adds a Workflow step to the form with the given settings. The following settings are required:
	 * - step_name (string)
	 * - step_type (string)
	 * - description (string)
	 *
	 * @param $step_settings
	 *
	 * @return mixed
	 */
	public function add_step( $step_settings ) {
		return GFAPI::add_feed( $this->form_id, $step_settings, 'gravityflow' );
	}

	/**
	 * Returns the step with the given step ID. Optionally pass an Entry object to perform entry-specific functions.
	 *
	 * @param $step_id
	 * @param null $entry
	 *
	 * @return Gravity_Flow_Step|bool Returns the Step. False if not found.
	 */
	public function get_step( $step_id, $entry = null  ) {
		return gravity_flow()->get_step( $step_id, $entry );
	}

	/**
	 * Returns all the steps for current form.
	 *
	 * @return Gravity_Flow_Step[]
	 */
	public function get_steps() {
		return gravity_flow()->get_steps( $this->form_id );
	}

	/**
	 * Returns the current step for the given entry.
	 *
	 * @param $entry
	 *
	 * @return Gravity_Flow_Step|bool
	 */
	public function get_current_step( $entry ) {
		return gravity_flow()->get_current_step( $this->form_id, $entry );
	}

	/**
	 * Processes the workflow for the given Entry ID. Handles the step orchestration - moving the workflow through the steps and ending the workflow.
	 *
	 * @param $entry_id
	 */
	public function process_workflow( $entry_id ){
		$form = GFAPI::get_form( $this->form_id );
		gravity_flow()->process_workflow( $form,  $entry_id );
	}

	/**
	 * Cancels the workflow for the given Entry ID. Removes the assignees and logs the event.
	 *
	 * @param array $entry The entry
	 * @return bool
	 */
	public function cancel_workflow( $entry ){
		$entry_id = absint( $entry['id'] );
		$form = GFAPI::get_form( $this->form_id );
		$step = $this->get_current_step( $entry );
		if ( ! $step ) {
			return false;
		}
		$assignees = $step->get_assignees();
		foreach ( $assignees as $assignee ) {
			$assignee->remove();
		}
		gform_update_meta( $entry_id, 'workflow_final_status', 'cancelled' );
		gform_delete_meta( $entry_id, 'workflow_step' );
		$feedback = esc_html__( 'Workflow cancelled.',  'gravityflow' );
		gravity_flow()->add_timeline_note( $entry_id, $feedback );
		gravity_flow()->log_event( 'workflow', 'cancelled', $form['id'], $entry_id );
		return true;
	}

	/**
	 * Restarts the current step for the given entry, adds a note in the entry's timeline and logs the activity.
	 *
	 * @param array $entry The entry
	 * @return bool True for success. False if the entry doesn't have a current step.
	 */
	public function restart_step( $entry ){
		$step = $this->get_current_step( $entry );
		if ( ! $step ) {
			return false;
		}
		$entry_id = $entry['id'];
		$this->log_activity( 'step', 'restarted', $this->form_id, $entry_id );
		$step->start();
		$feedback = esc_html__( 'Workflow Step restarted.',  'gravityflow' );
		$this->add_timeline_note( $entry_id, $feedback );
		return true;
	}

	/**
	 * Restarts the workflow for an entry, adds a note in the entry's timeline and logs the activity.
	 *
	 * @param $entry
	 */
	public function restart_workflow( $entry ) {
		$current_step = $this->get_current_step( $entry );
		$entry_id = absint( $entry['id'] );
		$form = GFAPI::get_form( $this->form_id );
		if ( $current_step ) {
			$assignees = $current_step->get_assignees();
			foreach ( $assignees as $assignee ) {
				$assignee->remove();
			}
		}
		$feedback = esc_html__( 'Workflow restarted.',  'gravityflow' );
		$this->add_timeline_note( $entry_id, $feedback );
		gform_update_meta( $entry_id, 'workflow_final_status', 'pending' );
		gform_update_meta( $entry_id, 'workflow_step', false );
		$this->log_activity( 'workflow', 'restarted', $form['id'], $entry_id );
		$this->process_workflow( $entry_id );
	}

	/**
	 * Returns the workflow status for the current entry.
	 *
	 * @param $entry
	 *
	 * @return string|bool The status.
	 */
	public function get_status( $entry ){
		$current_step = $this->get_current_step( $entry );

		if ( $current_step === false ){
			$status = gform_get_meta( $entry['id'], 'workflow_final_status' );
		} else {
			$status = $current_step->evaluate_status();
		}

		return $status;
	}

	/**
	 * Sends and
	 *
	 * @param $entry
	 * @param $step_id
	 */
	public function send_to_step( $entry, $step_id ){
		$current_step = $this->get_current_step( $entry );
		if ( $current_step ) {
			$assignees = $current_step->get_assignees();
			foreach ( $assignees as $assignee ) {
				$assignee->remove();
			}
		}
		$entry_id = $entry['id'];
		$new_step = $this->get_step( $step_id, $entry );
		$feedback = sprintf( esc_html__( 'Sent to step: %s',  'gravityflow' ), $new_step->get_name() );
		$this->add_timeline_note( $entry_id, $feedback );
		$this->log_activity( 'workflow', 'sent_to_step', $this->form_id, $entry_id, $step_id );
		gform_update_meta( $entry_id, 'workflow_final_status', 'pending' );
		$new_step->start();
		$this->process_workflow( $entry_id );
	}

	/**
	 * Add a note to the timeline of the specified entry.
	 *
	 * @param $entry_id
	 * @param $note
	 */
	public function add_timeline_note( $entry_id, $note ) {
		gravity_flow()->add_timeline_note( $entry_id, $note );
	}

	/**
	 * Registers activity event in the activity log. The activity log is used to generate reports.
	 *
	 * @param string $log_type The object of the event. 'workflow', 'step', 'assignee'
	 * @param string $event 'started', 'ended', 'status'
	 * @param int $form_id The form ID.
	 * @param int $entry_id The Entry ID.
	 * @param string $log_value The value to log.
	 * @param int $step_id The Step ID.
	 * @param int $duration The duration in seconds - if applicable.
	 * @param int $assignee_id The assignee ID - if applicable.
	 * @param string $assignee_type - The Assignee type - if applicable
	 * @param string $display_name - The display name of the User.
	 */
	public function log_activity( $log_type, $event, $form_id = 0, $entry_id = 0, $log_value = '', $step_id = 0, $duration = 0, $assignee_id = 0, $assignee_type = '', $display_name = '' ) {
		gravity_flow()->log_event( $log_type, $event, $form_id, $entry_id, $log_value, $step_id, $duration, $assignee_id, $assignee_type, $display_name );
	}

}

