<?php
/**
 * Gravity Flow Step Feed Dropbox
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Dropbox
 * @copyright   Copyright (c) 2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.3-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Dropbox extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'dropbox';

	protected $_class_name = 'GFDropbox';

	public function get_label() {
		return esc_html__( 'Dropbox', 'gravityflow' );
	}

	public function get_icon_url() {
		return $this->get_base_url() . '/images/dropbox-icon.svg';
	}

	/**
	 * Process the feed; remove the feed from the processed feeds list;
	 * 
	 * @param array $feed The feed to be processed.
	 *
	 * @return bool Returning false to ensure the next step is not processed until after the files are uploaded.
	 */
	public function process_feed( $feed ) {
		$feed['meta']['workflow_step'] = $this->get_id();
		parent::process_feed( $feed );

		$processed_feeds = $this->get_processed_feeds();
		$dropbox_feeds   = $this->get_processed_dropbox_feeds( $processed_feeds );

		foreach ( $dropbox_feeds as $key => $feed_id ) {
			if ( $feed_id == $feed['id'] ) {
				unset( $dropbox_feeds[ $key ] );
				$processed_feeds[ $this->get_slug() ] = $dropbox_feeds;
				$this->update_processed_feeds( $processed_feeds );
				break;
			}
		}

		return false;
	}

	/**
	 * Evaluates the status for the step.
	 *
	 * The step is only complete after Gravity_Flow::dropbox_post_upload() has added all the feeds for this step back into the entry meta processed_feeds array.
	 *
	 * @return string 'pending' or 'complete'
	 */
	public function evaluate_status() {
		$status = $this->get_status();

		if ( empty( $status ) ) {
			return 'pending';
		}

		if ( $status == 'pending' ) {
			$dropbox_feeds = $this->get_processed_dropbox_feeds();
			$feeds         = $this->get_feeds();
			foreach ( $feeds as $feed ) {
				$setting_key = 'feed_' . $feed['id'];
				if ( $this->{$setting_key} && ! in_array( $feed['id'], $dropbox_feeds ) ) {
					return 'pending';
				}
			}
		}

		return 'complete';
	}

	/**
	 * Retrieve an array containing the IDs of all the feeds processed for the current entry.
	 *
	 * @return array
	 */
	public function get_processed_feeds() {
		$processed_feeds = gform_get_meta( $this->get_entry_id(), 'processed_feeds' );
		if ( empty( $processed_feeds ) ) {
			$processed_feeds = array();
		}

		return $processed_feeds;
	}


	/**
	 * Retrieve an array of dropbox feed IDs which have been processed for the current entry.
	 *
	 * @param bool|array $processed_feeds False if the array of processed feeds needs to be retrieved or the array to use.
	 *
	 * @return array
	 */
	public function get_processed_dropbox_feeds( $processed_feeds = false ) {
		if ( $processed_feeds === false ) {
			$processed_feeds = $this->get_processed_feeds();
		}

		$dropbox_feeds = rgar( $processed_feeds, $this->get_slug() );
		if ( empty( $dropbox_feeds ) ) {
			$dropbox_feeds = array();
		}

		return $dropbox_feeds;
	}

	/**
	 * Update the processed_feeds array for the current entry.
	 *
	 * @param array $processed_feeds The array to be stored in the entry meta.
	 */
	public function update_processed_feeds( $processed_feeds ) {
		gform_update_meta( $this->get_entry_id(), 'processed_feeds', $processed_feeds );
	}

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Dropbox() );
