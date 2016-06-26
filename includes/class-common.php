<?php
/**
 * Gravity Flow Common Functions
 *
 *
 * @package     GravityFlow
 * @copyright   Copyright (c) 2015-2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.3
 */


if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Common {

	/**
	 * Returns a URl to a workflow page.
	 *
	 * @param int|null $page_id
	 * @param Gravity_Flow_Assignee $assignee
	 * @param string $access_token
	 *
	 * @return string
	 */
	public static function get_workflow_url( $query_args, $page_id = null, $assignee = null, $access_token = '' ) {
		if ( $assignee && $assignee->get_type() == 'email' ) {
			$token_lifetime_days        = apply_filters( 'gravityflow_entry_token_expiration_days', 30, $assignee );
			$token_expiration_timestamp = strtotime( '+' . (int) $token_lifetime_days . ' days' );
			$access_token               = $access_token ? $access_token : gravity_flow()->generate_access_token( $assignee, null, $token_expiration_timestamp );
		}

		if ( empty( $page_id ) || $page_id == 'admin' ) {
			$base_url = admin_url( 'admin.php' );
		} else {
			$base_url = get_permalink( $page_id );
		}
		$url = add_query_arg( $query_args, $base_url );

		if ( ! empty( $access_token ) ) {
			$url = add_query_arg( array( 'gflow_access_token' => $access_token ), $url );
		}

		return $url;
	}
}
