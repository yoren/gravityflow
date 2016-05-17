<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Field_Discussion extends GF_Field_Textarea {

	public $type = 'workflow_discussion';

	public function add_button( $field_groups ) {
		$field_groups = $this->maybe_add_workflow_field_group( $field_groups );

		return parent::add_button( $field_groups );
	}

	public function maybe_add_workflow_field_group( $field_groups ) {
		foreach ( $field_groups as $field_group ) {
			if ( $field_group['name'] == 'workflow_fields' ) {
				return $field_groups;
			}
		}
		$field_groups[] = array( 'name' => 'workflow_fields', 'label' => __( 'Workflow Fields', 'gravityflowdiscussion' ), 'fields' => array() );
		return $field_groups;
	}

	public function get_form_editor_button() {
		return array(
			'group' => 'workflow_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'maxlen_setting',
			'size_setting',
			'rules_setting',
			'visibility_setting',
			'duplicate_setting',
			'default_value_textarea_setting',
			'placeholder_textarea_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function get_form_editor_field_title() {
		return __( 'Discussion', 'gravityflowdiscussion' );
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		$return = $value;
		if ( $return ) {
			$discussion = json_decode( $value, ARRAY_A );
			if ( is_array( $discussion ) ) {
				$item = array_pop( $discussion );
				$return = $item['value'];
			}
		}
		return esc_html( $return );
	}

	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		$value = $this->format_discussion_value( $value );
		return $value;
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		$value = $this->format_discussion_value( $value );

		return $value;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$input = '';
		if ( is_array( $entry ) ) {
			$entry_value = rgar( $entry, $this->id );

			if ( $value == $entry_value ) {
				$value = '';
			}

			$input = $this->format_discussion_value( $entry_value );
		}

		$input .= parent::get_field_input( $form, $value, $entry );

		return $input;
	}

	/**
	 * @param string $value
	 * @param string $format
	 *
	 * @return string
	 */
	public function format_discussion_value( $value, $format = 'html' ) {
		$return = '';
		$discussion = json_decode( $value, ARRAY_A );
		if ( is_array( $discussion ) ) {
			foreach ( $discussion as $item ) {
				$item_datetime = date( 'Y-m-d H:i:s', $item['timestamp'] );
				$date = esc_html( GFCommon::format_date( $item_datetime , false, 'd M Y g:i a', false ) );
				if ( $item['assignee_key'] ) {
					$assignee = new Gravity_Flow_Assignee( $item['assignee_key'] );
					$display_name = $assignee->get_display_name();
				} else {
					$display_name = '';
				}

				$display_name = apply_filters( 'gravityflowdiscussion_display_name_discussion_field', $display_name, $item, $this );
				if ( $format == 'html' ) {
					$content = '<div class="gravityflow-dicussion-item-header"><span class="gravityflow-dicussion-item-name">' . $display_name  . '</span><span class="gravityflow-dicussion-item-date">' . $date . '</span></div>';
					$content .= '<div class="gravityflow-dicussion-item-value">' . esc_html( $item['value'] ) . '</div>';
					$return .= sprintf( '<div id="gravityflow-discussion-item-%s" class="gravityflow-discussion-item">%s</div>', $item['id'], $content );
				} elseif ( $format == 'text' ) {
					$return = $date . ': ' . $display_name . "\n";
					$return .= $item['value'] ;
				}
			}
		}
		return $return;
	}

	public function get_value_save_entry( $value, $form, $input_name, $entry_id, $entry ) {
		$value = $this->sanitize_entry_value( $value, $form['id'] );

		if ( $entry_id ) {
			$entry = GFAPI::get_entry( $entry_id );
			$previous_value_json = rgar( $entry, $this->id );
			$assignee_key = gravity_flow()->get_current_user_assignee_key();

			$new_comment = array( 'id' => uniqid( '', true ), 'assignee_key' => $assignee_key, 'timestamp' => time(), 'value' => $value );
			if ( empty( $previous_value_json ) ) {
				if ( ! empty( $value ) ) {
					$value = json_encode( array( $new_comment ) );
				}
			} else {
				$discussion = json_decode( $previous_value_json, ARRAY_A );
				if ( ! empty( $value ) ) {
					// Only add the comment to the discussion if a value was submitted.
					if ( is_array( $discussion ) ) {
						$discussion[] = $new_comment;
					} else {
						$discussion = array( $new_comment );
					}
				}
				$value = json_encode( $discussion );
			}
		}

		return $value;
	}

	/**
	 * Format the entry value before it is used in entry exports and by framework add-ons using GFAddOn::get_field_value().
	 *
	 * @param array $entry The entry currently being processed.
	 * @param string $input_id The field or input ID.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param bool|false $is_csv Is the value going to be used in the .csv entries export?
	 *
	 * @return string
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		return $this->format_discussion_value( rgar( $entry, $input_id ), 'text' );
	}
}

GF_Fields::register( new Gravity_Flow_Field_Discussion() );
