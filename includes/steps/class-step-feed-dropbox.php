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

		return false;
	}

	/**
	 * Evaluates the status for the step.
	 *
	 * The step is only complete after gravity_flow_step_dropbox_post_upload() has added all the feeds for this step back into the entry meta processed_feeds array.
	 *
	 * @return string 'pending' or 'complete'
	 */
	public function evaluate_status() {
		$status = $this->get_status();

		if ( empty( $status ) ) {
			return 'pending';
		}

		if ( $status == 'pending' ) {
			$add_on_feeds = $this->get_processed_add_on_feeds();
			$feeds        = $this->get_feeds();
			foreach ( $feeds as $feed ) {
				$setting_key = 'feed_' . $feed['id'];
				if ( $this->{$setting_key} && ! in_array( $feed['id'], $add_on_feeds ) ) {
					return 'pending';
				}
			}
		}

		return 'complete';
	}

}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Dropbox() );

/**
 * If the feed for a Dropbox step was processed maybe resume the workflow.
 *
 * @param array $feed The Dropbox feed for which uploading has just completed.
 * @param array $entry The entry which was processed.
 * @param array $form The form object for this entry.
 */
function gravity_flow_step_dropbox_post_upload( $feed, $entry, $form ) {
	$workflow_is_pending = rgar( $entry, 'workflow_final_status' ) == 'pending';
	$feed_step_id        = rgar( $feed['meta'], 'workflow_step' );
	$entry_step_id       = rgar( $entry, 'workflow_step' );

	if ( $workflow_is_pending && ! empty( $feed_step_id ) && $feed_step_id == $entry_step_id ) {
		$step = Gravity_Flow_Steps::get( 'dropbox' );
		if ( $step ) {
			$add_on_feeds = $step->get_processed_add_on_feeds( $entry['id'] );

			if ( ! in_array( $feed['id'], $add_on_feeds ) ) {
				$add_on_feeds[] = $feed['id'];
				$step->update_processed_feeds( $add_on_feeds, $entry['id'] );
				gravity_flow()->process_workflow( $form, $entry['id'] );
			}
		}
	}
}

add_action( 'gform_dropbox_post_upload', 'gravity_flow_step_dropbox_post_upload', 10, 3 );