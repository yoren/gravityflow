<?php
/**
 * Gravity Flow Step Feed WP E-Signature
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Esign
 * @copyright   Copyright (c) 2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.3-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Esign extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'wp-e-signature';

	protected $_class_name = 'GFEsignAddOn';
	protected $_slug = 'esig-gf';
	protected $_feed_id;


	public function get_label() {
		return esc_html__( 'WP E-Signature', 'gravityflow' );
	}

	function get_feed_label( $feed ) {
		$sad_page_id    = rgars( $feed, 'meta/esig_gf_sad' );
		$sad            = new esig_sad_document();
		$document_id    = $sad->get_sad_id( $sad_page_id );
		$document_model = new WP_E_Document();
		$document       = $document_model->getDocument( $document_id );

		return $document->document_title;
	}

	public function get_icon_url() {
		return $this->get_base_url() . '/images/esig-icon.png';
	}

	function intercept_submission() {
		parent::intercept_submission();

		remove_filter( 'gform_confirmation', array( ESIG_GRAVITY_Admin::get_instance(), 'reroute_confirmation' ) );
	}

	public function process_feed( $feed ) {
		$previous_step  = $this->get_previous_step();
		$was_user_input = $previous_step && $previous_step->get_type() == 'user_input';

		if ( ! $was_user_input ) {
			$feed['meta']['esign_gf_logic'] = 'email';
		}

		$this->_feed_id = $feed['id'];
		add_action( 'esig_sad_document_invite_send', array( $this, 'sad_document_invite_send' ) );
		parent::process_feed( $feed );

		// only perform the redirect when the previous step type was user_input
		if ( $was_user_input && rgars( $feed, 'meta/esign_gf_logic' ) == 'redirect' ) {
			$redirect = get_transient( 'esig-gf-redirect-' . $this->get_add_on_instance()->get_the_user_ip() );
			if ( $redirect ) {
				wp_redirect( $redirect );
			}
		}
		
		return true;
	}
	
	public function is_supported() {
		return parent::is_supported() && class_exists( 'esig_sad_document' ) && class_exists( 'WP_E_Document' );
	}

	/**
	 * Retrieve the previous step.
	 *
	 * @todo maybe move to Gravity_Flow or Gravity_Flow_API
	 *
	 * @return bool|Gravity_Flow_Step
	 */
	public function get_previous_step() {
		$entry           = $this->get_entry();
		$current_step_id = rgar( $entry, 'workflow_step' );
		$previous_step   = false;

		if ( $current_step_id ) {
			$steps = gravity_flow()->get_steps( $this->get_form_id(), $entry );

			foreach ( $steps as $step ) {
				if ( $current_step_id == $step->get_id() ) {

					return $previous_step;
				}

				$status = rgar( $entry, 'workflow_step_status_' . $step->get_id() );

				if ( $status && ! in_array( $status, array( 'pending', 'queued' ) ) ) {
					$previous_step = $step;
				}
			}
		}

		return $previous_step;
	}

	/**
	 * Evaluates the status for the step.
	 *
	 * The step is only complete after gravity_flow_step_esign_document_complete() has added all the feeds for this step back into the entry meta processed_feeds array.
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

	/**
	 * Target of esig_sad_document_invite_send hook. Store the feed id which created this document in the WP E-Signature meta.
	 *
	 * @param array $args The properties related to the document which was saved.
	 */
	public function sad_document_invite_send( $args ) {
		if ( ! empty( $this->_feed_id ) && class_exists( 'WP_E_Meta' ) ) {
			$sig_meta_api = new WP_E_Meta();
			$sig_meta_api->add( $args['document']->document_id, 'esig_gravity_feed_id', $this->_feed_id );
		}
	}

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Esign() );

/**
 * Resume the workflow if the completed document originated from a feed processed by one of our steps.
 *
 * @param array $args The properties related to the completed document.
 */
function gravity_flow_step_esign_document_complete( $args ) {
	if ( class_exists( 'WP_E_Meta' ) ) {
		$sig_meta_api = new WP_E_Meta();
		$entry_id     = $sig_meta_api->get( $args['invitation']->document_id, 'esig_gravity_entry_id' );
		$feed_id      = $sig_meta_api->get( $args['invitation']->document_id, 'esig_gravity_feed_id' );

		if ( $entry_id && $feed_id ) {
			$entry = GFAPI::get_entry( $entry_id );

			if ( ! is_wp_error( $entry ) && is_array( $entry ) && rgar( $entry, 'workflow_final_status' ) == 'pending' ) {
				$step = Gravity_Flow_Steps::get( 'wp-e-signature' );
				if ( $step ) {
					$add_on_feeds = $step->get_processed_add_on_feeds( $entry_id );

					if ( ! in_array( $feed_id, $add_on_feeds ) ) {
						$add_on_feeds[] = $feed_id;
						$step->update_processed_feeds( $add_on_feeds, $entry_id );
						$form = GFAPI::get_form( $entry['form_id'] );
						gravity_flow()->process_workflow( $form, $entry_id );
					}
				}
			}

		}
	}
}
add_action( 'esig_document_complate', 'gravity_flow_step_esign_document_complete' );