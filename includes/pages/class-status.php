<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Status {

	public static function display( $is_admin = true ){

		$table = new Gravity_Flow_Status_Table( $is_admin );
		$action_url = $is_admin ?  admin_url( 'admin.php?page=gravityflow-status' ) : 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}/{$_SERVER['REQUEST_URI']}?";
		?>
		<form id="gravityflow-status-filter" method="GET" action="<?php echo esc_url( $action_url ) ?>">
		<input type="hidden" name="page" value="gravityflow-status" />
		<?php
		$table->views();
		$table->filters();
		$table->prepare_items();
		$table->display();
		?>

		</form>
		<?php
	}
}


if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Gravity_Flow_Status_Table extends WP_List_Table {
	public $pagination_args;

	/**
	 * URL of this page
	 *
	 * @var string
	 */
	public $base_url;

	/**
	 * Base url of the detail page
	 *
	 * @var string
	 */
	public $detail_base_url;

	/**
	 * Total number of entries
	 *
	 * @var int
	 */
	public $total_count;
	/**
	 * Total number of pending entries
	 *
	 * @var int
	 */
	public $pending_count;
	/**
	 * Total number of complete entries
	 *
	 * @var int
	 */
	public $complete_count;
	/**
	 * Total number of cancelled entries
	 *
	 * @var int
	 */
	public $cancelled_count;

	public $form_ids;

	function __construct( $is_admin = true ) {
		parent::__construct( array(
			'singular' => __( 'entry', 'gravityflow' ),
			'plural'   => __( 'entries', 'gravityflow' ),
			'ajax'     => false,
			'screen'      => 'interval-list',
		) );
		$this->set_counts();
		$this->base_url = $is_admin ? admin_url( 'admin.php?page=gravityflow-status' ) : remove_query_arg( array( 'entry-id', 'form-id', 'start-date', 'end-date', '_wpnonce', '_wp_http_referer', 'action', 'action2' ) );
		$this->detail_base_url = $is_admin ? admin_url( 'admin.php?page=gravityflow-inbox&view=entry' ) : add_query_arg( array( 'page' => 'gravityflow-inbox', 'view' => 'entry' ) );
	}

	function no_items() {
		esc_html_e( "You haven't submitted any a workflow forms yet." );

	}

	public function get_views() {
		$current        = isset( $_GET['status'] ) ? $_GET['status'] : '';
		$total_count    = '&nbsp;<span class="count">(' . $this->total_count    . ')</span>';
		$complete_count = '&nbsp;<span class="count">(' . $this->complete_count . ')</span>';
		$pending_count  = '&nbsp;<span class="count">(' . $this->pending_count  . ')</span>';
		$cancelled_count  = '&nbsp;<span class="count">(' . $this->cancelled_count  . ')</span>';

		$views = array(
			'all'		=> sprintf( '<a href="%s"%s>%s</a>', remove_query_arg( array( 'status', 'paged' ) ), $current === 'all' || $current == '' ? ' class="current"' : '', esc_html__( 'All', 'gravityflow' ) . $total_count ),
			'pending'	=> sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'pending', 'paged' => false ) ), $current === 'pending' ? ' class="current"' : '', esc_html__( 'Pending', 'gravityflow' ) . $pending_count ),
			'complete'	=> sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'complete', 'paged' => false ) ), $current === 'complete' ? ' class="current"' : '', esc_html__( 'Complete', 'gravityflow' ) . $complete_count ),
			'cancelled'	=> sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'cancelled', 'paged' => false ) ), $current === 'cancelled' ? ' class="current"' : '', esc_html__( 'Cancelled', 'gravityflow' ) . $cancelled_count ),
		);
		return $views;
	}

	function filters(){

		$start_date = isset( $_GET['start-date'] )  ? sanitize_text_field( $_GET['start-date'] ) : null;
		$end_date   = isset( $_GET['end-date'] )    ? sanitize_text_field( $_GET['end-date'] )   : null;
		$status     = isset( $_GET['status'] )      ? $_GET['status'] : '';
		$filter_form_id     = empty( $_GET['form-id'] ) ? '' : absint( $_GET['form-id'] );
		$filter_entry_id     = empty( $_GET['entry-id'] ) ? '' :  absint( $_GET['entry-id'] );
		?>
		<div id="gravityflow-status-filters">

			<span id="gravityflow-status-date-filters">

				<input placeholder="ID" type="text" name="entry-id" id="entry-id" class="small-text" value="<?php echo $filter_entry_id; ?>"/>
				<select id="gravityflow-form-select" name="form-id">
					<?php
					$selected = selected( '', $filter_form_id, false );
					printf( '<option value="" %s >%s</option>', $selected, esc_html__( 'Workflow Form', 'gravityflow' ) );
					$forms = GFAPI::get_forms();
					foreach ( $forms as $form ) {
						$form_id = absint( $form['id'] );
						$steps = gravity_flow()->get_steps( $form_id );
						if ( ! empty( $steps ) ) {
							$selected = selected( $filter_form_id, $form_id, false );
							printf( '<option value="%d" %s>%s</option>', $form_id, $selected, esc_html( $form['title'] ) );
						}
					}
					?>
			</select>
				<label for="start-date"><?php esc_html_e( 'Start:', 'gravityflow' ); ?></label>
				<input type="text" id="start-date" name="start-date" class="datepicker medium-text ymd_dash" value="<?php echo $start_date; ?>" placeholder="yyyy/mm/dd"/>
				<label for="end-date"><?php esc_html_e( 'End:', 'gravityflow' ); ?></label>
				<input type="text" id="end-date" name="end-date" class="datepicker medium-text ymd_dash" value="<?php echo $end_date; ?>" placeholder="yyyy/mm/dd"/>
				<input type="submit" class="button-secondary" value="<?php esc_html_e( 'Apply', 'gravityflow' ); ?>"/>
			</span>
			<?php if ( ! empty( $status ) ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"/>
			<?php endif; ?>
			<?php if ( ! empty( $start_date ) || ! empty( $end_date ) || ! empty( $filter_form_id ) | ! empty( $filter_entry_id ) ) : ?>
				<a href="<?php echo esc_url( $this->base_url ); ?>" class="button-secondary"><?php esc_html_e( 'Clear Filter', 'gravityflow' ); ?></a>
			<?php endif; ?>
			<?php $this->search_box( esc_html__( 'Search', 'gravityflow' ), 'gravityflow-search' ); ?>
		</div>

	<?php
	}

	function column_cb( $item ) {
		$feed_id = rgar( $item, 'id' );

		return sprintf(
			'<input type="checkbox" class="gravityflow-cb-step-id" name="step_ids[]" value="%s" />', esc_attr( $feed_id )
		);
	}

	function column_default( $item, $column_name ) {
		$url_entry = $this->detail_base_url . sprintf( '&id=%d&lid=%d', $item['form_id'], $item['id'] );
		$label = esc_html( rgar( $item, $column_name ) );

		$link = "<a href='{$url_entry}'>$label</a>";
		echo $link;
	}

	function column_created_by( $item ) {
		$url_entry = $this->detail_base_url . sprintf( '&id=%d&lid=%d', $item['form_id'], $item['id'] );

		$user_id = $item['created_by'];
		if ( $user_id ) {
			$user = get_user_by( 'id', $user_id );
			$display_name = $user->display_name;
		} else {
			$display_name = $item['ip'];
		}
		$label = esc_html( $display_name );
		$link = "<a href='{$url_entry}'>$label</a>";
		echo $link;
	}

	function column_form_id( $item ) {
		$url_entry = $this->detail_base_url . sprintf( '&id=%d&lid=%d', $item['form_id'], $item['id'] );

		$form_id = $item['form_id'];
		$form    = GFAPI::get_form( $form_id );

		$label = esc_html( $form['title'] );
		$link = "<a href='{$url_entry}'>$label</a>";
		echo $link;
	}

	function column_workflow_step( $item ) {
		$step_id = rgar( $item, 'workflow_step' );
		if ( $step_id > 0 ) {
			$step = gravity_flow()->get_step( $step_id );
			$url_entry = $this->detail_base_url . sprintf( '&id=%d&lid=%d', $item['form_id'], $item['id'] );

			$label = esc_html( $step->get_name() );
			$link = "<a href='{$url_entry}'>$label</a>";
			echo $link;
		} else{
			echo '<span class="gravityflow-empty">&nbsp;</span>';
		}
	}

	function column_date_created( $item ) {
		$url_entry = $this->detail_base_url . sprintf( '&id=%d&lid=%d', $item['form_id'], $item['id'] );

		$label = GFCommon::format_date( $item['date_created'] );
		$link = "<a href='{$url_entry}'>$label</a>";
		echo $link;
	}

	function get_bulk_actions() {
		$bulk_actions = array( 'print' => esc_html__( 'Print', 'gravityforms' ) );
		return $bulk_actions;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'id' => array('id', false),
			'created_by' => array('created_by', false),
			'workflow_final_status' => array('workflow_final_status', false),
			'workflow_step'  => array('workflow_step', false),
			'date_created'  => array('date_created', false)
		);

		return $sortable_columns;
	}

	function get_columns() {
		$columns['cb']         = esc_html__( 'Checkbox', 'gravityflow' );
		$columns['id']         = esc_html__( 'ID', 'gravityflow' );
		$columns['form_id']         = esc_html__( 'Form', 'gravityflow' );
		$columns['created_by']      = esc_html__( 'Submitter', 'gravityflow' );
		$columns['workflow_final_status'] = esc_html__( 'Status', 'gravityflow' );
		$columns['workflow_step']   = esc_html__( 'Step', 'gravityflow' );
		$columns['date_created']    = esc_html__( 'Date', 'gravityflow' );

		return $columns;
	}

	public function set_counts() {
		$args = array();
		if ( ! empty( $_GET['form-id'] ) ) {
			$args['form-id'] = absint( $_GET['form-id'] );
		}
		if ( ! empty( $_GET['start-date'] ) ) {
			$args['start-date'] = urldecode( $_GET['start-date'] );
		}
		if ( ! empty( $_GET['end-date'] ) ) {
			$args['end-date'] = urldecode( $_GET['end-date'] );
		}
		$counts = $this->get_counts( $args );
		$this->pending_count  = $counts->pending;
		$this->complete_count = $counts->complete;
		$this->cancelled_count  = $counts->cancelled;
		foreach ( $counts as $count ) {
			$this->total_count += $count;
		}
	}

	function get_counts( $args ){
		global $wpdb;

		if ( ! empty( $args['form-id'] ) ) {
			$form_clause = ' AND l.form_id=' . absint( $args['form-id'] );
		} else {
			$form_ids = $this->get_workflow_form_ids();
			$form_clause = ' AND l.form_id IN(' . join( ',', $form_ids ) . ')';
		}

		$start_clause = '';

		if ( ! empty( $args['start-date'] ) ) {
			$start_date_gmt = $this->prepare_start_date_gmt( $args['start-date'] );
			$start_clause = $wpdb->prepare( ' AND l.date_created >= %s', $start_date_gmt );
		}

		$end_clause = '';

		if ( ! empty( $args['end-date'] ) ) {
			$end_date_gmt = $this->prepare_end_date_gmt( $args['end-date'] );
			$end_clause = $wpdb->prepare( ' AND l.date_created <= %s', $end_date_gmt );
		}

		$user_id_clause = '';
		if ( ! GFAPI::current_user_can_any( 'gravityflow_status_view_all' ) ) {
			$user = wp_get_current_user();
			$user_id_clause = $wpdb->prepare( ' AND created_by=%d' , $user->ID );
		}


		$lead_table = GFFormsModel::get_lead_table_name();
		$meta_table = GFFormsModel::get_lead_meta_table_name();

		$sql =  "SELECT
		(SELECT count(distinct(l.id)) FROM $lead_table l INNER JOIN  $meta_table m ON l.id = m.lead_id WHERE l.status='active' AND meta_key='workflow_final_status' AND meta_value='pending' $form_clause $start_clause $end_clause $user_id_clause) as pending,
		(SELECT count(distinct(l.id)) FROM $lead_table l INNER JOIN  $meta_table m ON l.id = m.lead_id WHERE l.status='active' AND meta_key='workflow_final_status' AND meta_value NOT IN('pending', 'cancelled') $form_clause $start_clause $end_clause $user_id_clause) as complete,
		(SELECT count(distinct(l.id)) FROM $lead_table l INNER JOIN  $meta_table m ON l.id = m.lead_id WHERE l.status='active' AND meta_key='workflow_final_status' AND meta_value='cancelled' $form_clause $start_clause $end_clause $user_id_clause) as cancelled
		";
		$results = $wpdb->get_results( $sql );

		return $results[0];
	}

	function prepare_start_date_gmt( $start_date ){
		$start_date         = new DateTime( $start_date );
		$start_date_str     = $start_date->format( 'Y-m-d H:i:s' );
		$start_date_gmt = get_gmt_from_date( $start_date_str );
		return $start_date_gmt;
	}

	function prepare_end_date_gmt( $end_date ){
		$end_date         = new DateTime( $end_date );

		$end_datetime_str = $end_date->format( 'Y-m-d H:i:s' );
		$end_date_str     = $end_date->format( 'Y-m-d' );

		// extend end date till the end of the day unless a time was specified. 00:00:00 is ignored.
		if ( $end_datetime_str == $end_date_str . ' 00:00:00' ) {
			$end_date = $end_date->format( 'Y-m-d' ) . ' 23:59:59';
		} else {
			$end_date = $end_date->format( 'Y-m-d H:i:s' );
		}
		$end_date_gmt = get_gmt_from_date( $end_date );
		return $end_date_gmt;
	}

	function get_workflow_form_ids(){
		return gravity_flow()->get_workflow_form_ids();
	}

	protected function single_row_columns( $item ) {
		list( $columns, $hidden ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class='$column_name column-$column_name'";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$data_label = ( ! empty( $column_display_name ) ) ? " data-label='$column_display_name'" : '';

			$attributes = "$class$style$data_label";

			if ( 'cb' == $column_name ) {
				echo '<th data-label="' . esc_html__( 'Select', 'gravityflow' ) . '" scope="row" class="check-column">';
				echo $this->column_cb( $item );
				echo '</th>';
			}
			elseif ( method_exists( $this, 'column_' . $column_name ) ) {
				echo "<td $attributes>";
				echo call_user_func( array( $this, 'column_' . $column_name ), $item );
				echo "</td>";
			}
			else {
				echo "<td $attributes>";
				echo $this->column_default( $item, $column_name );
				echo "</td>";
			}
		}
	}

	function prepare_items() {

		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		if ( isset( $_GET['form-id'] ) ) {
			$form_ids = absint( $_GET['form-id'] );
		} else {
			$form_ids = $this->get_workflow_form_ids();

			if ( empty( $form_ids ) ) {
				$this->items = array();
				return;
			}
		}

		global $current_user;
		$search_criteria['status'] = 'active';

		if ( ! empty( $_GET['start-date'] ) ) {
			$start_date = sanitize_text_field( $_GET['start-date'] );
			$start_date_gmt = $this->prepare_start_date_gmt( $start_date );
			$search_criteria['start_date'] = $start_date_gmt;
		}
		if ( ! empty( $_GET['end-date'] ) ) {
			$end_date = sanitize_text_field( $_GET['end-date'] );
			$end_date_gmt = $this->prepare_end_date_gmt( $end_date );
			$search_criteria['end_date'] = $end_date_gmt;
		}

		if ( ! empty( $_GET['entry-id'] ) ) {
			$search_criteria['field_filters'][] = array(
				'key'   => 'id',
				'value' => absint( $_GET['entry-id'] ),
			);
		}

		if ( ! empty( $_GET['status'] ) ) {
			if ( $_GET['status'] == 'complete' ) {
				$search_criteria['field_filters'][] = array(
					'key'   => 'workflow_final_status',
					'operator' => 'not in',
					'value' => array('pending', 'cancelled'),
				);
			} else {
				$search_criteria['field_filters'][] = array(
					'key'   => 'workflow_final_status',
					'value' => sanitize_text_field( $_GET['status'] ),
				);
			}
		}

		if ( ! GFAPI::current_user_can_any( 'gravityflow_status_view_all' ) ) {
			$search_criteria['field_filters'][] = array(
				'key'   => 'created_by',
				'value' => $current_user->ID,
			);
		}

		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'date_created';

		$order = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'desc';

		$user             = get_current_user_id();
		if (function_exists( 'get_current_screen' ) ) {
			$screen           = get_current_screen();
			if ( $screen ) {
				$option = $screen->get_option( 'per_page', 'option' );
			}

		}

		$per_page_setting = ! empty( $option ) ? get_user_meta( $user, $option, true ) : false;
		$per_page         = empty( $per_page_setting ) ? 20 : $per_page_setting;

		$page_size    = $per_page;
		$current_page = $this->get_pagenum();
		$offset       = $page_size * ($current_page - 1);

		$paging = array( 'page_size' => $page_size, 'offset' => $offset );

		$total_count = 0;

		$sorting =  array( 'key' => $orderby, 'direction' => $order );

		$entries     = GFAPI::get_entries( $form_ids, $search_criteria, $sorting, $paging, $total_count );

		$this->pagination_args = array(
			'total_items' => $total_count,
			'per_page'    => $page_size
		);

		$this->set_pagination_args( $this->pagination_args );


		$this->items = $entries;

	}

} //class


