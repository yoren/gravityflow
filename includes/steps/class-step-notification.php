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

		$form         = $this->get_form();
		$notfications = $form['notifications'];

		$choices = array();

		foreach ( $notfications as $notfication ) {
			$choices[] = array(
				'label' => $notfication['name'],
				'name'  => 'notification_id_' . $notfication['id'],
			);
		}

		$account_choices = gravity_flow()->get_users_as_choices();

		return array(
			'title'  => 'Notification',
			'fields' => array(
				array(
					'name'     => 'notification',
					'label'    => esc_html__( 'Gravity Forms Notifications', 'gravityflow' ),
					'type'     => 'checkbox',
					'required' => false,
					'choices'  => $choices,
				),
				array(
					'name'    => 'workflow_notification_enabled',
					'label'   => __( 'Workflow notification', 'gravityflow' ),
					'tooltip' => __( 'Enable this setting to send an email.', 'gravityflow' ),
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
					'name'          => 'workflow_notification_type',
					'label'         => __( 'Send To', 'gravityflow' ),
					'type'          => 'radio',
					'default_value' => 'select',
					'horizontal'    => true,
					'choices'       => array(
						array( 'label' => __( 'Select', 'gravityflow' ), 'value' => 'select' ),
						array( 'label' => __( 'Configure Routing', 'gravityflow' ), 'value' => 'routing' ),
					),
				),
				array(
					'id'       => 'workflow_notification_users',
					'name'     => 'workflow_notification_users[]',
					'label'    => __( 'Select User', 'gravityflow' ),
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
					'name'          => 'workflow_notification_from_email',
					'class'         => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
					'label'         => __( 'From Email', 'gravityflow' ),
					'type'          => 'text',
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
				array(
					'name'    => 'workflow_notification_autoformat',
					'label'   => '',
					'type'    => 'checkbox',
					'choices' => array(
						array(
							'label'         => __( 'Disable auto-formatting', 'gravityflow' ),
							'name'          => 'workflow_notification_disable_autoformat',
							'default_value' => false,
							'tooltip'       => __( 'Disable auto-formatting to prevent paragraph breaks being automatically inserted when using HTML to create the email message.', 'gravityflow' ),

						),
					),
				),
			),
		);
	}

	function process() {
		$this->log_debug( __METHOD__ . '(): starting' );

		/* Ensure compatibility with Gravity PDF 3.x */
		if ( defined( 'PDF_EXTENDED_VERSION' ) && version_compare( PDF_EXTENDED_VERSION, '4.0-beta1', '<' ) && class_exists( 'GFPDF_Core' ) ) {
			global $gfpdf;
			if ( empty( $gfpdf ) ) {
				$gfpdf = new GFPDF_Core();
			}
		}

		$entry = $this->get_entry();

		$form = $this->get_form();

		foreach ( $form['notifications'] as $notification ) {
			$notification_id = $notification['id'];
			$setting_key     = 'notification_id_' . $notification_id;
			if ( $this->{$setting_key} ) {
				if ( ! GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $entry ) ) {
					$this->log_debug( __METHOD__ . "(): Notification conditional logic not met, not processing notification (#{$notification_id} - {$notification['name']})." );
					continue;
				}
				GFCommon::send_notification( $notification, $form, $entry );
				$this->log_debug( __METHOD__ . "(): Notification sent (#{$notification_id} - {$notification['name']})." );

				$note = sprintf( esc_html__( 'Sent Notification: %s', 'gravityflow' ), $notification['name'] );
				$this->add_note( $note, 0, $this->get_type() );
			}
		}

		$this->send_workflow_notification();

		return true;
	}

	public function send_workflow_notification() {

		if ( ! $this->workflow_notification_enabled ) {
			return;
		}

		$type      = 'workflow';
		$assignees = $this->get_notification_assignees( $type );

		if ( empty( $assignees ) ) {
			return;
		}

		$notification = $this->get_notification( $type );
		$this->send_notifications( $assignees, $notification );

		$note = esc_html__( 'Sent Notification: ', 'gravityflow' ) . $this->get_name();
		$this->add_note( $note, 0, $this->get_type() );

	}

	/**
	 * Replace the workflow_note merge tag and the tags in the base step class.
	 *
	 * @param string $text The text with merge tags.
	 * @param Gravity_Flow_Assignee $assignee
	 *
	 * @return mixed
	 */
	public function replace_variables( $text, $assignee ) {
		$text    = parent::replace_variables( $text, $assignee );
		$comment = rgpost( 'gravityflow_note' );
		$text    = str_replace( '{workflow_note}', $comment, $text );

		return $text;
	}

	/**
	 * Prevent the notifications assigned to the current step from being sent during form submission.
	 */
	public function intercept_submission() {
		$form_id = $this->get_form_id();
		add_filter( "gform_disable_notification_{$form_id}", array( $this, 'maybe_disable_notification' ), 10, 2 );
	}

	/**
	 * Prevents the current notification from being sent during form submission if it is selected for this step.
	 *
	 * @param bool $is_disabled Indicates if the current notification has already been disabled.
	 * @param array $notification The current notifications properties.
	 *
	 * @return bool
	 */
	public function maybe_disable_notification( $is_disabled, $notification ) {
		$setting_key = 'notification_id_' . $notification['id'];

		return $this->{$setting_key} ? true : $is_disabled;
	}
}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Notification() );
