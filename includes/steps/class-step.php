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
	 * @var null
	 */
	private $_entry;

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
	function __construct( $feed = array(), $entry = null ) {

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
	public function __get( $name ){
		if ( ! isset( $this->_meta[ $name ] ) ) {
			$this->_meta[ $name ] = '';
		}

		return $this->_meta[ $name ];
	}

	public function __set( $key, $value ){
		$this->_meta[ $key ] = $value;
		$this->$key = $value;
	}

	/**
	 * Returns an array with the configuration of the final status options for this step.
	 * These options will appear in the step settings.
	 * Override this method to add final status options.
	 *
	 * e.g.
	 * array(
	 *    'status' => 'complete',
	 *    'status_label' => __( 'Complete', 'gravityflow' ),
	 *    'default_destination_label' => __( 'Next Step', 'gravityflow' ),
	 *    'default_destination' => 'next',
	 *    )
	 *
	 *
	 * @return array
	 */
	public function get_final_status_config(){
		return array(
			array(
				'status' => 'complete',
				'status_label' => __( 'Complete', 'gravityflow' ),
				'default_destination_label' => __( 'Next Step', 'gravityflow' ),
				'default_destination' => 'next',
			),
		);
	}

	/**
	 * Returns the translated label for a status key.
	 *
	 * @param $status
	 *
	 * @return string
	 */
	public function get_status_label($status) {
		if ( $status == 'pending' ) {
			return __( 'Pending', 'gravityflow' );
		}
		$final_statuses = $this->get_final_status_config();
		foreach ( $final_statuses as $final_status ) {
			if ( $status == rgar( $final_status, 'status' ) ) {
				return isset( $final_status['status_label'] ) ? $final_status['status_label'] : $status;
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
	public function get_entry(){
		return $this->_entry;
	}

	/**
	 * Flushes and reloads the cached entry for this step.
	 *
	 * @return array|mixed|null
	 */
	public function refresh_entry(){
		$entry_id = $this->get_entry_id();
		$this->_entry = GFAPI::get_entry( $entry_id );
		return $this->_entry;
	}

	/**
	 * Returns the Form object for this step.
	 *
	 * @return mixed
	 */
	public function get_form(){
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
	 * Returns the ID for the current entry object (if set).
	 *
	 * @return int
	 */
	public function get_entry_id(){
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
	public function get_id(){
		return $this->_id;
	}

	/**
	 * Is the step active? The step may have been deactivated by the user in the list of steps.
	 *
	 * @return bool
	 */
	public function is_active(){
		return $this->_is_active;
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
	public function get_name(){
		return $this->step_name;
	}

	/**
	 * Override this method to add settings to the step. Use the Gravity Forms Add-On Framework Settings API.
	 *
	 * @return array
	 */
	public function get_settings(){
		return array();
	}

	/**
	 * Returns the ID of the next step.
	 *
	 * @return int|mixed|string
	 */
	public function get_next_step_id(){
		if ( isset( $this->_next_step_id ) ) {
			return $this->_next_step_id;
		}
		$next_step_id = $this->is_complete() ? $this->destination_complete : $this->get_id();
		$this->set_next_step_id( $next_step_id );
		return $next_step_id;
	}

	/**
	 * Sets the next step.
	 *
	 * @param $id
	 */
	public function set_next_step_id( $id ){
		$this->_next_step_id = $id;
	}

	/**
	 * Attempts to start this step for the current entry. If the step is scheduled then the entry will be queued.
	 *
	 * @return bool Is the step complete?
	 */
	public function start(){
		$this->log_debug( __METHOD__ . '() - triggered step: ' . $this->get_name() );

		$step_id = $this->get_id();
		$entry_id = $this->get_entry_id();
		gform_update_meta( $entry_id , 'workflow_step', $step_id );

		if ( $this->scheduled && ! $this->validate_schedule() ) {
			$this->log_debug( __METHOD__ . '() - Step queued: ' . $this->get_name() );
			$this->update_step_status( 'queued' );
			$complete = false;
		} else {
			$this->log_debug( __METHOD__ . '() - Starting step: ' . $this->get_name() );

			$this->update_step_status();

			$this->refresh_entry();

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
	function is_queued(){
		$entry = $this->get_entry();

		return rgar( $entry, 'workflow_step_status_' . $this->get_id() ) == 'queued';
	}

	/**
	 * Validates the step schedule.
	 *
	 * @return bool Returns true if step is ready to proceed.
	 */
	function validate_schedule() {
		if ( ! $this->scheduled ) {
			return true;
		}

		$schedule_timestamp = $this->get_schedule_timestamp();

		return time() >= $schedule_timestamp;
	}

	/**
	 * Returns the schedule timestamp calculated from the schedule settings.
	 *
	 * @return int
	 */
	function get_schedule_timestamp(){

		if ( $this->schedule_type == 'date' ) {
			$date_gmt = get_gmt_from_date( $this->schedule_date );
			return strtotime( $date_gmt );
		}

		$entry = $this->get_entry();

		$entry_timestamp = $entry['workflow_timestamp'];

		$schedule_timestamp = $entry_timestamp;

		switch ( $this->schedule_delay_unit ) {
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

	public function get_timestamp(){
		$entry = $this->get_entry();

		return $entry['workflow_timestamp'];
	}


	/**
	 * Process the step. For example, assign to a user, send to a service or send a notification. Return (bool) $complete.
	 *
	 * @return bool Is the step complete?
	 */
	public function process(){
		$complete = $this->is_complete();

		$assignee_details = $this->get_assignees();

		if ( empty( $assignee_details ) ) {
			$note = sprintf( __( '%s: not required', 'gravityflow' ), $this->get_name() );
			$this->add_note( $note, 0 , 'gravityflow' );
		} else {
			foreach ( $assignee_details as $assignee_detail ) {
				$assignee = is_array( $assignee_detail ) ? $assignee_detail['assignee'] : $assignee_detail;
				$this->update_assignee_status( $assignee, false, 'pending' );
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
	 * @param $assignee
	 */
	public function maybe_send_assignee_notification( $assignee ){
		if ( $this->assignee_notification_enabled ) {
			$this->send_assignee_notification( $assignee );
		}
	}

	/**
	 * Sends the assignee email.
	 *
	 * @param $assignee
	 */
	public function send_assignee_notification( $assignee ) {
		$this->log_debug( __METHOD__ . '() assignee notification enabled. assignee: ' . $assignee );
		list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee, 2 );
		if ( $assignee_type == 'assignee_field' ) {
			$entry       = $this->get_entry();
			$assignee_id = rgar( $entry, $assignee_id );
			list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee_id, 2 );
		}
		if ( $assignee_type == 'entry' ) {
			$entry       = $this->get_entry();
			$assignee_id = rgar( $entry, $assignee_id );
		}
		if ( $assignee_type == 'role' ) {
			$users = get_users( array( 'role' => $assignee_id ) );
		} else {
			$users = get_users( array( 'include' => array( $assignee_id ) ) );
		}
		$form = $this->get_form();

		$this->log_debug( __METHOD__ . sprintf( '() sending assignee notifications to %d users', count( $users ) ) );
		$this->log_debug( __METHOD__ . sprintf( '() users: ', print_r( $users, true ) ) );

		foreach ( $users as $user ) {
			$notification['id']      = 'workflow_step_' . $this->get_id() . '_user_' . $user->ID;
			$notification['name']    = $notification['id'];
			$notification['to']      = $user->user_email;
			$notification['fromName'] = get_bloginfo();
			$notification['from']     = get_bloginfo( 'admin_email' );
			$notification['subject'] = $form['title'] . ': ' . $this->get_name();
			$notification['message'] = $this->replace_variables( $this->assignee_notification_message, $user->ID );
			$this->send_notification( $notification );
		}
	}

	/**
	 * Override this method to replace merge tags.
	 *
	 * @param $text
	 * @param $user_id
	 *
	 * @return mixed
	 */
	public function replace_variables($text, $user_id){
		return $text;
	}

	/**
	 * Updates the status for this step.
	 *
	 * @param bool $status
	 */
	public function update_step_status( $status = false ){
		if ( empty( $status ) ) {
			$status = 'pending';
		}
		$entry_id = $this->get_entry_id();
		$step_id = $this->get_id();
		gform_update_meta( $entry_id , 'workflow_step_status_' . $step_id, $status );
	}

	/**
	 * Ends the step if it's complete.
	 *
	 * @return bool Is the step complete?
	 */
	public function end_if_complete(){

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
	 * @param $entry_meta
	 * @param $form_id
	 *
	 * @return array
	 */
	public function get_entry_meta( $entry_meta, $form_id){
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
	public function get_status_key( $assignee, $type = false ){

		if ( $type === false ) {
			list( $type, $value ) = rgexplode( '|', $assignee, 2 );
		} else {
			$value = $assignee;
		}

		$key = 'workflow_' . $type . '_' . $value;
		return $key;
	}

	/**
	 * Returns the status for the step.
	 * Override this method for interactive or long running steps.
	 *
	 * @return string 'queued' or 'complete'
	 */
	public function get_status() {
		if ( $this->is_queued() ) {
			return 'queued';
		}
		return 'complete';
	}

	/**
	 * Processes the conditional logic for the entry in this step.
	 *
	 * @param $form
	 *
	 * @return bool
	 */
	public function is_condition_met( $form ){
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
	 * Returns the status for a user. Current user is default.
	 *
	 * @param bool $user_id
	 *
	 * @return bool|mixed
	 */
	public function get_user_status( $user_id = false ){
		global $current_user;
		if ( $user_id === false ) {
			$user_id = $current_user->ID;
		}
		$key = $this->get_status_key( $user_id, 'user_id' );
		return gform_get_meta( $this->get_entry_id(), $key );
	}

	/**
	 * Returns the status for the given role.
	 *
	 * @param $role
	 *
	 * @return bool|mixed
	 */
	public function get_role_status( $role ){

		if ( empty( $role ) ) {
			return false;
		}
		$key = $this->get_status_key( $role, 'role' );
		return gform_get_meta( $this->get_entry_id(), $key );
	}

	/**
	 * Returns the status for the given assignee.
	 *
	 * @param $assignee
	 * @param string $assignee_type
	 *
	 * @return bool|mixed
	 */
	public function get_assignee_status( $assignee, $assignee_type = 'user_id'){
		if ( strpos( $assignee, '|', 0 ) > - 1 ) {
			list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee, 2 );
		} else {
			$assignee_id = $assignee;
		}

		if ( $assignee_type == 'assignee_field' ) {
			$entry       = $this->get_entry();
			$assignee_id = rgar( $entry, $assignee_id );
			list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee_id, 2 );
		}

		if ( $assignee_type == 'entry' ) {
			$entry = $this->get_entry();
			$assignee_id = rgar( $entry, $assignee_id );
			$assignee_type = 'user_id';
		}

		$entry_id = $this->get_entry_id();
		$key = $this->get_status_key( $assignee_id, $assignee_type );
		$cache_key = $entry_id . '_' . $key;
		global $_gform_lead_meta;
		unset( $_gform_lead_meta[ $cache_key ] );
		return gform_get_meta( $entry_id, $key );
	}

	/**
	 * Updates the status for a given assignee.
	 * Examples of assignees:
	 * user_id|1
	 * assignee_field|2
	 * role|editor
	 *
	 * @param string|bool $assignee The assignee or false.
	 * @param string $assignee_type
	 * @param bool $new_assignee_status
	 */
	public function update_assignee_status( $assignee = false, $assignee_type = 'user_id', $new_assignee_status = false ) {

		if ( $assignee === false ) {
			global $current_user;
			$assignee = $current_user->ID;
		}

		if ( strpos( $assignee, '|', 0 ) > - 1 ) {
			list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee, 2 );
		} else {
			$assignee_id   = $assignee;
		}

		if ( $assignee_type == 'assignee_field' ) {
			$entry       = $this->get_entry();
			$assignee_id = rgar( $entry, $assignee_id );
			list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee_id, 2 );
		}

		if ( $assignee_type == 'entry' ) {
			$entry = $this->get_entry();
			$assignee_id = rgar( $entry, $assignee_id );
			$assignee_type = 'user_id';
		}

		$key = $this->get_status_key( $assignee_id, $assignee_type );

		gform_update_meta( $this->get_entry_id(), $key, $new_assignee_status );
	}

	/**
	 * Updates the status for the given user.
	 *
	 * @param bool $user_id
	 * @param bool $new_assignee_status
	 */
	public function update_user_status( $user_id = false, $new_assignee_status = false) {
		if ( $user_id === false ) {
			global $current_user;
			$user_id = $current_user->ID;
		}

		$this->update_assignee_status( $user_id, 'user_id', $new_assignee_status );
	}

	/**
	 * Updates the status for the given role.
	 *
	 * @param bool $role
	 * @param bool $new_assignee_status
	 */
	public function update_role_status( $role = false, $new_assignee_status = false) {
		if ( $role == false ) {
			$roles = gravity_flow()->get_user_roles( $role );
			$role = current( $roles );
		}
		$this->update_assignee_status( $role, 'role', $new_assignee_status );
	}

	/**
	 * Override this method to return assignees for this step. If the entry is set then the assignees must be for the current entry.
	 *
	 * @return array
	 */
	public function get_assignees(){
		return array();
	}

	/**
	 * Removes assignee from the step. This is only used for maintenance when the assignee settings change.
	 *
	 * @param string|bool $assignee
	 * @param string $assignee_type
	 */
	public function remove_assignee( $assignee = false, $assignee_type = 'user_id' ){
		if ( $assignee === false ) {
			global $current_user;
			$assignee = $current_user->ID;
		}

		if ( strpos( $assignee, '|', 0 ) > - 1 ) {
			list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee, 2 );
		} else {
			$assignee_id   = $assignee;
		}

		if ( $assignee_type == 'assignee_field' ) {
			$entry       = $this->get_entry();
			$assignee_id = rgar( $entry, $assignee_id );
			list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee_id, 2 );
		}

		if ( $assignee_type == 'entry' ) {
			$entry = $this->get_entry();
			$assignee_id = rgar( $entry, $assignee_id );
			$assignee_type = 'user_id';
		}

		$key = $this->get_status_key( $assignee_id, $assignee_type );

		gform_delete_meta( $this->get_entry_id(), $key );
	}

	/**
	 * Handles POSTed values from the workflow detail page.
	 *
	 * @param $form
	 * @param $entry
	 *
	 * @return string|bool Return a feedback message to display to the user.
	 */
	public function maybe_process_status_update( $form, $entry ){
		return false;
	}

	/**
	 * Displays content inside the Workflow metabox on the workflow detail page.
	 *
	 * @param $form
	 */
	public function workflow_detail_status_box( $form ){
		if ( $this->is_queued() ) {
			printf( '<h4>%s (%s)</h4>', $this->get_name(), esc_html__( 'Queued', 'gravityflow' ) );
		}
	}

	/**
	 * Displays content inside the Workflow metabox on the Gravity Forms Entry Detail page.
	 *
	 * @param $form
	 */
	public function entry_detail_status_box( $form ){

	}

	/**
	 * Override to return an array of editable fields for the given user.
	 *
	 * @param int|bool $user_id
	 *
	 * @return array
	 */
	public function get_editable_fields( $user_id = false ){
		return array();
	}

	/**
	 * Sends an email.
	 *
	 * @param $notification
	 */
	public function send_notification( $notification ){

		$entry = $this->get_entry();

		$form = $this->get_form();

		$notification = apply_filters( 'gravityflow_notification', $notification, $form, $entry, $this );

		$this->log_debug( __METHOD__ . '() - sending notification: ' . print_r( $notification, true ) );

		GFCommon::send_notification( $notification, $form, $entry );

	}

	/**
	 * Ends the step cleanly and wraps up loose ends.
	 * Sets the next step. Deletes assignee status entry meta.
	 */
	public function end(){
		$next_step_id = $this->get_next_step_id();
		$this->set_next_step_id( $next_step_id );
		$status = $this->get_status();
		$this->update_step_status( $status );

		$assignee_details = $this->get_assignees();

		foreach ( $assignee_details as $assignee_detail ) {
			$assignee = is_array( $assignee_detail ) ? $assignee_detail['assignee'] : $assignee_detail;
			$user_status_key = $this->get_status_key( $assignee );
			gform_delete_meta( $this->get_entry_id(), $user_status_key );
		}

	}

	/**
	 * Override this method to check whether the step is complete in interactive and long running steps.
	 *
	 * @return bool
	 */
	public function is_complete(){
		return true;
	}

	/**
	 * Adds a note to the timeline. The timeline is a filtered subset of the Gravity Forms Entry notes.
	 *
	 * @param $note
	 * @param bool $user_id
	 * @param bool $user_name
	 */
	public function add_note( $note, $user_id = false, $user_name = false ){
		global $current_user;
		if ( $user_id === false ) {
			$user_id = $current_user->ID;
		}

		if ( $user_name === false ) {
			global $current_user;
			$user_name = $current_user->display_name;
		}

		if ( empty ( $user_name ) ) {
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

		return $is_value_match;
	}

	/**
	 * Sends notifications to assignees.
	 *
	 * @param array $assignees
	 * @param string $message
	 */
	public function send_notifications( $assignees, $message) {
		if ( empty( $assignees ) ) {
			return;
		}
		foreach ( $assignees as $assignee ) {
			list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee, 2 );
			if ( $assignee_type == 'assignee_field' ) {
				$entry       = $this->get_entry();
				$assignee_id = rgar( $entry, $assignee_id );
				list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee_id, 2 );
			}

			if ( $assignee_type == 'entry' ) {
				$entry       = $this->get_entry();
				$assignee_id = rgar( $entry, $assignee_id );
			}
			if ( $assignee_type == 'role' ) {
				$users = get_users( array( 'role' => $assignee_id ) );
			} else {
				$users = get_users( array( 'include' => array( $assignee_id ) ) );
			}
			$form = $this->get_form();
			foreach ( $users as $user ) {
				$notification['id']      = 'workflow_step_' . $this->get_id() . '_user_' . $user->ID;
				$notification['name']    = $notification['id'];
				$notification['to']      = $user->user_email;
				$notification['fromName'] = get_bloginfo();
				$notification['from']     = get_bloginfo( 'admin_email' );
				$notification['subject'] = $form['title'] . ': ' . $this->get_name();
				$notification['message'] = $this->replace_variables( $message, $user->ID );
				$this->send_notification( $notification );
			}
		}
	}

	/**
	 * Returns the number of entries on this step.
	 *
	 * @return int|mixed
	 */
	public function entry_count(){
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
	 * Logs debug messages to the Gravity Flow log file generated by the Gravity Forms Loggin Add-On.
	 *
	 * @param $message
	 */
	public function log_debug( $message ) {
		gravity_flow()->log_debug( $message );
	}

	public function get_feed_meta(){
		return $this->_meta;
	}
}

