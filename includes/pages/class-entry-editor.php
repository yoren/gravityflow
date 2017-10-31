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
 * @copyright   Copyright (c) 2015-2017, Steven Henty S.L.
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
	 * An array of field IDs of display fields.
	 *
	 * @var array
	 */
	private $_display_fields = array();

	/**
	 * An array of field IDs required for use with calculations.
	 *
	 * @since 1.7.1-dev
	 *
	 * @var array
	 */
	private $_calculation_dependencies = array();

	/**
	 * The content to be displayed for the display fields.
	 *
	 * @var array
	 */
	private $_non_editable_field_content = array();

	/**
	 * The init scripts to be deregistered.
	 *
	 * @var array
	 */
	private $_non_editable_field_script_names = array();

	/**
	 * The Form Object after the non-editable and non-display fields have been removed.
	 *
	 * @var array
	 */
	private $_modified_form;

	/**
	 * Used to help determine whether the gform_product_total script should be output for the coupon field.
	 *
	 * @var bool
	 */
	private $_has_editable_product_field = false;

	/**
	 * An array of post field IDs to be used when updating the post created from the entry.
	 *
	 * @since 1.9.2-dev
	 *
	 * @var array
	 */
	private $_update_post_fields = array();

	/**
	 * An array of post image field IDs to be used when updating the post created from the entry.
	 *
	 * @since 1.9.2-dev
	 *
	 * @var array
	 */
	private $_update_post_images = array();

	/**
	 * @since 1.9.2-dev
	 *
	 * @var Gravity_Flow_Entry_Editor[]
	 */
	private static $_instance = array();

	/**
	 * Get an instance of this class.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param array             $form
	 * @param array             $entry
	 * @param Gravity_Flow_Step $step
	 * @param bool|null         $display_empty_fields
	 *
	 * @return Gravity_Flow_Entry_Editor
	 */
	public static function get_instance( $form, $entry, $step, $display_empty_fields = null ) {
		$key = $step->get_id() . '|' . $entry['id'];

		if ( empty( self::$_instance[ $key ] ) ) {
			self::$_instance[ $key ] = new self( $form, $entry, $step, $display_empty_fields );
		} elseif ( $display_empty_fields !== null ) {
			self::$_instance[ $key ]->display_empty_fields = $display_empty_fields;
		}

		return self::$_instance[ $key ];
	}

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

	// # Display -----------------------------------------------------------------------------------------------

	/**
	 * Renders the form. Uses GFFormDisplay::get_form() to display the fields.
	 */
	public function render_edit_form() {
		$this->add_hooks();

		// Impersonate front-end form
		unset( $_GET['page'] );

		require_once( GFCommon::get_base_path() . '/form_display.php' );

		$html = GFFormDisplay::get_form( $this->form['id'], false, false, true, $this->entry );

		$this->remove_hooks();

		echo $html;
	}

	/**
	 * Add the filters and actions required to modify the form markup for this step.
	 */
	public function add_hooks() {
		add_filter( 'gform_pre_render', array( $this, 'filter_gform_pre_render' ), 999 );
		add_filter( 'gform_submit_button', '__return_empty_string' );
		add_filter( 'gform_disable_view_counter', '__return_true' );
		add_filter( 'gform_field_input', array( $this, 'filter_gform_field_input' ), 10, 2 );
		add_filter( 'gform_form_tag', '__return_empty_string' );
		add_filter( 'gform_get_form_filter', array( $this, 'filter_gform_get_form_filter' ) );
		add_filter( 'gform_field_container', array( $this, 'filter_gform_field_container' ), 10, 2 );
		add_filter( 'gform_has_conditional_logic', array( $this, 'filter_gform_has_conditional_logic' ), 10, 2 );
		add_filter( 'gform_field_css_class', array( $this, 'filter_gform_field_css_class' ), 10, 2 );

		add_action( 'gform_register_init_scripts', array( $this, 'deregsiter_init_scripts' ), 11 );
	}

	/**
	 * Remove the filters and actions.
	 */
	public function remove_hooks() {
		remove_filter( 'gform_pre_render', array( $this, 'filter_gform_pre_render' ), 999 );
		remove_filter( 'gform_submit_button', '__return_empty_string' );
		remove_filter( 'gform_disable_view_counter', '__return_true' );
		remove_filter( 'gform_field_input', array( $this, 'filter_gform_field_input' ), 10 );
		remove_filter( 'gform_form_tag', '__return_empty_string' );
		remove_filter( 'gform_get_form_filter', array( $this, 'filter_gform_get_form_filter' ) );
		remove_filter( 'gform_field_container', array( $this, 'filter_gform_field_container' ), 10 );
		remove_filter( 'gform_has_conditional_logic', array( $this, 'filter_gform_has_conditional_logic' ), 10 );
		remove_filter( 'gform_field_css_class', array( $this, 'filter_gform_field_css_class' ), 10 );

		remove_action( 'gform_register_init_scripts', array( $this, 'deregsiter_init_scripts' ), 11 );
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
		$form                              = $this->remove_page_fields( $form );
		$fields                            = array();
		$dynamic_conditional_logic_enabled = $this->_is_dynamic_conditional_logic_enabled;

		/**
		 * Process all other field types.
		 *
		 * @var GF_Field $field
		 */
		foreach ( $form['fields'] as $field ) {
			if ( $field->type == 'section' ) {
				// Unneeded section fields will be removed via filter_gform_field_container().

				$field->adminOnly = false;
				$fields[]         = $field;
				continue;
			}

			if ( $dynamic_conditional_logic_enabled ) {
				$conditional_logic_fields      = GFFormDisplay::get_conditional_logic_fields( $form, $field->id );
				$field->conditionalLogicFields = $conditional_logic_fields;
			}

			$field->gravityflow_is_display_field = $this->is_display_field( $field );

			// Remove unneeded fields from the form to prevent JS errors resulting from scripts expecting fields to be present and visible.
			if ( $this->can_remove_field( $field ) ) {
				continue;
			}

			$is_product_field = GFCommon::is_product_field( $field->type );

			if ( ! $this->has_product_fields && $is_product_field ) {
				$this->has_product_fields = true;
			}

			if ( ! $this->is_editable_field( $field ) ) {
				$content = $this->get_non_editable_field( $field );

				if ( empty( $content ) ) {
					continue;
				}

				$this->_non_editable_field_content[ $field->id ] = $content;
				$this->_non_editable_field_script_names[]        = $field->type . '_' . $field->id;

				if ( $field->type == 'tos' ) {
					$field->gwtermsofservice_require_scroll = false;
				}

				$field->description = null;
				$field->maxLength   = null;
			} else {
				$field->gravityflow_is_editable = true;
				if ( ! $this->_has_editable_product_field && $is_product_field && $field->type != 'total' ) {
					$this->_has_editable_product_field = true;
				}
			}

			if ( empty( $field->label ) ) {
				$field->label = $field->adminLabel;
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

		$form['fields']       = $fields;
		$this->_modified_form = $form;

		return $form;
	}

	/**
	 * Removes the form button logic and page fields so they are not taken into account when processing conditional logic for other fields.
	 * Also disables save and continue.
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array
	 */
	public function remove_page_fields( $form ) {
		unset( $form['save'] );
		unset( $form['button']['conditionalLogic'] );

		$dynamic_conditional_logic_enabled = $this->_is_dynamic_conditional_logic_enabled;

		/* @var GF_Field $field */
		foreach ( $form['fields'] as $key => $field ) {
			if ( $field->type == 'page' ) {
				unset( $form['fields'][ $key ] );
				continue;
			}

			$is_applicable_field = $this->is_editable_field( $field );

			if ( $is_applicable_field && $field->has_calculation() ) {
				$this->set_calculation_dependencies( $field->calculationFormula );
			}

			if ( ! $is_applicable_field ) {
				// Populate the $_display_fields array.
				$is_applicable_field = $this->is_display_field( $field, true );
			}

			if ( ! $dynamic_conditional_logic_enabled || ! $is_applicable_field ) {
				// Clear the field conditional logic properties as conditional logic is not enabled for the step or the field is not for display or editable.
				$field->conditionalLogicFields = null;
				$field->conditionalLogic       = null;
			}
		}

		return $form;
	}

	/**
	 * Add the IDs of any fields in the formula to the $_calculation_dependencies array.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param string $formula The calculation formula to be evaluated.
	 */
	public function set_calculation_dependencies( $formula ) {
		if ( empty( $formula ) ) {
			return;
		}

		preg_match_all( '/{[^{]*?:(\d+).*?}/mi', $formula, $matches, PREG_SET_ORDER );
		if ( ! empty( $matches ) ) {
			foreach ( $matches as $match ) {
				$field_id = rgar( $match, 1 );
				if ( $field_id && ! $this->is_calculation_dependency( $field_id ) ) {
					$this->_calculation_dependencies[] = $field_id;
				}
			}
		}
	}

	/**
	 * Checks whether a field is required for calculations.
	 *
	 * @since 1.7.1-dev
	 *
	 * @param GF_Field|string $field The field object or field ID to be checked.
	 *
	 * @return bool
	 */
	public function is_calculation_dependency( $field ) {
		$field_id = is_object( $field ) ? $field->id : $field;

		return in_array( $field_id, $this->_calculation_dependencies );
	}

	/**
	 * Determines if the field can be removed from the form object.
	 *
	 * Fields involved in conditional logic must always be added to the form.
	 *
	 * @param GF_Field $field The current field.
	 *
	 * @return bool
	 */
	public function can_remove_field( $field ) {
		$can_remove_field = ! ( $this->is_editable_field( $field ) || $this->is_display_field( $field ) || $this->is_calculation_dependency( $field ) ) && empty( $field->conditionalLogicFields );

		return $can_remove_field;
	}

	/**
	 * Target for the gform_field_input filter.
	 *
	 * Handles the construction of the field input. Returns markup for the editable field or the display value.
	 *
	 * @param string $html The field input markup.
	 * @param GF_Field $field The current field.
	 *
	 * @return string
	 */
	public function filter_gform_field_input( $html, $field ) {

		if ( ! $this->is_editable_field( $field ) ) {
			return rgar( $this->_non_editable_field_content, $field->id );
		}

		if ( ! empty( $html ) ) {
			// the field input has already been set via the gform_field_input filter. e.g. the Signature Add-On < v3
			return $html;
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

			if ( $field->get_input_type() == 'multiselect' && $field->storageType === 'json' ) {
				$value = json_decode( $value, true );
			}
		}

		if ( $field->get_input_type() == 'fileupload' ) {
			$field->_is_entry_detail = true;
		}

		$value = apply_filters( 'gravityflow_field_value_entry_editor', $value, $field, $this->form, $this->entry, $this->step );

		$html = $field->get_field_input( $this->form, $value, $this->entry );
		$html .= $this->maybe_get_coupon_script( $field );

		if ( $field->type === 'chainedselect' && function_exists( 'gf_chained_selects' ) ) {
			if ( ! wp_script_is( 'gform_chained_selects' ) ) {
				wp_enqueue_script( 'gform_chained_selects' );
				gf_chained_selects()->localize_scripts();
			}

			if ( ! $this->_is_dynamic_conditional_logic_enabled && wp_script_is( 'gform_conditional_logic' ) ) {
				$script = "if ( typeof window.gf_form_conditional_logic === 'undefined' ) { window.gf_form_conditional_logic = []; }";
				GFFormDisplay::add_init_script( $field->formId, 'conditional_logic', GFFormDisplay::ON_PAGE_RENDER, $script );
			}
		}

		return $html;
	}

	/**
	 * Get the gform_product_total script for the coupon field when there aren't any editable product fields.
	 *
	 * @param GF_Field $field The field currently being processed.
	 *
	 * @return string
	 */
	public function maybe_get_coupon_script( $field ) {
		if ( $field->type != 'coupon' || $this->_has_editable_product_field ) {
			return '';
		}

		$total = GFCommon::get_order_total( $this->form, $this->entry );

		return "<script type='text/javascript'>gform.addFilter('gform_product_total', function (total, formId) {return {$total};}, 49);</script>";
	}

	/**
	 * Checks whether dynamic conditional logic is enabled.
	 *
	 * @return bool
	 */
	public function is_dynamic_conditional_logic_enabled() {
		return $this->step && $this->step->conditional_logic_editable_fields_enabled && $this->step->conditional_logic_editable_fields_mode != 'page_load' && gravity_flow()->fields_have_conditional_logic( $this->form );
	}

	/**
	 * Target for the gform_get_form_filter filter.
	 * Strips the closing form tag and replaces the Gravity Forms token for Gravity Flow's token.
	 *
	 * @param string $form_string The form markup.
	 *
	 * @return string
	 */
	public function filter_gform_get_form_filter( $form_string ) {
		$form_string = str_replace( 'gform_submit', 'gravityflow_submit', $form_string );
		$form_string = str_replace( '</form>', '', $form_string );

		return $form_string;
	}

	/**
	 * Generates and returns the markup for a display field.
	 *
	 * @param GF_Field $field
	 *
	 * @return string
	 */
	public function get_non_editable_field( $field ) {

		if ( $field->type == 'html' ) {
			$html = GFCommon::replace_variables( $field->content, $this->form, $this->entry, false, true, false, 'html' );
			$html = do_shortcode( $html );

			return $html;
		}

		$html  = '';

		$value = RGFormsModel::get_lead_field_value( $this->entry, $field );

		$conditional_logic_dependency = $this->_is_dynamic_conditional_logic_enabled && ! empty( $field->conditionalLogicFields );

		if ( $conditional_logic_dependency || $this->is_calculation_dependency( $field ) ) {
			$html = $field->get_field_input( $this->form, $value, $this->entry );
		}

		if ( ! $this->is_display_field( $field ) ) {

			return $html;
		}

		if ( $html ) {
			$html = '<div style="display:none;">' . $html . '</div>';
		}

		$value = $this->maybe_get_product_calculation_value( $value, $field );

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
	 * If this is a calculated product field ensure the input values are set.
	 *
	 * @param mixed $value The field value.
	 * @param GF_Field $field The current field object.
	 *
	 * @return mixed
	 */
	public function maybe_get_product_calculation_value( $value, $field ) {
		if ( $field->type == 'product' && $field->has_calculation() ) {
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

		return $value;
	}

	/**
	 * Checks whether the given field is a display field and whether it should be displayed.
	 *
	 * @param GF_Field $field The field to be checked.
	 * @param bool $is_init Return after checking the $_display_fields array? Default is false.
	 *
	 * @return bool
	 */
	public function is_display_field( $field, $is_init = false ) {
		if ( in_array( $field->id, $this->_display_fields ) ) {
			return true;
		}

		if ( ! $is_init ) {
			return false;
		}

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

		if ( $display_field ) {
			$this->_display_fields[] = $field->id;
		}

		return $display_field;
	}

	/**
	 * Checks whether a field is an editable field.
	 *
	 * @param GF_Field $field The field to be checked.
	 *
	 * @return bool
	 */
	public function is_editable_field( $field ) {
		return in_array( $field->id, $this->_editable_fields );
	}

	/**
	 * Check if the current field is hidden.
	 *
	 * @param GF_Field $field The field to be checked.
	 *
	 * @return bool
	 */
	public function is_hidden_field( $field ) {

		return ! $this->is_editable_field( $field ) && ! $this->is_display_field( $field ) && isset( $this->_non_editable_field_content[ $field->id ] );
	}

	/**
	 * Check if the display mode is selected_fields and that all this sections fields are hidden.
	 *
	 * @param GF_Field[] $section_fields The fields located in the current section.
	 *
	 * @return bool
	 */
	public function section_fields_hidden( $section_fields ) {
		if ( $this->step->display_fields_mode == 'selected_fields' ) {
			foreach ( $section_fields as $field ) {
				if ( ! $this->is_hidden_field( $field ) ) {
					return false;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Checks whether the section should be hidden for the given section field.
	 *
	 * Hidden sections contain no editable fields and no non-empty display fields.
	 *
	 * @param GF_Field_Section $section_field The current section field.
	 * @param GF_Field[] $section_fields The fields located in the current section.
	 *
	 * @return bool
	 */
	public function is_section_hidden( $section_field, $section_fields ) {
		if ( ! empty( $section_fields ) ) {
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
	 * Retrieve an array of fields located within the specified section.
	 *
	 * @param int $section_field_id The ID of the current section field.
	 *
	 * @return array
	 */
	public function get_section_fields( $section_field_id ) {
		$section_fields = GFCommon::get_section_fields( $this->_modified_form, $section_field_id );
		if ( count( $section_fields ) >= 1 ) {
			// Remove the section field.
			unset( $section_fields[0] );
		}

		return $section_fields;
	}

	/**
	 * Target for the gform_field_container filter.
	 *
	 * Removes the markup completely for section fields that are hidden.
	 *
	 * Fields with conditional logic remain on the form to avoid JS errors.
	 *
	 * @param $field_container
	 * @param $field
	 *
	 * @return string
	 */
	public function filter_gform_field_container( $field_container, $field ) {
		if ( $field->type == 'section' ) {
			$section_fields = $this->get_section_fields( $field->id );

			if ( $this->section_fields_hidden( $section_fields )
			     || ( $this->is_section_hidden( $field, $section_fields ) && empty( $field->conditionalLogic ) ) // Section fields with conditional logic must be added to the form so fields inside the section can be hidden or displayed dynamically
			) {
				return '';
			}
		}

		if ( $this->is_hidden_field( $field ) ) {
			$field_container = sprintf( '<li id="field_%s_%s" style="display:none;">%s</li>', $field->formId, $field->id, $this->_non_editable_field_content[ $field->id ] );
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
	 *
	 * @return string
	 */
	public function filter_gform_field_css_class( $classes, $field ) {
		$is_editable = $this->is_editable_field( $field );
		$class       = $is_editable ? 'gravityflow-editable-field' : 'gravityflow-display-field';
		if ( $is_editable && $this->step->highlight_editable_fields_enabled ) {
			$class .= ' ' . $this->step->highlight_editable_fields_class;
		}

		$classes .= ' ' . $class;

		return $classes;
	}

	/**
	 * Deregister init scripts for any non-editable fields to prevent js errors.
	 *
	 * @param array $form The filtered form object.
	 */
	public function deregsiter_init_scripts( $form ) {
		$script_names = $this->_non_editable_field_script_names;
		if ( ! empty( $script_names ) ) {
			$init_scripts = GFFormDisplay::$init_scripts[ $form['id'] ];
			if ( ! empty( $init_scripts ) ) {
				$location = GFFormDisplay::ON_PAGE_RENDER;
				foreach ( $script_names as $name ) {
					unset( $init_scripts[ $name . '_' . $location ] );
				}
				GFFormDisplay::$init_scripts[ $form['id'] ] = $init_scripts;
			}

		}
	}

	// # Submission --------------------------------------------------------------------------------------------

	/**
	 * Add a message to the Gravity Flow log file.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param string $message The message to be logged.
	 */
	public function log_debug( $message ) {
		$this->step->log_debug( $message );
	}

	/**
	 * Flushes and reloads the cached entry for the current step.
	 *
	 * @since 1.9.2-dev
	 */
	public function refresh_entry() {
		$this->entry = $this->step->refresh_entry();
	}

	/**
	 * Process the entry detail page submission.
	 *
	 * Validates the note and editable fields.
	 * Updates the entry and post using the submitted values.
	 *
	 * @param string $new_status The new status for the current step.
	 *
	 * @since 1.9.2-dev
	 *
	 * @return bool|WP_Error WP_Error for an invalid note and/or editable fields; False for an invalid referer or true when the entry/post update functionality has run.
	 */
	public function process( $new_status ) {
		$this->log_debug( __METHOD__ . '(): Running.' );

		if ( ! $this->is_valid_referer() ) {
			$this->log_debug( __METHOD__ . '(): Aborting; Invalid referer.' );

			return false;
		}

		$files = $this->get_files_pre_validation();
		$form  = $this->form;

		$is_valid          = $this->step->validate_note( $new_status, $form );
		$is_valid          = $this->is_valid_editable_fields( $is_valid, $form );
		$validation_result = $this->step->get_validation_result( $is_valid, $form, $new_status );

		if ( is_wp_error( $validation_result ) ) {
			// Upload valid temp single files.
			$this->maybe_upload_files( $form, $files );
			$this->log_debug( __METHOD__ . '(): Aborting; Failed validation.' );

			return $validation_result;
		}

		if ( empty( $this->_editable_fields ) ) {
			$this->log_debug( __METHOD__ . '(): Aborting; No editable fields.' );

			return true;
		}

		$original_entry     = $this->entry;
		$previous_assignees = $this->step->get_assignees();

		$this->process_fields();

		remove_action( 'gform_after_update_entry', array( gravity_flow(), 'filter_after_update_entry' ) );
		do_action( 'gform_after_update_entry', $form, $original_entry['id'], $original_entry );
		do_action( "gform_after_update_entry_{$form['id']}", $form, $original_entry['id'], $original_entry );

		$entry = GFFormsModel::get_lead( $original_entry['id'] );
		GFFormsModel::set_entry_meta( $entry, $form );

		$this->refresh_entry();
		$this->maybe_process_post_fields();

		GFCache::flush();

		$this->step->maybe_adjust_assignment( $previous_assignees );

		return true;
	}

	/**
	 * Check the submission belongs to the current step and contains a valid nonce from the entry detail page.
	 *
	 * @since 1.9.2-dev
	 *
	 * @return bool
	 */
	public function is_valid_referer() {
		return isset( $_POST['gforms_save_entry'] ) && rgpost( 'step_id' ) == $this->step->get_id() && check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
	}

	/**
	 * Get an array of uploaded files for the current submission.
	 *
	 * @since 1.9.2-dev
	 *
	 * @return array
	 */
	public function get_files_pre_validation() {
		if ( empty( $this->_editable_fields ) ) {
			return array();
		}

		$files = GFCommon::json_decode( rgpost( 'gform_uploaded_files' ) );
		if ( ! is_array( $files ) ) {
			$files = array();
		}

		GFFormsModel::$uploaded_files[ $this->form['id'] ] = $files;

		return $files;
	}

	/**
	 * Determine if the editable fields for this step are valid.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param bool  $valid The steps current validation state.
	 * @param array $form  The form currently being processed.
	 *
	 * @return bool
	 */
	public function is_valid_editable_fields( $valid, &$form ) {
		if ( empty( $this->_editable_fields ) ) {
			return $valid;
		}

		$this->log_debug( __METHOD__ . '(): Running.' );

		$conditional_logic_enabled           = $this->step->conditional_logic_editable_fields_enabled && gravity_flow()->fields_have_conditional_logic( $form );
		$page_load_conditional_logic_enabled = $conditional_logic_enabled && $this->step->conditional_logic_editable_fields_mode == 'page_load';
		$dynamic_conditional_logic_enabled   = $conditional_logic_enabled && $this->step->conditional_logic_editable_fields_mode != 'page_load';

		$saved_entry = $this->entry;

		if ( ! $conditional_logic_enabled || $page_load_conditional_logic_enabled ) {
			$entry = $saved_entry;
		} else {
			$entry = GFFormsModel::create_lead( $form );
		}

		foreach ( $form['fields'] as $field ) {
			/* @var GF_Field $field */

			if ( ! $this->is_applicable_validation_field( $field, $form, $entry, $page_load_conditional_logic_enabled, $dynamic_conditional_logic_enabled ) ) {
				continue;
			}

			$submission_is_empty = $field->is_value_submission_empty( $form['id'] );

			if ( $field->get_input_type() == 'fileupload' ) {

				if ( $field->isRequired && $submission_is_empty && rgempty( $field->id, $saved_entry ) ) {
					$this->fail_required_validation( $field );
					$valid = false;

					continue;
				}

				$this->validate_editable_field( $field );
				if ( $field->failed_validation ) {
					$valid = false;
				}

				continue;
			}

			if ( $submission_is_empty && $field->isRequired ) {
				$this->fail_required_validation( $field );
				$valid = false;
			} elseif ( ! $submission_is_empty ) {
				$value = GFFormsModel::get_field_value( $field );
				$this->validate_editable_field( $field, $value );

				if ( $field->failed_validation ) {
					$valid = false;
				}
			}
		}

		return $valid;
	}

	/**
	 * Determines if the field needs validating.
	 *
	 * The field needs validating if editable. Fields hidden by conditional logic should not be validated.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param GF_Field $field                               The field properties.
	 * @param array    $form                                The form currently being processed.
	 * @param array    $entry                               The saved entry or the temporary entry created from the current $_POST.
	 * @param bool     $page_load_conditional_logic_enabled Indicates if conditional logic is enabled for the step and set to page_load mode.
	 * @param bool     $dynamic_conditional_logic_enabled   Indicates if conditional logic is enabled for the step and set to dynamic mode.
	 *
	 * @return bool
	 */
	public function is_applicable_validation_field( $field, $form, $entry, $page_load_conditional_logic_enabled, $dynamic_conditional_logic_enabled ) {
		if ( ! $this->is_editable_field( $field ) ) {
			return false;
		}

		if ( $page_load_conditional_logic_enabled ) {
			$field_is_hidden = GFFormsModel::is_field_hidden( $form, $field, array(), $entry );
		} elseif ( $dynamic_conditional_logic_enabled ) {
			$field_is_hidden = GFFormsModel::is_field_hidden( $form, $field, array() );
		} else {
			$field_is_hidden = false;
		}

		return ! $field_is_hidden;
	}

	/**
	 * Update the field properties to indicate the field failed the required validation.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param GF_Field $field The field properties.
	 */
	public function fail_required_validation( $field ) {
		$field->failed_validation  = true;
		$field->validation_message = empty( $field->errorMessage ) ? esc_html__( 'This field is required.', 'gravityflow' ) : $field->errorMessage;
	}

	/**
	 * Validate the field and allow the result to be overridden by the gform_field_validation filter.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param GF_Field $field The field properties.
	 * @param mixed    $value The field value.
	 */
	public function validate_editable_field( $field, $value = '' ) {
		$form = $this->form;

		$field->validate( $value, $form );
		$custom_validation_result = gf_apply_filters( array(
			'gform_field_validation',
			$form['id'],
			$field->id
		), array(
			'is_valid' => $field->failed_validation ? false : true,
			'message'  => $field->validation_message,
		), $value, $form, $field );

		$field->failed_validation  = rgar( $custom_validation_result, 'is_valid' ) ? false : true;
		$field->validation_message = rgar( $custom_validation_result, 'message' );
	}

	/**
	 * Determines if there are any fields which need files uploading to the temporary folder.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param array $form  The form currently being processed.
	 * @param array $files An array of files which have already been uploaded.
	 */
	public function maybe_upload_files( $form, $files ) {
		if ( empty( $this->_editable_fields ) || empty( $_FILES ) ) {
			return;
		}

		$this->log_debug( __METHOD__ . '(): Checking for fields to process.' );

		$target_path = $this->get_temp_files_path( $form['id'] );

		foreach ( $form['fields'] as $field ) {

			/** @var GF_Field $field */
			if ( ! in_array( $field->id, $this->_editable_fields )
			     || ! in_array( $field->get_input_type(), array( 'fileupload', 'post_image' ) )
			     || $field->multipleFiles
			     || $field->failed_validation
			) {
				// Skip fields which are not editable, are the wrong type, or have failed validation.
				continue;
			}

			$files = $this->maybe_upload_temp_file( $field, $files, $target_path );
		}

		GFFormsModel::$uploaded_files[ $form['id'] ] = $files;
	}

	/**
	 * Get the temporary file path and create the folder if it does not already exist.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param int $form_id The ID of the form currently being processed.
	 *
	 * @return string
	 */
	public function get_temp_files_path( $form_id ) {
		$form_upload_path = GFFormsModel::get_upload_path( $form_id );
		$target_path      = $form_upload_path . '/tmp/';

		wp_mkdir_p( $target_path );
		GFCommon::recursive_add_index_file( $form_upload_path );

		return $target_path;
	}

	/**
	 * Upload the file to the temporary folder for the current field.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param GF_Field $field       The field properties.
	 * @param array    $files       An array of files which have already been uploaded.
	 * @param string   $target_path The path to the tmp folder the file should be moved to.
	 *
	 * @return array
	 */
	public function maybe_upload_temp_file( $field, $files, $target_path ) {
		$input_name = "input_{$field->id}";

		if ( empty( $_FILES[ $input_name ]['name'] ) ) {
			return $files;
		}

		$file_info = GFFormsModel::get_temp_filename( $field->formId, $input_name );
		$this->log_debug( __METHOD__ . "(): Uploading temporary file for field: {$field->label}({$field->id} - {$field->type}). File info => " . print_r( $file_info, true ) );

		if ( $file_info && move_uploaded_file( $_FILES[ $input_name ]['tmp_name'], $target_path . $file_info['temp_filename'] ) ) {
			GFFormsModel::set_permissions( $target_path . $file_info['temp_filename'] );
			$files[ $input_name ] = $file_info['uploaded_filename'];
			$this->log_debug( __METHOD__ . '(): File uploaded successfully.' );
		} else {
			$this->log_debug( __METHOD__ . "(): File could not be uploaded: tmp_name: {$_FILES[ $input_name ]['tmp_name']} - target location: " . $target_path . $file_info['temp_filename'] );
		}

		return $files;
	}

	/**
	 * Trigger the updating of entry values for the appropriate fields.
	 *
	 * @since 1.9.2-dev
	 */
	public function process_fields() {
		$entry = $this->entry;

		if ( ! $entry ) {
			return;
		}

		$this->log_debug( __METHOD__ . '(): Running.' );

		$form               = $this->form;
		$total_fields       = array();
		$calculation_fields = array();

		foreach ( $form['fields'] as $field ) {
			/** @var GF_Field $field */

			//Ignore fields that are marked as display only
			if ( $field->displayOnly && $field->type != 'password' ) {
				continue;
			}

			//process total field after all fields have been saved
			if ( $field->type == 'total' ) {
				$total_fields[] = $field;
				continue;
			}

			// process calculation fields after all fields have been saved (moved after the is hidden check)
			if ( $field->has_calculation() ) {
				$calculation_fields[] = $field;
				continue;
			}

			if ( ! in_array( $field->id, $this->_editable_fields ) ) {
				continue;
			}

			if ( ! $this->step->conditional_logic_editable_fields_enabled ) {
				$field->conditionalLogic = null;
			}

			if ( in_array( $field->get_input_type(), array( 'fileupload', 'post_image' ) ) ) {
				$this->maybe_save_field_files( $field, $form, $entry );
				continue;
			}

			if ( $field->type == 'post_category' ) {
				$field = GFCommon::add_categories_as_choices( $field, '' );
			}

			$inputs = $field->get_entry_inputs();

			if ( is_array( $inputs ) ) {
				foreach ( $inputs as $input ) {
					$this->maybe_update_input( $form, $field, $entry, $input['id'] );
				}
			} else {
				$this->maybe_update_input( $form, $field, $entry, $field->id );
			}
		}

		$this->maybe_process_calculation_fields( $calculation_fields, $entry );
		$this->maybe_process_total_fields( $total_fields );
	}

	/**
	 * Update the entry with the calculation field values.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param GF_Field[] $fields The calculation fields to be processed.
	 * @param array      $entry  The entry being updated.
	 */
	public function maybe_process_calculation_fields( $fields, $entry ) {
		if ( empty( $fields ) ) {
			return;
		}

		$this->log_debug( __METHOD__ . '(): Saving calculation fields.' );

		foreach ( $fields as $field ) {
			// Make sure that the value gets recalculated
			$field->conditionalLogic = null;

			$inputs = $field->get_entry_inputs();

			if ( is_array( $inputs ) ) {

				if ( ! in_array( $field->id, $this->_editable_fields ) ) {
					// Make sure calculated product names and quantities are saved as if they're submitted.
					$value                                 = array( $field->id . '.1' => $entry[ $field->id . '.1' ] );
					$_POST[ 'input_' . $field->id . '_1' ] = $field->get_field_label( false, $value );
					$quantity                              = trim( $entry[ $field->id . '.3' ] );
					if ( $field->disableQuantity && empty( $quantity ) ) {
						$_POST[ 'input_' . $field->id . '_3' ] = 1;
					} else {
						$_POST[ 'input_' . $field->id . '_3' ] = $quantity;
					}
				}
				foreach ( $inputs as $input ) {
					$this->maybe_update_input( $this->form, $field, $entry, $input['id'] );
				}
			} else {
				$this->maybe_update_input( $this->form, $field, $entry, $field->id );
			}
		}
	}

	/**
	 * Update the entry with the total field values.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param GF_Field[] $fields The total fields to be processed.
	 */
	public function maybe_process_total_fields( $fields ) {
		$entry = GFFormsModel::get_lead( $this->entry['id'] );
		GFFormsModel::refresh_product_cache( $this->form, $entry );

		if ( empty( $fields ) ) {
			return;
		}

		$this->log_debug( __METHOD__ . '(): Saving total fields.' );

		foreach ( $fields as $field ) {
			$this->maybe_update_input( $this->form, $field, $entry, $field->id );
		}
	}

	/**
	 * If any new files have been uploaded save them to the entry.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param GF_Field $field The current fields properties.
	 * @param array    $form  The form currently being processed.
	 * @param array    $entry The entry currently being processed.
	 */
	public function maybe_save_field_files( $field, $form, $entry ) {
		$input_name = 'input_' . $field->id;
		if ( $field->multipleFiles && ! isset( GFFormsModel::$uploaded_files[ $form['id'] ][ $input_name ] ) ) {
			// No new files uploaded, abort.
			return;
		}

		$existing_value = rgar( $entry, $field->id );
		$value          = $field->get_value_save_entry( $existing_value, $form, $input_name, $entry['id'], $entry );

		if ( ! empty( $value ) && $existing_value != $value ) {
			$result = GFAPI::update_entry_field( $entry['id'], $field->id, $value );
			$this->log_debug( __METHOD__ . "(): Saving: {$field->label}(#{$field->id} - {$field->type}). Result: " . var_export( $result, 1 ) );
			$this->maybe_pre_process_post_image( $field );
		}
	}

	/**
	 * If this is a post image field add it to the queue for processing when the post is updated.
	 * Also delete the previous image uploaded using this field from the post.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param GF_Field $field The current fields properties.
	 */
	public function maybe_pre_process_post_image( $field ) {
		if ( GFCommon::is_post_field( $field ) && ! in_array( $field->id, $this->_update_post_images ) ) {
			$this->_update_post_images[] = $field->id;

			$post_images = gform_get_meta( $this->entry['id'], '_post_images' );
			if ( $post_images && isset( $post_images[ $field->id ] ) ) {
				wp_delete_attachment( $post_images[ $field->id ] );
				unset( $post_images[ $field->id ] );
				gform_update_meta( $this->entry['id'], '_post_images', $post_images, $this->form['id'] );
			}
		}
	}

	/**
	 * Update the input value in the entry.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param array      $form     The form currently being processed.
	 * @param GF_Field   $field    The current fields properties.
	 * @param array      $entry    The entry currently being processed.
	 * @param int|string $input_id The ID of the field or input currently being processed.
	 */
	public function maybe_update_input( $form, $field, &$entry, $input_id ) {
		$input_name = 'input_' . str_replace( '.', '_', $input_id );

		if ( $field->enableCopyValuesOption && rgpost( 'input_' . $field->id . '_copy_values_activated' ) ) {
			$source_field_id   = $field->copyValuesOptionField;
			$source_input_name = str_replace( 'input_' . $field->id, 'input_' . $source_field_id, $input_name );
			$value             = rgpost( $source_input_name );
		} else {
			$value = rgpost( $input_name );
		}

		$existing_value = rgar( $entry, $input_id );
		$value          = GFFormsModel::maybe_trim_input( $value, $form['id'], $field );
		$value          = GFFormsModel::prepare_value( $form, $field, $value, $input_name, $entry['id'], $entry );

		if ( $existing_value != $value ) {
			$result = GFAPI::update_entry_field( $entry['id'], $input_id, $value );
			$this->log_debug( __METHOD__ . "(): Saving: {$field->label}(#{$input_id} - {$field->type}). Result: " . var_export( $result, 1 ) );
			if ( $result ) {
				$entry[ $input_id ] = $value;
			}

			if ( GFCommon::is_post_field( $field ) && ! in_array( $field->id, $this->_update_post_fields ) ) {
				$this->_update_post_fields[] = $field->id;
			}
		}
	}

	/**
	 * If a post exists for this entry initiate the update.
	 *
	 * @since 1.9.2-dev
	 */
	public function maybe_process_post_fields() {
		$this->log_debug( __METHOD__ . '(): Running.' );

		$post_id = $this->entry['post_id'];

		if ( empty( $post_id ) ) {
			$this->log_debug( __METHOD__ . '(): Aborting; No post id.' );

			return;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			$this->log_debug( __METHOD__ . '(): Aborting; Unable to get post.' );

			return;
		}

		$result = $this->process_post_fields( $post );
		$this->log_debug( __METHOD__ . '(): wp_update_post result => ' . print_r( $result, 1 ) );
	}

	/**
	 * Update the post with the field values which have changed.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param WP_Post $post The post to be updated.
	 *
	 * @return int|WP_Error
	 */
	public function process_post_fields( $post ) {
		$this->log_debug( __METHOD__ . '(): Running.' );

		$form                 = $this->form;
		$entry                = $this->entry;
		$post_images          = $this->process_post_images( $form, $entry );
		$has_content_template = rgar( $form, 'postContentTemplateEnabled' );

		foreach ( $this->_update_post_fields as $field_id ) {

			$field = GFFormsModel::get_field( $form, $field_id );
			$value = GFFormsModel::get_lead_field_value( $entry, $field );

			switch ( $field->type ) {
				case 'post_title' :
					$post_title       = $this->get_post_title( $value, $form, $entry, $post_images );
					$post->post_title = $post_title;
					$post->post_name  = $post_title;
					break;

				case 'post_content' :
					if ( ! $has_content_template ) {
						$post->post_content = GFCommon::encode_shortcodes( $value );
					}
					break;

				case 'post_excerpt' :
					$post->post_excerpt = GFCommon::encode_shortcodes( $value );
					break;

				case 'post_tags' :
					$this->set_post_tags( $value, $post->ID );
					break;

				case 'post_category' :
					$this->set_post_categories( $value, $post->ID );
					break;

				case 'post_custom_field' :
					$this->set_post_meta( $field, $value, $form, $entry, $post_images );
					break;
			}
		}

		if ( $has_content_template ) {
			$post->post_content = GFFormsModel::process_post_template( $form['postContentTemplate'], 'post_content', $post_images, array(), $form, $entry );
		}

		return wp_update_post( $post, true );
	}

	/**
	 * Attach any new images to the post and set the featured image.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param array $form  The form currently being processed.
	 * @param array $entry The entry currently being processed.
	 *
	 * @return array
	 */
	public function process_post_images( $form, $entry ) {
		$this->log_debug( __METHOD__ . '(): Running.' );

		$post_id     = $entry['post_id'];
		$post_images = gform_get_meta( $entry['id'], '_post_images' );
		if ( ! $post_images ) {
			$post_images = array();
		}

		foreach ( $this->_update_post_images as $field_id ) {
			$value = rgar( $entry, $field_id );
			list( $url, $title, $caption, $description ) = rgexplode( '|:|', $value, 4 );

			if ( empty( $url ) ) {
				continue;
			}

			$image_meta = array(
				'post_excerpt' => $caption,
				'post_content' => $description,
			);

			// Adding title only if it is not empty. It will default to the file name if it is not in the array.
			if ( ! empty( $title ) ) {
				$image_meta['post_title'] = $title;
			}

			$media_id = GFFormsModel::media_handle_upload( $url, $post_id, $image_meta );

			if ( $media_id ) {
				$post_images[ $field_id ] = $media_id;

				// Setting the featured image.
				$field = RGFormsModel::get_field( $form, $field_id );
				if ( $field && $field->postFeaturedImage ) {
					$result = set_post_thumbnail( $post_id, $media_id );
				}
			}

		}

		if ( ! empty( $post_images ) ) {
			gform_update_meta( $entry['id'], '_post_images', $post_images, $form['id'] );
		}

		return $post_images;
	}

	/**
	 * Get the post title.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param string $value       The entry field value.
	 * @param array  $form        The form currently being processed.
	 * @param array  $entry       The entry currently being processed.
	 * @param array  $post_images The images which have been attached to the post.
	 *
	 * @return string
	 */
	public function get_post_title( $value, $form, $entry, $post_images ) {
		if ( rgar( $form, 'postTitleTemplateEnabled' ) ) {
			return GFFormsModel::process_post_template( $form['postTitleTemplate'], 'post_title', $post_images, array(), $form, $entry );
		}

		return GFCommon::encode_shortcodes( $value );
	}

	/**
	 * Set the post tags.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param string|array $value   The entry field value.
	 * @param int          $post_id The ID of the post created from the current entry.
	 */
	public function set_post_tags( $value, $post_id ) {
		$post_tags = array( $value ) ? array_values( $value ) : explode( ',', $value );

		wp_set_post_tags( $post_id, $post_tags, false );
	}

	/**
	 * Set the post categories.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param string|array $value   The entry field value.
	 * @param int          $post_id The ID of the post created from the current entry.
	 */
	public function set_post_categories( $value, $post_id ) {
		$post_categories = array();

		foreach ( explode( ',', $value ) as $cat_string ) {
			$cat_array = explode( ':', $cat_string );
			// the category id is the last item in the array, access it using end() in case the category name includes colons.
			array_push( $post_categories, end( $cat_array ) );
		}

		wp_set_post_categories( $post_id, $post_categories, false );
	}

	/**
	 * Set the post meta.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param GF_Field     $field       The Post Custom Field.
	 * @param string|array $value       The entry field value.
	 * @param array        $form        The form currently being processed.
	 * @param array        $entry       The entry currently being processed.
	 * @param array        $post_images The images which have been attached to the post.
	 */
	public function set_post_meta( $field, $value, $form, $entry, $post_images ) {
		$post_id = $entry['post_id'];

		delete_post_meta( $post_id, $field->postCustomFieldName );

		if ( ! empty( $field->customFieldTemplateEnabled ) ) {
			$value = GFFormsModel::process_post_template( $field->customFieldTemplate, 'post_custom_field', $post_images, array(), $form, $entry );
		}

		switch ( $field->inputType ) {
			case 'list' :
				$value = maybe_unserialize( $value );
				if ( is_array( $value ) ) {
					foreach ( $value as $item ) {
						if ( is_array( $item ) ) {
							$item = implode( '|', $item );
						}

						if ( ! rgblank( $item ) ) {
							add_post_meta( $post_id, $field->postCustomFieldName, $item );
						}
					}
				}
				break;

			case 'multiselect' :
			case 'checkbox' :
				$value = ! is_array( $value ) ? explode( ',', $value ) : $value;
				foreach ( $value as $item ) {
					if ( ! rgblank( $item ) ) {
						add_post_meta( $post_id, $field->postCustomFieldName, $item );
					}
				}
				break;

			case 'date' :
				$value = GFCommon::date_display( $value, $field->dateFormat );
				if ( ! rgblank( $value ) ) {
					add_post_meta( $post_id, $field->postCustomFieldName, $value );
				}
				break;

			default :
				if ( ! rgblank( $value ) ) {
					add_post_meta( $post_id, $field->postCustomFieldName, $value );
				}
				break;
		}
	}

}
