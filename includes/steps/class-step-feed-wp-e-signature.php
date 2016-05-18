<?php
/**
 * Gravity Flow Step Feed WP E-Signature
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Esign
 * @copyright   Copyright (c) 2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.2-dev
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
		parent::process_feed( $feed );

		// should we redirect to the sig page? seems like that should only be possible if the previous step was assigned to the user?
		// if we don't redirect should we filter the feed to change the action so the add-on sends the email requesting the signature
		// filtering the feed is possible with GF2.0 and the gform_pre_process_feeds filter
		if ( rgars( $feed, 'meta/esign_gf_logic' ) == 'redirect' ) {
			$redirect = get_transient( 'esig-gf-redirect-' . $this->get_add_on_instance()->get_the_user_ip() );
			if ( $redirect ) {
				wp_redirect( $redirect );
			}	
		}
	}
	
	public function is_supported() {
		return parent::is_supported() && class_exists( 'esig_sad_document' ) && class_exists( 'WP_E_Document' );
	}

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Esign() );
