<?php
/**
 * Gravity Flow Step User Input
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_User_Input
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_User_Input extends Gravity_Flow_Step{

	public $_step_type = 'user_input';

	public function get_label() {
		return esc_html__( 'User Input', 'gravityflow' );
	}

	public function supports_expiration() {
		return true;
	}

	public function get_icon_url() {
		return '<i class="fa fa-pencil" ></i>';
	}

	public function get_settings() {

		$account_choices = gravity_flow()->get_users_as_choices();

		$type_field_choices = array(
			array( 'label' => __( 'Select', 'gravityflow' ), 'value' => 'select' ),
			array( 'label' => __( 'Conditional Routing', 'gravityflow' ), 'value' => 'routing' ),
		);

		$form = $this->get_form();

		$settings = array(
			'title'  => esc_html__( 'User Input', 'gravityflow' ),
			'fields' => array(
				array(
					'name'       => 'type',
					'label'      => __( 'Assign To:', 'gravityflow' ),
					'type'       => 'radio',
					'default_value' => 'select',
					'horizontal' => true,
					'choices'    => $type_field_choices,
				),
				array(
					'id'       => 'assignees',
					'name'     => 'assignees[]',
					'multiple' => 'multiple',
					'label'    => 'Select Assignees',
					'type'     => 'select',
					'choices'  => $account_choices,
				),
				array(
					'id' => 'editable_fields',
					'name'  => 'editable_fields[]',
					'label' => __( 'Editable fields', 'gravityflow' ),
					'multiple' => 'multiple',
					'type'  => 'editable_fields',
				),
				array(
					'name'  => 'routing',
					'label' => 'Assignee Routing',
					'type'  => 'routing',
				),
			),
		);

		if ( $this->fields_have_conditional_logic( $form ) ) {
			$display_page_load_logic_setting = apply_filters( 'gravityflow_page_load_logic_setting',  false );
			if ( $display_page_load_logic_setting && GFCommon::has_pages( $form ) && $this->pages_have_conditional_logic( $form ) ) {
				$settings['fields'][] = array(
					'name'     => 'conditional_logic_editable_fields_enabled',
					'label'    => 'Conditional Logic',
					'type'     => 'checkbox_and_select',
					'checkbox' => array(
						'label'          => esc_html__( 'Enable field conditional logic', 'gravityflow' ),
						'name'           => 'conditional_logic_editable_fields_enabled',
						'defeault_value' => '0',
					),
					'select'   => array(
						'name' => 'conditional_logic_editable_fields_mode',
						'choices' => array(
							array(
								'value' => 'dynamic',
								'label' => esc_html__( 'Dynamic', 'gravityflow' ),
							),
							array(
								'value' => 'page_load',
								'label' => esc_html__( 'Only when the page loads', 'gravityflow' ),
							),
						),
						'tooltip' => esc_html__( 'Fields and Sections support dynamic conditional logic. Pages do not support dynamic conditional logic so they will only be shown or hidden when the page loads.' , 'gravityflow' ),
					),
				);
			} else {
				$settings['fields'][] = array(
					'name'    => 'conditional_logic_editable_fields_enabled',
					'label'   => 'Conditional Logic',
					'type'    => 'checkbox',
					'choices' => array(
						array(
							'label'          => esc_html__( 'Enable field conditional logic' ),
							'name'           => 'conditional_logic_editable_fields_enabled',
							'defeault_value' => '0',
						),
					),
				);
			}
		}

		$settings2 = array(
			array(
				'name'     => 'highlight_editable_fields',
				'label'    => 'Highlight Editable Fields',
				'type'     => 'checkbox_and_select',
				'checkbox' => array(
					'label'          => esc_html__( 'Enable' ),
					'name'           => 'highlight_editable_fields_enabled',
					'defeault_value' => '0',
				),
				'select'   => array(
					'name' => 'highlight_editable_fields_class',
					'choices' => array(
						array(
							'value' => 'green-triangle',
							'label' => esc_html__( 'Green triangle', 'gravityflow' ),
						),
						array(
							'value' => 'green-background',
							'label' => esc_html__( 'Green Background', 'gravityflow' ),
						),
					),
					'tooltip' => esc_html__( 'Fields and Sections support dynamic conditional logic. Pages do not support dynamic conditional logic so they will only be shown or hidden when the page loads.' , 'gravityflow' ),
				),
			),
			array(
				'id'            => 'assignee_policy',
				'name'          => 'assignee_policy',
				'label'         => __( 'Assignee Policy', 'gravityflow' ),
				'tooltip'       => __( 'Define how this step should be processed. If all assignees must complete this step then the entry will require input from every assignee before the step can be completed. If the step is assigned to a role only one user in that role needs to complete the step.', 'gravityflow' ),
				'type'          => 'radio',
				'default_value' => 'all',
				'choices'       => array(
					array(
						'label' => __( 'At least one assignee must complete this step', 'gravityflow' ),
						'value' => 'any',
					),
					array(
						'label' => __( 'All assignees must complete this step', 'gravityflow' ),
						'value' => 'all',
					),
				),
			),
			array(
				'name'  => 'instructions',
				'label' => __( 'Instructions', 'gravityflow' ),
				'type'  => 'checkbox_and_textarea',
				'tooltip' => esc_html__( 'Activate this setting to display instructions to the user for the current step.', 'gravityflow' ),
				'checkbox' => array(
					'label' => esc_html__( 'Display instructions', 'gravityflow' ),
				),
				'textarea'  => array(
					'use_editor' => true,
				),
			),
			array(
				'name'    => 'display_fields',
				'label'   => __( 'Display Fields', 'gravityflow' ),
				'tooltip' => __( 'Select the fields to hide or display.', 'gravityflow' ),
				'type'    => 'display_fields',
			),
			array(
				'name'          => 'default_status',
				'type'          => 'radio',
				'label'         => __( 'Default Status Option', 'gravityflow' ),
				'tooltip'       => __( 'Select the default value for the status on the workflow detail page. Select Hidden to hide the status options.', 'gravityflow' ),
				'default_value' => 'hidden',
				'horizontal'    => true,
				'choices'       => array(
					array( 'label' => __( 'Hidden', 'gravityflow' ), 'value' => 'hidden' ),
					array( 'label' => __( 'In progress', 'gravityflow' ), 'value' => 'in_progress' ),
					array( 'label' => __( 'Complete', 'gravityflow' ), 'value' => 'complete' ),
				),
			),
			array(
				'name'          => 'note_mode',
				'label'         => esc_html__( 'Workflow Note', 'gravityflow' ),
				'type'          => 'select',
				'tooltip'       => esc_html__( 'The text entered in the Note box will be added to the timeline. Use this setting to select the options for the Note box.', 'gravityflow' ),
				'default_value' => 'not_required',
				'choices'       => array(
					array( 'value' => 'hidden', 'label' => esc_html__( 'Hidden', 'gravityflow' ) ),
					array( 'value' => 'not_required', 'label' => esc_html__( 'Not required', 'gravityflow' ) ),
					array( 'value' => 'required', 'label' => esc_html__( 'Always Required', 'gravityflow' ) ),
					array(
						'value' => 'required_if_in_progress',
						'label' => esc_html__( 'Required if in progress', 'gravityflow' ),
					),
					array(
						'value' => 'required_if_complete',
						'label' => esc_html__( 'Required if complete', 'gravityflow' ),
					),
				),
			),
			array(
				'name'    => 'assignee_notification_enabled',
				'label'   => 'Email',
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'label'         => __( 'Enabled' ),
						'name'          => 'assignee_notification_enabled',
						'default_value' => 1,
					),
				),
			),
			array(
				'name'  => 'assignee_notification_from_name',
				'label' => __( 'From Name', 'gravityflow' ),
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'type'  => 'text',
			),
			array(
				'name'          => 'assignee_notification_from_email',
				'label'         => __( 'From Email', 'gravityflow' ),
				'type'          => 'text',
				'class'         => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
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
				'class' => 'fieldwidth-1 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'Subject', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'          => 'assignee_notification_message',
				'label'         => 'Message',
				'type'          => 'visual_editor',
				'default_value' => esc_html__( 'A new entry requires your input', 'gravityflow' ),
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
				'name'  => 'resend_assignee_email',
				'label' => __( 'Send reminder', 'gravityflow' ),
				'type'  => 'checkbox_and_text',
				'text'  => array(
					'default_value' => 7,
					'before_input'  => __( 'Resend the assignee email after', 'gravityflow' ),
					'after_input'   => ' ' . __( 'day(s)', 'gravityflow' ),
				),
			),

		);

		$settings['fields'] = array_merge( $settings['fields'], $settings2 );

		return $settings;
	}

	public function fields_have_conditional_logic( $form ) {
		return gravity_flow()->fields_have_conditional_logic( $form );
	}

	public function pages_have_conditional_logic( $form ) {
		return gravity_flow()->pages_have_conditional_logic( $form );
	}

	public function process() {
		return $this->assign();
	}

	/**
	 *
	 * @return Gravity_Flow_Assignee[]
	 */
	public function get_assignees() {

		$assignees = array();

		$assignee_details = array();

		$input_type = $this->type;

		switch ( $input_type ) {
			case 'select':
				foreach ( $this->assignees as $assignee_key ) {
					list( $assignee_type, $assignee_id ) = explode( '|', $assignee_key );
					$assignee_details[] = new Gravity_Flow_Assignee( array(
						'id'              => $assignee_id,
						'type'            => $assignee_type,
						'editable_fields' => $this->editable_fields,
					), $this );
				}
				break;
			case 'routing' :
				$routings = $this->routing;
				if ( is_array( $routings ) ) {
					$entry = $this->get_entry();
					foreach ( $routings as $routing ) {
						$assignee_key = rgar( $routing, 'assignee' );
						if ( in_array( $assignee_key, $assignees ) ) {
							continue;
						}
						list( $assignee_type, $assignee_id ) = explode( '|', $assignee_key );
						$editable_fields = rgar( $routing, 'editable_fields' );
						if ( $entry ) {
							if ( $this->evaluate_routing_rule( $routing ) ) {
								$assignee_details[] = new Gravity_Flow_Assignee( array(
									'id'              => $assignee_id,
									'type'            => $assignee_type,
									'editable_fields' => $editable_fields,
								), $this );
								$assignees[] = $assignee_key;
							}
						} else {
							$assignee_details[] = new Gravity_Flow_Assignee( array(
								'id'              => $assignee_id,
								'type'            => $assignee_type,
								'editable_fields' => $editable_fields,
							), $this );
							$assignees[] = $assignee_key;
						}
					}
				}

				break;
		}

		gravity_flow()->log_debug( __METHOD__ . '(): assignees: ' . print_r( $assignees, true ) );

		return $assignee_details;
	}

	public function get_next_step_id() {
		if ( isset( $this->_next_step_id ) ) {
			return $this->_next_step_id;
		}
		$this->_next_step_id = $this->is_complete() ? $this->destination_complete : $this->get_id();
		return $this->_next_step_id;
	}

	public function evaluate_status() {

		if ( $this->is_queued() ) {
			return 'queued';
		}

		if ( $this->is_expired() ) {
			return $this->get_expiration_status_key();
		}

		$assignee_details = $this->get_assignees();

		$step_status = 'complete';

		foreach ( $assignee_details as $assignee ) {
			$user_status = $assignee->get_status();

			if ( $this->type == 'select' && $this->assignee_policy == 'any' ) {
				if ( $user_status == 'complete' ) {
					$step_status = 'complete';
					break;
				} else {
					$step_status = 'pending';
				}
			} else if ( empty( $user_status ) || $user_status == 'pending' ) {
				$step_status = 'pending';
			}
		}

		return $step_status;
	}

	public function fields_empty( $entry, $editable_fields ) {

		foreach ( $editable_fields as $editable_field ) {
			if ( isset( $entry[ $editable_field ] ) && ! empty( $entry[ $editable_field ] ) ) {
				return false;
			}
		}

		return true;
	}

	public function get_editable_fields() {
		if ( $token = gravity_flow()->decode_access_token() ) {
			$current_user_key = sanitize_text_field( $token['sub'] );
		} else {
			global $current_user;
			$current_user_key = 'user_id|' . $current_user->ID;
		}

		$editable_fields = array();
		$assignee_details = $this->get_assignees();

		foreach ( $assignee_details as $assignee ) {

			$assignee_key = $assignee->get_key();
			$assignee_type = $assignee->get_type();

			$match = false;
			if ( $assignee_type == 'role' && gravity_flow()->check_user_role( $assignee->get_id() ) ) {
				$match = true;
			} elseif ( $assignee_key == $current_user_key ) {
				$match = true;
			}
			if ( $match && is_array( $assignee->get_editable_fields() ) ) {
				$assignee_editable_fields = $assignee->get_editable_fields();
				$editable_fields          = array_merge( $editable_fields, $assignee_editable_fields );
			}
		}

		$editable_fields = apply_filters( 'gravityflow_editable_fields_user_input', $editable_fields, $this );

		return $editable_fields;
	}


	public function maybe_process_status_update( $form, $entry ) {

		$feedback = false;

		$form_id = $form['id'];

		if ( isset( $_POST['gforms_save_entry'] ) && check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' ) ) {

			$new_status = rgpost( 'gravityflow_status' );

			if ( ! in_array( $new_status, array( 'in_progress', 'complete' ) ) ) {
				return false;
			}

			// Loading files that have been uploaded to temp folder
			$files = GFCommon::json_decode( stripslashes( RGForms::post( 'gform_uploaded_files' ) ) );
			if ( ! is_array( $files ) ) {
				$files = array();
			}

			GFFormsModel::$uploaded_files[ $form_id ] = $files;

			$validation = $this->validate_status_update( $new_status, $form );
			if ( is_wp_error( $validation )  ) {
				return $validation;
			}

			$editable_fields = $this->get_editable_fields();

			$previous_assignees = $this->get_assignees();

			$this->save_entry( $form, $entry, $editable_fields );

			remove_action( 'gform_after_update_entry', array( gravity_flow(), 'filter_after_update_entry' ) );

			do_action( 'gform_after_update_entry', $form, $entry['id'] );
			do_action( "gform_after_update_entry_{$form['id']}", $form, $entry['id'] );

			$entry = GFFormsModel::get_lead( $entry['id'] );
			GFFormsModel::set_entry_meta( $entry, $form );

			$this->refresh_entry();

			GFCache::flush();

			$this->maybe_adjust_assignment( $previous_assignees );

			if ( $token = gravity_flow()->decode_access_token() ) {
				$assignee_key = sanitize_text_field( $token['sub'] );
			} else {
				$user = wp_get_current_user();
				$assignee_key = 'user_id|' . $user->ID;
			}

			$assignee = new Gravity_Flow_Assignee( $assignee_key, $this );

			$feedback = $this->process_assignee_status( $assignee, $new_status, $form );

		}
		return $feedback;
	}

	/**
	 * @param Gravity_Flow_Assignee $assignee
	 * @param string $new_status
	 * @param array $form
	 *
	 * @return string|bool If processed return a message to be displayed to the user.
	 */
	public function process_assignee_status( $assignee, $new_status, $form ) {
		if ( $new_status == 'complete' ) {
			$current_user_status = $assignee->get_status();

			$current_role_status = false;
			$role = false;
			foreach ( gravity_flow()->get_user_roles() as $role ) {
				$current_role_status = $this->get_role_status( $role );
				if ( $current_role_status == 'pending' ) {
					break;
				}
			}
			if ( $current_user_status == 'pending' ) {
				$assignee->update_status( 'complete' );
			}

			if ( $current_role_status == 'pending' ) {
				$this->update_role_status( $role, 'complete' );
			}
			$this->refresh_entry();
		}

		$feedback = $new_status == 'complete' ?  __( 'Entry updated and marked complete.', 'gravityflow' ) : __( 'Entry updated - in progress.', 'gravityflow' );

		/**
		 * Allow the feedback message to be modified on the user input step.
		 *
		 * @param string $feedback
		 * @param string $new_status
		 * @param Gravity_Flow_Assignee $assignee
		 * @param array $form
		 * @param Gravity_Flow_Step $this
		 */
		$feedback = apply_filters( 'gravityflow_feedback_message_user_input', $feedback, $new_status, $assignee, $form, $this );

		$note = sprintf( '%s: %s', $this->get_name(), $feedback );

		$user_note = rgpost( 'gravityflow_note' );
		if ( ! empty( $user_note ) ) {
			$note .= sprintf( "\n%s: %s", esc_html__( 'Note', 'gravityflow' ), $user_note );
		}

		$this->add_note( $note );

		$status = $this->evaluate_status();
		$this->update_step_status( $status );
		$entry = $this->refresh_entry();

		GFAPI::send_notifications( $form, $entry, 'workflow_user_input' );

		return $feedback;
	}

	public function validate_status_update( $new_status, $form ) {

		$note = rgpost( 'gravityflow_note' );
		$valid = true;
		switch ( $this->note_mode ) {
			case 'required' :
				$valid = ! empty( $note );
				break;
			case 'required_if_in_progress' :
				if ( $new_status == 'in_progress' && empty( $note ) ) {
					$valid = false;
				};
				break;
			case 'required_if_complete' :
				if ( $new_status == 'complete' && empty( $note ) ) {
					$valid = false;
				};
		}

		if ( ! $valid ) {
			$form['workflow_note'] = array( 'failed_validation' => true, 'validation_message' => esc_html__( 'A note is required' ) );
		}

		$editable_fields = $this->get_editable_fields();

		$conditional_logic_enabled = gravity_flow()->fields_have_conditional_logic( $form ) && $this->conditional_logic_editable_fields_enabled;
		$page_load_conditional_logic_enabled = gravity_flow()->fields_have_conditional_logic( $form ) && $this->conditional_logic_editable_fields_enabled && $this->conditional_logic_editable_fields_mode == 'page_load';
		$dynamic_conditional_logic_enabled = gravity_flow()->fields_have_conditional_logic( $form ) && $this->conditional_logic_editable_fields_enabled && $this->conditional_logic_editable_fields_mode != 'page_load';

		$saved_entry = $this->get_entry();

		if ( ! $conditional_logic_enabled || $page_load_conditional_logic_enabled ) {
			$entry = $saved_entry;
		} else {
			$entry = GFFormsModel::create_lead( $form );
		}

		foreach ( $form['fields'] as $field ) {
			/* @var GF_Field $field */
			if ( in_array( $field->id, $editable_fields ) ) {
				if ( ( $dynamic_conditional_logic_enabled && GFFormsModel::is_field_hidden( $form, $field, array() ) ) ) {
					continue;
				}
				$value = GFFormsModel::get_field_value( $field );

				if ( $field->get_input_type() == 'fileupload' ) {
					$input_name = 'input_' . $field->id;
					$form_id = $form['id'];

					$value = null;

					if ( isset( $saved_entry[ $field->id ] ) ) {
						$value = $saved_entry[ $field->id ];
					}

					if ( ! empty( $_FILES[ $input_name ] ) && ! empty( $_FILES[  $input_name ]['name'] ) ) {
						$file_path = GFFormsModel::get_file_upload_path( $form['id'], $_FILES[ $input_name ]['name'] );
						$value = $file_path['url'];

					} else {
						$_FILES[ $input_name ] = array( 'name' => '', 'size' => '' );
					}

					if ( $field->multipleFiles ) {
						if ( isset( GFFormsModel::$uploaded_files[ $form_id ][  $input_name ] ) ) {
							$value = empty( $value ) ? '[]' : $value;
							$value = stripslashes_deep( $value );
							$value = GFFormsModel::prepare_value( $form, $field, $value, $input_name, $saved_entry['id'], array() );
						}
					} else {
						GFFormsModel::$uploaded_files[ $form_id ][ $input_name ] = $value;
					}

					$original_value = GFFormsModel::get_lead_field_value( $saved_entry, $field );
					if ( empty( $value ) && ! empty( $original_value ) ) {
						continue;
					}

					$_POST[ $input_name ] = $value;

					if ( $field->isRequired && empty( $value ) ) {
						$field->failed_validation  = true;
						$field->validation_message = empty( $field->errorMessage ) ? esc_html__( 'This field is required.', 'gravityflow' ) : $field->errorMessage;
						$valid = false;
					}


					$field->validate( $value, $form );
					if ( $field->failed_validation ) {
						$valid = false;
					}

					continue;
				}

				$submission_is_empty = $field->is_value_submission_empty( $form['id'] );

				if ( $page_load_conditional_logic_enabled ) {
					$field_is_hidden = GFFormsModel::is_field_hidden( $form, $field, array(), $entry );
				} elseif ( $dynamic_conditional_logic_enabled ) {
					$field_is_hidden = GFFormsModel::is_field_hidden( $form, $field, array() );
				} else {
					$field_is_hidden = false;
				}

				if ( ! $field_is_hidden && $submission_is_empty && $field->isRequired ) {
					$field->failed_validation  = true;
					$field->validation_message = empty( $field->errorMessage ) ? esc_html__( 'This field is required.', 'gravityflow' ) : $field->errorMessage;
					$valid                     = false;
				} elseif ( ! $field_is_hidden && ! $submission_is_empty ) {
					$field->validate( $value, $form );
					if ( $field->failed_validation ) {
						$valid = false;
					}
				}
			}
		}

		if ( ! $valid ) {
			$form['failed_validation'] = true;
		}

		$validation_result = array(
			'is_valid' => $valid,
			'form' => $form,
		);

		$validation_result = apply_filters( 'gravityflow_validation_user_input', $validation_result, $this, $new_status );

		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		if ( ! $validation_result['is_valid'] ) {
			$valid = new WP_Error( 'validation_result', esc_html__( 'There was a problem while updating your form.', 'gravityflow' ), $validation_result );
		}

		if ( $validation_result['is_valid'] ) {
			return true;
		}

		return $valid;
	}


	public function workflow_detail_status_box( $form, $args ) {
		global $current_user;

		$form_id = absint( $form['id'] );

		$status_str            = __( 'Pending Input', 'gravityflow' );
		$approve_icon      = '<i class="fa fa-check" style="color:green"></i>';
		$input_step_status = $this->get_status();
		if ( $input_step_status == 'complete' ) {
			$status_str = $approve_icon . __( 'Complete', 'gravityflow' );
		} elseif ( $input_step_status == 'queued' ) {
			$status_str = __( 'Queued', 'gravityflow' );
		}

		$display_step_status = (bool) $args['step_status'];

		?>
		<div>
			<?php
			/**
			 * Allows the assignee status list to be hidden.
			 *
			 * @param array $form
			 * @param array $entry
			 * @param Gravity_Flow_Step $current_step
			 */
			$display_assignee_status_list = apply_filters( 'gravityflow_assignee_status_list_user_input', $display_step_status, $form, $this );
			if ( $display_assignee_status_list ) {
				?>
				<h4 style="margin-bottom:10px;"><?php echo $this->get_name() . ' (' . $status_str . ')'?></h4>
				<ul>
					<?php
					$assignees = $this->get_assignees();

					gravity_flow()->log_debug( __METHOD__ . '(): assignee details: ' . print_r( $assignees, true ) );

					foreach ( $assignees as $assignee ) {

						gravity_flow()->log_debug( __METHOD__ . '(): showing status for: ' . $assignee->get_key() );

						$assignee_status = $assignee->get_status();

						gravity_flow()->log_debug( __METHOD__ . '(): assignee status: ' . $assignee_status );

						if ( ! empty( $assignee_status ) ) {

							$assignee_type = $assignee->get_type();
							$assignee_id = $assignee->get_id();

							if ( $assignee_type == 'user_id' ) {
								$user_info = get_user_by( 'id', $assignee_id );
								$status_label = $this->get_status_label( $assignee_status );
								echo sprintf( '<li>%s: %s (%s)</li>', esc_html__( 'User', 'gravityflow' ), $user_info->display_name,  $status_label );
							} elseif ( $assignee_type == 'email' ) {
								$email = $assignee_id;
								$status_label = $this->get_status_label( $assignee_status );
								echo sprintf( '<li>%s: %s (%s)</li>', esc_html__( 'Email', 'gravityflow' ), $email,  $status_label );
							} elseif ( $assignee_type == 'role' ) {
								$status_label = $this->get_status_label( $assignee_status );
								$role_name = translate_user_role( $assignee_id );
								echo sprintf( '<li>%s: (%s)</li>', esc_html__( 'Role', 'gravityflow' ), $role_name, $status_label );
								echo '<li>' . $role_name . ': ' . $assignee_status . '</li>';
							}
						}
					}

					?>
				</ul>
				<?php
			}
			?>
			<div>
				<?php

				if ( $token = gravity_flow()->decode_access_token() ) {
					$assignee_key = sanitize_text_field( $token['sub'] );
				} else {
					$assignee_key = 'user_id|' . $current_user->ID;
				}
				$assignee = new Gravity_Flow_Assignee( $assignee_key, $this );
				$assignee_status = $assignee->get_status();

				$role_status = false;
				foreach ( gravity_flow()->get_user_roles() as $role ) {
					$role_status = $this->get_role_status( $role );
					if ( $role_status == 'pending' ) {
						break;
					}
				}

				if ( $assignee_status == 'pending' || $role_status == 'pending' ) {
					?>
					<script>
						(function (GFFlowInput, $) {
							$(document).ready(function () {
								$('#gravityflow_update_button').prop('disabled', false);
							});
						}(window.GFFlowInput = window.GFFlowInput || {}, jQuery));
					</script>
				<?php
				}
				?>
			</div>
			<?php

			/**
			 * Allows content to be added in the workflow box below the status list.
			 *
			 * @param Gravity_Flow_Step $this
			 * @param array $form
			 */
			do_action( 'gravityflow_below_status_list_user_input', $this, $form );

			$can_update = $assignee_status == 'pending' || $role_status == 'pending';

			if ( $can_update ) {
				$default_status = $this->default_status ? $this->default_status : 'complete';

				if ( $this->note_mode !== 'hidden' ) {
					$invalid_note = ( isset( $form['workflow_note'] ) && is_array( $form['workflow_note'] ) && $form['workflow_note']['failed_validation'] );
					$posted_note = '';
					if ( rgar( $form, 'failed_validation' ) ) {
						$posted_note = rgpost( 'gravityflow_note' );
					}
					?>

					<div>
						<label id="gravityflow-notes-label"
						       for="gravityflow-note">
							<?php
							esc_html_e( 'Note', 'gravityflow' );
							$required_indicator = ( $this->note_mode == 'required' ) ? '*' : '';
							printf( "<span class='gfield_required'>%s</span>", $required_indicator );
							?>
						</label>
					</div>

					<textarea id="gravityflow-note" style="width:100%;" rows="4" class="wide"
					          name="gravityflow_note"><?php echo esc_textarea( $posted_note )?></textarea>
					<?php

					if ( $invalid_note ) {
						printf( "<div class='gfield_description validation_message'>%s</div>", $form['workflow_note']['validation_message'] );
					}
				}
				if ( $default_status == 'hidden' ) {
					?>
					<input type="hidden" id="gravityflow_status_hidden" name="gravityflow_status" value="complete" />
					<?php
				} else {

					$in_progress_label = esc_html__( 'In progress', 'gravityflow' );

					/**
					 * Allows the 'in progress' label to be modified on the User Input step.
					 * @params string $in_progress_label
					 * @params Gravity_Flow_Step $this The current step.
					 */
					$in_progress_label = apply_filters( 'gravityflow_in_progress_label_user_input', $in_progress_label, $this );


					$complete_label = esc_html__( 'Complete', 'gravityflow' );

					/**
					 * Allows the 'complete' label to be modified on the User Input step.
					 * @params string $complete_label
					 * @params Gravity_Flow_Step $this The current step.
					 */
					$complete_label = apply_filters( 'gravityflow_complete_label_user_input', $complete_label, $this )
					?>
					<br /><br />
					<div>
						<label for="gravityflow_in_progress"><input type="radio" id="gravityflow_in_progress" name="gravityflow_status" <?php checked( $default_status, 'in_progress' ); ?> value="in_progress" /><?php echo $in_progress_label; ?></label>&nbsp;&nbsp;
						<label for="gravityflow_complete"><input type="radio" id="gravityflow_complete" name="gravityflow_status" value="complete" <?php checked( $default_status, 'complete' ); ?>/><?php echo $complete_label; ?></label>
					</div>
					<?php
				}
				?>
				<br />
				<div style="text-align:right;">
				<?php
				$button_text      = esc_html__( 'Update', 'gravityflow' );
				$button_text      = apply_filters( 'gravityflow_update_button_text_user_input', $button_text, $form, $this );
				$update_button_id = 'gravityflow_update_button';
				$button_click     = "jQuery('#action').val('update'); jQuery('#gform_{$form_id}')[0].submit(); return false;";
				$update_button    = '<input id="' . $update_button_id . '" disabled="disabled" class="button button-large button-primary" type="submit" tabindex="4" value="' . $button_text . '" name="save" onclick="' . $button_click . '"/>';
				echo apply_filters( 'gravityflow_update_button_user_input', $update_button );
				?>
				</div>
				<?php
			}

			?>
		</div>
	<?php
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

					$assignee_type = $this->get_type();

					$status = $assignee->get_status();

					if ( ! empty( $user_status ) ) {
						$status_label = $this->get_status_label( $status );
						switch ( $assignee_type ) {
							case 'email':
								echo sprintf( '<li>%s: %s (%s)</li>', esc_html__( 'Email', 'gravityflow' ), $this->get_id(),  $status_label );
								break;
							case 'user_id' :
								$user_info = get_user_by( 'id', $assignee->get_id() );
								echo '<li>' . esc_html__( 'User', 'gravityflow' ) . ': ' . $user_info->display_name . '<br />' . esc_html__( 'Status', 'gravityflow' ) . ': ' . esc_html( $status_label ) . '</li>';
								break;
							case 'role' :

								$role_name = translate_user_role( $assignee->get_id() );
								echo '<li>' . $role_name . ': ' . esc_html( $status_label ) . '</li>';
								break;
						}
					}
				}

				?>
			</ul>
		</div>
	<?php
	}

	public function save_entry( $form, &$lead, $editable_fields ) {
		global $wpdb;

		gravity_flow()->log_debug( __METHOD__ . '(): Saving entry.' );

		$lead_detail_table = GFFormsModel::get_lead_details_table_name();
		$is_new_lead       = $lead == null;

		// Bailing if null
		if ( $is_new_lead ) {
			return;
		}

		$current_fields   = $wpdb->get_results( $wpdb->prepare( "SELECT id, field_number FROM $lead_detail_table WHERE lead_id=%d", $lead['id'] ) );

		$total_fields = array();

		/* @var $calculation_fields GF_Field[] */
		$calculation_fields = array();

		GFCommon::log_debug( __METHOD__ . '(): Saving entry fields.' );

		foreach ( $form['fields'] as &$field ) {
			/* @var $field GF_Field */

			//Ignore fields that are marked as display only
			if ( $field->displayOnly && $field->type != 'password' ) {
				continue;
			}

			//process total field after all fields have been saved
			if ( $field->type == 'total' ) {
				$total_fields[] = $field;
				continue;
			}

			// process calculation fields after all fields have been saved (moved after the is hidden check)
			if ( $field->has_calculation() ) {
				$calculation_fields[] = $field;
				continue;
			}

			if ( ! in_array( $field->id, $editable_fields ) ) {
				continue;
			}

			if ( ! $this->conditional_logic_editable_fields_enabled ) {
				$field->conditionalLogic = null;
			}

			gravity_flow()->log_debug( __METHOD__ . "(): Saving field {$field->label}(#{$field->id} - {$field->type})." );

			if ( $field->type == 'post_category' ) {
				$field = GFCommon::add_categories_as_choices( $field, '' );
			}

			$inputs = $field->get_entry_inputs();

			if ( is_array( $inputs ) ) {
				foreach ( $inputs as $input ) {
					GFFormsModel::save_input( $form, $field, $lead, $current_fields, $input['id'] );
				}
			} else {
				GFFormsModel::save_input( $form, $field, $lead, $current_fields, $field->id );
			}
		}

		if ( ! empty( $calculation_fields ) ) {
			foreach ( $calculation_fields as $calculation_field ) {

				gravity_flow()->log_debug( __METHOD__ . "(): Saving calculated field {$calculation_field->label}(#{$calculation_field->id} - {$calculation_field->type})." );

				// Make sure that the value gets recalculated
				$calculation_field->conditionalLogic = null;

				$inputs = $calculation_field->get_entry_inputs();

				if ( is_array( $inputs ) ) {

					if ( ! in_array( $calculation_field->id, $editable_fields ) ) {
						// Make sure calculated product names and quantities are saved as if they're submitted.
						$value = array( $calculation_field->id . '.1' => $lead[ $calculation_field->id . '.1' ] );
						$_POST[ 'input_' . $calculation_field->id . '_1' ] = $calculation_field->get_field_label( false, $value );
						$quantity = trim( $lead[ $calculation_field->id . '.3' ] );
						if ( $calculation_field->disableQuantity && empty( $quantity ) ) {
							$_POST[ 'input_' . $calculation_field->id . '_3' ] = 1;
						} else {
							$_POST[ 'input_' . $calculation_field->id . '_3' ] = $quantity;
						}
					}
					foreach ( $inputs as $input ) {
						GFFormsModel::save_input( $form, $calculation_field, $lead, $current_fields, $input['id'] );
						GFFormsModel::refresh_lead_field_value( $lead['id'], $input['id'] );
					}
				} else {
					GFFormsModel::save_input( $form, $calculation_field, $lead, $current_fields, $calculation_field->id );
					GFFormsModel::refresh_lead_field_value( $lead['id'], $calculation_field->id );
				}
			}
		}

		GFFormsModel::refresh_product_cache( $form, $lead = RGFormsModel::get_lead( $lead['id'] ) );

		//saving total field as the last field of the form.
		if ( ! empty( $total_fields ) ) {
			foreach ( $total_fields as $total_field ) {
				gravity_flow()->log_debug( __METHOD__ . '(): Saving total field.' );
				GFFormsModel::save_input( $form, $total_field, $lead, $current_fields, $total_field->id );
				GFFormsModel::refresh_lead_field_value( $lead['id'], $total_field->id );
			}
		}
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
		$text = parent::replace_variables( $text, $assignee );
		$comment = rgpost( 'gravityflow_note' );
		$text = str_replace( '{workflow_note}', $comment, $text );

		return $text;
	}
}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_User_Input() );
