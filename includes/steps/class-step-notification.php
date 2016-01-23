<?php
/**
 * Gravity Flow Step Notification
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Notification
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Notification extends Gravity_Flow_Step {
	public $_step_type = 'notification';

	public function get_label() {
		return esc_html__( 'Notification', 'gravityflow' );
	}
	public function get_icon_url() {
		return '<i class="fa fa-envelope-o"></i>';
	}



	public function get_settings() {

		$form = $this->get_form();
		$notfications = $form['notifications'];

		$choices = array();

		foreach ( $notfications as $notfication ) {
			$choices[] = array(
				'label' => $notfication['name'],
				'name' => 'notification_id_' . $notfication['id'],
			);
		}

		$account_choices = gravity_flow()->get_users_as_choices();

		return array(
			'title'  => 'Notification',
			'fields' => array(
				array(
					'name' => 'notification',
					'label' => esc_html__( 'Gravity Forms Notifications', 'gravityflow' ),
					'type' => 'checkbox',
					'required' => false,
					'choices' => $choices,
				),
				array(
					'name'    => 'workflow_notification_enabled',
					'label'   => __( 'Workflow notification', 'gravityflow' ),
					'tooltip'   => __( 'Enable this setting to send an email.', 'gravityflow' ),
					'type'    => 'checkbox',
					'choices' => array(
						array(
							'label'         => __( 'Enabled', 'gravityflow' ),
							'name'          => 'workflow_notification_enabled',
							'default_value' => false,
						),
					),
				),
				array(
					'name'    => 'workflow_notification_type',
					'label'   => __( 'Send To', 'gravityflow' ),
					'type'       => 'radio',
					'default_value' => 'select',
					'horizontal' => true,
					'choices'    => array(
						array( 'label' => __( 'Select', 'gravityflow' ), 'value' => 'select' ),
						array( 'label' => __( 'Configure Routing', 'gravityflow' ), 'value' => 'routing' ),
					),
				),
				array(
					'id'       => 'workflow_notification_users',
					'name'    => 'workflow_notification_users[]',
					'label'   => __( 'Select User', 'gravityflow' ),
					'size'     => '8',
					'multiple' => 'multiple',
					'type'     => 'select',
					'choices'  => $account_choices,
				),
				array(
					'name'  => 'workflow_notification_routing',
					'label' => __( 'Routing', 'gravityflow' ),
					'type'  => 'user_routing',
				),
				array(
					'name'  => 'workflow_notification_from_name',
					'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
					'label' => __( 'From Name', 'gravityflow' ),
					'type'  => 'text',
				),
				array(
					'name'  => 'workflow_notification_from_email',
					'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
					'label' => __( 'From Email', 'gravityflow' ),
					'type'  => 'text',
					'default_value' => '{admin_email}',
				),
				array(
					'name'  => 'workflow_notification_reply_to',
					'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
					'label' => __( 'Reply To', 'gravityflow' ),
					'type'  => 'text',
				),
				array(
					'name'  => 'workflow_notification_bcc',
					'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
					'label' => __( 'BCC', 'gravityflow' ),
					'type'  => 'text',
				),
				array(
					'name'  => 'workflow_notification_subject',
					'class' => 'fieldwidth-1 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
					'label' => __( 'Subject', 'gravityflow' ),
					'type'  => 'text',
				),
				array(
					'name'  => 'workflow_notification_message',
					'label' => __( 'Message', 'gravityflow' ),
					'type'  => 'visual_editor',
				),
			),
		);
	}

	function process() {
		$this->log_debug( __METHOD__ . '(): starting' );

		if ( class_exists( 'GFPDF_Core' ) ) {
			global $gfpdf;
			if ( empty( $gfpdf ) ) {
				$gfpdf = new GFPDF_Core();
			}
		}

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
				$this->log_debug( __METHOD__ . "(): Notification sent (#{$notification_id} - {$notification['name']})." );

				$note = sprintf( esc_html__( 'Sent Notification: %s', 'gravityflow' ), $notification['name'] );
				$this->add_note( $note );
			}
		}

		$this->send_workflow_notification();

		return true;
	}

	public function send_workflow_notification() {

		if ( ! $this->workflow_notification_enabled ) {
			return;
		}

		$assignees = array();

		$notification_type = $this->workflow_notification_type;

		switch ( $notification_type ) {
			case 'select' :
				if ( is_array( $this->workflow_notification_users ) ) {
					foreach ( $this->workflow_notification_users as $assignee_key ) {
						$assignees[] = new Gravity_Flow_Assignee( $assignee_key, $this );
					}
				}
				break;
			case 'routing' :
				$routings = $this->workflow_notification_routing;
				if ( is_array( $routings ) ) {
					foreach ( $routings as $routing ) {
						if ( $user_is_assignee = $this->evaluate_routing_rule( $routing ) ) {
							$assignees[] = new Gravity_Flow_Assignee( rgar( $routing, 'assignee' ), $this );
						}
					}
				}

				break;
		}

		if ( empty( $assignees ) ) {
			return;
		}

		$notification['workflow_notification_type'] = 'workflow';
		$notification['fromName'] = $this->workflow_notification_from_name;
		$notification['from'] = $this->workflow_notification_from_email;
		$notification['replyTo'] = $this->workflow_notification_reply_to;
		$notification['bcc'] = $this->workflow_notification_bcc;
		$notification['subject'] = $this->workflow_notification_subject;
		$notification['message'] = $this->workflow_notification_message;

		$this->send_notifications( $assignees, $notification );

		$note = esc_html__( 'Sent Notification: ', 'gravityflow' ) . $this->get_name();
		$this->add_note( $note );

	}
}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Notification() );
