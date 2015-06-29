<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Entry_Detail {

	/**
	 * @param $form
	 * @param $entry
	 * @param bool $allow_display_empty_fields
	 * @param array $editable_fields
	 * @param Gravity_Flow_Step $current_step
	 */
	public static function entry_detail( $form, $entry, $allow_display_empty_fields = false, $current_step = null, $check_view_entry_permissions = true, $admin_ui = true ){

		?>

		<script type="text/javascript">

			function DeleteFile(leadId, fieldId, deleteButton) {
				if (confirm(<?php echo json_encode( __( "Would you like to delete this file? 'Cancel' to stop. 'OK' to delete", 'gravityforms' ) ); ?>)) {
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
						alert(<?php echo json_encode( __( 'Ajax error while deleting file.', 'gravityforms' ) ) ?>)
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

			<?php if ( $admin_ui ) :	?>
			<h2 class="gf_admin_page_title">
				<span><?php echo esc_html__( 'Workflow Entry #', 'gravityflow' ) . absint( $entry['id'] ); ?></span><span class="gf_admin_page_subtitle"><span class="gf_admin_page_formid">ID: <?php echo absint( $form['id'] ); ?></span><span class='gf_admin_page_formname'><?php esc_html_e( 'Workflow Form Name', 'gravityforms' ) ?>: <?php esc_html_e( $form['title'] ); ?></span></span>

			</h2>
			<?php endif; ?>

			<?php
			if ( $admin_ui ) :
			?>
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
						return;
					}
				}
			}
			$url = remove_query_arg( array( 'gworkflow_token', 'new_status' ) );
			?>
			<form method="post" id="entry_form" enctype='multipart/form-data' action="<?php echo esc_url( $url ); ?>">
				<?php wp_nonce_field( 'gforms_save_entry', 'gforms_save_entry' ) ?>
				<div id="poststuff" class="metabox-holder has-right-sidebar">

					<div id="side-info-column" class="inner-sidebar">
						<?php
						gravity_flow()->workflow_entry_detail_status_box( $form, $entry );
						?>

						<!-- begin print button -->
						<div class="detail-view-print">
							<a href="javascript:;"
							   onclick="var notes_qs = jQuery('#gform_print_notes').is(':checked') ? '&notes=1' : ''; var url='<?php echo admin_url( 'admin-ajax.php' )?>?action=gravityflow_print_entries&lid=<?php echo absint( $entry['id'] ); ?>' + notes_qs; printPage(url);"
							   class="button"><?php esc_html_e( 'Print', 'gravityforms' ) ?></a>

								<input type="checkbox" name="print_notes" value="print_notes" checked="checked"
								       id="gform_print_notes"/>
								<label for="print_notes"><?php esc_html_e( 'include timeline', 'gravityforms' ) ?></label>

						</div>
						<!-- end print button -->

					</div>

					<div id="post-body" class="has-sidebar">
						<div id="post-body-content" class="has-sidebar-content">
							<?php

							do_action( 'gravityflow_entry_detail_content_before', $form, $entry );

							$current_user_status = $current_step ? $current_step->get_user_status() : false;
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

							self::entry_detail_grid( $form, $entry, true, $editable_fields, $current_step );

							do_action( 'gravityflow_entry_detail', $form, $entry );
								?>
								<div class="postbox">
									<h3>
										<label for="name"><?php esc_html_e( 'Timeline', 'gravityforms' ); ?></label>
									</h3>


										<div class="inside">
											<?php
											self::timeline( $entry, $form );
											?>
										</div>

								</div>
						</div>
					</div>

				</div>

			</form>
		</div>
		<?php
	}

	public static function timeline( $entry, $form ) {
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



	public static function entry_detail_grid( $form, $entry, $allow_display_empty_fields = false, $editable_fields = array(), $current_step = null ) {
		$form_id = absint( $form['id'] );

		$display_empty_fields = false;
		if ( $allow_display_empty_fields ) {
			$display_empty_fields = rgget( 'gf_display_empty_fields', $_COOKIE );
		}

		$display_empty_fields = (bool) apply_filters( 'gform_entry_detail_grid_display_empty_fields', $display_empty_fields, $form, $entry );

		?>

		<input type="hidden" name="action" id="action" value="" />
			<input type="hidden" name="save" id="action" value="Update" />
		<input type="hidden" name="screen_mode" id="screen_mode" value="<?php echo esc_attr( rgpost( 'screen_mode' ) ) ?>" />

		<table cellspacing="0" class="widefat fixed entry-detail-view">
			<thead>
			<tr>
				<th id="details">
					<?php
					$title = sprintf( '%s : %s %s', esc_html( $form['title'] ), __( 'Entry # ', 'gravityforms' ), absint( $entry['id'] ) );
					echo apply_filters( 'gravityflow_title_entry_detail', $title, $form, $entry );
					?>
				</th>
				<th style="width:140px; font-size:10px; text-align: right;">
					<?php
					if ( $allow_display_empty_fields ) {
						?>
						<input type="checkbox" id="gentry_display_empty_fields" <?php echo $display_empty_fields ? "checked='checked'" : '' ?> onclick="ToggleShowEmptyFields();" />&nbsp;&nbsp;
						<label for="gentry_display_empty_fields"><?php _e( 'show empty fields', 'gravityforms' ) ?></label>
					<?php
					}
					?>
				</th>
			</tr>
			</thead>
			<tbody>
			<?php
			$count = 0;
			$field_count = sizeof( $form['fields'] );
			$has_product_fields = false;
			foreach ( $form['fields'] as $field ) {
				switch ( RGFormsModel::get_input_type( $field ) ) {
					case 'section' :
						if ( ! GFCommon::is_section_empty( $field, $form, $entry ) || $display_empty_fields ) {
							$count ++;
							$is_last = $count >= $field_count ? true : false;
							?>
							<tr>
								<td colspan="2" class="entry-view-section-break<?php echo $is_last ? ' lastrow' : '' ?>"><?php echo esc_html( GFCommon::get_label( $field ) ) ?></td>
							</tr>
						<?php
						}
						break;

					case 'captcha':
					case 'html':
					case 'password':
					case 'page':
						//ignore captcha, html, password, page field
						break;


					default :

						$field_id = $field->id;

						if ( in_array( $field_id, $editable_fields ) ) {
							$field->_is_entry_detail = true;
							$value   = RGFormsModel::get_lead_field_value( $entry, $field );
							$td_id   = 'field_' . $form_id . '_' . $field_id;
							$td_id = esc_attr( $td_id );
							$content = "<tr valign='top'><td colspan='2' class='detail-view' id='{$td_id}'><label class='detail-label'>" . esc_html( GFCommon::get_label( $field ) ) . '</label>' .
							           self::get_field_input( $field, $value, $entry['id'], $form_id, $form ) . '</td></tr>';

							$content = apply_filters( 'gform_field_content', $content, $field, $value, $entry['id'], $form['id'] );

							echo $content;
						} else {

							$display_field = (bool) apply_filters( 'gravityflow_workflow_detail_display_field', true, $field, $form, $entry, $current_step );

							if ( ! $display_field ) {
								continue;
							}

							//ignore product fields as they will be grouped together at the end of the grid
							if ( GFCommon::is_product_field( $field->type ) ) {
								$has_product_fields = true;
								continue;
							}

							$value         = RGFormsModel::get_lead_field_value( $entry, $field );
							$display_value = GFCommon::get_lead_field_display( $field, $value, $entry['currency'] );

							$display_value = apply_filters( 'gform_entry_field_value', $display_value, $field, $entry, $form );

							if ( $display_empty_fields || ! empty( $display_value ) || $display_value === '0' ) {
								$count ++;
								$is_last  = $count >= $field_count && ! $has_product_fields ? true : false;
								$last_row = $is_last ? ' lastrow' : '';

								$display_value = empty( $display_value ) && $display_value !== '0' ? '&nbsp;' : $display_value;

								$content = '
                                <tr>
                                    <td colspan="2" class="entry-view-field-name">' . esc_html( GFCommon::get_label( $field ) ) . '</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="entry-view-field-value' . $last_row . '">' . $display_value . '</td>
                                </tr>';

								$content = apply_filters( 'gform_field_content', $content, $field, $value, $entry['id'], $form['id'] );

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
						<td colspan="2" class="entry-view-field-name"><?php echo apply_filters( "gform_order_label_{$form_id}", apply_filters( 'gform_order_label', __( 'Order', 'gravityforms' ), $form_id ), $form_id ) ?></td>
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
								<th scope="col"><?php echo apply_filters( "gform_product_{$form_id}", apply_filters( 'gform_product', __( 'Product', 'gravityforms' ), $form_id ), $form_id ); ?></th>
								<th scope="col" class="textcenter"><?php echo esc_html( apply_filters( "gform_product_qty_{$form_id}", apply_filters( 'gform_product_qty', __( 'Qty', 'gravityforms' ), $form_id ), $form_id ) ); ?></th>
								<th scope="col"><?php echo esc_html( apply_filters( "gform_product_unitprice_{$form_id}", apply_filters( 'gform_product_unitprice', __( 'Unit Price', 'gravityforms' ), $form_id ), $form_id ) ); ?></th>
								<th scope="col"><?php echo esc_html( apply_filters( "gform_product_price_{$form_id}", apply_filters( 'gform_product_price', __( 'Price', 'gravityforms' ), $form_id ), $form_id ) ); ?></th>
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
									<td class="textright grandtotal"><?php _e( 'Total', 'gravityforms' ) ?></td>
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
			<input type="hidden" name="gform_uploaded_files" id='gform_uploaded_files_<?php echo absint( $form_id ); ?>' value="" />
		</div>

	<?php
	}

	public static function notes_grid( $notes, $is_editable, $emails = null, $subject = '' ) {

		if ( empty( $notes ) ) {
			return;
		}

		foreach ( $notes as $note ) {


			?>

			<div id="gravityflow-note-<?php echo $note->id; ?>" class="gravityflow-note">
				<div class="gravityflow-note-avatar">
					<div>
						<?php
						echo get_avatar( $note->user_id, 65 );
						?>
					</div>
					<div></div>
				</div>

				<div class="gravityflow-note-body-wrap">
					<div class="gravityflow-note-body">
						<div class="gravityflow-note-header">

							<div class="gravityflow-note-title">
								<?php echo esc_html( $note->user_name ); ?>
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

		$is_form_editor = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin = $is_form_editor || $is_entry_detail;

		$id       = intval( $field->id );
		$field_id = $is_admin || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$form_id  = $is_admin && empty( $form_id ) ? rgget( 'id' ) : $form_id;

		if ( RG_CURRENT_VIEW == 'entry' ) {
			$lead      = RGFormsModel::get_lead( $lead_id );
			$post_id   = $lead['post_id'];
			$post_link = '';
			if ( is_numeric( $post_id ) && GFCommon::is_post_field( $field ) ) {
				$post_link = "<div>You can <a href='post.php?action=edit&post=$post_id'>edit this post</a> from the post page.</div>";
			}
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

				if ( ! isset( $lead ) ) {
					$lead = null;
				}

				return $field->get_field_input( $form, $value, $lead );

				break;

		}
	}


}