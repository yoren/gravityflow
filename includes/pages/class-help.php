<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Help {
	public static function display(){

		?>
		<div class="wrap gf_entry_wrap">

			<h2 class="gf_admin_page_title">
				<span><?php esc_html_e( 'Help', 'gravityflow' ); ?></span>
			</h2>

			<h3>
				What is Gravity Flow?
			</h3>

			<p>
				Gravity Flow is an add-on for Gravity Forms which allows form administrators to build
				sophisticated form-based workflow processes in WordPress.
			</p>
			<h3>
				Where do I start?
			</h3>
			<p>
				First, plan your workflow. If you do this well, the rest will be straightforward. I can't stress this enough - if you don't plan, you'll very likely find the whole experience very frustrating. List the users or roles involved and identify what each one needs to do and at which stage. Try to separate the process into distinct steps involving approval and user input.
				You may find it useful to draw the process using a <a href="http://en.wikipedia.org/wiki/Swim_lane" target="_blank">swim lane diagram</a>.
			</p>
			<p>
				Create a form to use for your workflow. This form must contain all the fields used at every step of the process but don't worry if you need to add fields later.
				If you want to hide some fields on initial form that the process initiator completes, then make them admin only (advanced field settings)
				or hide them in a section hidden by conditional logic.
			</p>
			<p>
				Add Workflow steps in the form settings. There currently two types of steps.
			</p>
			<ol>
				<li>
					Approval: Assigns the form to users to request their approval.
				</li>
				<li>
					User input: Assign the form to users to request their input.
				</li>
			</ol>
			<p>
				Add as many steps as you need to implement your workflow. As soon as one step is complete, by default, it will be followed by the next.
			</p>
			<p>
				The <b>inbox</b> displays the pending tasks for the current user. So this is where approval and user input assignees will find the links to forms pending their action.
			</p>
			<p>
				The <b>submit</b> page displays all the published workflow forms. To publish a form on the submit page go to settings and select the form.
			</p>
			<p>
				The <b>status</b> page, by default, displays all the forms submitted by the current user.
			</p>
			<h3>
				Is it possible to implement approval loops?
			</h3>
			<p>
				Yes. You can implement an approval loop which will allow approvers to send the form back a step so the previous user can modify values on the form.
				Create a user input step followed by an approval step. In the approval step settings select the user input step in the setting for 'Next step if Rejected'.
			</p>
			<h3>
				Is it possible to implement branches?
			</h3>
			<p>
				Yes. Use the 'Condition' setting to define the branching rule and then send the flow to a later step by selecting it in the 'Next Step' setting.
				Forms that skip that step will go on to the next step.
			</p>
			<h3>
				Can I allow the form submitter to select the step assignee?
			</h3>
			<p>
				Yes. Add an assignee field to the form and select it in the 'Assign to' setting.
			</p>
			<h3>
				Can I assign steps and send notifications to the form submitter?
			</h3>
			<p>
				Yes. The form submitter must have a WordPress user account. If you don't see the 'User (Created by)' field in the 'Assign to' setting, go to form settings and activate the 'Require user to be logged in' setting in the 'Restrictions' section of the form settings.
			</p>
			<h3>
				So how do I send notifications to anonymous form submitters?
			</h3>
			<p>
				Create a Gravity Forms notification and select the workflow step from the event setting.
			</p>
			<h3>
				How do I control permissions?
			</h3>
			<p>
				Gravity Flow uses the WordPress system of Roles and Capabilities. Use the <a href="https://wordpress.org/plugins/members/" target="_blank" />members plugin</a> to edit the capabilities for each role.
			</p>
			<p>
				The follow capabilities are available to assign to WordPress Roles.
			</p>
			<ol>
				<li>
					gravityflow_uninstall - allows the user to uninstall Gravity Flow.
				</li>
				<li>
					gravityflow_settings - allows the user to edit the Gravity Flow settings.
				</li>
				<li>
					gravityflow_create_steps - allows the user to create Workflow steps in the Form Settings.
				</li>
				<li>
					gravityflow_submit - allows the user to view the submit page.
				</li>
				<li>
					gravityflow_inbox - allows the user to view the inbox page.
				</li>
				<li>
					gravityflow_status - allows the user to view the status page.
				</li>
				<li>
					gravityflow_status_view_all - allows the user to view the status of all the workflows from all users on the status page.
				</li>
			</ol>
			<h3>
				Where can I find information about the action and filter hooks?
			</h3>
			<p>
				Check the readme.md file for developer info.
			</p>
			<h3>
				This help page hasn't helped me at all.
			</h3>
			<p>
				Please check the <a href="http://docs.gravityflow.io" >online documentation</a> and if you still can't find the answer to your question please complete the form on the beta support page.
			</p>
		</div>
	<?php
	}
}