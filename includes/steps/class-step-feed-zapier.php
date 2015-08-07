<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class Gravity_Flow_Step_Feed_Zapier extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'zapier';

	protected $_class_name = 'GFZapier';

	public function get_label() {
		return esc_html__( 'Zapier', 'gravityflow' );
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
