<?php
/**
 * Gravity Flow Step Webhook
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Webhook
 * @copyright   Copyright (c) 2015, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

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
							'label' => 'PUT',
							'value' => 'put',
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

		$this->log_debug( __METHOD__ . '() - url before replacing variables: ' . $url );

		$url = GFCommon::replace_variables( $url, $this->get_form(), $entry, true, false, false, 'text' );

		$this->log_debug( __METHOD__ . '() - url after replacing variables: ' . $url );

		$method = strtoupper( $this->method );

		$body = null;

		$headers = array();

		if ( in_array( $method, array( 'POST', 'PUT' ) ) ) {
			if ( $this->format == 'json' ) {
				$headers = array( 'Content-type' => 'application/json' );
				$body    = json_encode( $entry );
			} else {
				$headers = array();
				$body    = $entry;
			}
		}

		$response = wp_remote_request( $url, array(
			'method'      => $method,
			'timeout'     => 45,
			'redirection' => 3,
			'blocking'    => true,
			'headers'     => $headers,
			'body'        => $body,
			'cookies'     => array()
		) );

		$this->log_debug( __METHOD__ . '() - response: ' . print_r( $response, true ) );

		if ( is_wp_error( $response ) ) {
			$step_status = 'error';
		} else {
			$step_status = 'success';
		}

		return $step_status;
	}

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Webhook() );