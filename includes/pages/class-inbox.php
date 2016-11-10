<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Gravity Flow Inbox
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Inbox
 * @copyright   Copyright (c) 2015-2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
class Gravity_Flow_Inbox {

	public static function display( $args ) {

		$args = array_merge( self::get_defaults(), $args );

		/**
		 * Allow the inbox page arguments to be overridden.
		 *
		 * @param array $args The inbox page arguments.
		 */
		$args = apply_filters( 'gravityflow_inbox_args', $args );

		$total_count = 0;
		$entries     = self::get_entries( $args, $total_count );

		if ( sizeof( $entries ) > 0 ) {
			$columns = self::get_columns( $args );
			?>

			<table id="gravityflow-inbox" class="widefat gravityflow-inbox" cellspacing="0" style="border:0px;">

				<?php
				self::display_table_head( $columns );
				?>

				<tbody class="list:user user-list">
				<?php
				foreach ( $entries as $entry ) {
					self::display_entry_row( $args, $entry, $columns );
				}
				?>
				</tbody>
			</table>

			<?php
			if ( $total_count > 150 ) {
				echo '<br />';
				echo '<div class="excess_entries_indicator">';
				printf( '(Showing 150 of %d)', absint( $total_count ) );
				echo '</div>';
			}
		} else {
			?>
			<div id="gravityflow-no-pending-tasks-container">
				<div id="gravityflow-no-pending-tasks-content">
					<i class="fa fa-check-circle-o gravityflow-inbox-check"></i>
					<br/><br/>
					<?php esc_html_e( 'No pending tasks', 'gravityflow' ); ?>
				</div>

			</div>
		<?php
		}
	}

	/**
	 * Get the default arguments used when rendering the inbox page.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		$field_ids = apply_filters( 'gravityflow_inbox_fields', array() );
		$filter    = apply_filters( 'gravityflow_inbox_filter', array(
			'form_id'    => 0,
			'start_date' => '',
			'end_date'   => ''
		) );

		return array(
			'display_empty_fields' => true,
			'id_column'            => true,
			'submitter_column'     => true,
			'step_column'          => true,
			'check_permissions'    => true,
			'form_id'              => absint( rgar( $filter, 'form_id' ) ),
			'field_ids'            => $field_ids,
			'detail_base_url'      => admin_url( 'admin.php?page=gravityflow-inbox&view=entry' ),
			'last_updated'         => false,
		);
	}

	/**
	 * Get the filter key for the current user.
	 *
	 * @return string
	 */
	public static function get_filter_key() {
		global $current_user;

		$filter_key = '';

		if ( $current_user->ID > 0 ) {
			$filter_key = 'user_id_' . $current_user->ID;
		} elseif ( $token = gravity_flow()->decode_access_token() ) {
			$filter_key = 'email_' . gravity_flow()->parse_token_assignee( $token )->get_id();
		}

		return $filter_key;
	}

	/**
	 * Get the entries to be displayed.
	 *
	 * @param array $args The inbox page arguments.
	 * @param int $total_count The total number of entries.
	 *
	 * @return array
	 */
	public static function get_entries( $args, &$total_count ) {
		$entries    = array();
		$filter_key = self::get_filter_key();

		if ( ! empty( $filter_key ) ) {
			$field_filters   = array();
			$field_filters[] = array(
				'key'   => 'workflow_' . $filter_key,
				'value' => 'pending',
			);

			$user_roles = gravity_flow()->get_user_roles();
			foreach ( $user_roles as $user_role ) {
				$field_filters[] = array(
					'key'   => 'workflow_role_' . $user_role,
					'value' => 'pending',
				);
			}

			$field_filters['mode'] = 'any';

			$search_criteria                  = array();
			$search_criteria['field_filters'] = $field_filters;
			$search_criteria['status']        = 'active';

			$form_ids = $args['form_id'] ? $args['form_id'] : gravity_flow()->get_workflow_form_ids();

			if ( ! empty( $form_ids ) ) {
				$paging  = array(
					'page_size' => 150,
				);

				$sorting = array();

				/**
				 * Allows the sorting criteria to be modified before entries are searched for the inbox.
				 *
				 * @param array $entries
				 */
				$sorting = apply_filters( 'gravityflow_inbox_sorting', $sorting );

				$entries = GFAPI::get_entries( $form_ids, $search_criteria, $sorting, $paging, $total_count );
			}
		}

		return $entries;
	}

	/**
	 * Get the columns to be displayed.
	 *
	 * @param array $args The inbox page arguments.
	 *
	 * @return array
	 */
	public static function get_columns( $args ) {
		$columns = array();

		if ( $args['id_column'] ) {
			$columns['id'] = __( 'ID', 'gravityflow' );
		}

		if ( empty( $args['form_id'] ) ) {
			$columns['form_title'] = __( 'Form', 'gravityflow' );
		}

		if ( $args['submitter_column'] ) {
			$columns['created_by'] = __( 'Submitter', 'gravityflow' );
		}

		if ( $args['step_column'] ) {
			$columns['workflow_step'] = __( 'Step', 'gravityflow' );
		}

		$columns['date_created'] = __( 'Submitted', 'gravityflow' );
		$columns                 = Gravity_Flow_Common::get_field_columns( $columns, $args['form_id'], $args['field_ids'] );

		if ( $args['last_updated'] ) {
			$columns['last_updated'] = __( 'Last Updated', 'gravityflow' );
		}

		return $columns;
	}

	/**
	 * Display the table header.
	 *
	 * @param array $columns The column properties.
	 */
	public static function display_table_head( $columns ) {
		echo '<thead><tr>';

		foreach ( $columns as $label ) {
			echo sprintf( '<th data-label="%s">%s</th>', esc_attr( $label ), esc_html( $label ) );
		}

		echo '</tr></thead>';
	}

	/**
	 * Display the row for the current entry.
	 *
	 * @param array $args The inbox page arguments.
	 * @param array $entry The entry currently being processed.
	 * @param array $columns The column properties.
	 */
	public static function display_entry_row( $args, $entry, $columns ) {
		$form      = GFAPI::get_form( $entry['form_id'] );
		$url_entry = esc_url_raw( sprintf( '%s&id=%d&lid=%d', $args['detail_base_url'], $entry['form_id'], $entry['id'] ) );
		$link      = "<a href='%s'>%s</a>";

		echo '<tr>';

		foreach ( $columns as $id => $label ) {
			$value = sprintf( $link, $url_entry, self::get_column_value( $id, $form, $entry, $columns ) );
			echo sprintf( '<td data-label="%s">%s</td>', esc_attr( $label ), $value );
		}

		echo '</tr>';
	}

	/**
	 * Get the value for display in the current column for the entry being processed.
	 *
	 * @param string $id The column id, the key to the value in the entry or form.
	 * @param array $form The form object for the current entry.
	 * @param array $entry The entry currently being processed for display.
	 * @param array $columns The columns to be displayed.
	 *
	 * @return string
	 */
	public static function get_column_value( $id, $form, $entry, $columns ) {
		switch ( strtolower( $id ) ) {
			case 'form_title':
				return rgar( $form, 'title' );

			case 'created_by':
				$user           = get_user_by( 'id', (int) $entry['created_by'] );
				$submitter_name = $user ? $user->display_name : $entry['ip'];

				/**
				 * Allow the value displayed in the Submitter column to be overridden.
				 *
				 * @param string $submitter_name The display_name of the logged-in user who submitted the form or the guest ip address.
				 * @param array $entry The entry object for the row currently being processed.
				 * @param array $form The form object for the current entry.
				 */
				return apply_filters( 'gravityflow_inbox_submitter_name', $submitter_name, $entry, $form );

			case 'date_created':
				return GFCommon::format_date( $entry['date_created'] );

			case 'last_updated':
				$last_updated = date( 'Y-m-d H:i:s', $entry['workflow_timestamp'] );

				return $entry['date_created'] != $last_updated ? GFCommon::format_date( $last_updated, true, 'Y/m/d' ) : '-';

			case 'workflow_step':
				if ( isset( $entry['workflow_step'] ) ) {
					$step = gravity_flow()->get_step( $entry['workflow_step'] );
					if ( $step ) {
						return $step->get_name();
					}
				}

				return '';

			default:
				$field = GFFormsModel::get_field( $form, $id );

				if ( is_object( $field ) ) {
					return $field->get_value_entry_list( rgar( $entry, $id ), $entry, $id, $columns, $form );
				} else {
					return rgar( $entry, $id );
				}
		}
	}
}
