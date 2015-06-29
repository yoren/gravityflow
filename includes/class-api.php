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
			$status = $current_step->get_status();
		}

		return $status;
	}

}

