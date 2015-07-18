<?php

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
			)
		);
	}

	public function get_assignees() {

		$assignee_details = array();

		$input_type = $this->type;

		switch ( $input_type ) {
			case 'select':
				foreach ( $this->assignees as $assignee ) {
					$assignee_details[] = array(
						'assignee'  => $assignee,
						'editable_fields' => $this->editable_fields,
					);
				}
				break;
			case 'field':
				$entry = $this->get_entry();
				$assignee = rgar( $entry, $this->assignee_field );
				$assignee_details[] = array(
					'assignee'  => $assignee,
					'editable_fields' => $this->editable_fields,
				);
				break;
			case 'routing' :
				$routings = $this->routing;
				if ( is_array( $routings ) ) {
					foreach ( $routings as $routing ) {
						$assignee_details[] = array(
							'assignee'  => rgar( $routing, 'assignee' ),
							'editable_fields' => rgar( $routing, 'editable_fields' ),
						);
					}
				}

				break;
		}

		$entry = $this->get_entry();

		if ( $entry ) {
			$required_assignees = array();
			foreach ( $assignee_details as $assignee_detail ) {
				$assignee = $assignee_detail['assignee'];
				if ( $this->is_input_required( $assignee ) ) {
					$required_assignees[] = $assignee_detail;
				}
			}
			$assignee_details = $required_assignees;
		}

		return $assignee_details;
	}

	public function is_input_required( $assignee ) {

		$input_type = $this->type;

		if ( $input_type != 'routing' ) {
			return true;
		}

		$required = false;

		if ( $input_type == 'routing' ) {
			$routings = $this->routing;
			foreach ( $routings as $routing ) {
				if ( $assignee != $routing['assignee'] ) {
					continue;
				}
				if ( $this->evaluate_routing_rule( $routing ) ) {
					$required = true;
					break;
				}
			}
		}

		return $required;
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

		foreach ( $assignee_details as $assignee_detail ) {
			$assignee = $assignee_detail['assignee'];
			$user_status = $this->get_assignee_status( $assignee );
			if ( empty( $user_status ) || $user_status == 'pending' ) {
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

	public function get_editable_fields( $user_id = false ){
		if ( $user_id === false ) {
			global $current_user;
			$user_id = $current_user->ID;
		}

		$editable_fields = array();
		$assignee_details = $this->get_assignees();

		foreach ( $assignee_details as $assignee_detail ) {
			$assignee = $assignee_detail['assignee'];
			list( $assignee_type, $assignee_id) = explode( '|', $assignee );
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

			$match = false;
			switch ( $assignee_type ) {
				case 'user_id' :
					if ( $assignee_id == $user_id ) {
						$match = true;
					}
					break;
				case 'role' :
					if ( gravity_flow()->check_user_role( $assignee_id, $user_id ) ) {
						$match = true;
					}
			}

			if ( $match ) {
				if ( is_array( $assignee_detail['editable_fields'] ) ) {
					$editable_fields = array_merge( $editable_fields, $assignee_detail['editable_fields'] );
				}
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
				$current_user_status = $this->get_user_status( $user->ID );

				$current_role_status = false;
				$role = false;
				foreach ( gravity_flow()->get_user_roles() as $role ) {
					$current_role_status = $this->get_role_status( $role );
					if ( $current_role_status == 'pending' ) {
						break;
					}
				}
				if ( $current_user_status == 'pending' ) {
					$this->update_assignee_status( $user->ID, 'user_id', 'complete' );
				}

				if ( $current_role_status == 'pending' ) {
					$this->update_assignee_status( $role, 'role', 'complete' );
				}

			}
			$this->maybe_adjust_assignment();

			$feedback = $new_status == 'complete' ?  __( 'Entry updated and step complete.', 'gravityflow' ) : __( 'Entry updated.', 'gravityflow' );

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

	public function maybe_adjust_assignment(){
		$input_type = $this->type;

		if ( $input_type == 'field' ) {
			$entry = $this->get_entry();
			$entry_id = $this->get_entry_id();

			$assignee = rgar( $entry, $this->assignee_field );
			$assignee_status = $this->get_assignee_status( $assignee );
			$cache_key = 'GFFormsModel::get_lead_field_value_' . $entry_id . '_' . $this->assignee_field;
			GFCache::flush( $cache_key );
			if ( $assignee_status === false ) {
				// Remove the current user
				$this->remove_assignee();

				// Reassign to this user.
				$this->update_assignee_status( $assignee, false, 'pending' );
				// todo: send notification
			}
		} elseif ( $input_type == 'routing' ) {
			$routings = $this->routing;
			$entry_id = $this->get_entry_id();
			foreach ( $routings as $routing ) {
				$assignee = $routing['assignee'];
				$assignee_status = $this->get_assignee_status( $assignee );
				$cache_key = 'GFFormsModel::get_lead_field_value_' . $entry_id . '_' . $routing['fieldId'];
				GFCache::flush( $cache_key );
				$user_is_assignee = $this->evaluate_routing_rule( $routing );
				if ( $assignee_status === false && $user_is_assignee ) {
					// The user has been added.
					$this->update_assignee_status( $assignee, false, 'pending' );
					// todo: send notification
				} elseif ( $assignee !== false && ! $user_is_assignee ) {
					// The user has been removed.
					$this->remove_assignee( $assignee );
					// todo: send notification
				}
			}
		}
	}


	public function workflow_detail_status_box( $form ){
		global $current_user;

		$form_id = absint( $form['id'] );

		$status            = 'Pending Input';
		$approve_icon      = '<i class="fa fa-check" style="color:green"></i>';
		$input_step_status = $this->get_status();
		if ( $input_step_status == 'complete' ) {
			$status = $approve_icon . ' Complete';
		} elseif ( $input_step_status == 'queued' ) {
			$status = 'Queued';
		}
		?>
		<h4 style="margin-bottom:10px;"><?php echo $this->get_name() . ' (' . $status . ')'?></h4>

		<div>
			<ul>
				<?php
				$assignee_details = $this->get_assignees();

				$editable_fields = array();
				foreach ( $assignee_details as $assignee_detail ) {
					$assignee = $assignee_detail['assignee'];
					list( $assignee_type, $assignee_id) = explode( '|', $assignee );

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

					$user_status = $this->get_assignee_status( $assignee );
					if ( ! empty( $user_status ) ) {
						if ( $assignee_type == 'user_id' ) {
							$user_info = get_user_by( 'id', $assignee_id );
							$status = $this->get_status_label( $user_status );
							echo sprintf( '<li>%s: %s (%s)</li>', esc_html__( 'User', 'gravityflow' ), $user_info->display_name,  $status );
							if ( $assignee_id == $current_user->ID ) {
								$editable_fields = $assignee_detail['editable_fields'];
							}
						} elseif ( $assignee_type == 'role' ) {
							$status = $this->get_status_label( $user_status );
							$role_name = translate_user_role( $assignee_id );
							echo sprintf( '<li>%s: (%s)</li>', esc_html__( 'Role', 'gravityflow' ), $role_name, $status );
							echo '<li>' . $role_name . ': ' . $status . '</li>';
							if ( gravity_flow()->check_user_role( $assignee_id, $current_user->ID ) ) {
								$editable_fields = $assignee_detail['editable_fields'];
							}
						}
					}
				}

				?>
			</ul>
			<div>
				<?php
				$user_status = $this->get_user_status( $current_user->ID );
				$role_status = false;
				foreach ( gravity_flow()->get_user_roles() as $role ) {
					$role_status = $this->get_role_status( $role );
					if ( $role_status == 'pending' ) {
						break;
					}
				}

				if ( $user_status == 'pending' || $role_status == 'pending' ) {

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

			$current_user_status = $this->get_user_status();
			$can_update = $current_user_status == 'pending' || $role_status == 'pending';

			if ( $can_update ) {
				?>
				<div>
					<label id="gravityflow-notes-label" for="gravityflow-note"><?php esc_html_e( 'Notes', 'gravityflow' ); ?></label>
				</div>

				<textarea id="gravityflow-note" style="width:100%;" rows="4" class="wide" name="gravityflow_note"></textarea>
				<br /><br />
				<div>
					<label for="gravityflow_in_progress"><input type="radio" id="gravityflow_in_progress" name="gravityflow_status" value="in_progress" /><?php esc_html_e( 'In progress', 'gravityflow' ); ?></label>&nbsp;&nbsp;
					<label for="gravityflow_complete"><input type="radio" id="gravityflow_complete" name="gravityflow_status" value="complete" checked="checked"/><?php esc_html_e( 'Complete', 'gravityflow' ); ?></label>
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

				$assignee_details = $this->get_assignees();

				foreach ( $assignee_details as $assignee_detail ) {
					$assignee = $assignee_detail['assignee'];
					list( $assignee_type, $assignee_id) = explode( '|', $assignee );

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

					$user_status = $this->get_assignee_status( $assignee );
					if ( ! empty( $user_status ) ) {
						if ( $assignee_type == 'user_id' ) {
							$user_info = get_user_by( 'id', $assignee_id );
							$status    = $user_status;
							echo '<li>' . esc_html__( 'User', 'gravityflow' ) . ': ' . $user_info->display_name . '<br />' . esc_html__( 'Status', 'gravityflow' ) . ': ' . $status . '</li>';

						} elseif ( $assignee_type == 'role' ) {
							$status    = $this->get_status_label( $user_status );
							$role_name = translate_user_role( $assignee_id );
							echo '<li>' . $role_name . ': ' . $status . '</li>';

						}
					}
				}

				foreach ( $assignee_details as $input_assignee_detail ) {
					$input_assignee     = $input_assignee_detail['assignee'];
					$user_status = $this->get_user_status( $input_assignee );
					if ( ! empty( $user_status ) ) {
						$user_info = get_user_by( 'id', $input_assignee );
						$status    = $user_status;
						echo '<li>' . $user_info->display_name . ': ' . $status . '</li>';
					}
				}

				?>
			</ul>
		</div>
	<?php
	}

	public static function save_entry( $form, &$lead, $editable_fields ) {
		global $wpdb;

		GFCommon::log_debug( 'GFFormsModel::save_lead(): Saving entry.' );

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

			GFCommon::log_debug( "GFFormsModel::save_lead(): Saving field {$field->label}(#{$field->id} - {$field->type})." );

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

				GFCommon::log_debug( "GFFormsModel::save_lead(): Saving calculated field {$calculation_field->label}(#{$calculation_field->id} - {$calculation_field->type})." );

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
				GFCommon::log_debug( 'GFFormsModel::save_lead(): Saving total field.' );
				GFFormsModel::save_input( $form, $total_field, $lead, $current_fields, $total_field->id );
				GFFormsModel::refresh_lead_field_value( $lead['id'], $total_field['id'] );
			}
		}
	}

	public function replace_variables($text, $user_id){
		$text = parent::replace_variables( $text, $user_id );
		$comment = rgpost( 'gravityflow_note' );
		$text = str_replace( '{workflow_note}', $comment, $text );

		return $text;
	}


}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_User_Input() );