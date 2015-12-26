<?php

class Gravity_Flow_Installation_Wizard_Step_Welcome extends Gravity_Flow_Installation_Wizard_Step {

	protected $_name = 'welcome';

	function display() {

		esc_html_e( "Click the 'Get Started' button to complete your installation.", 'gravityflow' );
		?>

	<?php
	}

	function get_next_button_text() {
		return esc_html__( 'Get Started', 'gravityflow' );
	}

	function get_title() {
		return esc_html__( 'Welcome', 'gravityflow' );
	}
}
