function SetDefaultValues_workflow_assignee_select(field) {

	field.gravityflowAssigneeFieldShowUsers = true;
	field.gravityflowAssigneeFieldShowRoles = true;
	field.gravityflowAssigneeFieldShowFields = true;

	return field;
}

function SetDefaultValues_workflow_user(field) {

	field.label = gravityflow_form_editor_js_strings.user.defaults.label;

	return field;
}

function SetDefaultValues_workflow_role(field) {

	field.label = gravityflow_form_editor_js_strings.role.defaults.label;

	return field;
}

jQuery(document).bind("gform_load_field_settings", function(event, field, form) {

	if (field.type == 'workflow_assignee_select') {

		jQuery('#gravityflow-assignee-field-show-users').prop('checked', field.gravityflowAssigneeFieldShowUsers ? true : false);
		jQuery('#gravityflow-assignee-field-show-roles').prop('checked', field.gravityflowAssigneeFieldShowRoles ? true : false);
		jQuery('#gravityflow-assignee-field-show-fields').prop('checked', field.gravityflowAssigneeFieldShowFields ? true : false);

	}
});