<?php
/**
 * Gravity_Flow_Step_Feed_Add_On
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step_Feed_Add_On
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */


if ( ! class_exists( 'GFForms' ) ) {
	die();
}
/**
 * Abstract class to be used for integration with Gravity Forms Feed Add-Ons.
 * Extend this class to integrate any Gravity Forms Add-On that is built using the Feed-Add-On Framework.
 *
 * Register your extending class using Gravity_Flow_Steps::register().
 * example:
 * Gravity_Flow_Steps::register( new Gravity_Flow_Step_My_Feed_Add_On_Step() )
 *
 * Class Gravity_Flow_Step_Feed_Add_On
 *
 * @since		1.0
 */
abstract class Gravity_Flow_Step_Feed_Add_On extends Gravity_Flow_Step{

	/**
	 * The name of the class used by the add-on. Example: GFMailChimp.
	 *
	 * @var string
	 */
	protected $_class_name = '';

	/**
	 * Returns the class name for the add-on.
	 *
	 * @return string
	 */
	public function get_feed_add_on_class_name() {
		return $this->_class_name;
	}

	/**
	 * Is this feed step supported on this server? Override to hide this step in the list of step types if the requirements are not met.
	 *
	 * @return bool
	 */
	public function is_supported() {

		$is_supported      = true;
		$feed_add_on_class = $this->get_feed_add_on_class_name();
		if ( ! class_exists( $feed_add_on_class ) ) {
			$is_supported = false;
		}

		return $is_supported;
	}

	/**
	 * Returns the settings for this step.
	 *
	 * @return array
	 */
	public function get_settings() {

		$fields = array();

		$feed_add_on_class = $this->get_feed_add_on_class_name();
		if ( ! class_exists( $feed_add_on_class ) ) {
			return $fields;
		}

		$feeds = $this->get_feeds();

		$feed_choices = array();
		foreach ( $feeds as $feed ) {
			if ( $feed['is_active'] ) {
				$label = $this->get_feed_label( $feed );

				$feed_choices[] = array(
					'label' => $label,
					'name'  => 'feed_' . $feed['id'],
				);
			}
		}

		if ( ! empty( $feed_choices ) ) {
			$fields[] = array(
				'name'     => 'feeds',
				'required' => true,
				'label'    => esc_html__( 'Feeds', 'gravityflow' ),
				'type'     => 'checkbox',
				'choices'  => $feed_choices,
			);
		}

		if ( empty( $fields ) ) {
			$html = esc_html__( "You don't have any feeds set up.", 'gravityflow' );
			$fields[] = array(
				'name'  => 'no_feeds',
				'label' => esc_html__( 'Feeds', 'gravityflow' ),
				'type'  => 'html',
				'html'  => $html,
			);
		}

		return array(
			'title'  => $this->get_label(),
			'fields' => $fields,
		);
	}


	/**
	 * Processes this step.
	 *
	 * @return bool Is the step complete?
	 */
	public function process() {

		$form = $this->get_form();
		$entry = $this->get_entry();

		$feeds = $this->get_feeds();
		foreach ( $feeds as $feed ) {
			$setting_key = 'feed_' . $feed['id'];
			if ( $this->{$setting_key} ) {
				if ( gravity_flow()->is_feed_condition_met( $feed, $form, $entry ) ) {

					$this->process_feed( $feed );
					$label = $this->get_feed_label( $feed );
					$note = sprintf( esc_html__( 'Feed processed: %s', 'gravityflow' ), $label );
					$this->add_note( $note, 0, $this->get_type() );
					gravity_flow()->log_debug( 'Feed processed' );
				} else {
					gravity_flow()->log_debug( 'Feed condition not met' );
				}
			}
		}
		return true;
	}

	/**
	 * Returns the feeds for the add-on.
	 *
	 * @return array|mixed
	 */
	public function get_feeds() {

		$form_id = $this->get_form_id();

		if ( class_exists( $this->get_feed_add_on_class_name() ) ) {
			/* @var GFFeedAddOn $add_on */
			$add_on = call_user_func( array( $this->get_feed_add_on_class_name(), 'get_instance' ) );

			$feeds = $add_on->get_feeds( $form_id );
		} else {
			$feeds = array();
		}

		return $feeds;
	}

	/**
	 * Processes the given feed for the add-on.
	 *
	 * @param $feed
	 */
	public function process_feed( $feed ) {

		$form = $this->get_form();
		$entry = $this->get_entry();

		$add_on = call_user_func( array( $this->get_feed_add_on_class_name(), 'get_instance' ) );
		$add_on->process_feed( $feed, $entry, $form );
	}

	/**
	 * Intercepts the form submission to prevent the default behaviour of the add-on.
	 */
	function intercept_submission() {
		$add_on = call_user_func( array( $this->get_feed_add_on_class_name(), 'get_instance' ) );
		remove_filter( 'gform_entry_post_save', array( $add_on, 'maybe_process_feed' ), 10 );
	}


	/**
	 * Returns the label of the given feed.
	 *
	 * @param $feed
	 *
	 * @return mixed
	 */
	function get_feed_label( $feed ) {
		$label = $feed['meta']['feedName'];
		return $label;
	}
}
