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
			$feed        = self::get_feed( $notification );
			$add_on_slug = str_replace( 'gravityforms', '', $add_on->get_slug() );

			// Attempt to send the email using the email add-on.
			self::add_email_filter( $add_on_slug, $form['id'], $feed['id'] );
			$result = $add_on->process_feed( $feed, $entry, $form );
			self::remove_email_filter( $add_on_slug, $form['id'], $feed['id'] );
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

		$filter_parts = explode( '_', current_filter() );
		$add_on_slug  = $filter_parts[1];

		switch ( $add_on_slug ) {
			case 'mailgun' :
				$email['attachment'] = self::get_attachments( rgar( $email, 'attachment' ), $notification_attachments );
				break;

			case 'postmark' :
				$email['Attachments'] = self::get_attachments( rgar( $email, 'Attachments' ), $notification_attachments );
				break;

			case 'sendgrid' :
				$email['attachments'] = self::get_attachments( rgar( $email, 'attachments' ), $notification_attachments );
				break;
		}

		return $email;
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