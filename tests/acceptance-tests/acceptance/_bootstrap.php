<?php

global $wp_rewrite, $wpdb;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
$wp_rewrite->flush_rules();


GFFormsModel::drop_tables();

// Flush out the feeds
$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}gf_addon_feed" );
GFForms::setup( true );
update_option( 'gform_pending_installation', false );
update_option( 'gravityflow_pending_installation', false );


function setup_gravity_forms_pages() {
	require_once( GFCommon::get_base_path() . '/export.php' );

	$form_filenames = glob( dirname( __FILE__ ) . '/../_data/forms/*.json' );

	foreach ( $form_filenames as $filename ) {

		GFExport::import_file( $filename );
	}

	$forms = GFAPI::get_forms( false );
	foreach ( $forms as $form ) {
		GFFormsModel::update_form_active( $form['id'], true );
		$page = array(
			'post_type' => 'page',
			'post_content' => '[gravityform id=' . $form['id'] . ']',
			'post_name' => sanitize_title_with_dashes( $form['title'] ),
			'post_parent' => 0,
			'post_author' => 1,
			'post_status' => 'publish',
			'post_title' => $form['title'],
		);
		wp_insert_post( $page );
	}
}

setup_gravity_forms_pages();
