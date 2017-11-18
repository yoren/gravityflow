<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Merge_Tag_Workflow_Fields extends Gravity_Flow_Merge_Tag {

	/**
	 * The name of the merge tag.
	 *
	 * @since 1.9.2-dev
	 *
	 * @var null
	 */
	public $name = 'workflow_fields';

	/**
	 * The regular expression to use for the matching.
	 *
	 * @since 1.9.2-dev
	 *
	 * @var string
	 */
	protected $regex = '/{workflow_fields(:(.*?))?}/';

	/**
	 * Replace the {workflow_fields} merge tags with the field values for the current steps display/editable fields.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param string $text  The text to be processed.
	 *
	 * @return string
	 */
	public function replace( $text ) {

		$entry = $this->entry;

		if ( empty( $entry ) || empty( $this->step ) ) {
			return $text;
		}

		$matches = $this->get_matches( $text );

		if ( ! empty( $matches ) ) {
			add_filter( 'gform_merge_tag_filter', array( $this, 'merge_tag_filter' ), 20, 4 );

			foreach ( $matches as $match ) {
				$full_tag  = $match[0];
				$modifiers = rgar( $match, 2 );

				$a = $this->get_attributes( $modifiers, array(
					'empty' => false, // output empty fields
					'value' => false, // output choice values
					'admin' => false, // output admin labels
				) );

				$replacement = GFCommon::get_submitted_fields( $this->form, $entry, $a['empty'], ! $a['value'], $this->format, $a['admin'], $this->name );
				$text        = str_replace( $full_tag, $replacement, $text );
			}

			remove_filter( 'gform_merge_tag_filter', array( $this, 'merge_tag_filter' ), 20 );
		}

		return $text;
	}

	/**
	 * Prevents GFCommon::get_submitted_fields including non-editable and non-display fields in the content replacing the merge tag.
	 *
	 * @since 1.9.2-dev
	 *
	 * @param string   $value     The current merge tag value for the field.
	 * @param string   $merge_tag The current merge tag name.
	 * @param string   $modifiers The modifiers for the current merge tag.
	 * @param GF_Field $field     The field currently being processed.
	 *
	 * @return bool
	 */
	public function merge_tag_filter( $value, $merge_tag, $modifiers, $field ) {
		if ( ! $this->step->is_editable_field( $field ) && ! $this->step->is_display_field( $field, $this->form, $this->entry ) ) {
			$this->step->log_debug( __METHOD__ . '(): removing field.' );
			return false;
		}

		return $value;
	}

}

Gravity_Flow_Merge_Tags::register( new Gravity_Flow_Merge_Tag_Workflow_Fields );
