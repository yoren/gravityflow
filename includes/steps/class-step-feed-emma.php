<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Emma extends Gravity_Flow_Step_Feed_Add_On{
	public $_step_type = 'emma';

	protected $_class_name = 'GFEmma';

	public function get_label() {
		return esc_html__( 'Emma', 'gravityflow' );
	}

	function get_feed_label( $feed ){
		$label = $feed['meta']['feed_name'];
		return $label;
	}

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Emma() );