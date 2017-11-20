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
	 * Sends applicable notifications for the event which occurred.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param array $form The current form.
	 * @param array $entry The current entry.
	 * @param string $event The event that triggered the notifications.
	 */
	public static function send_notifications( $form, $entry, $event ) {
		if ( rgempty( 'notifications', $form ) || ! is_array( $form['notifications'] ) ) {
			return;
		}

		gravity_flow()->log_debug( sprintf( '%s(): Processing notifications for %s event for entry #%s (only active/applicable notifications are sent).', __METHOD__, $event, rgar( $entry, 'id' ) ) );

		foreach ( $form['notifications'] as $notification ) {
			if ( rgar( $notification, 'event' ) != $event ) {
				continue;
			}

			if ( gf_apply_filters( array( 'gform_disable_notification', $form['id'] ), false, $notification, $form, $entry ) ) {
				gravity_flow()->log_debug( sprintf( '%s(): Notification is disabled by gform_disable_notification hook, not processing notification (#%s - %s).', __METHOD__, rgar( $notification, 'id' ), rgar( $notification, 'name' ) ) );
				continue;
			}

			if ( rgempty( 'isActive', $notification ) ) {
				gravity_flow()->log_debug( sprintf( '%s(): Notification is inactive, not processing notification (#%s - %s).', __METHOD__, rgar( $notification, 'id' ), rgar( $notification, 'name' ) ) );
				continue;
			}

			if ( ! GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $entry ) ) {
				gravity_flow()->log_debug( sprintf( '%s(): Notification conditional logic not met, not processing notification (#%s - %s).', __METHOD__, rgar( $notification, 'id' ), rgar( $notification, 'name' ) ) );
				continue;
			}

			self::send_notification( $notification, $form, $entry );
		}
	}

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
		gravity_flow()->log_debug( sprintf( '%s(): Running for notification (#%s - %s).', __METHOD__, rgar( $notification, 'id' ), rgar( $notification, 'name' ) ) );

		$result = false;

		if ( $add_on = self::get_add_on_instance() ) {
			$add_on_slug = str_replace( 'gravityforms', '', $add_on->get_slug() );
			$feed        = self::get_feed( $notification, $add_on_slug );

			gravity_flow()->log_debug( sprintf( '%s(): Sending notification via the %s add-on.', __METHOD__, $add_on_slug ) );
			$add_on->log_debug( sprintf( '%s(): Sending notification (#%s - %s).', __METHOD__, rgar( $notification, 'id' ), rgar( $notification, 'name' ) ) );

			// Attempt to send the email using the email add-on.
			self::add_email_filter( $add_on_slug, $form['id'], $feed['id'] );
			$result = $add_on->process_feed( $feed, $entry, $form );
			self::remove_email_filter( $add_on_slug, $form['id'], $feed['id'] );

			gravity_flow()->log_debug( sprintf( '%s(): Result: %s', __METHOD__, var_export( (bool) $result, 1 ) ) );
		}

		if ( ! $result ) {
			// If an add-on was not available or sending by the add-on failed pass the notification to Gravity Forms for sending by wp_mail().
			gravity_flow()->log_debug( sprintf( '%s(): Sending notification via Gravity Forms and wp_mail().', __METHOD__ ) );
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
	 * @param array  $notification The notification properties.
	 * @param string $slug         The email add-on slug.
	 *
	 * @return array
	 */
	public static function get_feed( $notification, $slug ) {
		$feed = array(
			'id'          => rgar( $notification, 'id', '' ),
			'meta'        => array(
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
			'addon_slug'  => $slug,
			'attachments' => rgar( $notification, 'attachments' ),
		);

		return $feed;
	}

	/**
	 * Add the email filter for the current add-on.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param string $slug    The email add-on slug.
	 * @param int    $form_id The current form ID.
	 * @param string $feed_id The current feed ID.
	 */
	public static function add_email_filter( $slug, $form_id, $feed_id ) {
		add_filter( "gform_{$slug}_email_{$form_id}_{$feed_id}", array( __CLASS__, 'filter_email' ), 20, 2 );
	}

	/**
	 * Remove the email filter for the current add-on.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param string $slug    The email add-on slug.
	 * @param int    $form_id The current form ID.
	 * @param string $feed_id The current feed ID.
	 */
	public static function remove_email_filter( $slug, $form_id, $feed_id ) {
		remove_filter( "gform_{$slug}_email_{$form_id}_{$feed_id}", array( __CLASS__, 'filter_email' ), 20 );
	}

	/**
	 * Adds any attachments from the notification to the email during feed processing.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param array $email The email properties.
	 * @param array $feed  The current feed.
	 *
	 * @return array
	 */
	public static function filter_email( $email, $feed ) {
		$notification_attachments = rgar( $feed, 'attachments' );
		if ( ! is_array( $notification_attachments ) ) {
			return $email;
		}

		$key = self::get_attachment_key( $feed['addon_slug'] );

		if ( $key ) {
			gravity_flow()->log_debug( sprintf( '%s(): Attaching notification files.', __METHOD__ ) );
			$email[ $key ] = self::get_attachments( rgar( $email, $key ), $notification_attachments );
		}

		return $email;
	}

	/**
	 * Get the key to use when adding the attachments to the $email array.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param string $slug The email add-on slug.
	 *
	 * @return string
	 */
	public static function get_attachment_key( $slug ) {
		$keys = array(
			'mailgun'  => 'attachment',
			'postmark' => 'Attachments',
			'sendgrid' => 'attachments',
		);

		return rgar( $keys, $slug );
	}

	/**
	 * Get the attachments for the current email.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param array|null $email_attachments        The emails current attachments.
	 * @param array      $notification_attachments The notification attachments.
	 *
	 * @return array
	 */
	public static function get_attachments( $email_attachments, $notification_attachments ) {
		if ( ! is_array( $email_attachments ) ) {
			$email_attachments = array();
		}

		$add_on = self::get_add_on_instance();

		foreach ( $notification_attachments as $file_path ) {
			$email_attachments[] = $add_on->get_attachment( $file_path );
		}

		return $email_attachments;
	}

}