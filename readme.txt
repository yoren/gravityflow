=== Gravity Flow ===
Contributors: stevehenty
Tags: workflow, approvals, gravity forms
Requires at least: 4.0
Tested up to: 4.3.1
License: GPL-3.0+
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Add workflow processes to your Gravity Forms.

== Description ==

Gravity Flow is a business process workflow platform for WordPress.


= Who is it for? =

Gravity Flow is for organisations and departments of any size that need to get a form-based workflow process up and running online quickly with no programming. These processes usually already exist either offline or online but are often inefficiently implemented.

= How does it work? =

An end-user submits a web form which generates an entry. The entry is then passed around between users and systems on an established path until the process is complete. Each user or system in the workflow will add something to the process before allowing the entry to proceed to the next step.

For example, an employee may add additional information and a manager another might add their approval. Connected systems might send an email, add the user to a mailing list, create a user account or send data to an ERP system.

Gravity Flow requires [Gravity Forms](https://gravityflow.io/out/gravityforms)

Facebook: [Gravity Flow](https://facebook.com/gravityflow.io)

Twitter: [Gravity Flow](https://twitter.com/gravityflowapp)

= Requirements =

1. [Purchase and install Gravity Forms](https://gravityflow.io/out/gravityforms)
2. Wordpress 4.2+
3. Gravity Forms 1.9.10+


= Support =

If you find any that needs fixing, or if you have any ideas for improvements, please get in touch:
https://gravityflow.io/contact/


== Installation ==

1.  Download the zipped file.
1.  Extract and upload the contents of the folder to /wp-contents/plugins/ folder
1.  Go to the Plugin management page of WordPress admin section and enable the 'Gravity Flow' plugin

== Frequently Asked Questions ==

= Which license of Gravity Forms do I need? =
Gravity Flow will work with any license of [Gravity Forms](https://gravityflow.io/out/gravityforms).

== ChangeLog ==

= 1.1.1.4 =
- Fixed an issue with the schedule step setting where the values are not retained after changing the step type.

= 1.1.1.3 =
- Fixed an issue with the assignee by month report where the axis labels were switched.
- Fixed an issue with the status export where the created_by column is missing for forms submitted by anonymous users.

= 1.1.1.2 =
- Fixed a compatibility issue with the Gravity Perks Limit Dates Perk.

= 1.1.1.1 =
- Updated the default number of users returned for settings and for the assignee field from 300 to 1000.

= 1.1.1 =
- Added the id_column attribute to the shortcode so the ID column can be hidden.
- Added the Restart Workflow bulk action to the status page.
- Added entries to the status page which were created before steps were added.
- Added support for custom status labels.
- Added support for custom navigation labels.
- Added support for the Signature Add-on in the shortcode.
- Added step icons to the step list.
- Updated the submit page to display the forms in alphabetical order.
- Fixed an issue with the assignee field where the placeholder doesn't work correctly.
- Fixed an issue with Gravity PDF integration in certain situations which prevents the PDF from attaching.
- Fixed an issue with the merge tags in the assignee reminder email.
- Fixed an issue with the assignee field where the number 1 appears at the top of the lists of users and fields.
- Removed the redundant 'workflow: notification step' event in the Gravity Forms notification settings.
- API: Added the Gravity_Flow_Extension class.


= 1.1 =
- Added one-click cancel links so workflows can be cancelled by clicking on a link in an email.
- Added export to the admin UI status list.
- Added support for SMS message steps via Twilio. Requires the Gravity Forms Twilio Add-On and a Twilio account.
- Added support for form import and export. Requires Gravity Forms 1.9.13.29.
- Updated the step type radio buttons to display as buttons with icons.
- Fixed an issue when updating step settings where where entries may not get reassigned correctly to new roles.
- Fixed an issue when duplicating forms where the next step points to another step.
- Fixed the merge tag UI for the Workflow Notification setting on the Notification step.
- Fixed an issue with the status permissions.
- Fixed some untranslatable strings.

= 1.0 =
- All New!