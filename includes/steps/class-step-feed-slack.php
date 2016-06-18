<?php
/**
 * Gravity Flow Step Feed Slack
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Slack
 * @copyright   Copyright (c) 2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.2-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Slack extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'slack';

	protected $_class_name = 'GFSlack';

	public function get_label() {
		return esc_html__( 'Slack', 'gravityflow' );
	}

	function get_feed_label( $feed ) {
		$label = $feed['meta']['feed_name'];

		return $label;
	}

	public function get_icon_url() {
		return $this->get_base_url() . '/images/slack-icon.png';
	}
}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Slack() );
