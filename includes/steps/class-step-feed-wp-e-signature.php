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
		$this->_feed_id = $feed['id'];
		add_action( 'esig_sad_document_invite_send', array( $this, 'sad_document_invite_send' ) );
		$feed['meta']['esign_gf_logic'] = 'email';
		parent::process_feed( $feed );
		
		return false;
	}
	
	public function is_supported() {
		return parent::is_supported() && class_exists( 'esig_sad_document' ) && class_exists( 'WP_E_Document' );
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
			$this->save_document_id( $args['document']->document_id );
		}
	}

	/**
	 * Store the current document ID in the entry meta for this step.
	 *
	 * @param int $document_id The documents unique ID assigned by WP E-Signature.
	 */
	public function save_document_id( $document_id ) {
		$document_ids = $this->get_document_ids();
		if ( ! in_array( $document_id, $document_ids ) ) {
			$document_ids[] = $document_id;
		}

		gform_update_meta( $this->get_entry_id(), 'workflow_step_' . $this->get_id() . '_document_ids', $document_ids );
	}

	/**
	 * Retrieve this entries document IDs for the current step.
	 *
	 * @return array
	 */
	public function get_document_ids() {
		$document_ids = gform_get_meta( $this->get_entry_id(), 'workflow_step_' . $this->get_id() . '_document_ids' );
		if ( empty( $document_ids ) ) {
			$document_ids = array();
		}

		return $document_ids;
	}

	public function workflow_detail_box( $form, $args ) {
		$document_ids = $this->get_document_ids();

		if ( ! empty( $document_ids ) && class_exists( 'WP_E_Document' ) ) {
			echo sprintf( '<h4 style="margin-bottom:10px;">%s</h4>', $this->get_label() );

			$doc_api    = new WP_E_Document();
			$invite_api = new WP_E_Invite();

			foreach ( $document_ids as $document_id ) {
				$document = $doc_api->getDocument( $document_id );

				echo sprintf( '%s: <a href="%s" target="_blank" title="Preview">%s</a><br><br>',
					esc_html__( 'Title', 'gravityflow' ),
					$invite_api->get_preview_url( $document_id ),
					esc_html( $document->document_title )
				);

				echo sprintf( '%s: %s<br><br>', esc_html__( 'Invite Status', 'gravityflow' ), $invite_api->is_invite_sent( $document_id ) ? esc_html__( 'Sent', 'gravityflow' ) : esc_html__( 'Error - Not Sent', 'gravityflow' ) );

				$params = array(
					'page'        => 'esign-resend_invite-document',
					'document_id' => $document_id
				);
				$resend_url = add_query_arg( $params, admin_url() );
				echo sprintf( '<a href="%s" class="button">%s</a><br><br>', esc_url( $resend_url ), esc_html__( 'Resend Invite', 'gravityflow' ) );
			}
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