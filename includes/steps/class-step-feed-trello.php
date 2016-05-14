<?php
/**
 * Gravity Flow Step Feed Trello
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Trello
 * @copyright   Copyright (c) 2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.2-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Trello extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'trello';

	protected $_class_name = 'GFTrello';

	public function get_label() {
		return esc_html__( 'Trello', 'gravityflow' );
	}

	public function get_icon_url() {
		return $this->get_base_url() . '/images/trello-icon.svg';
	}
}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Trello() );
