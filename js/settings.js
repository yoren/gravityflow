;(function (GravityFlowFeedSettings, $) {

	"use strict";

	$(document).ready(function () {
		
		$("form.oauth").submit(function() {
			var inputs = $(this).find("input.required");
			var error = false;
			$.each(inputs, function() {
				if ($(this).val() == "") {
					error = true;
					if ($(this).closest('p').find('p.error').length < 1) {
						$(this).before('<p class="error">Please fill in all required fields</p>');
					}
				}
				else {
					$(this).closest('p').find('p.error').hide().remove();
				}
			});
			if (error) {
				return false;
			}
		});
		$('#gflow_reauthorize_app').click(function(e) {
			e.preventDefault();
			console.log("CLICK");
			var secure = gravityflow_settings_js_strings.nonce;
			var app = $(this).data('app');
			$.post(gravityflow_settings_js_strings.ajaxurl, {security: secure, action: 'gravity_flow_reauth_app', app: app}, function(response) {
				console.log(response);
				if (response.success) {
					window.location.reload();
				}
			})
		})
	});
	
	
}(window.GravityFlowFeedSettings = window.GravityFlowFeedSettings || {}, jQuery));
