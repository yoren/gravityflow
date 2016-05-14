<?php
/**
 * Gravity Flow Step Feed FreshBooks
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_FreshBooks
 * @copyright   Copyright (c) 2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.1.6
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_FreshBooks extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'freshbooks';

	protected $_class_name = 'GFFreshBooks';

	public function get_label() {
		return esc_html__( 'FreshBooks', 'gravityflow' );
	}
	
}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_FreshBooks() );
