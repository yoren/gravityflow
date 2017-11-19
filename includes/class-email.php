<?php
/**
 * Gravity Flow Email Integrations
 *
 * @package   GravityFlow
 * @copyright Copyright (c) 2017, Steven Henty S.L.
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.9.2-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Email {

	/**
	 * Send the given notification.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param array $notification The notification properties.
	 * @param array $form         The current form.
	 * @param array $entry        The current entry.
	 */
	public static function send_notification( $notification, $form, $entry ) {
		$result = false;

		if ( $add_on = self::get_add_on_instance() ) {
			$feed = self::get_feed( $notification );

			// Attempt to send the email using the email add-on.
			$result = $add_on->process_feed( $feed, $entry, $form );
		}

		if ( ! $result ) {
			// If an add-on was not available or sending by the add-on failed pass the notification to Gravity Forms for sending by wp_mail().
			GFCommon::send_notification( $notification, $form, $entry );
		}
	}

	/**
	 * If a compatible email add-on is available get the instance.
	 *
	 * @since 1.9.2-dev
	 *
	 * @return GFFeedAddOn|false
	 */
	public static function get_add_on_instance() {
		$classes = self::get_add_on_classes();

		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				$add_on = call_user_func( array( $class, 'get_instance' ) );

				if ( $add_on instanceof GFFeedAddOn ) {
					return $add_on;
				}
			}
		}

		return false;
	}

	/**
	 * Get an array of email add-on class names.
	 *
	 * @since 1.9.2-dev
	 *
	 * @return array
	 */
	public static function get_add_on_classes() {
		return array(
			'GF_Mailgun',
			'GF_Postmark',
			'GF_SendGrid',
		);
	}

	/**
	 * Creates and returns the feed for the current notification.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param array $notification The notification properties.
	 *
	 * @return array
	 */
	public static function get_feed( $notification ) {
		$feed = array(
			'id'   => rgar( $notification, 'id' ),
			'meta' => array(
				'feedName'          => rgar( $notification, 'name' ),
				'sendTo'            => rgar( $notification, 'to' ),
				'fromName'          => rgar( $notification, 'fromName' ),
				'fromEmail'         => rgar( $notification, 'from' ),
				'replyTo'           => rgar( $notification, 'replyTo' ),
				'bcc'               => rgar( $notification, 'bcc' ),
				'subject'           => rgar( $notification, 'subject' ),
				'message'           => rgar( $notification, 'message' ),
				'disableAutoFormat' => rgar( $notification, 'disableAutoformat' ),
			),
		);

		return $feed;
	}

}