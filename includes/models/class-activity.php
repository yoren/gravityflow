<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class Gravity_Flow_Activity {

	public static function get_activity_log_table_name(){
		global $wpdb;

		return $wpdb->prefix . 'gravityflow_activity_log';
	}

	public static function get_events( $limit = 200, $objects = array( 'workflow', 'step', 'assignee' ) ) {
		global $wpdb;

		$log_objects_placeholders = array_fill( 0, count( $objects ), '%s' );
		$log_objects_in_list = $wpdb->prepare( implode( ', ', $log_objects_placeholders ), $objects );

		$table   = self::get_activity_log_table_name();
		$sql     = $wpdb->prepare( "SELECT * FROM {$table} WHERE log_object IN ( $log_objects_in_list ) ORDER BY id DESC LIMIT %d", $limit );
		$results = $wpdb->get_results( $sql );

		return $results;
	}
}