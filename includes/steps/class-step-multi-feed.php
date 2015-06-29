<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}
// Deprecated

class Gravity_Flow_Step_Multi_Feed extends Gravity_Flow_Step {
	public $_step_type = 'feed';

	public function get_label() {
		return esc_html__( 'Multi-Feed', 'gravityflow' );
	}

	public function get_settings(){

		$feed_add_ons = gravity_flow()->get_supported_feed_add_ons();
		$fields = array();

		foreach ( $feed_add_ons as $feed_add_on ) {

			$feeds = $this->get_feeds( $feed_add_on );

			$feed_choices = array();
			foreach ( $feeds as $feed ) {
				if ( $feed['is_active'] ) {
					$label = $this->get_add_on_feed_label( $feed_add_on, $feed );

					$feed_choices[] = array(
						'label' => $label,
						'name'  => $feed_add_on['slug'] . '_feed_' . $feed['id'],
					);
				}
			}

			if ( ! empty( $feed_choices ) ) {
				$fields[] = array(
					'name'     => 'feeds_' . $feed_add_on['slug'],
					'label'    => $feed_add_on['name'],
					'type'     => 'checkbox',
					'choices'  => $feed_choices,
				);
			}
		}

		if ( empty($fields) ) {
			if ( empty( $feed_add_ons ) ) {
				$html = esc_html__( "You don't have any supported feed add-ons installed.", 'gravityflow' );
			} else {
				$html = esc_html__( "You don't have any feeds set up.", 'gravityflow' );
			}
			$fields[] = array(
				'name'  => 'no_feeds',
				'label' => esc_html__( 'Add-On Feeds', 'gravityflow' ),
				'type'  => 'html',
				'html'  => $html,
			);
		}

		return array(
			'title'  => 'Gravity Forms Add-On Feeds',
			'fields' => $fields,
		);
	}


	function process(){

		$form = $this->get_form();
		$entry = $this->get_entry();

		$feed_add_ons = gravity_flow()->get_supported_feed_add_ons();

		foreach ( $feed_add_ons as $feed_add_on ) {

			$feeds = $this->get_feeds( $feed_add_on );
			$slug = $feed_add_on['slug'];
			foreach ( $feeds as $feed ) {
				$setting_key = $slug . '_feed_' . $feed['id'];
				if ( $this->{$setting_key} ) {
					if ( gravity_flow()->is_feed_condition_met( $feed, $form, $entry ) ) {

						$this->process_add_on_feed( $feed_add_on, $feed );
						$label = $this->get_add_on_feed_label( $feed_add_on, $feed );
						$note = sprintf( esc_html__( 'Feed processed: %s', 'gravityflow' ), $label );
						$this->add_note( $note, 0, $slug );
						gravity_flow()->log_debug( 'Feed processed' );
					} else {
						gravity_flow()->log_debug( 'Feed condition not met' );
					}
				}
			}
		}

		return true;
	}

	function get_feeds( $feed_add_on ){

		$form_id = $this->get_form_id();

		$feeds = array();

		if ( is_subclass_of( $feed_add_on['class'], 'GFFeedAddOn' ) ) {
			/* @var GFFeedAddOn $add_on */
			$add_on = call_user_func( array( $feed_add_on['class'], 'get_instance' ) );

			$feeds = $add_on->get_feeds( $form_id );
		} else {

			// Legacy add-ons
			switch ( $feed_add_on['slug'] ) {
				case 'gravityformszapier':
					if ( class_exists( 'GFZapierData' ) ) {
						$feeds = GFZapierData::get_feed_by_form( $form_id );
					}

					break;
				case 'gravityformsuserregistration' :
					if ( class_exists( 'GFUserData' ) ) {
						$feeds = GFUserData::get_feeds( $form_id );
					}
					break;
			}
		}

		return $feeds;
	}

	function process_add_on_feed( $feed_add_on, $feed ) {

		$form = $this->get_form();
		$entry = $this->get_entry();

		if ( is_subclass_of( $feed_add_on['class'], 'GFFeedAddOn' ) ) {
			$add_on = call_user_func( array( $feed_add_on['class'], 'get_instance' ) );
			$add_on->process_feed( $feed, $entry, $form );
		} else {
			// Legacy add-ons
			switch ( $feed_add_on['slug'] ) {
				case 'gravityformszapier':
					if ( class_exists( 'GFZapier' ) ) {
						GFZapier::send_form_data_to_zapier( $entry, $form );
					}

					break;
				case 'gravityformsuserregistration' :
					if ( class_exists( 'GFUser' ) ) {
						GFUser::gf_create_user( $entry, $form );
					}
					break;
			}
		}
	}

	function intercept_submission( $feed_add_on ){
		if ( is_subclass_of( $feed_add_on['class'], 'GFFeedAddOn' ) ) {
			$add_on = call_user_func( array( $feed_add_on['class'], 'get_instance' ) );
			remove_filter( 'gform_entry_post_save', array( $add_on, 'maybe_process_feed' ), 10 );
		} else {
			// Legacy add-ons
			switch ( $feed_add_on['slug'] ) {
				case 'gravityformszapier':
					remove_action( 'gform_after_submission', array( 'GFZapier', 'send_form_data_to_zapier' ) );
					break;
				case 'gravityformsuserregistration' :
					add_filter( 'gform_disable_registration', '__return_true' );
					break;
			}
		}
	}

	function get_add_on_feed_label( $feed_add_on, $feed ){

		$label = sprintf( esc_html__( 'Feed ID %s', 'gravityflow' ), $feed['id'] );

		switch ( $feed_add_on['slug'] ) {
			case 'gravityformsmailchimp':
				$label = $feed['meta']['feedName'];
				break;
			case 'gravityformsemma':
				$label = $feed['meta']['feed_name'];
				break;
			case 'gravityformszapier':
				$label = $feed['name'];
				break;
			case 'gravityformsuserregistration' :
				$label = $feed['meta']['feed_type'] == 'create' ? __( 'Create', 'gravityflow' ) : __( 'Registration', 'gravityflow' );
				//$label .= ' (ID: ' . $feed['id'] . ')';
				break;
		}
		return $label;
	}

}
// This class will most likely be removed.
//Gravity_Flow_Steps::register( new Gravity_Flow_Step_Multi_Feed() );