<?php
/**
 * Gravity Flow Step Feed Campaign Monitor
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Campaign_Monitor
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Campaign_Monitor extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'campaign_monitor';

	protected $_class_name = 'GFCampaignMonitor';

	public function get_label() {
		return esc_html__( 'Campaign Monitor', 'gravityflow' );
	}
}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Campaign_Monitor() );
