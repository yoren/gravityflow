<?php
/**
 * Gravity Flow Step Feed User Registration
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_User_Registration
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class Gravity_Flow_Step_Feed_User_Registration extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'user_registration';

	protected $_class_name = 'GFUser';

	public function get_feed_add_on_class_name() {
		$class_name        = class_exists( 'GF_User_Registration' ) ? 'GF_User_Registration' : 'GFUser';
		$this->_class_name = $class_name;

		return $class_name;
	}

	public function get_label() {
		return esc_html__( 'User Registration', 'gravityflow' );
	}

	public function get_icon_url() {
		return '<i class="fa fa-user" style="color:darkgreen"></i>';
	}

	function get_feeds() {
		$form_id = $this->get_form_id();

		if ( class_exists( 'GF_User_Registration' ) ) {
			$feeds = parent::get_feeds();
		} else {
			$feeds = GFUserData::get_feeds( $form_id );
		}


		return $feeds;
	}

	function process_feed( $feed ) {
		if ( class_exists( 'GF_User_Registration' ) ) {
			parent::process_feed( $feed );

			return true;
		}
		$form  = $this->get_form();
		$entry = $this->get_entry();
		remove_filter( 'gform_disable_registration', '__return_true' );
		GFUser::gf_create_user( $entry, $form );

		// Make sure it's not run twice
		add_filter( 'gform_disable_registration', '__return_true' );
		
		return true;
	}

	function intercept_submission() {
		if ( class_exists( 'GF_User_Registration' ) ) {
			parent::intercept_submission();

			return;
		}

		add_filter( 'gform_disable_registration', '__return_true' );
	}

	function get_feed_label( $feed ) {
		if ( class_exists( 'GF_User_Registration' ) ) {
			return parent::get_feed_label( $feed );
		}

		$label = $feed['meta']['feed_type'] == 'create' ? __( 'Create', 'gravityflow' ) : __( 'Update', 'gravityflow' );

		return $label;
	}
}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_User_Registration() );
