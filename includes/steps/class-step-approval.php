<?php
/**
 * Gravity Flow Step Approval
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Approval
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */


if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Approval extends Gravity_Flow_Step {

	public $_step_type = 'approval';


	/**
	 * The resource slug for the REST API.
	 *
	 * @var string
	 */
	protected $_rest_base = 'approvals';

	public function get_status_config() {
		return array(
			array(
				'status'                    => 'rejected',
				'status_label'              => __( 'Rejected', 'gravityflow' ),
				'destination_setting_label' => esc_html__( 'Next step if Rejected', 'gravityflow' ),
				'default_destination'       => 'complete',
			),
			array(
				'status'                    => 'approved',
				'status_label'              => __( 'Approved', 'gravityflow' ),
				'destination_setting_label' => __( 'Next Step if Approved', 'gravityflow' ),
				'default_destination'       => 'next',
			),
		);
	}

	/**
	 * Returns an array of quick actions to be displayed on the inbox.
	 *
	 * @return array
	 */
	public function get_actions() {
		return array(
			array(
				'key' => 'approve',
				'icon' => $this->get_approve_icon(),
				'label' => __( 'Approve', 'gravityflow' ),
			),
			array(
				'key' => 'reject',
				'icon' => $this->get_reject_icon(),
				'label' => __( 'Reject', 'gravityflow' ),
			),
		);
	}

	/**
	 * Process the REST request for an entry.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed If response generated an error, WP_Error, if response
	 *                                is already an instance, WP_HTTP_Response, otherwise
	 *                                returns a new WP_REST_Response instance.
	 */
	public function handle_rest_request( $request ) {
		if ( $request->get_method() !== 'POST' ) {
			return new WP_Error( 'invalid_request_method', __( 'Invalid request method' ) );
		}
		$action = $request['action'];
		$new_status = '';
		switch ( $action ) {
			case 'approve' :
				$new_status = 'approved';
				break;
			case 'reject' :
				$new_status = 'rejected';
		}

		if ( empty( $new_status ) ) {
			return new WP_Error( 'invalid_action', __( 'Action not supported', 'gravityflow' ) );
		}

		$assignee_key = isset( $request['assignee'] ) ? $request['assignee'] : gravity_flow()->get_current_user_assignee_key();

		$assignee = new Gravity_Flow_Assignee( $assignee_key, $this );

		$response = $this->process_assignee_status( $assignee, $new_status, $this->get_form() );


		if ( empty( $assignee ) ) {
			return new WP_Error( 'not_supported', __( 'Action not supported.', 'gravityflow' ) );
		}

		$response = rest_ensure_response( $response );

		return $response;
	}

	/**
	 * Check if a given request has permission.
	 *
	 * @since  1.4.3
	 * @access public
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|boolean
	 */
	public function rest_request_permissions_check( $request ) {
		if ( isset( $request['assignee'] ) && ! gravity_flow()->current_user_can_any( 'gravityflow_create_steps' ) ) {
			return new WP_Error( 'not_allowed', __( "You're not authorized to perform this action.", 'gravityflow' ), array( 'status' => 403 ) );
		}

		$assignee = isset( $request['assignee'] ) ? $request['assignee'] : gravity_flow()->get_current_user_assignee_key();

		if ( empty( $assignee ) ) {
			return new WP_Error( 'not_allowed', __( 'Missing assignee.', 'gravityflow' ), array( 'status' => 403 ) );
		}
	}

	public function supports_expiration() {
		return true;
	}

	public function get_label() {
		return esc_html__( 'Approval', 'gravityflow' );
	}

	public function get_icon_url() {
		return '<i class="fa fa-check" style="color:darkgreen;"></i>';
	}

	public function get_settings() {
		$account_choices = gravity_flow()->get_users_as_choices();

		$type_field_choices = array(
			array( 'label' => __( 'Select', 'gravityflow' ), 'value' => 'select' ),
			array( 'label' => __( 'Conditional Routing', 'gravityflow' ), 'value' => 'routing' ),
		);

		$assignee_notification_fields = array(
			array(
				'name'    => 'assignee_notification_enabled',
				'label'   => '',
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'label'         => __( 'Send Email to the assignee(s).', 'gravityflow' ),
						'tooltip'       => __( 'Enable this setting to send email to each of the assignees as soon as the entry has been assigned. If a role is configured to receive emails then all the users with that role will receive the email.', 'gravityflow' ),
						'name'          => 'assignee_notification_enabled',
						'default_value' => false,
					),
				),
			),
			array(
				'name'  => 'assignee_notification_from_name',
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'From Name', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'          => 'assignee_notification_from_email',
				'class'         => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label'         => __( 'From Email', 'gravityflow' ),
				'type'          => 'text',
				'default_value' => '{admin_email}',
			),
			array(
				'name'  => 'assignee_notification_reply_to',
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'Reply To', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'  => 'assignee_notification_bcc',
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'BCC', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'  => 'assignee_notification_subject',
				'class' => 'large fieldwidth-1 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'Subject', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'          => 'assignee_notification_message',
				'label'         => __( 'Message to Assignee(s)', 'gravityflow' ),
				'type'          => 'visual_editor',
				'default_value' => __( 'A new entry is pending your approval. Please check your Workflow Inbox.', 'gravityflow' ),
			),
			array(
				'name'    => 'assignee_notification_autoformat',
				'label'   => '',
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'label'         => __( 'Disable auto-formatting', 'gravityflow' ),
						'name'          => 'assignee_notification_disable_autoformat',
						'default_value' => false,
						'tooltip'       => __( 'Disable auto-formatting to prevent paragraph breaks being automatically inserted when using HTML to create the email message.', 'gravityflow' ),

					),
				),
			),
			array(
				'name'     => 'resend_assignee_email',
				'label'    => '',
				'type'     => 'checkbox_and_text',
				'checkbox' => array(
					'label' => __( 'Send reminder', 'gravityflow' ),
				),
				'text'     => array(
					'default_value' => 7,
					'before_input'  => __( 'Resend the assignee email after', 'gravityflow' ),
					'after_input'   => ' ' . __( 'day(s)', 'gravityflow' ),
				),
			),
		);

		$rejection_notification_fields = array(
			array(
				'name'    => 'rejection_notification_enabled',
				'label'   => '',
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'label'         => __( 'Send email when the entry is rejected', 'gravityflow' ),
						'tooltip'       => __( 'Enable this setting to send an email when the entry is rejected.', 'gravityflow' ),
						'name'          => 'rejection_notification_enabled',
						'default_value' => false,
					),
				),
			),
			array(
				'name'          => 'rejection_notification_type',
				'label'         => __( 'Send To', 'gravityflow' ),
				'type'          => 'radio',
				'default_value' => 'select',
				'horizontal'    => true,
				'choices'       => $type_field_choices,
			),
			array(
				'id'       => 'rejection_notification_users',
				'name'     => 'rejection_notification_users[]',
				'label'    => __( 'Select User', 'gravityflow' ),
				'size'     => '8',
				'multiple' => 'multiple',
				'type'     => 'select',
				'choices'  => $account_choices,
			),
			array(
				'name'  => 'rejection_notification_routing',
				'label' => __( 'Routing', 'gravityflow' ),
				'type'  => 'user_routing',
			),
			array(
				'name'  => 'rejection_notification_from_name',
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'From Name', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'          => 'rejection_notification_from_email',
				'label'         => __( 'From Email', 'gravityflow' ),
				'class'         => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'type'          => 'text',
				'default_value' => '{admin_email}',
			),
			array(
				'name'  => 'rejection_notification_reply_to',
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'Reply To', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'  => 'rejection_notification_bcc',
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'BCC', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'  => 'rejection_notification_subject',
				'class' => 'fieldwidth-1 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'Subject', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'          => 'rejection_notification_message',
				'label'         => __( 'Message', 'gravityflow' ),
				'type'          => 'visual_editor',
				'default_value' => __( 'Entry {entry_id} has been rejected', 'gravityflow' ),
			),
			array(
				'name'    => 'rejection_notification_autoformat',
				'label'   => '',
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'label'         => __( 'Disable auto-formatting', 'gravityflow' ),
						'name'          => 'rejection_notification_disable_autoformat',
						'default_value' => false,
						'tooltip'       => __( 'Disable auto-formatting to prevent paragraph breaks being automatically inserted when using HTML to create the email message.', 'gravityflow' ),

					),
				),
			),
		);

		$approval_notification_fields = array(
			array(
				'name'    => 'approval_notification_enabled',
				'label'   => '',
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'label'         => __( 'Send email when the entry is approved', 'gravityflow' ),
						'tooltip'       => __( 'Enable this setting to send an email when the entry is approved.', 'gravityflow' ),
						'name'          => 'approval_notification_enabled',
						'default_value' => false,
					),
				),
			),
			array(
				'name'          => 'approval_notification_type',
				'label'         => __( 'Send To', 'gravityflow' ),
				'type'          => 'radio',
				'default_value' => 'select',
				'horizontal'    => true,
				'choices'       => $type_field_choices,
			),
			array(
				'id'       => 'approval_notification_users',
				'name'     => 'approval_notification_users[]',
				'label'    => __( 'Select', 'gravityflow' ),
				'size'     => '8',
				'multiple' => 'multiple',
				'type'     => 'select',
				'choices'  => $account_choices,
			),
			array(
				'name'  => 'approval_notification_routing',
				'label' => __( 'Routing', 'gravityflow' ),
				'class' => 'large',
				'type'  => 'user_routing',
			),
			array(
				'name'  => 'approval_notification_from_name',
				'label' => __( 'From Name', 'gravityflow' ),
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'type'  => 'text',
			),
			array(
				'name'          => 'approval_notification_from_email',
				'label'         => __( 'From Email', 'gravityflow' ),
				'class'         => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'type'          => 'text',
				'default_value' => '{admin_email}',
			),
			array(
				'name'  => 'approval_notification_reply_to',
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'Reply To', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'  => 'approval_notification_bcc',
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'BCC', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'  => 'approval_notification_subject',
				'class' => 'fieldwidth-1 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'Subject', 'gravityflow' ),
				'type'  => 'text',

			),
			array(
				'name'          => 'approval_notification_message',
				'label'         => __( 'Approval Message', 'gravityflow' ),
				'type'          => 'visual_editor',
				'default_value' => __( 'Entry {entry_id} has been approved', 'gravityflow' ),
			),
			array(
				'name'    => 'approval_notification_autoformat',
				'label'   => '',
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'label'         => __( 'Disable auto-formatting', 'gravityflow' ),
						'name'          => 'approval_notification_disable_autoformat',
						'default_value' => false,
						'tooltip'       => __( 'Disable auto-formatting to prevent paragraph breaks being automatically inserted when using HTML to create the email message.', 'gravityflow' ),
					),
				),
			),
		);


		// Support for Gravity PDF 4
		if ( defined( 'PDF_EXTENDED_VERSION' ) && version_compare( PDF_EXTENDED_VERSION, '4.0-RC2', '>=' ) ) {

			$form_id    = $this->get_form_id();
			$gpdf_feeds = GPDFAPI::get_form_pdfs( $form_id );

			if ( ! is_wp_error( $gpdf_feeds ) ) {

				/* Format the PDFs in the appropriate format for use in a select field */
				$gpdf_choices = array();
				foreach ( $gpdf_feeds as $gpdf_feed ) {
					if ( true === $gpdf_feed['active'] ) {
						$gpdf_choices[] = array( 'label' => $gpdf_feed['name'], 'value' => $gpdf_feed['id'] );
					}
				}

				/* Create a select box for the Gravity PDFs if there are active PDFs */
				if ( 0 < sizeof( $gpdf_choices ) ) {
					$pdf_setting = array(
						'name'     => 'assignee_notification_gpdf',
						'label'    => '',
						'type'     => 'checkbox_and_select',
						'checkbox' => array(
							'label' => esc_html__( 'Attach PDF', 'gravityflow' ),
						),
						'select'   => array(
							'choices' => $gpdf_choices,
						),
					);

					/* Include PDF select box in assignee notification settings */
					$assignee_notification_fields[] = $pdf_setting;

					/* Include PDF select box in rejection notification settings */
					$pdf_setting['name']             = 'rejection_notification_gpdf';
					$rejection_notification_fields[] = $pdf_setting;

					/* Include PDF select box in aproval notification settings */
					$pdf_setting['name']            = 'approval_notification_gpdf';
					$approval_notification_fields[] = $pdf_setting;
				}
			}
		}

		$settings = array(
			'title'  => esc_html__( 'Approval', 'gravityflow' ),
			'fields' => array(
				array(
					'name'          => 'type',
					'label'         => __( 'Assign To:', 'gravityflow' ),
					'type'          => 'radio',
					'default_value' => 'select',
					'horizontal'    => true,
					'choices'       => $type_field_choices,
				),
				array(
					'id'       => 'assignees',
					'name'     => 'assignees[]',
					'tooltip'  => __( 'Users and roles fields will appear in this list. If the form contains any assignee fields they will also appear here. Click on an item to select it. The selected items will appear on the right. If you select a role then anybody from that role can approve.', 'gravityflow' ),
					'size'     => '8',
					'multiple' => 'multiple',
					'label'    => esc_html__( 'Select Assignees', 'gravityflow' ),
					'type'     => 'select',
					'choices'  => $account_choices,
				),
				array(
					'name'    => 'routing',
					'tooltip' => __( 'Build assignee routing rules by adding conditions. Users and roles fields will appear in the first drop-down field. If the form contains any assignee fields they will also appear here. Select the assignee and define the condition for that assignee. Add as many routing rules as you need.', 'gravityflow' ),
					'label'   => __( 'Routing', 'gravityflow' ),
					'type'    => 'routing',
				),
				array(
					'name'          => 'unanimous_approval',
					'label'         => __( 'Approval Policy', 'gravityflow' ),
					'tooltip'       => __( 'Define how approvals should be processed. If all assignees must approve then the entry will require unanimous approval before the step can be completed. If the step is assigned to a role only one user in that role needs to approve.', 'gravityflow' ),
					'type'          => 'radio',
					'default_value' => false,
					'choices'       => array(
						array(
							'label' => __( 'At least one assignee must approve', 'gravityflow' ),
							'value' => false,
						),
						array(
							'label' => __( 'All assignees must approve', 'gravityflow' ),
							'value' => true,
						),
					),
				),
				array(
					'name'     => 'instructions',
					'label'    => __( 'Instructions', 'gravityflow' ),
					'type'     => 'checkbox_and_textarea',
					'tooltip'  => esc_html__( 'Activate this setting to display instructions to the user for the current step.', 'gravityflow' ),
					'checkbox' => array(
						'label' => esc_html__( 'Display instructions', 'gravityflow' ),
					),
					'textarea' => array(
						'use_editor'    => true,
						'default_value' => esc_html__( 'Instructions: please review the values in the fields below and click on the Approve or Reject button', 'gravityflow' ),
					),
				),
				array(
					'name'    => 'display_fields',
					'label'   => __( 'Display Fields', 'gravityflow' ),
					'tooltip' => __( 'Select the fields to hide or display.', 'gravityflow' ),
					'type'    => 'display_fields',
				),
				array(
					'name'    => 'notification_tabs',
					'label'   => __( 'Emails', 'gravityflow' ),
					'tooltip' => __( 'Configure the emails that should be sent for this step.', 'gravityflow' ),
					'type'    => 'tabs',
					'tabs'    => array(
						array(
							'label'  => __( 'Assignee Email', 'gravityflow' ),
							'id'     => 'tab_assignee_notification',
							'fields' => $assignee_notification_fields,
						),
						array(
							'label'  => __( 'Rejection Email', 'gravityflow' ),
							'id'     => 'tab_rejection_notification',
							'fields' => $rejection_notification_fields,
						),
						array(
							'label'  => __( 'Approval Email', 'gravityflow' ),
							'id'     => 'tab_approval_notification',
							'fields' => $approval_notification_fields,
						),
					),
				),
			),
		);

		$user_input_step_choices = array();
		$revert_field            = array();
		$form_id                 = $this->get_form_id();
		$steps                   = gravity_flow()->get_steps( $form_id );
		foreach ( $steps as $step ) {
			if ( $step->get_type() === 'user_input' ) {
				$user_input_step_choices[] = array( 'label' => $step->get_name(), 'value' => $step->get_id() );
			}
		}

		if ( ! empty( $user_input_step_choices ) ) {
			$revert_field = array(
				'name'     => 'revert',
				'label'    => esc_html__( 'Revert to User Input step', 'gravityflow' ),
				'type'     => 'checkbox_and_select',
				'tooltip'  => esc_html__( 'The Revert setting enables a third option in addition to Approve and Reject which allows the assignee to send the entry directly to a User Input step without changing the status. Enable this setting to show the Revert button next to the Approve and Reject buttons and specify the User Input step the entry will be sent to.', 'gravityflow' ),
				'checkbox' => array(
					'label' => esc_html__( 'Enable', 'gravityflow' ),
				),
				'select'   => array(
					'choices' => $user_input_step_choices,
				),
			);
		}

		$note_mode_setting = array(
			'name'          => 'note_mode',
			'label'         => esc_html__( 'Workflow Note', 'gravityflow' ),
			'type'          => 'select',
			'tooltip'       => esc_html__( 'The text entered in the Note box will be added to the timeline. Use this setting to select the options for the Note box.', 'gravityflow' ),
			'default_value' => 'not_required',
			'choices'       => array(
				array( 'value' => 'hidden', 'label' => esc_html__( 'Hidden', 'gravityflow' ) ),
				array( 'value' => 'not_required', 'label' => esc_html__( 'Not required', 'gravityflow' ) ),
				array( 'value' => 'required', 'label' => esc_html__( 'Always required', 'gravityflow' ) ),
				array(
					'value' => 'required_if_approved',
					'label' => esc_html__( 'Required if approved', 'gravityflow' )
				),
				array(
					'value' => 'required_if_rejected',
					'label' => esc_html__( 'Required if rejected', 'gravityflow' )
				),
			),
		);

		if ( ! empty( $revert_field ) ) {
			$note_mode_setting['choices'][] = array(
				'value' => 'required_if_reverted',
				'label' => esc_html__( 'Required if reverted', 'gravityflow' )
			);
			$note_mode_setting['choices'][] = array(
				'value' => 'required_if_reverted_or_rejected',
				'label' => esc_html__( 'Required if reverted or rejected', 'gravityflow' )
			);
			$settings['fields'][]           = $revert_field;
		}

		$settings['fields'][] = $note_mode_setting;

		$form = gravity_flow()->get_current_form();
		if ( GFCommon::has_post_field( $form['fields'] ) ) {
			$settings['fields'][] = array(
				'name'    => 'post_action_on_rejection',
				'label'   => __( 'Post Action if Rejected:', 'gravityflow' ),
				'type'    => 'select',
				'choices' => array(
					array( 'label' => '' ),
					array( 'label' => __( 'Mark Post as Draft', 'gravityflow' ), 'value' => 'draft' ),
					array( 'label' => __( 'Trash Post', 'gravityflow' ), 'value' => 'trash' ),
					array( 'label' => __( 'Delete Post', 'gravityflow' ), 'value' => 'delete' ),

				),
			);

			$settings['fields'][] = array(
				'name'    => 'post_action_on_approval',
				'label'   => __( 'Post Action if Approved:', 'gravityflow' ),
				'type'    => 'checkbox',
				'choices' => array(
					array( 'label' => __( 'Publish Post', 'gravityflow' ), 'name' => 'publish_post_on_approval' ),

				),
			);
		}

		return $settings;
	}

	public function process() {
		return $this->assign();
	}

	public function is_complete() {
		$status = $this->evaluate_status();

		return ! in_array( $status, array( 'pending', 'queued' ) );
	}

	public function status_evaluation() {
		$approvers   = $this->get_assignees();
		$step_status = 'approved';

		foreach ( $approvers as $approver ) {

			$approver_status = $approver->get_status();

			if ( $approver_status == 'rejected' ) {
				$step_status = 'rejected';
				break;
			}
			if ( $this->type == 'select' && ! $this->unanimous_approval ) {
				if ( $approver_status == 'approved' ) {
					$step_status = 'approved';
					break;
				} else {
					$step_status = 'pending';
				}
			} else if ( empty( $approver_status ) || $approver_status == 'pending' ) {
				$step_status = 'pending';
			}
		}

		return $step_status;
	}

	public function is_valid_token( $token ) {
		$token_json  = base64_decode( $token );
		$token_array = json_decode( $token_json, true );

		if ( empty( $token_array ) ) {
			return false;
		}

		$timestamp  = $token_array['timestamp'];
		$user_id    = $token_array['user_id'];
		$new_status = $token_array['new_status'];
		$entry_id   = $token_array['entry_id'];
		$sig        = $token_array['sig'];


		$expiration_days = apply_filters( 'gravityflow_approval_token_expiration_days', 1 );

		$i = wp_nonce_tick();

		$is_valid = false;

		for ( $n = 1; $n <= $expiration_days; $n ++ ) {
			$sig_key          = sprintf( '%s|%s|%s|%s|%s|%s', $i, $this->get_id(), $timestamp, $entry_id, $user_id, $new_status );
			$verification_sig = substr( wp_hash( $sig_key ), - 12, 10 );
			if ( hash_equals( $verification_sig, $sig ) ) {
				$is_valid = true;
				break;
			}
			$i --;
		}

		return $is_valid;
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
		$feedback        = false;
		$step_status_key = 'gravityflow_approval_new_status_step_' . $this->get_id();

		if ( isset( $_REQUEST[ $step_status_key ] ) || isset( $_GET['gflow_token'] ) || $token = gravity_flow()->decode_access_token() ) {
			if ( isset( $_POST['_wpnonce'] ) && check_admin_referer( 'gravityflow_approvals_' . $this->get_id() ) ) {
				$new_status = rgpost( $step_status_key );
				$validation = $this->validate_status_update( $new_status, $form );
				if ( is_wp_error( $validation ) ) {
					return $validation;
				}

				$assignee_key = $this->get_current_assignee_key();
				$assignee     = $this->get_assignee( $assignee_key );
			} else {

				$gflow_token = rgget( 'gflow_token' );
				$new_status  = rgget( 'new_status' );

				if ( ! $gflow_token ) {
					return false;
				}

				if ( $gflow_token ) {
					$token_json  = base64_decode( $gflow_token );
					$token_array = json_decode( $token_json, true );

					if ( empty( $token_array ) ) {
						return false;
					}

					$new_status = $token_array['new_status'];
					if ( empty( $new_status ) ) {
						return false;
					}
				}

				$valid_token = $this->is_valid_token( $gflow_token );

				if ( ! ( $valid_token ) ) {
					return false;
				}

				$assignee = $this->get_assignee( 'user_id|' . get_current_user_id() );
			}

			$feedback = $this->process_assignee_status( $assignee, $new_status, $form );

			$entry = $this->refresh_entry();

			do_action( 'gravityflow_post_status_update_approval', $entry, $assignee, $new_status, $form );

			apply_filters( 'gravityflow_feedback_approval', $feedback, $entry, $assignee, $new_status, $form );

		}

		return $feedback;
	}

	/**
	 * @param Gravity_Flow_Assignee $assignee
	 * @param $new_status
	 * @param $form
	 *
	 * @return bool|string If processed return a message to be displayed to the user.
	 */
	public function process_assignee_status( $assignee, $new_status, $form ) {
		if ( ! in_array( $new_status, array( 'pending', 'approved', 'rejected', 'revert' ) ) ) {
			return false;
		}

		$current_user_status = $assignee->get_status();
		list( $role, $current_role_status ) = $this->get_current_role_status();

		if ( $current_user_status != 'pending' && $current_role_status != 'pending' ) {
			return esc_html__( 'The status could not be changed because this step has already been processed.', 'gravityflow' );
		}

		if ( $new_status == 'revert' ) {
			return $this->process_revert_status();
		}

		if ( $current_user_status == 'pending' ) {
			$assignee->update_status( $new_status );
		}

		if ( $current_role_status == 'pending' ) {
			$this->update_role_status( $role, $new_status );
		}

		$this->add_status_update_note( $new_status, $assignee );
		$status = $this->evaluate_status();
		$this->update_step_status( $status );
		$this->refresh_entry();

		return $this->get_status_update_feedback( $new_status );
	}

	/**
	 * If the revert settings are configured end the current step and start the specified step.
	 *
	 * @return bool|string
	 */
	public function process_revert_status() {
		$feedback = false;

		if ( $this->revertEnable ) {
			$step = gravity_flow()->get_step( $this->revertValue, $this->get_entry() );

			if ( $step ) {
				$this->end();
				$note      = $this->get_name() . ': ' . esc_html__( 'Reverted to step', 'gravityflow' ) . ' - ' . $step->get_label();
				$user_note = rgpost( 'gravityflow_note' );

				if ( ! empty( $user_note ) ) {
					$note .= sprintf( "\n%s: %s", __( 'Note', 'gravityflow' ), $user_note );
				}

				$this->add_note( $note );
				$step->start();
				$feedback = esc_html__( 'Reverted to step:', 'gravityflow' ) . ' ' . $step->get_label();
			}
		}

		return $feedback;
	}

	/**
	 * If applicable add a note to the current entry.
	 *
	 * @param string $new_status The new status for the step.
	 * @param Gravity_Flow_Assignee $assignee The step assignee.
	 */
	public function add_status_update_note( $new_status, $assignee ) {
		$note = '';

		if ( $new_status == 'approved' ) {
			$note = $this->get_name() . ': ' . __( 'Approved.', 'gravityflow' );
		} elseif ( $new_status == 'rejected' ) {
			$note = $this->get_name() . ': ' . __( 'Rejected.', 'gravityflow' );
		}

		if ( ! empty( $note ) ) {
			$user_note = rgpost( 'gravityflow_note' );
			if ( ! empty( $user_note ) ) {
				$note .= sprintf( "\n%s: %s", __( 'Note', 'gravityflow' ), $user_note );
			}
			$user_id = ( $assignee->get_type() == 'user_id' ) ? $assignee->get_id() : 0;
			$this->add_note( $note, $user_id, $assignee->get_display_name() );
		}
	}

	/**
	 * Get the feedback for this status update.
	 *
	 * @param string $new_status The new status for the step.
	 *
	 * @return bool|string
	 */
	public function get_status_update_feedback( $new_status ) {
		switch ( $new_status ) {
			case 'approved':
				return __( 'Entry Approved', 'gravityflow' );
			case 'rejected':
				return __( 'Entry Rejected', 'gravityflow' );
		}

		return false;
	}

	/**
	 * Determine if this step is valid.
	 *
	 * @param string $new_status The new status for the current step.
	 * @param array $form The form currently being processed.
	 *
	 * @return bool
	 */
	public function validate_status_update( $new_status, $form ) {
		$valid = $this->validate_note( $new_status, $form );

		return $this->get_validation_result( $valid, $form, $new_status );
	}

	/**
	 * Determine if the note is valid.
	 *
	 * @param string $new_status The new status for the current step.
	 * @param string $note The submitted note.
	 *
	 * @return bool
	 */
	public function validate_note_mode( $new_status, $note ) {
		switch ( $this->note_mode ) {
			case 'required' :
				return ! empty( $note );

			case 'required_if_approved' :
				if ( $new_status == 'approved' && empty( $note ) ) {
					return false;
				}
				break;

			case 'required_if_rejected' :
				if ( $new_status == 'rejected' && empty( $note ) ) {
					return false;
				}
				break;

			case 'required_if_reverted' :
				if ( $new_status == 'revert' && empty( $note ) ) {
					return false;
				}
				break;

			case 'required_if_reverted_or_rejected' :
				if ( ( $new_status == 'revert' || $new_status == 'rejected' ) && empty( $note ) ) {
					return false;
				}
		}

		return true;
	}

	/**
	 * Allow the validation result to be overridden using the gravityflow_validation_approval filter.
	 *
	 * @param array $validation_result The validation result and form currently being processed.
	 * @param string $new_status The new status for the current step.
	 *
	 * @return array
	 */
	public function maybe_filter_validation_result( $validation_result, $new_status ) {

		return apply_filters( 'gravityflow_validation_approval', $validation_result, $this );

	}

	public function workflow_detail_box( $form, $args ) {
		$status               = esc_html__( 'Pending Approval', 'gravityflow' );
		$approve_icon         = '<i class="fa fa-check" style="color:green"></i>';
		$reject_icon          = '<i class="fa fa-times" style="color:red"></i>';
		$approval_step_status = $this->get_status();
		if ( $approval_step_status == 'approved' ) {
			$status = $approve_icon . ' ' . esc_html__( 'Approved', 'gravityflow' );
		} elseif ( $approval_step_status == 'rejected' ) {
			$status = $reject_icon . ' ' . esc_html__( 'Rejected', 'gravityflow' );
		} elseif ( $approval_step_status == 'queued' ) {
			$status = esc_html__( 'Queued', 'gravityflow' );
		}
		$display_step_status = (bool) $args['step_status'];
		if ( $display_step_status ) : ?>
			<h4>
				<?php printf( '%s (%s)', $this->get_name(), $status ); ?>
			</h4>
			<div>
				<?php $this->workflow_detail_status_box_status() ?>
			</div>
		<?php endif; ?>
		<?php $this->workflow_detail_status_box_actions( $form ) ?>

		<?php

	}

	public function workflow_detail_status_box_status() {
		?>
		<ul>
			<?php
			$assignees = $this->get_assignees();
			foreach ( $assignees as $assignee ) {
				$user_approval_status = $assignee->get_status();
				$status_label         = $this->get_status_label( $user_approval_status );
				if ( ! empty( $user_approval_status ) ) {
					$assignee_type = $assignee->get_type();

					switch ( $assignee_type ) {
						case 'email' :
							$type_label   = esc_html__( 'Email', 'gravityflow' );
							$display_name = $assignee->get_id();
							break;
						case 'role' :
							$type_label   = esc_html__( 'Role', 'gravityflow' );
							$display_name = translate_user_role( $assignee->get_id() );
							break;
						case 'user_id' :
							$user         = get_user_by( 'id', $assignee->get_id() );
							$display_name = $user ? $user->display_name : $assignee->get_id() . ' ' . esc_html__( '(Missing)', 'gravityflow' );
							$type_label   = esc_html__( 'User', 'gravityflow' );
							break;
						default :
							$display_name = $assignee->get_id();
							$type_label   = $assignee->get_type();
					}
					$assignee_status_label = sprintf( '%s: %s (%s)', $type_label, $display_name, $status_label );

					$assignee_status_label = apply_filters( 'gravityflow_assignee_status_workflow_detail', $assignee_status_label, $assignee, $this );

					$assignee_status_li = sprintf( '<li>%s</li>', $assignee_status_label );

					echo $assignee_status_li;

				}
			}
			?>
		</ul>
		<?php
	}

	public function workflow_detail_status_box_actions( $form ) {
		$approve_icon = $this->get_approve_icon();
		$reject_icon  = $this->get_reject_icon();
		$revert_icon = $this->get_revert_icon();

		$user_approval_status = $this->get_user_status();

		$role_approval_status = false;
		foreach ( gravity_flow()->get_user_roles() as $role ) {
			$role_approval_status = $this->get_role_status( $role );
			if ( $role_approval_status == 'pending' ) {
				break;
			}
		}

		if ( $user_approval_status == 'pending' || $role_approval_status == 'pending' ) {
			wp_nonce_field( 'gravityflow_approvals_' . $this->get_id() );

			if ( $this->note_mode !== 'hidden' ) { ?>
				<br/>
				<div>
					<label for="gravityflow-note">
						<?php
						esc_html_e( 'Note', 'gravityflow' );
						$required_indicator = ( $this->note_mode == 'required' ) ? '*' : '';
						printf( "<span class='gfield_required'>%s</span>", $required_indicator );
						?>
					</label>
				</div>
				<textarea id="gravityflow-note" style="width:100%;" rows="4" class="wide" name="gravityflow_note"><?php
					echo rgar( $form, 'failed_validation' ) ? esc_textarea( rgpost( 'gravityflow_note' ) ) : '';
					?></textarea>
				<?php
				$invalid_note = ( isset( $form['workflow_note'] ) && is_array( $form['workflow_note'] ) && $form['workflow_note']['failed_validation'] );
				if ( $invalid_note ) {
					printf( "<div class='gfield_description validation_message'>%s</div>", $form['workflow_note']['validation_message'] );
				}
			}

			do_action( 'gravityflow_above_approval_buttons', $this, $form );
			?>
			<br/><br/>
			<div class="gravityflow-action-buttons">
				<button name="gravityflow_approval_new_status_step_<?php echo $this->get_id() ?>" value="approved"
				        type="submit"
				        class="button">
					<?php
					$approve_label = esc_html__( 'Approve', 'gravityflow' );

					/**
					 * Allows the 'Approve' label to be modified on the Approval step.
					 *
					 * @params string $approve_label The label to be modified.
					 * @params Gravity_Flow_Step $this The current step.
					 */
					$approve_label = apply_filters( 'gravityflow_approve_label_workflow_detail', $approve_label, $this );

					echo $approve_icon . ' ' . $approve_label; ?>
				</button>
				<button name="gravityflow_approval_new_status_step_<?php echo $this->get_id() ?>" value="rejected"
				        type="submit"
				        class="button">
					<?php
					$reject_label = esc_html__( 'Reject', 'gravityflow' );

					/**
					 * Allows the 'Reject' label to be modified on the Approval step.
					 *
					 * @params string $reject_label The label to be modified.
					 * @params Gravity_Flow_Step $this The current step.
					 */
					$reject_label = apply_filters( 'gravityflow_reject_label_workflow_detail', $reject_label, $this );

					echo $reject_icon . ' ' . $reject_label; ?>
				</button>
				<?php if ( $this->revertEnable ) : ?>
					<button name="gravityflow_approval_new_status_step_<?php echo $this->get_id() ?>" value="revert"
					        type="submit"
					        class="button">
						<?php
						$revert_label = esc_html__( 'Revert', 'gravityflow' );

						/**
						 * Allows the 'Revert' label to be modified on the Approval step.
						 *
						 * @params string $revert_label The label to be modified.
						 * @params Gravity_Flow_Step $this The current step.
						 */
						$revert_label = apply_filters( 'gravityflow_revert_label_workflow_detail', $revert_label, $this );

						echo $revert_icon . ' ' . $revert_label; ?>
					</button>
					<?php
				endif;
				?>
			</div>
			<?php
		}
	}

	public function entry_detail_status_box( $form ) {
		$status = $this->evaluate_status();
		?>

		<h4 style="padding:10px;"><?php echo $this->get_name() . ': ' . $status ?></h4>

		<div style="padding:10px;">
			<ul>
				<?php
				$assignees = $this->get_assignees();
				foreach ( $assignees as $assignee ) {
					$user_approval_status = $assignee->get_status();
					if ( ! empty( $user_approval_status ) ) {
						$assignee_type = $assignee->get_type();
						$assignee_id   = $assignee->get_id();
						if ( $assignee_type == 'email' ) {
							echo '<li>' . $assignee_id . ': ' . $user_approval_status . '</li>';
							continue;
						}
						if ( $assignee_type == 'role' ) {
							$users = get_users( array( 'role' => $assignee_id ) );
						} else {
							$users = get_users( array( 'include' => array( $assignee_id ) ) );
						}

						foreach ( $users as $user ) {
							echo '<li>' . $user->display_name . ': ' . $user_approval_status . '</li>';
						}
					}
				}

				?>
			</ul>
		</div>
		<?php

	}

	/**
	 * Send the applicable notification if it is enabled and has assignees.
	 *
	 * @param string $type The type of notification currently being processed; approval or rejection.
	 */
	public function maybe_send_notification( $type ) {
		if ( ! $this->{$type . '_notification_enabled'} ) {
			return;
		}

		$assignees = $this->get_notification_assignees( $type );

		if ( empty( $assignees ) ) {
			return;
		}

		$notification = $this->get_notification( $type );
		$this->send_notifications( $assignees, $notification );
	}

	public function send_approval_notification() {
		$this->maybe_send_notification( 'approval' );
	}

	public function send_rejection_notification() {
		$this->maybe_send_notification( 'rejection' );
	}

	/**
	 * @param $text
	 * @param Gravity_Flow_Assignee $assignee
	 *
	 * @return mixed
	 */
	public function replace_variables( $text, $assignee ) {
		$text    = parent::replace_variables( $text, $assignee );
		$comment = rgpost( 'gravityflow_note' );
		$text    = str_replace( '{workflow_note}', $comment, $text );

		$expiration_days = apply_filters( 'gravityflow_approval_token_expiration_days', 2, $assignee );

		$expiration_str = '+' . (int) $expiration_days . ' days';

		$expiration_timestamp = strtotime( $expiration_str );

		$scopes = array(
			'pages'           => array( 'inbox' ),
			'step_id'         => $this->get_id(),
			'entry_timestamp' => $this->get_entry_timestamp(),
			'entry_id'        => $this->get_entry_id(),
			'action'          => 'approve',
		);

		$approve_token = '';

		if ( $assignee ) {
			$approve_token = gravity_flow()->generate_access_token( $assignee, $scopes, $expiration_timestamp );

			$text = str_replace( '{workflow_approve_token}', $approve_token, $text );
		}

		preg_match_all( '/{workflow_approve_url(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );
		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {
				$full_tag       = $match[0];
				$options_string = isset( $match[2] ) ? $match[2] : '';
				$options        = shortcode_parse_atts( $options_string );

				$a = shortcode_atts(
					array(
						'page_id' => gravity_flow()->get_app_setting( 'inbox_page' ),
					), $options
				);

				$approve_url = $this->get_entry_url( $a['page_id'], $assignee, $approve_token );
				$approve_url = esc_url_raw( $approve_url );

				$text = str_replace( $full_tag, $approve_url, $text );
			}
		}

		preg_match_all( '/{workflow_approve_link(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );
		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {
				$full_tag       = $match[0];
				$options_string = isset( $match[2] ) ? $match[2] : '';
				$options        = shortcode_parse_atts( $options_string );

				$a = shortcode_atts(
					array(
						'page_id' => gravity_flow()->get_app_setting( 'inbox_page' ),
						'text'    => esc_html__( 'Approve', 'gravityflow' ),
					), $options
				);

				$approve_url  = $this->get_entry_url( $a['page_id'], $assignee, $approve_token );
				$approve_url  = esc_url_raw( $approve_url );
				$approve_link = sprintf( '<a href="%s">%s</a>', $approve_url, esc_html( $a['text'] ) );
				$text         = str_replace( $full_tag, $approve_link, $text );
			}
		}

		$scopes['action'] = 'reject';

		$reject_token = '';

		if ( $assignee ) {
			$reject_token = gravity_flow()->generate_access_token( $assignee, $scopes, $expiration_timestamp );
			$text         = str_replace( '{workflow_reject_token}', $reject_token, $text );
		}

		preg_match_all( '/{workflow_reject_url(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );
		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {
				$full_tag       = $match[0];
				$options_string = isset( $match[2] ) ? $match[2] : '';
				$options        = shortcode_parse_atts( $options_string );

				$a = shortcode_atts(
					array(
						'page_id' => gravity_flow()->get_app_setting( 'inbox_page' ),
					), $options
				);

				$reject_url = $this->get_entry_url( $a['page_id'], $assignee, $reject_token );
				$reject_url = esc_url_raw( $reject_url );
				$text       = str_replace( $full_tag, $reject_url, $text );
			}
		}

		preg_match_all( '/{workflow_reject_link(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );
		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {
				$full_tag       = $match[0];
				$options_string = isset( $match[2] ) ? $match[2] : '';
				$options        = shortcode_parse_atts( $options_string );

				$a = shortcode_atts(
					array(
						'page_id' => gravity_flow()->get_app_setting( 'inbox_page' ),
						'text'    => esc_html__( 'Reject', 'gravityflow' ),
					), $options
				);

				$reject_url  = $this->get_entry_url( $a['page_id'], $assignee, $reject_token );
				$reject_url  = esc_url_raw( $reject_url );
				$reject_link = sprintf( '<a href="%s">%s</a>', $reject_url, esc_html( $a['text'] ) );
				$text        = str_replace( $full_tag, $reject_link, $text );
			}
		}

		return $text;
	}

	/**
	 * Provides a way for a step to process a token action before anything else. If feedback is returned it is displayed and nothing else with be rendered.
	 *
	 * @param $action
	 * @param $token
	 * @param $form
	 * @param $entry
	 *
	 * @return bool|string|void|WP_Error
	 */
	public function maybe_process_token_action( $action, $token, $form, $entry ) {
		$feedback = parent::maybe_process_token_action( $action, $token, $form, $entry );

		if ( $feedback ) {
			return $feedback;
		}

		if ( ! in_array( $action, array( 'approve', 'reject' ) ) ) {
			return false;
		}

		$entry_id = rgars( $token, 'scopes/entry_id' );
		if ( empty( $entry_id ) || $entry_id != $entry['id'] ) {
			return new WP_Error( 'incorrect_entry_id', esc_html__( 'Error: incorrect entry.', 'gravityflow' ) );
		}

		$step_id = rgars( $token, 'scopes/step_id' );
		if ( empty( $step_id ) || $step_id != $this->get_id() ) {
			return new WP_Error( 'step_already_processed', esc_html__( 'Error: step already processed.', 'gravityflow' ) );
		}

		$assignee_key = sanitize_text_field( $token['sub'] );
		$assignee     = $this->get_assignee( $assignee_key );
		$new_status   = false;
		switch ( $token['scopes']['action'] ) {
			case 'approve' :
				$new_status = 'approved';
				break;
			case 'reject' :
				$new_status = 'rejected';
				break;
		}
		$feedback = $this->process_assignee_status( $assignee, $new_status, $form );

		return $feedback;
	}

	public function end() {
		$status = $this->evaluate_status();
		$entry  = $this->get_entry();
		if ( $status == 'approved' ) {
			$this->send_approval_notification();
			$this->maybe_perform_post_action( $entry, $this->publish_post_on_approval ? 'publish' : '' );
		} elseif ( $status == 'rejected' ) {
			$this->send_rejection_notification();
			$this->maybe_perform_post_action( $entry, $this->post_action_on_rejection );
		}
		if ( $status == 'approved' || $status == 'rejected' ) {
			GFAPI::send_notifications( $this->get_form(), $entry, 'workflow_approval' );
		}
		parent::end();
	}

	/**
	 * If a post exists for the entry perform the configured approval or rejection action.
	 *
	 * @param array $entry The current entry.
	 * @param string $action The action to perform.
	 */
	public function maybe_perform_post_action( $entry, $action ) {
		$post_id = rgar( $entry, 'post_id' );
		if ( $post_id && $action ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				$result = '';
				switch ( $action ) {
					case 'publish' :
					case 'draft' :
						$post->post_status = $action;
						$result            = wp_update_post( $post );
						break;

					case 'trash' :
						$result = wp_delete_post( $post_id );
						break;

					case 'delete' :
						$result = wp_delete_post( $post_id, true );
						break;
				}

				gravity_flow()->log_debug( __METHOD__ . "() - Post: {$post_id}. Action: {$action}. Result: " . var_export( (bool) $result, 1 ) );
			}
		}
	}

	public function get_approve_icon() {
		$approve_icon = '<i class="fa fa-check" style="color:green"></i>';
		return $approve_icon;
	}

	public function get_reject_icon() {
		$reject_icon  = '<i class="fa fa-times" style="color:red"></i>';
		return $reject_icon;
	}

	public function get_revert_icon() {
		$revert_icon  = '<i class="fa fa-undo" style="color:blue"></i>';
		return $revert_icon;
	}
}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Approval() );
