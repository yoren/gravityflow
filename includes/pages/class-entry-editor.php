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

	/**
	 * Indicates if dynamic conditional logic is enabled.
	 *
	 * @var bool
	 */
	private $_is_dynamic_conditional_logic_enabled;

	/**
	 * An array of field IDs which the user can edit.
	 * 
	 * @var array
	 */
	private $_editable_fields;

	/**
	 * The content to be displayed for the display fields.
	 *
	 * @var array
	 */
	private $_display_field_content = array();

	/**
	 * The Form Object after the non-editable and non-display fields have been removed.
	 *
	 * @var array
	 */
	private $_modified_form;

	/**
	 * Gravity_Flow_Entry_Editor constructor.
	 *
	 * @param array $form
	 * @param array $entry
	 * @param Gravity_Flow_Step $step
	 * @param bool $display_empty_fields
	 */
	public function __construct( $form, $entry, $step, $display_empty_fields ) {
		$this->form                                  = $form;
		$this->entry                                 = $entry;
		$this->step                                  = $step;
		$this->display_empty_fields                  = $display_empty_fields;
		$this->_is_dynamic_conditional_logic_enabled = $this->is_dynamic_conditional_logic_enabled();
		$this->_editable_fields                      = $step->get_editable_fields();
	}


	/**
	 * Renders the form. Uses GFFormDisplay::get_form() to display the fields.
	 */
	public function render_edit_form() {

		add_filter( 'gform_pre_render', array( $this, 'filter_gform_pre_render' ), 999, 3 );
		add_filter( 'gform_submit_button', array( $this, 'filter_gform_submit_button' ), 10, 2 );
		add_filter( 'gform_disable_view_counter', '__return_true' );
		add_filter( 'gform_field_input', array( $this, 'filter_gform_field_input' ), 10, 5 );
		add_filter( 'gform_form_tag', array( $this, 'filter_gform_form_tag' ), 10, 2 );
		add_filter( 'gform_get_form_filter', array( $this, 'filter_gform_get_form_filter' ), 10, 2 );
		add_filter( 'gform_field_container', array( $this, 'filter_gform_field_container' ), 10, 6 );
		add_filter( 'gform_has_conditional_logic', array( $this, 'filter_gform_has_conditional_logic' ), 10, 2 );

		add_filter( 'gform_field_css_class', array( $this, 'filter_gform_field_css_class' ), 10, 3 );

		// Impersonate front-end form
		unset( $_GET['page'] );

		require_once( GFCommon::get_base_path() . '/form_display.php' );

		$html = GFFormDisplay::get_form( $this->form['id'], false, false, true, $this->entry );

		remove_filter( 'gform_pre_render', array( $this, 'filter_gform_pre_render' ), 999 );
		remove_filter( 'gform_submit_button', array( $this, 'render_gform_submit_button' ), 10 );
		remove_filter( 'gform_disable_view_counter', '__return_true' );
		remove_filter( 'gform_field_input', array( $this, 'filter_gform_field_input' ), 10 );
		remove_filter( 'gform_form_tag', array( $this, 'filter_gform_form_tag' ), 10 );
		remove_filter( 'gform_get_form_filter', array( $this, 'filter_gform_get_form_filter' ), 10 );
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
		$fields          = array();
		/** @var GF_Field $field */
		foreach ( $existing_fields as $field ) {
			if ( $field->type !== 'page' ) {

				if ( ! $this->is_editable_field( $field ) && ! $this->is_display_field( $field ) ) {
					continue;
				}

				if ( ! $this->has_product_fields && GFCommon::is_product_field( $field->type ) ) {
					$this->has_product_fields = true;
				}

				if ( ! $this->is_editable_field( $field ) ) {
					if ( $field->type != 'section' ) {
						$content = $this->get_display_field( $field );

						if ( empty( $content ) ) {
							continue;
						}

						$this->_display_field_content[ $field->id ] = $content;
					}

					if ( $field->type == 'tos' ) {
						$field->gwtermsofservice_require_scroll = false;
					}

					$field->description = null;
					$field->maxLength   = null;
				}

				$field->adminOnly  = false;
				$field->adminLabel = '';

				if ( $field->type === 'hidden' ) {
					// Render hidden fields as text fields
					$field       = new GF_Field_Text( $field );
					$field->type = 'text';
				}

				$fields[] = $field;
			}
		}

		$form['fields'] = $fields;

		// disable save and continue
		unset( $form['save'] );
		unset( $form['button']['conditionalLogic'] );
		$this->_modified_form = $form;

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
	 * Target for the gform_field_input filter.
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

		if ( ! $this->is_editable_field( $field ) ) {
			return rgar( $this->_display_field_content, $field->id );
		}

		if ( ! empty( $html ) ) {
			// the field input has already been set via the gform_field_input filter. e.g. the Signature Add-On < v3
			return $html;
		}

		$dynamic_conditional_logic_enabled = $this->_is_dynamic_conditional_logic_enabled;

		if ( ! $dynamic_conditional_logic_enabled ) {
			$field->conditionalLogicFields = null;
		}


		$posted_form_id = rgpost( 'gravityflow_submit' );
		if ( $posted_form_id == $this->form['id'] && rgpost( 'step_id' ) == $this->step->get_id() ) {
			// updated or failed validation
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

		$value = apply_filters( 'gravityflow_field_value_entry_editor', $value, $field, $this->form, $this->entry, $this->step );

		$html = $field->get_field_input( $this->form, $value, $this->entry );

		return $html;
	}

	/**
	 * Checks whether dynamic conditional logic is enabled.
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
	 *
	 * @return string
	 */
	public function get_display_field( $field ) {
		if ( $field->type == 'html' ) {
			$html = GFCommon::replace_variables( $field->content, $this->form, $this->entry, false, true, false, 'html' );

			return $html;
		}

		$html                              = '';
		$value                             = RGFormsModel::get_lead_field_value( $this->entry, $field );
		$dynamic_conditional_logic_enabled = $this->_is_dynamic_conditional_logic_enabled;
		if ( $dynamic_conditional_logic_enabled ) {
			$conditional_logic_fields = GFFormDisplay::get_conditional_logic_fields( $this->form, $field->id );
			if ( ! empty( $conditional_logic_fields ) ) {
				$field->conditionalLogicFields = $conditional_logic_fields;
				$field_input                   = $field->get_field_input( $this->form, $value, $this->entry );
				$html                          = '<div style="display:none;">' . $field_input . '</div>';
			}
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
				$display_value = '';
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
		$display_field           = true;
		$display_fields_mode     = $this->step->display_fields_mode;
		$display_fields_selected = is_array( $this->step->display_fields_selected ) ? $this->step->display_fields_selected : array();

		if ( $display_fields_mode == 'selected_fields' ) {
			if ( ! in_array( $field->id, $display_fields_selected ) ) {
				$display_field = false;
			}
		} else {
			if ( GFFormsModel::is_field_hidden( $this->form, $field, array(), $this->entry ) ) {
				$display_field = false;
			}
			$display_field = (bool) apply_filters( 'gravityflow_workflow_detail_display_field', $display_field, $field, $this->form, $this->entry, $this->step );
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
		return in_array( $field->id, $this->_editable_fields );
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
		$section_fields = GFCommon::get_section_fields( $this->_modified_form, $section_field->id );

		if ( count( $section_fields ) > 1 ) {
			foreach ( $section_fields as $field ) {
				if ( $this->is_editable_field( $field ) || $this->is_display_field( $field ) ) {

					return false;
				}
			}

			if ( $this->step->display_fields_mode == 'all_fields' ) {

				return GFCommon::is_section_empty( $section_field, $this->_modified_form, $this->entry ) || ! $this->display_empty_fields;
			}
		}

		return true;
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
	 * Checks the conditional logic setting and configures the form accordingly.
	 *
	 * @return bool
	 */
	public function filter_gform_has_conditional_logic() {

		return $this->_is_dynamic_conditional_logic_enabled;
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
		$class       = $is_editable ? 'gravityflow-editable-field' : 'gravityflow-display-field';
		if ( $is_editable && $this->step->highlight_editable_fields_enabled ) {
			$class .= ' ' . $this->step->highlight_editable_fields_class;
		}

		$classes .= ' ' . $class;

		return $classes;
	}
}
