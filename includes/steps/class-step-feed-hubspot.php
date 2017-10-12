<?php
/**
 * Gravity Flow Step Feed HubSpot
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_HubSpot
 * @copyright   Copyright (c) 2016-2017, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.3-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_HubSpot extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'hubspot';

	protected $_class_name = '\BigSea\GFHubSpot\GF_HubSpot';
	protected $_slug = 'gravityforms-hubspot';

	public function get_label() {
		return 'HubSpot';
	}

	public function get_feed_add_on_class_name() {
		if ( ! class_exists( '\BigSea\GFHubSpot\GF_HubSpot' ) ) {
			$this->_class_name = 'GF_HubSpot';
		}

		return $this->_class_name;
	}

}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_HubSpot() );
