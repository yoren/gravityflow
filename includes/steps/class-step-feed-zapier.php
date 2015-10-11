<?php
/**
 * Gravity Flow Step Feed Zapier
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Zapier
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class Gravity_Flow_Step_Feed_Zapier extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'zapier';

	protected $_class_name = 'GFZapier';

	public function get_label() {
		return esc_html__( 'Zapier', 'gravityflow' );
	}

	public function get_icon_url(){
		return $this->get_base_url() . '/images/zapier-icon.svg';
	}

	function get_feeds() {

		$form_id = $this->get_form_id();

		$feeds = GFZapierData::get_feed_by_form( $form_id );

		return $feeds;
	}

	function process_feed( $feed ) {

		$form  = $this->get_form();
		$entry = $this->get_entry();

		GFZapier::send_form_data_to_zapier( $entry, $form );
	}

	function intercept_submission() {
		remove_action( 'gform_after_submission', array( 'GFZapier', 'send_form_data_to_zapier' ) );
	}

	function get_feed_label( $feed ) {
		return $feed['name'];
	}

}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Zapier() );
