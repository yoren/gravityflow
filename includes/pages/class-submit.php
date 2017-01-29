<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Gravity Flow Submit
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Submit
 * @copyright   Copyright (c) 2015-2017, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
class Gravity_Flow_Submit {
	public static function list_page( $form_ids, $is_admin ) {

		if ( empty( $form_ids ) ) {
			esc_html_e( "You haven't submitted any workflow forms yet.", 'gravityflow' );
			return;
		}

		$items = array();
		foreach ( $form_ids as $form_id ) {
			$form        = GFAPI::get_form( $form_id );
			if ( ! $form ) {
				continue;
			}
			$title       = sprintf( '<div class="gravityflow-initiate-form-title">%s</div>', rgar( $form, 'title' ) );
			$description = sprintf( '<div class="gravityflow-initiate-form-description">%s</div>', rgar( $form, 'description' ) );
			$form_id = absint( $form_id );
			$url = $is_admin ? admin_url( 'admin.php?page=gravityflow-submit&id=' . $form_id ) : add_query_arg( array(
				'page' => 'gravityflow-submit',
				'id'   => $form_id,
			) );


			$block = sprintf( '<a href="%s"><div class="panel">%s%s</div></a>', $url, $title, $description );
			$items[]     = sprintf( '<li id="gravityflow-initiate-form-%d">%s</li>', $form_id, $block );
		}

		$list = sprintf( '<ul id="gravityflow-initiate-list">%s</a></ul>', join( '', $items ) );

		echo $list;
	}

	public static function form( $form_id ) {
		gravity_form_enqueue_scripts( $form_id );
		gravity_form( $form_id );
	}
}
