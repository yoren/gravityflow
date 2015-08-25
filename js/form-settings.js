(function (GravityFlowFeedSettings, $) {

    $(document).ready(function () {

        if(window['gformInitDatepicker']) {gformInitDatepicker();}

        $('#assignees, #editable_fields, #rejection_notification_users, #approval_notification_users, #workflow_notification_users').multiSelect();

        var gravityFlowIsDirty = false, gravityFlowSubmitted = false;

		$('form#gform-settings').submit(function(){
			gravityFlowSubmitted = true;
			$('form#gform-settings').find(':input').removeAttr('disabled');
		});

        $(':input').change(function () {
			gravityFlowIsDirty = true;
        });

        window.onbeforeunload = function(){
            if ( gravityFlowIsDirty && ! gravityFlowSubmitted) {
                return "You have unsaved changes.";
            }
        };

		var $stepType = $('input[name=_gaddon_setting_step_type]:checked');
		var selectedStepType = $stepType.val();

        setSubSettings();

        var $assigneeNotificationEnabled = $('#assignee_notification_enabled'),
            $assigneeNotificationMessage = $('#gaddon-setting-tab-field-assignee_notification_message');
        $assigneeNotificationEnabled.click(function () {
            $assigneeNotificationMessage.fadeToggle('normal');
            $('#gaddon-setting-tab-field-resend_assignee_email').fadeToggle();
            $('#gaddon-setting-tab-tab_assignee_notification i.gravityflow-tab-checked').toggle();
            $('#gaddon-setting-tab-tab_assignee_notification i.gravityflow-tab-unchecked').toggle();
        });
        var assigneeNotificationChecked = $assigneeNotificationEnabled.prop('checked');
        $assigneeNotificationMessage.toggle(assigneeNotificationChecked);
        $('#gaddon-setting-tab-field-resend_assignee_email').toggle( assigneeNotificationChecked );
        $('#gaddon-setting-tab-tab_assignee_notification i.gravityflow-tab-checked').toggle(assigneeNotificationChecked);
        $('#gaddon-setting-tab-tab_assignee_notification i.gravityflow-tab-unchecked').toggle(!assigneeNotificationChecked);

        var rejectionNotificationEnabled = $('#rejection_notification_enabled').prop('checked');
        toggleRejectionNotificationSettings(rejectionNotificationEnabled);
        $('#rejection_notification_enabled').click(function () {
            toggleRejectionNotificationSettings(this.checked);
        });

        var selectedType = $("input[name=_gaddon_setting_type]:checked");
        toggleType(selectedType.val());

        $('#gaddon-setting-row-type input[type=radio]').change(function () {
            toggleType(this.value);
        });


        $('#gaddon-setting-tab-field-rejection_notification_type input[type=radio]').click(function () {
            toggleRejectionNotificationType(this.value);

        });


        $('#gaddon-setting-tab-field-approval_notification_type input[type=radio]').click(function () {
            toggleApprovalNotificationType(this.value);
        });



        var approvalNotificationEnabled = $('#approval_notification_enabled').prop('checked');
        toggleApprovalNotificationSettings(approvalNotificationEnabled);
        $('#approval_notification_enabled').click(function () {
            toggleApprovalNotificationSettings(this.checked);
        });

		$('#gaddon-setting-row-workflow_notification_type input[type=radio]').click(function () {
			toggleWorkflowNotificationType(this.value);
		});

		var workflowNotificationEnabled = $('#workflow_notification_enabled').prop('checked');
		toggleWorkflowNotificationSettings(workflowNotificationEnabled);
		$('#workflow_notification_enabled').click(function () {
			toggleWorkflowNotificationSettings(this.checked);
		});

        GravityFlowFeedSettings.getUsersMarkup = function (propertyName) {
            var i, n, account,
                accounts = gf_routing_setting_strings['accounts'],
                str = '<select class="gform-routing-users ' + propertyName + '_{i}">';

            for (i = 0; i < accounts.length; i++) {
                account = accounts[i];
                if (typeof account.choices != 'undefined') {
                    var optgroup = '', choice;
                    for (n = 0; n < account.choices.length; n++) {
                        choice = account.choices[n];
                        optgroup += '<option value="{0}">{1}</option>'.format(choice.value, choice.label);
                    }
                    str += '<optgroup label="{0}">{1}</option>'.format(account.label, optgroup);

                } else {
                    str += '<option value="{0}">{1}</option>'.format(account.value, account.label);
                }

            }

            str += "</select>";
            return str;
        };

        var $routingSetting = $('#gform_routing_setting');

        var json = $('#routing').val();

        var routing_items = json ? $.parseJSON(json) : null;

		var options;
		if ( selectedStepType == 'approval' ) {
			if (!routing_items) {
				routing_items = [{
					assignee: gf_routing_setting_strings['accounts'][0]['choices'][0]['value'],
					fieldId: '0',
					operator: 'is',
					value: '',
					type: '',
				}];
				$('#routing').val($.toJSON(routing_items));
			}

			options = {
				fieldName: $routingSetting.data('field_name'),
				fieldId: $routingSetting.data('field_id'),
				settings: gf_routing_setting_strings['fields'],
				accounts: gf_routing_setting_strings['accounts'],
				imagesURL: gf_vars.baseUrl + "/images",
				items: routing_items,
				callbacks: {
					addNewTarget: function (obj, target) {
						var str = GravityFlowFeedSettings.getUsersMarkup('assignee');
						return str;
					}
				}
			};

		} else if ( selectedStepType == 'user_input' ) {
			if (!routing_items) {
				routing_items = [{
					assignee: gf_routing_setting_strings['accounts'][0]['choices'][0]['value'],
					editable_fields: [gf_routing_setting_strings['input_fields'][0]['key']],
					fieldId: '0',
					operator: 'is',
					value: '',
					type: '',
				}];
				$('#user_input_routing').val($.toJSON(routing_items));
			}

			options = {
				fieldName: $routingSetting.data('field_name'),
				fieldId: $routingSetting.data('field_id'),
				settings: gf_routing_setting_strings['fields'],
				accounts: gf_routing_setting_strings['accounts'],
				imagesURL: gf_vars.baseUrl + "/images",
				items: routing_items,
				callbacks: {
					addNewTarget: function (obj, target) {

						var str = GravityFlowFeedSettings.getUsersMarkup('assignee');

						var $fields = $('#editable_fields').clone();
						$fields.attr('name', 'editable_fields');
						var id = $('#gform-routings tbody tr').length;
						$fields.attr('id', 'editable_fields_routing_{i}');
						$fields.attr('style', '');
						$fields.addClass('gform-routing-input-field editable_fields_{i}');

						str += '</td><td>' + $fields[0].outerHTML;
						return str;
					},
					header: function (obj, header) {
						return '<thead><tr><th>Assign To</th><th>Editable Fields</th><th colspan="3">Condition</th></tr></thead>';
					}
				}
			};
		}

        $routingSetting.gfRoutingSetting(options);

        // Rejection Notification Routing

        var $rejectionNotificationRoutingSetting = $('#gform_user_routing_setting_rejection_notification_routing');

        var rejectionNotificationRoutingJSON = $('#rejection_notification_routing').val();

        var rejection_notification_routing_items = rejectionNotificationRoutingJSON ? $.parseJSON(rejectionNotificationRoutingJSON) : null;

        if (!rejection_notification_routing_items) {
            rejection_notification_routing_items = [{
                assignee: gf_routing_setting_strings['accounts'][0]['choices'][0]['value'],
                fieldId: '0',
                operator: 'is',
                value: '',
                type: '',
            }];
            $('#approval_rejection_notification_routing').val($.toJSON(rejection_notification_routing_items));
        }

        var rejectionNotificationOptions = {
            fieldName: $rejectionNotificationRoutingSetting.data('field_name'),
            fieldId: $rejectionNotificationRoutingSetting.data('field_id'),
            settings: gf_routing_setting_strings['fields'],
            accounts: gf_routing_setting_strings['accounts'],
            imagesURL: gf_vars.baseUrl + "/images",
            items: rejection_notification_routing_items,
            callbacks: {
                addNewTarget: function (obj, target) {
                    var str = GravityFlowFeedSettings.getUsersMarkup('assignee');
                    return str;
                }
            }
        };

        $rejectionNotificationRoutingSetting.gfRoutingSetting(rejectionNotificationOptions);

        // Approval Notification Routing

        var $approvalNotificationRoutingSetting = $('#gform_user_routing_setting_approval_notification_routing');

        var approvalNotificationRoutingJSON = $('#approval_notification_routing').val();

        var approval_notification_routing_items = approvalNotificationRoutingJSON ? $.parseJSON(approvalNotificationRoutingJSON) : null;

        if (!approval_notification_routing_items) {
            approval_notification_routing_items = [{
                assignee: gf_routing_setting_strings['accounts'][0]['choices'][0]['value'],
                fieldId: '0',
                operator: 'is',
                value: '',
                type: '',
            }];
            $('#approval_notification_routing').val($.toJSON(approval_notification_routing_items));
        }

        var approvalNotificationOptions = {
            fieldName: $approvalNotificationRoutingSetting.data('field_name'),
            fieldId: $approvalNotificationRoutingSetting.data('field_id'),
            settings: gf_routing_setting_strings['fields'],
            accounts: gf_routing_setting_strings['accounts'],
            imagesURL: gf_vars.baseUrl + "/images",
            items: approval_notification_routing_items,
            callbacks: {
                addNewTarget: function (obj, target) {
                    var str = GravityFlowFeedSettings.getUsersMarkup('assignee');
                    return str;
                }
            }
        };

        $approvalNotificationRoutingSetting.gfRoutingSetting(approvalNotificationOptions);


		// Workflow Notification Routing

		var $workflowNotificationRoutingSetting = $('#gform_user_routing_setting_workflow_notification_routing');

		var workflowNotificationRoutingJSON = $('#workflow_notification_routing').val();

		var workflow_notification_routing_items = workflowNotificationRoutingJSON ? $.parseJSON(workflowNotificationRoutingJSON) : null;

		if (!workflow_notification_routing_items) {
			workflow_notification_routing_items = [{
				assignee: gf_routing_setting_strings['accounts'][0]['choices'][0]['value'],
				fieldId: '0',
				operator: 'is',
				value: '',
				type: '',
			}];
			$('#workflow_notification_routing').val($.toJSON(workflow_notification_routing_items));
		}

		var workflowNotificationOptions = {
			fieldName: $workflowNotificationRoutingSetting.data('field_name'),
			fieldId: $workflowNotificationRoutingSetting.data('field_id'),
			settings: gf_routing_setting_strings['fields'],
			accounts: gf_routing_setting_strings['accounts'],
			imagesURL: gf_vars.baseUrl + "/images",
			items: workflow_notification_routing_items,
			callbacks: {
				addNewTarget: function (obj, target) {
					var str = GravityFlowFeedSettings.getUsersMarkup('assignee');
					return str;
				}
			}
		};

		$workflowNotificationRoutingSetting.gfRoutingSetting(workflowNotificationOptions);

		//-----

		if (window.gform) {
            gform.addFilter('gform_merge_tags', GravityFlowFeedSettings.gravityflow_add_merge_tags);
        }

        if(window['gformInitDatepicker']) {gformInitDatepicker();}

        loadMessages();

    });

    function toggleRejectionNotificationType(showType) {
        var fields = {
            select: ['rejection_notification_users', 'rejection_notification_message'],
            routing: ['rejection_notification_routing', 'rejection_notification_message']
        };
        toggleFields(fields, showType, true);
    }

    function toggleApprovalNotificationType(showType) {
        var fields = {
            select: ['approval_notification_users', 'approval_notification_message'],
            routing: ['approval_notification_routing', 'approval_notification_message']
        };
        toggleFields(fields, showType, true);
    }

	function toggleWorkflowNotificationType(showType) {
		var fields = {
			select: ['workflow_notification_users\\[\\]', 'workflow_notification_message'],
			routing: ['workflow_notification_routing', 'workflow_notification_message']
		};
		toggleFields(fields, showType, false);
	}

    function toggleType(showType) {
        var fields = {
            select: ['assignees\\[\\]', 'editable_fields\\[\\]', 'unanimous_approval', 'assignee_policy'],
            routing: ['routing']
        };

        toggleFields(fields, showType);
    }

    function toggleFields(fields, showType, isTab) {
        var prefix = isTab ? '#gaddon-setting-tab-field-' : '#gaddon-setting-row-';
        $.each(fields, function (type, activeFields) {
            $.each(activeFields, function (i, activeField) {
                $(prefix + activeField).hide();
            });
        });

        $.each(fields, function (type, activeFields) {
            if (showType == type) {
                $.each(activeFields, function (i, activeField) {
                    $(prefix + activeField).fadeToggle('normal');
                });
            }
        });
    }

    function toggleRejectionNotificationSettings(enabled) {
        var $rejectionNotificationType = $('#gaddon-setting-tab-field-rejection_notification_type');
        $rejectionNotificationType.toggle(enabled);
        if (enabled) {
            var selected = $rejectionNotificationType.find('input[type=radio]:checked').val();
            toggleRejectionNotificationType(selected);
            $('#gaddon-setting-tab-tab_rejection_notification i.gravityflow-tab-checked').show();
            $('#gaddon-setting-tab-tab_rejection_notification i.gravityflow-tab-unchecked').hide();
        } else {
            toggleRejectionNotificationType('off');
            $('#gaddon-setting-tab-tab_rejection_notification i.gravityflow-tab-checked').hide();
            $('#gaddon-setting-tab-tab_rejection_notification i.gravityflow-tab-unchecked').show();
        }
    }

    function toggleApprovalNotificationSettings(enabled) {
        var $approvalNotificationType = $('#gaddon-setting-tab-field-approval_notification_type');
        $approvalNotificationType.toggle(enabled);
        if (enabled) {
            var selected = $approvalNotificationType.find('input[type=radio]:checked').val();
            toggleApprovalNotificationType(selected);
            $('#gaddon-setting-tab-tab_approval_notification i.gravityflow-tab-checked').show();
            $('#gaddon-setting-tab-tab_approval_notification i.gravityflow-tab-unchecked').hide();
        } else {
            toggleApprovalNotificationType('off');
            $('#gaddon-setting-tab-tab_approval_notification i.gravityflow-tab-checked').hide();
            $('#gaddon-setting-tab-tab_approval_notification i.gravityflow-tab-unchecked').show();
        }
    }

	function toggleWorkflowNotificationSettings(enabled) {
		var $workflowNotificationType = $('#gaddon-setting-row-workflow_notification_type');
		$workflowNotificationType.toggle(enabled);
		if (enabled) {
			var selected = $workflowNotificationType.find('input[type=radio]:checked').val();
			toggleWorkflowNotificationType(selected);
		} else {
			toggleWorkflowNotificationType('off');
		}
	}

    function setSubSettings() {
        var subSettings = [
            'routing',
            'assignees\\[\\]',
            'unanimous_approval',
            'assignee_notification_message',
            'resend_assignee_email',
            'rejection_notification_type',
            'rejection_notification_users\\[\\]',
            'rejection_notification_user_field',
            'rejection_notification_routing',
            'rejection_notification_message',
            'approval_notification_type',
            'approval_notification_users\\[\\]',
            'approval_notification_user_field',
            'approval_notification_routing',
            'approval_notification_message',

			'workflow_notification_type',
			'workflow_notification_users\\[\\]',
			'workflow_notification_user_field',
			'workflow_notification_routing',
			'workflow_notification_message',

            'assignees\\[\\]',
            'editable_fields\\[\\]',
            'routing',
            'assignee_policy',
            'assignee_notification_message',

        ];
        for (var i = 0; i < subSettings.length; i++) {
            $('#gaddon-setting-row-' + subSettings[i]).addClass('gravityflow_sub_setting');
        }
    }

    GravityFlowFeedSettings.gravityflow_add_merge_tags = function (mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option) {
        if (isPrepop) {
            return mergeTags;
        }

        addNoteMergeTag(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option);
        addAprovalMergeTags(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option);

        return mergeTags;
    };

    function addNoteMergeTag(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option) {

        var supportedElementIds = [
            '_gaddon_setting_assignee_notification_message',
            '_gaddon_setting_approval_notification_message',
            '_gaddon_setting_rejection_notification_message',
        ];

        if (supportedElementIds.indexOf(elementId) < 0) {
            return mergeTags;
        }

        var tags = [];
        tags.push({tag: '{workflow_entry_link}', label: 'Entry Link'});
        tags.push({tag: '{workflow_entry_url}', label: 'Entry URL'});
        tags.push({tag: '{workflow_note}', label: 'Note'});

        mergeTags['gravityflow'] = {
            label: 'Workflow',
            tags: tags
        };

        return mergeTags;

    }

    function addAprovalMergeTags(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option) {
        var supportedElementIds = [
            '_gaddon_setting_assignee_notification_message',
        ];

        if (supportedElementIds.indexOf(elementId) < 0) {
            return mergeTags;
        }

        var tags = [];
        tags.push({tag: '{workflow_entry_link}', label: 'Entry Link'});
        tags.push({tag: '{workflow_entry_url}', label: 'Entry URL'});
        tags.push({tag: '{workflow_approve_link}', label: 'Approve Link'});
        tags.push({tag: '{workflow_reject_link}', label: 'Reject Link'});
        tags.push({tag: '{workflow_approve_token}', label: 'Approve Token'});
        tags.push({tag: '{workflow_reject_token}', label: 'Reject Token'});

        mergeTags['gravityflow'] = {
            label: 'Workflow',
            tags: tags
        };

        return mergeTags;
    }

	function loadMessages(){
		var feedId = gravityflow_form_settings_js_strings['feedId'];
		if ( feedId > 0 ) {
			var url = ajaxurl + '?action=gravityflow_feed_message&fid=' + feedId + '&id=' + gravityflow_form_settings_js_strings['formId'];
			$.get( url, function(response){
				var $heading = $('#save_button');
				$heading.before(response);
			} );
		}

	}

}(window.GravityFlowFeedSettings = window.GravityFlowFeedSettings || {}, jQuery));


