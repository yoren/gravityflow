<?php
/**
 * Gravity Flow Step Feed AWeber
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_AWeber
 * @copyright   Copyright (c) 2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.1.4
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_AWeber extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'aweber';

	protected $_class_name = 'GFAWeber';

	public function get_label() {
		return esc_html__( 'AWeber', 'gravityflow' );
	}
	
}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_AWeber() );
