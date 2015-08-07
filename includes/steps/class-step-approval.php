<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Approval extends Gravity_Flow_Step {
	public $_step_type = 'approval';

	public function get_final_status_config(){
		return array(
			array(
				'status' => 'rejected',
				'status_label' => __( 'Rejected', 'gravityflow' ),
				'default_destination_label' => esc_html__( 'Next step if Rejected', 'gravityflow' ),
				'default_destination' => 'complete',
			),
			array(
				'status' => 'approved',
				'status_label' => __( 'Approved', 'gravityflow' ),
				'default_destination_label' => __( 'Next Step if Approved', 'gravityflow' ),
				'default_destination' => 'next',
			),
		);
	}

	public function get_label() {
		return esc_html__( 'Approval', 'gravityflow' );
	}

	public function get_settings(){

		$account_choices = gravity_flow()->get_users_as_choices();

		$type_field_choices = array(
			array( 'label' => __( 'Select Users', 'gravityflow' ), 'value' => 'select' ),
			array( 'label' => __( 'Configure Routing', 'gravityflow' ), 'value' => 'routing' ),
		);

		return array(
			'title'  => 'Approval',
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
					'tooltip'   => __( 'Users and roles fields will appear in this list. If the form contains any assignee fields they will also appear here. Click on an item to select it. The selected items will appear on the right. If you select a role then anybody from that role can approve.', 'gravityflow' ),
					'size'     => '8',
					'multiple' => 'multiple',
					'label'    => 'Select Assignees',
					'type'     => 'select',
					'choices'  => $account_choices,
				),
				array(
					'name'  => 'routing',
					'tooltip'   => __( 'Build assignee routing rules by adding conditions. Users and roles fields will appear in the first drop-down field. If the form contains any assignee fields they will also appear here. Select the assignee and define the condition for that assignee. Add as many routing rules as you need.', 'gravityflow' ),
					'label' => __( 'Routing', 'gravityflow' ),
					'type'  => 'routing',
				),
				array(
					'name'     => 'unanimous_approval',
					'label'    => __( 'Approval Policy', 'gravityflow' ),
					'tooltip'   => __( 'Define how approvals should be processed. If all assignees must approve then the entry will require unanimous approval before the step can be completed. If the step is assigned to a role only one user in that role needs to approve.', 'gravityflow' ),
					'type'     => 'radio',
					'default_value' => false,
					'choices' => array(
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
					'name' => 'notification_tabs',
					'label' => __( 'Emails', 'gravityflow' ),
					'tooltip'   => __( 'Configure the emails that should be sent for this step.', 'gravityflow' ),
					'type' => 'tabs',
					'tabs' => array(
						array(
							'label'  => __( 'Assignee Email', 'gravityflow' ),
							'id' => 'tab_assignee_notification',
							'fields' => array(
								array(
									'name'    => 'assignee_notification_enabled',
									'label'   => __( 'Send Email to the assignee(s).', 'gravityflow' ),
									'tooltip'   => __( 'Enable this setting to send email to each of the assignees as soon as the entry has been assigned. If a role is configured to receive emails then all the users with that role will receive the email.', 'gravityflow' ),
									'type'    => 'checkbox',
									'choices' => array(
										array(
											'label'         => __( 'Enabled', 'gravityflow' ),
											'name'          => 'assignee_notification_enabled',
											'default_value' => false,
										),
									)
								),
								array(
									'name'  => 'assignee_notification_message',
									'label' => __( 'Message to Assignee(s)', 'gravityflow' ),
									'type'  => 'visual_editor',
									'default_value' => __( 'A new entry is pending your approval. Please check your Workflow Inbox.', 'gravityflow' )
								),
							)
						),
						array(
							'label' => __( 'Rejection Email', 'gravityflow' ),
							'id' => 'tab_rejection_notification',
							'fields' => array(
								array(
									'name'    => 'rejection_notification_enabled',
									'label'   => __( 'Send email when the entry is rejected', 'gravityflow' ),
									'tooltip'   => __( 'Enable this setting to send an email when the entry is rejected.', 'gravityflow' ),
									'type'    => 'checkbox',
									'choices' => array(
										array(
											'label'         => __( 'Enabled', 'gravityflow' ),
											'name'          => 'rejection_notification_enabled',
											'default_value' => false,
										),
									)
								),
								array(
									'name'    => 'rejection_notification_type',
									'label'   => __( 'Send To', 'gravityflow' ),
									'type'       => 'radio',
									'default_value' => 'select',
									'horizontal' => true,
									'choices'    => $type_field_choices,
								),
								array(
									'id'       => 'rejection_notification_users',
									'name'    => 'rejection_notification_users[]',
									'label'   => __( 'Select User', 'gravityflow' ),
									'size'     => '8',
									'multiple' => 'multiple',
									'type'     => 'select',
									'choices'  => $account_choices,
								),
								array(
									'name'  => 'rejection_notification_routing',
									'label' => __( 'Routing', 'gravityflow' ) ,
									'type'  => 'user_routing',
								),
								array(
									'name'  => 'rejection_notification_message',
									'label' => __( 'Message', 'gravityflow' ),
									'type'  => 'visual_editor',
									'default_value' => __( 'Entry {entry_id} has been rejected', 'gravityflow' )
								),
							)
						),
						array(
							'label' => __( 'Approval Email', 'gravityflow' ),
							'id' => 'tab_approval_notification',
							'fields' => array(
								array(
									'name'    => 'approval_notification_enabled',
									'label'   => __( 'Send email when the entry is approved', 'gravityflow' ),
									'tooltip'   => __( 'Enable this setting to send an email when the entry is approved.', 'gravityflow' ),
									'type'    => 'checkbox',
									'choices' => array(
										array(
											'label'         => __( 'Enabled', 'gravityflow' ),
											'name'          => 'approval_notification_enabled',
											'default_value' => false,
										),
									)
								),
								array(
									'name'    => 'approval_notification_type',
									'label'   => __( 'Send To', 'gravityflow' ),
									'type'       => 'radio',
									'default_value' => 'select',
									'horizontal' => true,
									'choices'    => $type_field_choices,
								),
								array(
									'id'       => 'approval_notification_users',
									'name'    => 'approval_notification_users[]',
									'label'   => __( 'Select Users', 'gravityflow' ),
									'size'     => '8',
									'multiple' => 'multiple',
									'type'     => 'select',
									'choices'  => $account_choices,
								),
								array(
									'name'  => 'approval_notification_routing',
									'label' => __( 'Routing', 'gravityflow' ) ,
									'type'  => 'user_routing',
								),
								array(
									'name'  => 'approval_notification_message',
									'label' => __( 'Approval Message', 'gravityflow' ),
									'type'  => 'visual_editor',
									'default_value' => __( 'Entry {entry_id} has been approved', 'gravityflow' )
								),
							)
						),
					)
				),
			),
		);
	}


	public function get_next_step_id(){
		if ( isset( $this->next_step_id ) ) {
			return $this->next_step_id;
		}

		$status = $this->get_status();
		$this->next_step_id = $status == 'rejected' ? $this->destination_rejected : $this->destination_approved;
		return $this->next_step_id;
	}

	public function get_assignees() {

		$approvers = array();

		$type = $this->type;

		switch ( $type ) {
			case 'select' :
				$approvers = $this->assignees;
				if ( empty( $approvers ) || ! is_array( $approvers ) ) {
					$approvers = array();
				}
				break;
			case 'field' :
				$entry = $this->get_entry();
				$approvers = array( rgar( $entry, $this->assignee_field ) );
				break;
			case 'routing' :
				$routings = $this->routing;
				if ( is_array( $routings ) ) {
					$entry = $this->get_entry();
					foreach ( $routings as $routing ) {
						$assignee = rgar( $routing, 'assignee' );
						if ( in_array( $assignee, $approvers ) ) {
							continue;
						}
						if ( $entry ) {
							if ( $this->evaluate_routing_rule( $routing ) ) {
								$approvers[] = $assignee;
							}
						} else {
							$approvers[] = $assignee;
						}
					}
				} else {
					$approvers = array();
				}

				break;
		}

		return $approvers;
	}

	public function is_complete() {
		$status = $this->get_status();

		return $status != 'pending';
	}

	public function get_status(){

		if ( $this->is_queued() ) {
			return 'queued';
		}

		$approvers = $this->get_assignees();

		$step_status = 'approved';

		foreach ( $approvers as $approver ) {

			$approver_status = $this->get_assignee_status( $approver );

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

	public function is_valid_token( $token ){

		$token_json = base64_decode( $token );
		$token_array = json_decode( $token_json, true );

		if ( empty( $token_array ) ) {
			return false;
		}

		$timestamp = $token_array['timestamp'];
		$user_id = $token_array['user_id'];
		$new_status = $token_array['new_status'];
		$entry_id = $token_array['entry_id'];
		$sig = $token_array['sig'];


		$expiration_days = apply_filters( 'gravityflow_approval_token_expiration_days', 1 );

		$i = wp_nonce_tick();

		$is_valid = false;

		for ( $n = 1; $n <= $expiration_days; $n++ ) {
			$sig_key = sprintf( '%s|%s|%s|%s|%s|%s', $i, $this->get_id(), $timestamp, $entry_id, $user_id, $new_status );
			$verification_sig     = substr( wp_hash( $sig_key ),  -12, 10 );
			if ( hash_equals( $verification_sig, $sig ) ) {
				$is_valid = true;
				break;
			}
			$i--;
		}
		return $is_valid;
	}

	/**
	 * @deprecated  deprecated since version 1.0-beta-7
	 *
	 * @param $token
	 * @param $user_id
	 * @param $new_status
	 *
	 * @return bool
	 */
	public function is_valid_token_deprecated ($token, $user_id, $new_status){
		$expiration_days = apply_filters( 'gravityflow_approval_token_expiration_days', 1 );

		$i = wp_nonce_tick();

		$is_valid = false;

		$step_status_key = 'gravityflow_approval_new_status_step_' . $this->get_id();

		for ( $n = 1; $n <= $expiration_days; $n++ ) {
			$token_key = sprintf( '%s|%s|%s|%s', $i, $step_status_key, $user_id, $new_status );
			$verification_token     = substr( wp_hash( $token_key ),  -12, 10 );
			if ( hash_equals( $verification_token, $token ) ) {
				$is_valid = true;
				break;
			}
			$i--;
		}
		return $is_valid;
	}

	public function maybe_process_status_update( $form, $entry ){
		$feedback = false;
		$step_status_key = 'gravityflow_approval_new_status_step_' . $this->get_id();
		if ( isset( $_REQUEST[ $step_status_key ] ) || isset( $_GET['gworkflow_token'] ) || isset( $_GET['gflow_token'] ) ) {
			global $current_user;
			if ( isset( $_POST['_wpnonce'] ) && check_admin_referer( 'gravityflow_approvals_' . $this->get_id() ) ) {
				$new_status = rgpost( $step_status_key );
			} else {
				$gworkflow_token = rgget( 'gworkflow_token' );
				$gflow_token = rgget( 'gflow_token' );
				$new_status      = rgget( 'new_status' );

				if ( ! $gflow_token && ! $gworkflow_token ) {
					return false;
				}

				if ( $gflow_token ) {
					$token_json = base64_decode( $gflow_token );
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

				$valid_token_deprecated = $this->is_valid_token_deprecated( $gworkflow_token, $current_user->ID, $new_status );

				if ( ! ( $valid_token || $valid_token_deprecated ) ) {
					return false;
				}

			}

			$feedback = $this->process_status_update( $current_user->ID , $new_status, $form );

		}
		return $feedback;
	}

	public function process_status_update( $user_id, $new_status, $form ){
		$feedback = false;

		if ( ! in_array( $new_status, array( 'pending', 'approved', 'rejected' ) ) ) {
			return $feedback;
		}

		$current_user_status = $this->get_user_status( $user_id );

		$current_role_status = false;
		$role = false;
		foreach ( gravity_flow()->get_user_roles() as $role ) {
			$current_role_status = $this->get_role_status( $role );
			if ( $current_role_status == 'pending' ) {
				break;
			}
		}

		if ( $current_user_status != 'pending' && $current_role_status != 'pending' ) {
			return esc_html__( 'The status could not be changed because this step has already been processed.', 'gravityflow' );
		}

		if ( $current_user_status == 'pending' ) {
			$this->update_assignee_status( $user_id, 'user_id', $new_status );
		}

		if ( $current_role_status == 'pending' ) {
			$this->update_assignee_status( $role, 'role', $new_status );
		}

		$note = '';
		if ( $new_status == 'approved' ) {
			$note = $this->get_name() . ': ' . __( 'Approved.', 'gravityflow' );
			$this->send_approval_notification();

		} elseif ( $new_status == 'rejected' ) {
			$note = $this->get_name() . ': ' . __( 'Rejected.', 'gravityflow' );
			$this->send_rejection_notification();
		}
		if ( ! empty( $note ) ) {
			$user = get_user_by( 'id', $user_id );
			$note     = sprintf( $note, $user->display_name );
			$user_note = rgpost( 'gravityflow_note' );
			if ( ! empty( $user_note ) ) {
				$note .= sprintf( "\n%s: %s", __( 'Note', 'gravityflow' ), $user_note );
			}

			$this->add_note( $note );
		}

		// keep????
		$status = $this->get_status();
		$this->update_step_status( $status );
		$entry = $this->refresh_entry();


		GFAPI::send_notifications( $form, $entry, 'workflow_approval' );

		switch ( $new_status ) {
			case 'approved':
				$feedback = __( 'Entry Approved', 'gravityflow' );
				break;
			case 'rejected':
				$feedback = __( 'Entry Rejected', 'gravityflow' );
				break;
		}
		return $feedback;
	}

	public function workflow_detail_status_box( $form ){
		global $current_user;

		$entry = $this->get_entry();

		$status               = esc_html__( 'Pending Approval', 'gravityflow' );
		$approve_icon         = '<i class="fa fa-check" style="color:green"></i>';
		$reject_icon          = '<i class="fa fa-times" style="color:red"></i>';
		$approval_step_status = $this->is_complete( $entry );
		if ( $approval_step_status == 'approved' ) {
			$status = $approve_icon . ' Approved';
		} elseif ( $approval_step_status == 'rejected' ) {
			$status = $reject_icon . ' Rejected';
		} elseif ( $approval_step_status == 'queued' ) {
			$status = 'Queued';
		}
		?>

		<h4>
			<?php printf( '%s (%s)', $this->get_name(), $status ); ?>
		</h4>
		<div>
			<ul>
				<?php
				$assignees = $this->get_assignees();
				foreach ( $assignees as $assignee ) {
					$user_approval_status = $this->get_assignee_status( $assignee );
					$status_label = $this->get_status_label( $user_approval_status );
					if ( ! empty( $user_approval_status ) ) {
						list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee, 2 );
						if ( $assignee_type == 'assignee_field' ) {
							$entry       = $this->get_entry();
							$assignee_id = rgar( $entry, $assignee_id );
							list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee_id, 2 );
						} elseif ( $assignee_type == 'entry' ) {
							$entry       = $this->get_entry();
							$assignee_id = rgar( $entry, $assignee_id );
						}
						if ( $assignee_type == 'role' ) {
							$role_name = translate_user_role( $assignee_id );
							echo sprintf( '<li>%s: %s (%s)</li>', esc_html__( 'Role', 'gravityflow' ), $role_name,  $status_label );

						} else {
							$user = get_user_by( 'id', $assignee_id );

							echo sprintf( '<li>%s: %s (%s)</li>', esc_html__( 'User', 'gravityflow' ), $user->display_name,  $status_label );
						}
					}
				}
				?>
			</ul>
			<div>
				<?php
				$user_approval_status = $this->get_user_status( $current_user->ID );
				$role_approval_status = false;
				foreach ( gravity_flow()->get_user_roles() as $role ) {
					$role_approval_status = $this->get_role_status( $role );
					if ( $role_approval_status == 'pending' ) {
						break;
					}
				}

				if ( $user_approval_status == 'pending' || $role_approval_status == 'pending' ) {
					?>

					<?php
					wp_nonce_field( 'gravityflow_approvals_' . $this->get_id() );
					?>
					<br />
					<div>
						<label for="gravityflow-note"><?php esc_html_e( 'Note', 'gravityflow' ); ?></label>
					</div>

					<textarea id="gravityflow-note" style="width:100%;" rows="4" class="wide" name="gravityflow_note" ></textarea>
					<br /><br />
					<div style="text-align:right;">
						<button name="gravityflow_approval_new_status_step_<?php echo $this->get_id() ?>" value="approved" type="submit"
						        class="button">
							<?php echo $approve_icon; ?> <?php esc_html_e( 'Approve', 'gravityflow' ); ?>
						</button>
						<button name="gravityflow_approval_new_status_step_<?php echo $this->get_id() ?>" value="rejected" type="submit"
						        class="button">
							<?php echo $reject_icon; ?> <?php esc_html_e( 'Reject', 'gravityflow' ); ?>
						</button>
					</div>


				<?php
				}
				?>
			</div>
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
					$user_approval_status = $this->get_assignee_status( $assignee );
					if ( ! empty( $user_approval_status ) ) {
						list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee, 2 );
						if ( $assignee_type == 'assignee_field' ) {
							$entry       = $this->get_entry();
							$assignee_id = rgar( $entry, $assignee_id );
							list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee_id, 2 );
						} elseif ( $assignee_type == 'entry' ) {
							$entry       = $this->get_entry();
							$assignee_id = rgar( $entry, $assignee_id );
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

	public function send_approval_notification(){

		if ( ! $this->approval_notification_enabled ) {
			return;
		}

		$assignees = array();

		$notification_type = $this->approval_notification_type;

		switch ( $notification_type ) {
			case 'select' :
				$assignees = $this->approval_notification_users;
				break;
			case 'field' :
				$entry = $this->get_entry();
				$assignees = array( rgar( $entry, $this->approval_notification_user_field ) );
				break;
			case 'routing' :
				$routings = $this->approval_notification_routing;
				if ( is_array( $routings ) ) {
					foreach ( $routings as $routing ) {
						if ( $user_is_assignee = $this->evaluate_routing_rule( $routing ) ) {
							$assignees[] = rgar( $routing, 'assignee' );
						}
					}
				}

				break;
		}

		if ( empty( $assignees ) ) {
			return;
		}

		$body = $this->approval_notification_message;

		$this->send_notifications( $assignees, $body );

	}

	public function send_rejection_notification(){

		if ( ! $this->rejection_notification_enabled ) {
			return;
		}

		$assignees = array();

		$notification_type = $this->rejection_notification_type;

		switch ( $notification_type ) {
			case 'select' :
				$assignees = $this->rejection_notification_users;
				break;
			case 'field' :
				$entry = $this->get_entry();
				$assignees = array( rgar( $entry, $this->rejection_notification_user_field ) );
				break;
			case 'routing' :
				$routings = $this->rejection_notification_routing;
				if ( is_array( $routings ) ) {
					foreach ( $routings as $routing ) {
						if ( $user_is_assignee = $this->evaluate_routing_rule( $routing ) ) {
							$assignees[] = rgar( $routing, 'assignee' );
						}
					}
				}

				break;
		}

		if ( empty( $assignees ) ) {
			return;
		}

		$body = $this->rejection_notification_message;

		$this->send_notifications( $assignees, $body );

	}

	public function replace_variables($text, $user_id){
		$text = parent::replace_variables( $text, $user_id );
		$comment = rgpost( 'gravityflow_note' );
		$text = str_replace( '{workflow_note}', $comment, $text );

		$entry_id = $this->get_entry_id();
		$i = wp_nonce_tick();
		$timestamp = $this->get_timestamp();
		$sig_key = sprintf( '%s|%s|%s|%s|%s|%s', $i, $this->get_id(), $timestamp, $entry_id, $user_id, 'approved' );
		$sig     = substr( wp_hash( $sig_key ),  -12, 10 );
		$token_array = array(
			'sig'        => $sig,
			'step_id'    => $this->get_id(),
			'timestamp'  => $this->get_timestamp(),
			'user_id'    => $user_id,
			'entry_id' => $this->get_entry_id(),
			'new_status' => 'approved',
		);
		$token = base64_encode( json_encode( $token_array ) );

		$text = str_replace( '{workflow_approve_token}', $token, $text );

		$approve_url = sprintf( 'admin.php?page=gravityflow-inbox&view=entry&id=%d&lid=%d&gflow_token=%s', $this->get_form_id(), $this->get_entry_id(), $token );
		$approve_link = sprintf( '<a href="%s">%s</a>', admin_url( $approve_url ), esc_html__( 'Approve', 'gravityflow' ) );
		$text = str_replace( '{workflow_approve_link}', $approve_link, $text );

		$sig_key = sprintf( '%s|%s|%s|%s|%s|%s', $i, $this->get_id(), $timestamp, $entry_id, $user_id, 'rejected' );
		$sig     = substr( wp_hash( $sig_key ),  -12, 10 );
		$token_array = array(
			'sig'        => $sig,
			'step_id'    => $this->get_id(),
			'timestamp'  => $this->get_timestamp(),
			'user_id'    => $user_id,
			'entry_id' => $this->get_entry_id(),
			'new_status' => 'approved',
		);
		$token = base64_encode( json_encode( $token_array ) );

		$text = str_replace( '{workflow_reject_token}', $token, $text );

		$reject_url = sprintf( 'admin.php?page=gravityflow-inbox&view=entry&id=%d&lid=%d&gflow_token=%s', $this->get_form_id(), $this->get_entry_id(), $token );
		$reject_link = sprintf( '<a href="%s">%s</a>', admin_url( $reject_url ), esc_html__( 'Reject', 'gravityflow' ) );
		$text = str_replace( '{workflow_reject_link}', $reject_link, $text );
		return $text;
	}


}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Approval() );