<?php

abstract class Gravity_Flow_Installation_Wizard_Step extends stdClass {

	protected $_name = '';

	protected $_field_validation_results = array();
	protected $_validation_summary = '';

	private $_step_values;

	public function __construct( $values = array() ) {
		if ( empty( $this->_name ) ) {
			throw new Exception( 'Name not set' );
		}
		$this->_step_values = $values;
	}

	public function get_name() {
		return $this->_name;
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function is( $key ) {
		return $key == $this->get_name();
	}

	public function get_title() {
		return '';
	}

	public function __set( $key, $value ) {
		$this->_step_values[ $key ] = $value;
	}

	public function __isset( $key ) {
		return isset( $this->_step_values[ $key ] );
	}

	public function __unset( $key ) {
		unset( $this->_step_values[ $key ] );
	}

	public function &__get( $key ) {
		if ( ! isset( $this->_step_values[ $key ] ) ) {
			$this->_step_values[ $key ] = '';
		}
		return $this->_step_values[ $key ];
	}

	public function get_values() {
		return $this->_step_values;
	}

	public function display() {
	}

	public function validate( $posted_values ) {
		// Assign $this->_validation_result;
		return true;
	}

	public function get_field_validation_result( $key ) {
		if ( ! isset( $this->_field_validation_results[ $key ] ) ) {
			$this->_field_validation_results[ $key ] = '';
		}
		return $this->_field_validation_results[ $key ];
	}

	/**
	 * @param string  $key
	 * @param $text
	 */
	public function set_field_validation_result( $key, $text ) {
		$this->_field_validation_results[ $key ] = $text;
	}

	public function set_validation_summary( $text ) {
		$this->_validation_summary = $text;
	}

	public function get_validation_summary() {
		return $this->_validation_summary;
	}

	/**
	 * @param string $key
	 * @param bool $echo
	 *
	 * @return string
	 */
	public function validation_message( $key, $echo = true ) {
		$message = '';
		$validation_result = $this->get_field_validation_result( $key );
		if ( ! empty( $validation_result ) ) {
			$message = sprintf( '<div class="validation_message">%s</div>', $validation_result );
		}

		if ( $echo ) {
			echo $message;
		}
		return $message;
	}

	public function is_complete() {
	}

	public function get_next_button_text() {
		return __( 'Next', 'gravityflow' );
	}

	public function get_previous_button_text() {
		return __( 'Back', 'gravityflow' );
	}

	public function update( $values ) {
		update_option( 'gravityflow_installation_wizard_' . $this->get_name(), $values );
		$this->_step_values = $values;
	}

	public function summary( $echo = true ) {
		return '';
	}

	public function install() {
		// do something
	}

	public function flush_values() {
		delete_option( 'gravityflow_installation_wizard_' . $this->get_name() );
	}
}
