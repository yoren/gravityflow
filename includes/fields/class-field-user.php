<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Field_User extends GF_Field_Select {

	public $type = 'workflow_user';

	public function add_button( $field_groups ) {
		$field_groups = $this->maybe_add_workflow_field_group( $field_groups );

		return parent::add_button( $field_groups );
	}

	public function maybe_add_workflow_field_group( $field_groups ) {
		foreach ( $field_groups as $field_group ) {
			if ( $field_group['name'] == 'workflow_fields' ) {
				return $field_groups;
			}
		}
		$field_groups[] = array( 'name' => 'workflow_fields', 'label' => __( 'Workflow Fields', 'gravityforms' ), 'fields' => array() );
		return $field_groups;
	}

	public function get_form_editor_button() {
		return array(
			'group' => 'workflow_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'enable_enhanced_ui_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'size_setting',
			'rules_setting',
			'placeholder_setting',
			'default_value_setting',
			'visibility_setting',
			'duplicate_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function get_form_editor_field_title() {
		return __( 'User', 'gravityflow' );
	}

	public function get_choices( $value ) {

		$choices = $this->get_users_as_choices( $value );
		return $choices;
	}

	public function get_users_as_choices( $value ) {
		$form_id = $this->formId;

		$args            = apply_filters( 'gravityflow_get_users_args_user_field', array( 'orderby' => 'display_name' ), $form_id, $this );
		$accounts        = get_users( $args );
		$account_choices = array();
		foreach ( $accounts as $account ) {
			$account_choices[] = array( 'value' => $account->ID, 'text' => $account->display_name );
		}

		$account_choices = apply_filters( 'gravityflow_user_field', $account_choices, $form_id, $this );

		$this->choices = $account_choices;
		$choices = GFCommon::get_select_choices( $this, $value );

		return $choices;
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		$assignee = parent::get_value_entry_list( $value, $entry, $field_id, $columns, $form );
		$value = $this->get_display_name( $assignee );

		return $value;
	}


	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		switch ( $modifier ) {
			case 'id' :
				break;
			case 'email' :
			case 'user_email' :
				$user  = get_user_by( 'id', $value );
				$value = $user->user_email;
				break;
			case '' :
			case 'display_name' :
			default :
				$value = $this->get_display_name( $value );
		}
		return $value;
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		$assignee = parent::get_value_entry_detail( $value, $currency, $use_text, $format, $media );
		$value = $this->get_display_name( $assignee );

		return $value;
	}

	public function get_display_name( $user_id ) {
		if ( empty( $user_id ) ) {
			return '';
		}
		$user  = get_user_by( 'id', $user_id );
		$value = is_object( $user ) ? $user->display_name : $user_id;

		return $value;
	}

	/**
	 * Format the entry value before it is used in entry exports and by framework add-ons using GFAddOn::get_field_value().
	 *
	 * @param array $entry The entry currently being processed.
	 * @param string $input_id The field or input ID.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param bool|false $is_csv Is the value going to be used in the .csv entries export?
	 *
	 * @return string
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		return $this->get_display_name( rgar( $entry, $input_id ) );
	}
}

GF_Fields::register( new Gravity_Flow_Field_User() );
