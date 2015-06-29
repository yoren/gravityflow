<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_MailChimp extends Gravity_Flow_Step_Feed_Add_On{
	public $_step_type = 'mailchimp';

	protected $_class_name = 'GFMailChimp';

	public function get_label() {
		return esc_html__( 'MailChimp', 'gravityflow' );
	}

	function get_feed_label( $feed ){
		$label = $feed['meta']['feedName'];
		return $label;
	}

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_MailChimp() );