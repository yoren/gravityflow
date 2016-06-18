<?php
/**
 * Gravity Flow Step Feed Emma
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Emma
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Emma extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'emma';

	protected $_class_name = 'GFEmma';

	public function get_label() {
		return esc_html__( 'Emma', 'gravityflow' );
	}

	function get_feed_label( $feed ) {
		$label = $feed['meta']['feed_name'];

		return $label;
	}
}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Emma() );
