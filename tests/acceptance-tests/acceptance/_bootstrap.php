<?php

global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
$wp_rewrite->flush_rules();


GFFormsModel::drop_tables();
GFForms::setup( true );
update_option( 'gform_pending_installation', false );
gravity_flow()->setup();
update_option( 'gravityflow_pending_installation', false );


function setup_gravity_forms_pages() {
	$form_filenames = array( 'conditional-logic.json', 'two-pages.json', 'vacation-request.json' );
	foreach ( $form_filenames as $filename ) {
		$form_json = file_get_contents( dirname( __FILE__ ) . '/../_data/forms/' . $filename );
		$forms     = json_decode( $form_json, true );
		$form      = $forms[0];
		$form_id   = GFAPI::add_form( $form );
		$slug = str_replace( '.json', '', $filename );
		$page = array(
			'post_type' => 'page',
			'post_content' => '[gravityform id=' . $form_id . ']',
			'post_name' => sanitize_key( $slug ),
			'post_parent' => 0,
			'post_author' => 1,
			'post_status' => 'publish',
			'post_title' => $form['title'],
		);
		wp_insert_post( $page );
	}
}

setup_gravity_forms_pages();
