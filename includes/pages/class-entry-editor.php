<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Gravity Flow Entry Editor
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Entry_Editor
 * @copyright   Copyright (c) 2015-2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2.0.30
 */
class Gravity_Flow_Entry_Editor {

	/**
	 * The Gravity Forms form array.
	 *
	 * @var array
	 */
	public $form;

	/**
	 * The Gravity Forms Entry array.
	 * @var
	 */
	public $entry;

	/**
	 *
	 * @var Gravity_Flow_Step $step
	 */
	public $step;

	/**
	 * Used to help determine whether to display the order summary.
	 *
	 * @var bool
	 */
	public $has_product_fields = false;

	/**
	 * Flag set in the constructor to control the visibility of empty fields.
	 *
	 * @var
	 */
	public $display_empty_fields;

	public function __construct( $form, $entry, $step, $display_empty_fields ) {
		$this->form = $form;
		$this->entry = $entry;
		$this->step = $step;

		$this->display_empty_fields = $display_empty_fields;
	}


	/**
	 * Renders the form. Uses GFFormDisplay::get_form() to display the fields.
	 */
	public function render_edit_form() {

		add_filter( 'gform_pre_render', array( $this, 'filter_gform_pre_render' ), 999, 3 );
		add_filter( 'gform_submit_button', array( $this, 'filter_gform_submit_button' ), 10, 2 );
		add_filter( 'gform_previous_button', array( $this, 'filter_gform_submit_button' ), 10, 2 );
		add_filter( 'gform_next_button', array( $this, 'filter_gform_submit_button' ), 10, 2 );
		add_filter( 'gform_disable_view_counter', '__return_true' );
		add_filter( 'gform_field_input', array( $this, 'filter_gform_field_input' ), 10, 5 );
		add_filter( 'gform_form_tag', array( $this, 'filter_gform_form_tag' ), 10, 2 );
		add_filter( 'gform_get_form_filter', array( $this, 'filter_gform_get_form_filter' ), 10, 2 );
		add_filter( 'gform_field_content', array( $this, 'filter_gform_field_content' ), 10, 5 );
		add_filter( 'gform_field_container', array( $this, 'filter_gform_field_container' ), 10, 6 );
		add_filter( 'gform_has_conditional_logic', array( $this, 'filter_gform_has_conditional_logic' ), 10, 2 );

		add_filter( 'gform_field_css_class',  array( $this, 'filter_gform_field_css_class' ), 10, 3 );

		// Impersonate front-end form
		unset( $_GET['page'] );

		$html = GFFormDisplay::get_form( $this->form['id'], false, false, true, $this->entry );

		remove_filter( 'gform_pre_render', array( $this, 'filter_gform_pre_render' ), 999 );
		remove_filter( 'gform_submit_button', array( $this, 'render_gform_submit_button' ), 10 );
		remove_filter( 'gform_previous_button', array( 'Gravity_Flow_Entry_Detail', 'filter_gform_submit_button' ), 10 );
		remove_filter( 'gform_next_button', array( $this, 'filter_gform_submit_button' ), 10 );
		remove_filter( 'gform_disable_view_counter', '__return_true' );
		remove_filter( 'gform_field_input', array( $this, 'filter_gform_field_input' ), 10 );
		remove_filter( 'gform_form_tag', array( $this, 'filter_gform_form_tag' ), 10 );
		remove_filter( 'gform_get_form_filter', array( $this, 'filter_gform_get_form_filter' ), 10 );
		remove_filter( 'gform_field_content', array( $this, 'filter_gform_field_content' ), 10 );
		remove_filter( 'gform_field_container', array( $this, 'filter_gform_field_container' ), 10 );
		remove_filter( 'gform_has_conditional_logic', array( $this, 'filter_gform_has_conditional_logic' ), 10 );

		echo $html;
	}

	/**
	 * Target of the gform_pre_render filter.
	 * Removes the page fields from the form.
	 *
	 * @param array $form
	 *
	 * @return array the filtered form
	 */
	public function filter_gform_pre_render( $form ) {
		// Remove page fields
		$existing_fields = $form['fields'];
		$fields = array();
		foreach ( $existing_fields as $field ) {
			if ( $field->type !== 'page' ) {
				if ( ! in_array( $field->id, $this->step->get_editable_fields() ) ) {
					$field->description = null;
				}
				$field->adminOnly = false;
				$fields[] = $field;
			}
		}

		$form['fields'] = $fields;

		return $form;
	}

	/**
	 * Target for the gform_submit_button filter.
	 *
	 * @param $button_input
	 * @param $form
	 *
	 * @return string
	 */
	public function filter_gform_submit_button( $button_input, $form ) {
		return '';
	}

	/**
	 * Taget for the gform_field_input filter.
	 *
	 * Handles the construction of the field input. Returns markup for the editable field or the display value.
	 *
	 * @param $html
	 * @param GF_Field $field
	 * @param $value
	 * @param $lead_id
	 * @param $form_id
	 *
	 * @return mixed
	 */
	public function filter_gform_field_input( $html, $field, $value, $lead_id, $form_id ) {

		$field_id = $field->id;

		$editable_fields = $this->step->get_editable_fields();

		if ( ! in_array( $field_id, $editable_fields ) ) {
			return $this->get_display_field( $field, $value, $lead_id, $form_id );
		}

		$dynamic_conditional_logic_enabled = $this->is_dynamic_conditional_logic_enabled();

		if ( $dynamic_conditional_logic_enabled ) {
			$field->conditionalLogicFields = GFFormDisplay::get_conditional_logic_fields( $this->form, $field->id );
		}

		if ( GFCommon::is_product_field( $field->type ) ) {
			$this->has_product_fields = true;
		}

		$posted_form_id = rgpost( 'gravityflow_submit' );
		if ( $posted_form_id == $this->form['id'] ) {
			$value = GFFormsModel::get_field_value( $field );
		} else {
			$value = GFFormsModel::get_lead_field_value( $this->entry, $field );
			if ( $field->get_input_type() == 'email' && $field->emailConfirmEnabled ) {
				$_POST[ 'input_' . $field->id . '_2' ] = $value;
			}
		}

		if ( $field->get_input_type() == 'fileupload' ) {
			$field->_is_entry_detail = true;
		}

		$html = $field->get_field_input( $this->form, $value, $this->entry );

		return $html;
	}

	/**
	 * Checks wether dynamic conditional logic is enabled.
	 *
	 * @return bool
	 */
	public function is_dynamic_conditional_logic_enabled() {
		return $this->step && gravity_flow()->fields_have_conditional_logic( $this->form ) && $this->step->conditional_logic_editable_fields_enabled && $this->step->conditional_logic_editable_fields_mode != 'page_load';
	}

	/**
	 * Target for the gform_get_form_filter filter.
	 * Strips the closing form tag and replaces the Gravity Forms token for Gravity Flow's token.
	 *
	 * @param $form_string
	 * @param $form
	 *
	 * @return mixed
	 */
	public function filter_gform_get_form_filter( $form_string, $form ) {
		$form_string = str_replace( 'gform_submit', 'gravityflow_submit', $form_string );
		$form_string = str_replace( '</form>', '', $form_string );
		return $form_string;
	}

	/**
	 * Target for the gform_form_tag filter.
	 *
	 * Strips the form tag off.
	 *
	 * @param $form_tag
	 * @param $form
	 *
	 * @return string
	 */
	public function filter_gform_form_tag( $form_tag, $form ) {
		return '';
	}

	/**
	 * Generates and returns the markup for a display field.
	 *
	 * @param GF_Field $field
	 * @param $value
	 * @param $lead_id
	 * @param $form_id
	 *
	 * @return string
	 */
	public function get_display_field( $field, $value, $lead_id, $form_id ) {

		if ( $field->type == 'section' && ( GFCommon::is_section_empty( $field, $this->form, $this->entry ) || ! $this->display_empty_fields ) ) {
			$html = '<!-- gravityflow_hidden -->';
			return $html;
		}

		if ( GFCommon::is_product_field( $field->type ) ) {
			$this->has_product_fields = true;
		}
		$html = '';
		$value = RGFormsModel::get_lead_field_value( $this->entry, $field );
		$dynamic_conditional_logic_enabled = $this->is_dynamic_conditional_logic_enabled();
		if ( $dynamic_conditional_logic_enabled ) {
			$conditional_logic_fields = GFFormDisplay::get_conditional_logic_fields( $this->form, $field->id );
			if ( ! empty( $conditional_logic_fields ) ) {
				$field->conditionalLogicFields = $conditional_logic_fields;
				$field_input = $field->get_field_input( $this->form, $value, $this->entry );
				$html = '<div style="display:none;">' . $field_input . '</div>';
			}
		}

		if ( ! $this->is_display_field( $field ) ) {
			$html = '<!-- gravityflow_hidden -->' . $html;
			return $html;
		}

		if ( $field->type == 'html' ) {
			$html = $field->content;
			return $html;
		}

		if ( $field->type == 'product' ) {
			if ( $field->has_calculation() ) {
				$product_name = trim( $value[ $field->id . '.1' ] );
				$price        = trim( $value[ $field->id . '.2' ] );
				$quantity     = trim( $value[ $field->id . '.3' ] );

				if ( empty( $product_name ) ) {
					$value[ $field->id . '.1' ] = $field->get_field_label( false, $value );
				}

				if ( empty( $price ) ) {
					$value[ $field->id . '.2' ] = '0';
				}

				if ( empty( $quantity ) ) {
					$value[ $field->id . '.3' ] = '0';
				}
			}
		}

		$input_type = $field->get_input_type();
		if ( $input_type == 'hiddenproduct' ) {
			$display_value = $value[ $field->id . '.2' ];
		} else {
			$display_value = GFCommon::get_lead_field_display( $field, $value, $this->entry['currency'] );
		}

		$display_value = apply_filters( 'gform_entry_field_value', $display_value, $field, $this->entry, $this->form );

		if ( $this->display_empty_fields ) {
			if ( empty( $display_value ) || $display_value === '0' ) {
				$display_value = '&nbsp;';
			}
			$display_value = sprintf( '<div class="gravityflow-field-value">%s<div>', $display_value );
		} else {
			if ( empty( $display_value ) || $display_value === '0' ) {
				$display_value = '<!-- gravityflow_hidden -->';
			} else {
				$display_value = sprintf( '<div class="gravityflow-field-value">%s<div>', $display_value );
			}
		}

		$html .= $display_value;
		return $html;
	}

	/**
	 * Checks whether the given field is a display field and whether it should be displayed.
	 *
	 * @param $field
	 *
	 * @return bool
	 */
	public function is_display_field( $field ) {
		$display_field = true;

		$display_fields_mode = $this->step->display_fields_mode;

		$display_fields_selected = is_array( $this->step->display_fields_selected ) ? $this->step->display_fields_selected : array();

		if ( $display_fields_mode == 'selected_fields' ) {
			if ( ! in_array( $field->id, $display_fields_selected ) ) {
				$display_field = false;
			}
		} else {
			if ( GFFormsModel::is_field_hidden( $this->form, $field, array(), $this->entry ) ) {
				$display_field = false;
			}
		}

		return $display_field;
	}

	/**
	 * Checks whether a field is an editable field.
	 *
	 * @param $field
	 *
	 * @return bool
	 */
	public function is_editable_field( $field ) {
		return in_array( $field->id, $this->step->get_editable_fields() );
	}


	/**
	 * Target for the gform_field_content filter.
	 *
	 * Checks whether the field should hidden and then ensures that the markup is completely empty for the field.
	 *
	 * @param $content
	 * @param $field
	 * @param $value
	 * @param $lead_id
	 * @param $form_id
	 *
	 * @return string
	 */
	public function filter_gform_field_content( $content, $field, $value, $lead_id, $form_id ) {

		$hidden_token = '<!-- gravityflow_hidden -->';
		$pos = strpos( $content, $hidden_token );
		if ( $pos !== false ) {
			$len_token = strlen( $hidden_token );
			if ( strlen( $content ) > $len_token ) {
				$content = substr( $content, $pos + $len_token );
				if ( $content === false ) {
					$content = '';
				}
			} else {
				$content = '';
			}
		}

		return $content;
	}

	/**
	 * Checks whether the section should be hidden for the given section field.
	 *
	 * Hidden sections contain no editable fields and no non-empty display fields.
	 *
	 * @param $section_field
	 *
	 * @return bool
	 */
	public function is_section_hidden( $section_field ) {

		$has_an_editable_field = false;
		$section_fields = GFCommon::get_section_fields( $this->form, $section_field->id );
		foreach ( $section_fields as $field ) {
			if ( $this->is_editable_field( $field ) ) {
				$has_an_editable_field = true;
				break;
			}
		}

		if ( $has_an_editable_field ) {
			return false;
		}

		return GFCommon::is_section_empty( $section_field, $this->form, $this->entry ) && ! $this->display_empty_fields;
	}

	/**
	 * Target for the gform_field_container filter.
	 *
	 * Removes the markup completely for section fields that are hidden.
	 *
	 * @param $field_container
	 * @param $field
	 * @param $form
	 * @param $css_class
	 * @param $style
	 * @param $field_content
	 *
	 * @return string
	 */
	public function filter_gform_field_container( $field_container, $field, $form, $css_class, $style, $field_content ) {
		if ( $field->type == 'section' && $this->is_section_hidden( $field ) ) {
			$field_container = '';
		}

		return $field_container;
	}

	/**
	 * Target for the gform_has_conditional_logic filter.
	 *
	 * Checks the condtional logic setting and configures the form accordingly.
	 *
	 * @return bool
	 */
	public function filter_gform_has_conditional_logic() {
		$dynamic_conditional_logic_enabled = $this->is_dynamic_conditional_logic_enabled();
		return $dynamic_conditional_logic_enabled;
	}

	/**
	 * Target for the gform_field_css_class filter.
	 *
	 * Checks the step settings and adds the appropriate classes.
	 *
	 * @param $classes
	 * @param $field
	 * @param $form
	 *
	 * @return string
	 */
	public function filter_gform_field_css_class( $classes, $field, $form ) {
		$is_editable = $this->is_editable_field( $field );
		$editable_class = $is_editable ? 'gravityflow-editable-field' : '';
		$editable_class .= $is_editable && $this->step->highlight_editable_fields_enabled ? ' ' . $this->step->highlight_editable_fields_class : 'gravityflow-display-field';

		$classes .= ' ' . $editable_class;
		return $classes;
	}
}
