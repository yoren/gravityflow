<?php
/*
Plugin Name: Gravity Flow
Plugin URI: https://gravityflow.io
Description: Build Workflow Applications with Gravity Forms.
Version: 1.1.3.13
Author: Steven Henty
Author URI: http://www.stevenhenty.com
License: GPL-3.0+
Text Domain: gravityflow
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2015 Steven Henty

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 3, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'GRAVITY_FLOW_VERSION', '1.1.3.13' );

define( 'GRAVITY_FLOW_EDD_STORE_URL', 'https://gravityflow.io' );

define( 'GRAVITY_FLOW_EDD_ITEM_NAME', 'Gravity Flow' );

add_action( 'gform_loaded', array( 'Gravity_Flow_Bootstrap', 'load' ), 1 );

class Gravity_Flow_Bootstrap {

	public static function load(){

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		if ( ! class_exists( 'Gravity_Flow_EDD_SL_Plugin_Updater' ) ) {
			include( dirname( __FILE__ ) . '/includes/EDD_SL_Plugin_Updater.php' );
		}

		if ( ! class_exists( 'Gravity_Flow_API' ) ) {
			include( dirname( __FILE__ ) . '/includes/class-api.php' );
		}

		if ( ! class_exists( 'Gravity_Flow_Web_API' ) ) {
			include( dirname( __FILE__ ) . '/includes/class-web-api.php' );
		}

		if ( ! class_exists( 'Gravity_Flow_Extension' ) ) {
			include( dirname( __FILE__ ) . '/includes/class-extension.php' );
		}

		if ( ! class_exists( 'Gravity_Flow_Assignee' ) ) {
			include( dirname( __FILE__ ) . '/includes/class-assignee.php' );
		}

		require_once( 'class-gravity-flow.php' );
		require_once( 'includes/models/class-activity.php' );
		require_once( 'includes/steps/class-step.php' );
		require_once( 'includes/steps/class-steps.php' );

		foreach ( glob( plugin_dir_path( __FILE__ ) . 'includes/steps/class-step-*.php' ) as $gravity_flow_filename ) {
			require_once( $gravity_flow_filename );
		}

		foreach ( glob( plugin_dir_path( __FILE__ ) . 'includes/fields/class-field-*.php' ) as $gravity_flow_filename ) {
			require_once( $gravity_flow_filename );
		}

		GFAddOn::register( 'Gravity_Flow' );
		do_action( 'gravityflow_loaded' );
	}

}

function gravity_flow() {
	if ( class_exists( 'Gravity_Flow' ) ) {
		return Gravity_Flow::get_instance();
	}
}

add_action( 'init', 'gravityflow_edd_plugin_updater', 0 );

function gravityflow_edd_plugin_updater() {

	$gravity_flow = gravity_flow();
	if ( $gravity_flow ) {

		if ( defined( 'GRAVITY_FLOW_LICENSE_KEY' ) ) {
			$license_key = GRAVITY_FLOW_LICENSE_KEY;
		} else {
			$settings = gravity_flow()->get_app_settings();

			$license_key = trim( rgar( $settings, 'license_key' ) );
		}

		$edd_updater = new Gravity_Flow_EDD_SL_Plugin_Updater( GRAVITY_FLOW_EDD_STORE_URL, __FILE__, array(
			'version'   => GRAVITY_FLOW_VERSION,
			'license'   => $license_key,
			'item_name' => GRAVITY_FLOW_EDD_ITEM_NAME,
			'author'    => 'Steven Henty',
		) );
	}

}

