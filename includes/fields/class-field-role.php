<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Field_Role extends GF_Field_Select {

	public $type = 'workflow_role';

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
			'text'  => $this->get_form_editor_field_title()
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
		return __( 'Role', 'gravityflow' );
	}

	public function get_choices( $value ) {

		$choices = $this->get_roles_as_choices( $value );
		return $choices;
	}

	public function get_roles_as_choices( $value  ) {
		global $wp_roles;
		$form_id = $this->formId;

		$editable_roles = $wp_roles->roles;
		$role_choices = array();
		foreach ( $editable_roles as $role => $details ) {
			$name           = translate_user_role( $details['name'] );
			$role_choices[] = array( 'value' => $role, 'text' => $name );
		}

		$role_choices = apply_filters( 'gravityflow_role_field', $role_choices, $form_id, $this );

		$this->choices = $role_choices;
		$choices = GFCommon::get_select_choices( $this, $value );

		return $choices;
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		$assignee = parent::get_value_entry_list( $value, $entry, $field_id, $columns, $form );
		$value = $this->get_display_name( $assignee );

		return $value;
	}


	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		$value = $this->get_display_name( $value );

		return $value;
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		$assignee = parent::get_value_entry_detail( $value, $currency, $use_text, $format, $media );
		$value = $this->get_display_name( $assignee );

		return $value;
	}

	public function get_display_name( $value ){
		$value = translate_user_role( $value );

		return $value;
	}

}

GF_Fields::register( new Gravity_Flow_Field_Role() );