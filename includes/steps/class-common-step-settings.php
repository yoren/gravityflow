<?php
/**
 * Gravity Flow Common Step Settings Functions
 *
 * @package   GravityFlow
 * @copyright Copyright (c) 2015-2017, Steven Henty
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.5.1-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Common_Step_Settings {

	private $_account_choices = array();
	private $_gpdf_choices = array();

	public function __construct() {
		$this->_account_choices = gravity_flow()->get_users_as_choices();
		$this->set_gpdf_choices();
	}

	/**
	 * If Gravity PDF 4 is active prepare a choices array of active PDF feeds for the current form.
	 *
	 * @since 1.5.1-dev
	 */
	public function set_gpdf_choices() {
		if ( defined( 'PDF_EXTENDED_VERSION' ) && version_compare( PDF_EXTENDED_VERSION, '4.0-RC2', '>=' ) ) {
			$form_id    = rgget( 'id' );
			$gpdf_feeds = GPDFAPI::get_form_pdfs( $form_id );

			if ( ! is_wp_error( $gpdf_feeds ) ) {

				/* Format the PDFs in the appropriate format for use in a select field */
				foreach ( $gpdf_feeds as $gpdf_feed ) {
					if ( true === $gpdf_feed['active'] ) {
						$this->_gpdf_choices[] = array( 'label' => $gpdf_feed['name'], 'value' => $gpdf_feed['id'] );
					}
				}

			}
		}
	}

	/**
	 * Get the choices array for the type setting.
	 *
	 * @since 1.5.1-dev
	 *
	 * @return array
	 */
	public function get_type_choices() {
		return array(
			array( 'label' => __( 'Select', 'gravityflow' ), 'value' => 'select' ),
			array( 'label' => __( 'Conditional Routing', 'gravityflow' ), 'value' => 'routing' ),
		);
	}

	/**
	 * Get the notification "Send To" settings.
	 *
	 * @since 1.5.1-dev
	 *
	 * @param array $config The notification settings properties.
	 *
	 * @return array
	 */
	public function get_notification_send_to_fields( $config ) {
		if ( ! rgar( $config, 'send_to_enabled' ) ) {
			return array();
		}

		$type = rgar( $config, 'type' );

		return array(
			array(
				'name'          => $type . '_notification_type',
				'label'         => __( 'Send To', 'gravityflow' ),
				'type'          => 'radio',
				'default_value' => 'select',
				'horizontal'    => true,
				'choices'       => $this->get_type_choices(),
			),
			array(
				'id'       => $type . '_notification_users',
				'name'     => $type . '_notification_users[]',
				'label'    => __( 'Select', 'gravityflow' ),
				'size'     => '8',
				'multiple' => 'multiple',
				'type'     => 'select',
				'choices'  => $this->_account_choices,
			),
			array(
				'name'  => $type . '_notification_routing',
				'label' => __( 'Routing', 'gravityflow' ),
				'class' => 'large',
				'type'  => 'user_routing',
			)
		);
	}

	/**
	 * Get the common notification fields.
	 *
	 * @since 1.5.1-dev
	 *
	 * @param array $config The notification settings properties.
	 *
	 * @return array
	 */
	public function get_notification_common_fields( $config ) {
		$type = rgar( $config, 'type' );

		return array(
			array(
				'name'  => $type . '_notification_from_name',
				'label' => __( 'From Name', 'gravityflow' ),
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'type'  => 'text',
			),
			array(
				'name'          => $type . '_notification_from_email',
				'label'         => __( 'From Email', 'gravityflow' ),
				'class'         => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'type'          => 'text',
				'default_value' => '{admin_email}',
			),
			array(
				'name'  => $type . '_notification_reply_to',
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'Reply To', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'  => $type . '_notification_bcc',
				'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'BCC', 'gravityflow' ),
				'type'  => 'text',
			),
			array(
				'name'  => $type . '_notification_subject',
				'class' => 'fieldwidth-1 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
				'label' => __( 'Subject', 'gravityflow' ),
				'type'  => 'text',

			),
			array(
				'name'          => $type . '_notification_message',
				'label'         => __( 'Message', 'gravityflow' ),
				'type'          => 'visual_editor',
				'default_value' => rgar( $config, 'default_message' ),
			),
			array(
				'name'    => $type . '_notification_autoformat',
				'label'   => '',
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'label'         => __( 'Disable auto-formatting', 'gravityflow' ),
						'name'          => $type . '_notification_disable_autoformat',
						'default_value' => false,
						'tooltip'       => __( 'Disable auto-formatting to prevent paragraph breaks being automatically inserted when using HTML to create the email message.', 'gravityflow' ),
					),
				),
			),
		);
	}

	/**
	 * Get the resend notification field.
	 *
	 * @since 1.5.1-dev
	 *
	 * @param array $config The notification settings properties.
	 *
	 * @return array
	 */
	public function get_notification_resend_field( $config ) {
		if ( ! rgar( $config, 'resend_enabled' ) ) {
			return array();
		}

		return array(
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
	}

	/**
	 * Get the "Attach PDF" notification field.
	 *
	 * @since 1.5.1-dev
	 *
	 * @param array $config The notification settings properties.
	 *
	 * @return array
	 */
	public function get_notification_gpdf_field( $config ) {
		if ( empty( $this->_gpdf_choices ) ) {
			return array();
		}

		return array(
			array(
				'name'     => rgar( $config, 'type' ) . '_notification_gpdf',
				'label'    => '',
				'type'     => 'checkbox_and_select',
				'checkbox' => array(
					'label' => esc_html__( 'Attach PDF', 'gravityflow' ),
				),
				'select'   => array(
					'choices' => $this->_gpdf_choices,
				),
			)
		);
	}

	/**
	 * Get the properties for the fields which appear on one notification tab.
	 *
	 * @since 1.5.1-dev
	 *
	 * @param array $config The notification settings properties.
	 *
	 * @return array
	 */
	public function get_setting_notification( $config ) {
		$type = rgar( $config, 'type' );

		$fields = array_merge(
			array(
				array(
					'name'    => $type . '_notification_enabled',
					'label'   => '',
					'type'    => 'checkbox',
					'choices' => array(
						array(
							'label'         => rgar( $config, 'label' ),
							'tooltip'       => rgar( $config, 'tooltip' ),
							'name'          => $type . '_notification_enabled',
							'default_value' => false,
						),
					),
				),
			),
			$this->get_notification_send_to_fields( $config ),
			$this->get_notification_common_fields( $config ),
			$this->get_notification_resend_field( $config ),
			$this->get_notification_gpdf_field( $config )
		);

		return $fields;
	}

	/**
	 * Get the properties for the notification tabs setting.
	 *
	 * @since 1.5.1-dev
	 *
	 * @param array $tabs The properties for each tab.
	 *
	 * @return array
	 */
	public function get_setting_notification_tabs( $tabs ) {
		return array(
			'name'    => 'notification_tabs',
			'label'   => __( 'Emails', 'gravityflow' ),
			'tooltip' => __( 'Configure the emails that should be sent for this step.', 'gravityflow' ),
			'type'    => 'tabs',
			'tabs'    => $tabs,
		);
	}

	/**
	 * Get the properties for the assignee type field.
	 *
	 * @since 1.5.1-dev
	 *
	 * @return array
	 */
	public function get_setting_assignee_type() {
		return array(
			'name'          => 'type',
			'label'         => __( 'Assign To:', 'gravityflow' ),
			'type'          => 'radio',
			'default_value' => 'select',
			'horizontal'    => true,
			'choices'       => $this->get_type_choices(),
		);
	}

	/**
	 * Get the properties for the assignees field.
	 *
	 * @since 1.5.1-dev
	 *
	 * @return array
	 */
	public function get_setting_assignees() {
		return array(
			'id'       => 'assignees',
			'name'     => 'assignees[]',
			'tooltip'  => __( 'Users and roles fields will appear in this list. If the form contains any assignee fields they will also appear here. Click on an item to select it. The selected items will appear on the right. If you select a role then anybody from that role can approve.', 'gravityflow' ),
			'size'     => '8',
			'multiple' => 'multiple',
			'label'    => esc_html__( 'Select Assignees', 'gravityflow' ),
			'type'     => 'select',
			'choices'  => $this->_account_choices,
		);
	}

	/**
	 * Get the properties for the assignee routing field.
	 *
	 * @since 1.5.1-dev
	 *
	 * @return array
	 */
	public function get_setting_assignee_routing() {
		return array(
			'name'    => 'routing',
			'tooltip' => __( 'Build assignee routing rules by adding conditions. Users and roles fields will appear in the first drop-down field. If the form contains any assignee fields they will also appear here. Select the assignee and define the condition for that assignee. Add as many routing rules as you need.', 'gravityflow' ),
			'label'   => __( 'Routing', 'gravityflow' ),
			'type'    => 'routing',
		);
	}

	/**
	 * Get the properties for the instructions field.
	 *
	 * @since 1.5.1-dev
	 *
	 * @param string $default_value The default value to appear in the editor.
	 *
	 * @return array
	 */
	public function get_setting_instructions( $default_value ) {
		return array(
			'name'     => 'instructions',
			'label'    => __( 'Instructions', 'gravityflow' ),
			'type'     => 'checkbox_and_textarea',
			'tooltip'  => esc_html__( 'Activate this setting to display instructions to the user for the current step.', 'gravityflow' ),
			'checkbox' => array(
				'label' => esc_html__( 'Display instructions', 'gravityflow' ),
			),
			'textarea' => array(
				'use_editor'    => true,
				'default_value' => $default_value,
			),
		);
	}

	/**
	 * Get the properties for the display fields type field.
	 *
	 * @since 1.5.1-dev
	 *
	 * @return array
	 */
	public function get_setting_display_fields() {
		return array(
			'name'    => 'display_fields',
			'label'   => __( 'Display Fields', 'gravityflow' ),
			'tooltip' => __( 'Select the fields to hide or display.', 'gravityflow' ),
			'type'    => 'display_fields',
		);
	}
}