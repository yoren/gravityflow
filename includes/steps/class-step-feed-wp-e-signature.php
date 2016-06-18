<?php
/**
 * Gravity Flow Step Feed WP E-Signature
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Esign
 * @copyright   Copyright (c) 2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.3-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Esign extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'wp-e-signature';

	protected $_class_name = 'GFEsignAddOn';
	protected $_slug = 'esig-gf';
	protected $_feed_id;


	public function get_label() {
		return esc_html__( 'WP E-Signature', 'gravityflow' );
	}

	function get_feed_label( $feed ) {
		$sad_page_id    = rgars( $feed, 'meta/esig_gf_sad' );
		$sad            = new esig_sad_document();
		$document_id    = $sad->get_sad_id( $sad_page_id );
		$document_model = new WP_E_Document();
		$document       = $document_model->getDocument( $document_id );

		return $document->document_title;
	}

	public function get_icon_url() {
		return $this->get_base_url() . '/images/esig-icon.png';
	}

	public function get_settings() {
		$settings = parent::get_settings();

		if ( ! $this->is_supported() ) {
			return $settings;
		}

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
				'default_value' => __( 'A new document has been generated and requires a signature. Please check your Workflow Inbox.', 'gravityflow' ),
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

		$fields = array(
			array(
				'name'          => 'type',
				'label'         => __( 'Assign To:', 'gravityflow' ),
				'type'          => 'radio',
				'default_value' => 'select',
				'horizontal'    => true,
				'choices'       => array(
					array( 'label' => __( 'Select', 'gravityflow' ), 'value' => 'select' ),
					array( 'label' => __( 'Conditional Routing', 'gravityflow' ), 'value' => 'routing' ),
				),
			),
			array(
				'id'       => 'assignees',
				'name'     => 'assignees[]',
				'label'    => __( 'Select Assignee', 'gravityflow' ),
				'type'     => 'select',
				'multiple' => 'multiple',
				'choices'  => gravity_flow()->get_users_as_choices(),
			),
			array(
				'name'    => 'routing',
				'tooltip' => __( 'Build assignee routing rules by adding conditions. Users and roles fields will appear in the first drop-down field. If the form contains any assignee fields they will also appear here. Select the assignee and define the condition for that assignee. Add as many routing rules as you need.', 'gravityflow' ),
				'label'   => __( 'Routing', 'gravityflow' ),
				'type'    => 'routing',
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
					'default_value' => esc_html__( 'Instructions: check the signature invite status in the WP E-Signature section of the Workflow sidebar and resend if necessary.', 'gravityflow' ),
				),
			),
			array(
				'name'    => 'display_fields',
				'label'   => __( 'Display Fields', 'gravityflow' ),
				'tooltip' => __( 'Select the fields to hide or display.', 'gravityflow' ),
				'type'    => 'display_fields',
			),
		);

		$settings['fields'] = array_merge( $settings['fields'], $fields );

		return $settings;
	}


	function intercept_submission() {
		parent::intercept_submission();

		remove_filter( 'gform_confirmation', array( ESIG_GRAVITY_Admin::get_instance(), 'reroute_confirmation' ) );
	}

	public function process() {
		$complete = parent::process();
		$this->assign();

		return $complete;
	}

	public function process_feed( $feed ) {
		$this->_feed_id = $feed['id'];
		add_action( 'esig_sad_document_invite_send', array( $this, 'sad_document_invite_send' ) );
		$feed['meta']['esign_gf_logic'] = 'email';
		parent::process_feed( $feed );

		return false;
	}

	public function is_supported() {
		return parent::is_supported() && class_exists( 'esig_sad_document' ) && class_exists( 'WP_E_Document' );
	}

	/**
	 * Evaluates the status for the step.
	 *
	 * The step is only complete after gravity_flow_step_esign_document_complete() has added all the feeds for this step back into the entry meta processed_feeds array.
	 *
	 * @return string 'pending' or 'complete'
	 */
	public function evaluate_status() {
		$status = $this->get_status();

		if ( empty( $status ) ) {
			return 'pending';
		}

		if ( $status == 'pending' ) {
			$add_on_feeds = $this->get_processed_add_on_feeds();
			$feeds        = $this->get_feeds();
			foreach ( $feeds as $feed ) {
				$setting_key = 'feed_' . $feed['id'];
				if ( $this->{$setting_key} && ! in_array( $feed['id'], $add_on_feeds ) ) {
					return 'pending';
				}
			}
		}

		return 'complete';
	}

	/**
	 * Target of esig_sad_document_invite_send hook. Store the feed id which created this document in the WP E-Signature meta.
	 *
	 * @param array $args The properties related to the document which was saved.
	 */
	public function sad_document_invite_send( $args ) {
		if ( ! empty( $this->_feed_id ) && class_exists( 'WP_E_Meta' ) ) {
			$sig_meta_api = new WP_E_Meta();
			$sig_meta_api->add( $args['document']->document_id, 'esig_gravity_feed_id', $this->_feed_id );
			$this->save_document_id( $args['document']->document_id );
		}
	}

	/**
	 * Store the current document ID in the entry meta for this step.
	 *
	 * @param int $document_id The documents unique ID assigned by WP E-Signature.
	 */
	public function save_document_id( $document_id ) {
		$document_ids = $this->get_document_ids();
		if ( ! in_array( $document_id, $document_ids ) ) {
			$document_ids[] = $document_id;
		}

		gform_update_meta( $this->get_entry_id(), 'workflow_step_' . $this->get_id() . '_document_ids', $document_ids );
	}

	/**
	 * Retrieve this entries document IDs for the current step.
	 *
	 * @return array
	 */
	public function get_document_ids() {
		$document_ids = gform_get_meta( $this->get_entry_id(), 'workflow_step_' . $this->get_id() . '_document_ids' );
		if ( empty( $document_ids ) ) {
			$document_ids = array();
		}

		return $document_ids;
	}

	public function workflow_detail_box( $form, $args ) {
		$document_ids = $this->get_document_ids();

		if ( ! empty( $document_ids ) && class_exists( 'WP_E_Document' ) ) {
			echo sprintf( '<h4 style="margin-bottom:10px;">%s</h4>', $this->get_label() );

			$doc_api    = new WP_E_Document();
			$invite_api = new WP_E_Invite();

			global $current_user;
			$user_email = $current_user->user_email;
			if ( empty( $user_email ) ) {
				$assignee_key = gravity_flow()->get_current_user_assignee_key();
				list( $type, $user_id ) = rgexplode( '|', $assignee_key, 2 );
				$user_email = $type == 'email' ? $user_id : '';
			}

			foreach ( $document_ids as $document_id ) {
				$document = $doc_api->getDocument( $document_id );

				echo sprintf( '%s: %s<br><br>', esc_html__( 'Title', 'gravityflow' ), esc_html( $document->document_title ) );


				echo sprintf( '%s: %s', esc_html__( 'Invite Status', 'gravityflow' ), $invite_api->is_invite_sent( $document_id ) ? esc_html__( 'Sent', 'gravityflow' ) : esc_html__( 'Error - Not Sent', 'gravityflow' ) );
				echo '&nbsp;';
				$params     = array(
					'page'        => 'esign-resend_invite-document',
					'document_id' => $document_id,
				);
				$resend_url = add_query_arg( $params, admin_url() );
				echo sprintf( '&nbsp;-&nbsp;<a href="%s">%s</a><br><br>', esc_url( $resend_url ), esc_html__( 'Resend', 'gravityflow' ) );

				$text = '';

				if ( ! empty( $user_email ) ) {
					$invitations = $invite_api->getInvitations( $document_id );

					if ( $user_email == $invitations[0]->user_email ) {
						$url  = $invite_api->get_invite_url( $invitations[0]->invite_hash, $document->document_checksum );
						$text = esc_html__( 'Review &amp; Sign', 'gravityflow' );
					}
				}

				if ( empty( $url ) || empty( $text ) ) {
					$url  = $invite_api->get_preview_url( $document_id );
					$text = esc_html__( 'Preview', 'gravityflow' );
				}
				echo '<br /><div class="gravityflow-action-buttons">';
				echo sprintf( '<a href="%s" target="_blank" class="button button-large button-primary">%s</a><br><br>', $url, $text );
				echo '</div>';

			}
		}
	}

	public function get_assignees() {
		$assignees = $this->get_assignee_details();
		if ( ! empty( $assignees ) ) {
			return $assignees;
		}

		$type = $this->type;

		switch ( $type ) {
			case 'select' :
				if ( is_array( $this->assignees ) ) {
					foreach ( $this->assignees as $assignee_key ) {
						$this->maybe_add_assignee( $assignee_key );
					}
				}
				break;
			case 'routing' :
				$routings = $this->routing;
				if ( is_array( $routings ) ) {
					$entry = $this->get_entry();
					foreach ( $routings as $routing ) {
						$assignee_key = rgar( $routing, 'assignee' );
						if ( $entry ) {
							if ( $this->evaluate_routing_rule( $routing ) ) {
								$this->maybe_add_assignee( $assignee_key );
							}
						} else {
							$this->maybe_add_assignee( $assignee_key );
						}
					}
				}

				break;
		}

		return $this->get_assignee_details();
	}

	public function restart_action() {
		gform_delete_meta( $this->get_entry_id(), 'workflow_step_' . $this->get_id() . '_document_ids' );
	}

}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Esign() );

/**
 * Resume the workflow if the completed document originated from a feed processed by one of our steps.
 *
 * @param array $args The properties related to the completed document.
 */
function gravity_flow_step_esign_signature_saved( $args ) {
	if ( class_exists( 'WP_E_Meta' ) ) {
		$sig_meta_api = new WP_E_Meta();
		$entry_id     = $sig_meta_api->get( $args['invitation']->document_id, 'esig_gravity_entry_id' );
		$feed_id      = $sig_meta_api->get( $args['invitation']->document_id, 'esig_gravity_feed_id' );

		if ( $entry_id && $feed_id ) {
			$entry = GFAPI::get_entry( $entry_id );

			if ( ! is_wp_error( $entry ) && is_array( $entry ) && rgar( $entry, 'workflow_final_status' ) == 'pending' ) {
				$api = new Gravity_Flow_API( $entry['form_id'] );

				/* @var Gravity_Flow_Step_Feed_Esign $step */
				$step = $api->get_current_step( $entry );

				if ( $step ) {
					$feed  = gravity_flow()->get_feed( $feed_id );
					$label = $step->get_feed_label( $feed );
					$note  = sprintf( esc_html__( 'Document signed: %s', 'gravityflow' ), $label );
					$step->log_debug( __METHOD__ . '() - Feed processing complete: ' . $label );
					$step->add_note( $note, 0, $step->get_type() );

					$add_on_feeds = $step->get_processed_add_on_feeds( $entry_id );
					if ( ! in_array( $feed_id, $add_on_feeds ) ) {
						$add_on_feeds[] = $feed_id;
						$step->update_processed_feeds( $add_on_feeds, $entry_id );
						$form = GFAPI::get_form( $entry['form_id'] );
						gravity_flow()->process_workflow( $form, $entry_id );
					}
				}
			}
		}
	}
}

add_action( 'esig_signature_saved', 'gravity_flow_step_esign_signature_saved' );
