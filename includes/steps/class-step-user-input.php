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

	public function get_settings(){

		$account_choices = gravity_flow()->get_users_as_choices();

		$type_field_choices = array(
			array( 'label' => __( 'Select Users', 'gravityflow' ), 'value' => 'select' ),
			array( 'label' => __( 'Configure Routing', 'gravityflow' ), 'value' => 'routing' ),
		);

		return array(
			'title'  => 'User Input',
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
				array(
					'id' => 'assignee_policy',
					'name'     => 'assignee_policy',
					'label'    => __( 'Assignee Policy', 'gravityflow' ),
					'tooltip'   => __( 'Define how this step should be processed. If all assignees must complete this step then the entry will require input from every assignee before the step can be completed. If the step is assigned to a role only one user in that role needs to complete the step.', 'gravityflow' ),
					'type'     => 'radio',
					'default_value' => 'all',
					'choices' => array(
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
					'name' => 'default_status',
					'type' => 'radio',
					'label' => __( 'Default update status', 'gravityflow' ),
					'tooltip' => __( 'The default value for the status on the workflow detail page', 'gravityflow' ),
					'default_value' => 'complete',
					'horizontal' => true,
					'choices' => array(
						array( 'label' => __( 'In progress', 'gravityflow' ), 'value' => 'in_progress' ),
						array( 'label' => __( 'Complete', 'gravityflow' ), 'value' => 'complete' ),
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
					)
				),
				array(
					'name'  => 'assignee_notification_message',
					'label' => 'Message',
					'type'  => 'visual_editor',
				),
				array(
					'name' => 'resend_assignee_email',
					'label' => __( 'Send reminder', 'gravityflow' ),
					'type' => 'checkbox_and_text',
					'text' => array(
						'default_value' => 7,
						'before_input' => __( 'Resend the assignee email after', 'gravityflow' ),
						'after_input' => ' ' . __( 'day(s)', 'gravityflow' )
					)
				),
			)
		);
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

	public function is_complete(){
		$status = $this->get_status();

		return $status == 'complete';
	}

	public function get_next_step_id(){
		if ( isset( $this->next_step_id ) ) {
			return $this->next_step_id;
		}
		$this->next_step_id = $this->is_complete() ? $this->destination_complete : $this->get_id();
		return $this->next_step_id;
	}

	public function get_status() {

		if ( $this->is_queued() ) {
			return 'queued';
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

	public function get_editable_fields(){
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
		return $editable_fields;
	}


	public function maybe_process_status_update( $form, $entry ){

		$feedback = false;

		$form_id = $form['id'];

		if ( isset( $_POST['gforms_save_entry'] ) && check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' ) ) {
			//Loading files that have been uploaded to temp folder
			$files = GFCommon::json_decode( stripslashes( RGForms::post( 'gform_uploaded_files' ) ) );
			if ( ! is_array( $files ) ) {
				$files = array();
			}

			GFFormsModel::$uploaded_files[ $form_id ] = $files;

			$editable_fields = $this->get_editable_fields();

			$previous_assignees = $this->get_assignees();

			$this->save_entry( $form, $entry, $editable_fields );

			remove_action( 'gform_after_update_entry', array( gravity_flow(), 'filter_after_update_entry' ) );

			do_action( 'gform_after_update_entry', $form, $entry['id'] );
			do_action( "gform_after_update_entry_{$form['id']}", $form, $entry['id'] );

			$entry = GFFormsModel::get_lead( $entry['id'] );
			$entry = GFFormsModel::set_entry_meta( $entry, $form );

			$this->refresh_entry();

			$user = wp_get_current_user();

			$new_status = rgpost( 'gravityflow_status' );

			if ( ! in_array( $new_status, array( 'in_progress', 'complete' ) ) ) {
				return false;
			}

			if ( $new_status == 'complete' ) {
				$current_user_status = $this->get_user_status();

				$current_role_status = false;
				$role = false;
				foreach ( gravity_flow()->get_user_roles() as $role ) {
					$current_role_status = $this->get_role_status( $role );
					if ( $current_role_status == 'pending' ) {
						break;
					}
				}
				if ( $current_user_status == 'pending' ) {
					if ( $token = gravity_flow()->decode_access_token() ) {
						$assignee_key = sanitize_text_field( $token['sub'] );

					} else {
						$assignee_key = 'user_id|' . $user->ID;
					}
					$assignee = new Gravity_Flow_Assignee( $assignee_key, $this );
					$assignee->update_status( 'complete' );
				}

				if ( $current_role_status == 'pending' ) {
					$this->update_role_status( $role, 'complete' );
				}
				$this->refresh_entry();
			}

			GFCache::flush();
			$this->maybe_adjust_assignment( $previous_assignees );

			$feedback = $new_status == 'complete' ?  __( 'Entry updated and marked complete.', 'gravityflow' ) : __( 'Entry updated - in progress.', 'gravityflow' );

			$note = sprintf( '%s: %s', $this->get_name(), $feedback );

			$user_note = rgpost( 'gravityflow_note' );
			if ( ! empty( $user_note ) ) {
				$note .= sprintf( "\n%s: %s", esc_html__( 'Note', 'gravityflow' ), $user_note );
			}

			$this->add_note( $note );

			$status = $this->get_status();
			$this->update_step_status( $status );
			$entry = $this->refresh_entry();

			GFAPI::send_notifications( $form, $entry, 'workflow_user_input' );

		}
		return $feedback;
	}

	/**
	 * @param Gravity_Flow_Assignee[] $previous_assignees
	 */
	public function maybe_adjust_assignment( $previous_assignees ){

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


	public function workflow_detail_status_box( $form ){
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
		?>
		<h4 style="margin-bottom:10px;"><?php echo $this->get_name() . ' (' . $status_str . ')'?></h4>

		<div>
			<ul>
				<?php
				$assignees = $this->get_assignees();

				gravity_flow()->log_debug( __METHOD__ . '(): assignee details: ' . print_r( $assignees, true ) );

				$editable_fields = array();
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
							if ( $assignee_id == $current_user->ID ) {
								$editable_fields = $assignee->get_editable_fields();
							}
						} elseif ( $assignee_type == 'email' ) {
							$email = $assignee_id;
							$status_label = $this->get_status_label( $assignee_status );
							echo sprintf( '<li>%s: %s (%s)</li>', esc_html__( 'Email', 'gravityflow' ), $email,  $status_label );
							if ( $email == rgget('gflow_access_email' ) ) {
								$editable_fields = $assignee['editable_fields'];
							} elseif ( $token = gravity_flow()->decode_access_token() ) {
								if ( $email == gravity_flow()->parse_token_assignee( $token )->get_id() ) {
									$editable_fields = $assignee->get_editable_fields();
								}
							}
						} elseif ( $assignee_type == 'role' ) {
							$status_label = $this->get_status_label( $assignee_status );
							$role_name = translate_user_role( $assignee_id );
							echo sprintf( '<li>%s: (%s)</li>', esc_html__( 'Role', 'gravityflow' ), $role_name, $status_label );
							echo '<li>' . $role_name . ': ' . $assignee_status . '</li>';
							if ( gravity_flow()->check_user_role( $assignee_id, $current_user->ID ) ) {
								$editable_fields = $assignee->get_editable_fields();
							}
						}
					}
				}

				?>
			</ul>
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

					$field_ids = array();
					if ( is_array( $editable_fields ) ) {
						foreach ( $editable_fields as $editable_field ) {
							$field_ids[] = '#field_' . $form_id . '_' . str_replace( '.', '_', $editable_field );
						}
					}

					$field_ids_str = join( ', ', $field_ids );
					?>
					<script>
						(function (GFFlowInput, $) {
							$(document).ready(function () {
								$('#gravityflow_update_button').prop('disabled', false);
								$(<?php  echo json_encode( $field_ids_str ) ?>).addClass('gravityflow-input-required');
							});
						}(window.GFFlowInput = window.GFFlowInput || {}, jQuery));
					</script>
				<?php
				}
				?>
			</div>
			<?php

			$can_update = $assignee_status == 'pending' || $role_status == 'pending';

			if ( $can_update ) {
				$default_status = $this->default_status ? $this->default_status : 'complete';
				?>
				<div>
					<label id="gravityflow-notes-label" for="gravityflow-note"><?php esc_html_e( 'Notes', 'gravityflow' ); ?></label>
				</div>

				<textarea id="gravityflow-note" style="width:100%;" rows="4" class="wide" name="gravityflow_note"></textarea>
				<br /><br />
				<div>
					<label for="gravityflow_in_progress"><input type="radio" id="gravityflow_in_progress" name="gravityflow_status" <?php checked( $default_status, 'in_progress' ); ?> value="in_progress" /><?php esc_html_e( 'In progress', 'gravityflow' ); ?></label>&nbsp;&nbsp;
					<label for="gravityflow_complete"><input type="radio" id="gravityflow_complete" name="gravityflow_status" value="complete" <?php checked( $default_status, 'complete' ); ?>/><?php esc_html_e( 'Complete', 'gravityflow' ); ?></label>
				</div>
				<br />
				<div style="text-align:right;">
				<?php
				$button_text      = __( 'Update', 'gravityflow' );
				$update_button_id = 'gravityflow_update_button';
				$button_click     = "jQuery('#action').val('update'); jQuery('#entry_form').submit(); return false;";
				$update_button    = '<input id="' . $update_button_id . '" disabled="disabled" class="button button-large button-primary" type="submit" tabindex="4" value="' . $button_text . '" name="save" onclick="' . $button_click . '"/>';
				echo apply_filters( 'gravityflow_entrydetail_update_button', $update_button );
				?>
				</div>
				<?php
			}

			?>
		</div>
	<?php
	}

	public function entry_detail_status_box( $form ){
		$status = $this->get_status();
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

	public static function save_entry( $form, &$lead, $editable_fields ) {
		global $wpdb;

		gravity_flow()->log_debug( __METHOD__ . '(): Saving entry.' );

		$is_form_editor = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin = $is_form_editor || $is_entry_detail;

		if ( $is_admin && ! GFCommon::current_user_can_any( 'gravityforms_edit_entries' ) ) {
			die( __( "You don't have adequate permission to edit entries.", 'gravityflow' ) );
		}

		$lead_detail_table = GFFormsModel::get_lead_details_table_name();
		$is_new_lead       = $lead == null;

		//Bailing  if null
		if ( $is_new_lead ) {

			return;
		}

		$current_fields   = $wpdb->get_results( $wpdb->prepare( "SELECT id, field_number FROM $lead_detail_table WHERE lead_id=%d", $lead['id'] ) );

		$total_fields = array();
		/* @var $calculation_fields GF_Field[] */
		$calculation_fields = array();
		$recalculate_total  = false;

		GFCommon::log_debug( 'GFFormsModel::save_lead(): Saving entry fields.' );

		foreach ( $form['fields'] as $field ) {
			/* @var $field GF_Field */

			//Ignore fields that are marked as display only
			if ( $field->displayOnly && $field->type != 'password' ) {
				continue;
			}

			//ignore pricing fields in the entry detail
			if ( RG_CURRENT_VIEW == 'entry' && GFCommon::is_pricing_field( $field->type ) ) {
				//continue;
			}


			//process total field after all fields have been saved
			if ( $field->type == 'total' ) {
				$total_fields[] = $field;
				continue;
			}

			$read_value_from_post = $is_new_lead || ! isset( $lead[ 'date_created' ] );


			// process calculation fields after all fields have been saved (moved after the is hidden check)
			if ( $field->has_calculation() ) {
				$calculation_fields[] = $field;
				continue;
			}

			if ( ! in_array( $field->id, $editable_fields ) ) {
				continue;
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

				$inputs = $calculation_field->get_entry_inputs();

				if ( is_array( $inputs ) ) {
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
				GFFormsModel::refresh_lead_field_value( $lead['id'], $total_field['id'] );
			}
		}
	}

	/**
	 * @param $text
	 * @param Gravity_Flow_Assignee $assignee
	 *
	 * @return mixed
	 */
	public function replace_variables($text, $assignee){
		$text = parent::replace_variables( $text, $assignee );
		$comment = rgpost( 'gravityflow_note' );
		$text = str_replace( '{workflow_note}', $comment, $text );

		return $text;
	}


}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_User_Input() );