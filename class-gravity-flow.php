<?php

// Make sure Gravity Forms is active and already loaded.
if ( class_exists( 'GFForms' ) ) {

	// The Add-On Framework is not loaded by default.
	// Use the following function to load the appropriate files.
	GFForms::include_feed_addon_framework();

	class Gravity_Flow extends GFFeedAddOn {

		private static $_instance = null;

		public $_version = GRAVITY_FLOW_VERSION;

		// The Framework will display an appropriate message on the plugins page if necessary
		protected $_min_gravityforms_version = '1.9.6';

		protected $_slug = 'gravityflow';

		protected $_path = 'gravityflow/gravityflow.php';

		protected $_full_path = __FILE__;

		// Title of the plugin to be used on the settings page, form settings and plugins page.
		protected $_title = 'Gravity Flow';

		// Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
		protected $_short_title = 'Workflow';

		protected $_capabilities = array(
			'gravityflow_uninstall',
			'gravityflow_settings',
			'gravityflow_create_steps',
			'gravityflow_submit',
			'gravityflow_inbox',
			'gravityflow_status',
			'gravityflow_status_view_all',
			'gravityflow_workflow_detail_admin_actions',
		);

		protected $_capabilities_app_settings = 'gravityflow_settings';
		protected $_capabilities_form_settings = 'gravityflow_create_steps';
		protected $_capabilities_app_menu = array(
			'gravityflow_uninstall',
			'gravityflow_settings',
			'gravityflow_create_steps',
			'gravityflow_submit',
			'gravityflow_inbox',
			'gravityflow_status',
		);
		protected $_capabilities_uninstall = 'gravityflow_uninstall';

		public static function get_instance() {
			if ( self::$_instance == null ) {
				self::$_instance = new Gravity_Flow();
			}

			return self::$_instance;
		}

		private function __clone() {
		} /* do nothing */


		public function pre_init(){
			// Pending GF support for import hooks
			//add_filter( 'gform_export_form', array( $this, 'filter_gform_export_form' ) );
			parent::pre_init();

			if ( ! wp_next_scheduled( 'gravityflow_cron' ) ) {
				wp_schedule_event( time(), 'hourly', 'gravityflow_cron' );
			}

			add_action( 'gravityflow_cron', array( $this, 'cron' ) );
		}

		public function init() {
			parent::init();

			$this->maybe_redirect_after_activation();

			remove_filter( 'gform_entry_post_save', array( $this, 'maybe_process_feed' ), 10 );

			add_filter( 'gform_entry_post_save', array( $this, 'maybe_process_feed' ), 8, 2 );

			add_action( 'gform_after_submission', array( $this, 'after_submission' ), 9, 2 );
			add_action( 'gform_after_update_entry', array( $this, 'filter_after_update_entry' ), 10, 2 );

			add_shortcode( 'gravityflow', array( $this, 'shortcode' ), 10, 3 );

			add_action( 'gform_register_init_scripts', array( $this, 'filter_gform_register_init_scripts' ), 10, 3 );

			add_filter( 'auto_update_plugin', array( $this, 'maybe_auto_update' ), 10, 2 );
		}

		public function init_admin() {
			parent::init_admin();
			add_action( 'gform_entry_detail_sidebar_middle', array( $this, 'entry_detail_status_box' ), 10, 2 );
			add_filter( 'gform_notification_events', array( $this, 'add_notification_event' ) );

			add_filter( 'set-screen-option', array( $this, 'set_option' ), 10, 3 );
			add_action( 'load-workflow_page_gravityflow-status', array( $this, 'load_screen_options' ) );
			add_filter( 'gform_entries_field_value', array( $this, 'filter_gform_entries_field_value' ), 10, 4 );
			add_action( 'gform_field_standard_settings', array( $this, 'field_settings' ), 10, 2 );

			add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );

			add_filter( $this->_slug . '_feed_actions', array( $this, 'filter_feed_actions' ), 10, 3 );

			add_action( 'gform_post_form_duplicated', array( $this, 'action_gform_post_form_duplicated' ), 10, 2 );

		}

		public function init_ajax() {
			parent::init_ajax();
			add_action( 'wp_ajax_gravityflow_save_feed_order', array( $this, 'ajax_save_feed_order' ) );
			add_action( 'wp_ajax_gravityflow_feed_message', array( $this, 'ajax_feed_message' ) );

			add_action( 'wp_ajax_gravityflow_print_entries', array( $this, 'ajax_print_entries' ) );
		}

		public function init_frontend(){
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ), 10 );
		}

		public function upgrade( $previous_version ) {
			if ( empty( $previous_version ) ) {
				$settings = $this->get_app_settings();
				if ( defined( 'GRAVITY_FLOW_LICENSE_KEY' ) ) {
					$settings['license_key'] = GRAVITY_FLOW_LICENSE_KEY;
				} else {
					update_option( 'gravityflow_pending_installation', true );
				}
				$settings['background_updates'] = true;
				$this->update_app_settings( $settings );

			} elseif ( version_compare( $previous_version, '1.0-beta-3.5', '<' ) ) {
				$this->upgrade_feed_settings();
			}
		}

		public function upgrade_feed_settings(){

			$feeds = $this->get_feeds_by_slug( 'gravityflow' );
			foreach ( $feeds as $feed ) {
				$step_type_key = $feed['meta']['step_type'] . '_';
				foreach ( $feed['meta'] as $key => $meta ) {
					$pos = strpos( $key, $step_type_key );
					if ( $pos === 0 ) {
						$new_key  = substr_replace( $key, '', $pos, strlen( $step_type_key ) );

						if ( isset( $meta['choices'] ) && is_array( $meta['choices'] ) ){

							foreach ( $meta['choices'] as &$choice ) {

								$pos = strpos( $choice['name'], $step_type_key );
								if ( $pos === 0 ) {
									$choice['name']  = substr_replace( $choice['name'], '', $pos, strlen( $step_type_key ) );
								}
							}
						}
						unset( $feed['meta'][ $key ] );
						$feed['meta'][ $new_key ] = $meta;
					}

				}
				$this->update_feed_meta( $feed['id'], $feed['meta'] );
			}
		}


		// Enqueue the JavaScript and output the root url and the nonce.
		public function scripts() {
			$form_id        = absint( rgget( 'id' ) );
			$form           = GFAPI::get_form( $form_id );
			$routing_fields = ! empty( $form ) ? GFCommon::get_field_filter_settings( $form ) : array();
			$input_fields   = array();
			if ( is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					$input_fields[] = array( 'key' => absint( $field['id'] ), 'text' => esc_html__( $field['label'] ) );
				}
			}

			$users   = is_admin() ? $this->get_users_as_choices() : array();

			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

			$scripts = array(
				array(
					'handle'   => 'gravityflow_form_editor_js',
					'src'      => $this->get_base_url() . "/js/form-editor{$min}.js",
					'version'  => $this->_version,
					'enqueue'  => array(
						array( 'admin_page' => array( 'form_editor' ) ),
					),
				),
				array(
					'handle'  => 'gravityflow_multi_select',
					'src'     => $this->get_base_url() . "/js/multi-select{$min}.js",
					'deps'    => array( 'jquery' ),
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=_notempty_' ),
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=0' ),
					),
				),
				array(
					'handle'  => 'gravityflow_form_settings_js',
					'src'     => $this->get_base_url() . "/js/form-settings{$min}.js",
					'deps'    => array( 'jquery', 'jquery-ui-core', 'jquery-ui-tabs', 'jquery-ui-datepicker', 'gform_datepicker_init' ),
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=_notempty_' ),
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=0' ),
					),
					'strings' => array(
						'feedId' => absint( rgget( 'fid' ) ),
						'formId' => absint( rgget( 'id' ) ),
					)
				),
				array(
					'handle'  => 'gravityflow_feed_list',
					'src'     => $this->get_base_url() . "/js/feed-list{$min}.js",
					'deps'    => array( 'jquery', 'jquery-ui-sortable' ),
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow' ),
					),
				),
				array(
					'handle'  => 'gravityflow_entry_detail',
					'src'     => $this->get_base_url() . "/js/entry-detail{$min}.js",
					'version' => $this->_version,
					'enqueue' => array(
						array(
							'query' => 'page=gravityflow-inbox',
						),
					),
				),
				array(
					'handle'  => 'gravityflow_status_list',
					'src'     => $this->get_base_url() . "/js/status-list{$min}.js",
					'deps'    => array( 'jquery' ),
					'version' => $this->_version,
					'enqueue' => array(
						array(
							'query' => 'page=gravityflow-status',
						),
					),
					'strings' => array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) )
				),
				array(
					'handle'  => 'gf_routing_setting',
					'src'     => $this->get_base_url() . "/js/routing-setting{$min}.js",
					'deps'    => array( 'jquery' ),
					'version' => $this->_version,
					'enqueue' => array(
						array( 'admin_page' => array( 'form_settings' ) ),
					),
					'strings' => array(
						'accounts'     => $users,
						'fields'       => $routing_fields,
						'input_fields' => $input_fields,
					)
				),
			);

			return array_merge( parent::scripts(), $scripts );
		}

		public function enqueue_frontend_scripts(){
			global $wp_query;
			if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
				$shortcode_found = false;
				foreach ( $wp_query->posts as $post ) {
					if ( stripos( $post->post_content, '[gravityflow' ) !== false ) {
						$shortcode_found = true;
						break;
					}
				}

				if ( $shortcode_found ) {
					$this->enqueue_form_scripts();
					$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
					wp_enqueue_script( 'gravityflow_entry_detail', $this->get_base_url() . "/js/entry-detail{$min}.js", array( 'jquery' ), $this->_version );
					wp_enqueue_script( 'gravityflow_status_list', $this->get_base_url() . "/js/status-list{$min}.js",  array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'gform_datepicker_init' ), $this->_version );
					wp_enqueue_script( 'gravityflow_frontend', $this->get_base_url() . "/js/frontend{$min}.js",  array(), $this->_version );
					wp_enqueue_style( 'gform_admin',  GFCommon::get_base_url() . "/css/admin{$min}.css", null, $this->_version );
					wp_enqueue_style( 'gravityflow_entry_detail',  $this->get_base_url() . "/css/entry-detail{$min}.css", null, $this->_version );
					wp_enqueue_style( 'gravityflow_frontend_css', $this->get_base_url() . "/css/frontend{$min}.css", null, $this->_version );
					wp_enqueue_style( 'gravityflow_status', $this->get_base_url() . "/css/status{$min}.css", null, $this->_version );
					wp_localize_script( 'gravityflow_status_list', 'gravityflow_status_list_strings', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
				}
			}
		}

		public function filter_gform_register_init_scripts( $form, $field_values, $is_ajax ){

			if ( $this->has_enhanced_dropdown( $form ) ) {
				$chosen_script = $this->get_chosen_init_script( $form );
				GFFormDisplay::add_init_script( $form['id'], 'workflow_assignee_chosen', GFFormDisplay::ON_PAGE_RENDER, $chosen_script );
				GFFormDisplay::add_init_script( $form['id'], 'workflow_assignee_chosen', GFFormDisplay::ON_CONDITIONAL_LOGIC, $chosen_script );
			}
		}

		public static function get_chosen_init_script( $form ) {
			$chosen_fields = array();
			foreach ( $form['fields'] as $field ) {
				$input_type = GFFormsModel::get_input_type( $field );
				if ( $field->enableEnhancedUI && in_array( $input_type, array( 'workflow_assignee_select' ) ) ) {
					$chosen_fields[] = "#input_{$form['id']}_{$field->id}";
				}
			}

			return "gformInitChosenFields('" . implode( ',', $chosen_fields ) . "','" . esc_attr( apply_filters( "gform_dropdown_no_results_text_{$form['id']}", apply_filters( 'gform_dropdown_no_results_text', __( 'No results matched', 'gravityflow' ), $form['id'] ), $form['id'] ) ) . "');";
		}

		public function has_enhanced_dropdown( $form ) {

			if ( ! is_array( $form['fields'] ) ) {
				return false;
			}

			foreach ( $form['fields'] as $field ) {
				if ( in_array( RGFormsModel::get_input_type( $field ), array( 'workflow_assignee_select' ) ) && $field->enableEnhancedUI ) {
					return true;
				}
			}

			return false;
		}

		public function feed_list_title() {
			$url = add_query_arg( array( 'fid' => '0' ) );

			return esc_html__( 'Workflow Steps', 'gravityflow' ) . " <a class='add-new-h2' href='{$url}'>" . __( 'Add New' , 'gravityflow' ) . '</a>';
		}

		public function styles() {

			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

			$styles = array(
				array(
					'handle'  => 'gform_admin',
					'src'     => GFCommon::get_base_url() . "/css/admin{$min}.css",
					'version' => GFForms::$version,
					'enqueue' => array(
						array(
							'query'      => 'page=gravityflow-inbox',
						),
						array(
							'query'      => 'page=gravityflow-submit',
						),
						array(
							'query'      => 'page=gravityflow-status',
						),
					)
				),
				array(
					'handle'  => 'gravityflow_inbox',
					'src'     => $this->get_base_url() . "/css/inbox{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array(
							'query'      => 'page=gravityflow-inbox',
						),
					)
				),
				array(
					'handle'  => 'gravityflow_entry_detail',
					'src'     => $this->get_base_url() . "/css/entry-detail{$min}.css",
					'version' => $this->_version,
					'deps' => array( 'gform_admin' ),
					'enqueue' => array(
						array(
							'query'      => 'page=gravityflow-inbox&view=entry',
						),
					)
				),
				array(
					'handle'  => 'gravityflow_submit',
					'src'     => $this->get_base_url() . "/css/submit{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array(
							'query'      => 'page=gravityflow-submit',
						),
					)
				),
				array(
					'handle'  => 'gravityflow_status',
					'src'     => $this->get_base_url() . "/css/status{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array(
							'query'      => 'page=gravityflow-status',
						),
					)
				),
				array(
					'handle'  => 'gravityflow_feed_list',
					'src'     => $this->get_base_url() . "/css/feed-list{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow' ),
					),
				),
				array(
					'handle'  => 'gravityflow_multi_select_css',
					'src'     => $this->get_base_url() . "/css/multi-select{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=_notempty_' ),
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=0' ),
					),
				),
				array(
					'handle'  => 'gravityflow_form_settings',
					'src'     => $this->get_base_url() . "/css/form-settings{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=_notempty_' ),
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=0' ),
					),
				),
			);

			return array_merge( parent::styles(), $styles );
		}

		public function feed_settings_title() {
			return esc_html__( 'Workflow Step Settings', 'gravityflow' );
		}

		public function set_option($status, $option, $value){
			if ( 'entries_per_page' == $option ) {
				return $value;
			}

			return $status;
		}

		public function get_users_as_choices() {

			$editable_roles = array_reverse( get_editable_roles() );

			$role_choices = array();
			foreach ( $editable_roles as $role => $details ) {
				$name           = translate_user_role( $details['name'] );
				$role_choices[] = array( 'value' => 'role|' . $role, 'label' => $name );
			}

			$args            = apply_filters( 'gform_filters_get_users', array( 'number' => 200 ) );
			$accounts        = get_users( $args );
			$account_choices = array();
			foreach ( $accounts as $account ) {
				$account_choices[] = array( 'value' => 'user_id|' . $account->ID, 'label' => $account->display_name );
			}

			$choices = array(
				array(
					'label'   => __( 'Users', 'gravityflow' ),
					'choices' => $account_choices,
				),
				array(
					'label'   => __( 'Roles', 'gravityflow' ),
					'choices' => $role_choices,
				),
			);

			$form_id = absint( rgget( 'id' ) );

			$form = GFAPI::get_form( $form_id );

			$field_choices = array();

			$assignee_fields_as_choices = $this->get_assignee_fields_as_choices( $form );

			if ( ! empty( $assignee_fields_as_choices ) ) {
				$field_choices = $assignee_fields_as_choices;
			}

			if ( rgar( $form, 'requireLogin' ) ) {
				$field_choices[] = array(
					'label' => __( 'User (Created by)', 'gravityflow' ),
					'value' => 'entry|created_by',
				);
			}

			if ( ! empty( $field_choices ) ) {
				$choices[] = array(
					'label'   => __( 'Fields', 'gravityflow' ),
					'choices' => $field_choices,
				);
			}

			return $choices;
		}

		public function get_assignee_fields_as_choices( $form = null){
			if ( empty( $form ) ){
				$form_id = absint( rgget( 'id' ) );
				$form = GFAPI::get_form( $form_id );
			}

			$assignee_fields = array();
			if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					/* @var GF_Field $field */
					if ( GFFormsModel::get_input_type( $field ) == 'workflow_assignee_select' ) {
						$assignee_fields[] = array( 'label' => GFFormsModel::get_label( $field ), 'value' => 'assignee_field|' . $field->id );
					}
				}
			}
			return $assignee_fields;
		}

		/**
		 * Override the feed_settings_field() function and return the configuration for the Feed Settings.
		 * Updating is handled by the Framework.
		 *
		 * @return array
		 */
		function feed_settings_fields() {

			$current_step_id = $this->get_current_feed_id();

			$step_type_choices = array();

			$step_classes = Gravity_Flow_Steps::get_all();

			foreach ( $step_classes as $key => $step_class ) {
				$step_type_choice = array( 'label' => $step_class->get_label(), 'value' => $step_class->get_type() );
				if ( $current_step_id > 0 ) {
					$step_type_choice['disabled'] = 'disabled';
				}
				$step_settings = $step_class->get_settings();
				if ( empty( $step_settings ) ) {
					unset( $step_classes[ $key ] );
				} else {
					$step_type_choices[] = $step_type_choice;
				}
			}

			$settings = array();

			$step_type_setting = array(
				'name'       => 'step_type',
				'label'      => esc_html__( 'Step Type', 'gravityflow' ),
				'type'       => 'radio',
				'horizontal' => true,
				'required'   => true,
				'onchange' => 'jQuery(this).parents("form").submit();',
				'choices'    => $step_type_choices,
			);


			$settings[] = array(
				'title'  => 'Step',
				'fields' => array(
					array(
						'name'     => 'step_name',
						'label'    => __( 'Name', 'gravityflow' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<h6>' . __( 'Name', 'gravityflow' ) . '</h6>' . __( 'Enter a name to uniquely identify this step.', 'gravityflow' )
					),
					array(
						'name'  => 'description',
						'label' => 'Description',
						'class' => 'fieldwidth-3 fieldheight-2',
						'type'  => 'textarea',
					),
					$step_type_setting,
					array(
						'name'           => 'condition',
						'tooltip'        => "Build the conditional logic that should be applied to this step before it's allowed to be processed.",
						'label'          => 'Condition',
						'type'           => 'feed_condition',
						'checkbox_label' => 'Enable Condition for this step',
						'instructions'   => 'Perform this step if',
					),
					array(
						'name' => 'scheduled',
						'label' => esc_html__( 'Schedule' ),
						'type' => 'schedule',
						'tooltip' => esc_html__( 'Scheduling a step will queue entries and prevent them from starting this step until the specified date or until the delay period has elapsed.', 'gravityflow' )
					),
				)
			);

			foreach ( $step_classes as $step_class ) {
				$type = $step_class->get_type();
				$step_settings = $step_class->get_settings();
				$step_settings['id'] = 'gravityflow-step-settings-' . $type;
				$step_settings['class'] = 'gravityflow-step-settings';
				if ( isset( $step_settings['fields'] ) && is_array( $step_settings['fields'] ) ) {
					//$step_settings['fields'] = $this->prefix_step_settings_fields( $step_settings['fields'], $type );
					$final_status_options = $step_class->get_final_status_config();
					foreach ( $final_status_options as $final_status_option ) {
						$step_settings['fields'][] = array(
							'name' => 'destination_' . $final_status_option['status'],
							'label' => $final_status_option['default_destination_label'],
							'type'       => 'step_selector',
							'default_value' => $final_status_option['default_destination'],
						);
					}
				}
				$step_settings['dependency'] = array( 'field' => 'step_type', 'values' => array( $type ) );
				$settings[] = $step_settings;
			}

			$list_url         = remove_query_arg( 'fid' );
			$new_url          = add_query_arg( array( 'fid' => 0 ) );
			$success_feedback = sprintf( __( 'Step settings updated. %sBack to the list%s or %sAdd another step%s.', 'gravityflow' ), '<a href="' . esc_url( $list_url ) . '">', '</a>', '<a href="' . esc_url( $new_url ) . '">', '</a>' );

			$settings[] = array(
				'id'     => 'save_button',
				'fields' => array(
					array(
						'id'       => 'save_button',
						'type'     => 'save',
						'validation_callback' => array( $this, 'save_feed_validation_callback' ),
						'name' => 'save_button',
						'value'    => __( 'Update Step Settings', 'gravityflow' ),
						'messages' => array(
							'success' => $success_feedback,
							'error'   => __( 'There was an error while saving the step settings', 'gravityflow' )
						)
					),
				)
			);

			return $settings;
		}

		public function ajax_feed_message(){

			$current_step_id = absint( rgget( 'fid' ) );
			$form_id = rgget( 'id' );
			$entry_count = 0;
			if ( $current_step_id ) {
				$current_step = $this->get_step( $current_step_id );
				$entry_count = $current_step->entry_count();
			}
			if ( $entry_count > 0 ) {
				$html = '<div class="delete-alert alert_red"><i class="fa fa-exclamation-triangle gf_invalid"></i> ' . sprintf( _n( 'There is %s entry currently on this step. This entry may be affected if the settings are changed.', 'There are %s entries currently on this step. These entries may be affected if the settings are changed.', $entry_count, 'gravityflow' ), $entry_count ) . '</div>';

			} else {
				$html = '';
			}

			echo $html;
			die();
		}

		public function save_feed_validation_callback( $field, $field_setting ){

			$current_step_id = $this->get_current_feed_id();
			$entry_count = 0;
			if ( $current_step_id ) {
				$current_step = $this->get_step( $current_step_id );
				$entry_count = $current_step->entry_count();
			}

			if ( $entry_count > 0 ) {
				$assignee_settings['assignees'] = array();
				$current_assignee_details = $current_step->get_assignees();
				foreach ( $current_assignee_details as $assignee_detail ) {
					$assignee_settings['assignees'][] = is_array( $assignee_detail ) ? $assignee_detail['assignee'] : $assignee_detail;
				}
				if ( $current_step->get_type() == 'approval' ) {
					$assignee_settings['unanimous_approval'] = $current_step->unanimous_approval;
				}

				$this->_assignee_settings_md5 = md5( serialize( $assignee_settings ) );
			}

			return true;
		}

		public function update_feed_meta( $id, $meta ) {
			parent::update_feed_meta( $id, $meta );
			$results = $this->maybe_refresh_assignees();

			if ( ! empty( $results['removed'] ) || ! empty( $results['added'] ) ) {
				GFCommon::add_message( 'Assignees updated' );
			}
		}

		public function maybe_refresh_assignees(){
			$results = array(
				'removed' => array(),
				'added' => array()
			);

			if ( ! ( rgget( 'page' ) == 'gf_edit_forms' && rgget( 'view' ) == 'settings' && rgget( 'subview' ) == 'gravityflow' ) ) {
				return $results;
			}

			$current_step_id = $this->get_current_feed_id();
			$current_step = $this->get_step( $current_step_id );
			if ( empty ( $current_step ) ){
				return $results;
			}
			$assignee_settings['assignees'] = array();
			$current_assignee_details = $current_step->get_assignees();
			foreach ( $current_assignee_details as $assignee_detail ) {
				$assignee_settings['assignees'][] = is_array( $assignee_detail ) ? $assignee_detail['assignee'] : $assignee_detail;
			}
			if ( $current_step->get_type() == 'approval' ) {
				$assignee_settings['unanimous_approval'] = $current_step->unanimous_approval;
			}
			$assignee_settings_md5 = md5( serialize( $assignee_settings ) );
			if ( isset( $this->_assignee_settings_md5 ) && $this->_assignee_settings_md5 !== $assignee_settings_md5 ) {
				$results = $this->refresh_assignees();
			}
			return $results;
		}

		public function refresh_assignees(){
			$results = array(
				'removed' => array(),
				'added' => array()
			);
			$current_step_id = $this->get_current_feed_id();
			$form_id = absint( rgget( 'id' ) );
			$current_step = $this->get_step( $form_id, $current_step_id );

			$entry_count = $current_step->entry_count();

			if ( $entry_count == 0 ) {
				// Nothing to do
				return $results;
			}

			$form = $this->get_current_form();


			// Avoid paging through entries from GFAPI::get_entries() by using custom query.
			$assignee_status_by_entry = $this->get_asssignee_status_by_entry( $form['id'] );

			foreach ( $assignee_status_by_entry as $entry_id => $assignee_status ){
				$entry = GFAPI::get_entry( $entry_id );
				$step_for_entry = $this->get_step( $current_step_id, $entry );
				if ( $entry['workflow_step'] != $step_for_entry->get_id() ) {
					continue;
				}
				$updated = false;
				$current_assignees = $step_for_entry->get_assignees();
				foreach ( $current_assignees as $assignee ) {
					if ( is_array( $assignee ) ) {
						$assignee = $assignee['assignee'];
					}
					list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee, 2 );
					if ( $assignee_type == 'assignee_field' ) {
						$assignee = rgar( $entry, $assignee_id );
					} elseif ( $assignee_type == 'entry' ) {
						$assignee_id = rgar( $entry, $assignee_id );
						$assignee = 'user_id|' . $assignee_id;
					}

					if ( ! isset( $assignee_status[ $assignee ] ) ) {
						// New assignee - set & send
						$step = $this->get_step( $current_step_id, $entry );
						$step->update_assignee_status( $assignee, null, 'pending' );
						$step->maybe_send_assignee_notification( $assignee );
						$step->end_if_complete();
						$results['added'][] = $assignee;
						$updated = true;
					}
				}

				foreach ( $assignee_status as $old_assignee => $old_status ) {
					foreach ( $current_assignees as $assignee ) {
						if ( is_array( $assignee ) ) {
							$assignee = $assignee['assignee'];
						}
						list( $assignee_type, $assignee_id ) = rgexplode( '|', $assignee, 2 );
						if ( $assignee_type == 'assignee_field' ) {
							$assignee = rgar( $entry, $assignee_id );
						} elseif ( $assignee_type == 'entry' ) {
							$assignee_id = rgar( $entry, $assignee_id );
							$assignee = 'user_id|' . $assignee_id;
						}
						if ( $assignee == $old_assignee ) {
							continue 2;
						}
					}
					// No longer an assignee - remove
					$step_for_entry->remove_assignee( $old_assignee );
					$results['removed'][] = $old_assignee;
					$updated = true;
				}

				if ( $updated ) {
					$this->process_workflow( $form, $entry_id );
				}
			}

			return $results;
		}

		public function get_asssignee_status_by_entry( $form_id ){
			global $wpdb;
			$assignee_status_by_entry = array();
			$table = GFFormsModel::get_lead_meta_table_name();
			$lead_table = GFFormsModel::get_lead_table_name();
			$sql = $wpdb->prepare( "
			SELECT *
			FROM $table m
			INNER JOIN $lead_table l
			ON l.id = m.lead_id
			WHERE m.meta_key LIKE %s
			AND m.form_id=%d
			AND l.status='active'", 'workflow_user_id_%', $form_id );
			$rows = $wpdb->get_results( $sql );

			if ( ! is_wp_error( $rows ) && count( $rows ) > 0 ) {
				foreach ( $rows as $row ) {
					$user_id = str_replace( 'workflow_user_id_', '', $row->meta_key );
					if ( ! isset( $assignee_status_by_entry[ $row->lead_id ] ) ) {
						$assignee_status_by_entry[ $row->lead_id ] = array();
					}
					$assignee_status_by_entry[ $row->lead_id ][ 'user_id|' . $user_id ] = $row->meta_value;
				}
			}

			$sql = $wpdb->prepare( "
			SELECT *
			FROM $table m
			INNER JOIN $lead_table l
			ON l.id = m.lead_id
			WHERE m.meta_key LIKE %s
			AND m.form_id=%d
			AND l.status='active'", 'workflow_role_id_%', $form_id );
			$rows = $wpdb->get_results( $sql );

			if ( ! is_wp_error( $rows ) && count( $rows ) > 0 ) {
				foreach ( $rows as $row ) {
					$user_id = str_replace( 'workflow_role_id_', '', $row->meta_key );
					if ( ! isset( $assignee_status_by_entry[ $row->lead_id ] ) ) {
						$assignee_status_by_entry[ $row->lead_id ] = array();
					}
					$assignee_status_by_entry[ $row->lead_id ][ 'user_id|' . $user_id ] = 'user_id|' . $user_id;
				}
			}

			return $assignee_status_by_entry;
		}

		public function filter_gform_entries_field_value( $value, $form_id, $field_id, $entry ){
			if ( $field_id == 'workflow_step' && ! empty( $value ) ) {
				$step = $this->get_step( $value );
				if ( $step ) {
					$value = $step->get_name();
				}
			}
			return $value;
		}

		function ajax_save_feed_order() {
			$feed_ids = rgpost( 'feed_ids' );
			$form_id  = absint( rgpost( 'form_id' ) );
			foreach ( $feed_ids as &$feed_id ) {
				$feed_id = absint( $feed_id );
			}
			update_option( 'gravityflow_feed_order_' . $form_id, $feed_ids );

			echo json_encode( array( array( 'ok' ), 200 ) );
			die();
		}

		function ajax_print_entries(){
			require_once($this->get_base_path() . '/includes/pages/class-print-entries.php');
			Gravity_Flow_Print_Entries::render();
			exit();
		}

		public function get_feeds( $form_id = null ) {

			$feeds = parent::get_feeds( $form_id );

			$ordered_ids = get_option( 'gravityflow_feed_order_' . $form_id );

			if ( $ordered_ids ) {
				$feeds = array_reverse( $feeds );
			}

			if ( ! empty( $ordered_ids ) ) {
				$this->step_order = $ordered_ids;

				usort( $feeds, array( $this, 'sort_feeds' ) );

			}

			return $feeds;
		}

		/**
		 * @param null $form_id
		 * @param null $entry
		 *
		 * @return Gravity_Flow_Step[]
		 */
		public function get_steps( $form_id = null, $entry = null ) {
			$feeds = $this->get_feeds( $form_id );

			$steps = array();

			foreach ( $feeds as $feed ) {
				$steps[] = Gravity_Flow_Steps::create( $feed, $entry );
			}

			return $steps;
		}

		public function sort_feeds( $a, $b ) {
			$order = $this->step_order;
			$a     = array_search( $a['id'], $order );
			$b     = array_search( $b['id'], $order );

			if ( $a === false && $b === false ) {
				return 0;
			} else if ( $a === false ) {
				return 1;
			} else if ( $b === false ) {
				return - 1;
			} else {
				return $a - $b;
			}
		}

		public function settings_schedule(){

			$scheduled = array(
				'name' => 'scheduled',
				'type' => 'checkbox',
				'choices' => array(
					array(
						'label' => esc_html__( 'Schedule this step', 'gravityflow' ),
						'name' => 'scheduled',
					),
				),
			);

			$schedule_type = array(
				'name' => 'schedule_type',
				'type' => 'radio',
				'horizontal' => true,
				'default_value' => 'delay',
				'choices' => array(
					array(
						'label' => esc_html__( 'Delay' ),
						'value' => 'delay',
					),
					array(
						'label' => esc_html__( 'Date' ),
						'value' => 'date',
					),
				)
			);

			$schedule_date = array(
				'id' => 'schedule_date',
				'name' => 'schedule_date',
				'placeholder' => 'mm/dd/yyyy',
				'class' => 'datepicker datepicker_with_icon',
				'label' => esc_html__( 'Schedule', 'gravityflow' ),
				'type' => 'text',
			);

			$delay_offset_field = array(
				'name' => 'schedule_delay_offset',
				'class' => 'small-text',
				'label' => esc_html__( 'Schedule', 'gravityflow' ),
				'type' => 'text',
			);

			$unit_field = array(
				'name' => 'schedule_delay_unit',
				'label' => esc_html__( 'Schedule', 'gravityflow' ),
				'choices' => array(
					array(
						'label' => esc_html__( 'Hour(s)', 'gravityflow' ),
						'value' => 'hours',
					),
					array(
						'label' => esc_html__( 'Day(s)', 'gravityflow' ),
						'value' => 'days',
					),
					array(
						'label' => esc_html__( 'Week(s)', 'gravityflow' ),
						'value' => 'weeks',
					),
				)
			);

			$this->settings_checkbox( $scheduled );

			$feed_id = $this->get_current_feed_id();
			$enabled = false;
			$schedule_type_setting = 'delay'; // default
			if ( ! empty( $feed_id ) ) {
				$feed = $this->get_feed( $feed_id );
				$enabled = rgar( $feed['meta'], 'scheduled' );
				$schedule_type_setting = rgar( $feed['meta'], 'schedule_type' );
			}
			$schedule_style = $enabled ? '' : 'style="display:none;"';
			$schedule_date_style = ( $schedule_type_setting == 'date' ) ? '' : 'style="display:none;"';
			$schedule_delay_style = ( $schedule_type_setting !== 'date' ) ? '' : 'style="display:none;"';
			?>
			<div class="gravityflow-schedule-settings" <?php echo $schedule_style ?> >
				<div class="gravityflow-schedule-type-container">
					<?php $this->settings_radio( $schedule_type ); ?>
				</div>
				<div class="gravityflow-schedule-date-container" <?php echo $schedule_date_style ?> >
					<?php
					esc_html_e( 'Start this step on', 'gravityflow' );
					echo '&nbsp;';
					$this->settings_text( $schedule_date );
					?>
					<input type="hidden" id="gforms_calendar_icon_schedule_date" class="gform_hidden" value="<?php echo GFCommon::get_base_url() . '/images/calendar.png'; ?>" />
				</div>
				<div class="gravityflow-schedule-delay-container" <?php echo $schedule_delay_style ?>>
					<?php
					esc_html_e( 'Start this step', 'gravityflow' );
					echo '&nbsp;';
					$this->settings_text( $delay_offset_field );
					$this->settings_select( $unit_field );
					echo '&nbsp;';
					esc_html_e( 'after the workflow step is triggered.' );
					?>
				</div>
			</div>
			<script>
				(function($) {
					$( '#scheduled' ).click(function(){
						$('.gravityflow-schedule-settings').slideToggle();
					});
					$( '#schedule_type0' ).click(function(){
						$('.gravityflow-schedule-date-container').hide();
						$('.gravityflow-schedule-delay-container').show();
					});
					$( '#schedule_type1' ).click(function(){
						$('.gravityflow-schedule-date-container').show();
						$('.gravityflow-schedule-delay-container').hide();
					});
				})(jQuery);
			</script>
			<?php


		}

		function settings_tabs( $tabs_field ) {
			printf( '<div id="tabs-%s">', $tabs_field['name'] );
			echo '<ul>';
			foreach ( $tabs_field['tabs'] as $i => $tab ) {
				$id = isset( $tab['id'] ) ? $tab['id'] : $tab['name'];
				printf( '<li id="gaddon-setting-tab-%s">', $id );
				printf( '<a href="#tabs-%d"><span style="display:inline-block;width:10px;margin-right:5px"><i class="fa fa-check-square-o gravityflow-tab-checked" style="display:none;"></i><i class="fa fa-square-o gravityflow-tab-unchecked"></i></span>%s</a>', $i, $tab['label'] );
				echo '</li>';
			}
			echo '</ul>';
			foreach ( $tabs_field['tabs'] as $i => $tab ) {
				printf( '<div id="tabs-%d">', $i );
				foreach ( $tab['fields'] as $field ) {
					$func = array( $this, 'settings_' . $field['type'] );
					if ( is_callable( $func ) ) {
						$id = isset( $field['id'] ) ? $field['id'] : $field['name'];
						$tooltip = '';
						if ( isset( $field['tooltip'] ) ) {
							$tooltip_class = isset( $field['tooltip_class'] ) ? $field['tooltip_class'] : '';
							$tooltip = gform_tooltip( $field['tooltip'], $tooltip_class, true );
						}
						printf( '<div id="gaddon-setting-tab-field-%s" class="gravityflow-tab-field"><div class="gravityflow-tab-field-label">%s %s</div>', $id, $field['label'], $tooltip );
						call_user_func( $func, $field );
						echo '</div>';
					}
				}
				echo '</div>';
			}
			?>
			</div>
			<script>
				(function($) {
					 $( "#tabs-<?php echo $tabs_field['name'] ?>" ).tabs();
				})(jQuery);
			</script>
			<?php
		}

		function settings_visual_editor( $field ) {

			$default_value = rgar( $field, 'value' ) ? rgar( $field, 'value' ) : rgar( $field, 'default_value' );
			$value         = $this->get_setting( $field['name'], $default_value );
			$id            = '_gaddon_setting_' . $field['name'];
			echo "<span class='mt-{$id}'></span>";
			wp_editor( $value, $id, array(
				'autop'        => false,
				'editor_class' => 'merge-tag-support mt-wp_editor mt-manual_position mt-position-right',
			) );
		}

		function settings_routing() {
			echo '<div id="gform_routing_setting" class="gravityflow-routing" data-field_name="_gaddon_setting_routing" data-field_id="routing" ></div>';
			$field['name'] = 'routing';

			$this->settings_hidden( $field );
		}

		function settings_user_routing( $field ) {
			$name = $field['name'];
			$id = isset( $field['id'] ) ?  $field['id'] : 'gform_user_routing_setting_' . $name;

			echo '<div class="gravityflow-user-routing" id="' . $id . '" data-field_name="_gaddon_setting_' . $name . 'user_routing" data-field_id="' . $name . '" ></div>';

			$this->settings_hidden( $field );
		}

		function settings_step_selector( $field ) {
			$form = $this->get_current_form();
			$feed_id = $this->get_current_feed_id();
			$form_id = absint( $form['id'] );
			$steps = $this->get_steps( $form_id );

			$step_choices   = array();
			$step_choices[] = array( 'label' => esc_html__( 'Workflow Complete', 'gravityflow' ), 'value' => 'complete' );
			$step_choices[] = array( 'label' => esc_html__( 'Next step in list', 'gravityflow' ), 'value' => 'next' );
			foreach ( $steps as $i => $step ) {
				$step_id = $step->get_id();
				if ( $feed_id != $step_id ) {
					$step_choices[] = array( 'label' => $step->get_name(), 'value' => $step_id );
				}
			}

			$step_selector_field = array(
				'name'       => $field['name'],
				'label'      => $field['label'],
				'type'       => 'select',
				'default_value' => isset( $field['default_value'] ) ? $field['default_value'] : 'next',
				'horizontal' => true,
				'choices'    => $step_choices,
			);

			$this->settings_select( $step_selector_field );
		}

		function settings_editable_fields( $field ) {
			$form = $this->get_current_form();
			$choices = array();
			if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $form_field ) {
					if ( $form_field->displayOnly ) {
						continue;
					}
					$choices[] = array( 'label' => GFFormsModel::get_label( $form_field ), 'value' => $form_field->id );
				}
			}
			$field['choices'] = $choices;

			$this->settings_select( $field );
		}

		/**
		 * Adds columns to the list of feeds.
		 *
		 * setting name => label
		 *
		 * @return array
		 */
		function feed_list_columns() {
			return array(
				'step_name' => __( 'Step name', 'gravityflow' ),
				'step_type' => esc_html__( 'Step Type', 'gravityflow' ),
				'entry_count' => esc_html__( 'Entries', 'gravityflow' ),
			);
		}

		public function get_column_value_entry_count( $item ){
			$form_id = rgget( 'id' );
			$form_id = absint( $form_id );
			$step = $this->get_step( $item['id'] );
			$step_id = $step->get_id();
			$count = $step->entry_count();
			$url = admin_url( 'admin.php?page=gf_entries&view=entries&id='. $form_id . '&field_id=workflow_step&operator=is&s=' . $step_id );
			$link = sprintf( '<a href="%s">%d</a>', $url, $count );
			return $link;
		}

		public function feed_list_no_item_message() {
			$url = add_query_arg( array( 'fid' => 0 ) );
			return sprintf( __( "You don't have any steps configured. Let's go %screate one%s!", 'gravityflow' ), "<a href='" . esc_url( $url ) . "'>", '</a>' );
		}


		/**
		 * Entry meta data is custom data that's stored and retrieved along with the entry object.
		 * For example, entry meta data may contain the results of a calculation made at the time of the entry submission.
		 *
		 * To add entry meta override the get_entry_meta() function and return an associative array with the following keys:
		 *
		 * label
		 * - (string) The label for the entry meta
		 * is_numeric
		 * - (boolean) Used for sorting
		 * is_default_column
		 * - (boolean) Default columns appear in the entry list by default. Otherwise the user has to edit the columns and select the entry meta from the list.
		 * update_entry_meta_callback
		 * - (string | array) The function that should be called when updating this entry meta value
		 * filter
		 * - (array) An array containing the configuration for the filter used on the results pages, the entry list search and export entries page.
		 *           The array should contain one element: operators. e.g. 'operators' => array('is', 'isnot', '>', '<')
		 *
		 *
		 * @param array $entry_meta An array of entry meta already registered with the gform_entry_meta filter.
		 * @param int $form_id The Form ID
		 *
		 * @return array The filtered entry meta array.
		 */
		function get_entry_meta( $entry_meta, $form_id ) {
			$steps = $this->get_steps( $form_id );

			$step_choices = array();

			foreach ( $steps as $step ) {

				if (  empty ( $step ) || ! $step->is_active() ) {
					continue;
				}
				$status_choices = array();
				$step_id = $step->get_id();
				$step_name = $step->get_name();
				$step_choices[] = array( 'value' => $step_id, 'text' => $step_name );

				$final_status_options = $step->get_final_status_config();
				foreach ( $final_status_options as $final_status_option ) {
					$status_choices[] = array(
						'value' => $final_status_option['status'],
						'text' => $final_status_option['status_label'],
					);
				}

				$entry_meta = array_merge( $entry_meta, $step->get_entry_meta( $entry_meta, $form_id ) );

				$entry_meta[ 'workflow_step_status_' . $step_id ] = array(
					'label'                      => __( 'Status:', 'gravityflow' ) . ' ' . $step_name,
					'is_numeric'                 => false,
					'is_default_column'          => true, // this column will be displayed by default on the entry list
					'filter'                     => array(
						'operators' => array( 'is', 'isnot' ),
						'choices'   => $status_choices,
					)
				);
			}

			if ( ! empty( $steps ) ) {

				$entry_meta['workflow_final_status'] = array(
					'label'                      => 'Final Status',
					'is_numeric'                 => false,
					'update_entry_meta_callback' => array( $this, 'callback_update_entry_meta' ),
					'is_default_column'          => true, // this column will be displayed by default on the entry list
					'filter'                     => array(
						'operators' => array( 'is', 'isnot' ),
						'choices'   => array( array( 'value' => 'complete', 'text' => __( 'Complete', 'gravityflow' ) ) ),
					)
				);

				$entry_meta['workflow_step'] = array(
					'label'                      => 'Workflow Step',
					'is_numeric'                 => false,
					'update_entry_meta_callback' => array( $this, 'callback_update_entry_meta' ),
					'is_default_column'          => true, // this column will be displayed by default on the entry list
					'filter'                     => array(
						'operators' => array( 'is', 'isnot' ),
						'choices'   => $step_choices,
					)
				);

				$entry_meta['workflow_timestamp'] = array(
					'label'                      => 'Timestamp',
					'is_numeric'                 => false,
					'update_entry_meta_callback' => array( $this, 'callback_update_entry_meta_timestamp' ),
					'is_default_column'          => false, // this column will be displayed by default on the entry list
				);

			}


			return $entry_meta;
		}

		/**
		 * The target of update_entry_meta_callback.
		 *
		 * @param string $key The entry meta key
		 * @param array $entry The Entry Object
		 * @param array $form The Form Object
		 *
		 * @return string|void
		 */
		function callback_update_entry_meta( $key, $entry, $form ) {
			if ( isset( $entry[ $key ] ) && $entry[ $key ] !== false ) {
				// value could be false if temp entry was created before processing (e.g. for product fields)
				return $entry[ $key ];
			}

			return $key == 'workflow_step' ? 0 : 'pending';
		}

		/**
		 * The target of update_entry_meta_callback.
		 *
		 * @param string $key The entry meta key
		 * @param array $entry The Entry Object
		 * @param array $form The Form Object
		 *
		 * @return string|void
		 */
		function callback_update_entry_meta_timestamp( $key, $entry, $form ) {
			return ! isset( $entry['workflow_timestamp'] ) ? strtotime( $entry['date_created'] ) : time();
		}


		function workflow_entry_detail_status_box( $form, $entry ) {

			if ( ! isset( $entry['workflow_final_status'] ) ) {
				return;
			}

			$current_step = $this->get_current_step( $form, $entry );

			$lead = $entry;
			?>
			<div class="postbox">
				<h3 class="hndle" style="cursor:default;">
					<span><?php esc_html_e( 'Workflow', 'gravityflow' ); ?></span>
				</h3>

				<div id="submitcomment" class="submitbox">
					<div id="minor-publishing" style="padding:10px;">
						<?php esc_html_e( 'Entry Id', 'gravityflow' ); ?>: <?php echo absint( $lead['id'] ) ?><br /><br />
						<?php esc_html_e( 'Submitted', 'gravityflow' ); ?>: <?php echo esc_html( GFCommon::format_date( $lead['date_created'], true, 'Y/m/d' ) ) ?>
						<?php
						$last_updated = date( 'Y-m-d H:i:s', $lead['workflow_timestamp'] );
						if ( $lead['date_created'] != $last_updated ) {
							echo '<br /><br />';
							esc_html_e( 'Last updated', 'gravityflow' ); ?>: <?php echo esc_html( GFCommon::format_date( $last_updated, true, 'Y/m/d' ) );
						}
						?>
						<br /><br />
						<?php
						if ( ! empty( $lead['created_by'] ) && $usermeta = get_userdata( $lead['created_by'] ) ) {
							?>
							<?php _e( 'Submitted by', 'gravityflow' ); ?>:
							<?php echo esc_html( $usermeta->display_name ) ?>
							<br /><br />
						<?php
						}

						$workflow_status = gform_get_meta( $entry['id'], 'workflow_final_status' );

						//$workflow_status = $current_step ? $current_step->get_status_label( $workflow_status ) : esc_html__( 'Complete', 'gravityflow' );

						printf( '%s: %s', esc_html__( 'Status', 'gravityflow' ), $workflow_status );



						if ( $current_step !== false ) {
							?>
							<hr style="margin-top:10px;"/>
							<?php

							$current_step->workflow_detail_status_box( $form );
						}

						?>

					</div>

				</div>


			</div>

			<?php if ( GFAPI::current_user_can_any( 'gravityflow_workflow_detail_admin_actions' ) ) : ?>

				<div class="postbox">
					<h3 class="hndle" style="cursor:default;">
						<span><?php esc_html_e( 'Admin', 'gravityflow' ); ?></span>
					</h3>

					<div id="submitcomment" class="submitbox">
						<div id="minor-publishing" style="padding:10px;">
							<?php wp_nonce_field( 'gravityflow_admin_action', '_gravityflow_admin_action_nonce' ); ?>
							<select id="gravityflow-admin-action" name="gravityflow_admin_action">

								<option value=""><?php esc_html_e( 'Select an action', 'gravityflow' ) ?></option>
								<?php if ( $current_step ) : ?>
									<option value="cancel_workflow"><?php esc_html_e( 'Cancel Workflow', 'gravityflow' ); ?></option>

									<option	value="restart_step"><?php esc_html_e( 'Restart this step', 'gravityflow' ); ?></option>
								<?php endif; ?>
								<option value="restart_workflow"><?php esc_html_e( 'Restart Workflow', 'gravityflow' ) ?></option>
								<?php
								$steps = $this->get_steps( $form['id'] );
								if ( $current_step && count( $steps ) > 1 ) :
								?>
								<optgroup label="<?php esc_html_e( 'Send to step:', 'gravityflow' ); ?>" >
									<?php
									foreach ( $steps as $step ) {
										$step_id = $step->get_id();
										if ( ! $current_step || ( $current_step && $current_step->get_id() != $step_id ) ) {
											printf( '<option value="send_to_step|%d">%s</option>', $step->get_id(), $step->get_name() );
										}
									}
									?>
								</optgroup>
								<?php endif; ?>
							</select>
							<input type="submit" class="button " name="_gravityflow_admin_action" value="<?php esc_html_e( 'Apply', 'gravityflow' ) ?>" />

						</div>
					</div>
				</div>

			<?php endif; ?>


		<?php
		}

		function entry_detail_status_box( $form, $entry ) {

			if ( ! isset( $entry['workflow_final_status'] ) ) {
				return;
			}

			$current_step = $this->get_current_step( $form, $entry );

			?>
			<div class="postbox">
				<h3><?php esc_html_e( 'Workflow', 'gravityflow' ) ?></h3>
				<?php
				if ( $current_step == false ) {
					?>
					<h4 style="padding:10px;"><?php esc_html_e( 'Workflow complete', 'gravityflow' ); ?></h4>

					<?php

				} else {

					$current_step->entry_detail_status_box( $form );
				}

				?>
			</div>
		<?php
		}

		public function trigger_workflow_complete( $form, $entry ) {


			// Integration with the User Registration Add-On
			if ( class_exists( 'GFUser' ) ) {
				GFUser::gf_create_user( $entry, $form );
			}

			// Integration with the Zapier Add-On
			if ( class_exists( 'GFZapier' ) ) {
				GFZapier::send_form_data_to_zapier( $entry, $form );
			}


			GFAPI::send_notifications( $form, $entry, 'workflow_complete' );

		}


		public function get_current_step( $form, $entry ) {

			if ( $entry['workflow_step'] === 0 ) {
				$step = $this->get_first_step( $form['id'], $entry );
			} else {
				$step = $this->get_step( $entry['workflow_step'], $entry );
			}

			return $step;
		}

		/**
		 * @param Gravity_Flow_Step $step
		 * @param $entry
		 * @param $form
		 *
		 * @return bool|Gravity_Flow_Step
		 */
		public function get_next_step( $step, $entry, $form ){
			//$complete = true;
			$keep_looking = true;
			$form_id = absint( $form['id'] );
			$steps = $this->get_steps( $form_id, $entry );
			while ( $keep_looking ) {

				$next_step_id = $step->get_next_step_id();

				if ( $next_step_id == 'complete' ) {
					return false;
				}

				if ( $next_step_id == 'next' ) {
					$step = $this->get_next_step_in_list( $form, $step, $entry, $steps );
					$keep_looking = false;
				} else {
					$step = $this->get_step( $next_step_id, $entry );
					if ( ! $step->is_active() || ! $step->is_condition_met( $form ) ) {
						$step = $this->get_next_step_in_list( $form, $step, $entry, $steps );
					} else {
						$keep_looking = false;
					}
				}
			}
			return $step;
		}

		public function get_step( $step_id, $entry = null ){

			$feed = $this->get_feed( $step_id );
			if ( ! $feed ) {
				return false;
			}

			$step = Gravity_Flow_Steps::create( $feed, $entry );

			return $step;
		}

		/**
		 * @param int $form_id
		 * @param Gravity_Flow_Step $current_step
		 * @param array $steps
		 *
		 * @return bool|Gravity_Flow_Step
		 */
		public function get_next_step_in_list( $form, $current_step, $entry, $steps = array() ){
			$form_id = absint( $form['id'] );

			if ( empty( $steps ) ) {
				$steps = $this->get_steps( $form_id, $entry );
			}
			$current_step_id = $current_step->get_id();
			$next_step = false;
			foreach ( $steps as $step ) {
				if ( $next_step ) {
					if ( $step->is_active() && $step->is_condition_met( $form ) ) {
						return $step;
					}
				}

				if ( $next_step == false && $current_step_id == $step->get_id() ) {
					$next_step = true;
				}
			}
			return false;
		}

		public function get_app_menu_items(){
			$menu_items = array();

			$inbox_item = array(
				'name' => 'gravityflow-inbox',
				'label' => esc_html__( 'Inbox', 'gravityflow' ),
				'permission' => 'gravityflow_inbox',
				'callback' => array( $this, 'inbox' )
			);
			$menu_items[] = $inbox_item;

			$form_ids = $this->get_published_form_ids();

			if ( ! empty( $form_ids ) ){
				$menu_item = array(
					'name' => 'gravityflow-submit',
					'label' => esc_html__( 'Submit', 'gravityflow' ),
					'permission' => 'gravityflow_submit',
					'callback' => array( $this, 'submit' )
				);
				$menu_items[] = $menu_item;
			}

			$status_item = array(
				'name' => 'gravityflow-status',
				'label' => esc_html__( 'Status', 'gravityflow' ),
				'permission' => 'gravityflow_status',
				'callback' => array( $this, 'status' )
			);
			$menu_items[] = $status_item;

			$help_item = array(
				'name' => 'gravityflow-help',
				'label' => esc_html__( 'Help', 'gravityflow' ),
				'permission' => 'gform_full_access',
				'callback' => array( $this, 'help' )
			);
			$menu_items[] = $help_item;


			$support_item = array(
				'name' => 'gravityflow-support',
				'label' => esc_html__( 'Beta Support', 'gravityflow' ),
				'permission' => 'gform_full_access',
				'callback' => array( $this, 'support' )
			);
			$menu_items[] = $support_item;

			$menu_items = apply_filters( 'gravityflow_menu_items', $menu_items );

			return $menu_items;
		}

		public function get_app_settings_tabs() {

			//build left side options, always have app Settings first and Uninstall last, put add-ons in the middle

			$setting_tabs = array(
				array(
					'name' => 'settings',
					'label' => __( 'Settings', 'gravityflow' ),
					'callback' => array( $this, 'app_settings_tab' )
				),
			);

			$setting_tabs = apply_filters( 'gform_addon_app_settings_menu_' . $this->_slug, $setting_tabs );

			if ( $this->current_user_can_any( $this->_capabilities_uninstall ) ) {
				$setting_tabs[] = array( 'name' => 'uninstall', 'label' => __( 'Uninstall', 'gravityflow' ), 'callback' => array( $this, 'app_settings_uninstall_tab' ) );
			}

			ksort( $setting_tabs, SORT_NUMERIC );

			return $setting_tabs;
		}

		public function get_app_menu_icon(){
			return 'dashicons-networking';
		}

		public function get_published_form_ids(){
			$settings = $this->get_app_settings();

			if ( $settings === false ) {
				return array();
			}

			$form_ids = array();

			foreach ( $settings as $key => $setting ) {
				if ( strstr( $key, 'publish_form_' ) && $setting == 1 ) {
					$form_id = str_replace( 'publish_form_', '', $key );
					$form_ids[] = absint( $form_id );
				}
			}
			return $form_ids;
		}

		public function load_screen_options(){

			$screen = get_current_screen();

			if ( ! is_object( $screen ) || $screen->id != 'workflow_page_gravityflow-status' ) {
				return;
			}

			if ( $this->is_status_page() ) {
				$args = array(
					'label'   => esc_html( 'Entries per page', 'gravityflow' ),
					'default' => 20,
					'option'  => 'entries_per_page',
				);
				add_screen_option( 'per_page', $args );
			}

		}

		public function is_status_page() {
			return rgget( 'page' ) == 'gravityflow-status';
		}

		public function app_settings_fields() {

			$forms = GFAPI::get_forms();
			$choices = array();
			foreach ( $forms as $form ) {
				$form_id = absint( $form['id'] );
				$feeds = $this->get_feeds( $form_id );
				if ( ! empty( $feeds ) ) {
					$choices[] = array(
						'label'         => esc_html( $form['title'] ),
						'name'          => 'publish_form_' . absint( $form['id'] ),
					);
				}
			}

			if ( ! empty( $choices ) ) {
				$published_forms_fields = array(
					array(
						'name'          => 'form_ids',
						'label'         => esc_html__( 'Published', 'gravityflow' ),
						'type'          => 'checkbox',
						'choices'       => $choices,
					),
				);
			} else {
				$published_forms_fields = array(
					array(
						'name'          => 'no_workflows',
						'label'         => '',
						'type'          => 'html',
						'html'          => esc_html__( 'No workflow steps have been added to any forms yet.', 'gravityflow' )
					),
				);
			}

			$settings = array();

			if ( ! is_multisite() || ( is_multisite() && is_main_site() && ! defined( 'GRAVITY_FLOW_LICENSE_KEY' ) ) ) {
				$settings[] = array(
					'title'  => 'Settings',
					'fields' => array(
						array(
							'name'          => 'license_key',
							'label'         => esc_html__( 'License Key', 'gravityflow' ),
							'type'          => 'text',
							'validation_callback' => array( $this, 'license_validation' ),
							'feedback_callback'    => array( $this, 'license_feedback' ),
							'error_message' => __( 'Invalid license', 'gravityflow' ),
							'class' => 'large',
							'default_value' => '',
						),
						array(
							'name'          => 'background_updates',
							'label'         => esc_html__( 'Background Updates', 'gravityflow' ),
							'tooltip' => __( 'Set this to ON to allow Gravity Forms to download and install bug fixes and security updates automatically in the background. Requires a valid license key.' , 'gravityflow' ),
							'type'          => 'radio',
							'horizontal' => true,
							'default_value' => false,
							'choices' => array(
								array( 'label' => __( 'On', 'gravityflow' ), 'value' => true ),
								array( 'label' => __( 'Off', 'gravityflow' ), 'value' => false ),
							)
						),
					),
				);
			}

			$settings[] = array(
				'title'       => esc_html__( 'Published Workflow Forms', 'gravityflow' ),
				'description' => esc_html__( 'Select the forms you wish to publish on the Submit page.', 'gravityflow' ),
				'fields'      => $published_forms_fields,
			);
			$settings[] = array(
				'id'     => 'save_button',
				'fields' => array(
					array(
						'id'       => 'save_button',
						'name'     => 'save_button',
						'type'     => 'save',
						'value'    => __( 'Update Settings', 'gravityflow' ),
						'messages' => array(
							'success' => __( 'Settings updated successfully', 'gravityflow' ),
							'error'   => __( 'There was an error while saving the settings', 'gravityflow' )
						)
					),
				)
			);

			return $settings;

		}

		public function license_feedback( $value, $field ) {

			if ( empty( $value ) ) {
				return null;
			}

			$license_data = $this->check_license( $value );

			$valid = null;
			if ( empty( $license_data ) || $license_data->license == 'invalid' ) {
				$valid = false;
			} elseif ( $license_data->license == 'valid' ) {
				$valid = true;
			}

			return $valid;

		}

		public function check_license( $value ){
			$api_params = array(
				'edd_action' => 'check_license',
				'license'    => $value,
				'item_name'  => urlencode( GRAVITY_FLOW_EDD_ITEM_NAME ),
				'url'       => home_url(),
			);
			// Send the remote request
			$response = wp_remote_post( GRAVITY_FLOW_EDD_STORE_URL, array( 'timeout' => 10, 'sslverify' => false, 'body' => $api_params  ) );
			return json_decode( wp_remote_retrieve_body( $response ) );

		}

		public function license_validation( $field, $field_setting ){
			$old_license = $this->get_app_setting( 'license_key' );

			if ( $old_license && $field_setting != $old_license  ) {
				// deactivate the old site
				$api_params = array(
					'edd_action' => 'deactivate_license',
					'license'    => $old_license,
					'item_name'  => urlencode( GRAVITY_FLOW_EDD_ITEM_NAME ),
					'url'        => home_url()
				);
				// Send the remote request
				$response = wp_remote_post( GRAVITY_FLOW_EDD_STORE_URL, array( 'timeout' => 10, 'sslverify' => false, 'body' => $api_params  ) );
			}


			if ( empty( $field_setting ) ) {
				return;
			}

			$this->activate_license( $field_setting );

		}

		public function activate_license( $license_key ){
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license_key,
				'item_name'  => urlencode( GRAVITY_FLOW_EDD_ITEM_NAME ),
				'url'        => home_url()
			);

			$response = wp_remote_post( GRAVITY_FLOW_EDD_STORE_URL, array( 'timeout' => 10, 'sslverify' => false, 'body' => $api_params  ) );
			return json_decode( wp_remote_retrieve_body( $response ) );
		}

		public function settings_html( $field ){
			echo $field['html'];
		}

		public function submit(){

			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}

			$this->submit_page( true );
		}

		public function submit_page( $admin_ui ){
			?>
			<div class="wrap gf_entry_wrap gravityflow_workflow_wrap gravityflow_workflow_submit">
				<?php if ( $admin_ui ) :	?>
				<h2 class="gf_admin_page_title">
					<span><?php esc_html_e( 'Submit a Workflow Form', 'gravityflow' ); ?></span>

				</h2>
				<?php
					$this->toolbar();
				endif;
				require_once( $this->get_base_path() . '/includes/pages/class-submit.php' );
				if ( isset( $_GET['id'] ) ) {
					$form_id = absint( $_GET['id'] );
					Gravity_Flow_Submit::form( $form_id );
				} else {
					$settings = $this->get_app_settings();
					$settings = $settings ? $settings : array();
					$form_ids = array();
					foreach ( $settings as $key => $setting ) {
						if ( $setting == 1 ) {
							$form_id = str_replace( 'publish_form_', '', $key );
							$form_ids[] = absint( $form_id );
						}
					}

					Gravity_Flow_Submit::list_page( $form_ids , $admin_ui );
				}

				?>
			</div>
		<?php
		}

		public function maybe_display_installation_wizard(){

			if ( ! current_user_can( 'gform_full_access' ) ) {
				return false;
			}

			$pending_installation = get_option( 'gravityflow_pending_installation' ) || isset( $_GET['gravityflow_installation_wizard'] );

			if ( $pending_installation ) {
				require_once( $this->get_base_path() . '/includes/wizard/class-installation-wizard.php' );
				$wizard = new Gravity_Flow_Installation_Wizard;
				$result = $wizard->display();
				return $result;
			}

			return false;
		}


		/**
		 * Displays the Inbox UI
		 */
		public function inbox() {

			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}

			$this->inbox_page( true );
		}

		public function inbox_page( $admin_ui = true ) {

			if ( rgget( 'view' ) == 'entry' ) {

				$entry_id = absint( rgget( 'lid' ) );

				$entry = GFAPI::get_entry( $entry_id );

				if ( is_wp_error( $entry ) ) {
					esc_html_e( 'Oops! We could not locate your entry.', 'gravityflow' );
					return;
				}

				$form_id = $entry['form_id'];
				$form = GFAPI::get_form( $form_id );

				require_once( $this->get_base_path() . '/includes/pages/class-entry-detail.php' );

				$step = $this->get_current_step( $form, $entry );

				$check_view_entry_permissions = true;

				$feedback = $this->maybe_process_admin_action( $form, $entry );

				if ( empty( $feedback ) && $step ) {

					$feedback = $step->maybe_process_status_update( $form, $entry );

					if ( $feedback ) {
						$this->process_workflow( $form, $entry_id );
					}
				}

				if ( $feedback ) {
					GFCache::flush();
					$check_view_entry_permissions = false;
					$entry = GFAPI::get_entry( $entry_id ); // refresh entry

					?>
					<div class="updated notice notice-success is-dismissible" style="padding:6px;">
						<?php echo esc_html( $feedback ); ?>
					</div>
					<?php

					$step = $this->get_current_step( $form, $entry );
				}


				Gravity_Flow_Entry_Detail::entry_detail( $form, $entry, $allow_display_empty_fields = true, $step, $check_view_entry_permissions, $admin_ui );
				return;
			} else {

				?>
				<div class="wrap gf_entry_wrap gravityflow_workflow_wrap gravityflow_workflow_detail">
					<?php if ( $admin_ui ) :	?>
						<h2 class="gf_admin_page_title">
							<span><?php esc_html_e( 'Workflow Inbox', 'gravityflow' ); ?></span>
						</h2>
					<?php endif; ?>
					<?php
					if ( $admin_ui ) {
						$this->toolbar();
					}

					require_once( $this->get_base_path() . '/includes/pages/class-inbox.php' );
					Gravity_Flow_Inbox::display( $admin_ui );

					?>
				</div>
			<?php
			}
		}



		public function status(){

			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}

			$this->status_page();
		}

		public function status_page( $is_admin_ui = true ) {
			?>
			<div class="wrap gf_entry_wrap gravityflow_workflow_wrap gravityflow_workflow_inbox">

				<?php if ( $is_admin_ui ) : ?>
					<h2 class="gf_admin_page_title">
						<span><?php esc_html_e( 'Workflow Status', 'gravityflow' ); ?></span>

					</h2>

					<?php $this->toolbar(); ?>
				<?php
				endif;

				require_once( $this->get_base_path() . '/includes/pages/class-status.php' );
				Gravity_Flow_Status::display( $is_admin_ui );
				?>
			</div>
		<?php
		}

		public function toolbar(){
			?>

			<div id="gf_form_toolbar">
				<ul id="gf_form_toolbar_links">

					<?php

					$menu_items = self::get_toolbar_menu_items();

					echo GFForms::format_toolbar_menu_items( $menu_items );

					?>
				</ul>
			</div>
		<?php
		}


		public function get_toolbar_menu_items() {
			$menu_items = array();

			$menu_items['inbox'] = array(
				'label'        => __( 'Inbox', 'gravityflow' ),
				'icon'         => '<i class="fa fa-inbox fa-lg"></i>',
				'title'        => __( 'Your inbox of pending tasks', 'gravityflow' ),
				'url'          => '?page=gravityflow-inbox',
				'menu_class'   => 'gf_form_toolbar_editor',
				'link_class'   => '',
				'capabilities' => 'gravityflow_inbox',
				'priority'     => 1000,
			);

			$form_ids = $this->get_published_form_ids();

			if ( ! empty( $form_ids ) ) {
				$menu_items['submit'] = array(
					'label'        => __( 'Submit', 'gravityflow' ),
					'icon'         => '<i class="fa fa-pencil-square-o fa-lg"></i>',
					'title'        => __( 'Submit a Workflow', 'gravityflow' ),
					'url'          => '?page=gravityflow-submit',
					'menu_class'   => 'gf_form_toolbar_editor',
					'link_class'   => '',
					'capabilities' => 'gravityflow_submit',
					'priority'     => 1000,
				);
			}



			$menu_items['status'] = array(
				'label'          => __( 'Status', 'gravityflow' ),
				'icon'           => '<i class="fa fa-tachometer fa-lg"></i>',
				'title'          => __( 'Your workflows', 'gravityflow' ),
				'url'            => '?page=gravityflow-status',
				'menu_class'     => 'gf_form_toolbar_settings',
				'link_class'     => '',
				'capabilities'   => 'gravityflow_status',
				'priority'       => 900,
			);

			return $menu_items;
		}

		function maybe_process_admin_action( $form, $entry ) {
			$feedback = false;
			if ( isset( $_POST['_gravityflow_admin_action'] ) && check_admin_referer( 'gravityflow_admin_action', '_gravityflow_admin_action_nonce' ) && GFAPI::current_user_can_any( 'gravityflow_workflow_detail_admin_actions' ) ) {
				$entry_id = absint( rgar( $entry, 'id' ) );
				$current_step = $this->get_current_step( $form, $entry );
				$admin_action = rgpost( 'gravityflow_admin_action' );
				switch ( $admin_action ) {
					case 'cancel_workflow' :
						if ( $current_step ) {
							$current_step->end();
						}
						gform_update_meta( $entry_id, 'workflow_final_status', 'cancelled' );
						gform_delete_meta( $entry_id, 'workflow_step' );
						$feedback = esc_html__( 'Workflow cancelled.',  'gravityflow' );
						$this->add_note( $entry_id, $feedback );

						break;
					case 'restart_step':
						if ( $current_step ) {
							$current_step->start();
							$feedback = esc_html__( 'Workflow Step restarted.',  'gravityflow' );
							$this->add_note( $entry_id, $feedback );
						}

					break;
					case 'restart_workflow':
						if ( $current_step ) {
							$current_step->end();
						}
						$feedback = esc_html__( 'Workflow restarted.',  'gravityflow' );
						$this->add_note( $entry_id, $feedback );
						gform_update_meta( $entry_id, 'workflow_final_status', 'pending' );
						gform_update_meta( $entry_id, 'workflow_step', false );
						$this->process_workflow( $form, $entry_id );

						break;
				}
				list( $admin_action, $action_id ) = rgexplode( '|', $admin_action, 2 );
				if ( $admin_action == 'send_to_step' ) {
					$step_id = $action_id;
					if ( $current_step ) {
						$current_step->end();
					}
					$new_step = $this->get_step( $step_id, $entry );
					$feedback = sprintf( esc_html__( 'Sent to step: %s',  'gravityflow' ), $new_step->get_name() );
					$this->add_note( $entry_id, $feedback );
					gform_update_meta( $entry_id, 'workflow_final_status', 'pending' );
					$new_step->start();
					$this->process_workflow( $form, $entry_id );
				}
			}
			return $feedback;
		}

		function remove_entrydetail_update_button( $button ) {
			return 'This entry has been approved and can no longer be edited';
		}

		function add_notification_event( $events ) {
			$events['workflow_approval'] = 'Workflow: approved or rejected';
			$events['workflow_user_input'] = 'Workflow: user input';
			$events['workflow_notification_step'] = 'Workflow: notification step';
			$events['workflow_complete'] = 'Workflow: complete';

			return $events;
		}

		function after_submission( $entry, $form ) {
			if ( isset( $entry['workflow_step'] ) ) {
				$entry_id = absint( $entry['id'] );
				$this->process_workflow( $form, $entry_id );
			}
		}

		public function filter_after_update_entry( $form, $entry_id ) {
			$this->process_workflow( $form, $entry_id );

		}

		public function process_workflow( $form, $entry_id ) {

			$entry = GFAPI::get_entry( $entry_id );
			if ( isset( $entry['workflow_step'] ) ) {
				$step_id = $entry['workflow_step'];

				if ( empty( $step_id ) && ( empty( $entry['workflow_final_status'] ) || $entry['workflow_final_status'] == 'pending') ) {
					// Starting workflow
					$form_id = absint( $form['id'] );
					$step = $this->get_first_step( $form_id, $entry );
					$step->start();
				} else {
					$step = $this->get_step( $step_id, $entry );
				}

				$step_complete = false;
				if ( $step ) {
					$step_complete = $step->end_if_complete();
					$step_id = $step->get_id();
				}

				while ( $step_complete && $step ) {
					$step = $this->get_next_step( $step, $entry, $form );
					$step_complete = false;
					if ( $step ) {
						$step->start();
						$step_id = $step->get_id();
						$step_complete = $step->end_if_complete();
					}
					$entry['workflow_step'] = $step_id;
				}

				if ( $step == false ) {
					gform_delete_meta( $entry_id, 'workflow_step' );
					$step_status = gform_get_meta( $entry_id, 'workflow_step_status_' . $step_id );
					gform_update_meta( $entry_id, 'workflow_final_status', $step_status );
					gform_delete_meta( $entry_id, 'workflow_step_status' );
					do_action( 'gravityflow_workflow_complete', $entry_id, $form, $step_status );
					GFAPI::send_notifications( $form, $entry, 'workflow_complete' );
				} else {
					$step_id = $step->get_id();
					gform_update_meta( $entry_id, 'workflow_step', $step_id );
				}
			}
		}

		function get_first_step( $form_id, $entry ){
			$steps = $this->get_steps( $form_id, $entry );
			foreach ( $steps as $step ) {
				if ( $step->is_active() ) {
					return $step;
				}
			}
			return false;
		}

		public function shortcode(  $atts, $content = null ) {

			if ( ! is_user_logged_in() ) {
				return;
			}

			$a = shortcode_atts( array(
				'page' => 'inbox',
			), $atts );


			switch ( $a['page'] ) {
				case 'inbox' :
					wp_enqueue_script( 'gravityflow_entry_detail' );
					wp_enqueue_script( 'gravityflow_status_list' );

					ob_start();
					$this->inbox_page( $display_title = false, $display_toolbar = false );
					return ob_get_clean();
					break;
				case 'submit' :
					ob_start();
					$this->submit_page( false );
					return ob_get_clean();
					break;
				case 'status' :
					wp_enqueue_script( 'gravityflow_entry_detail' );
					wp_enqueue_script( 'gravityflow_status_list' );

					if ( rgget( 'view' ) ) {
						ob_start();
						$this->inbox_page( $display_title = false, $display_toolbar = false );
						return ob_get_clean();
					} else {
						if( ! class_exists('WP_Screen') ) {
							require_once( ABSPATH . 'wp-admin/includes/screen.php' );
						}
						require_once( ABSPATH .'wp-admin/includes/template.php');
						ob_start();
						$this->status_page( false );
						return ob_get_clean();
					}

					break;
			}

		}


		/**
		 * Checks if a particular user has a role.
		 * Returns true if a match was found.
		 *
		 * @param string $role Role name.
		 * @param int $user_id (Optional) The ID of a user. Defaults to the current user.
		 * @return bool
		 */
		function check_user_role( $role, $user_id = null ) {

			if ( is_numeric( $user_id ) ) {
				$user = get_userdata( $user_id );
			} else {
				$user = wp_get_current_user();
			}

			if ( empty( $user ) ) {
				return false;
			}

			return in_array( $role, (array) $user->roles );
		}

		function get_user_roles( $user_id = null ) {

			if ( is_numeric( $user_id ) ) {
				$user = get_userdata( $user_id );
			} else {
				$user = wp_get_current_user();
			}

			if ( empty( $user ) ) {
				return false;
			}

			return (array) $user->roles;
		}

		public function support(){
			require_once( $this->get_base_path() . '/includes/pages/class-support.php' );
			Gravity_Flow_Support::display();
		}

		public function app_tab_page(){
			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}
			parent::app_tab_page();
		}

		public function help(){
			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}

			require_once( $this->get_base_path() . '/includes/pages/class-help.php' );
			Gravity_Flow_Help::display();
		}

		public function get_app_settings(){
			return parent::get_app_settings();
		}

		public function update_app_settings( $settings ){
			parent::update_app_settings( $settings );
		}


		public function maybe_auto_update( $update, $item ) {
			//$this->log_debug( __METHOD__ . '() - $item.' . print_r( $item, true ) );
			if ( isset($item->slug) && $item->slug == 'gravityflow' ) {

				$this->log_debug( __METHOD__ . '() - Starting auto-update for gravityflow.' );

				$auto_update_disabled = self::is_auto_update_disabled();
				$this->log_debug( __METHOD__ . '() - $auto_update_disabled: ' . var_export( $auto_update_disabled, true ) );

				if ( $auto_update_disabled || version_compare( $this->_version, $item->new_version, '=>' ) ) {
					$this->log_debug( __METHOD__ . '() - Aborting update.' );
					return false;
				}

				$current_major = implode( '.', array_slice( preg_split( '/[.-]/', $this->_version ), 0, 1 ) );
				$new_major     = implode( '.', array_slice( preg_split( '/[.-]/', $item->new_version ), 0, 1 ) );

				$current_branch = implode( '.', array_slice( preg_split( '/[.-]/', $this->_version ), 0, 2 ) );
				$new_branch     = implode( '.', array_slice( preg_split( '/[.-]/', $item->new_version ), 0, 2 ) );

				if ( $current_major == $new_major && $current_branch == $new_branch ) {
					$this->log_debug( __METHOD__ . '() - OK to update.' );
					return true;
				}

				$this->log_debug( __METHOD__ . '() - Skipping - not current branch.' );
			}

			return $update;
		}

		public function is_auto_update_disabled(){

			// Currently WordPress won't ask Gravity Flow to update if background updates are disabled.
			// Let's double check anyway.

			// WordPress background updates are disabled if you don't want file changes.
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ){
				return true;
			}

			if ( defined( 'WP_INSTALLING' ) ){
				return true;
			}

			$wp_updates_disabled = defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED;

			$wp_updates_disabled = apply_filters( 'automatic_updater_disabled', $wp_updates_disabled );

			if ( $wp_updates_disabled ) {
				$this->log_debug( __METHOD__ . '() - Background updates are disabled in WordPress.' );
				return true;
			}

			// Now check Gravity Flow Background Update Settings

			$enabled = $this->get_app_setting( 'background_updates' );
			$this->log_debug( __METHOD__ . ' - $enabled: ' . var_export( $enabled, true ) );

			$disabled = apply_filters( 'gravityflow_disable_auto_update', ! $enabled );
			$this->log_debug( __METHOD__ . '() - $disabled: ' . var_export( $disabled, true ) );

			if ( ! $disabled ) {
				$disabled = defined( 'GRAVITYFLOW_DISABLE_AUTO_UPDATE' ) && GRAVITYFLOW_DISABLE_AUTO_UPDATE;
				$this->log_debug( __METHOD__ . '() - GRAVITYFLOW_DISABLE_AUTO_UPDATE: ' . var_export( $disabled, true ) );
			}

			return $disabled;
		}

		public function uninstall(){

			require_once( $this->get_base_path() . '/includes/wizard/class-installation-wizard.php' );
			$wizard = new Gravity_Flow_Installation_Wizard;
			$wizard->flush_values();

			wp_clear_scheduled_hook( 'gravityflow_cron' );

			parent::uninstall();
		}

		public function add_note( $entry_id, $note, $user_id = false, $user_name = false ){
			global $current_user;
			if ( $user_id === false ) {
				$user_id = $current_user->ID;
			}

			if ( $user_name === false ) {
				global $current_user;
				$user_name = $current_user->display_name;
			}

			GFFormsModel::add_note( $entry_id, $user_id, $user_name, $note, 'gravityflow' );
		}

		public function filter_gform_export_form( $form ) {

			$feeds = $this->get_feeds( $form['id'] );

			if ( ! isset( $form['feeds'] ) ) {
				$form['feeds'] = array();
			}

			$form['feeds']['gravityflow'] = $feeds;
			return $form;
		}

		public function maybe_process_feed( $entry, $form ) {

			$form_id = absint( $form['id'] );
			$steps = $this->get_steps( $form_id );

			foreach ( $steps as $step ) {

				if ( ! $step instanceof Gravity_Flow_Step_Feed_Add_On ) {
					continue;
				}
				$add_on_feeds = $step->get_feeds();

				foreach ( $add_on_feeds as $feed ) {
					$setting_key = 'feed_' . $feed['id'];
					if ( $step->{$setting_key} ) {
						$step->intercept_submission();
						continue 2;
					}
				}
			}

			return parent::maybe_process_feed( $entry, $form );
		}

		public function get_feed_add_on_classes(){
			$add_on_classes = GFAddOn::get_registered_addons();

			$feed_add_on_classes = array();
			foreach ( $add_on_classes as $add_on_class ) {
				if ( $add_on_class == 'Gravity_Flow' ) {
					continue;
				}
				if ( is_subclass_of( $add_on_class, 'GFFeedAddOn' ) && ! is_subclass_of( $add_on_class, 'GFPaymentAddOn' ) ) {
					$feed_add_on_classes[] = $add_on_class;
				}
			}
			return $feed_add_on_classes;
		}

		public function get_supported_feed_add_ons(){
			$add_ons = array(
				array(  'class' => 'GFMailChimp', 'name' => __( 'MailChimp', 'gravityflow' ), 'slug' => 'gravityformsmailchimp' ),
				array(  'class' => 'GFEmma', 'name' => __( 'Emma', 'gravityflow' ), 'slug' => 'gravityformsemma' ),
				array(  'class' => 'GFZapierData', 'name' => __( 'Zapier', 'gravityflow' ), 'slug' => 'gravityformszapier' ),
				array(  'class' => 'GFUserData', 'name' => __( 'User Registration', 'gravityflow' ), 'slug' => 'gravityformsuserregistration' ),
			);

			$installed = array();
			foreach ( $add_ons as $add_on ) {
				if ( class_exists( $add_on['class'] ) ) {
					$installed[] = $add_on;
				}
			}

			return $installed;
		}

		public function field_settings( $position, $form_id ) {

			if ( $position == 20 ) { // After Description setting
				?>

				<li class="gravityflow_setting_assignees field_setting">
					<?php esc_html_e( 'Assignees', 'gravityflow' ); ?><br />
					<div>
						<input type="checkbox" id="gravityflow-assignee-field-show-users"
						       onclick="var value = jQuery(this).is(':checked'); SetFieldProperty('gravityflowAssigneeFieldShowUsers', value);" />
						<label for="gravityflow-assignee-field-show-users" class="inline">
							<?php esc_html_e( 'Show Users', 'gravityflow' ); ?>
							<?php gform_tooltip( 'gravityflow_assignee_field_show_users' ) ?>
						</label>
					</div>
					<div>
						<input type="checkbox" id="gravityflow-assignee-field-show-roles"
						       onclick="var value = jQuery(this).is(':checked'); SetFieldProperty('gravityflowAssigneeFieldShowRoles', value);" />
						<label for="gravityflow-assignee-field-show-roles" class="inline">
							<?php esc_html_e( 'Show Roles', 'gravityflow' ); ?>
							<?php gform_tooltip( 'gravityflow_assignee_field_show_roles' ) ?>
						</label>
					</div>
					<div>
						<input type="checkbox" id="gravityflow-assignee-field-show-fields"
						       onclick="var value = jQuery(this).is(':checked'); SetFieldProperty('gravityflowAssigneeFieldShowFields', value);" />
						<label for="gravityflow-assignee-field-show-fields" class="inline">
							<?php esc_html_e( 'Show Fields', 'gravityflow' ); ?>
							<?php gform_tooltip( 'gravityflow_assignee_field_show_fields' ) ?>
						</label>
					</div>

				</li>

			<?php }
		}

		public function action_admin_enqueue_scripts(){
			$this->maybe_enqueue_form_scripts();
		}


		public function maybe_enqueue_form_scripts(){
			if ( $this->is_workflow_detail_page() ) {
				$this->enqueue_form_scripts();
			}
		}

		public function enqueue_form_scripts(){
			$form = $this->get_current_form();
			require_once( GFCommon::get_base_path() . '/form_display.php');
			GFFormDisplay::enqueue_form_scripts( $form );
		}

		public function is_workflow_detail_page(){
			$id  = rgget( 'id' );
			$lid = rgget( 'lid' );
			return rgget( 'page' ) == 'gravityflow-inbox' && rgget( 'view' ) == 'entry' && ! empty( $id ) && ! empty( $lid );
		}

		function get_workflow_form_ids(){
			if ( isset( $this->form_ids ) ) {
				return $this->form_ids;
			}
			$forms = GFAPI::get_forms();
			$form_ids = array();
			foreach ( $forms as $form ) {
				$form_id = absint( $form['id'] );
				$feeds = gravity_flow()->get_feeds( $form_id );
				if ( ! empty( $feeds ) ) {
					$form_ids[] = $form_id;
				}
			}
			$this->form_ids = $form_ids;
			return $this->form_ids;
		}

		public function cron(){
			$this->log_debug( __METHOD__ . '() Starting cron.' );
			$form_ids = $this->get_workflow_form_ids();

			if ( empty( $form_ids  ) ) {
				return;
			}

			global $wpdb;

			$lead_table = GFFormsModel::get_lead_table_name();
			$meta_table = GFFormsModel::get_lead_meta_table_name();

			$sql = "
SELECT l.id, l.form_id
FROM $lead_table l
INNER JOIN $meta_table m
ON l.id = m.lead_id
AND l.status='active'
AND m.meta_key LIKE 'workflow_step_status_%'
AND m.meta_value='queued'";

			$results = $wpdb->get_results( $sql );

			if ( empty ( $results ) || is_wp_error( $results ) ) {
				return;
			}

			$this->log_debug( __METHOD__ . '() Queued entries: ' . print_r( $results, true ) );

			foreach ( $results as $result ) {
				$form = GFAPI::get_form( $result->form_id );
				$entry = GFAPI::get_entry( $result->id );
				$step = $this->get_current_step( $form, $entry );
				if ( $step ) {
					$complete = $step->start();
					if ( $complete ) {
						$this->process_workflow( $form, $entry['id'] );
					}
				}
			}

		}

		public function app_settings_title() {
			return esc_html__( 'Gravity Flow Settings', 'gravityflow' );
		}

		public function uninstall_warning_message() {
			return sprintf( esc_html__( '%sThis operation deletes ALL Gravity Flow settings%s. If you continue, you will NOT be able to retrieve these settings.', 'gravityflow' ), '<strong>', '</strong>' );
		}

		public function uninstall_confirm_message() {
			return __( "Warning! ALL Gravity Flow settings will be deleted. This cannot be undone. 'OK' to delete, 'Cancel' to stop", 'gravityflow' );
		}

		public function maybe_redirect_after_activation() {
			if ( get_option( 'gravityflow_do_activation_redirect', false ) ) {
				delete_option( 'gravityflow_do_activation_redirect' );
				if ( ! isset( $_GET['activate-multi'] ) ) {
					$url = admin_url( 'admin.php?page=gravityflow_settings' );
					wp_safe_redirect( $url );
				}
			}
		}

		public function filter_feed_actions( $action_links, $item, $column ) {

			if ( empty ( $action_links  ) ) {
				return $action_links;
			}
			$feed_id = $item['id'];
			$current_step = $this->get_step( $feed_id );
			$entry_count = $current_step->entry_count();

			if ( $entry_count > 0 ) {
				unset( $action_links['delete'] );
			}
			return $action_links;
		}

		public function action_gform_post_form_duplicated( $form_id, $new_id ) {

			$feeds = $this->get_feeds( $form_id );

			foreach ( $feeds as $feed ) {
				GFAPI::add_feed( $new_id, $feed['meta'], 'gravityflow' );
			}
		}

	}

}



