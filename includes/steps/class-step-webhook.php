<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Webhook extends Gravity_Flow_Step {
	public $_step_type = 'webhook';

	public function get_label() {
		return esc_html__( 'Webhook', 'gravityflow' );
	}

	public function get_settings(){

		return array(
			'title'  => 'Webhook',
			'fields' => array(
				array(
					'name' => 'url',
					'class' => 'large',
					'label' => esc_html__( 'Webhook URL', 'gravityflow' ),
					'type' => 'text',
				),
				array(
					'name' => 'method',
					'label' => esc_html__( 'Request Method', 'gravityflow' ),
					'type' => 'select',
					'default_value' => 'post',
					'choices' => array(
						array(
							'label' => 'POST',
							'value' => 'post',
						),
						array(
							'label' => 'GET',
							'value' => 'get',
						),
						array(
							'label' => 'UPDATE',
							'value' => 'update',
						),
						array(
							'label' => 'DELETE',
							'value' => 'delete',
						),
					)
				),
				array(
					'name' => 'format',
					'label' => esc_html__( 'Format', 'gravityflow' ),
					'type' => 'select',
					'default_value' => 'json',
					'choices' => array(
						array(
							'label' => 'JSON',
							'value' => 'json',
						),
						array(
							'label' => 'FORM',
							'value' => 'form',
						),
					)
				),
				/* later
				array(
					'id' => 'all_fields',
					'name' => 'all_fields',
					'label' => esc_html__( 'Format', 'gravityflow' ),
					'type' => 'radio',
					'default_value' => 1,
					'onclick' => "jQuery('#webhook_field_select').parents('tr').toggle(this.value);",
					'choices' => array(
						array(
							'label' => __( 'All Fields', 'gravityflow' ),
							'value' => 1,
						),
						array(
							'label' => __( 'Select Fields', 'gravityflow' ),
							'value' => 0,
						),
					)
				),
				array(
					'id' => 'field_select',
					'name' => 'fields[]',
					'label' => esc_html__( 'Format', 'gravityflow' ),
					'type' => 'field_select',
					'multiple' => 'multiple',
				),
				*/
			),
		);
	}

	function process(){

		$result = $this->send_webhook();

		$this->add_note( $result, 0, 'webhook' );

		return true;
	}

	function send_webhook() {

		$entry = $this->get_entry();

		$url = $this->url;

		if ( $this->format == 'json' ) {
			$headers = array( 'Content-type' => 'application/json' );
			$body    = json_encode( $entry );
		} else {
			$headers = array();
			$body    = $entry;
		}

		$response = wp_remote_request( $url, array(
			'method'      => strtoupper( $this->method ),
			'timeout'     => 45,
			'redirection' => 3,
			'blocking'    => false,
			'headers'     => $headers,
			'body'        => $body,
			'cookies'     => array()
		) );

		if ( is_wp_error( $response ) ) {
			$step_status = 'error';
		} else {
			$step_status = 'success';
		}

		return $step_status;
	}

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Webhook() );