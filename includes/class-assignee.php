<?php
/**
 * Gravity Flow Assignee
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Assignee
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */


if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Assignee {
	protected $id;
	protected $type;
	protected $key;
	protected $editable_fields = array();

	/* @var Gravity_Flow_Step */
	protected $step;

	function __construct( $args = array(), $step = false) {
		$this->step = $step;
		if ( is_string( $args ) ) {
			$parts = explode( '|', $args );
			$type = $parts[0];
			$id = $parts[1];
		} elseif ( is_array( $args ) ) {

			$id = $args['id'];
			$type = $args['type'];
			if ( isset ( $args['editable_fields'] ) ) {
				$this->editable_fields = $args['editable_fields'];
			}
		} else {
			throw new Exception( 'Assignees must be instantiated with either a string or an array' );
		}

		if ( $type == 'assignee_field' ) {
			$entry = $this->step->get_entry();
			$assignee_key = rgar( $entry, $id );
			list( $this->type, $this->id ) = rgexplode( '|', $assignee_key, 2 );
		} elseif ( $type == 'email_field' ) {
			$entry = $this->step->get_entry();
			$this->id = sanitize_email( rgar( $entry, $id ) );
			$this->type = 'email';
		} elseif ( $type == 'entry' ) {
			$entry = $this->step->get_entry();
			$this->id = rgar( $entry, $id );
			$this->type = 'user_id';
		} else {
			$this->type = $type;
			$this->id = $id;
		}
		$this->key = $this->type . '|' . $this->id;
	}

	function get_id(){
		return $this->id;
	}

	function get_key(){
		return $this->key;
	}

	function get_type(){
		return $this->type;
	}

	function get_editable_fields(){
		return $this->editable_fields;
	}


	/**
	 * Returns the status.
	 *
	 * @return bool|mixed
	 */
	public function get_status(){

		$assignee_type = $this->get_type();

		$assignee_id = $this->get_id();

		$entry_id = $this->step->get_entry_id();
		$key = $this->get_status_key( $assignee_id, $assignee_type );
		$cache_key = $entry_id . '_' . $key;
		global $_gform_lead_meta;
		unset( $_gform_lead_meta[ $cache_key ] );
		return gform_get_meta( $entry_id, $key );
	}

	/**
	 * Returns the status key. Defaults to this assignee.
	 *
	 * @param string|bool $assignee_id
	 * @param string|bool $assignee_type
	 *
	 * @return string
	 */
	public function get_status_key( $assignee_id = false, $assignee_type = false ){
		if ( empty( $assignee_id ) ) {
			$assignee_id = $this->get_id();
		}

		if ( empty( $assignee_type ) ) {
			$assignee_type = $this->get_type();
		}
		$key = 'workflow_' . $assignee_type . '_' . $assignee_id;
		return $key;
	}

	public function update_status( $new_assignee_status = false ) {

		$assignee_type = $this->get_type();

		$assignee_id = $this->get_id();

		$key = $this->get_status_key( $assignee_id, $assignee_type );

		$assignee_status_timestamp = gform_get_meta( $this->step->get_entry_id(), $key . '_timestamp' );

		$duration = $assignee_status_timestamp ? time() - $assignee_status_timestamp : 0;

		gform_update_meta( $this->step->get_entry_id(), $key, $new_assignee_status );
		gform_update_meta( $this->step->get_entry_id(), $key . '_timestamp', time() );

		$this->log_event( $new_assignee_status, $duration );
	}

	public function get_display_name(){
		if ( $this->get_type() == 'user_id' ) {
			$user = get_user_by( 'id', $this->get_id() );
			$name = $user->display_name;
		} else {
			$name = $this->get_id();
		}

		return $name;
	}

	public function remove(){
		$key = $this->get_status_key();

		gform_delete_meta( $this->step->get_entry_id(), $key );
		gform_delete_meta( $this->step->get_entry_id(), $key . '_timestamp' );
	}

	/**
	 * Returns the status timestamp.
	 *
	 * @return bool|mixed
	 */
	public function get_status_timestamp(){

		$key = $this->get_status_key( $this->get_id(), $this->get_type() );
		return gform_get_meta( $this->step->get_entry_id(), $key );
	}

	public function log_event( $status, $duration = 0) {
		gravity_flow()->log_event( 'assignee', 'status', $this->step->get_form_id(), $this->step->get_entry_id(), $status, $this->step->get_id(), $duration, $this->get_id(), $this->get_type(), $this->get_display_name() );
	}

}