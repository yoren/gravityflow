<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 *
 * Class Gravity_Flow_Field_Assignee_Select
 */
class Gravity_Flow_Field_Assignee_Select extends GF_Field_Select {

	public $type = 'workflow_assignee_select';

	public function is_conditional_logic_supported() {
		return false;
	}

	public function add_button( $field_groups ) {
		$field_groups = Gravity_Flow_Fields::maybe_add_workflow_field_group( $field_groups );

		return parent::add_button( $field_groups );
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

	public function get_form_editor_button() {
		return array(
			'group' => 'workflow_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	public function get_form_editor_field_title() {
		return __( 'Assignee', 'gravityflow' );
	}

	public function get_choices( $value ) {

		$include_users  = (bool) $this->gravityflowAssigneeFieldShowUsers;
		$include_roles  = (bool) $this->gravityflowAssigneeFieldShowRoles;
		$include_fields = (bool) $this->gravityflowAssigneeFieldShowFields;

		$choices = $this->get_assignees_as_choices( $value, $include_users, $include_roles, $include_fields );

		return $choices;
	}

	public function get_assignees_as_choices( $value, $include_users = true, $include_roles = true, $include_fields = true ) {
		$form_id         = $this->formId;
		$account_choices = $role_choices = $fields_choices = $optgroups = array();

		if ( $include_users ) {
			$args = array(
				'number'  => 1000,
				'orderby' => 'display_name',
				'role'    => $this->gravityflowUsersRoleFilter,
			);

			$args     = apply_filters( 'gravityflow_get_users_args_assignee_field', $args, $form_id, $this );
			$accounts = get_users( $args );
			foreach ( $accounts as $account ) {
				$account_choices[] = array( 'value' => 'user_id|' . $account->ID, 'text' => $account->display_name );
			}

			$account_choices = apply_filters( 'gravityflow_assignee_field_users', $account_choices, $form_id, $this );

			if ( ! empty( $account_choices ) ) {
				$users_opt_group          = new GF_Field();
				$users_opt_group->choices = $account_choices;

				$optgroups[] = array(
					'label'   => __( 'Users', 'gravityflow' ),
					'choices' => GFCommon::get_select_choices( $users_opt_group, $value ),
				);
			}
		}


		if ( $include_roles ) {
			$role_choices = Gravity_Flow_Common::get_roles_as_choices( true, true, true );
			$role_choices = apply_filters( 'gravityflow_assignee_field_roles', $role_choices, $form_id, $this );

			if ( ! empty( $role_choices ) ) {
				$roles_opt_group          = new GF_Field();
				$roles_opt_group->choices = $role_choices;

				$optgroups[] = array(
					'label'   => __( 'Roles', 'gravityflow' ),
					'key'     => 'roles',
					'choices' => GFCommon::get_select_choices( $roles_opt_group, $value ),
				);
			}
		}

		if ( $include_fields ) {
			$form_id = $this->formId;
			$form    = GFAPI::get_form( $form_id );
			if ( rgar( $form, 'requireLogin' ) ) {

				$fields_choices = array(
					array(
						'text'  => __( 'User (Created by)', 'gravityflow' ),
						'value' => 'entry|created_by',
					),
				);

				$fields_choices = apply_filters( 'gravityflow_assignee_field_fields', $fields_choices, $form_id, $this );

				if ( ! empty( $fields_choices ) ) {
					$fields_opt_group          = new GF_Field();
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
			$html     = sprintf( "<option value='' %s class='gf_placeholder'>%s</option>", $selected, esc_html( $this->placeholder ) );
		}

		foreach ( $optgroups as $optgroup ) {
			$html .= sprintf( '<optgroup label="%s">%s</optgroup>', $optgroup['label'], $optgroup['choices'] );
		}

		return $html;
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		$assignee = parent::get_value_entry_list( $value, $entry, $field_id, $columns, $form );
		$value    = $this->get_display_name( $assignee );

		return $value;
	}


	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		$value = $this->get_display_name( $value );

		return $value;
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		$assignee = parent::get_value_entry_detail( $value, $currency, $use_text, $format, $media );
		$value    = $this->get_display_name( $assignee );

		return $value;
	}

	public function get_display_name( $assignee ) {
		if ( empty( $assignee ) ) {
			return '';
		}
		list( $type, $value ) = explode( '|', $assignee, 2 );
		switch ( $type ) {
			case 'role' :
				$value = translate_user_role( $value );
				break;
			case 'user_id' :
				$user  = get_user_by( 'id', $value );
				$value = is_object( $user ) ? $user->display_name : $assignee;
		}

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

	public function sanitize_settings() {
		parent::sanitize_settings();
		if ( ! empty( $this->gravityflowUsersRoleFilter ) ) {
			$this->gravityflowUsersRoleFilter = wp_strip_all_tags( $this->gravityflowUsersRoleFilter );
		}

		$this->gravityflowAssigneeFieldShowUsers  = (bool) $this->gravityflowAssigneeFieldShowUsers;
		$this->gravityflowAssigneeFieldShowRoles  = (bool) $this->gravityflowAssigneeFieldShowRoles;
		$this->gravityflowAssigneeFieldShowFields = (bool) $this->gravityflowAssigneeFieldShowFields;
	}
}

GF_Fields::register( new Gravity_Flow_Field_Assignee_Select() );
