<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Entry_Detail {

	/**
	 * @param $form
	 * @param $entry
	 * @param null|Gravity_Flow_Step $current_step
	 * @param array $args
	 */
	public static function entry_detail( $form, $entry, $current_step = null, $args = array() ) {

		$form_id = absint( $form['id'] );
		$form    = apply_filters( 'gform_pre_render', $form );
		$form    = apply_filters( 'gform_pre_render_' . $form_id, $form );

		$defaults = array(
			'display_empty_fields' => true,
			'check_permissions' => true,
			'show_header' => true,
			'timeline' => true,
			'display_instructions' => true,
		);

		$args = array_merge( $defaults, $args );

		$display_empty_fields = (bool) $args['display_empty_fields'];
		$check_view_entry_permissions = (bool) $args['check_permissions'];
		$show_header = (bool) $args['show_header'];
		$show_timeline = (bool) $args['timeline'];
		$display_instructions = (bool) $args['display_instructions'];

		?>

		<script type="text/javascript">

			if ( typeof ajaxurl == 'undefined' ) {
				ajaxurl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			}

			function DeleteFile(leadId, fieldId, deleteButton) {
				if (confirm(<?php echo json_encode( __( "Would you like to delete this file? 'Cancel' to stop. 'OK' to delete", 'gravityflow' ) ); ?>)) {
					var fileIndex = jQuery(deleteButton).parent().index();
					var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' )?>");
					mysack.execute = 1;
					mysack.method = 'POST';
					mysack.setVar("action", "rg_delete_file");
					mysack.setVar("rg_delete_file", "<?php echo wp_create_nonce( 'rg_delete_file' ) ?>");
					mysack.setVar("lead_id", leadId);
					mysack.setVar("field_id", fieldId);
					mysack.setVar("file_index", fileIndex);
					mysack.onError = function () {
						alert(<?php echo json_encode( __( 'Ajax error while deleting file.', 'gravityflow' ) ) ?>)
					};
					mysack.runAJAX();

					return true;
				}
			}

			function EndDeleteFile(fieldId, fileIndex) {
				var previewFileSelector = "#preview_existing_files_" + fieldId + " .ginput_preview";
				var $previewFiles = jQuery(previewFileSelector);
				var rr = $previewFiles.eq(fileIndex);
				$previewFiles.eq(fileIndex).remove();
				var $visiblePreviewFields = jQuery(previewFileSelector);
				if ($visiblePreviewFields.length == 0) {
					jQuery('#preview_' + fieldId).hide();
					jQuery('#upload_' + fieldId).show('slow');
				}
			}

			function ToggleShowEmptyFields() {
				if (jQuery("#gentry_display_empty_fields").is(":checked")) {
					createCookie("gf_display_empty_fields", true, 10000);
					document.location = document.location.href;
				}
				else {
					eraseCookie("gf_display_empty_fields");
					document.location = document.location.href;
				}
			}

			function createCookie(name, value, days) {
				if (days) {
					var date = new Date();
					date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
					var expires = "; expires=" + date.toGMTString();
				}
				else var expires = "";
				document.cookie = name + "=" + value + expires + "; path=/";
			}

			function eraseCookie(name) {
				createCookie(name, "", -1);
			}


		</script>

		<div class="wrap gf_entry_wrap gravityflow_workflow_wrap gravityflow_workflow_detail">

			<?php if ( $show_header ) :	?>
			<h2 class="gf_admin_page_title">
				<img width="45" height="22" src="<?php echo gravity_flow()->get_base_url(); ?>/images/gravityflow-icon-blue-grad.svg" style="margin-right:5px;"/>
				<span><?php echo esc_html__( 'Workflow Entry #', 'gravityflow' ) . absint( $entry['id'] ); ?></span><span class="gf_admin_page_subtitle"><span class='gf_admin_page_formname'><?php esc_html_e( 'Workflow Form', 'gravityflow' ) ?>: <?php esc_html_e( $form['title'] ); ?></span></span>
			</h2>

			<div id="gf_form_toolbar">
				<ul id="gf_form_toolbar_links">

					<?php

					$menu_items = gravity_flow()->get_toolbar_menu_items();

					echo GFForms::format_toolbar_menu_items( $menu_items );

					?>
				</ul>
			</div>

			<?php
			endif;

			if ( $check_view_entry_permissions ) {
				// Check view permissions
				global $current_user;

				if ( $entry['created_by'] != $current_user->ID ) {
					$user_status = false;
					if ( $current_step ) {
						$user_status = $current_step->get_user_status();

						if ( ! $user_status ) {
							$user_roles = gravity_flow()->get_user_roles();

							foreach ( $user_roles as $user_role ) {
								$user_status = $current_step->get_role_status( $user_role );
							}
						}
					}

					$full_access = GFAPI::current_user_can_any( array(
						'gform_full_access',
						'gravityflow_status_view_all',
					) );

					if ( ! ( $user_status || $full_access ) ) {
						$permission_denied_message = esc_attr__( "You don't have permission to view this entry.", 'gravityflow' );
						$permission_denied_message = apply_filters( 'gravityflow_permission_denied_message_entry_detail', $permission_denied_message, $current_step );
						echo $permission_denied_message;
						return;
					}
				}
			}
			$url = remove_query_arg( array( 'gworkflow_token', 'new_status' ) );

			?>
			<div class="gform_wrapper">
				<form method="post" id="entry_form" enctype='multipart/form-data' action="<?php echo esc_url( $url ); ?>">
					<?php wp_nonce_field( 'gforms_save_entry', 'gforms_save_entry' ) ?>
					<div id="poststuff" class="metabox-holder has-right-sidebar">

						<div id="side-info-column" class="inner-sidebar">
							<?php
							gravity_flow()->workflow_entry_detail_status_box( $form, $entry, $current_step );

							if ( is_user_logged_in() || $check_view_entry_permissions ) :
							?>

							<!-- begin print button -->
							<div class="detail-view-print">
								<a href="javascript:;"
								   onclick="var notes_qs = jQuery('#gform_print_notes').is(':checked') ? '&notes=1' : ''; var url='<?php echo admin_url( 'admin-ajax.php' )?>?action=gravityflow_print_entries&lid=<?php echo absint( $entry['id'] ); ?>' + notes_qs; printPage(url);"
								   class="button"><?php esc_html_e( 'Print', 'gravityflow' ) ?></a>

									<?php if ( $show_timeline ) { ?>

									<input type="checkbox" name="print_notes" value="print_notes" checked="checked"
									       id="gform_print_notes"/>
									<label for="print_notes"><?php esc_html_e( 'include timeline', 'gravityflow' ) ?></label>
									<?php } ?>

							</div>
							<!-- end print button -->

							<?php endif; ?>
						</div>

						<div id="post-body" class="has-sidebar">
							<div id="post-body-content" class="has-sidebar-content">
								<?php

								do_action( 'gravityflow_entry_detail_content_before', $form, $entry );

								$editable_fields = array();

								if ( $current_step ) {
									$current_user_status = $current_step->get_user_status();
									$current_role_status = false;
									if ( $current_step ) {
										foreach ( gravity_flow()->get_user_roles() as $role ) {
											$current_role_status = $current_step->get_role_status( $role );
											if ( $current_role_status == 'pending' ) {
												break;
											}
										}
									}

									$can_update = $current_step && ( $current_user_status == 'pending' || $current_role_status == 'pending' );
									$editable_fields = $can_update ? $current_step->get_editable_fields() : array();

									if ( $can_update && $display_instructions && $current_step->instructionsEnable ) {
										$instructions = $current_step->instructionsValue;
										$instructions = GFCommon::replace_variables( $instructions, $form, $entry, false, true, true );
										$instructions = $current_step->replace_variables( $instructions, null );
										$instructions = wp_kses_post( $instructions );
										?>
										<div class="postbox">
											<div class="inside">
												<?php echo $instructions; ?>
											</div>

										</div>

										<?php
									}
								}

								self::entry_detail_grid( $form, $entry, $display_empty_fields, $editable_fields, $current_step );

								do_action( 'gravityflow_entry_detail', $form, $entry );

								if ( $show_timeline ) {
									?>
									<div class="postbox">
										<h3>
											<label for="name"><?php esc_html_e( 'Timeline', 'gravityflow' ); ?></label>
										</h3>

										<div class="inside">
											<?php self::timeline( $entry, $form ); ?>
										</div>

									</div>

								<?php } ?>

							</div>
						</div>

					</div>

				</form>
			</div>
		</div>
		<?php
	}

	public static function timeline( $entry, $form ) {
		$notes = self::get_timeline_notes( $entry );

		//getting email values
		$email_fields = GFCommon::get_email_fields( $form );
		$emails = array();

		foreach ( $email_fields as $email_field ) {
			if ( ! empty( $entry[ $email_field->id ] ) ) {
				$emails[] = $entry[ $email_field->id ];
			}
		}
		//displaying notes grid
		$subject = '';
		self::notes_grid( $notes, true, $emails, $subject );
	}

	public static function get_timeline_notes( $entry ) {
		$notes = RGFormsModel::get_lead_notes( $entry['id'] );

		foreach ( $notes as $key => $note ) {
			if ( $note->note_type !== 'gravityflow' ) {
				unset( $notes[ $key ] );
			}
		}

		reset( $notes );

		$initial_note = new stdClass();
		$initial_note->id = 0;
		$initial_note->date_created = $entry['date_created'];
		$initial_note->value = esc_html__( 'Workflow Submitted', 'gravityflow' );
		$initial_note->user_id = $entry['created_by'];
		$user = get_user_by( 'id', $entry['created_by'] );
		$initial_note->user_name = $user ? $user->display_name : $entry['ip'];

		array_unshift( $notes, $initial_note );

		$notes = array_reverse( $notes );
		return $notes;
	}


	/**
	 * @param $form
	 * @param $entry
	 * @param bool|false $allow_display_empty_fields
	 * @param array $editable_fields
	 * @param Gravity_Flow_Step|null $current_step
	 */
	public static function entry_detail_grid( $form, $entry, $allow_display_empty_fields = false, $editable_fields = array(), $current_step = null ) {
		$form_id = absint( $form['id'] );

		$display_empty_fields = false;
		if ( $allow_display_empty_fields ) {
			$display_empty_fields = rgget( 'gf_display_empty_fields', $_COOKIE );
		}

		$display_empty_fields = (bool) apply_filters( 'gravityflow_entry_detail_grid_display_empty_fields', $display_empty_fields, $form, $entry );

		$condtional_logic_enabled = $current_step && $current_step->conditional_logic_editable_fields_enabled;
		self::register_form_init_scripts( $form, array(), $condtional_logic_enabled );

		if ( apply_filters( 'gform_init_scripts_footer', false ) ) {
			add_action( 'wp_footer', create_function( '', 'GFFormDisplay::footer_init_scripts(' . $form['id'] . ');' ), 20 );
			add_action( 'gform_preview_footer', create_function( '', 'GFFormDisplay::footer_init_scripts(' . $form['id'] . ');' ) );
		} else {
			echo GFFormDisplay::get_form_init_scripts( $form );
			$current_page = 1;
			$scripts = "<script type='text/javascript'>" . apply_filters( 'gform_cdata_open', '' ) . " jQuery(document).ready(function(){jQuery(document).trigger('gform_post_render', [{$form_id}, {$current_page}]) } ); " . apply_filters( 'gform_cdata_close', '' ) . '</script>';
			echo $scripts;
		}



		?>

		<input type="hidden" name="action" id="action" value="" />
			<input type="hidden" name="save" id="action" value="Update" />
		<input type="hidden" name="screen_mode" id="screen_mode" value="<?php echo esc_attr( rgpost( 'screen_mode' ) ) ?>" />

		<table cellspacing="0" class="widefat fixed entry-detail-view">
			<thead>
			<tr>
				<th id="details">
					<?php
					$title = sprintf( '%s : %s %s', esc_html( $form['title'] ), __( 'Entry # ', 'gravityflow' ), absint( $entry['id'] ) );
					echo apply_filters( 'gravityflow_title_entry_detail', $title, $form, $entry );
					?>
				</th>
				<th style="width:140px; font-size:10px; text-align: right;">
					<?php
					if ( $allow_display_empty_fields ) {
						?>
						<input type="checkbox" id="gentry_display_empty_fields" <?php echo $display_empty_fields ? "checked='checked'" : '' ?> onclick="ToggleShowEmptyFields();" />&nbsp;&nbsp;
						<label for="gentry_display_empty_fields"><?php _e( 'show empty fields', 'gravityflow' ) ?></label>
					<?php
					}
					?>
				</th>
			</tr>
			</thead>
			<tbody class="<?php echo GFCommon::get_ul_classes( $form ) ?>">
			<?php
			$count = 0;
			$field_count = sizeof( $form['fields'] );
			$has_product_fields = false;
			$display_fields_mode = $current_step ? $current_step->display_fields_mode : 'all_fields';
			$display_fields_selected = $current_step && is_array( $current_step->display_fields_selected ) ? $current_step->display_fields_selected : array();

			foreach ( $form['fields'] as &$field ) {
				/* @var GF_Field $field */

				$display_field = true;

				if ( $display_fields_mode == 'selected_fields' ) {
					if ( ! in_array( $field->id, $display_fields_selected ) ) {
						$display_field = false;
					}
				} else {
					if ( GFFormsModel::is_field_hidden( $form, $field, array(), $entry ) ) {
						$display_field = false;
					}
				}

				$display_field = (bool) apply_filters( 'gravityflow_workflow_detail_display_field', $display_field, $field, $form, $entry, $current_step );

				switch ( RGFormsModel::get_input_type( $field ) ) {
					case 'section' :
						if ( ! GFCommon::is_section_empty( $field, $form, $entry ) || $display_empty_fields ) {
							$count ++;
							$is_last = $count >= $field_count ? true : false;
							?>
							<tr>
								<td colspan="2" class="entry-view-section-break<?php echo $is_last ? ' lastrow' : '' ?>"><?php echo esc_html( rgar( $field, 'label' ) ) ?></td>
							</tr>
						<?php
						}
						break;

					case 'captcha':
					case 'password':
					case 'page':
						//ignore captcha, password, page field
						break;

					case 'html':
						if ( $display_field ) {
							?>
							<tr>
								<td colspan="2" class="entry-view-field-value"><?php echo $field->content; ?></td>
							</tr>
							<?php
						}

						break;
					default :
						$field_id = $field->id;

						if ( in_array( $field_id, $editable_fields ) ) {

							if ( $current_step->conditional_logic_editable_fields_enabled ) {
								$field->conditionalLogicFields = GFFormDisplay::get_conditional_logic_fields( $form, $field->id );
							}

							if ( GFCommon::is_product_field( $field->type ) ) {
								$has_product_fields = true;
							}

							$posted_step_id = rgpost( 'step_id' );
							if ( $posted_step_id == $current_step->get_id() ) {
								$value = GFFormsModel::get_field_value( $field );
							} else {
								$value = GFFormsModel::get_lead_field_value( $entry, $field );
								if ( $field->get_input_type() == 'email' && $field->emailConfirmEnabled ) {
									$_POST[ 'input_' . $field->id . '_2' ] = $value;
								}
							}

							if ( $field->get_input_type() == 'fileupload' ) {
								$field->_is_entry_detail = true;
							}

							$content = self::get_field_content( $field, $value, $form, $entry );

							$content = apply_filters( 'gform_field_content', $content, $field, $value, $entry['id'], $form['id'] );
							$content = apply_filters( 'gravityflow_field_content', $content, $field, $value, $entry['id'], $form['id'] );

							echo $content;
						} else {

							//$field->conditionalLogic = null;

							if ( ! $display_field ) {
								continue;
							}

							if ( GFCommon::is_product_field( $field->type ) ) {
								$has_product_fields = true;
							}

							$value = RGFormsModel::get_lead_field_value( $entry, $field );

							$conditional_logic_fields = GFFormDisplay::get_conditional_logic_fields( $form, $field->id );
							if ( ! empty( $conditional_logic_fields ) ) {
								$field->conditionalLogicFields = $conditional_logic_fields;
								$field_input = self::get_field_input( $field, $value, $entry['id'], $form_id, $form );
								echo '<div style="display:none;"">' . $field_input . '</div>';
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
								$display_value = GFCommon::get_lead_field_display( $field, $value, $entry['currency'] );
							}

							$display_value = apply_filters( 'gform_entry_field_value', $display_value, $field, $entry, $form );

							if ( $display_empty_fields || ! empty( $display_value ) || $display_value === '0' ) {
								$count ++;
								$is_last  = $count >= $field_count && ! $has_product_fields ? true : false;
								$last_row = $is_last ? ' lastrow' : '';

								$display_value = empty( $display_value ) && $display_value !== '0' ? '&nbsp;' : $display_value;

								$content = '
                                <tr>
                                    <td colspan="2" class="entry-view-field-name">' . esc_html( rgar( $field, 'label' ) ) . '</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="entry-view-field-value' . $last_row . '">' . $display_value . '</td>
                                </tr>';
								$content = apply_filters( 'gform_field_content', $content, $field, $value, $entry['id'], $form['id'] );
								$content = apply_filters( 'gravityflow_field_content', $content, $field, $value, $entry['id'], $form['id'] );

								echo $content;

							}
						}


						break;
				}
			}


			$products = array();
			if ( $has_product_fields ) {
				$products = GFCommon::get_product_fields( $form, $entry );
				if ( ! empty( $products['products'] ) ) {
					?>
					<tr>
						<td colspan="2" class="entry-view-field-name"><?php echo apply_filters( "gform_order_label_{$form_id}", apply_filters( 'gform_order_label', __( 'Order', 'gravityflow' ), $form_id ), $form_id ) ?></td>
					</tr>
					<tr>
						<td colspan="2" class="entry-view-field-value lastrow">
							<table class="entry-products" cellspacing="0" width="97%">
								<colgroup>
									<col class="entry-products-col1" />
									<col class="entry-products-col2" />
									<col class="entry-products-col3" />
									<col class="entry-products-col4" />
								</colgroup>
								<thead>
								<th scope="col"><?php echo apply_filters( "gform_product_{$form_id}", apply_filters( 'gform_product', __( 'Product', 'gravityflow' ), $form_id ), $form_id ); ?></th>
								<th scope="col" class="textcenter"><?php echo esc_html( apply_filters( "gform_product_qty_{$form_id}", apply_filters( 'gform_product_qty', __( 'Qty', 'gravityflow' ), $form_id ), $form_id ) ); ?></th>
								<th scope="col"><?php echo esc_html( apply_filters( "gform_product_unitprice_{$form_id}", apply_filters( 'gform_product_unitprice', __( 'Unit Price', 'gravityflow' ), $form_id ), $form_id ) ); ?></th>
								<th scope="col"><?php echo esc_html( apply_filters( "gform_product_price_{$form_id}", apply_filters( 'gform_product_price', __( 'Price', 'gravityflow' ), $form_id ), $form_id ) ); ?></th>
								</thead>
								<tbody>
								<?php

								$total = 0;
								foreach ( $products['products'] as $product ) {
									?>
									<tr>
										<td>
											<div class="product_name"><?php echo esc_html( $product['name'] ); ?></div>
											<ul class="product_options">
												<?php
												$price = GFCommon::to_number( $product['price'] );
												if ( is_array( rgar( $product, 'options' ) ) ) {
													$count = sizeof( $product['options'] );
													$index = 1;
													foreach ( $product['options'] as $option ) {
														$price += GFCommon::to_number( $option['price'] );
														$class = $index == $count ? " class='lastitem'" : '';
														$index ++;
														?>
														<li<?php echo $class ?>><?php echo $option['option_label'] ?></li>
													<?php
													}
												}
												$subtotal = floatval( $product['quantity'] ) * $price;
												$total += $subtotal;
												?>
											</ul>
										</td>
										<td class="textcenter"><?php echo esc_html( $product['quantity'] ); ?></td>
										<td><?php echo GFCommon::to_money( $price, $entry['currency'] ) ?></td>
										<td><?php echo GFCommon::to_money( $subtotal, $entry['currency'] ) ?></td>
									</tr>
								<?php
								}
								$total += floatval( $products['shipping']['price'] );
								?>
								</tbody>
								<tfoot>
								<?php
								if ( ! empty( $products['shipping']['name'] ) ) {
									?>
									<tr>
										<td colspan="2" rowspan="2" class="emptycell">&nbsp;</td>
										<td class="textright shipping"><?php echo esc_html( $products['shipping']['name'] ); ?></td>
										<td class="shipping_amount"><?php echo GFCommon::to_money( $products['shipping']['price'], $entry['currency'] ) ?>&nbsp;</td>
									</tr>
								<?php
								}
								?>
								<tr>
									<?php
									if ( empty( $products['shipping']['name'] ) ) {
										?>
										<td colspan="2" class="emptycell">&nbsp;</td>
									<?php
									}
									?>
									<td class="textright grandtotal"><?php _e( 'Total', 'gravityflow' ) ?></td>
									<td class="grandtotal_amount"><?php echo GFCommon::to_money( $total, $entry['currency'] ) ?></td>
								</tr>
								</tfoot>
							</table>
						</td>
					</tr>

				<?php
				}
			}
			?>
			</tbody>
		</table>
		<div class="gform_footer">
			<input type="hidden" name="gform_unique_id" value="" />
			<input type="hidden" name="is_submit_<?php echo $form_id ?>" value="1" />
			<input type="hidden" name="step_id" value="<?php echo $current_step ? $current_step->get_id() : '' ?>" />
			<?php
			if ( GFCommon::has_multifile_fileupload_field( $form ) || ! empty( GFFormsModel::$uploaded_files[ $form_id ] ) ) {
				$files       = ! empty( GFFormsModel::$uploaded_files[ $form_id ] ) ? GFCommon::json_encode( GFFormsModel::$uploaded_files[ $form_id ] ) : '';
				$files_input = "<input type='hidden' name='gform_uploaded_files' id='gform_uploaded_files_{$form_id}' value='" . str_replace( "'", '&#039;', $files ) . "' />";
				echo $files_input;
			}
			//GFFormDisplay::print_form_scripts( $form, false );




			?>
		</div>

	<?php
	}

	public static function notes_grid( $notes, $is_editable, $emails = null, $subject = '' ) {

		if ( empty( $notes ) ) {
			return;
		}

		foreach ( $notes as $note ) {

			?>

			<div id="gravityflow-note-<?php echo $note->id; ?>" class="gravityflow-note gravityflow-note-<?php echo $note->user_name; ?>">
				<div class="gravityflow-note-avatar">
					<div>
						<?php

						if ( empty( $note->user_id ) ) {

							$img_url = '';

							if ( $note->user_name !== 'gravityflow' ) {
								$step = Gravity_Flow_Steps::get( $note->user_name );
								if ( $step ) {
									$img_url = $step->get_icon_url();
								}
							}

							if ( empty( $img_url ) ) {
								$img_url = gravity_flow()->get_base_url() . '/images/gravityflow-icon-blue.svg';
							}

							if ( strpos( $img_url, 'http' ) !== false ) {
								printf( '<img class="avatar avatar-65 photo" src="%s" style="width:65px;height:65px;" />', $img_url );
							} else {
								printf( '<span class="avatar avatar-65 photo">%s</span>', $img_url );
							}
						} else {
							echo get_avatar( $note->user_id, 65 );
						}

						?>
					</div>
					<div></div>
				</div>

				<div class="gravityflow-note-body-wrap">
					<div class="gravityflow-note-body">
						<div class="gravityflow-note-header">

							<div class="gravityflow-note-title">
								<?php

								if ( empty( $note->user_id ) ) {
									if ( $note->user_name == 'gravityflow' ) {
										echo esc_html( gravity_flow()->translate_navigation_label( 'Workflow' ) );
									} else {
										$step = Gravity_Flow_Steps::get( $note->user_name );
										if ( $step ) {
											echo $step->get_label();
										} else {
											echo esc_html( $note->user_name );
										}
									}
								} else {
									echo esc_html( $note->user_name );
								}

								?>
							</div>
							<div class="gravityflow-note-meta">
								<?php echo esc_html( GFCommon::format_date( $note->date_created, false, 'd M Y g:i a', false ) ) ?>
							</div>
						</div>

						<div class="gravityflow-note-body">
							<?php echo nl2br( esc_html( $note->value ) ) ?>
						</div>

					</div>
				</div>

			</div>
		<?php
		}

	}

	/**
	 * @param GF_Field $field
	 * @param string   $value
	 * @param int      $lead_id
	 * @param int      $form_id
	 * @param null     $form
	 *
	 * @return mixed|string|void
	 */
	public static function get_field_input( $field, $value = '', $lead_id = 0, $form_id = 0, $form = null ) {

		if ( ! $field instanceof GF_Field ) {
			$field = GF_Fields::create( $field );
		}

		$field->adminOnly = false;

		$id       = intval( $field->id );
		$field_id = 'input_' . $form_id . "_$id";

		$entry      = RGFormsModel::get_lead( $lead_id );
		$post_id   = $entry['post_id'];
		$post_link = '';
		if ( is_numeric( $post_id ) && GFCommon::is_post_field( $field ) ) {
			$post_link = "<div>You can <a href='post.php?action=edit&post=$post_id'>edit this post</a> from the post page.</div>";
		}

		$field_input = apply_filters( 'gform_field_input', '', $field, $value, $lead_id, $form_id );
		if ( $field_input ) {
			return $field_input;
		}

		// add categories as choices for Post Category field
		if ( $field->type == 'post_category' ) {
			$field = GFCommon::add_categories_as_choices( $field, $value );
		}

		$type = RGFormsModel::get_input_type( $field );
		switch ( $type ) {

			case 'honeypot':
				$autocomplete = RGFormsModel::is_html5_enabled() ? "autocomplete='off'" : '';

				return "<div class='ginput_container'><input name='input_{$id}' id='{$field_id}' type='text' value='' {$autocomplete}/></div>";
				break;

			case 'adminonly_hidden' :
				if ( ! is_array( $field->inputs ) ) {
					if ( is_array( $value ) ) {
						$value = json_encode( $value );
					}

					return sprintf( "<input name='input_%d' id='%s' class='gform_hidden' type='hidden' value='%s'/>", $id, $field_id, esc_attr( $value ) );
				}


				$fields = '';
				foreach ( $field->inputs as $input ) {
					$fields .= sprintf( "<input name='input_%s' class='gform_hidden' type='hidden' value='%s'/>", $input['id'], esc_attr( rgar( $value, strval( $input['id'] ) ) ) );
				}

				return $fields;
				break;

			default :

				if ( ! empty( $post_link ) ) {
					return $post_link;
				}

				if ( ! isset( $entry ) ) {
					$entry = null;
				}


				return $field->get_field_input( $form, $value, $entry );

				break;

		}

	}

	public static function get_field_content( GF_Field $field, $value, $form, $entry ) {

		$validation_message = ( $field->failed_validation && ! empty( $field->validation_message ) ) ? sprintf( "<div class='gfield_description validation_message'>%s</div>", $field->validation_message ) : '';

		$required_div = $field->isRequired ? sprintf( "<span class='gfield_required'>%s</span>", $field->isRequired ? '*' : '' ) : '';

		$target_input_id = $field->get_first_input_id( $form );

		$for_attribute = empty( $target_input_id ) ? '' : "for='{$target_input_id}'";

		$form_id = absint( $form['id'] );

		$td_id = 'field_' . $form_id . '_' . $field->id;
		$td_id = esc_attr( $td_id );

		$description = $field->get_description( $field->description, 'gfield_description' );

		$field->conditionalLogicFields = GFFormDisplay::get_conditional_logic_fields( $form, $field->id );

		$field_input = self::get_field_input( $field, $value, $entry['id'], $form_id, $form );

		if ( $field->is_description_above( $form ) ) {
			$clear       = "<div class='gf_clear'></div>";
			$field_input = $description . $field_input . $validation_message . $clear;
		} else {
			$field_input = $field_input . $description . $validation_message;
		}

		$field_content = "<tr valign='top'><td colspan='2' class='detail-view' id='{$td_id}'><ul><li><label class='gfield_label' $for_attribute >" . esc_html( rgar( $field, 'label' ) ) . $required_div . "</label>$field_input</li></ul>  </td></tr>";

		return $field_content;
	}

	/**
	 * Enqueue and retrieve all inline scripts that should be executed when the form is rendered.
	 * Use add_init_script() function to enqueue scripts.
	 *
	 * @param array $form
	 * @param array $field_values
	 * @param bool  $is_ajax
	 */
	public static function register_form_init_scripts( $form, $field_values = array(), $conditional_logic_enabled = true ) {
		$is_ajax = false;
		// adding conditional logic script if conditional logic is configured for this form.
		// get_conditional_logic also adds the chosen script for the enhanced dropdown option.
		// if this form does not have conditional logic, add chosen script separately
		if ( $conditional_logic_enabled && GFFormDisplay::has_conditional_logic( $form ) ) {
			GFFormDisplay::add_init_script( $form['id'], 'number_formats', GFFormDisplay::ON_PAGE_RENDER, GFFormDisplay::get_number_formats_script( $form ) );
			GFFormDisplay::add_init_script( $form['id'], 'conditional_logic', GFFormDisplay::ON_PAGE_RENDER, self::get_conditional_logic( $form, $field_values ) );
		}

		//adding currency config if there are any product fields in the form
		if ( self::has_price_field( $form ) ) {
			GFFormDisplay::add_init_script( $form['id'], 'pricing', GFFormDisplay::ON_PAGE_RENDER, GFFormDisplay::get_pricing_init_script( $form ) );
		}

		if ( self::has_password_strength( $form ) ) {
			$password_script = GFFormDisplay::get_password_strength_init_script( $form );
			GFFormDisplay::add_init_script( $form['id'], 'password', GFFormDisplay::ON_PAGE_RENDER, $password_script );
		}

		if ( self::has_enhanced_dropdown( $form ) ) {
			$chosen_script = GFFormDisplay::get_chosen_init_script( $form );
			GFFormDisplay::add_init_script( $form['id'], 'chosen', GFFormDisplay::ON_PAGE_RENDER, $chosen_script );
			GFFormDisplay::add_init_script( $form['id'], 'chosen', GFFormDisplay::ON_CONDITIONAL_LOGIC, $chosen_script );
		}

		if ( self::has_character_counter( $form ) ) {
			GFFormDisplay::add_init_script( $form['id'], 'character_counter', GFFormDisplay::ON_PAGE_RENDER, GFFormDisplay::get_counter_init_script( $form ) );
		}

		if ( GFFormDisplay::has_input_mask( $form ) ) {
			GFFormDisplay::add_init_script( $form['id'], 'input_mask', GFFormDisplay::ON_PAGE_RENDER, GFFormDisplay::get_input_mask_init_script( $form ) );
		}

		if ( GFFormDisplay::has_calculation_field( $form ) ) {
			GFFormDisplay::add_init_script( $form['id'], 'number_formats', GFFormDisplay::ON_PAGE_RENDER, GFFormDisplay::get_number_formats_script( $form ) );
			GFFormDisplay::add_init_script( $form['id'], 'calculation', GFFormDisplay::ON_PAGE_RENDER, GFFormDisplay::get_calculations_init_script( $form ) );
		}

		if ( self::has_currency_format_number_field( $form ) ) {
			GFFormDisplay::add_init_script( $form['id'], 'currency_format', GFFormDisplay::ON_PAGE_RENDER, GFFormDisplay::get_currency_format_init_script( $form ) );
		}

		if ( self::has_currency_copy_values_option( $form ) ) {
			GFFormDisplay::add_init_script( $form['id'], 'copy_values', GFFormDisplay::ON_PAGE_RENDER, GFFormDisplay::get_copy_values_init_script( $form ) );
		}

		if ( self::has_placeholder( $form ) ) {
			GFFormDisplay::add_init_script( $form['id'], 'placeholders', GFFormDisplay::ON_PAGE_RENDER, GFFormDisplay::get_placeholders_init_script( $form ) );
		}

		if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */
				if ( is_subclass_of( $field, 'GF_Field' ) ) {
					$field->register_form_init_scripts( $form );
				}
			}
		}

		gf_do_action( array( 'gform_register_init_scripts', $form['id'] ), $form, $field_values, $is_ajax );

	}

	private static function get_conditional_logic( $form, $field_values = array() ) {
		$logics            = '';
		$dependents        = '';
		$fields_with_logic = array();
		$default_values    = array();

		foreach ( $form['fields'] as $field ) {

			/* @var GF_Field $field */

			$field_deps = GFFormDisplay::get_conditional_logic_fields( $form, $field->id );
			$field_dependents[ $field->id ] = ! empty( $field_deps ) ? $field_deps : array();

			//use section's logic if one exists
			$section       = RGFormsModel::get_section( $form, $field->id );
			$section_logic = ! empty( $section ) ? rgar( $section, 'conditionalLogic' ) : null;

			$field_logic = $field->type != 'page' ? $field->conditionalLogic : null; //page break conditional logic will be handled during the next button click

			$next_button_logic = ! empty( $field->nextButton ) && ! empty( $field->nextButton['conditionalLogic'] ) ? $field->nextButton['conditionalLogic'] : null;

			if ( ! empty( $field_logic ) || ! empty( $next_button_logic ) ) {

				$field_section_logic = array( 'field' => $field_logic, 'nextButton' => $next_button_logic, 'section' => $section_logic );

				$logics .= $field->id . ': ' . GFCommon::json_encode( $field_section_logic ) . ',';

				$fields_with_logic[] = $field->id;

				$peers    = $field->type == 'section' ? GFCommon::get_section_fields( $form, $field->id ) : array( $field );
				$peer_ids = array();

				foreach ( $peers as $peer ) {
					$peer_ids[] = $peer->id;
				}

				$dependents .= $field->id . ': ' . GFCommon::json_encode( $peer_ids ) . ',';
			}

			//-- Saving default values so that they can be restored when toggling conditional logic ---
			$field_val  = '';
			$input_type = $field->get_input_type();
			$inputs     = $field->get_entry_inputs();

			//get parameter value if pre-populate is enabled
			if ( $field->allowsPrepopulate ) {
				if ( $input_type == 'checkbox' ) {
					$field_val = RGFormsModel::get_parameter_value( $field->inputName, $field_values, $field );
					if ( ! is_array( $field_val ) ) {
						$field_val = explode( ',', $field_val );
					}
				} elseif ( is_array( $inputs ) ) {
					$field_val = array();
					foreach ( $inputs as $input ) {
						$field_val["input_{$input['id']}"] = RGFormsModel::get_parameter_value( rgar( $input, 'name' ), $field_values, $field );
					}
				} elseif ( $input_type == 'time' ) { // maintained for backwards compatibility. The Time field now has an inputs array.
					$parameter_val = RGFormsModel::get_parameter_value( $field->inputName, $field_values, $field );
					if ( ! empty( $parameter_val ) && preg_match( '/^(\d*):(\d*) ?(.*)$/', $parameter_val, $matches ) ) {
						$field_val   = array();
						$field_val[] = esc_attr( $matches[1] ); //hour
						$field_val[] = esc_attr( $matches[2] ); //minute
						$field_val[] = rgar( $matches, 3 );     //am or pm
					}
				} elseif ( $input_type == 'list' ) {
					$parameter_val = RGFormsModel::get_parameter_value( $field->inputName, $field_values, $field );
					$field_val     = is_array( $parameter_val ) ? $parameter_val : explode( ',', str_replace( '|', ',', $parameter_val ) );

					if ( is_array( rgar( $field_val, 0 ) ) ) {
						$list_values = array();
						foreach ( $field_val as $row ) {
							$list_values = array_merge( $list_values, array_values( $row ) );
						}
						$field_val = $list_values;
					}
				} else {
					$field_val = RGFormsModel::get_parameter_value( $field->inputName, $field_values, $field );
				}
			}

			//use default value if pre-populated value is empty
			$field_val = $field->get_value_default_if_empty( $field_val );

			if ( is_array( $field->choices ) && $input_type != 'list' ) {

				//radio buttons start at 0 and checkboxes start at 1
				$choice_index     = $input_type == 'radio' ? 0 : 1;
				$is_pricing_field = GFCommon::is_pricing_field( $field->type );

				foreach ( $field->choices as $choice ) {

					if ( $input_type == 'checkbox' && ( $choice_index % 10 ) == 0 ){
						$choice_index++;
					}

					$is_prepopulated    = is_array( $field_val ) ? in_array( $choice['value'], $field_val ) : $choice['value'] == $field_val;
					$is_choice_selected = rgar( $choice, 'isSelected' ) || $is_prepopulated;

					if ( $is_choice_selected && $input_type == 'select' ) {
						$price = GFCommon::to_number( rgar( $choice, 'price' ) ) == false ? 0 : GFCommon::to_number( rgar( $choice, 'price' ) );
						$val   = $is_pricing_field && $field->type != 'quantity' ? $choice['value'] . '|' . $price : $choice['value'];
						$default_values[ $field->id ] = $val;
					} elseif ( $is_choice_selected ) {
						if ( ! isset( $default_values[ $field->id ] ) ) {
							$default_values[ $field->id ] = array();
						}

						$default_values[ $field->id ][] = "choice_{$form['id']}_{$field->id}_{$choice_index}";
					}
					$choice_index ++;
				}
			} elseif ( ! empty( $field_val ) ) {

				switch ( $input_type ) {
					case 'date':
						// for date fields; that are multi-input; and where the field value is a string
						// (happens with prepop, default value will always be an array for multi-input date fields)
						if ( is_array( $field->inputs ) && ( ! is_array( $field_val ) || ! isset( $field_val['m'] ) ) ) {

							$format    = empty( $field->dateFormat ) ? 'mdy' : esc_attr( $field->dateFormat );
							$date_info = GFcommon::parse_date( $field_val, $format );

							// converts date to array( 'm' => 1, 'd' => '13', 'y' => '1987' )
							$field_val = $field->get_date_array_by_format( array( $date_info['month'], $date_info['day'], $date_info['year'] ) );

						}
						break;
					case 'time':
						if ( is_array( $field_val ) ) {
							$ampm_key               = key( array_slice( $field_val, - 1, 1, true ) );
							$field_val[ $ampm_key ] = strtolower( $field_val[ $ampm_key ] );
						}
						break;
					case 'address':

						$state_input_id = sprintf( '%s.4', $field->id );
						if ( isset( $field_val[ $state_input_id ] ) && ! $field_val[ $state_input_id ] ) {
							$field_val[ $state_input_id ] = $field->defaultState;
						}

						$country_input_id = sprintf( '%s.6', $field->id );
						if ( isset( $field_val[ $country_input_id ] ) && ! $field_val[ $country_input_id ] ) {
							$field_val[ $country_input_id ] = $field->defaultCountry;
						}

						break;
				}

				$default_values[ $field->id ] = $field_val;

			}

		}

		$button_conditional_script = '';

		//adding form button conditional logic if enabled
		if ( isset( $form['button']['conditionalLogic'] ) ) {
			$logics .= '0: ' . GFCommon::json_encode( array( 'field' => $form['button']['conditionalLogic'], 'section' => null ) ) . ',';
			$dependents .= '0: ' . GFCommon::json_encode( array( 0 ) ) . ',';
			$fields_with_logic[] = 0;

			$button_conditional_script = "jQuery('#gform_{$form['id']}').submit(" .
			                             'function(event, isButtonPress){' .
			                             '    var visibleButton = jQuery(".gform_next_button:visible, .gform_button:visible, .gform_image_button:visible");' .
			                             '    return visibleButton.length > 0 || isButtonPress == true;' .
			                             '}' .
			                             ');';
		}

		if ( ! empty( $logics ) ) {
			$logics = substr( $logics, 0, strlen( $logics ) - 1 );
		} //removing last comma;

		if ( ! empty( $dependents ) ) {
			$dependents = substr( $dependents, 0, strlen( $dependents ) - 1 );
		} //removing last comma;

		$animation = rgar( $form, 'enableAnimation' ) ? '1' : '0';
		global $wp_locale;
		$number_format = $wp_locale->number_format['decimal_point'] == ',' ? 'decimal_comma' : 'decimal_dot';

		$str = "if(window['jQuery']){" .

		       "if(!window['gf_form_conditional_logic'])" .
		       "window['gf_form_conditional_logic'] = new Array();" .
		       "window['gf_form_conditional_logic'][{$form['id']}] = { logic: { {$logics} }, dependents: { {$dependents} }, animation: {$animation}, defaults: " . json_encode( $default_values ) . ", fields: " . json_encode( $field_dependents ) . " }; " .

		       "if(!window['gf_number_format'])" .
		       "window['gf_number_format'] = '" . $number_format . "';" .

		       'jQuery(document).ready(function(){' .
		       "gf_apply_rules({$form['id']}, " . json_encode( $fields_with_logic ) . ', true);' .
		       "jQuery('#gform_wrapper_{$form['id']}').show();" .
		       "jQuery(document).trigger('gform_post_conditional_logic', [{$form['id']}, null, true]);" .
		       $button_conditional_script .

		       '} );' .

		       '} ';

		return $str;
	}

	private static function has_price_field( $form ) {
		$has_price_field = false;
		foreach ( $form['fields'] as $field ) {
			$input_type      = GFFormsModel::get_input_type( $field );
			$has_price_field = GFCommon::is_product_field( $input_type ) ? true : $has_price_field;
		}

		return $has_price_field;
	}

	private static function has_character_counter( $form ) {

		if ( ! is_array( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( $field->maxLength && ! $field->inputMask ) {
				return true;
			}
		}

		return false;
	}

	private static function has_placeholder( $form ) {

		if ( ! is_array( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( $field->placeholder != '' ) {
				return true;
			}
			if ( is_array( $field->inputs ) ) {
				foreach ( $field->inputs as $input ) {
					if ( rgar( $input, 'placeholder' ) != '' ) {
						return true;
					}
				}
			}
		}

		return false;
	}


	private static function has_enhanced_dropdown( $form ) {

		if ( ! is_array( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( in_array( RGFormsModel::get_input_type( $field ), array( 'select', 'multiselect' ) ) && $field->enableEnhancedUI ) {
				return true;
			}
		}

		return false;
	}

	private static function has_password_strength( $form ) {

		if ( ! is_array( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( $field->type == 'password' && $field->passwordStrengthEnabled ) {
				return true;
			}
		}

		return false;
	}

	private static function has_other_choice( $form ) {

		if ( ! is_array( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( $field->type == 'radio' && $field->enableOtherChoice ) {
				return true;
			}
		}

		return false;
	}

	private static function has_currency_copy_values_option( $form ) {
		if ( is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				if ( $field->enableCopyValuesOption == true ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function has_fileupload_field( $form ) {
		if ( is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$input_type = RGFormsModel::get_input_type( $field );
				if ( in_array( $input_type, array( 'fileupload', 'post_image' ) ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function has_currency_format_number_field( $form ) {
		if ( is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$input_type = RGFormsModel::get_input_type( $field );
				if ( $input_type == 'number' && $field->numberFormat == 'currency' ) {
					return true;
				}
			}
		}

		return false;
	}
}
