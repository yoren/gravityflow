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
			$id_style        = $args['id_column'] ? '' : 'style="display:none;"';
			$submitter_style = $args['submitter_column'] ? '' : 'style="display:none;"';
			$step_style      = $args['step_column'] ? '' : 'style="display:none;"';
			$columns         = self::get_columns( $args );
			?>

			<table id="gravityflow-inbox" class="widefat gravityflow-inbox" cellspacing="0" style="border:0px;">

				<?php
				self::display_table_head( $args, $columns, $id_style, $submitter_style, $step_style );
				?>

				<tbody class="list:user user-list">
				<?php
				foreach ( $entries as $entry ) {
					self::display_entry_row( $args, $entry, $columns, $id_style, $submitter_style, $step_style );
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
				$entries = GFAPI::get_entries( $form_ids, $search_criteria, null, $paging, $total_count );
			}
		}

		return $entries;
	}

	/**
	 * If a form id and an array of field ids have been specified then get the properties for those columns.
	 *
	 * @param array $args The inbox page arguments.
	 *
	 * @return array
	 */
	public static function get_columns( $args ) {
		$columns = array();

		if ( $args['form_id'] && is_array( $args['field_ids'] ) ) {
			$columns = RGFormsModel::get_grid_columns( $args['form_id'], true );
		}

		return $columns;
	}

	/**
	 * Display the table header.
	 *
	 * @param array $args The inbox page arguments.
	 * @param array $columns The column properties.
	 * @param string $id_style The class to be added to the id column header.
	 * @param string $submitter_style The class to be added to the submitter column header.
	 * @param string $step_style The class to be added to the step column header.
	 */
	public static function display_table_head( $args, $columns, $id_style, $submitter_style, $step_style ) {
		?>

		<thead>
		<tr>
			<th <?php echo $id_style ?>
				data-label="<?php esc_html_e( 'ID', 'gravityflow' ); ?>"><?php esc_html_e( 'ID', 'gravityflow' ); ?></th>
			<?php if ( empty( $args['form_id'] ) ) : ?>
				<th><?php esc_html_e( 'Form', 'gravityflow' ); ?></th>
			<?php endif; ?>
			<th <?php echo $submitter_style ?>><?php esc_html_e( 'Submitter', 'gravityflow' ); ?></th>
			<th <?php echo $step_style ?>><?php esc_html_e( 'Step', 'gravityflow' ); ?></th>
			<th><?php esc_html_e( 'Submitted', 'gravityflow' ); ?></th>
			<?php
			if ( ! empty( $columns ) ) {
				$field_ids = array_keys( $columns );
				foreach ( $args['field_ids'] as $field_id ) {
					$field_id = trim( $field_id );
					if ( in_array( $field_id, $field_ids ) ) {
						$field_info = $columns[ $field_id ];
						echo '<th>' . esc_html( $field_info['label'] ) . '</th>';
					}
				}
			}
			if ( $args['last_updated'] ) {
				echo '<th>' . esc_html__( 'Last Updated', 'gravityflow' ) . '</th>';
			}

			?>
		</tr>
		</thead>

		<?php
	}

	/**
	 * Display the row for the current entry.
	 *
	 * @param array $args The inbox page arguments.
	 * @param array $entry The entry currently being processed.
	 * @param array $columns The column properties.
	 * @param string $id_style The class to be added to the id column header.
	 * @param string $submitter_style The class to be added to the submitter column header.
	 * @param string $step_style The class to be added to the step column header.
	 */
	public static function display_entry_row( $args, $entry, $columns, $id_style, $submitter_style, $step_style ) {
		$form           = GFAPI::get_form( $entry['form_id'] );
		$user           = get_user_by( 'id', (int) $entry['created_by'] );
		$submitter_name = $user ? $user->display_name : $entry['ip'];

		/**
		 * Allow the value displayed in the Submitter column to be overridden.
		 *
		 * @param string $submitter_name The display_name of the logged-in user who submitted the form or the guest ip address.
		 * @param array $entry The entry object for the row currently being processed.
		 * @param array $form The form object for the current entry.
		 */
		$submitter_name = apply_filters( 'gravityflow_inbox_submitter_name', $submitter_name, $entry, $form );

		$base_url  = $args['detail_base_url'];
		$url_entry = $base_url . sprintf( '&id=%d&lid=%d', $entry['form_id'], $entry['id'] );
		$url_entry = esc_url_raw( $url_entry );
		$link      = "<a href='%s'>%s</a>";
		?>
		<tr>
			<td data-label="<?php esc_html_e( 'ID', 'gravityflow' ); ?>" <?php echo $id_style ?>>
				<?php
				printf( $link, $url_entry, $entry['id'] );
				?>
			</td>
			<?php if ( empty( $args['form_id'] ) ) : ?>
				<td data-label="<?php esc_html_e( 'Form', 'gravityflow' ); ?>">
					<?php
					printf( $link, $url_entry, $form['title'] );
					?>
				</td>
			<?php endif; ?>
			<td data-label="<?php esc_html_e( 'Submitter', 'gravityflow' ); ?>" <?php echo $submitter_style ?>>
				<?php
				printf( $link, $url_entry, $submitter_name );
				?>
			</td>
			<td data-label="<?php esc_html_e( 'Step', 'gravityflow' ); ?>" <?php echo $step_style ?>>
				<?php
				if ( isset( $entry['workflow_step'] ) ) {
					$step = gravity_flow()->get_step( $entry['workflow_step'] );
					if ( $step ) {
						printf( $link, $url_entry, $step->get_name() );
					}
				}
				?>
			</td>
			<td data-label="<?php esc_html_e( 'Submitted', 'gravityflow' ); ?>">
				<?php
				printf( $link, $url_entry, GFCommon::format_date( $entry['date_created'] ) );
				?>
			</td>

			<?php

			self::maybe_display_field_values( $columns, $form, $args, $entry, $link, $url_entry );
			self::maybe_display_last_updated( $args, $entry );

			?>
		</tr>
		<?php
	}

	/**
	 * Display the field columns.
	 *
	 * @param array $columns The column properties.
	 * @param array $form The form for the current entry.
	 * @param array $args The inbox page arguments.
	 * @param array $entry The entry currently being processed.
	 * @param string $link The HTML link markup.
	 * @param string $url_entry The entry URL.
	 */
	public static function maybe_display_field_values( $columns, $form, $args, $entry, $link, $url_entry ) {
		if ( empty( $columns ) || ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return;
		}

		$field_ids = array_keys( $columns );
		foreach ( $args['field_ids'] as $field_id ) {
			/* @var GF_Field $field */
			if ( ! in_array( $field_id, $field_ids ) ) {
				continue;
			}
			$field_id   = trim( $field_id );
			$field_info = $columns[ $field_id ];
			$value      = rgar( $entry, $field_id );
			$field      = GFFormsModel::get_field( $form, $field_id );
			?>
			<td data-label="<?php echo esc_attr( $field_info['label'] ); ?>">
				<?php printf( $link, $url_entry, $field->get_value_entry_list( $value, $entry, $field_id, $columns, $form ) ); ?>
			</td>
			<?php
		}
	}

	/**
	 * Display the last updated column, if enabled.
	 *
	 * @param array $args The inbox page arguments.
	 * @param array $entry The entry currently being processed.
	 */
	public static function maybe_display_last_updated( $args, $entry ) {
		if ( ! $args['last_updated'] ) {
			return;
		}

		?>
		<td data-label="<?php esc_html_e( 'Last_Updated', 'gravityflow' ); ?>">
			<?php
			$last_updated = date( 'Y-m-d H:i:s', $entry['workflow_timestamp'] );
			if ( $entry['date_created'] != $last_updated ) {
				echo esc_html( GFCommon::format_date( $last_updated, true, 'Y/m/d' ) );
			} else {
				echo '-';
			}
			?>
		</td>
		<?php
	}

}
