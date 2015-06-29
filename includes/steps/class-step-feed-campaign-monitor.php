<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Campaign_Monitor extends Gravity_Flow_Step_Feed_Add_On{
	public $_step_type = 'campaign_monitor';

	protected $_class_name = 'GFCampaignMonitor';

	public function get_label() {
		return esc_html__( 'Campaign Monitor', 'gravityflow' );
	}

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Campaign_Monitor() );