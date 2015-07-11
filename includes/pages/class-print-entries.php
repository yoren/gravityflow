<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class Gravity_Flow_Print_Entries {
	public static function render(){

		$form_id = 0;
		$entries = rgget( 'lid' );
		if ( 0 == $entries ) {
			// get all the entry ids for the current filter / search
			$filter                    = rgget( 'filter' );
			$search                    = rgget( 'search' );
			$star                      = $filter == 'star' ? 1 : null;
			$read                      = $filter == 'unread' ? 0 : null;
			$status                    = in_array( $filter, array( 'trash', 'spam' ) ) ? $filter : 'active';
			$search_criteria['status'] = $status;

			if ( $star ) {
				$search_criteria['field_filters'][] = array( 'key' => 'is_starred', 'value' => (bool) $star );
			}
			if ( ! is_null( $read ) ) {
				$search_criteria['field_filters'][] = array( 'key' => 'is_read', 'value' => (bool) $read );
			}

			$search_field_id = rgget( 'field_id' );
			$search_operator = rgget( 'operator' );
			if ( isset( $_GET['field_id'] ) && $_GET['field_id'] !== '' ) {
				$key            = $search_field_id;
				$val            = rgget( 's' );
				$strpos_row_key = strpos( $search_field_id, '|' );
				if ( $strpos_row_key !== false ) { //multi-row
					$key_array = explode( '|', $search_field_id );
					$key       = $key_array[0];
					$val       = $key_array[1] . ':' . $val;
				}
				$search_criteria['field_filters'][] = array(
					'key'      => $key,
					'operator' => rgempty( 'operator', $_GET ) ? 'is' : rgget( 'operator' ),
					'value'    => $val,
				);
			}
			$entry_ids = GFFormsModel::search_lead_ids( $form_id, $search_criteria );
		} else {
			$entry_ids = explode( ',', $entries );
		}


		$page_break = rgget( 'page_break' ) ? 'print-page-break' : false;

		// sort lead IDs numerically
		sort( $entry_ids );

		if ( empty( $entry_ids ) ) {
			die( esc_html__( 'Form Id and Lead Id are required parameters.', 'gravityflow' ) );
		}


		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		?>

		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
			"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
		<head>
			<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
			<meta name="keywords" content="" />
			<meta name="description" content="" />
			<meta name="MSSmartTagsPreventParsing" content="true" />
			<meta name="Robots" content="noindex, nofollow" />
			<meta http-equiv="Imagetoolbar" content="No" />
			<title>
				<?php
				$entry_count = count( $entry_ids );
				$title       = $entry_count > 1 ? esc_html__( 'Bulk Print', 'gravityflow' ) : esc_html__( 'Entry # ', 'gravityflow' ) . $entry_ids[0];
				$title       = apply_filters( 'gravityflow_page_title_print_entry', $title, $entry_count );
				echo esc_html( $title );
				?>
			</title>
			<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/print<?php echo $min; ?>.css' type='text/css' />
			<link rel='stylesheet' href='<?php echo gravity_flow()->get_base_url() ?>/css/entry-detail<?php echo $min; ?>.css' type='text/css' />
			<?php
			$styles = apply_filters( 'gravityflow_print_styles', false );
			if ( ! empty( $styles ) ) {
				wp_print_styles( $styles );
			}


			?>
		</head>
		<body>

		<div id="view-container">
			<?php

			require_once( GFCommon::get_base_path() . '/entry_detail.php' );

			foreach ( $entry_ids as $entry_id ) {

				$entry = RGFormsModel::get_lead( $entry_id );

				$form = GFAPI::get_form( $entry['form_id'] );

				do_action( 'gravityflow_print_entry_header', $form, $entry );

				// Separate each entry inside a form element so radio buttons don't get treated as a single group across multiple entries.
				echo '<form>';
				$gravity_flow = gravity_flow();
				$current_step = $gravity_flow->get_current_step( $form, $entry );

				// Check view permissions
				global $current_user;

				if ( $entry['created_by'] != $current_user->ID ) {
					$user_status = false;
					if ( $current_step ) {
						$user_status = $current_step->get_user_status( $current_user->ID );

						if ( ! $user_status ) {
							$user_roles = gravity_flow()->get_user_roles();
							foreach ( $user_roles as $user_role ) {
								$user_status = $current_step->get_role_status( $user_role );
							}
						}
					}

					$full_access = GFAPI::current_user_can_any( array( 'gform_full_access', 'gravityflow_status_view_all' ) );

					if  ( ! ( $user_status || $full_access ) ) {
						esc_attr_e( "You don't have permission to view this entry.", 'gravityflow' );
						continue;
					}
				}

				require_once( $gravity_flow->get_base_path() . '/includes/pages/class-entry-detail.php' );
				Gravity_Flow_Entry_Detail::entry_detail_grid( $form, $entry, false, array(), $current_step );

				echo '</form>';

				if ( rgget( 'notes' ) ) {
					Gravity_Flow_Entry_Detail::timeline( $entry, $form );
				}

				// output entry divider/page break
				if ( array_search( $entry_id, $entry_ids ) < count( $entry_ids ) - 1 ) {
					echo '<div class="print-hr ' . $page_break . '"></div>';
				}

				do_action( 'gravityflow_print_entry_footer', $form, $entry );
			}

			?>
		</div>
		</body>
		</html>
		<?php
	}
}
