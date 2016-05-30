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

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Esign() );
