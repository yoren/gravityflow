<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Notification extends Gravity_Flow_Step {
	public $_step_type = 'notification';

	public function get_label() {
		return esc_html__( 'Notification', 'gravityflow' );
	}

	public function get_settings(){

		$form = $this->get_form();
		$notfications = $form['notifications'];

		$choices = array();

		foreach ( $notfications as $notfication ) {
			$choices[] = array(
				'label' => $notfication['name'],
				'name' => 'notification_id_' . $notfication['id'],
			);
		}

		return array(
			'title'  => 'Notification',
			'fields' => array(
				array(
					'name' => 'notification',
					'label' => esc_html__( 'Select Notifications', 'gravityflow' ),
					'type' => 'checkbox',
					'required' => true,
					'choices' => $choices,
				),
			),
		);
	}

	function process(){

		$entry = $this->get_entry();

		$form = $this->get_form();

		foreach ( $form['notifications'] as $notification ) {
			$notification_id = $notification['id'];
			$setting_key = 'notification_id_' . $notification_id;
			if ( $this->{$setting_key} ) {
				if ( ! GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $entry ) ) {
					$this->log_debug( __METHOD__ . "(): Notification conditional logic not met, not processing notification (#{$notification_id} - {$notification['name']})." );
					continue;
				}
				GFCommon::send_notification( $notification, $form, $entry );
				$note = sprintf( esc_html__( 'Sent Notification: %s' ), $notification['name'] );
				$this->add_note( $note );
			}
		}

		return true;
	}

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Notification() );