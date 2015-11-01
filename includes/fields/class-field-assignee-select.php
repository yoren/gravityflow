<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Field_Assignee_Select extends GF_Field_Select {

	public $type = 'workflow_assignee_select';

	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
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
			'gravityflow_setting_assignees',
		);
	}

	public function get_form_editor_field_title() {
		return __( 'Assignee', 'gravityflow' );
	}

	public function get_choices( $value ) {

		$include_users = (bool) $this->gravityflowAssigneeFieldShowUsers;
		$include_roles = (bool) $this->gravityflowAssigneeFieldShowRoles;
		$include_fields = (bool) $this->gravityflowAssigneeFieldShowFields;

		$choices = $this->get_assignees_as_choices( $value, $include_users, $include_roles, $include_fields );
		return $choices;
	}

	public function get_assignees_as_choices( $value, $include_users = true, $include_roles = true, $include_fields = true  ) {
		global $wp_roles;

		$form_id = $this->formId;

		$optgroups = array();

		if ( $include_users ) {
			$args            = apply_filters( 'gravityflow_get_users_args_assignee_field', array( 'number' => 1000, 'orderby' => 'display_name' ) );
			$accounts        = get_users( $args );
			$account_choices = array();
			foreach ( $accounts as $account ) {
				$account_choices[] = array( 'value' => 'user_id|' . $account->ID, 'text' => $account->display_name );
			}

			$account_choices = apply_filters( 'gravityflow_assignee_field_users', $account_choices, $form_id, $this );

			$optgroups = array();

			if ( ! empty( $account_choices ) ) {
				$users_opt_group = new GF_Field();
				//$users_opt_group->placeholder = true;
				$users_opt_group->choices = $account_choices;

				$optgroups[] = array(
					'label'   => __( 'Users', 'gravityflow' ),
					'choices' => GFCommon::get_select_choices( $users_opt_group, $value ),
				);
			}
		}


		if ( $include_roles ) {
			$editable_roles = array_reverse( $wp_roles->roles );
			$role_choices = array();
			foreach ( $editable_roles as $role => $details ) {
				$name           = translate_user_role( $details['name'] );
				$role_choices[] = array( 'value' => 'role|' . $role, 'text' => $name );
			}

			$role_choices = apply_filters( 'gravityflow_assignee_field_roles', $role_choices, $form_id, $this );

			if ( ! empty( $role_choices ) ) {
				$roles_opt_group = new GF_Field();
				$roles_opt_group->choices = $role_choices;

				$optgroups[] = array(
					'label'   => __( 'Roles', 'gravityflow' ),
					'key' => 'roles',
					'choices' => GFCommon::get_select_choices( $roles_opt_group, $value ),
				);
			}
		}

		if ( $include_fields ) {
			$form_id = $this->formId;
			$form = GFAPI::get_form( $form_id );
			if ( rgar( $form, 'requireLogin' ) ) {

				$fields_choices = array(
					array(
						'text' => __( 'User (Created by)', 'gravityflow' ),
						'value' => 'entry|created_by',
					),
				);

				$fields_choices = apply_filters( 'gravityflow_assignee_field_fields', $fields_choices, $form_id, $this );

				if ( ! empty( $fields_choices ) ) {
					$fields_opt_group = new GF_Field();
					$fields_opt_group->choices = $fields_choices;

					$optgroups[] = array(
						'label'   => __( 'Fields', 'gravityflow' ),
						'choices' => GFCommon::get_select_choices( $fields_opt_group, $value ),
					);
				}
			}
		}

		$html = '';

		if ( ! empty( $this->placeholder ) ) {
			$selected = empty( $value ) ? "selected='selected'" : '';
			$html = sprintf( "<option value='' %s class='gf_placeholder'>%s</option>", $selected, esc_html( $this->placeholder ) );
		}

		foreach ( $optgroups as $optgroup ) {
			$html .= sprintf( '<optgroup label="%s">%s</optgroup>', $optgroup['label'], $optgroup['choices'] );
		}

		return $html;
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

	public function get_display_name( $assignee ){
		if ( empty( $assignee ) ) {
			return '';
		}
		list( $type, $value ) = explode( '|', $assignee, 2 );
		switch ( $type ) {
			case 'role' :
				$value = translate_user_role( $value );
				break;
			case 'user_id' :
				$user = get_user_by( 'id', $value );
				$value = $user->display_name;
		}

		return $value;
	}
}

GF_Fields::register( new Gravity_Flow_Field_Assignee_Select() );