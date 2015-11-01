<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class Gravity_Flow_Activity {

	public static function get_activity_log_table_name(){
		global $wpdb;

		return $wpdb->prefix . 'gravityflow_activity_log';
	}

	public static function get_events( $limit = 400, $objects = array( 'workflow', 'step', 'assignee' ) ) {
		global $wpdb;

		$log_objects_placeholders = array_fill( 0, count( $objects ), '%s' );
		$log_objects_in_list = $wpdb->prepare( implode( ', ', $log_objects_placeholders ), $objects );

		$table   = self::get_activity_log_table_name();
		$sql     = $wpdb->prepare( "SELECT * FROM {$table} WHERE log_object IN ( $log_objects_in_list ) ORDER BY id DESC LIMIT %d", $limit );
		$results = $wpdb->get_results( $sql );

		return $results;
	}

	public static function get_report_data_for_all_forms( $start_date, $end_date = '' ){
		global $wpdb;

		$table   = self::get_activity_log_table_name();

		$form_ids = self::get_form_ids();
		if ( empty ( $form_ids ) ) {
			return false;
		}
		$in_str_arr    = array_fill( 0, count( $form_ids ), '%d' );
		$in_str        = join( ',', $in_str_arr );
		$form_id_clause = $wpdb->prepare( "AND form_id IN ($in_str)", $form_ids );

		$sql     = $wpdb->prepare( "
SELECT form_id, count(id) as c, ROUND( AVG(duration) ) as av
FROM {$table}
WHERE log_object = 'workflow' AND log_event = 'ended'
AND date_created >= %s
{$form_id_clause}
GROUP BY form_id", $start_date );

		$results = $wpdb->get_results( $sql );

		return $results;

	}

	public static function get_report_data_for_form( $form_id, $start_date, $end_date = '' ){
		global $wpdb;


		$table   = self::get_activity_log_table_name();

		$sql     = $wpdb->prepare( "
SELECT MONTH(date_created) as month, count(id) as c, ROUND( AVG(duration) ) as av
FROM {$table}
WHERE log_object = 'workflow' AND log_event = 'ended'
  AND form_id = %d
  AND date_created >= %s
GROUP BY YEAR(date_created), MONTH(date_created)", $form_id, $start_date );

		$results = $wpdb->get_results( $sql );

		return $results;

	}

	public static function get_report_data_for_form_by_step( $form_id, $start_date, $end_date = '' ){
		global $wpdb;

		$table   = self::get_activity_log_table_name();

		$sql     = $wpdb->prepare( "
SELECT feed_id, count(id) as c, ROUND( AVG(duration) ) as av
FROM {$table}
WHERE log_object = 'step' AND log_event = 'ended'
  AND form_id = %d
  AND date_created >= %s
GROUP BY feed_id", $form_id, $start_date );

		$results = $wpdb->get_results( $sql );

		return $results;

	}

	public static function get_report_data_for_step_by_assignee( $step_id, $start_date, $end_date = '' ){
		global $wpdb;

		$table   = self::get_activity_log_table_name();

		$sql     = $wpdb->prepare( "
SELECT assignee_id, assignee_type, count(id) as c, ROUND( AVG(duration) ) as av
FROM {$table}
WHERE log_object = 'assignee' AND log_event = 'status' AND log_value NOT IN ('pending', 'removed')
  AND feed_id = %d
  AND date_created >= %s
GROUP BY assignee_id, assignee_type", $step_id, $start_date );

		$results = $wpdb->get_results( $sql );

		return $results;

	}

	public static function get_report_data_for_form_by_assignee( $form_id, $start_date, $end_date = '' ){
		global $wpdb;

		$table   = self::get_activity_log_table_name();

		$sql     = $wpdb->prepare( "
SELECT assignee_id, assignee_type, count(id) as c, ROUND( AVG(duration) ) as av
FROM {$table}
WHERE log_object = 'assignee' AND log_event = 'status' AND log_value NOT IN ('pending', 'removed')
  AND form_id = %d
  AND date_created >= %s
GROUP BY assignee_id, assignee_type", $form_id, $start_date );

		$results = $wpdb->get_results( $sql );

		return $results;

	}

	public static function get_report_data_for_all_forms_by_assignee( $start_date, $end_date = '' ){
		global $wpdb;

		$table   = self::get_activity_log_table_name();

		$sql     = $wpdb->prepare( "
SELECT assignee_id, assignee_type, count(id) as c, ROUND( AVG(duration) ) as av
FROM {$table}
WHERE log_object = 'assignee' AND log_event = 'status' AND log_value NOT IN ('pending', 'removed')
  AND date_created >= %s
GROUP BY assignee_id, assignee_type", $start_date );

		$results = $wpdb->get_results( $sql );

		return $results;

	}

	public static function get_report_data_for_assignee_by_month( $assignee_type, $assignee_id, $start_date, $end_date = '' ){
		global $wpdb;

		$table   = self::get_activity_log_table_name();

		$form_ids = self::get_form_ids();
		if ( empty ( $form_ids ) ) {
			return false;
		}
		$in_str_arr    = array_fill( 0, count( $form_ids ), '%d' );
		$in_str        = join( ',', $in_str_arr );
		$form_id_clause = $wpdb->prepare( "AND form_id IN ($in_str)", $form_ids );

		$sql     = $wpdb->prepare( "
SELECT YEAR(date_created) as year, MONTH(date_created) as month, count(id) as c, ROUND( AVG(duration) ) as av
FROM {$table}
WHERE log_object = 'assignee' AND log_event = 'status' AND log_value NOT IN ('pending', 'removed')
  AND assignee_type = %s AND assignee_id = %s
  AND date_created >= %s
  {$form_id_clause}
GROUP BY YEAR(date_created), MONTH(date_created)", $assignee_type, $assignee_id, $start_date );

		$results = $wpdb->get_results( $sql );

		return $results;

	}

	public static function get_form_ids(){
		return gravity_flow()->get_workflow_form_ids();
	}


}