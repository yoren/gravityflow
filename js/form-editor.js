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

function SetDefaultValues_workflow_discussion(field) {
	field.label = gravityflow_form_editor_js_strings.discussion.defaults.label;
}

function SetDiscussionTimestampFormat(format) {
	SetFieldProperty('gravityflowDiscussionTimestampFormat', format);
	RefreshSelectedFieldPreview();
}

jQuery(document).bind('gform_load_field_settings', function (event, field, form) {

	if (field.type == 'workflow_assignee_select') {

		jQuery('#gravityflow-assignee-field-show-users').prop('checked', field.gravityflowAssigneeFieldShowUsers ? true : false);
		jQuery('#gravityflow-assignee-field-show-roles').prop('checked', field.gravityflowAssigneeFieldShowRoles ? true : false);
		jQuery('#gravityflow-assignee-field-show-fields').prop('checked', field.gravityflowAssigneeFieldShowFields ? true : false);

	}

	if (field.type == 'workflow_discussion') {
		var timestamp_format = field.gravityflowDiscussionTimestampFormat == undefined ? '' : field.gravityflowDiscussionTimestampFormat;
		jQuery('#gravityflow_discussion_timestamp_format').val(timestamp_format);
	}

});

jQuery(document).ready(function () {

	// Allow admin-only field to be used in conditional logic.
	gform.addFilter('gform_is_conditional_logic_field', function (isConditionalLogicField, field) {
		if (field.adminOnly || field.visibility == 'administrative') {
			var inputType = field.inputType ? field.inputType : field.type,
				supported_fields = GetConditionalLogicFields(),
				index = jQuery.inArray(inputType, supported_fields);

			isConditionalLogicField = index >= 0 ? true : false;
		}
		return isConditionalLogicField;
	}, 20 );
});
