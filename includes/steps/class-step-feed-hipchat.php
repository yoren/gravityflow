<?php
/**
 * Gravity Flow Step Feed HipChat
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_HipChat
 * @copyright   Copyright (c) 2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.2-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_HipChat extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'hipchat';

	protected $_class_name = 'GFHipChat';

	public function get_label() {
		return esc_html__( 'HipChat', 'gravityflow' );
	}

	function get_feed_label( $feed ) {
		$label = $feed['meta']['feed_name'];
		return $label;
	}
}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_HipChat() );
