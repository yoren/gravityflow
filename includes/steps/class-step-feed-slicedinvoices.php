<?php
/**
 * Gravity Flow Step Feed Sliced Invoices
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Sliced_Invoices
 * @copyright   Copyright (c) 2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.3-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Sliced_Invoices extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'slicedinvoices';

	protected $_class_name = 'Sliced_Invoices_GF';
	protected $_slug = 'slicedinvoices';

	public function get_label() {
		return esc_html__( 'Sliced Invoices', 'gravityflow' );
	}

}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Sliced_Invoices() );
