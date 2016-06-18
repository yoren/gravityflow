<?php
/**
 * Gravity Flow Step Feed MailChimp
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_MailChimp
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_MailChimp extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'mailchimp';

	protected $_class_name = 'GFMailChimp';

	public function get_label() {
		return esc_html__( 'MailChimp', 'gravityflow' );
	}

	public function get_icon_url() {
		return $this->get_base_url() . '/images/mailchimp.svg';
	}

}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_MailChimp() );
