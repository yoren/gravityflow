<?php

abstract class Gravity_Flow_Installation_Wizard_Step extends stdClass {

	protected $_name = '';

	protected $_field_validation_results = array();
	protected $_validation_summary = '';

	private $_step_values;

	function __construct( $values = array() ){
		if ( empty( $this->_name ) ) {
			throw new Exception( 'Name not set' );
		}
		$this->_step_values = $values;
	}

	function get_name(){
		return $this->_name;
	}

	function is( $key ) {
		return $key == $this->get_name();
	}

	function get_title(){
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

	function &__get( $key ){
		if ( ! isset( $this->_step_values[ $key ] ) ) {
			$this->_step_values[ $key ] = '';
		}
		return $this->_step_values[ $key ];
	}

	function get_values(){
		return $this->_step_values;
	}

	function display(){
	}

	function validate( $posted_values ){
		// Assign $this->_validation_result;
		return true;
	}

	function get_field_validation_result( $key ){
		if ( ! isset( $this->_field_validation_results[ $key ] ) ) {
			$this->_field_validation_results[ $key ] = '';
		}
		return $this->_field_validation_results[ $key ];
	}

	function set_field_validation_result( $key, $text ){
		$this->_field_validation_results[ $key ] = $text;
	}

	function set_validation_summary( $text ) {
		$this->_validation_summary = $text;
	}

	function get_validation_summary(){
		return $this->_validation_summary;
	}

	function validation_message( $key, $echo = true ){
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

	function is_complete(){
	}

	function get_next_button_text(){
		return __( 'Next', 'gravityflow' );
	}

	function get_previous_button_text(){
		return __( 'Back', 'gravityflow' );
	}

	function update( $values ){
		update_option( 'gravityflow_installation_wizard_' . $this->get_name(), $values );
		$this->_step_values = $values;
	}

	function summary( $echo = true ){
		return '';
	}

	function install(){
		// do something
	}

	function flush_values(){
		delete_option( 'gravityflow_installation_wizard_' . $this->get_name() );
	}
}