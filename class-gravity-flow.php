<?php


// Make sure Gravity Forms is active and already loaded.
if ( class_exists( 'GFForms' ) ) {

	// The Add-On Framework is not loaded by default.
	// Use the following function to load the appropriate files.
	GFForms::include_feed_addon_framework();

	/**
	 * Gravity Flow
	 *
	 *
	 * @package     GravityFlow
	 * @subpackage  Classes/Gravity_Flow
	 * @copyright   Copyright (c) 2015-2017, Steven Henty S.L.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0
	 */
	class Gravity_Flow extends GFFeedAddOn {

		private static $_instance = null;

		public $_version = GRAVITY_FLOW_VERSION;

		// The Framework will display an appropriate message on the plugins page if necessary
		protected $_min_gravityforms_version = '2.1';

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
			'gravityflow_reports',
			'gravityflow_activity',
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
			'gravityflow_activity',
			'gravityflow_reports',
		);
		protected $_capabilities_uninstall = 'gravityflow_uninstall';

		public static function get_instance() {
			if ( self::$_instance == null ) {
				self::$_instance = new Gravity_Flow();
			}

			return self::$_instance;
		}

		private $_custom_page_content = null;

		private function __clone() {
		} /* do nothing */


		public function pre_init() {
			add_filter( 'gform_export_form', array( $this, 'filter_gform_export_form' ) );
			add_action( 'gform_forms_post_import', array( $this, 'action_gform_forms_post_import' ) );
			parent::pre_init();
			add_filter( 'cron_schedules', array( $this, 'filter_cron_schedule' ) );
			if ( ! wp_next_scheduled( 'gravityflow_cron' ) ) {
				wp_schedule_event( time(), 'fifteen_minutes', 'gravityflow_cron' );
			}

			add_action( 'gravityflow_cron', array( $this, 'cron' ) );
			add_action( 'wp', array( $this, 'filter_wp' ) );
		}

		public function init() {
			parent::init();

			if ( ! is_user_logged_in() ) {
				add_filter( 'nonce_user_logged_out', array( $this, 'filter_nonce_user_logged_out' ) );
			}

			add_shortcode( 'gravityflow', array( $this, 'shortcode' ) );

			// Prevent default feed processing behaviour; step processing starts after submission.
			remove_filter( 'gform_entry_post_save', array( $this, 'maybe_process_feed' ), 10 );
			add_filter( 'auto_update_plugin', array( $this, 'maybe_auto_update' ), 10, 2 );
			add_filter( 'gform_enqueue_scripts', array( $this, 'filter_gform_enqueue_scripts' ), 10, 2 );
			add_filter( 'gform_pre_replace_merge_tags', array( $this, 'replace_variables' ), 10, 7 );

			add_action( 'gform_entry_created', array( $this, 'action_entry_created' ), 8, 2 );
			add_action( 'gform_register_init_scripts', array( $this, 'filter_gform_register_init_scripts' ), 10, 3 );
			add_action( 'wp_login', array( $this, 'filter_wp_login' ), 10, 2 );
			add_action( 'gform_post_add_entry', array( $this, 'action_gform_post_add_entry' ), 10, 2 );
			add_action( 'gform_after_submission', array( $this, 'after_submission' ), 9, 2 );
			add_action( 'gform_after_update_entry', array( $this, 'filter_after_update_entry' ), 10, 2 );

			$this->add_delayed_payment_support(
				array(
					'option_label' => esc_html__( 'Start the Workflow once payment has been received.', 'gravityflow' ),
				)
			);

			// GravityView Integration
			add_filter( 'gravityview/adv_filter/field_filters', array( $this, 'filter_gravityview_adv_filter_field_filters' ), 10, 2 );
			add_filter( 'gravityview_search_criteria', array( $this, 'filter_gravityview_search_criteria' ), 999, 3 );
			add_filter( 'gravityview/common/get_entry/check_entry_display', array( $this, 'filter_gravityview_common_get_entry_check_entry_display' ), 999, 2 );
		}

		public function init_admin() {
			parent::init_admin();

			add_action( 'gform_entry_detail_sidebar_middle', array( $this, 'entry_detail_status_box' ), 10, 2 );
			add_filter( 'gform_notification_events', array( $this, 'add_notification_event' ), 10, 2 );

			add_filter( 'set-screen-option', array( $this, 'set_option' ), 10, 3 );
			add_action( 'load-workflow_page_gravityflow-status', array( $this, 'load_screen_options' ) );
			add_filter( 'gform_entries_field_value', array( $this, 'filter_gform_entries_field_value' ), 10, 4 );

			add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );

			add_filter( $this->_slug . '_feed_actions', array( $this, 'filter_feed_actions' ), 10, 3 );

			if ( ! has_action( 'gform_post_form_duplicated', array( $this, 'post_form_duplicated' ) ) ) {
				add_action( 'gform_post_form_duplicated', array( $this, 'post_form_duplicated' ), 10, 2 );
			}

			// Members 2.0+ Integration.
			if ( function_exists( 'members_register_cap_group' ) ) {
				remove_filter( 'members_get_capabilities', array( $this, 'members_get_capabilities' ) );
				add_action( 'members_register_cap_groups', array( $this, 'members_register_cap_group' ) );
				add_action( 'members_register_caps', array( $this, 'members_register_caps' ) );
			}

			if ( $this->is_app_settings() ) {
				require_once( GFCommon::get_base_path() . '/tooltips.php' );
			}
		}

		public function init_ajax() {
			parent::init_ajax();
			add_action( 'wp_ajax_gravityflow_save_feed_order', array( $this, 'ajax_save_feed_order' ) );
			add_action( 'wp_ajax_gravityflow_feed_message', array( $this, 'ajax_feed_message' ) );

			add_action( 'wp_ajax_gravityflow_print_entries', array( $this, 'ajax_print_entries' ) );
			add_action( 'wp_ajax_nopriv_gravityflow_print_entries', array( $this, 'ajax_print_entries' ) );

			add_action( 'wp_ajax_gravityflow_export_status', array( $this, 'ajax_export_status' ) );
			add_action( 'wp_ajax_nopriv_gravityflow_export_status', array( $this, 'ajax_export_status' ) );
			add_action( 'wp_ajax_gravityflow_download_export', array( $this, 'ajax_download_export' ) );
		}

		public function init_frontend() {
			parent::init_frontend();
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ), 10 );
			add_action( 'template_redirect', array( $this, 'action_template_redirect' ), 2 );
			if ( class_exists( 'GFSignature' ) && ! class_exists( 'GF_Field_Signature' ) ) {
				add_filter( 'gform_admin_pre_render', array( $this, 'delete_signature_script' ) );
				$this->maybe_save_signature();
			}
		}

		public function get_short_title() {
			return $this->translate_navigation_label( 'workflow' );
		}

		public function setup() {
			parent::setup();
		}

		public function upgrade( $previous_version ) {

			wp_cache_flush();

			if ( empty( $previous_version ) ) {
				// New installation
				$settings = $this->get_app_settings();
				if ( defined( 'GRAVITY_FLOW_LICENSE_KEY' ) ) {
					$settings['license_key'] = GRAVITY_FLOW_LICENSE_KEY;
				} else {
					update_option( 'gravityflow_pending_installation', true );
				}
				$settings['background_updates'] = true;
				$this->update_app_settings( $settings );

			} else {
				// Upgrade
				if ( version_compare( $previous_version,'1.5.1', '<' ) ) {
					$this->fix_workflow_field_choices();
				}

				if ( version_compare( $previous_version,'1.7.1-dev', '<' ) ) {
					$this->upgrade_171();
				}
			}

			wp_cache_flush();

			$this->setup_db();
		}

		private function setup_db() {
			global $wpdb;

			// Default collatation
			$charset_collate = 'utf8_unicode_ci';

			require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}

			$sql = "
CREATE TABLE {$wpdb->prefix}gravityflow_activity_log (
id bigint(20) unsigned not null auto_increment,
log_object varchar(50),
log_event varchar(50),
log_value varchar(255),
date_created datetime not null,
form_id mediumint(8) unsigned not null,
lead_id int(10) unsigned not null,
assignee_id varchar(255),
assignee_type varchar(50),
display_name varchar(250),
feed_id mediumint(8) unsigned not null,
duration int(10) unsigned not null,
PRIMARY KEY  (id)
) $charset_collate;";

			// Fixes an issue with dbDelta lower-casing table names, which cause problems on case sensitive DB servers.
			if ( class_exists( 'GF_Upgrade' ) ) {
				add_filter( 'dbdelta_create_queries', array( gf_upgrade(), 'dbdelta_fix_case' ) );
			} else {
				// deprecated since Gravity Forms 2.2
				add_filter( 'dbdelta_create_queries', array( 'RGForms', 'dbdelta_fix_case' ) );
			}

			dbDelta( $sql );

			if ( class_exists( 'GF_Upgrade' ) ) {
				remove_filter( 'dbdelta_create_queries', array( gf_upgrade(), 'dbdelta_fix_case' ) );
			} else {
				// deprecated since Gravity Forms 2.2
				remove_filter( 'dbdelta_create_queries', array( 'RGForms', 'dbdelta_fix_case' ) );
			}
		}

		/**
		 * Fixes and issue with the Assignee, User and Role fields where the choices are saved in the form meta causing
		 * conditional logic and field filters to display a dropdown with out of date choices.
		 *
		 * @since 1.5.1
		 */
		private function fix_workflow_field_choices() {
			$forms = GFAPI::get_forms();
			foreach ( $forms as $form ) {
				$form_dirty = false;
				if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
					foreach ( $form['fields'] as $field ) {
						/** @var GF_Field $field */
						if ( in_array( $field->type, array( 'workflow_assignee_select', 'workflow_user', 'workflow_role' ) ) ) {
							if ( is_array( $field->choices ) ) {
								$field->choices = '';
								$form_dirty = true;
							}
						}
					}
				}
				if ( $form_dirty ) {
					GFAPI::update_form( $form );
				}
			}
		}

		public function upgrade_171() {
			$steps = $this->get_steps();

			foreach( $steps as $step ) {
				$step_dirty = false;
				$step_type = $step->get_type();

				if ( $step_type == 'approval' && $step->type == 'select' && ! $step->assignee_policy ) {
					// Convert unanimous_approval setting to assignee_policy if not already.
					$unanimous_approval = $step->unanimous_approval;
					if ( ! $unanimous_approval ) {
						$step->assignee_policy = 'any';
					} else {
						$step->assignee_policy = 'all';
					}
					$step_dirty = true;
				}

				if ( in_array( $step_type, array( 'approval', 'user_input' ), true )
				     && $step->type == 'routing'
				     && ! $step->assignee_policy_171_migration_complete
				) {
					$step->assignee_policy = 'all';
					$step->assignee_policy_171_migration_complete = true;
					$step_dirty = true;
				}

				if ( $step_dirty ) {
					$this->save_feed_settings( $step->get_id(), $step->get_form_id(), $step->get_feed_meta() );
				}
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
					/* @var GF_Field $field */
					$input_fields[] = array(
						'key'  => absint( $field->id ),
						'text' => esc_html( $field->get_field_label( false, null ) ),
					);
				}
			}

			$users = is_admin() ? $this->get_users_as_choices() : array();

			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

			$nonce = wp_create_nonce( 'wp_rest' );

			$scripts = array(
				array(
					'handle'   => 'gravityflow_form_editor_js',
					'src'      => $this->get_base_url() . "/js/form-editor{$min}.js",
					'version'  => $this->_version,
					'enqueue'  => array(
						array(
							'admin_page' => array('form_editor'),
						),
					),
					'strings' => array(
						'user' => array(
							'defaults' => array(
								'label' => esc_html__( 'User', 'gravityflow' ),
							),
						),
						'role' => array(
							'defaults' => array(
								'label' => esc_html__( 'Role', 'gravityflow' ),
							),
						),
						'discussion' => array(
							'defaults' => array(
								'label' => esc_html__( 'Discussion', 'gravityflow' ),
							),
						),
					),
				),
				array(
					'handle'   => 'gravityflow_settings_js',
					'src'      => $this->get_base_url() . "/js/settings.js",
					'version'  => $this->_version,
					'enqueue'  => array(
						array( 'query' => 'page=gravityflow_settings&view=connected_apps' ),
					),
					'strings' => array(
						'nonce' => wp_create_nonce( 'gflow_settings_js' ),
						'ajaxurl' => admin_url( 'admin-ajax.php' ),
						'required_fields' => esc_html__( 'Please fill in all required fields', 'gravityflow' ),
					)
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
					'handle'  => 'gf_routing_setting',
					'src'     => $this->get_base_url() . "/js/routing-setting{$min}.js",
					'deps'    => array( 'jquery' ),
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=_notempty_' ),
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=0' ),
					),
					'strings' => array(
						'accounts'     => $users,
						'fields'       => $routing_fields,
						'input_fields' => $input_fields,
					),
				),
				array(
					'handle'  => 'gravityflow_form_settings_js',
					'src'     => $this->get_base_url() . "/js/form-settings{$min}.js",
					'deps'    => array( 'jquery', 'jquery-ui-core', 'jquery-ui-tabs', 'jquery-ui-datepicker', 'gform_datepicker_init', 'gf_routing_setting' ),
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=_notempty_' ),
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=0' ),
					),
					'strings' => array(
						'feedId'         => absint( rgget( 'fid' ) ),
						'formId'         => absint( rgget( 'id' ) ),
						'mergeTagLabels' => $this->get_form_settings_js_merge_tag_labels(),
					),
				),
				array(
					'handle'   => 'gravityflow_generic_map_js',
					'src'      => $this->get_base_url() . "/js/generic-map{$min}.js",
					'version'  => $this->_version,
					'enqueue'  => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=_notempty_' ),
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&fid=0' ),
					),
				),
				array(
					'handle'  => 'gravityflow_feed_list',
					'src'     => $this->get_base_url() . "/js/feed-list{$min}.js",
					'deps'    => array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ),
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow' ),
					),
				),
				array(
					'handle'  => 'gravityflow_entry_detail',
					'src'     => $this->get_base_url() . "/js/entry-detail{$min}.js",
					'version' => $this->_version,
					'deps'    => array( 'jquery', 'sack' ),
					'enqueue' => array(
						array(
							'query' => 'page=gravityflow-inbox',
						),
					),
				),
				array(
					'handle'  => 'gravityflow_status_list',
					'src'     => $this->get_base_url() . "/js/status-list{$min}.js",
					'deps'    => array( 'jquery', 'gform_field_filter' ),
					'version' => $this->_version,
					'enqueue' => array(
						array(
							'query' => 'page=gravityflow-status',
						),
					),
					'strings' => array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ),
				),
				array(
					'handle'  => 'google_charts',
					'src'     => 'https://www.google.com/jsapi',
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gravityflow-reports' ),
					),
				),
				array(
					'handle'  => 'gravityflow_reports',
					'src'     => $this->get_base_url() . "/js/reports{$min}.js",
					'version' => $this->_version,
					'deps' => array( 'jquery', 'google_charts' ),
					'enqueue' => array(
						array( 'query' => 'page=gravityflow-reports' ),
					),
				),
				array(
					'handle' => 'gravityflow_inbox',
					'src' => $this->get_base_url() . "/js/inbox{$min}.js",
					'version' => $this->_version,
					'enqueue' => array(
						array(
							'query' => 'page=gravityflow-inbox',
						),
					),
					'strings' => array(
						'restUrl' => esc_url_raw( rest_url() ),
						'nonce'   => $nonce,
					),
				),
			);

			return array_merge( parent::scripts(), $scripts );
		}

		public function enqueue_frontend_scripts() {
			global $wp_query;
			if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
				$shortcode_found = $this->look_for_shortcode();


				if ( $shortcode_found ) {
					$this->enqueue_form_scripts();
					$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
					$nonce = wp_create_nonce( 'wp_rest' );
					wp_enqueue_script( 'sack', "/wp-includes/js/tw-sack$min.js", array(), '1.6.1' );
					wp_enqueue_script( 'gravityflow_entry_detail', $this->get_base_url() . "/js/entry-detail{$min}.js", array( 'jquery', 'sack' ), $this->_version );
					wp_enqueue_script( 'gravityflow_status_list', $this->get_base_url() . "/js/status-list{$min}.js",  array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'gform_datepicker_init' ), $this->_version );
					wp_enqueue_script( 'gform_field_filter', GFCommon::get_base_url() . "/js/gf_field_filter{$min}.js",  array( 'jquery', 'gform_datepicker_init' ), $this->_version );
					wp_enqueue_script( 'gravityflow_frontend', $this->get_base_url() . "/js/frontend{$min}.js",  array(), $this->_version );
					wp_enqueue_script( 'gravityflow_inbox', $this->get_base_url() . "/js/inbox{$min}.js",  array(), $this->_version );

					wp_enqueue_style( 'gform_admin',  GFCommon::get_base_url() . "/css/admin{$min}.css", null, $this->_version );
					wp_enqueue_style( 'gravityflow_entry_detail',  $this->get_base_url() . "/css/entry-detail{$min}.css", null, $this->_version );
					wp_enqueue_style( 'gravityflow_frontend_css', $this->get_base_url() . "/css/frontend{$min}.css", null, $this->_version );
					wp_enqueue_style( 'gravityflow_status', $this->get_base_url() . "/css/status{$min}.css", null, $this->_version );
					wp_localize_script( 'gravityflow_status_list', 'gravityflow_status_list_strings', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
					wp_localize_script( 'gravityflow_inbox', 'gravityflow_inbox_strings', array( 'restUrl' => esc_url_raw( rest_url() ), 'nonce' => $nonce ) );



					/**
					 * Allows additional scripts to be enqueued when the gravityflow shortcode is present on the page.
					 */
					do_action( 'gravityflow_enqueue_frontend_scripts' );
					GFCommon::maybe_output_gf_vars();
				}
			}
		}

		public function look_for_shortcode() {
			global $wp_query;

			$shortcode_found = false;
			foreach ( $wp_query->posts as $post ) {
				if ( stripos( $post->post_content, '[gravityflow' ) !== false ) {
					$shortcode_found = true;
					break;
				}
			}
			return $shortcode_found;
		}

		public function filter_nonce_user_logged_out( $uid ) {
			if ( empty( $uid ) ) {
				$assignee_key = $this->get_current_user_assignee_key();
				if ( ! empty( $assignee_key ) ) {
					$uid = $assignee_key;
				}
			}
			return $uid;
		}

		public function filter_gform_enqueue_scripts( $form, $is_ajax ) {

			if ( $this->has_enhanced_dropdown( $form ) ) {
				wp_enqueue_script( 'gform_gravityforms' );
				if ( wp_script_is( 'chosen', 'registered' ) ) {
					wp_enqueue_script( 'chosen' );
				} else {
					wp_enqueue_script( 'gform_chosen' );
				}
			}
		}

		public function filter_gform_register_init_scripts( $form, $field_values, $is_ajax ) {

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
				if ( $field->enableEnhancedUI && in_array( $input_type, array( 'workflow_assignee_select', 'workflow_user', 'workflow_role' ) ) ) {
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
				if ( in_array( RGFormsModel::get_input_type( $field ), array( 'workflow_assignee_select', 'workflow_user', 'workflow_role' ) ) && $field->enableEnhancedUI ) {
					return true;
				}
			}

			return false;
		}

		public function feed_list_title() {
			$url = add_query_arg( array( 'fid' => '0' ) );
			$url = esc_url( $url );
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
						array(
							'query'      => 'page=gravityflow-reports',
						),
						array(
							'query'      => 'page=gravityflow-activity',
						),
					),
				),
				array(
					'handle'  => 'gravityflow_inbox',
					'src'     => $this->get_base_url() . "/css/inbox{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array(
							'query' => 'page=gravityflow-inbox',
						),
					),
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
					),
				),
				array(
					'handle'  => 'gravityflow_submit',
					'src'     => $this->get_base_url() . "/css/submit{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array(
							'query'      => 'page=gravityflow-submit',
						),
					),
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
					'handle'  => 'gravityflow_activity',
					'src'     => $this->get_base_url() . "/css/activity{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array(
							'query'      => 'page=gravityflow-activity',
						),
					),
				),
				array(
					'handle'  => 'gravityflow_feed_list',
					'src'     => $this->get_base_url() . "/css/feed-list{$min}.css",
					'version' => $this->_version,
					'deps' => array( 'wp-color-picker' ),
					'enqueue' => array(
						array(
							'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow',
						),
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
				array(
					'handle'  => 'gravityflow_settings',
					'src'     => $this->get_base_url() . "/css/settings{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=gravityflow_settings&view=_empty_' ),
						array( 'query' => 'page=gravityflow_settings&view=settings' ),
						array( 'query' => 'page=gravityflow_settings&view=labels' ),
						array( 'query' => 'page=gravityflow_settings&view=connected_apps' ),
					),
				),
				array(
					'handle'  => 'gravityflow_discussion_field',
					'src'     => $this->get_base_url() . "/css/discussion-field{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array( 'field_types' => array( 'workflow_discussion' ) ),
					),
				),
				array(
					'handle'  => 'gravityflow_dashicons',
					'src'     => $this->get_base_url() . "/css/dashicons{$min}.css",
					'version' => $this->_version,
					'enqueue' => array(
						array( 'query' => 'page=roles&action=edit' ),
					),
				),
			);

			return array_merge( parent::styles(), $styles );
		}

		public function feed_settings_title() {
			return esc_html__( 'Workflow Step Settings', 'gravityflow' );
		}

		public function set_option( $status, $option, $value ) {
			if ( 'entries_per_page' == $option ) {
				return $value;
			}

			return $status;
		}

		public function get_users_as_choices() {

			$role_choices = Gravity_Flow_Common::get_roles_as_choices( true, true );

			$args            = apply_filters( 'gravityflow_get_users_args', array( 'number' => 1000, 'orderby' => 'display_name' ) );
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

			$email_fields_as_choices = $this->get_email_fields_as_choices( $form );

			if ( ! empty( $email_fields_as_choices ) ) {
				$field_choices = array_merge( $field_choices, $email_fields_as_choices );
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

		public function get_assignee_fields_as_choices( $form = null ) {
			if ( empty( $form ) ) {
				$form_id = absint( rgget( 'id' ) );
				$form = GFAPI::get_form( $form_id );
			}

			$assignee_fields = array();
			if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					/* @var GF_Field $field */
					$type = GFFormsModel::get_input_type( $field );
					if ( $type == 'workflow_assignee_select' ) {
						$assignee_fields[] = array( 'label' => GFFormsModel::get_label( $field ), 'value' => 'assignee_field|' . $field->id );
					} elseif ( $type == 'workflow_user' ) {
						$assignee_fields[] = array( 'label' => GFFormsModel::get_label( $field ), 'value' => 'assignee_user_field|' . $field->id );
					} elseif ( $type == 'workflow_role' ) {
						$assignee_fields[] = array( 'label' => GFFormsModel::get_label( $field ), 'value' => 'assignee_role_field|' . $field->id );
					}
				}
			}
			return $assignee_fields;
		}

		public function get_email_fields_as_choices( $form = null) {
			if ( empty( $form ) ) {
				$form_id = absint( rgget( 'id' ) );
				$form = GFAPI::get_form( $form_id );
			}

			$email_fields = array();
			if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					/* @var GF_Field $field */
					if ( $field->get_input_type() == 'email' ) {
						$email_fields[] = array( 'label' => GFFormsModel::get_label( $field ), 'value' => 'email_field|' . $field->id );
					}
				}
			}
			return $email_fields;
		}

		/**
		 * Override the feed_settings_field() function and return the configuration for the Feed Settings.
		 * Updating is handled by the Framework.
		 *
		 * @return array
		 */
		public function feed_settings_fields() {
			$current_step_id = $this->get_current_feed_id();

			$step_type_choices = array();

			$step_classes = Gravity_Flow_Steps::get_all();

			foreach ( $step_classes as $key => $step_class ) {
				$step_type_choice = array( 'label' => $step_class->get_label(), 'value' => $step_class->get_type() );
				$step_type_choice['icon_url'] = $step_class->get_icon_url();
				if ( $current_step_id > 0 ) {
					$step_type_choice['disabled'] = 'disabled';
					$step_type_choice['div_class'] = 'gravityflow-disabled';
				}
				if ( $step_class->is_supported() ) {
					$step_type_choices[] = $step_type_choice;
				} else {
					unset( $step_classes[ $key ] );
				}
			}

			$settings = array();

			$step_type_setting = array(
				'name'       => 'step_type',
				'label'      => esc_html__( 'Step Type', 'gravityflow' ),
				'type'       => 'radio_image',
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
						'tooltip'  => '<h6>' . __( 'Name', 'gravityflow' ) . '</h6>' . __( 'Enter a name to uniquely identify this step.', 'gravityflow' ),
					),
					array(
						'name'  => 'description',
						'label' => esc_html__( 'Description', 'gravityflow' ),
						'class' => 'fieldwidth-3 fieldheight-2',
						'type'  => 'textarea',
					),
					$step_type_setting,
					array(
						'name'                => 'step_highlight',
						'label'               => esc_html__( 'Highlight', 'gravityflow' ),
						'type'                => 'step_highlight',
						'required'            => false,
						'tooltip'             => esc_html__( 'Highlighted steps will stand out in both the workflow inbox and the step list. Use highlighting to bring attention to important tasks and to help organise complex workflows.', 'gravityflow' ),
					),
					array(
						'name'           => 'condition',
						'tooltip'        => esc_html__( "Build the conditional logic that should be applied to this step before it's allowed to be processed. If an entry does not meet the conditions of this step it will fall on to the next step in the list.", 'gravityflow' ),
						'label'          => esc_html__( 'Condition', 'gravityflow' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Enable Condition for this step', 'gravityflow' ),
						'instructions'   => esc_html__( 'Perform this step if', 'gravityflow' ),
					),
					array(
						'name' => 'scheduled',
						'label' => esc_html__( 'Schedule', 'gravityflow' ),
						'type' => 'schedule',
						'tooltip' => esc_html__( 'Scheduling a step will queue entries and prevent them from starting this step until the specified date or until the delay period has elapsed.', 'gravityflow' )
						             . ' ' . esc_html__( 'Note: the schedule setting requires the WordPress Cron which is included and enabled by default unless your host has deactivated it.', 'gravityflow' ),

					),
				),
			);

			foreach ( $step_classes as $step_class ) {
				$type = $step_class->get_type();
				$step_settings = $step_class->get_settings();
				$step_settings['id'] = 'gravityflow-step-settings-' . $type;
				$step_settings['class'] = 'gravityflow-step-settings';

				if ( ! isset( $step_settings['fields'] ) ) {
					$step_settings['fields'] = array();
				}
				$status_options = $step_class->get_status_config();

				if ( $step_class->supports_expiration() ) {
					$final_status_choices = array();

					foreach ( $status_options as $status_option ) {
						$final_status_choices[] = array( 'label' => $status_option['status_label'], 'value' => $status_option['status'] );
					}

					$final_status_choices[] = array( 'label' => esc_html__( 'Expired', 'gravityflow' ), 'value' => 'expired' );

					$step_settings['fields'][] = array(
						'name' => 'expiration',
						'label' => esc_html__( 'Expiration', 'gravityflow' ),
						'tooltip' => esc_html__( 'Enable the expiration setting to allow this step to expire. Once expired, the entry will automatically proceed to the step configured in the Next Step setting(s) below.', 'gravityflow' ),
						'type'       => 'expiration',
						'status_choices' => $final_status_choices,
					);
				}

				foreach ( $status_options as $status_option ) {
					$setting_label = isset( $status_option['destination_setting_label'] ) ?  $status_option['destination_setting_label'] : esc_html__( 'Next step if', 'gravityflow' ) . ' ' . $status_option['status_label'];
					$default_destination = isset( $status_option['default_destination'] ) ? $status_option['default_destination'] : 'next';
					$step_settings['fields'][] = array(
						'name' => 'destination_' . $status_option['status'],
						'label' => $setting_label,
						'type'       => 'step_selector',
						'default_value' => $default_destination,
					);
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
							'error'   => __( 'There was an error while saving the step settings', 'gravityflow' ),
						),
					),
				),
			);

			return $settings;
		}

		public function ajax_feed_message() {
			$html            = '';
			$warning         = false;
			$entry_count     = 0;
			$current_step_id = absint( rgget( 'fid' ) );

			if ( $current_step_id ) {
				$current_step = $this->get_step( $current_step_id );
				if ( empty( $current_step ) ) {
					$warning = esc_html__( 'This step type is missing.', 'gravityflow' );
				} elseif ( ! $current_step->is_supported() ) {
					$warning = esc_html__( 'The plugin required by this step type is missing.', 'gravityflow' );
				} else {
					$entry_count = $current_step->entry_count();
				}
			}

			if ( $entry_count > 0 ) {
				$warning = sprintf( _n( 'There is %s entry currently on this step. This entry may be affected if the settings are changed.', 'There are %s entries currently on this step. These entries may be affected if the settings are changed.', $entry_count, 'gravityflow' ), $entry_count );
			}

			if ( $warning ) {
				$html = '<div class="delete-alert alert_red"><i class="fa fa-exclamation-triangle gf_invalid"></i> ' . $warning . '</div>';
			}

			echo $html;
			die();
		}

		public function save_feed_validation_callback( $field, $field_setting ) {

			$current_step_id = $this->get_current_feed_id();
			$entry_count = 0;
			$current_step = false;
			if ( $current_step_id ) {
				$current_step = $this->get_step( $current_step_id );
				$entry_count = $current_step->entry_count();
			}

			$assignee_settings = array();

			if ( $entry_count > 0 && $current_step ) {
				$assignee_settings['assignees'] = array();
				$current_assignees = $current_step->get_assignees();
				foreach ( $current_assignees as $current_assignee ) {
					$assignee_settings['assignees'][] = $current_assignee->get_key();
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

		public function maybe_refresh_assignees() {
			$results = array(
				'removed' => array(),
				'added' => array(),
			);

			if ( ! ( rgget( 'page' ) == 'gf_edit_forms' && rgget( 'view' ) == 'settings' && rgget( 'subview' ) == 'gravityflow' ) ) {
				return $results;
			}

			$current_step_id = $this->get_current_feed_id();
			$current_step = $this->get_step( $current_step_id );
			if ( empty( $current_step ) ) {
				return $results;
			}
			$assignee_settings['assignees'] = array();
			$assignees = $current_step->get_assignees();
			foreach ( $assignees as $assignee ) {
				/* @var Gravity_Flow_Assignee $assignee */
				$assignee_settings['assignees'][] = $assignee->get_key();
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

		public function refresh_assignees() {
			$results = array(
				'removed' => array(),
				'added' => array(),
			);
			$current_step_id = $this->get_current_feed_id();

			$current_step = $this->get_step( $current_step_id );

			$entry_count = $current_step->entry_count();

			if ( $entry_count == 0 ) {
				// Nothing to do
				return $results;
			}

			$form = $this->get_current_form();


			// Avoid paging through entries from GFAPI::get_entries() by using custom query.
			$assignee_status_by_entry = $this->get_asssignee_status_by_entry( $form['id'] );

			foreach ( $assignee_status_by_entry as $entry_id => $assignee_status ) {
				$entry = GFAPI::get_entry( $entry_id );
				$step_for_entry = $this->get_step( $current_step_id, $entry );
				if ( $entry['workflow_step'] != $step_for_entry->get_id() ) {
					continue;
				}
				$updated = false;
				$current_assignees = $step_for_entry->get_assignees();
				foreach ( $current_assignees as $assignee ) {
					/* @var Gravity_Flow_Assignee $assignee */
					$assignee_key = $assignee->get_key();

					if ( ! isset( $assignee_status[ $assignee_key ] ) ) {
						// New assignee
						$step = $this->get_step( $current_step_id, $entry );
						$assignee->update_status( 'pending' );
						$step->end_if_complete();
						$results['added'][] = $assignee;
						$updated = true;
					}
				}

				foreach ( $assignee_status as $old_assignee_key => $old_status ) {
					foreach ( $current_assignees as $assignee ) {
						$assignee_key = $assignee->get_key();
						if ( $assignee_key == $old_assignee_key ) {
							continue 2;
						}
					}
					// No longer an assignee - remove
					$old_assignee = new Gravity_Flow_Assignee( $old_assignee_key, $step_for_entry );
					$old_assignee->remove();
					$old_assignee->log_event( 'removed' );
					$results['removed'][] = $old_assignee;
					$updated = true;
				}

				if ( $updated ) {
					$this->process_workflow( $form, $entry_id );
				}
			}

			return $results;
		}

		public function get_asssignee_status_by_entry( $form_id ) {
			global $wpdb;
			$assignee_status_by_entry = array();
			$table = Gravity_Flow_Common::get_entry_meta_table_name();
			$entry_table = Gravity_Flow_Common::get_entry_table_name();
			$entry_id_column = Gravity_Flow_Common::get_entry_id_column_name();
			$sql = $wpdb->prepare( "
			SELECT m.form_id, m.{$entry_id_column} as entry_id, m.meta_key, m.meta_value
			FROM $table m
			INNER JOIN $entry_table l
			ON l.id = m.{$entry_id_column}
			WHERE m.meta_key LIKE %s
			AND m.meta_key NOT LIKE '%%_timestamp'
			AND m.form_id=%d
			AND l.status='active'", 'workflow_user_id_%', $form_id );
			$rows = $wpdb->get_results( $sql );

			if ( ! is_wp_error( $rows ) && count( $rows ) > 0 ) {
				foreach ( $rows as $row ) {
					$user_id = str_replace( 'workflow_user_id_', '', $row->meta_key );
					if ( ! isset( $assignee_status_by_entry[ $row->entry_id ] ) ) {
						$assignee_status_by_entry[ $row->entry_id ] = array();
					}
					$assignee_status_by_entry[ $row->entry_id ][ 'user_id|' . $user_id ] = $row->meta_value;
				}
			}

			$sql = $wpdb->prepare( "
			SELECT m.form_id, m.{$entry_id_column} as entry_id, m.meta_key, m.meta_value
			FROM $table m
			INNER JOIN $entry_table l
			ON l.id = m.{$entry_id_column}
			WHERE m.meta_key LIKE %s
			AND m.meta_key NOT LIKE '%%_timestamp'
			AND m.form_id=%d
			AND l.status='active'", 'workflow_email_%', $form_id );
			$rows = $wpdb->get_results( $sql );

			if ( ! is_wp_error( $rows ) && count( $rows ) > 0 ) {
				foreach ( $rows as $row ) {
					$user_id = str_replace( 'workflow_email_', '', $row->meta_key );
					if ( ! isset( $assignee_status_by_entry[ $row->entry_id ] ) ) {
						$assignee_status_by_entry[ $row->entry_id ] = array();
					}
					$assignee_status_by_entry[ $row->entry_id ][ 'email|' . $user_id ] = $row->meta_value;
				}
			}

			$sql = $wpdb->prepare( "
			SELECT m.form_id, m.{$entry_id_column} as entry_id, m.meta_key, m.meta_value
			FROM $table m
			INNER JOIN $entry_table l
			ON l.id = m.{$entry_id_column}
			WHERE m.meta_key LIKE %s
			AND m.meta_key NOT LIKE '%%_timestamp'
			AND m.form_id=%d
			AND l.status='active'", 'workflow_role_%', $form_id );
			$rows = $wpdb->get_results( $sql );

			if ( ! is_wp_error( $rows ) && count( $rows ) > 0 ) {
				foreach ( $rows as $row ) {
					$user_id = str_replace( 'workflow_role_', '', $row->meta_key );
					if ( ! isset( $assignee_status_by_entry[ $row->entry_id ] ) ) {
						$assignee_status_by_entry[ $row->entry_id ] = array();
					}
					$assignee_status_by_entry[ $row->entry_id ][ 'role|' . $user_id ] = 'role|' . $user_id;
				}
			}

			return $assignee_status_by_entry;
		}

		public function filter_gform_entries_field_value( $value, $form_id, $field_id, $entry ) {
			if ( $field_id == 'workflow_step' ) {
				if ( empty( $value ) ) {
					$value = '';
				} else {
					$step = $this->get_step( $value );
					if ( $step ) {
						$value = $step->get_name();
					}
				}
			}
			return $value;
		}

		public function ajax_save_feed_order() {
			$feed_ids = rgpost( 'feed_ids' );
			$form_id  = absint( rgpost( 'form_id' ) );
			foreach ( $feed_ids as &$feed_id ) {
				$feed_id = absint( $feed_id );
			}
			update_option( 'gravityflow_feed_order_' . $form_id, $feed_ids );

			echo json_encode( array( array( 'ok' ), 200 ) );
			die();
		}

		public function ajax_print_entries() {
			require_once( $this->get_base_path() . '/includes/pages/class-print-entries.php' );
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
				$step = Gravity_Flow_Steps::create( $feed, $entry );
				if ( $step ) {
					$steps[] = $step;
				}
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

		/***
		 * Renders and initializes a radio field or a collection of radio fields based on the $field array.
		 * Images/icons are used in place of the HTML radio buttons.
		 *
		 * @param array $field - Field array containing the configuration options of this field
		 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
		 *
		 * @return string Returns the markup for the radio buttons
		 *
		 */
		protected function settings_radio_image( $field, $echo = true ) {

			$field['type'] = 'radio'; //making sure type is set to radio

			$selected_value   = $this->get_setting( $field['name'], rgar( $field, 'default_value' ) );
			$field_attributes = $this->get_field_attributes( $field );
			$horizontal       = rgar( $field, 'horizontal' ) ? ' gaddon-setting-inline' : '';
			$html             = '';
			if ( is_array( $field['choices'] ) ) {
				foreach ( $field['choices'] as $i => $choice ) {
					$choice['id']      = $field['name'] . $i;
					$choice_attributes = $this->get_choice_attributes( $choice, $field_attributes );

					$tooltip = isset( $choice['tooltip'] ) ? gform_tooltip( $choice['tooltip'], rgar( $choice, 'tooltip_class' ), true ) : '';

					$radio_value = isset( $choice['value'] ) ? $choice['value'] : $choice['label'];
					$checked     = checked( $selected_value, $radio_value, false );

					$div_class = rgar( $choice, 'div_class' );
					if ( ! empty( $div_class ) ) {
						$div_class = ' ' . sanitize_html_class( $div_class );
					}

					$icon_url = rgar( $choice, 'icon_url' );

					if ( strpos( $icon_url, 'http' ) === 0 ) {
						$icon = '<img src="' . $icon_url . '"/>';
					} else {
						$icon = $icon_url;
					}

					$html .= '
	                        <div id="gaddon-setting-radio-choice-' . $choice['id'] . '" class="gaddon-setting-radio' . $div_class . $horizontal . '">
	                        <input
	                                id = "' . esc_attr( $choice['id'] ) . '"
	                                type = "radio" ' .
					         'name="_gaddon_setting_' . esc_attr( $field['name'] ) . '" ' .
					         'value="' . $radio_value . '" ' .
					         implode( ' ', $choice_attributes ) . ' ' .
					         $checked .
					         ' />
	                        <label for="' . esc_attr( $choice['id'] ) . '">
	                            <span>' . $icon . '<br />' . esc_html( $choice['label'] ) . ' ' . $tooltip . '</span>
							</label>
	                        </div>
	                    ';
				}
			}

			if ( $this->field_failed_validation( $field ) ) {
				$html .= $this->get_error_icon( $field );
			}

			if ( $echo ) {
				echo $html;
			}

			return $html;
		}

		public function settings_schedule( $field ) {

			$form = $this->get_current_form();

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
						'label' => esc_html__( 'Delay', 'gravityflow' ),
						'value' => 'delay',
					),
					array(
						'label' => esc_html__( 'Date', 'gravityflow' ),
						'value' => 'date',
					),
				),
			);

			$date_fields = GFFormsModel::get_fields_by_type( $form, 'date' );

			$date_field_choices = array();

			if ( ! empty( $date_fields ) ) {
				$schedule_type['choices'][] = array(
					'label' => esc_html__( 'Date Field', 'gravityflow' ),
					'value' => 'date_field',
				);


				foreach ( $date_fields  as $date_field ) {
					$date_field_choices[] = array( 'value' => $date_field->id, 'label' => GFFormsModel::get_label( $date_field ) );
				}
			}

			$schedule_date_fields = array(
				'name' => 'schedule_date_field',
				'label' => esc_html__( 'Schedule Date Field', 'gravityflow' ),
				'choices' => $date_field_choices,
			);

			$schedule_date = array(
				'id' => 'schedule_date',
				'name' => 'schedule_date',
				'placeholder' => 'yyyy-mm-dd',
				'class' => 'datepicker datepicker_with_icon ymd_dash',
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
				'default_value' => 'hours',
				'choices' => array(
					array(
						'label' => esc_html__( 'Minute(s)', 'gravityflow' ),
						'value' => 'minutes',
					),
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
				),
			);

			$this->settings_checkbox( $scheduled );

			$enabled = $this->get_setting( 'scheduled', false );
			$schedule_type_setting = $this->get_setting( 'schedule_type', 'delay' );
			$schedule_style = $enabled ? '' : 'style="display:none;"';
			$schedule_date_style = ( $schedule_type_setting == 'date' ) ? '' : 'style="display:none;"';
			$schedule_delay_style = ( $schedule_type_setting == 'delay' ) ? '' : 'style="display:none;"';
			$schedule_date_fields_style = ( $schedule_type_setting == 'date_field' ) ? '' : 'style="display:none;"';
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
					esc_html_e( 'after the workflow step is triggered.', 'gravityflow' );
					?>
				</div>
				<div class="gravityflow-schedule-date-field-container" <?php echo $schedule_date_fields_style ?>>
					<?php
					esc_html_e( 'Start this step', 'gravityflow' );
					echo '&nbsp;';
					$delay_offset_field['name'] = 'schedule_date_field_offset';
					$delay_offset_field['default_value'] = '0';
					$this->settings_text( $delay_offset_field );
					$unit_field['name'] = 'schedule_date_field_offset_unit';
					$this->settings_select( $unit_field );
					echo '&nbsp;';
					$before_after_field = array(
						'name' => 'schedule_date_field_before_after',
						'label' => esc_html__( 'Schedule', 'gravityflow' ),
						'default_value' => 'after',
						'choices' => array(
							array(
								'label' => esc_html__( 'after', 'gravityflow' ),
								'value' => 'after',
							),
							array(
								'label' => esc_html__( 'before', 'gravityflow' ),
								'value' => 'before',
							),
						),
					);
					$this->settings_select( $before_after_field );

					$this->settings_select( $schedule_date_fields );
					?>
				</div>
			</div>
			<script>
				(function($) {
					$( '#scheduled' ).click(function(){
						$('.gravityflow-schedule-settings').slideToggle();
					});
					$( '#schedule_type0' ).click(function(){
						$('.gravityflow-schedule-delay-container').show();
						$('.gravityflow-schedule-date-container').hide();
						$('.gravityflow-schedule-date-field-container').hide();
					});
					$( '#schedule_type1' ).click(function(){
						$('.gravityflow-schedule-delay-container').hide();
						$('.gravityflow-schedule-date-container').show();
						$('.gravityflow-schedule-date-field-container').hide();
					});
					$( '#schedule_type2' ).click(function(){
						$('.gravityflow-schedule-delay-container').hide();
						$('.gravityflow-schedule-date-container').hide();
						$('.gravityflow-schedule-date-field-container').show();
					});
				})(jQuery);
			</script>
			<?php

		}

		public function settings_expiration( $field ) {

			$form = $this->get_current_form();

			$expiration = array(
				'name' => 'expiration',
				'type' => 'checkbox',
				'choices' => array(
					array(
						'label' => esc_html__( 'Schedule expiration', 'gravityflow' ),
						'name' => 'expiration',
					),
				),
			);

			$expiration_type = array(
				'name' => 'expiration_type',
				'type' => 'radio',
				'horizontal' => true,
				'default_value' => 'delay',
				'choices' => array(
					array(
						'label' => esc_html__( 'Delay', 'gravityflow' ),
						'value' => 'delay',
					),
					array(
						'label' => esc_html__( 'Date', 'gravityflow' ),
						'value' => 'date',
					),
				),
			);

			$date_fields = GFFormsModel::get_fields_by_type( $form, 'date' );

			$date_field_choices = array();

			if ( ! empty( $date_fields ) ) {
				$expiration_type['choices'][] = array(
					'label' => esc_html__( 'Date Field', 'gravityflow' ),
					'value' => 'date_field',
				);


				foreach ( $date_fields  as $date_field ) {
					$date_field_choices[] = array( 'value' => $date_field->id, 'label' => GFFormsModel::get_label( $date_field ) );
				}
			}

			$expiration_date_fields = array(
				'name' => 'expiration_date_field',
				'label' => esc_html__( 'Expiration Date Field', 'gravityflow' ),
				'choices' => $date_field_choices,
			);

			$expiration_date = array(
				'id' => 'expiration_date',
				'name' => 'expiration_date',
				'placeholder' => 'yyyy-mm-dd',
				'class' => 'datepicker datepicker_with_icon ymd_dash',
				'label' => esc_html__( 'Expiration', 'gravityflow' ),
				'type' => 'text',
			);

			$delay_offset_field = array(
				'name' => 'expiration_delay_offset',
				'class' => 'small-text',
				'label' => esc_html__( 'Expiration', 'gravityflow' ),
				'type' => 'text',
			);

			$unit_field = array(
				'name' => 'expiration_delay_unit',
				'label' => esc_html__( 'Expiration', 'gravityflow' ),
				'default_value' => 'hours',
				'choices' => array(
					array(
						'label' => esc_html__( 'Minute(s)', 'gravityflow' ),
						'value' => 'minutes',
					),
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
				),
			);

			$this->settings_checkbox( $expiration );

			$enabled = $this->get_setting( 'expiration', false );
			$expiration_type_setting = $this->get_setting( 'expiration_type', 'delay' );
			$expiration_style = $enabled ? '' : 'style="display:none;"';
			$expiration_date_style = ( $expiration_type_setting == 'date' ) ? '' : 'style="display:none;"';
			$expiration_delay_style = ( $expiration_type_setting == 'delay' ) ? '' : 'style="display:none;"';
			$expiration_date_fields_style = ( $expiration_type_setting == 'date_field' ) ? '' : 'style="display:none;"';

			?>
			<div class="gravityflow-expiration-settings" <?php echo $expiration_style ?> >
				<div class="gravityflow-expiration-type-container" class="gravityflow-sub-setting">
					<?php $this->settings_radio( $expiration_type ); ?>
				</div>
				<div class="gravityflow-expiration-date-container" <?php echo $expiration_date_style ?> >
					<?php
					esc_html_e( 'This step expires on', 'gravityflow' );
					echo '&nbsp;';
					$this->settings_text( $expiration_date );
					?>
					<input type="hidden" id="gforms_calendar_icon_expiration_date" class="gform_hidden" value="<?php echo GFCommon::get_base_url() . '/images/calendar.png'; ?>" />
				</div>
				<div class="gravityflow-expiration-delay-container" <?php echo $expiration_delay_style ?> class="gravityflow-sub-setting">
					<?php
					esc_html_e( 'This step will expire', 'gravityflow' );
					echo '&nbsp;';
					$this->settings_text( $delay_offset_field );
					$this->settings_select( $unit_field );
					echo '&nbsp;';
					esc_html_e( 'after the workflow step has started.' );
					?>
				</div>
				<div class="gravityflow-expiration-date-field-container" <?php echo $expiration_date_fields_style ?>>
					<?php
					esc_html_e( 'Expire this step', 'gravityflow' );
					echo '&nbsp;';
					$delay_offset_field['name'] = 'expiration_date_field_offset';
					$delay_offset_field['default_value'] = '0';
					$this->settings_text( $delay_offset_field );
					$unit_field['name'] = 'expiration_date_field_offset_unit';
					$this->settings_select( $unit_field );
					echo '&nbsp;';
					$before_after_field = array(
						'name' => 'expiration_date_field_before_after',
						'label' => esc_html__( 'Expiration', 'gravityflow' ),
						'default_value' => 'after',
						'choices' => array(
							array(
								'label' => esc_html__( 'after', 'gravityflow' ),
								'value' => 'after',
							),
							array(
								'label' => esc_html__( 'before', 'gravityflow' ),
								'value' => 'before',
							),
						),
					);
					$this->settings_select( $before_after_field );

					$this->settings_select( $expiration_date_fields );
					?>
				</div>
				<div class="gravityflow-sub-setting">
					<?php
					$status_choices = rgar( $field, 'status_choices' );
					if ( is_array( $status_choices ) && ! empty( $status_choices ) ) {
						esc_html_e( 'Status after expiration', 'gravityflow' );
						echo ': ';
						$status_choices_field = array(
							'name' => 'status_expiration',
							'label' => esc_html__( 'Expiration Status', 'gravityflow' ),
							'type' => 'select',
							'choices' => $status_choices,
						);
						$this->settings_select( $status_choices_field );
					}
					?>
				</div>
				<div id="expiration_sub_setting_destination_expired" class="gravityflow-sub-setting">
					<?php
					esc_html_e( 'Next Step if Expired', 'gravityflow' );
					echo ': ';
					$next_step_field = array(
						'name'          => 'destination_expired',
						'label'         => esc_html__( 'Next Step if Expired', 'gravityflow' ),
						'type'          => 'step_selector',
						'default_value' => 'next',
					);
					$this->settings_step_selector( $next_step_field );
					?>
				</div>
			</div>
			<script>
				(function($) {
					$( '#expiration' ).click(function(){
						$('.gravityflow-expiration-settings').slideToggle();
					});
					$( '#expiration_type0' ).click(function(){
						$('.gravityflow-expiration-date-container').hide();
						$('.gravityflow-expiration-delay-container').show();
						$('.gravityflow-expiration-date-field-container').hide();
					});
					$( '#expiration_type1' ).click(function(){
						$('.gravityflow-expiration-date-container').show();
						$('.gravityflow-expiration-delay-container').hide();
						$('.gravityflow-expiration-date-field-container').hide();
					});
					$( '#expiration_type2' ).click(function(){
						$('.gravityflow-expiration-delay-container').hide();
						$('.gravityflow-expiration-date-container').hide();
						$('.gravityflow-expiration-date-field-container').show();
					});
				})(jQuery);
			</script>
			<?php
		}

		/**
		 * Prepare and render markup for the step_highlight composite setting.
		 *
		 * The container will be displayed or hidden depending on the value of the step_highlight checkbox field.
		 *
		 * @since 1.9.2
		 *
		 * @param array $field
		 */
		public function settings_step_highlight( $field ) {
			$field = $this->prepare_settings_step_highlight( $field );

			return $this->settings_step_highlight_container( $field );
		}

		/**
		* Prepare the step_highlight composite settings to be accessible for every field in the composite
		*
		* @since 1.9.2
		*
		* @param array $field The step_highlight field
		*
		* @return array
		*/
		public function prepare_settings_step_highlight( $field ) {
			unset( $field['settings'] );

			$step_highlight = array(
				'name'     => 'step_highlight',
				'type'     => 'checkbox',
				'choices'  => array(
					array(
						'label'         => esc_html__( 'Highlight this step', 'gravityflow' ),
						'name'          => 'step_highlight',
					),
				),
			);
			$field['settings']['step_highlight'] = $step_highlight;

			$step_highlight_type = array(
				'name'           => 'step_highlight_type',
				'type'           => 'hidden',
				'default_value'  => 'color',
				'required'       => true,
			);
			$field['settings']['step_highlight_type'] = $step_highlight_type;

			$step_highlight_color = array(
				'name'                => 'step_highlight_color',
				'id'                  => 'step_highlight_color',
				'class'               => 'small-text',
				'label'               => esc_html__( 'Color', 'gravityflow' ),
				'type'                => 'text',
				'default_value'       => '#dd3333',
			);
			$field['settings']['step_highlight_color'] = $step_highlight_color;

			return $field;
		}

		/**
		* Generate the step_highlight composite setting container
		*
		* The container will be displayed or hidden depending on the value of the step_highlight checkbox field.
		*
		* @since 1.9.2
		*
		* @param array $field The step_highlight field
		*/
		public function settings_step_highlight_container( $field ) {
			$form = $this->get_current_form();
			$step_settings = rgar( $field, 'settings' );

			if ( empty( $step_settings ) ) {
				return '';
			}

			$this->settings_checkbox( $step_settings['step_highlight'] );

			$enabled = $this->get_setting( 'step_highlight', false );
			$step_highlight_style = $enabled ? '' : 'style="display:none;"';
			$step_highlight_type_setting = $this->get_setting( 'step_highlight_type', 'color' );
			$step_highlight_color_style = ( $step_highlight_type_setting == 'color' ) ? '' : 'style="display:none;"';
			?>
			<div class="gravityflow-step-highlight-settings" <?php echo $step_highlight_style; ?> >
				<div class="gravityflow-step-highlight-type-container">
					<?php $this->settings_hidden( $step_settings['step_highlight_type'] ); ?>
				</div>
				<div class="gravityflow-step-highlight-color-container" <?php echo $step_highlight_color_style; ?> >
					<?php
					$this->settings_text( $step_settings['step_highlight_color'] );
					?>
				</div>
			</div>
			<script>
				(function($) {
					$( '#step_highlight' ).click(function(){
						$('.gravityflow-step-highlight-settings').slideToggle();
					});
					$(document).ready(function () {
						$("#step_highlight_color").wpColorPicker();
					});
				})(jQuery);
			</script>
			<?php

			return;
		}

		public function settings_tabs( $tabs_field ) {
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

		/**
		 * Renders or returns markup for a checkbox plus container composite setting.
		 *
		 * The container will be displayed or hidden depending on the value of the checkbox field.
		 *
		 * @since 1.5.1
		 *
		 * @param array $field
		 * @param bool  $echo
		 *
		 * @return string
		 */
		public function settings_checkbox_and_container( $field, $echo = true ) {
			$checkbox_field = rgar( $field, 'checkbox' );

			if ( empty( $checkbox_field ) ) {
				return '';
			}

			$checkbox_defaults = array(
				'type'       => 'checkbox',
				'name'       => $field['name'] . 'Enable',
				'label'      => esc_html__( 'Enable', 'gravityflow' ),
				'horizontal' => true,
				'value'      => '1',
				'choices'    => false,
				'tooltip'    => false,
			);

			$checkbox_field = wp_parse_args( $checkbox_field, $checkbox_defaults );

			if ( empty( $checkbox_field['choices'] ) ) {
				$checkbox_field['choices'] = array(
					array(
						'name'          => $checkbox_field['name'],
						'label'         => $checkbox_field['label'],
						'onchange'      => sprintf( "( function( $, elem ) {
								$( elem ).parents( 'td' ).css( 'position', 'relative' );
								if( $( elem ).prop( 'checked' ) ) {
									$( '%1\$s' ).fadeIn();
								} else {
									$( '%1\$s' ).fadeOut();
								}
							} )( jQuery, this );",
							"#{$field['name']}Container" ),
					),
				);
			}

			$field['checkbox'] = $checkbox_field;

			$checkbox_field = rgar( $field, 'checkbox' );

			$is_enabled = $this->get_setting( $checkbox_field['name'] );

			$container_settings_markup = '';

			if ( isset( $field['settings'] ) && is_array( $field['settings'] ) ) {
				foreach (  $field['settings'] as $setting ) {
					if ( ! isset( $setting['type'] ) ) {
						continue;
					}
					$method = 'settings_' . $setting['type'];
					if ( isset( $setting['before'] ) ) {
						$container_settings_markup .= rgar( $setting, 'before' );
						unset( $setting['before'] );
					}
					if ( isset( $setting['after'] ) ) {
						$after = rgar( $setting, 'after' );
						unset( $setting['after'] );
					} else {
						$after = '';
					}
					if ( method_exists( $this, $method ) ) {
						$container_settings_markup .= $this->{$method}( $setting, false );
					}
					$container_settings_markup .= isset( $setting['tooltip'] ) ? gform_tooltip( $setting['tooltip'], rgar( $setting, 'tooltip_class' ) . ' tooltip ' . $setting['name'], true ) : '';
					$container_settings_markup .= $after;
				}
			}

			$html = sprintf(
				'%s <div id="%s" class="%s">%s</div>',
				$this->settings_checkbox( $checkbox_field, false ),
				$field['name'] . 'Container',
				$is_enabled ? '' : 'hidden',
				$container_settings_markup
			);

			if ( $echo ) {
				echo $html;
			}

			return $html;
		}

		/**
		 * Renders or returns a composite setting with a checkbox and text field.
		 *
		 * The text field will be hidden or displayed depending on the value of the checkbox.
		 *
		 * @since 1.5.1 Updated to use Gravity_Flow::settings_checkbox_and_container()
		 * @since unknown
		 *
		 * @param array $field
		 * @param bool  $echo
		 *
		 * @return string
		 */
		public function settings_checkbox_and_text( $field, $echo = true ) {
			$text_input = rgars( $field, 'text' );

			$text_field = array(
				'name'    => $field['name'] . 'Value',
				'type'    => 'text',
				'class'   => '',
				'tooltip' => false,
			);

			$text_field['class'] .= ' ' . $text_field['name'];

			$text_field = wp_parse_args( $text_input, $text_field );

			unset( $field['textarea'] );

			$field['settings'] = array( $text_field );

			return $this->settings_checkbox_and_container( $field, $echo );
		}

		/**
		 * Renders or returns a composite setting with a checkbox and text field.
		 *
		 * The text field will be hidden or displayed depending on the value of the checkbox.
		 *
		 * @since 1.5.1 Updated to use Gravity_Flow::settings_checkbox_and_container()
		 * @since unknown
		 *
		 * @param array $field
		 * @param bool  $echo
		 *
		 * @return string
		 */
		public function settings_checkbox_and_textarea( $field, $echo = true ) {
			$field = $this->prepare_settings_checkbox_and_textarea( $field );

			return $this->settings_checkbox_and_container( $field, $echo );
		}

		public function prepare_settings_checkbox_and_textarea( $field ) {
			$textarea_input = rgars( $field, 'textarea' );

			$textarea_field = array(
				'name'    => $field['name'] . 'Value',
				'type'    => 'textarea',
				'class'   => '',
				'tooltip' => false,
			);

			$textarea_field['class'] .= ' ' . $textarea_field['name'];

			$textarea_field = wp_parse_args( $textarea_input, $textarea_field );

			unset( $field['textarea'] );

			$field['settings'] = array( 'textarea' => $textarea_field );

			return $field;
		}

		public function validate_checkbox_and_textarea_settings( $field, $settings ) {
			$field = $this->prepare_settings_checkbox_and_textarea( $field );

			$checkbox_field = $field['checkbox'];
			$textarea_field = $field['settings']['textarea'];

			$this->validate_checkbox_settings( $checkbox_field, $settings );
			$this->validate_textarea_settings( $textarea_field, $settings );
		}

		/**
		 * Validate step_highlight composite setting
		 *
		 * Validate the sub-settings are of appropriate type and required status
		 *
		 * @since 1.9.2
		 *
		 * @param array $field The field properties.
		 * @param array $settings The settings to be potentially saved.
		 */
		public function validate_step_highlight_settings( $field, $settings ) {
			$field = $this->prepare_settings_step_highlight( $field );

			$checkbox_field = $field['settings']['step_highlight'];
			$this->validate_checkbox_settings( $checkbox_field, $settings );

			$color_field = $field['settings']['step_highlight_color'];
			$this->validate_text_settings( $color_field, $settings );
			$this->validate_step_highlight_color_settings( $color_field, $settings );

		}

		/**
		 * Validate step_highlight_color is a hexadecimal code
		 *
		 * @since 1.9.2
		 *
		 * @param array $field The field properties.
		 * @param array $settings The settings to be potentially saved.
		 */
		public function validate_step_highlight_color_settings( $field, $settings ) {

			if( $settings['step_highlight'] && ! preg_match( '/^#[a-f0-9]{6}$/i', $settings['step_highlight_color'] ) ) {
				$this->set_field_error( $field, __( 'You must provide a color value for the active highlight to apply.', 'gravityflow' ) );
			}

		}

		public function settings_visual_editor( $field ) {

			$default_value = rgar( $field, 'value' ) ? rgar( $field, 'value' ) : rgar( $field, 'default_value' );
			$value         = $this->get_setting( $field['name'], $default_value );
			$id            = '_gaddon_setting_' . $field['name'];
			echo "<span class='mt-{$id}'></span>";
			wp_editor( $value, $id, array(
				'autop'        => false,
				'editor_class' => 'merge-tag-support mt-wp_editor mt-manual_position mt-position-right',
			) );
		}

		public function settings_routing() {
			echo '<div id="gform_routing_setting" class="gravityflow-routing" data-field_name="_gaddon_setting_routing" data-field_id="routing" ></div>';
			$field['name'] = 'routing';

			$this->settings_hidden( $field );
		}

		public function settings_user_routing( $field ) {
			$name = $field['name'];
			$id = isset( $field['id'] ) ?  $field['id'] : 'gform_user_routing_setting_' . $name;

			echo '<div class="gravityflow-user-routing" id="' . $id . '" data-field_name="_gaddon_setting_' . $name . 'user_routing" data-field_id="' . $name . '" ></div>';

			$this->settings_hidden( $field );
		}

		public function settings_step_selector( $field ) {
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

		public function settings_editable_fields( $field ) {
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
		public function feed_list_columns() {
			$columns = array(
				'step_name' => __( 'Step name', 'gravityflow' ),
				'step_highlight' => '',
				'step_type' => esc_html__( 'Step Type', 'gravityflow' ),
			);

			$count_entries = apply_filters( 'gravityflow_entry_count_step_list', true );
			if ( $count_entries ) {
				$columns['entry_count'] = esc_html__( 'Entries', 'gravityflow' );
			}
			return $columns;
		}

		public function get_column_value_step_type( $item ) {
			$step       = $this->get_step( $item['id'] );
			$step_label = empty( $step ) ? $item['meta']['step_type'] : $step->get_label();

			if ( empty( $step ) || ! $step->is_supported() ) {

				return '<span class="validation_error"><i class="fa fa-exclamation-triangle gf_invalid"></i> ' . $step_label . '  ' . esc_html__( '(missing)', 'gravityflow' ) . '</span>';
			}

			$icon_url  = $step->get_icon_url();
			$icon_html = ( strpos( $icon_url, 'http' ) === 0 ) ? sprintf( '<img src="%s" style="width:20px;height:20px;margin-right:5px;vertical-align:middle;"/>', $icon_url ) : sprintf( '<span style="width:20px;height:20px;margin-right:5px;vertical-align:middle;">%s</span>', $icon_url );

			return $icon_html . $step_label;
		}


		public function get_column_value_entry_count( $item ) {
			$count_entries = apply_filters( 'gravityflow_entry_count_step_list', true );
			if ( ! $count_entries ) {
				return '';
			}
			$form_id = rgget( 'id' );
			$form_id = absint( $form_id );
			$step = $this->get_step( $item['id'] );
			$step_id = $step ? $step->get_id() : 0;
			$count = $step ? $step->entry_count() : 0;
			$url = admin_url( 'admin.php?page=gf_entries&view=entries&id='. $form_id . '&field_id=workflow_step&operator=is&s=' . $step_id );
			$link = sprintf( '<a href="%s">%d</a>', $url, $count );
			return $link;
		}

		/**
		 * Return value of step_highlight composite setting for display on feed list
		 *
		 * @since 1.9.2
		 *
		 * @param array $item Current workflow step
		 *
		 * @return string
		 */
		public function get_column_value_step_highlight( $item ) {
			$step_highlight = '';

			if ( ! empty( $item['meta']['step_highlight'] ) ) {
				switch ( $item['meta']['step_highlight_type'] ) :

					case 'color':
						if ( preg_match( '/^#[a-f0-9]{6}$/i', $item['meta']['step_highlight_color'] ) ) {
							$step_highlight = '<div class="step_highlight step_highlight_color" style="background-color: ' . $item['meta']['step_highlight_color'] . ';">&nbsp;</div>';
						}
						break;

					case 'text':
						$step_highlight = '<div class="step_highlight step_highlight_text">' . $item['meta']['step_highlight_text'] . '</div>';
						break;

					case 'icon':
						$step_highlight = $item['meta']['step_highlight_icon'];
						break;

				endswitch;
			}

			return $step_highlight;
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
		public function get_entry_meta( $entry_meta, $form_id ) {
			$steps        = $this->get_steps( $form_id );
			$step_choices = $workflow_final_status_options = array();

			foreach ( $steps as $step ) {
				if (  empty( $step ) || ! $step->is_active() ) {
					continue;
				}

				$status_choices = array();
				$step_id        = $step->get_id();
				$step_name      = $step->get_name();
				$step_choices[] = array( 'value' => $step_id, 'text' => $step_name );

				$step_status_options = $step->get_status_config();
				foreach ( $step_status_options as $status_option ) {
					$status_choices[] = array(
						'value' => $status_option['status'],
						'text'  => $this->translate_status_label( $status_option['status'] ),
					);
				}

				$entry_meta = array_merge( $entry_meta, $step->get_entry_meta( $entry_meta, $form_id ) );

				$entry_meta[ 'workflow_step_status_' . $step_id ] = array(
					'label'             => __( 'Status:', 'gravityflow' ) . ' ' . $step_name,
					'is_numeric'        => false,
					'is_default_column' => false, // this column will not be displayed by default on the entry list
					'filter'            => array(
						'operators' => array( 'is', 'isnot' ),
						'choices'   => $status_choices,
					),
				);

				$workflow_final_status_options = array_merge( $workflow_final_status_options, $status_choices );

			}

			if ( ! empty( $steps ) ) {
				$workflow_final_status_options[] = array(
					'value' => 'pending',
					'text'  => $this->translate_status_label( 'pending' ),
				);

				$workflow_final_status_options[] = array(
					'value' => 'complete',
					'text'  => $this->translate_status_label( 'complete' ),
				);

				$workflow_final_status_options[] = array(
					'value' => 'cancelled',
					'text' => $this->translate_status_label( 'cancelled' ),
				);

				// Remove duplicates
				$workflow_final_status_options = array_map( 'unserialize', array_unique( array_map( 'serialize', $workflow_final_status_options ) ) );

				$workflow_final_status_options = array_values( $workflow_final_status_options );

				$entry_meta['workflow_final_status'] = array(
					'label'                      => 'Final Status',
					'is_numeric'                 => false,
					'update_entry_meta_callback' => array( $this, 'callback_update_entry_meta_workflow_final_status' ),
					'is_default_column'          => true, // this column will be displayed by default on the entry list
					'filter'                     => array(
						'operators' => array( 'is', 'isnot' ),
						'choices'   => $workflow_final_status_options,
					),
				);

				$entry_meta['workflow_step'] = array(
					'label'                      => 'Workflow Step',
					'is_numeric'                 => false,
					'update_entry_meta_callback' => array( $this, 'callback_update_entry_meta_workflow_step' ),
					'is_default_column'          => true, // this column will be displayed by default on the entry list
					'filter'                     => array(
						'operators' => array( 'is', 'isnot' ),
						'choices'   => $step_choices,
					),
				);

				$entry_meta['workflow_timestamp'] = array(
					'label'                      => 'Timestamp',
					'is_numeric'                 => true,
					'update_entry_meta_callback' => array( $this, 'callback_update_entry_meta_timestamp' ),
					'is_default_column'          => false, // this column will not be displayed by default on the entry list
				);
			}

			return $entry_meta;
		}

		/**
		 * The target of callback_update_entry_meta_workflow_step.
		 *
		 * @param string $key The entry meta key
		 * @param array $entry The Entry Object
		 * @param array $form The Form Object
		 *
		 * @return string|void
		 */
		public function callback_update_entry_meta_workflow_step( $key, $entry, $form ) {

			if ( ! isset( $entry['id'] ) ) {
				return;
			}

			if ( isset( $entry['workflow_final_status'] ) && $entry['workflow_final_status'] != 'pending' && isset( $entry['workflow_step'] ) ) {
				return $entry['workflow_step'];
			}

			if ( isset( $entry['workflow_step'] )  && $entry[ $key ] !== false ) {
				return $entry['workflow_step'];
			} else {
				return 0;
			}
		}

		/**
		 * The target of callback_update_entry_meta_workflow_current_status.
		 *
		 * @param string $key The entry meta key
		 * @param array $entry The Entry Object
		 * @param array $form The Form Object
		 *
		 * @return string|void
		 */
		public function callback_update_entry_meta_workflow_current_status( $key, $entry, $form ) {

			if ( ! isset( $entry['id'] ) ) {
				return;
			}

			if ( isset( $entry['workflow_current_status'] ) && $entry['workflow_current_status'] != 'pending' && $entry[ $key ] !== false ) {
				return $entry['workflow_current_status'];
			} else {
				return 'pending';
			}
		}

		/**
		 * The target of callback_update_entry_meta_workflow_final_status.
		 *
		 * @param string $key The entry meta key
		 * @param array $entry The Entry Object
		 * @param array $form The Form Object
		 *
		 * @return string|void
		 */
		public function callback_update_entry_meta_workflow_final_status( $key, $entry, $form ) {

			if ( ! isset( $entry['id'] ) ) {
				return;
			}

			if ( isset( $entry['workflow_final_status'] ) && $entry['workflow_final_status'] != 'pending' && $entry[ $key ] !== false ) {
				return $entry['workflow_final_status'];
			} else {
				return 'pending';
			}
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
		public function callback_update_entry_meta_timestamp( $key, $entry, $form ) {
			if ( ! isset( $entry['id'] ) ) {
				return;
			}
			return ! isset( $entry['workflow_timestamp'] ) ? strtotime( $entry['date_created'] ) : time();
		}


		public function workflow_entry_detail_status_box( $form, $entry, $current_step = null, $args = array() ) {

			if ( is_null( $current_step ) ) {
				$current_step = $this->get_current_step( $form, $entry );
			}

			$display_workflow_info = (bool) $args['workflow_info'];

			?>
			<div id="gravityflow-status-box-container" class="postbox">

				<h3 class="hndle" style="cursor:default;">
					<span><?php if ( $display_workflow_info ) { echo esc_html( $this->translate_navigation_label( 'workflow' ) ); } ?></span>
				</h3>

				<div id="submitcomment" class="submitbox">
					<div id="minor-publishing" class="gravityflow-status-box">
						<?php

						$this->maybe_display_entry_detail_workflow_info( $current_step, $form, $entry, $args );
						$this->maybe_display_entry_detail_step_status( $current_step, $form, $entry, $args );

						?>
					</div>

				</div>

			</div>

			<?php

			do_action( 'gravityflow_workflow_detail_sidebar', $form, $entry, $current_step, $args );

			$this->maybe_display_entry_detail_admin_actions( $current_step, $form, $entry );
		}

		/**
		 * Displays the workflow info on the entry detail page, if enabled.
		 *
		 * @param Gravity_Flow_Step $current_step The current step for this entry.
		 * @param array $form The form which created this entry.
		 * @param array $entry The entry currently being displayed.
		 * @param array $args The properties for the page currently being displayed.
		 */
		public function maybe_display_entry_detail_workflow_info( $current_step, $form, $entry, $args ) {
			$display_workflow_info = (bool) $args['workflow_info'];

			if ( ! $display_workflow_info ) {
				return;
			}

			$entry_id      = absint( $entry['id'] );
			$entry_id_link = $entry_id;

			if ( GFAPI::current_user_can_any( 'gravityforms_view_entries' ) ) {
				$entry_id_link = '<a href="' . admin_url( 'admin.php?page=gf_entries&view=entry&id=' . absint( $form['id'] ) . '&lid=' . absint( $entry['id'] ) ) . '">' . $entry_id . '</a>';
			}

			printf( '%s: %s<br/><br/>%s: %s', esc_html__( 'Entry ID', 'gravityflow' ), $entry_id_link, esc_html__( 'Submitted', 'gravityflow' ), esc_html( GFCommon::format_date( $entry['date_created'], true, 'Y/m/d' ) ) );

			if ( ! empty( $entry['workflow_timestamp'] ) ) {
				$last_updated = date( 'Y-m-d H:i:s', $entry['workflow_timestamp'] );
				if ( $entry['date_created'] != $last_updated ) {
					echo '<br /><br />';
					esc_html_e( 'Last updated', 'gravityflow' ); ?>: <?php echo esc_html( GFCommon::format_date( $last_updated, true, 'Y/m/d' ) );
				}
			}

			echo '<br/><br/>';

			if ( ! empty( $entry['created_by'] ) && $usermeta = get_userdata( $entry['created_by'] ) ) {
				printf( '%s: %s<br/><br/>', esc_html__( 'Submitted by', 'gravityflow' ), esc_html( $usermeta->display_name ) );
			}

			$workflow_status = gform_get_meta( $entry['id'], 'workflow_final_status' );

			if ( ! empty( $workflow_status ) ) {
				$workflow_status_label = $this->translate_status_label( $workflow_status );
				printf( '%s: %s', esc_html__( 'Status', 'gravityflow' ), $workflow_status_label );
			}

			if ( false !== $current_step && $current_step instanceof Gravity_Flow_Step
			     && $current_step->supports_expiration() && $current_step->expiration
			) {
				$expiration_timestamp = $current_step->get_expiration_timestamp();
				$expiration_date_str  = date( 'Y-m-d H:i:s', $expiration_timestamp );
				$expiration_date      = get_date_from_gmt( $expiration_date_str );
				printf( '<br /><br />%s: %s', esc_html__( 'Expires', 'gravityflow' ), $expiration_date );
			}

			/**
			 * Allows content to be added in the workflow box below the workflow status info.
			 *
			 * @param array $form The form which created this entry.
			 * @param array $entry The entry currently being displayed.
			 * @param Gravity_Flow_Step $current_step The current step for this entry.
			 */
			do_action( 'gravityflow_below_workflow_info_entry_detail', $form, $entry, $current_step );
		}

		/**
		 * Displays the step status on the entry detail page.
		 *
		 * @param Gravity_Flow_Step $current_step The current step for this entry.
		 * @param array $form The form which created this entry.
		 * @param array $entry The entry currently being displayed.
		 * @param array $args The properties for the page currently being displayed.
		 */
		public function maybe_display_entry_detail_step_status( $current_step, $form, $entry, $args ) {
			if ( false !== $current_step && $current_step instanceof Gravity_Flow_Step ) {
				$display_workflow_info = (bool) $args['workflow_info'];

				if ( $display_workflow_info ) {
					echo '<hr style="margin-top:10px;"/>';
				}

				if ( $current_step->is_queued() ) {
					$this->display_queued_step_details( $current_step );
				} elseif ( $current_step->is_expired() ) {
					$entry_id = absint( $entry['id'] );
					$this->display_expired_step_details( $current_step, $form, $entry_id );
				} else {
					$current_step->workflow_detail_box( $form, $args );
				}
			}
		}

		/**
		 * Display the details for the queued step.
		 *
		 * @param Gravity_Flow_Step $current_step The current step for this entry.
		 */
		public function display_queued_step_details( $current_step ) {
			printf( '<h4>%s (%s)</h4>', $current_step->get_name(), esc_html__( 'Queued', 'gravityflow' ) );

			$scheduled_timestamp = $current_step->get_schedule_timestamp();

			switch ( $current_step->schedule_type ) {
				case 'date' :
					$scheduled_date = $current_step->schedule_date;
					break;
				case 'date_field' :
					$scheduled_date_str = date( 'Y-m-d H:i:s', $scheduled_timestamp );
					$scheduled_date     = get_date_from_gmt( $scheduled_date_str );
					break;
				case 'delay' :
				default:
					$scheduled_date_str = date( 'Y-m-d H:i:s', $scheduled_timestamp );
					$scheduled_date     = get_date_from_gmt( $scheduled_date_str );
			}

			printf( '<h4>%s: %s</h4>', esc_html__( 'Scheduled', 'gravityflow' ), $scheduled_date );
		}

		/**
		 * Display the details for the expired step.
		 *
		 * @param Gravity_Flow_Step $current_step The current step for this entry.
		 * @param array $form The form which created this entry.
		 * @param integer $entry_id The ID of the current entry.
		 */
		public function display_expired_step_details( $current_step, $form, $entry_id ) {
			$current_step->log_event( esc_html__( 'Step expired', 'gravityflow' ) );
			$note = esc_html__( 'Step expired', 'gravityflow' ) . ': ' . $current_step->get_name();
			$current_step->add_note( $note );
			$this->process_workflow( $form, $entry_id );
			$current_step = null;
			printf( '<h4>%s</h4>', esc_html__( 'Expired: refresh the page', 'gravityflow' ) );
		}

		/**
		 * Displays the admin actions drop down on the entry detail page, if applicable.
		 *
		 * @param Gravity_Flow_Step $current_step The current step for this entry.
		 * @param array $form The form which created this entry.
		 * @param array $entry The entry currently being displayed.
		 */
		public function maybe_display_entry_detail_admin_actions( $current_step, $form, $entry ) {
			$steps = $this->get_steps( $form['id'] );

			if ( GFAPI::current_user_can_any( 'gravityflow_workflow_detail_admin_actions' ) && ! empty( $steps ) ) {
				?>
				<div class="postbox">
					<h3 class="hndle" style="cursor:default;">
						<span><?php esc_html_e( 'Admin', 'gravityflow' ); ?></span>
					</h3>

					<div id="submitcomment" class="submitbox">
						<div id="minor-publishing" style="padding:10px;">
							<?php wp_nonce_field( 'gravityflow_admin_action', '_gravityflow_admin_action_nonce' ); ?>
							<select id="gravityflow-admin-action" name="gravityflow_admin_action">
								<option value=""><?php esc_html_e( 'Select an action', 'gravityflow' ) ?></option>
								<?php echo $this->get_admin_action_select_options( $current_step, $steps, $form, $entry ); ?>
							</select>
							<input type="submit" class="button " name="_gravityflow_admin_action" value="<?php esc_html_e( 'Apply', 'gravityflow' ) ?>"/>

						</div>
					</div>
				</div>
				<?php
			}
		}

		/**
		 * Prepares a string containing the HTML options and optgroups for the admin actions drop down.
		 *
		 * @param bool|Gravity_Flow_Step $current_step The current step.
		 * @param Gravity_Flow_Step[] $steps The steps for this form.
		 * @param array $form The current form.
		 * @param array $entry The current entry,
		 *
		 * @return string
		 */
		public function get_admin_action_select_options( $current_step, $steps, $form, $entry ) {

			if ( $current_step ) {
				$admin_actions = array(
					array(
						'label' => esc_html__( 'Cancel Workflow', 'gravityflow' ),
						'value' => 'cancel_workflow',
					),
					array(
						'label' => esc_html__( 'Restart this step', 'gravityflow' ),
						'value' => 'restart_step',
					),
				);
			} else {
				$admin_actions = array();
			}

			$admin_actions[] = array(
				'label' => esc_html__( 'Restart Workflow', 'gravityflow' ),
				'value' => 'restart_workflow',
			);

			if ( count( $steps ) > 1 ) {
				$choices = array();
				foreach ( $steps as $step ) {
					if ( ! $step->is_active() ) {
						continue;
					}
					$step_id = $step->get_id();
					if ( ! $current_step || ( $current_step && $current_step->get_id() != $step_id ) ) {
						$choices[] = array(
							'label' => $step->get_name(),
							'value' => 'send_to_step|' . $step->get_id()
						);
					}
				}

				if ( ! empty( $choices ) ) {
					$admin_actions[] = array(
						'label'   => esc_html__( 'Send to step:', 'gravityflow' ),
						'choices' => $choices,
					);
				}
			}

			/**
			 * Filter the choices which appear in the admin actions drop down.
			 *
			 * @param array $admin_actions Contains the properties for the options and optgroups.
			 * @param bool|Gravity_Flow_Step $current_step The current step.
			 * @param Gravity_Flow_Step[] $steps The steps for this form.
			 * @param array $form The current form.
			 * @param array $entry The current entry,
			 */
			$admin_actions = apply_filters( 'gravityflow_admin_actions_workflow_detail', $admin_actions, $current_step, $steps, $form, $entry );

			return $this->get_select_options( $admin_actions, '' );
		}

		public function entry_detail_status_box( $form, $entry ) {

			if ( ! isset( $entry['workflow_final_status'] ) ) {
				return;
			}

			$current_step = $this->get_current_step( $form, $entry );

			?>
			<div class="postbox">
				<h3><?php echo esc_html( $this->translate_navigation_label( 'workflow' ) ); ?></h3>
				<?php
				if ( $current_step == false ) {
					?>
					<h4 style="padding:10px;"><?php esc_html_e( 'Workflow complete', 'gravityflow' ); ?></h4>

					<?php

				} else {

					$current_step->entry_detail_status_box( $form );
				}
				?>
				<div style="padding:10px;">
					<a href="<?php echo admin_url( 'admin.php?page=gravityflow-inbox&view=entry&id=' . absint( $form['id'] ) . '&lid=' . absint( $entry['id'] ) ); ?>" ><?php esc_html_e( 'View' ); ?></a>
				</div>

			</div>
			<?php
		}

		public function get_current_step( $form, $entry ) {

			if ( ! isset( $entry['workflow_step'] ) ) {
				return false;
			}

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
		public function get_next_step( $step, $entry, $form ) {
			$keep_looking = true;
			$form_id = absint( $form['id'] );
			$steps = $this->get_steps( $form_id, $entry );
			while ( $keep_looking && $step ) {

				if ( ! $step instanceof Gravity_Flow_Step ) {
					return false;
				}

				$next_step_id = $step->get_next_step_id();

				if ( $next_step_id == 'complete' ) {
					return false;
				}

				if ( $next_step_id == 'next' ) {
					$step = $this->get_next_step_in_list( $form, $step, $entry, $steps );
					$keep_looking = false;
				} else {
					$step = $this->get_step( $next_step_id, $entry );

					if ( empty( $step ) ) {
						$keep_looking = false;
					} elseif ( ! $step->is_active() || ! $step->is_condition_met( $form ) ) {
						$step = $this->get_next_step_in_list( $form, $step, $entry, $steps );
						if ( ! empty( $step ) ) {
							$keep_looking = false;
						}
					} else {
						$keep_looking = false;
					}
				}
			}
			return $step;
		}

		public function get_step( $step_id, $entry = null ) {

			$feed = $this->get_feed( $step_id );
			if ( ! $feed ) {
				return false;
			}

			$step = Gravity_Flow_Steps::create( $feed, $entry );

			return $step;
		}

		/**
		 * Returns the next step in the list. FALSE if there isn't a next step.
		 *
		 * @param array $form
		 * @param Gravity_Flow_Step $current_step
		 * @param array $entry
		 * @param array $steps
		 *
		 * @return bool|Gravity_Flow_Step
		 */
		public function get_next_step_in_list( $form, $current_step, $entry, $steps = array() ) {
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

		public function get_app_menu_items() {
			$menu_items = array();

			$inbox_item = array(
				'name' => 'gravityflow-inbox',
				'label' => esc_html( $this->translate_navigation_label( 'inbox' ) ),
				'permission' => 'gravityflow_inbox',
				'callback' => array( $this, 'inbox' ),
			);
			$menu_items[] = $inbox_item;

			$form_ids = $this->get_published_form_ids();

			if ( ! empty( $form_ids ) ) {
				$menu_item = array(
					'name' => 'gravityflow-submit',
					'label' => esc_html( $this->translate_navigation_label( 'submit' ) ),
					'permission' => 'gravityflow_submit',
					'callback' => array( $this, 'submit' ),
				);
				$menu_items[] = $menu_item;
			}

			$status_item = array(
				'name' => 'gravityflow-status',
				'label' => esc_html( $this->translate_navigation_label( 'status' ) ),
				'permission' => 'gravityflow_status',
				'callback' => array( $this, 'status' ),
			);
			$menu_items[] = $status_item;

			$support_item = array(
				'name' => 'gravityflow-support',
				'label' => esc_html( $this->translate_navigation_label( 'support' ) ),
				'permission' => 'gform_full_access',
				'callback' => array( $this, 'support' ),
			);
			$menu_items[] = $support_item;

			$reports_item = array(
				'name' => 'gravityflow-reports',
				'label' => esc_html( $this->translate_navigation_label( 'reports' ) ),
				'permission' => 'gravityflow_reports',
				'callback' => array( $this, 'reports' )
			);
			$menu_items[] = $reports_item;

			$activity_item = array(
				'name' => 'gravityflow-activity',
				'label' => esc_html( $this->translate_navigation_label( 'activity' ) ),
				'permission' => 'gravityflow_activity',
				'callback' => array( $this, 'activity' ),
			);
			$menu_items[] = $activity_item;

			$menu_items = apply_filters( 'gravityflow_menu_items', $menu_items );

			return $menu_items;
		}

		public function get_app_settings_tabs() {

			//build left side options, always have app Settings first and Uninstall last, put extensions in the middle

			$setting_tabs = array(
				array(
					'name' => 'settings',
					'label' => esc_html__( 'General', 'gravityflow' ),
					'title' => esc_html__( 'Gravity Flow Settings', 'gravityflow' ),
					'callback' => array( $this, 'app_settings_tab' ),
				),
				array(
					'name' => 'labels',
					'label' => __( 'Labels', 'gravityflow' ),
					'callback' => array( $this, 'app_settings_label_tab' ),
				),
				array(
					'name' => 'connected_apps',
					'label' => __( 'Connected Apps', 'gravityflow' ),
					'callback' => array( $this, 'app_settings_connected_apps_tab' ),
				),
				/*
				array(
					'name' => 'tools',
					'label' => __( 'Tools', 'gravityflow' ),
					'callback' => array( $this, 'app_tools_tab' )
				),
				*/
			);

			$setting_tabs = apply_filters( 'gravityflow_settings_menu_tabs', $setting_tabs );

			if ( $this->current_user_can_any( $this->_capabilities_uninstall ) ) {
				$setting_tabs[] = array( 'name' => 'uninstall', 'label' => __( 'Uninstall', 'gravityflow' ), 'callback' => array( $this, 'app_settings_uninstall_tab' ) );
			}

			ksort( $setting_tabs, SORT_NUMERIC );

			return $setting_tabs;
		}

		public function get_app_menu_icon() {
			$admin_icon = $this->get_admin_icon_b64();
			return $admin_icon;
		}


		/**
		 * Stores an array containing the status and navigation labels in the gravityflow_app_settings_labels option when the settings are saved.
		 */
		public function maybe_update_app_settings_labels() {
			if ( isset( $_POST['gravityflow-labels-update'] ) ) {
				check_admin_referer( 'gravityflow_app_settings_labels' );
				$labels = array(
					'status'     => rgpost( 'status_labels' ),
					'navigation' => rgpost( 'navigation_labels' ),
				);
				update_option( 'gravityflow_app_settings_labels', $labels );
			}
		}

		/**
		 * Prepares a string containing the markup for the navigation label fields.
		 *
		 * @param array $labels The navigation and status labels.
		 *
		 * @return string
		 */
		public function get_navigation_labels_fields( $labels ) {
			$default_navigation_labels = $this->get_default_navigation_labels();
			$custom_navigation_labels  = isset( $labels['navigation'] ) ? $labels['navigation'] : array();
			$navigation_labels         = array_merge( $default_navigation_labels, $custom_navigation_labels );
			$fields                    = array();

			foreach ( $navigation_labels as $navigation_label_key => $navigation_label ) {
				if ( isset( $default_navigation_labels[ $navigation_label_key ] ) ) {
					$default_navigation_label = $default_navigation_labels[ $navigation_label_key ];
					$fields[]                 = sprintf( '<tr><th><label for="navigation_label_%s">%s</label></th><td><input id="navigation_label_%s" type="text" name="navigation_labels[%s]" value="%s" /></td></tr>', $navigation_label_key, $default_navigation_label, $navigation_label_key, $navigation_label_key, rgar( $custom_navigation_labels, $navigation_label_key ) );
				}
			}

			return join( "\n", $fields );
		}

		/**
		 * Prepares a string containing the markup for the status label fields.
		 *
		 * @param array $labels The navigation and status labels.
		 *
		 * @return string
		 */
		public function get_status_labels_fields( $labels ) {
			$default_status_labels = array(
				'pending'   => esc_html__( 'Pending', 'gravityflow' ),
				'cancelled' => esc_html__( 'Cancelled', 'gravityflow' )
			);
			$custom_status_labels  = isset( $labels['status'] ) ? $labels['status'] : array();
			$steps                 = Gravity_Flow_Steps::get_all();

			foreach ( $steps as $step ) {
				$status_configs = $step->get_status_config();
				foreach ( $status_configs as $status_config ) {
					$default_status_labels[ $status_config['status'] ] = $status_config['status_label'];
				}
			}

			$status_labels = array_merge( $default_status_labels, $custom_status_labels );
			$fields        = array();

			foreach ( $status_labels as $status_label_key => $status_label ) {
				$default_status_label = $default_status_labels[ $status_label_key ];
				$fields[]             = sprintf( '<tr><th><label for="status_label_%s">%s</label></th><td><input id="status_label_%s" type="text" name="status_labels[%s]" value="%s" /></td></tr>', $status_label_key, $default_status_label, $status_label_key, $status_label_key, rgar( $custom_status_labels, $status_label_key ) );
			}

			return join( "\n", $fields );
		}

		/**
		 * Render the content for the app Settings > Labels tab.
		 */
		public function app_settings_label_tab() {
			$this->maybe_update_app_settings_labels();

			$labels = get_option( 'gravityflow_app_settings_labels', array() );

			?>

			<h3><span><i class="fa fa-cogs"></i> <?php esc_html_e( 'Labels', 'gravityflow' ); ?></span></h3>

			<form  id="gform-settings" method="POST" action="">
				<?php wp_nonce_field( 'gravityflow_app_settings_labels' ); ?>
				<div class="gaddon-section gaddon-first-section">
					<h4 class="gaddon-section-title gf_settings_subgroup_title"> <?php echo esc_html__( 'Navigation', 'gravityflow' ); ?> </h4>
					<?php

					printf( '<table id="gravityflow-settings-labels-navigation" class="gravityflow-settings-labels">%s</table>', $this->get_navigation_labels_fields( $labels ) );

					?>
				</div>
				<div class="gaddon-section">
					<h4 class="gaddon-section-title gf_settings_subgroup_title"> <?php echo esc_html__( 'Status Labels', 'gravityflow' ); ?> </h4>
					<?php

					printf( '<table id="gravityflow-settings-labels-status" class="gravityflow-settings-labels">%s</table>', $this->get_status_labels_fields( $labels ) );

					?>
				</div>
				<?php echo get_submit_button( esc_html__( 'Update', 'gravityflow' ), 'primary large', 'gravityflow-labels-update', false ); ?>
			</form>

			<?php
		}

		/**
		 * Render the content for the app Settings > Connected Apps tab.
		 */
		public function app_settings_connected_apps_tab() {
			gravityflow_connected_apps()->settings_tab();
		}

		public function app_tools_tab() {
			$message = '';
			$success = null;

			if ( isset( $_POST['_revoke_token'] ) && check_admin_referer( 'gflow_revoke_token' ) ) {
				$token_str = sanitize_text_field( $_POST['gflow_token'] );
				$token = $this->decode_access_token( $token_str, false );
				if ( empty( $token ) ) {
					$message = __( 'Invalid token', 'gravityflow' );
					$success = false;
				}
				if ( ! empty( $token ) && $token['exp'] < time() ) {
					$message = __( 'Token already expired', 'gravityflow' );
					$success = false;
				}
				if ( is_null( $success ) ) {
					$revoked_tokens = get_option( 'gravityflow_revoked_tokens', array() );
					$revoked_tokens[ $token['jti'] ] = $token['exp'];
					update_option( 'gravityflow_revoked_tokens', $revoked_tokens );
					$success = true;
					$message = __( 'Token revoked', 'gravityflow' );
				}
			}
			?>
			<h3><span><i class="fa fa-cogs"></i> <?php esc_html_e( 'Tools', 'gravityflow' ) ?></span></h3>
			<?php

			if ( ! is_null( $success ) ) {
				$class = $success ? 'gold' : 'red';
				?>

				<div class="push-alert-<?php echo $class; ?>"
				     style="border-left: 1px solid #E6DB55; border-right: 1px solid #E6DB55;">
					<?php echo esc_html( $message ); ?>
				</div>
			<?php } ?>
			<div>
				<form method="POST" action="<?php echo admin_url( 'admin.php?page=gravityflow_settings&view=tools' ); ?>">
					<?php wp_nonce_field( 'gflow_revoke_token' ); ?>
					<div>
						<label for="gflow_token"><?php esc_html_e( 'Revoke a token', 'gravityflow' );?></label>
					</div>
					<div>
						<textarea id="gflow_token" name="gflow_token"></textarea>
					</div>

					<input type="submit" name="_revoke_token" value="<?php esc_html_e( 'Revoke', 'gravityflow' );?>" />
				</form>
			</div>
			<?php
		}

		public function get_published_form_ids() {
			$settings = $this->get_app_settings();

			if ( $settings === false ) {
				return array();
			}

			$selected_form_ids = array();

			foreach ( $settings as $key => $setting ) {
				if ( strstr( $key, 'publish_form_' ) && $setting == 1 ) {
					$form_id = str_replace( 'publish_form_', '', $key );
					$selected_form_ids[] = absint( $form_id );
				}
			}

			$workflow_forms = GFFormsModel::get_forms( true );

			$published_form_ids = array();

			foreach ( $workflow_forms as $workflow_form ) {
				if ( in_array( $workflow_form->id, $selected_form_ids ) ) {
					$published_form_ids[] = $workflow_form->id;
				}
			}

			return $published_form_ids;
		}

		public function load_screen_options() {

			$screen = get_current_screen();

			if ( ! is_object( $screen ) || $screen->id != 'workflow_page_gravityflow-status' ) {
				return;
			}

			if ( $this->is_status_page() ) {
				$args = array(
					'label'   => esc_html__( 'Entries per page', 'gravityflow' ),
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
						'html'          => esc_html__( 'No workflow steps have been added to any forms yet.', 'gravityflow' ),
					),
				);
			}

			$settings = array();

			if ( ! is_multisite() || ( is_multisite() && is_main_site() && ! defined( 'GRAVITY_FLOW_LICENSE_KEY' ) ) ) {
				$settings[] = array(
					'title'  => esc_html__( 'Settings', 'gravityflow' ),
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
							'tooltip' => __( 'Set this to ON to allow Gravity Flow to download and install bug fixes and security updates automatically in the background. Requires a valid license key.' , 'gravityflow' ),
							'type'          => 'radio',
							'horizontal' => true,
							'default_value' => false,
							'choices' => array(
								array( 'label' => __( 'On', 'gravityflow' ), 'value' => true ),
								array( 'label' => __( 'Off', 'gravityflow' ), 'value' => false ),
							),
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
				'title'       => esc_html__( 'Default Pages', 'gravityflow' ),
				'description' => esc_html__( 'Select the pages which contain the following gravityflow shortcodes. For example, the inbox page selected below will be used when preparing merge tags such as {workflow_inbox_link} when the page_id attribute is not specified.', 'gravityflow' ),
				'fields'      => array(
					array(
						'name'  => 'inbox_page',
						'label' => esc_html__( 'Inbox', 'gravityflow' ),
						'type'  => 'wp_dropdown_pages',
					),
					array(
						'name'  => 'status_page',
						'label' => esc_html__( 'Status', 'gravityflow' ),
						'type'  => 'wp_dropdown_pages',
					),
					array(
						'name'  => 'submit_page',
						'label' => esc_html__( 'Submit', 'gravityflow' ),
						'type'  => 'wp_dropdown_pages',
					),
				),
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
							'error'   => __( 'There was an error while saving the settings', 'gravityflow' ),
						),
					),
				)
			);

			return $settings;

		}

		/**
		 * Display or return the markup for the wp_dropdown_pages field type.
		 *
		 * @since 1.4.3-dev
		 *
		 * @param array     $field The field properties.
		 * @param bool|true $echo  Should the setting markup be echoed.
		 *
		 * @return string
		 */
		public function settings_wp_dropdown_pages( $field, $echo = true ) {

			$args = array(
				'selected'         => $this->get_setting( $field['name'] ),
				'echo'             => $echo,
				'name'             => '_gaddon_setting_' . esc_attr( $field['name'] ),
				'class'            => 'gaddon-setting gaddon-select',
				'show_option_none' => esc_html__( 'Select page', 'gravityflow' ),
			);

			$html = wp_dropdown_pages( $args );

			return $html;

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

		public function check_license( $value = '' ) {
			if ( empty( $value ) ) {
				$value = $this->get_app_setting( 'license_key' );
			}

			$response = $this->perform_edd_license_request( 'check_license', $value );

			return json_decode( wp_remote_retrieve_body( $response ) );
		}

		public function license_validation( $field, $field_setting ) {
			$old_license = $this->get_app_setting( 'license_key' );

			if ( $old_license && $field_setting != $old_license ) {
				// deactivate the old site
				$response = $this->perform_edd_license_request( 'deactivate_license', $old_license );
				$this->log_debug( __METHOD__ . '() - response: ' . print_r( $response, 1 ) );
			}


			if ( empty( $field_setting ) ) {
				return;
			}

			$this->activate_license( $field_setting );

		}

		public function activate_license( $license_key ) {
			$response = $this->perform_edd_license_request( 'activate_license', $license_key );

			set_site_transient( 'update_plugins', null );
			$cache_key = md5( 'edd_plugin_' . sanitize_key( $this->_path ) . '_version_info' );
			delete_transient( $cache_key );

			return json_decode( wp_remote_retrieve_body( $response ) );
		}

		/**
		 * Send a request to the EDD store url.
		 *
		 * @param string $edd_action The action to perform (check_license, activate_license or deactivate_license).
		 * @param string $license The license key.
		 * @param string $item_name The EDD item name. Defaults to the value of the GRAVITY_FLOW_EDD_ITEM_NAME constant.
		 *
		 * @return array|WP_Error The response.
		 */
		public function perform_edd_license_request( $edd_action, $license, $item_name = GRAVITY_FLOW_EDD_ITEM_NAME ) {
			// Prepare the request arguments
			$args = array(
				'timeout'   => 10,
				'sslverify' => true,
				'body'      => array(
					'edd_action' => $edd_action,
					'license'    => trim( $license ),
					'item_name'  => urlencode( $item_name ),
					'url'        => home_url(),
				),
			);

			// Send the remote request
			$response = wp_remote_post( GRAVITY_FLOW_EDD_STORE_URL, $args );

			return $response;
		}

		public function settings_html( $field ) {
			echo $field['html'];
		}

		public function submit() {

			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}

			$this->submit_page( true );
		}

		public function submit_page( $admin_ui ) {
			?>
			<div class="wrap gf_entry_wrap gravityflow_workflow_wrap gravityflow_workflow_submit">
				<?php if ( $admin_ui ) :	?>
					<h2 class="gf_admin_page_title">
						<img width="45" height="22" src="<?php echo esc_url( gravity_flow()->get_base_url() ); ?>/images/gravityflow-icon-blue-grad.svg" style="margin-right:5px;"/>

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

					$published_form_ids = gravity_flow()->get_published_form_ids();

					Gravity_Flow_Submit::list_page( $published_form_ids , $admin_ui );
				}

				?>
			</div>
			<?php
		}

		public function maybe_display_installation_wizard() {

			if ( is_multisite() || ! current_user_can( 'gform_full_access' ) ) {
				return false;
			}

			$pending_installation = get_option( 'gravityflow_pending_installation' ) || isset( $_GET['gravityflow_installation_wizard'] );

			if ( $pending_installation ) {
				require_once( $this->get_base_path() . '/includes/wizard/class-installation-wizard.php' );
				$wizard = new Gravity_Flow_Installation_Wizard;
				$result = $wizard->display();
				return $result;
			}

			if ( GFAPI::current_user_can_any( 'gform_full_access' ) && $this->is_dev_version() && ! SCRIPT_DEBUG ) {
				$message = esc_html__( 'Important: Gravity Flow (Development Version) is missing some important files that were not included in the installation package. Consult the readme.md file for further details.', 'gravityflow' );
				GFCommon::add_message( $message, true );
			};

			return false;
		}

		/**
		 * Checks whether the current version is a development version. The development version does not include
		 * minified CSS and JavaScript files.
		 *
		 * Interim build packages of the development version generated during continuous integration do contain
		 * the minified files and are therefore not considered development versions despite the version number.
		 * These builds contain the commit hash in the plugin version.
		 *
		 * @since 1.7.1
		 *
		 * @return bool
		 */
		public function is_dev_version() {
			$is_dev_version = false;
			$version = $this->get_version();
			if ( strpos( $version, '-dev' ) > 0  ) {
				$plugin_data    = get_plugin_data( $this->get_base_path() . '/gravityflow.php' );
				$plugin_version = $plugin_data['Version'];
				$hash = str_replace( $version, '', $plugin_version );
				if ( empty( $hash ) ) {
					$is_dev_version = true;
				}
			}
			return $is_dev_version;
		}


		/**
		 * Displays the Inbox UI
		 */
		public function inbox() {

			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}

			$this->inbox_page();

		}

		public function inbox_page( $args = array() ) {

			$defaults = array(
				'display_empty_fields' => true,
				'check_permissions'    => true,
				'show_header'          => true,
				'timeline'             => true,
				'step_highlight'       => true,
			);

			$args = array_merge( $defaults, $args );

			if ( rgget( 'view' ) == 'entry' || ! empty( $args['entry_id'] ) ) {

				$entry_id = absint( rgget( 'lid' ) );

				if ( empty( $entry_id ) ) {

					$entry_id = absint( $args['entry_id'] );

				}

				$entry = GFAPI::get_entry( $entry_id );

				if ( is_wp_error( $entry ) ) {
					esc_html_e( 'Oops! We could not locate your entry.', 'gravityflow' );
					return;
				}

				$form_id = $entry['form_id'];
				$form = GFAPI::get_form( $form_id );

				$process_entry_detail = apply_filters( 'gravityflow_inbox_entry_detail_pre_process', true, $form, $entry );

				if ( ! $process_entry_detail || is_wp_error( $process_entry_detail ) ) {
					return;
				}

				require_once( $this->get_base_path() . '/includes/pages/class-entry-detail.php' );

				$step = $this->get_current_step( $form, $entry );

				if ( $step ) {
					$token = $this->decode_access_token();

					if ( isset( $token['scopes']['action'] ) ) {
						if ( $token['scopes']['action'] === 'cancel_workflow'  ) {
							$entry_id = rgars( $token, 'scopes/entry_id' );
							if ( empty( $entry_id ) || $entry_id != $entry['id'] ) {
								esc_html_e( 'Error: incorrect entry.', 'gravityflow' );
								return;
							}
							$api = new Gravity_Flow_API( $form_id );
							$result = $api->cancel_workflow( $entry );
							if ( $result ) {
								esc_html_e( 'Workflow Cancelled', 'gravityflow' );
							}
							return;
						}

						$feedback = $step->maybe_process_token_action( $token['scopes']['action'], $token, $form, $entry );
						if ( empty( $feedback ) ) {
							esc_html_e( 'Error: This URL is no longer valid.', 'gravityflow' );
							return;
						}
						if ( is_wp_error( $feedback ) ) {
							/* @var WP_Error $feedback */
							echo $feedback->get_error_message();
							return;
						}
						$this->process_workflow( $form, $entry_id );
						echo $feedback;
						return;
					}
				}

				$feedback = $this->maybe_process_admin_action( $form, $entry );

				if ( empty( $feedback ) && $step ) {

					$feedback = $step->maybe_process_status_update( $form, $entry );

					if ( $feedback && ! is_wp_error( $feedback ) ) {
						$this->process_workflow( $form, $entry_id );
					}
				}

				if ( is_wp_error( $feedback ) ) {
					$error_data = $feedback->get_error_data();
					if ( ! empty( $error_data['form'] ) ) {
						$form = $error_data['form'];
					}
					?>
					<div class="notice error is-dismissible gravityflow_validation_error" style="padding:6px;">
						<?php echo esc_html( $feedback->get_error_message() ); ?>
					</div>
					<?php

				} elseif ( $feedback ) {
					GFCache::flush();

					$entry = GFAPI::get_entry( $entry_id ); // refresh entry

					?>
					<div class="updated notice notice-success is-dismissible" style="padding:6px;">
						<?php echo $feedback; ?>
					</div>
					<?php

					$next_step = $this->get_current_step( $form, $entry );
					$current_user_assignee_key = $this->get_current_user_assignee_key();
					if ( ( $next_step && $next_step->is_assignee( $current_user_assignee_key ) ) || $args['check_permissions'] == false || $this->current_user_can_any( 'gravityflow_view_all' ) ) {
						$step = $next_step;
					} else {
						$args['display_instructions'] = false;
					}
					$args['check_permissions'] = false;
				}

				Gravity_Flow_Entry_Detail::entry_detail( $form, $entry, $step, $args );
				return;
			} else {

				?>
				<div class="wrap gf_entry_wrap gravityflow_workflow_wrap gravityflow_workflow_detail">
					<?php if ( $args['show_header'] ) :	?>
						<h2 class="gf_admin_page_title">
							<img width="45" height="22" src="<?php echo $this->get_base_url(); ?>/images/gravityflow-icon-blue-grad.svg" style="margin-right:5px;"/>
							<span><?php esc_html_e( 'Workflow Inbox', 'gravityflow' ); ?></span>
						</h2>

						<?php GFCommon::display_admin_message(); ?>

						<?php
						$this->toolbar();
					endif;

					require_once( $this->get_base_path() . '/includes/pages/class-inbox.php' );
					Gravity_Flow_Inbox::display( $args );

					?>
				</div>
				<?php
			}
		}



		public function status() {

			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}

			$this->status_page();
		}

		public function status_page( $args = array() ) {
			$defaults = array(
				'display_header' => true,
			);
			$args = array_merge( $defaults, $args );
			?>
			<div class="wrap gf_entry_wrap gravityflow_workflow_wrap gravityflow_workflow_status">

				<?php if ( $args['display_header'] ) : ?>
					<h2 class="gf_admin_page_title">
						<img width="45" height="22" src="<?php echo esc_url( gravity_flow()->get_base_url() ); ?>/images/gravityflow-icon-blue-grad.svg" style="margin-right:5px;"/>
						<span><?php esc_html_e( 'Workflow Status', 'gravityflow' ); ?></span>
					</h2>

					<?php GFCommon::display_admin_message(); ?>

					<?php $this->toolbar(); ?>
					<?php
				endif;

				require_once( $this->get_base_path() . '/includes/pages/class-status.php' );
				Gravity_Flow_Status::render( $args );
				?>
			</div>
			<?php
		}

		/**
		 * Displays the Activity UI
		 */
		public function activity() {

			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}

			$this->activity_page();
		}

		public function activity_page( $args = array() ) {
			$defaults = array(
				'display_header' => true,
			);
			$args = array_merge( $defaults, $args );
			?>
			<div class="wrap gf_entry_wrap gravityflow_workflow_wrap gravityflow_workflow_activity">

				<?php if ( $args['display_header'] ) : ?>
					<h2 class="gf_admin_page_title">
						<img width="45" height="22" src="<?php echo esc_url( gravity_flow()->get_base_url() ); ?>/images/gravityflow-icon-blue-grad.svg" style="margin-right:5px;"/>

						<span><?php esc_html_e( 'Workflow Activity', 'gravityflow' ); ?></span>

					</h2>

					<?php GFCommon::display_admin_message(); ?>

					<?php $this->toolbar(); ?>
					<?php
				endif;

				require_once( $this->get_base_path() . '/includes/pages/class-activity.php' );
				Gravity_Flow_Activity_List::display( $args );
				?>
			</div>
			<?php
		}

		/**
		 * Displays the Reports UI
		 */
		public function reports() {

			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}

			$this->reports_page();
		}

		public function reports_page( $args = array() ) {
			$defaults = array(
				'display_header' => true,
			);
			$args = array_merge( $defaults, $args );
			?>
			<div class="wrap gf_entry_wrap gravityflow_workflow_wrap gravityflow_workflow_reports">

				<?php if ( $args['display_header'] ) : ?>
					<h2 class="gf_admin_page_title">
						<img width="45" height="22" src="<?php echo esc_url( gravity_flow()->get_base_url() ); ?>/images/gravityflow-icon-blue-grad.svg" style="margin-right:5px;"/>

						<span><?php esc_html_e( 'Workflow Reports', 'gravityflow' ); ?></span>

					</h2>

					<?php GFCommon::display_admin_message(); ?>

					<?php $this->toolbar(); ?>
					<?php
				endif;

				require_once( $this->get_base_path() . '/includes/pages/class-reports.php' );
				Gravity_Flow_Reports::display( $args );
				?>
			</div>
			<?php
		}

		public function toolbar() {
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

			$active_class = 'gf_toolbar_active';
			$not_active_class = '';

			$menu_items['inbox'] = array(
				'label'        => esc_html( $this->translate_navigation_label( 'inbox' ) ),
				'icon'         => '<i class="fa fa-inbox fa-lg"></i>',
				'title'        => __( 'Your inbox of pending tasks', 'gravityflow' ),
				'url'          => '?page=gravityflow-inbox',
				'menu_class'   => 'gf_form_toolbar_editor',
				'link_class'   => ( rgget( 'page' ) == 'gravityflow-inbox' ) ? $active_class : $not_active_class,
				'capabilities' => 'gravityflow_inbox',
				'priority'     => 1000,
			);

			$form_ids = $this->get_published_form_ids();

			if ( ! empty( $form_ids ) ) {
				$menu_items['submit'] = array(
					'label'        => esc_html( $this->translate_navigation_label( 'submit' ) ),
					'icon'         => '<i class="fa fa-pencil-square-o fa-lg"></i>',
					'title'        => __( 'Submit a Workflow', 'gravityflow' ),
					'url'          => '?page=gravityflow-submit',
					'menu_class'   => 'gf_form_toolbar_editor',
					'link_class'   => ( rgget( 'page' ) == 'gravityflow-submit' ) ? $active_class : $not_active_class,
					'capabilities' => 'gravityflow_submit',
					'priority'     => 900,
				);
			}

			$menu_items['status'] = array(
				'label'          => esc_html( $this->translate_navigation_label( 'status' ) ),
				'icon'           => '<i class="fa fa-tachometer fa-lg"></i>',
				'title'          => __( 'Your workflows', 'gravityflow' ),
				'url'            => '?page=gravityflow-status',
				'menu_class'     => 'gf_form_toolbar_settings',
				'link_class'   => ( rgget( 'page' ) == 'gravityflow-status' ) ? $active_class : $not_active_class,
				'capabilities'   => 'gravityflow_status',
				'priority'       => 800,
			);

			$menu_items['reports'] = array(
				'label'          => esc_html( $this->translate_navigation_label( 'reports' ) ),
				'icon'           => '<i class="fa fa fa-bar-chart-o fa-lg"></i>',
				'title'          => __( 'Reports', 'gravityflow' ),
				'url'            => '?page=gravityflow-reports',
				'menu_class'     => 'gf_form_toolbar_settings',
				'link_class'   => ( rgget( 'page' ) == 'gravityflow-reports' ) ? $active_class : $not_active_class,
				'capabilities'   => 'gravityflow_reports',
				'priority'       => 700,
			);

			$menu_items['activity'] = array(
				'label'          => esc_html( $this->translate_navigation_label( 'activity' ) ),
				'icon'           => '<i class="fa fa fa-list fa-lg"></i>',
				'title'          => __( 'Activity', 'gravityflow' ),
				'url'            => '?page=gravityflow-activity',
				'menu_class'     => 'gf_form_toolbar_settings',
				'link_class'   => ( rgget( 'page' ) == 'gravityflow-activity' ) ? $active_class : $not_active_class,
				'capabilities'   => 'gravityflow_activity',
				'priority'       => 600,
			);

			$menu_items = apply_filters( 'gravityflow_toolbar_menu_items', $menu_items );

			return $menu_items;
		}

		/**
		 * Processes the admin action from the entry detail page.
		 *
		 * @param $form
		 * @param $entry
		 *
		 * @return bool|string|WP_Error Return a success feedback message safe for page output or a WP_Error instance with an error.
		 */
		public function maybe_process_admin_action( $form, $entry ) {
			$feedback = false;
			if ( isset( $_POST['_gravityflow_admin_action'] ) && check_admin_referer( 'gravityflow_admin_action', '_gravityflow_admin_action_nonce' ) && GFAPI::current_user_can_any( 'gravityflow_workflow_detail_admin_actions' ) ) {
				$admin_action = rgpost( 'gravityflow_admin_action' );
				switch ( $admin_action ) {
					case 'cancel_workflow' :
						$api = new Gravity_Flow_API( $form['id'] );
						$success = $api->cancel_workflow( $entry );
						if ( $success ) {
							$this->log_debug( __METHOD__ . '() - workflow cancelled. entry id ' . $entry['id'] );
							$feedback = esc_html__( 'Workflow cancelled.',  'gravityflow' );

						} else {
							$this->log_debug( __METHOD__ . '() - workflow cancel failed. entry id ' . $entry['id'] );
							$feedback = esc_html__( 'The entry does not currently have an active step.', 'gravityflow' );
						}

						break;
					case 'restart_step':
						$api = new Gravity_Flow_API( $form['id'] );
						$success = $api->restart_step( $entry );
						if ( $success ) {
							$this->log_debug( __METHOD__ . '() - step restarted. entry id ' . $entry['id'] );
							$feedback = esc_html__( 'Workflow Step restarted.',  'gravityflow' );
						} else {
							$this->log_debug( __METHOD__ . '() - step restart failed. entry id ' . $entry['id'] );
							$feedback = esc_html__( 'The entry does not currently have an active step.', 'gravityflow' );
						}

						break;
					case 'restart_workflow':
						$api = new Gravity_Flow_API( $form['id'] );
						$api->restart_workflow( $entry );
						$this->log_debug( __METHOD__ . '() - workflow restarted. entry id ' . $entry['id'] );
						$feedback = esc_html__( 'Workflow restarted.',  'gravityflow' );
						break;
				}
				list( $base_admin_action, $action_id ) = rgexplode( '|', $admin_action, 2 );
				if ( $base_admin_action == 'send_to_step' ) {
					$step_id = $action_id;
					$api = new Gravity_Flow_API( $form['id'] );
					$api->send_to_step( $entry, $step_id );
					$entry = GFAPI::get_entry( $entry['id'] );
					$new_step = $api->get_current_step( $entry );
					$feedback = $new_step ? sprintf( esc_html__( 'Sent to step: %s',  'gravityflow' ), $new_step->get_name() ) : esc_html__( 'Workflow Complete',  'gravityflow' );
				}

				/**
				 * Allows the feedback for the admin action to be modified. Also allows custom admin actions to be processed.
				 *
				 * @param string $feedback A string with the feedback to be displayed to the user or an instance of WP_Error.
				 * @param string $admin_action The admin action.
				 * @param array $form The form array.
				 * @param array $entry The entry array.
				 */
				$feedback = apply_filters( 'gravityflow_admin_action_feedback', $feedback, $admin_action, $form, $entry );
			}
			return $feedback;
		}

		public function add_notification_event( $events, $form ) {
			if ( $this->has_feed( $form['id'] ) ) {
				$events['workflow_approval']   = __( 'Workflow: approved or rejected', 'gravityflow' );
				$events['workflow_user_input'] = __( 'Workflow: user input', 'gravityflow' );
				$events['workflow_complete']   = __( 'Workflow: complete', 'gravityflow' );
			}

			return $events;
		}

		/**
		 * Checks the workflow steps to see if any feeds belonging to other add-ons need to be delayed.
		 *
		 * @param array $entry The entry created from the current form submission.
		 * @param array $form The form object used to process the current submission.
		 *
		 * @return null
		 */
		public function action_entry_created( $entry, $form ) {
			$form_id = absint( $form['id'] );

			if ( empty( $form_id ) || ! isset( $entry['id'] ) || $entry['status'] === 'spam' ) {
				return;
			}

			$steps = $this->get_steps( $form_id );

			foreach ( $steps as $step ) {
				if ( ! $step->is_active() || ! is_callable( array( $step, 'intercept_submission' ) ) ) {
					continue;
				}

				$step->intercept_submission();
			}

			$this->maybe_delay_workflow( $entry, $form );
		}

		/**
		 * Determines if the current submission requires a PayPal payment and if the workflow should be delayed.
		 *
		 * @param array $entry The entry created from the current form submission.
		 * @param array $form The form object used to process the current submission.
		 */
		public function maybe_delay_workflow( $entry, $form ) {
			$is_delayed = false;

			if ( class_exists( 'GFPayPal' ) ) {
				$feed = gf_paypal()->get_single_submission_feed( $entry, $form );

				if ( ! empty( $feed ) && $this->is_delayed( $feed ) && $this->has_paypal_payment( $feed, $form, $entry ) ) {
					$this->log_debug( __METHOD__ . '() - processing delayed pending PayPal payment for entry id ' . $entry['id'] );
					remove_action( 'gform_after_submission', array( $this, 'after_submission' ), 9 );
					$is_delayed = true;
				}
			}

			if ( ! $is_delayed ) {
				gform_update_meta( $entry['id'], "{$this->_slug}_is_fulfilled", true );
			}
		}

		/**
		 * Starts the workflow if it was delayed pending PayPal payment.
		 *
		 * @param array $entry The entry for which the PayPal payment has been completed.
		 * @param array $paypal_config The PayPal feed used to process the entry.
		 * @param string $transaction_id The PayPal transaction ID.
		 * @param float $amount The transaction amount.
		 *
		 * @return void
		 */
		public function paypal_fulfillment( $entry, $paypal_config, $transaction_id, $amount ) {
			if ( empty( $entry['workflow_step'] ) && $this->is_delayed( $paypal_config ) && ! $this->is_entry_view() ) {
				$form     = GFAPI::get_form( $entry['form_id'] );
				$entry_id = absint( $entry['id'] );
				$this->process_workflow( $form, $entry_id );
			}
		}

		public function after_submission( $entry, $form ) {
			if ( ! isset( $entry['id'] ) || $entry['status'] === 'spam' ) {
				return;
			}
			if ( isset( $entry['workflow_step'] ) ) {
				$entry_id = absint( $entry['id'] );
				$this->process_workflow( $form, $entry_id );
			}
		}

		public function filter_after_update_entry( $form, $entry_id ) {
			$entry = GFAPI::get_entry( $entry_id );
			if ( isset( $entry['workflow_final_status'] ) && $entry['workflow_final_status'] == 'pending' ) {
				$this->process_workflow( $form, $entry_id );
			}
		}

		public function process_workflow( $form, $entry_id ) {

			$entry = GFAPI::get_entry( $entry_id );
			if ( isset( $entry['workflow_step'] ) ) {

				$this->log_debug( __METHOD__ . '() - processing. entry id ' . $entry_id );

				$step_id = $entry['workflow_step'];

				$starting_step_id = $step_id;

				if ( empty( $step_id ) && ( empty( $entry['workflow_final_status'] ) || $entry['workflow_final_status'] == 'pending') ) {
					$this->log_debug( __METHOD__ . '() - not yet started workflow. starting.' );
					// Starting workflow
					$form_id = absint( $form['id'] );
					$step = $this->get_first_step( $form_id, $entry );
					$this->log_event( 'workflow', 'started', $form['id'], $entry_id );
					if ( $step ) {
						$step->start();
						$this->log_debug( __METHOD__ . '() - started.' );
					} else {
						$this->log_debug( __METHOD__ . '() - no first step.' );
					}
				} else {
					$this->log_debug( __METHOD__ . '() - resuming workflow.' );
					$step = $this->get_step( $step_id, $entry );
				}

				$step_complete = false;

				if ( $step ) {
					$step_id = $step->get_id();
					$step_complete = $step->end_if_complete();
					$this->log_debug( __METHOD__ . '() - step ' . $step_id . ' complete: ' . ( $step_complete ? 'yes' : 'no' ) );
				}

				while ( $step_complete && $step ) {

					$this->log_debug( __METHOD__ . '() - getting next step.' );

					$step = $this->get_next_step( $step, $entry, $form );
					$step_complete = false;
					if ( $step ) {
						$step_id = $step->get_id();
						$step_complete = $step->start();
						if ( $step_complete ) {
							$step->end();
						}
					}
					$entry['workflow_step'] = $step_id;
				}

				if ( $step == false ) {
					$this->log_debug( __METHOD__ . '() - ending workflow.' );
					gform_delete_meta( $entry_id, 'workflow_step' );
					$final_status = gform_get_meta( $entry_id, 'workflow_current_status' );
					if ( empty( $final_status ) || $final_status == 'pending' ) {
						$final_status = 'complete';
					}
					gform_delete_meta( $entry_id, 'workflow_current_status' );
					gform_update_meta( $entry_id, 'workflow_final_status', $final_status );
					$entry_created_timestamp = strtotime( $entry['date_created'] );
					$duration = time() - $entry_created_timestamp;
					$this->log_event( 'workflow', 'ended', $form['id'], $entry_id, $final_status, 0, $duration );
					do_action( 'gravityflow_workflow_complete', $entry_id, $form, $final_status );
					// Refresh entry after action.
					$entry = GFAPI::get_entry( $entry_id );
					GFAPI::send_notifications( $form, $entry, 'workflow_complete' );
				} else {
					$this->log_debug( __METHOD__ . '() - not ending workflow.' );
					$step_id = $step->get_id();
					gform_update_meta( $entry_id, 'workflow_step', $step_id );
				}

				do_action( 'gravityflow_post_process_workflow', $form, $entry_id, $step_id, $starting_step_id );
			}
		}

		public function get_first_step( $form_id, $entry ) {
			$form  = GFAPI::get_form( $form_id );
			$steps = $this->get_steps( $form_id, $entry );
			foreach ( $steps as $step ) {
				if ( $step->is_active() && $step->is_condition_met( $form ) ) {
					return $step;
				}
			}

			return false;
		}

		public function shortcode( $atts, $content = null ) {

			$a = $this->get_shortcode_atts( $atts );

			if ( ! $a['allow_anonymous'] && ! is_user_logged_in() ) {
				if ( ! $this->validate_access_token() ) {
					return;
				}
			}

			$entry_id = absint( rgget( 'lid' ) );

			if ( empty( $entry_id ) && ! empty( $a['entry_id'] ) ) {
					$entry_id = absint( $a['entry_id'] );
			}

			if ( ! empty( $a['form'] ) && ! empty( $entry_id ) ) {
				// Limited support for multiple shortcodes on the same page
				$entry = GFAPI::get_entry( $entry_id );
				if ( is_wp_error( $entry ) || $entry['form_id'] !== $a['form'] ) {
					return;
				}
			}

			$html = '';

			if ( ! empty( $a['title'] ) ) {
				$html .= sprintf( '<h3>%s</h3>', $a['title'] );
			}

			switch ( $a['page'] ) {
				case 'inbox' :
					$html .= $this->get_shortcode_inbox_page( $a );
					break;
				case 'submit' :
					ob_start();
					$this->submit_page( false );
					$html .= ob_get_clean();
					break;
				case 'status' :
					wp_enqueue_script( 'gravityflow_entry_detail' );
					wp_enqueue_script( 'gravityflow_status_list' );

					if ( rgget( 'view' ) || ! empty( $entry_id ) ) {
						$html .= $this->get_shortcode_status_page_detail( $a );
					} else {
						$html .= $this->get_shortcode_status_page( $a );
					}
			}

			/**
			 * Allows the gravityflow shortcode to be modified and supports custom pages.
			 *
			 * @param string $html The HTML.
			 * @param array $atts The original shortcode attributes.
			 * @param string $content The content inside the shortcode block.
			 */
			$html = apply_filters( 'gravityflow_shortcode_' . $a['page'], $html, $atts, $content );

			return $html;

		}

		/**
		 * Get the shortcode attributes, after merging with the defaults.
		 *
		 * @param array $atts The attributes from the shortcode.
		 *
		 * @return array
		 */
		public function get_shortcode_atts( $atts ) {
			$a = shortcode_atts( $this->get_shortcode_defaults(), $atts );

			if ( $a['form_id'] > 0 ) {
				$a['form'] = $a['form_id'];
			}

			$a['title'] = sanitize_text_field( $a['title'] );
			$a          = $this->booleanize_shortcode_attributes( $a );

			if ( is_null( $a['display_all'] ) ) {
				$a['display_all'] = GFAPI::current_user_can_any( 'gravityflow_status_view_all' );
				$this->log_debug( __METHOD__ . '() - display_all set by capabilities: ' . $a['display_all'] );
			} else {
				$a['display_all'] = strtolower( $a['display_all'] ) == 'true' ? true : false;
				$this->log_debug( __METHOD__ . '() - display_all overridden: ' . $a['display_all'] );
			}

			return $a;
		}

		/**
		 * The default attributes for the gravityflow shortcode.
		 *
		 * @return array
		 */
		public function get_shortcode_defaults() {
			$defaults = array(
				'page'             => 'inbox',
				'form'             => null,
				'form_id'          => null,
				'entry_id'         => null,
				'fields'           => array(),
				'display_all'      => null,
				'actions_column'   => false,
				'allow_anonymous'  => false,
				'title'            => '',
				'id_column'        => true,
				'submitter_column' => true,
				'step_column'      => true,
				'status_column'    => true,
				'timeline'         => true,
				'last_updated'     => false,
				'step_status'      => true,
				'workflow_info'    => true,
				'sidebar'          => true,
				'step_highlight'   => true,
			);

			return $defaults;
		}

		/**
		 * Converts the string attribute values to booleans.
		 *
		 * @param array $a The shortcode attributes.
		 *
		 * @return array
		 */
		public function booleanize_shortcode_attributes( $a ) {
			$attributes = $this->get_shortcode_defaults();

			foreach ( $attributes as $attribute => $default ) {
				if ( $default === true ) {
					$a[ $attribute ] = strtolower( $a[ $attribute ] ) == 'false' ? false : true;
				} elseif ( $default === false ) {
					$a[ $attribute ] = strtolower( $a[ $attribute ] ) == 'true' ? true : false;
				}
			}

			return $a;
		}

		/**
		 * Get the HTML for the inbox page shortcode.
		 *
		 * @param array $a The shortcode attributes.
		 *
		 * @return string
		 */
		public function get_shortcode_inbox_page( $a ) {
			wp_enqueue_script( 'gravityflow_entry_detail' );
			wp_enqueue_script( 'gravityflow_status_list' );
			$args = array(
				'form_id'              => $a['form'],
				'entry_id'             => $a['entry_id'],
				'id_column'            => $a['id_column'],
				'submitter_column'     => $a['submitter_column'],
				'step_column'          => $a['step_column'],
				'actions_column'       => $a['actions_column'],
				'show_header'          => false,
				'field_ids'            => $a['fields'] ? explode( ',', $a['fields'] ) : '',
				'detail_base_url'      => add_query_arg( array( 'page' => 'gravityflow-inbox', 'view' => 'entry' ) ),
				'timeline'             => $a['timeline'],
				'last_updated'         => $a['last_updated'],
				'step_status'          => $a['step_status'],
				'workflow_info'        => $a['workflow_info'],
				'sidebar'              => $a['sidebar'],
				'step_highlight'       => $a['step_highlight'],
			);

			ob_start();
			$this->inbox_page( $args );
			$html = ob_get_clean();

			return $html;
		}

		/**
		 * Get the HTML for the status page shortcode, detail view.
		 *
		 * @param array $a The shortcode attributes.
		 *
		 * @return string
		 */
		public function get_shortcode_status_page_detail( $a ) {
			ob_start();
			$check_permissions = true;

			if ( $a['allow_anonymous'] || $a['display_all'] ) {
				$check_permissions = false;
			}

			$args = array(
				'entry_id'          => $a['entry_id'],
				'show_header'       => false,
				'detail_base_url'   => add_query_arg( array( 'page' => 'gravityflow-inbox', 'view' => 'entry' ) ),
				'check_permissions' => $check_permissions,
				'timeline'          => $a['timeline'],
				'sidebar'           => $a['sidebar'],
			);

			$this->inbox_page( $args );
			$html = ob_get_clean();

			return $html;
		}

		/**
		 * Get the HTML for the status page shortcode, list view.
		 *
		 * @param array $a The shortcode attributes.
		 *
		 * @return string
		 */
		public function get_shortcode_status_page( $a ) {
			require_once( ABSPATH . 'wp-admin/includes/screen.php' );
			require_once( ABSPATH . 'wp-admin/includes/template.php' );
			ob_start();

			$args = array(
				'base_url'           => remove_query_arg( array(
					'entry-id',
					'form-id',
					'start-date',
					'end-date',
					'_wpnonce',
					'_wp_http_referer',
					'action',
					'action2',
					'o',
					'f',
					't',
					'v',
					'gravityflow-print-page-break',
					'gravityflow-print-timelines',
				) ),
				'detail_base_url'    => add_query_arg( array( 'page' => 'gravityflow-inbox', 'view' => 'entry' ) ),
				'display_header'     => false,
				'action_url'         => 'http' . ( isset( $_SERVER['HTTPS'] ) ? 's' : '' ) . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}?",
				'field_ids'          => $a['fields'] ? explode( ',', $a['fields'] ) : '',
				'display_all'        => $a['display_all'],
				'id_column'          => $a['id_column'],
				'submitter_column'   => $a['submitter_column'],
				'step_column'        => $a['step_column'],
				'status_column'      => $a['status_column'],
				'last_updated'       => $a['last_updated'],
				'step_status'        => $a['step_status'],
				'workflow_info'      => $a['workflow_info'],
				'sidebar'            => $a['sidebar'],
			);

			if ( isset( $a['form'] ) ) {
				$args['constraint_filters'] = array(
					'form_id' => $a['form'],
				);
			}

			if ( ! is_user_logged_in() && $a['allow_anonymous'] ) {
				$args['bulk_actions'] = array();
			}

			$this->status_page( $args );
			$html = ob_get_clean();

			return $html;
		}

		/**
		 * Checks if a particular user has a role.
		 * Returns true if a match was found.
		 *
		 * @param string $role Role name.
		 * @param int $user_id (Optional) The ID of a user. Defaults to the current user.
		 * @return bool
		 */
		public function check_user_role( $role, $user_id = null ) {

			return in_array( $role, $this->get_user_roles( $user_id ) );
		}

		/**
		 * Get the roles for the current or specified user.
		 *
		 * @param null|int $user_id (Optional) The ID of a user. Defaults to the current user.
		 *
		 * @return array
		 */
		public function get_user_roles( $user_id = null ) {

			if ( is_numeric( $user_id ) ) {
				$user = get_userdata( $user_id );
			} else {
				$user = wp_get_current_user();
			}

			if ( empty( $user ) ) {
				return array();
			}

			return (array) $user->roles;
		}

		public function support() {
			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}

			require_once( $this->get_base_path() . '/includes/pages/class-support.php' );
			Gravity_Flow_Support::display();
		}

		public function app_tab_page() {
			if ( $this->maybe_display_installation_wizard() ) {
				return;
			}
			parent::app_tab_page();
		}

		/**
		 * Returns the specified app setting.
		 *
		 * @since 1.4.3-dev
		 *
		 * @param string $setting_name The app setting to be returned.
		 *
		 * @return mixed|string
		 */
		public function get_app_setting( $setting_name ) {
			$setting = parent::get_app_setting( $setting_name );

			// If a default page hasn't been configured use the admin page.
			if ( ! $setting && in_array( $setting_name, array( 'inbox_page', 'status_page', 'submit_page' ) ) ) {
				return 'admin';
			}

			return $setting;
		}

		public function get_app_settings() {
			return parent::get_app_settings();
		}

		public function update_app_settings( $settings ) {
			if ( $this->is_save_postback() ) {
				$previous_settings = $this->get_previous_settings();
				$pages             = array( 'inbox_page', 'status_page', 'submit_page' );

				foreach ( $pages as $page ) {
					$this->maybe_update_page_content( $page, $settings, $previous_settings );
				}
			}

			parent::update_app_settings( $settings );
		}

		/**
		 * If a new page has been selected ensure it contains the gravityflow shortcode.
		 *
		 * @since 1.4.3-beta
		 *
		 * @param string $page The setting currently being processed; inbox_page, status_page, or submit_page.
		 * @param array $settings The valid settings to be saved.
		 * @param array $previous_settings The previous settings.
		 */
		public function maybe_update_page_content( $page, $settings, $previous_settings ) {
			$new_setting = rgar( $settings, $page );

			if ( ! $new_setting || $new_setting == rgar( $previous_settings, $page ) ) {
				return;
			}

			$post = get_post( $new_setting );

			if ( ! $post || stripos( $post->post_content, '[gravityflow' ) !== false ) {
				return;
			}

			if ( ! empty( $post->post_content ) ) {
				$post->post_content .= "\n";
			}

			$post->post_content .= sprintf( '[gravityflow page="%s"]', str_replace( '_page', '', $page ) );

			wp_update_post( $post );
		}

		public function maybe_auto_update( $update, $item ) {
			if ( isset( $item->slug ) && $item->slug == 'gravityflow' ) {

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

		public function is_auto_update_disabled() {

			// Currently WordPress won't ask Gravity Flow to update if background updates are disabled.
			// Let's double check anyway.

			// WordPress background updates are disabled if you don't want file changes.
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return true;
			}

			if ( defined( 'WP_INSTALLING' ) ) {
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

		public function uninstall() {

			require_once( $this->get_base_path() . '/includes/wizard/class-installation-wizard.php' );
			$wizard = new Gravity_Flow_Installation_Wizard;
			$wizard->flush_values();

			wp_clear_scheduled_hook( 'gravityflow_cron' );

			$this->uninstall_db();

			parent::uninstall();
		}

		private function uninstall_db() {

			global $wpdb;
			$table = Gravity_Flow_Activity::get_activity_log_table_name();
			$wpdb->query( "DROP TABLE IF EXISTS $table" );

		}

		/**
		 * Add a step note to the specified entry.
		 *
		 * @param int         $entry_id  The ID of the entry the note is to be added to.
		 * @param string      $note      The note to be added.
		 * @param bool|int    $user_id   The user ID or false.
		 * @param bool|string $user_name The user name or step type.
		 */
		public function add_timeline_note( $entry_id, $note, $user_id = false, $user_name = 'gravityflow' ) {
			$assignee_key = $this->get_current_user_assignee_key();
			if ( $assignee_key ) {
				$assignee = new Gravity_Flow_Assignee( $assignee_key );
				if ( $assignee->get_type() === 'user_id' ) {
					$user_id   = $assignee->get_id();
					$user_name = $assignee->get_display_name();
				}
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

		public function action_gform_forms_post_import( $forms ) {
			$gravityflow_feeds_imported = false;
			foreach ( $forms as $import_form ) {

				// Ensure the imported form is the latest. Compensates for a bug in Gravity Forms < 2.1.1.13
				$form = GFAPI::get_form( $import_form['id'] );

				if ( isset( $form['feeds']['gravityflow'] ) ) {
					$this->import_gravityflow_feeds( $form['feeds']['gravityflow'], $form['id'] );
					unset( $form['feeds']['gravityflow'] );
					if ( empty( $form['feeds'] ) ) {
						unset( $form['feeds'] );
					}
					GFAPI::update_form( $form );
					$gravityflow_feeds_imported = true;
				}
			}

			if ( $gravityflow_feeds_imported ) {
				GFCommon::add_message( esc_html__( 'Gravity Flow Steps imported. IMPORTANT: Check the assignees for each step. If the form was imported from a different installation with different user IDs then steps may need to be reassigned.', 'gravityflow' ) );
			}
		}

		public function action_admin_enqueue_scripts() {
			$this->maybe_enqueue_form_scripts();
		}


		public function maybe_enqueue_form_scripts() {
			if ( $this->is_workflow_detail_page() ) {
				$this->enqueue_form_scripts();
			}
		}

		public function enqueue_form_scripts() {
			$form = $this->get_current_form();

			if ( empty( $form ) ) {
				return;
			}
			require_once( GFCommon::get_base_path() . '/form_display.php' );

			if ( $this->has_enhanced_dropdown( $form ) ) {
				if ( wp_script_is( 'chosen', 'registered' ) ) {
					wp_enqueue_script( 'chosen' );
				} else {
					wp_enqueue_script( 'gform_chosen' );
				}
			}

			GFFormDisplay::enqueue_form_scripts( $form );
		}

		public function is_workflow_detail_page() {
			$id  = rgget( 'id' );
			$lid = rgget( 'lid' );
			return rgget( 'page' ) == 'gravityflow-inbox' && rgget( 'view' ) == 'entry' && ! empty( $id ) && ! empty( $lid );
		}

		public function get_workflow_form_ids() {
			if ( isset( $this->form_ids ) ) {
				return $this->form_ids;
			}
			$forms = GFFormsModel::get_forms();
			$form_ids = array();
			foreach ( $forms as $form ) {
				$form_id = absint( $form->id );
				$feeds = gravity_flow()->get_feeds( $form_id );
				if ( ! empty( $feeds ) ) {
					$form_ids[] = $form_id;
				}
			}
			$this->form_ids = $form_ids;
			return $this->form_ids;
		}

		public function cron() {
			$this->log_debug( __METHOD__ . '() Starting cron.' );

			$this->maybe_process_queued_entries();
			$this->maybe_process_expiration_and_reminders();

			$this->log_debug( __METHOD__ . '() Finished cron.' );
		}

		public function maybe_process_queued_entries() {

			$this->log_debug( __METHOD__ . '(): starting' );

			$form_ids = $this->get_workflow_form_ids();

			if ( empty( $form_ids ) ) {
				return;
			}

			global $wpdb;

			$entry_table = Gravity_Flow_Common::get_entry_table_name();
			$meta_table = Gravity_Flow_Common::get_entry_meta_table_name();
			$entry_id_column = Gravity_Flow_Common::get_entry_id_column_name();

			$sql = "
SELECT l.id, l.form_id
FROM $entry_table l
INNER JOIN $meta_table m
ON l.id = m.{$entry_id_column}
AND l.status='active'
AND m.meta_key LIKE 'workflow_step_status_%'
AND m.meta_value='queued'";

			$results = $wpdb->get_results( $sql );

			if ( empty( $results ) || is_wp_error( $results ) ) {
				return;
			}

			$this->log_debug( __METHOD__ . '() Queued entries: ' . print_r( $results, true ) );

			foreach ( $results as $result ) {
				$form = GFAPI::get_form( $result->form_id );
				$entry = GFAPI::get_entry( $result->id );
				$step = $this->get_current_step( $form, $entry );
				if ( $step && $step->is_queued() ) {
					$complete = $step->start();
					if ( $complete ) {
						$this->process_workflow( $form, $entry['id'] );
					} else {
						$this->log_debug( __METHOD__ . '() queued entry started step but step is not complete: ' . $entry['id'] );
					}
				} else {
					$this->log_debug( __METHOD__ . '() queued entry not on a queued step: ' . $entry['id'] );
				}
			}
		}

		/**
		 * Expire entries that need to be expired and send pending reminder emails.
		 *
		 * @since 1.5.1 Added support for repeat reminders.
		 * @since unknown
		 */
		public function maybe_process_expiration_and_reminders() {

			$this->log_debug( __METHOD__ . '(): starting' );

			$form_ids = $this->get_workflow_form_ids();

			$this->log_debug( __METHOD__ . '(): workflow form IDs: ' . print_r( $form_ids, true ) );

			foreach ( $form_ids as $form_id ) {
				$form = GFAPI::get_form( $form_id );
				$steps = $this->get_steps( $form_id );
				foreach ( $steps as $step ) {
					if ( ! $step || ! $step instanceof Gravity_Flow_Step ) {
						$this->log_debug( __METHOD__ . '(): step not a step!  ' . print_r( $step ) . ' - form ID: ' . $form_id );
						continue;
					}

					if ( ! $step->expiration && ! ( $step->assignee_notification_enabled && $step->resend_assignee_emailEnable && $step->resend_assignee_emailValue > 0 ) ) {
						continue;
					}

					$this->log_debug( __METHOD__ . '(): checking assignees for all the entries on step ' . $step->get_id() );

					$criteria = array(
						'status' => 'active',
						'field_filters' => array(
							array(
								'key' => 'workflow_step',
								'value' => $step->get_id(),
							),
						),
					);

					$paging = array(
						'offset'    => 0,
						'page_size' => 150,
					);
					// Criteria: step active
					$entries = GFAPI::get_entries( $form_id, $criteria, null, $paging );

					$this->log_debug( __METHOD__ . '(): count entries on step ' . $step->get_id() . ' = ' . count( $entries ) );

					foreach ( $entries as $entry ) {
						$current_step = $this->get_step( $entry['workflow_step'], $entry );

						$this->log_debug( __METHOD__ . '(): processing entry: ' . $entry['id'] );

						if ( $current_step->is_expired() ) {

							$this->log_debug( __METHOD__ . '(): step has expired: ' . $current_step->get_id() . ' entry id: ' . $entry['id'] );

							$expiration_status = $current_step->status_expiration ? $current_step->status_expiration : 'complete';

							$this->log_debug( __METHOD__ . '(): expiration status: ' . $expiration_status );

							$current_step->log_event( esc_html__( 'Step expired', 'gravityflow' ) );

							$expiration_note = $current_step->get_name() . ': ' . esc_html__( 'Step expired', 'gravityflow' );

							$current_step->add_note( $expiration_note );

							gravity_flow()->process_workflow( $form, $entry['id'] );

							// Next entry
							continue;
						}

						$assignees = $current_step->get_assignees();

						foreach ( $assignees as $assignee ) {
							$assignee_status = $assignee->get_status();
							if ( $assignee_status == 'pending' ) {
								$assignee_timestamp = $assignee->get_status_timestamp();
								$trigger_timestamp = $assignee_timestamp + ( (int) $current_step->resend_assignee_emailValue * DAY_IN_SECONDS );
								$reminder_timestamp = $assignee->get_reminder_timestamp();
								if ( time() > $trigger_timestamp && $reminder_timestamp == false ) {
									$this->log_debug( __METHOD__ . '(): assignee_timestamp: ' . $assignee_timestamp . ' - ' . get_date_from_gmt( date( 'Y-m-d H:i:s', $assignee_timestamp ), 'F j, Y H:i:s' ) );
									$this->log_debug( __METHOD__ . '(): trigger_timestamp: ' . $trigger_timestamp  . ' - ' . get_date_from_gmt( date( 'Y-m-d H:i:s', $trigger_timestamp ), 'F j, Y H:i:s' ) );
									$current_step->maybe_send_assignee_notification( $assignee, true );
									$assignee->set_reminder_timestamp();
									$this->log_debug( __METHOD__ . '(): sent first reminder about entry ' . $entry['id'] . ' to ' . $assignee->get_key() );
								}
								if ( time() > $trigger_timestamp && $reminder_timestamp !== false ) {
									$this->log_debug( __METHOD__ . '(): not sending first reminder to ' . $assignee->get_key() . ' for entry ' . $entry['id'] . ' because a reminder was already sent: ' . get_date_from_gmt( date( 'Y-m-d H:i:s', $reminder_timestamp ), 'F j, Y H:i:s' ) );
									if ( $current_step->resend_assignee_email_repeatEnable ) {
										$repeat_days = absint( $current_step->resend_assignee_email_repeatValue );
										/**
										 * Allows the number of days between each assignee email reminder to be modified.
										 *
										 * Return zero to deactivate the repeat reminder.
										 *
										 * @param int                   $repeat_days The number of days between each reminder.
										 * @param array                 $form
										 * @param array                 $entry
										 * @param Gravity_Flow_Step     $step
										 * @param Gravity_Flow_Assignee $assignee
										 */
										$repeat_days = apply_filters( 'gravityflow_assignee_eamil_reminder_repeat_days', $repeat_days, $form, $entry, $current_step, $assignee );
										if ( $repeat_days > 0 ) {
											$repeat_trigger_timestamp = $reminder_timestamp + ( (int) $repeat_days * DAY_IN_SECONDS );
											if ( time() > $repeat_trigger_timestamp ) {
												$current_step->maybe_send_assignee_notification( $assignee, true );
												$assignee->set_reminder_timestamp();
												$this->log_debug( __METHOD__ . '(): sent repeat reminder about entry ' . $entry['id'] . ' to ' . $assignee->get_key() );
											} else {
												$this->log_debug( __METHOD__ . '(): repeat reminder to ' . $assignee->get_key() .' for entry ' . $entry['id'] . ' is scheduled for ' . get_date_from_gmt( date( 'Y-m-d H:i:s', $repeat_trigger_timestamp ), 'F j, Y H:i:s' ) );
											}
										}
									}
								}
								if ( time() < $trigger_timestamp && $reminder_timestamp == false ) {
									$this->log_debug( __METHOD__ . '(): reminder to ' . $assignee->get_key() .' for entry ' . $entry['id'] . ' is scheduled for ' . get_date_from_gmt( date( 'Y-m-d H:i:s', $trigger_timestamp ), 'F j, Y H:i:s' ) );
								}
							}
						}
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

		public function filter_feed_actions( $action_links, $item, $column ) {

			if ( empty( $action_links ) ) {
				return $action_links;
			}
			$feed_id = $item['id'];

			$current_step = $this->get_step( $feed_id );

			$count_entries = apply_filters( 'gravityflow_entry_count_step_list', true );

			$entry_count = $current_step && $count_entries ? absint( $current_step->entry_count() ) : false;

			if ( $entry_count && $entry_count > 0 ) {
				unset( $action_links['delete'] );
			}
			return $action_links;
		}

		public function import_gravityflow_feeds( $original_feeds, $new_form_id ) {
			$feed_id_mappings = array();

			foreach ( $original_feeds as $feed ) {
				$new_feed_id = GFAPI::add_feed( $new_form_id, $feed['meta'], 'gravityflow' );
				if ( ! $feed['is_active'] ) {
					$this->update_feed_active( $new_feed_id, false );
				}
				$feed_id_mappings[ $feed['id'] ] = $new_feed_id;
			}

			$new_steps = $this->get_steps( $new_form_id );

			foreach ( $new_steps as $new_step ) {
				$statuses_configs = $new_step->get_status_config();
				$new_step_meta = $new_step->get_feed_meta();
				$step_ids_updated = false;
				foreach ( $statuses_configs as $status_config ) {
					$destination_key = 'destination_' . $status_config['status'];
					$old_destination_step_id = $new_step_meta[ $destination_key ];
					if ( ! in_array( $old_destination_step_id, array( 'next', 'complete' ) ) && isset( $feed_id_mappings[ $old_destination_step_id ] ) ) {
						$new_step_meta[ $destination_key ] = $feed_id_mappings[ $old_destination_step_id ];
						$step_ids_updated = true;
					}
				}
				if ( $new_step->get_type() == 'approval' ) {
					if ( ! empty( $new_step->revertValue ) ) {
						$new_step_meta['revertValue'] = $feed_id_mappings[ $new_step->revertValue ];
						$step_ids_updated = true;
					}
				}
				// Change feed id in conditional logic
				$is_condition_enabled = rgar( $new_step_meta, 'feed_condition_conditional_logic' ) == true;
				$logic                = rgars( $new_step_meta, 'feed_condition_conditional_logic_object/conditionalLogic' );
				if ( $is_condition_enabled && ! empty( $logic ) ) {
					foreach ( $new_step_meta['feed_condition_conditional_logic_object']['conditionalLogic']['rules'] as $key => $rule ) {
						if ( 0 === strpos( $rule['fieldId'], 'workflow_step_status_' ) ) {
							$old_feed_id = explode( '_', $rule['fieldId'] ); // fieldId is in the format of "workflow_step_status_30"
							$new_step_meta['feed_condition_conditional_logic_object']['conditionalLogic']['rules'][$key]['fieldId'] = 'workflow_step_status_' . $feed_id_mappings[$old_feed_id[3]];
							$step_ids_updated = true;
						}
					}
				}

				if ( $step_ids_updated ) {
					$this->update_feed_meta( $new_step->get_id(), $new_step_meta );
				}
			}
		}

		public function filter_wp() {

			if ( isset( $_GET['gflow_access_token'] ) ) {

				$token = $this->decode_access_token();

				if ( ! empty( $token ) && ! isset( $token['scopes']['action'] )&& ! is_user_logged_in() ) {
					// Remove the token from the URL to avoid accidental sharing.
					$secure = ( 'https' === parse_url( home_url(), PHP_URL_SCHEME ) );
					$sanitized_cookie = sanitize_text_field( $_GET['gflow_access_token'] );
					setcookie( 'gflow_access_token', $sanitized_cookie, null, $this->get_cookie_path(), null, $secure, true );

					$request_uri = remove_query_arg( 'gflow_access_token' );

					$redirect_url = home_url() . $request_uri;

					$this->log_debug( __METHOD__ . '(): redirect url: ' . $redirect_url );

					wp_safe_redirect( $redirect_url );

					exit();
				}
			}

			if ( isset( $_REQUEST['gflow_token'] ) && ! is_admin() ) {
				$token = $_REQUEST['gflow_token'];
				$token_json = base64_decode( $token );
				$token_array = json_decode( $token_json, true );

				if ( empty( $token_array ) ) {
					return false;
				}

				$entry_id = $token_array['entry_id'];
				if ( empty( $entry_id ) ) {
					return false;
				}

				$entry = GFAPI::get_entry( $entry_id );

				$step_id = $token_array['step_id'];
				if ( empty( $step_id ) ) {
					return false;
				}

				$step = $this->get_step( $step_id, $entry );
				if ( ! $step instanceof Gravity_Flow_Step_Approval ) {
					return false;
				}
				if ( ! $step->is_valid_token( $token ) ) {
					return false;
				}

				$form_id = $entry['form_id'];

				$form = GFAPI::get_form( $form_id );

				$user_id = $token_array['user_id'];
				$new_status = $token_array['new_status'];

				$feedback = $step->process_assignee_status( $user_id, 'user_id', $new_status, $form );

				if ( ! empty( $feedback ) ) {
					$this->process_workflow( $form, $entry_id );
					$this->_custom_page_content = $feedback;
					add_filter( 'the_content', array( $this, 'custom_page_content' ) );
				}
			}
		}

		public function custom_page_content( $content ) {
			$content .= $this->_custom_page_content;
			return $content;
		}


		/**
		 * Loosely based on the JWT spec.
		 *
		 * @param Gravity_Flow_Assignee $assignee
		 * @param array $scopes
		 * @param string $expiration_timestamp
		 *
		 * @return string
		 */
		public function generate_access_token( $assignee, $scopes = array(), $expiration_timestamp = false ) {

			if ( empty( $scopes ) ) {
				$scopes = array(
					'pages' => array( 'inbox', 'status' ),
				);
			}

			$user = $assignee->get_user();

			if ( ! empty( $user ) ) {
				$scopes['user_id'] = $user->ID;
			}

			if ( empty( $expiration_timestamp ) ) {
				$expiration_timestamp = strtotime( '+30 days' );
			}

			$jti = uniqid();

			$token_array = array(
				'iat'  => time(),
				'exp' => $expiration_timestamp,
				'sub'    => $assignee->get_key(),
				'scopes' => $scopes,
				'jti' => $jti,
			);

			$token = rawurlencode( base64_encode( json_encode( $token_array ) ) );

			$secret = get_option( 'gravityflow_token_secret' );
			if ( empty( $secret ) ) {
				$secret = wp_generate_password( 64 );
				update_option( 'gravityflow_token_secret', $secret );
			}

			$sig = hash_hmac( 'sha256', $token, $secret );

			$token .= '.' . $sig;

			$this->log_event( 'token', 'generated', 0, 0, json_encode( $token_array ), 0, 0, $assignee->get_id(), $assignee->get_type(), $assignee->get_display_name() );

			return $token;
		}

		public function validate_access_token( $token = false ) {

			if ( empty( $token ) ) {
				$token = $this->get_access_token();
			}

			if ( empty( $token ) ) {
				$this->log_debug( __METHOD__ . '(): empty token; returning false.' );

				return false;
			}

			$parts = explode( '.', $token );
			if ( count( $parts ) < 2 ) {
				$this->log_debug( __METHOD__ . '(): token parts < 2; returning false.' );

				return false;
			}

			$body_64_probably_url_decoded = $parts[0];
			$sig                          = $parts[1];

			if ( empty( $sig ) ) {
				$this->log_debug( __METHOD__ . '(): empty sig; returning false.' );

				return false;
			}

			$secret = get_option( 'gravityflow_token_secret' );
			if ( empty( $secret ) ) {
				$this->log_debug( __METHOD__ . '(): empty secret; returning false.' );

				return false;
			}

			$verification_sig  = hash_hmac( 'sha256', $body_64_probably_url_decoded, $secret );
			$verification_sig2 = hash_hmac( 'sha256', rawurlencode( $body_64_probably_url_decoded ), $secret );

			if ( ! hash_equals( $sig, $verification_sig ) && ! hash_equals( $sig, $verification_sig2 ) ) {
				$this->log_debug( __METHOD__ . '(): failed hash validation; returning false.' );

				return false;
			}

			$body_json = base64_decode( $body_64_probably_url_decoded );
			if ( empty( $body_json ) ) {
				$body_json = base64_decode( urldecode( $body_64_probably_url_decoded ) );
				if ( empty( $body_json ) ) {
					$this->log_debug( __METHOD__ . '(): empty body_json; returning false.' );

					return false;
				}
			}

			$token = json_decode( $body_json, true );

			if ( ! isset( $token['jti'] ) ) {
				$this->log_debug( __METHOD__ . '(): jti not set; returning false.' );

				return false;
			}

			if ( ! isset( $token['exp'] ) ) {
				$this->log_debug( __METHOD__ . '(): exp not set; returning false.' );

				return false;
			}

			if ( $token['exp'] < time() ) {
				$this->log_debug( __METHOD__ . '(): exp < time; returning false.' );

				return false;
			}

			$revoked_tokens = get_option( 'gravityflow_revoked_tokens', array() );
			if ( isset( $revoked_tokens[ $token['jti'] ] ) ) {
				$this->log_debug( __METHOD__ . '(): token revoked; returning false.' );

				return false;
			}

			$this->log_debug( __METHOD__ . '(): token valid.' );

			return true;
		}

		public function get_access_token() {
			$token = false;
			if ( empty( $token ) ) {
				$token = rgget( 'gflow_access_token' );
			}

			if ( empty( $token ) ) {
				$token = rgar( $_COOKIE, 'gflow_access_token' );
			}

			return $token;
		}

		public function decode_access_token( $token = false, $validate = true ) {
			if ( empty( $token ) ) {
				$token = $this->get_access_token();
			}

			if ( empty( $token ) ) {
				$this->log_debug( __METHOD__ . '(): empty token; returning false.' );

				return false;
			}

			if ( $validate && ! $this->validate_access_token( $token ) ) {
				$this->log_debug( __METHOD__ . '(): token failed validation; returning false.' );

				return false;
			}

			$parts = explode( '.', $token );
			if ( count( $parts ) < 2 ) {
				$this->log_debug( __METHOD__ . '(): token parts < 2; returning false.' );

				return false;
			}

			$body_64 = $parts[0];

			$body_json = base64_decode( $body_64 );
			if ( empty( $body_json ) ) {
				$this->log_debug( __METHOD__ . '(): base64_decode result empty; returning false.' );

				return false;
			}

			return json_decode( $body_json, true );

		}

		/**
		 * @param $token
		 *
		 * @return bool|Gravity_Flow_Assignee
		 */
		public function parse_token_assignee( $token ) {
			if ( empty( $token ) ) {
				return false;
			}

			$assignee_key = sanitize_text_field( $token['sub'] );

			$assignee = new Gravity_Flow_Assignee( $assignee_key );

			return $assignee;
		}

		public function log_event( $log_type, $event, $form_id = 0, $entry_id = 0, $log_value = '', $step_id = 0, $duration = 0, $assignee_id = 0, $assignee_type = '', $display_name = '' ) {
			global $wpdb;
			$wpdb->insert(
				$wpdb->prefix . 'gravityflow_activity_log',
				array(
					'log_object' => $log_type, // workflow, step, assignee - what did the activity happen to?
					'log_event' => $event, // started, ended, status - what activity happened?
					'log_value' => $log_value, // approved, rejected, complete - what value, if any, was generated?
					'date_created' => current_time( 'mysql', true ),
					'form_id' => $form_id,
					'lead_id' => $entry_id,
					'assignee_id' => $assignee_id,
					'assignee_type' => $assignee_type,
					'display_name' => $display_name,
					'feed_id' => $step_id,
					'duration' => $duration, // Time interval in seconds, if any.
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%d',
					'%s',
					'%s',
					'%s',
					'%d',
					'%d',
				)
			);
		}

		public function filter_wp_login() {
			unset( $_COOKIE['gflow_access_token'] );
			setcookie( 'gflow_access_token', null, - 1, $this->get_cookie_path() );
		}

		public function format_duration( $seconds ) {
			if ( method_exists( 'DateTime', 'diff' ) ) {
				$dtF           = new DateTime( '@0' );
				$dtT           = new DateTime( "@$seconds" );
				$date_interval = $dtF->diff( $dtT );
				$interval      = array();

				$interval = $this->maybe_add_date_intervals( $interval, $date_interval );

				if ( $date_interval->y == 0 && $date_interval->m == 0 ) {
					$interval = $this->maybe_add_time_intervals( $interval, $date_interval );
				}

				return join( ', ', $interval );
			} else {
				return esc_html( $seconds );
			}
		}

		/**
		 * Adds the year, month and day intervals, if appropriate.
		 *
		 * @param array $interval
		 * @param DateInterval $date_interval
		 *
		 * @return array
		 */
		public function maybe_add_date_intervals( $interval, $date_interval ) {
			if ( $date_interval->y > 0 ) {
				$years_format = _n( '%d year', '%d years', $date_interval->y, 'gravityflow' );
				$interval[]   = esc_html( sprintf( $years_format, $date_interval->y ) );
			}
			if ( $date_interval->m > 0 ) {
				$months_format = _n( '%d month', '%d months', $date_interval->m, 'gravityflow' );
				$interval[]    = esc_html( sprintf( $months_format, $date_interval->m ) );
			}
			if ( $date_interval->d > 0 ) {
				$days_format = esc_html__( '%dd', 'gravityflow' );
				$interval[]  = sprintf( $days_format, $date_interval->d );
			}

			return $interval;
		}

		/**
		 * Adds the hours, minutes and seconds intervals, if appropriate.
		 *
		 * @param array $interval
		 * @param DateInterval $date_interval
		 *
		 * @return array
		 */
		public function maybe_add_time_intervals( $interval, $date_interval ) {
			if ( $date_interval->h > 0 ) {
				$hours_format = esc_html__( '%dh', 'gravityflow' );
				$interval[]   = sprintf( $hours_format, $date_interval->h );
			}
			if ( $date_interval->d == 0 && $date_interval->h == 0 ) {
				if ( $date_interval->i > 0 ) {
					$minutes_format = esc_html__( '%dm', 'gravityflow' );
					$interval[]     = sprintf( $minutes_format, $date_interval->i );
				}
				if ( $date_interval->s > 0 ) {
					$seconds_format = esc_html__( '%ds', 'gravityflow' );
					$interval[]     = sprintf( $seconds_format, $date_interval->s );
				}
			}

			return $interval;
		}

		public function get_admin_icon_b64( $color = false ) {

			$svg_xml = '<?xml version="1.0" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg width="100%" height="100%" viewBox="0 20 581 640" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:1.41421;">
    <g id="Layer 1" transform="matrix(1,0,0,1,-309.5,-180)">
        <g transform="matrix(3.27114,0,0,3.27114,-738.318,-1054.55)">
            <path d="M377.433,481.219L434.396,514.29C444.433,519.437 453.741,520.595 464.421,516.392C477.161,511.373 485.868,500.993 486.898,487.138C487.756,476.115 483.38,464.791 475.273,457.241C465.622,448.191 452.797,446.132 440.272,449.392C437.999,449.864 434.096,451.494 431.179,452.566C429.935,452.995 428.905,453.381 428.262,453.467C423.286,453.982 420.584,447.333 425.045,444.716C434.61,439.097 447.607,437.339 456.272,438.197C466.738,439.355 476.603,443.901 484.152,451.322C493.117,460.201 497.75,472.126 497.407,484.736C496.935,502.623 486.855,517.936 470.469,525.228C460.432,529.646 449.108,530.461 438.685,527.33C434.953,526.214 432.723,524.885 429.334,522.997L371.9,490.784L369.026,495.717L362.163,478.645L380.393,476.158L377.433,481.219Z" style="fill:white;"/>
        </g>
        <g transform="matrix(3.27114,0,0,3.27114,-738.318,-1054.55)">
            <path d="M440.702,485.937L383.782,452.909C373.702,447.762 364.394,446.604 353.714,450.807C341.017,455.826 332.31,466.206 331.237,480.061C330.379,491.084 334.755,502.408 342.862,509.957C352.555,519.008 365.338,521.067 377.906,517.807C380.136,517.335 384.082,515.705 386.956,514.633C388.2,514.204 389.23,513.818 389.916,513.732C394.892,513.217 397.594,519.866 393.09,522.482C383.525,528.101 370.528,529.86 361.863,529.002C351.397,527.844 341.532,523.297 334.025,515.877C325.018,506.998 320.428,495.073 320.728,482.463C321.2,464.576 331.28,449.263 347.709,441.971C357.703,437.553 369.027,436.738 379.45,439.869C383.224,440.984 385.455,442.271 388.801,444.159L446.235,476.415L449.152,471.439L456.015,488.554L437.742,491.041L440.702,485.937Z" style="fill:white;"/>
        </g>
    </g>
</svg>';

			$icon = sprintf( 'data:image/svg+xml;base64,%s', base64_encode( $svg_xml ) );

			return $icon;
		}


		public function action_template_redirect() {
			global $wp_query;
			if ( isset( $wp_query->query_vars['paged'] ) && $wp_query->query_vars['paged'] > 0 ) {
				if ( $this->look_for_shortcode() ) {
					// Hack to fix paging on the status shortcode.
					remove_filter( 'template_redirect', 'redirect_canonical' );
				}
			}

		}

		function filter_cron_schedule( $schedules ) {
			$schedules['fifteen_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every Fifteen Minutes' ),
			);

			return $schedules;
		}

		public function get_setting( $setting_name, $default_value = '', $settings = false ) {
			return parent::get_setting( $setting_name, $default_value, $settings );
		}

		public function ajax_export_status() {
			if ( ! wp_verify_nonce( rgget( 'gravityflow_export_nonce' ), 'gravityflow_export_nonce' ) || ! GFAPI::current_user_can_any( 'gravityflow_status' ) ) {
				$response['status'] = 'error';
				$response['message'] = __( 'Not authorized', 'gravityflow' );
				$response_json = json_encode( $response );
				echo $response_json;
				die();
			}

			require_once( 'includes/pages/class-status.php' );

			$args['format'] = 'csv';
			$args['per_page'] = 50;
			$args['file_name'] = 'gravityflow-status-export';
			$result = Gravity_Flow_Status::render( $args );
			echo json_encode( $result );
			die();
		}

		public function ajax_download_export() {

			if ( ! wp_verify_nonce( rgget( 'nonce' ), 'gravityflow_download_export' ) || ! GFAPI::current_user_can_any( 'gravityflow_status' ) ) {
				$response['status'] = 'error';
				$response['message'] = __( 'Not authorized', 'gravityflow' );
				$response_json = json_encode( $response );
				echo $response_json;
				die();
			}

			$file_name = $_REQUEST['file_name'];

			$upload_dir = wp_upload_dir();

			$file_path = trailingslashit( $upload_dir['basedir'] ) . $file_name . '.csv';

			$file = '';

			if ( @file_exists( $file_path ) ) {
				$file = @file_get_contents( $file_path );
				@unlink( $file_path );
			}

			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $file_name . '-' . date( 'm-d-Y' ) . '.csv' );
			header( 'Expires: 0' );

			echo $file;
			die();
		}

		public function translate_navigation_label( $label_key ) {

			$custom_labels = get_option( 'gravityflow_app_settings_labels', array() );

			$custom_navigation_labels = rgar( $custom_labels, 'navigation' );


			$custom_label = rgar( $custom_navigation_labels, $label_key );

			if ( ! empty( $custom_label) ) {
				return $custom_label;
			}

			$default_labels = $this->get_default_navigation_labels();

			$label = rgar( $default_labels, $label_key );

			return empty( $label ) ?  $label_key :  $label;
		}

		public function get_default_navigation_labels() {
			return array(
				'workflow' => esc_html__( 'Workflow', 'gravityflow' ),
				'inbox' => esc_html__( 'Inbox', 'gravityflow' ),
				'submit' => esc_html__( 'Submit', 'gravityflow' ),
				'status' => esc_html__( 'Status', 'gravityflow' ),
				'support' => esc_html__( 'Support', 'gravityflow' ),
				'reports' => esc_html__( 'Reports', 'gravityflow' ),
				'activity' => esc_html__( 'Activity', 'gravityflow' ),
			);
		}

		public function translate_status_label( $status ) {
			$original_status = $status;

			$status = strtolower( $status );

			$custom_labels = get_option( 'gravityflow_app_settings_labels', array() );

			$status_labels = rgar( $custom_labels, 'status' );

			$custom_label = rgar( $status_labels, $status );

			if ( ! empty( $custom_label ) ) {
				return $custom_label;
			}

			switch ( $status ) {
				case 'pending' :
					return esc_html__( 'Pending', 'gravityflow' );

				case 'complete' :
					return esc_html__( 'Complete', 'gravityflow' );

				case 'approved' :
					return esc_html__( 'Approved', 'gravityflow' );

				case 'rejected' :
					return esc_html__( 'Rejected', 'gravityflow' );

				case 'expired' :
					return esc_html__( 'Expired', 'gravityflow' );

				case 'cancelled' :
					return esc_html__( 'Cancelled', 'gravityflow' );

			}

			$steps = Gravity_Flow_Steps::get_all();

			foreach ( $steps as $step ) {
				$status_configs = $step->get_status_config();
				foreach ( $status_configs as $status_config ) {
					if ( $status == strtolower( $status_config['status'] ) ) {
						return $step->get_status_label( $original_status );
					}
				}
			}
			return $original_status;
		}


		/**
		 * Hack to fix signature add-on in the front-end until GF_Field is implemented. The input name is rendered with the form ID in the front-end but editing is expected to be done in admin.
		 */
		public function maybe_save_signature() {

			//see if this is an entry and it needs to be updated. abort if not

			if ( ! ( RG_CURRENT_VIEW == 'entry' && rgpost( 'save' ) == 'Update' ) ) {
				return;
			}

			$lead_id = rgget( 'lid' );
			$form    = RGFormsModel::get_form_meta( rgget( 'id' ) );
			if ( empty( $lead_id ) ) {
				//lid is not always in the querystring when paging through entries, use same logic from entry detail page
				$filter         = rgget( 'filter' );
				$status         = in_array( $filter, array( 'trash', 'spam' ) ) ? $filter : 'active';
				$search         = rgget( 's' );
				$position       = rgget( 'pos' ) ? rgget( 'pos' ) : 0;
				$sort_direction = rgget( 'dir' ) ? rgget( 'dir' ) : 'DESC';

				$sort_field      = empty( $_GET['sort'] ) ? 0 : $_GET['sort'];
				$sort_field_meta = RGFormsModel::get_field( $form, $sort_field );
				$is_numeric      = $sort_field_meta['type'] == 'number';

				$star = $filter == 'star' ? 1 : null;
				$read = $filter == 'unread' ? 0 : null;

				$leads = RGFormsModel::get_leads( rgget( 'id' ), $sort_field, $sort_direction, $search, $position, 1, $star, $read, $is_numeric, null, null, $status );

				if ( ! $lead_id ) {
					$lead = ! empty( $leads ) ? $leads[0] : false;
				} else {
					$lead = RGFormsModel::get_lead( $lead_id );
				}

				if ( ! $lead ) {
					_e( "Oops! We couldn't find your lead. Please try again", 'gravityflow' );

					return;
				}
			}

			//loop through form fields, get the field name of the signature field
			foreach ( $form['fields'] as $field ) {
				if ( RGFormsModel::get_input_type( $field ) == 'signature' ) {
					//get field name so the value can be pulled from the post data
					$form_id = absint( $form['id'] );
					$input_name = 'input_' . $form_id . '_' . str_replace( '.', '_', $field['id'] );

					//when adding a new signature the data field will be populated
					if ( ! rgempty( "{$input_name}_data" ) ) {
						//new image added, save
						$filename = gf_signature()->save_signature( $input_name . '_data' );
					} else {
						//existing image edited
						$filename = rgpost( $input_name . '_signature_filename' );
					}
					$_POST[ "input_{$field['id']}" ] = $filename;

				}
			}

		}

		/**
		 *  Hack until the Signature Add-On uses GF_Field
		 *
		 * @param $form
		 *
		 * @return mixed
		 */
		public function delete_signature_script( $form ) {
			$form_id = absint( $form['id'] );
			?>

			<script type="text/javascript">
				function deleteSignature(leadId, fieldId) {

					if (!confirm(<?php echo json_encode( __( "Would you like to delete this file? 'Cancel' to stop. 'OK' to delete", 'gravityformssignature' ) ); ?>))
						return;

					jQuery.post(ajaxurl, {
						lead_id: leadId,
						field_id: fieldId,
						action: 'gf_delete_signature',
						gf_delete_signature: '<?php echo wp_create_nonce( 'gf_delete_signature' ) ?>'
					}, function (response) {
						if ( ! response ){
							jQuery('#input_' + fieldId + '_signature_filename').val('');
						}
						jQuery('#input_<?php echo $form_id; ?>_' + fieldId + '_signature_image').hide();
						jQuery('#input_<?php echo $form_id; ?>_' + fieldId + '_Container').show();
						jQuery('#input_<?php echo $form_id; ?>_' + fieldId + '_resetbutton').show();
					});
				}
			</script>

			<?php
			return $form;
		}

		public function can_duplicate_feed( $id ) {
			return true;
		}

		public function post_form_duplicated( $form_id, $new_id ) {

			$original_feeds = $this->get_feeds( $form_id );

			$this->import_gravityflow_feeds( $original_feeds, $new_id );

		}

		public function action_gform_post_add_entry( $entry, $form ) {
			if ( is_wp_error( $entry ) || ! empty( $entry['partial_entry_id'] ) ) {
				return;
			}

			$this->log_debug( __METHOD__ . '(): starting' );

			$api   = new Gravity_Flow_API( $form['id'] );
			$steps = $api->get_steps();

			if ( ! empty( $steps ) ) {
				gform_add_meta( $entry['id'], 'workflow_final_status', 'pending', $form['id'] );
				$this->log_debug( __METHOD__ . '(): triggering workflow for entry ID: ' . $entry['id'] );
				gravity_flow()->maybe_process_feed( $entry, $form );
				$api->process_workflow( $entry['id'] );
			}
		}

		/**
		 * Get the assignee key for the current access token or user.
		 *
		 * @return string|bool
		 */
		public function get_current_user_assignee_key() {
			$assignee_key = false;
			if ( $token = gravity_flow()->decode_access_token() ) {
				$assignee_key = sanitize_text_field( $token['sub'] );
			} elseif ( is_user_logged_in() ) {
				$assignee_key = 'user_id|' . get_current_user_id();
			}

			return $assignee_key;
		}

		public function settings_display_fields() {
			$mode_field = array(
				'name'     => 'display_fields_mode',
				'label'    => '',
				'type'     => 'select',
				'default_value' => 'all_fields',
				'onchange' => 'jQuery(this).siblings(".gravityflow_display_fields_selected_container").toggle(this.value=="selected_fields");',
				'choices' => array(
					array(
						'label' => __( 'All fields', 'gravityflow' ),
						'value' => 'all_fields',
					),
					array(
						'label' => __( 'Selected fields', 'gravityflow' ),
						'value' => 'selected_fields',
					),
				),
			);

			$form = $this->get_current_form();

			$fields = ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) ? $form['fields'] : array();

			$fields_as_choices = array();

			$has_product_field = false;

			foreach ( $fields as $field ) {
				/* @var GF_Field $field */
				if ( in_array( $field->type, array( 'page', 'section', 'captcha' ) ) ) {
					continue;
				}
				$fields_as_choices[] = array( 'label' => $field->get_field_label( false, null ), 'value' => $field->id );
				$has_product_field = GFCommon::is_product_field( $field->type ) ? true : $has_product_field;
			}

			$mode_value = $this->get_setting( 'display_fields_mode', 'all_fields' );

			$multiselect_field = array(
				'name'     => 'display_fields_selected[]',
				'label'    => __( 'Except', 'gravityflow' ),
				'type'     => 'select',
				'multiple' => 'multiple',
				'class' => 'gravityflow-multiselect-ui',
				'choices' => $fields_as_choices,
			);
			$this->settings_select( $mode_field );
			$style = $mode_value == 'selected_fields' ? '' :  'style="display:none;"';
			echo '<div class="gravityflow_display_fields_selected_container" ' . $style . '>';
			$this->settings_select( $multiselect_field );
			echo '</div>';

			if ( $has_product_field ) {

				$display_summary_field = array(
					'name' => 'display_order_summary',
					'type' => 'checkbox',
					'choices' => array(
						array(
							'label' => esc_html__( 'Order Summary', 'gravityflow' ),
							'name' => 'display_order_summary',
							'default_value' => '1',
						),
					),
				);
				echo '<div style="margin-top:5px;">';
				$this->settings_checkbox( $display_summary_field );
				echo '</div>';
			}
		}

		/**
		 * Display or return the markup for the generic_map field type.
		 *
		 * @param array $field The field properties.
		 * @param bool|true $echo Should the setting markup be echoed.
		 *
		 * @return string|void
		 */
		public function settings_generic_map( $field, $echo = true ) {

			$html = '';

			// Support for dynamic field map migrations
			if ( isset( $field['field_map'] ) ) {
				$field['key_choices'] = $field['field_map'];
			}

			$value_field = $key_field = $custom_key_field = $custom_value_field = $field;

			/* Setup key field drop down */
			$key_field['choices'] = ( isset( $field['key_choices'] ) ) ? $field['key_choices'] : null;
			$key_field['name'] .= '_key';
			$key_field['class'] = 'key key_{i}';
			$key_field['style'] = 'width:200px;';

			/* Setup custom key text field */
			$custom_key_field['name'] .= '_custom_key_{i}';
			$custom_key_field['class'] = 'custom_key custom_key_{i}';
			$custom_key_field['value'] = '{custom_key}';

			/* Setup value field drop down */
			$value_field['choices'] = ( isset( $field['value_choices'] ) ) ? $field['value_choices'] : null;
			$value_field['name'] .= '_custom_value';
			$value_field['class'] = 'value value_{i}';
			$value_field['style'] = 'width:200px;';

			/* Setup custom value text field */
			$custom_value_field['name'] .= '_custom_value_{i}';
			$custom_value_field['class'] = 'custom_value custom_value_{i}';
			$custom_value_field['value'] = '{custom_value}';

			/* Remove unneeded values */
			$unneeded_values = array( 'field_map', 'key_choices', 'value_choices', 'callback' );
			foreach ( $unneeded_values as $unneeded_value ) {
				unset( $field[ $unneeded_value ] );
				unset( $value_field[ $unneeded_value ] );
				unset( $key_field[ $unneeded_value ] );
				unset( $custom_key_field[ $unneeded_value ] );
				unset( $custom_value_field[ $unneeded_value ] );
			}

			//add on errors set when validation fails
			if ( $this->field_failed_validation( $field ) ) {
				$html .= $this->get_error_icon( $field );
			}

			$html .= $this->get_generic_map_table( $field, $key_field, $custom_key_field, $value_field, $custom_value_field );
			$html .= $this->settings_hidden( $field, false );
			$html .= $this->get_generic_map_script( $field, $key_field['name'], $value_field['name'] );

			if ( $echo ) {
				echo $html;
			}

			return $html;

		}

		/**
		 * Return the markup for the table containing the generic_map settings.
		 *
		 * @param array $field The generic_map field properties.
		 * @param array $key_field The properties for the key field drop down.
		 * @param array $custom_key_field The properties for the key field text input.
		 * @param array $value_field The properties for the value field drop down.
		 * @param array $custom_value_field The properties for the value field text input.
		 *
		 * @return string
		 */
		public function get_generic_map_table( $field, $key_field, $custom_key_field, $value_field, $custom_value_field ) {
			$key_field_title   = isset( $field['key_field_title'] ) ? $field['key_field_title'] : esc_html__( 'Key', 'gravityflow' );
			$value_field_title = isset( $field['value_field_title'] ) ? $field['value_field_title'] : esc_html__( 'Value', 'gravityflow' );

			$html = '
            <table class="settings-field-map-table" cellspacing="0" cellpadding="0">
            	<thead>
					<tr>
						<th>' . $key_field_title . '</th>
						<th>' . $value_field_title . '</th>
					</tr>
				</thead>
                <tbody class="repeater">
	                <tr>
	                    ' . $this->get_generic_map_field( 'key', $key_field, $custom_key_field ) .
			        $this->get_generic_map_field( 'value', $value_field, $custom_value_field ) . '
						<td>
							{buttons}
						</td>
	                </tr>
                </tbody>
            </table>';

			return $html;
		}

		/**
		 * Return the inline script for the generic_map field.
		 *
		 * @param array $field The generic_map field properties.
		 * @param string $key_field_name The name of the key field.
		 * @param string $value_field_name The name of the value field.
		 *
		 * @return string
		 */
		public function get_generic_map_script( $field, $key_field_name, $value_field_name ) {
			$limit = empty( $field['limit'] ) ? 0 : $field['limit'];

			$html = "
			<script type=\"text/javascript\">

				var dynamicGenericMap" . esc_attr( $field['name'] ) . " = new GravityFlowGenericMap({

					'baseURL':      '" . GFCommon::get_base_url() . "',
					'fieldId':      '" . esc_attr( $field['name'] ) . "',
					'fieldName':    '" . $field['name'] . "',
					'keyFieldName': '" . $key_field_name . "',
					'valueFieldName': '" . $value_field_name . "',
					'limit':        '" . $limit . "'

				});

			</script>";

			return $html;
		}

		/**
		 * Prepares the markup for the generic_map key and value fields.
		 *
		 * @param string $type The field type being prepared; key or value.
		 * @param array $select_field The drop down field properties.
		 * @param array $text_field The text field properties.
		 *
		 * @return string
		 */
		public function get_generic_map_field( $type, $select_field, $text_field ) {
			/* Build key cell based on available field map choices */
			if ( empty( $select_field['choices'] ) ) {

				/* Set key field value to "gf_custom" so custom key is used. */
				$select_field['value'] = 'gf_custom';

				/* Build HTML string */
				$html = sprintf(
					'<td>%s<div class="custom-%s-container">%s</div></td>',
					$this->settings_hidden( $select_field, false ),
					$type,
					$this->settings_text( $text_field, false )
				);

			} else {

				/* Ensure field map array has a custom key option. */
				$has_gf_custom = false;
				foreach ( $select_field['choices'] as $choice ) {
					if ( $this->is_gf_custom_choice( $choice ) ) {
						$has_gf_custom = true;
					}
					if ( rgar( $choice, 'choices' ) ) {
						foreach ( $choice['choices'] as $sub_choice ) {
							if ( $this->is_gf_custom_choice( $sub_choice ) ) {
								$has_gf_custom = true;
							}
						}
					}
				}

				if ( ! $has_gf_custom ) {
					$select_field = $this->maybe_add_custom_choice( $select_field, $type );
				}

				/* Build HTML string */
				$html = sprintf(
					'<th>%s<div class="custom-%s-container"><a href="#" class="custom-%s-reset">%s</a>%s</div></th>',
					$this->settings_select( $select_field, false ),
					$type,
					$type,
					esc_html__( 'Reset', 'gravityflow' ),
					$this->settings_text( $text_field, false )
				);

			}

			return $html;
		}

		/**
		 * Determines if the current choice is the gf_custom choice.
		 *
		 * @param array $choice The choice properties.
		 *
		 * @return bool
		 */
		public function is_gf_custom_choice( $choice ) {
			if ( 'gf_custom' === rgar( $choice, 'name' ) || rgar( $choice, 'value' ) == 'gf_custom' ) {
				return true;
			}

			return false;
		}

		/**
		 * Adds the gf_custom choice to the field, if applicable.
		 *
		 * @param array $select_field The drop down field properties.
		 * @param string $type The field type being prepared; key or value.
		 *
		 * @return array
		 */
		public function maybe_add_custom_choice( $select_field, $type ) {
			if ( $type == 'key' ) {
				$enable_custom = isset( $select_field['enable_custom_key'] ) ? (bool) $select_field['enable_custom_key'] : ! (bool) rgar( $select_field, 'disable_custom' );
				$label         = esc_html__( 'Add Custom Key', 'gravityflow' );
			} else {
				$enable_custom = isset( $select_field['enable_custom_value'] ) ? (bool) $select_field['enable_custom_value'] : false;
				$label         = esc_html__( 'Add Custom Value', 'gravityflow' );
			}

			if ( $enable_custom ) {
				$select_field['choices'][] = array(
					'label' => $label,
					'value' => 'gf_custom'
				);
			}

			return $select_field;
		}

		/**
		 * Display or return the markup for the feed_condition field type.
		 *
		 * @since 1.7.1-dev Added support for logic based on the entry meta.
		 *
		 * @param array $field The field properties.
		 * @param bool  $echo  Should the setting markup be echoed.
		 *
		 * @return string
		 */
		public function settings_feed_condition( $field, $echo = true ) {
			$entry_meta  = array_merge( $this->get_feed_condition_entry_meta(), $this->get_feed_condition_entry_properties() );
			$find        = 'var feedCondition';
			$replacement = sprintf( 'var entry_meta = %s; %s', json_encode( $entry_meta ), $find );
			$html        = str_replace( $find, $replacement, parent::settings_feed_condition( $field, false ) );

			if ( $echo ) {
				echo $html;
			}

			return $html;
		}

		/**
		 * Get the entry meta for use with the feed_condition setting.
		 *
		 * @since 1.7.1-dev
		 *
		 * @return array
		 */
		public function get_feed_condition_entry_meta() {
			$step_id    = absint( rgget( 'fid' ) );
			$form_id    = absint( rgget( 'id' ) );
			$entry_meta = GFFormsModel::get_entry_meta( $form_id );

			unset( $entry_meta['workflow_final_status'], $entry_meta['workflow_step'], $entry_meta[ 'workflow_step_status_' . $step_id ] );

			return $entry_meta;
		}

		/**
		 * Get the entry properties for use with the feed_condition setting.
		 *
		 * @since 1.7.1-dev
		 *
		 * @return array
		 */
		public function get_feed_condition_entry_properties() {
			$user_choices = array();

			if ( $this->is_form_settings() ) {
				$args = apply_filters( 'gform_filters_get_users', array(
					'number' => 200,
					'fields' => array( 'ID', 'user_login' )
				) );

				$users = get_users( $args );
				foreach ( $users as $user ) {
					$user_choices[] = array( 'text' => $user->user_login, 'value' => $user->ID );
				}
			}

			return array(
				'ip'             => array(
					'label'  => esc_html__( 'User IP', 'gravityflow' ),
					'filter' => array(
						'operators' => array( 'is', 'isnot', '>', '<', 'contains' ),
					),
				),
				'source_url'     => array(
					'label'  => esc_html__( 'Source URL', 'gravityflow' ),
					'filter' => array(
						'operators' => array( 'is', 'isnot', '>', '<', 'contains' ),
					),
				),
				'payment_status' => array(
					'label'  => esc_html__( 'Payment Status', 'gravityflow' ),
					'filter' => array(
						'operators' => array( 'is', 'isnot' ),
						'choices'   => array(
							array(
								'text'  => esc_html__( 'Paid', 'gravityflow' ),
								'value' => 'Paid',
							),
							array(
								'text'  => esc_html__( 'Processing', 'gravityflow' ),
								'value' => 'Processing',
							),
							array(
								'text'  => esc_html__( 'Failed', 'gravityflow' ),
								'value' => 'Failed',
							),
							array(
								'text'  => esc_html__( 'Active', 'gravityflow' ),
								'value' => 'Active',
							),
							array(
								'text'  => esc_html__( 'Cancelled', 'gravityflow' ),
								'value' => 'Cancelled',
							),
							array(
								'text'  => esc_html__( 'Pending', 'gravityflow' ),
								'value' => 'Pending',
							),
							array(
								'text'  => esc_html__( 'Refunded', 'gravityflow' ),
								'value' => 'Refunded',
							),
							array(
								'text'  => esc_html__( 'Voided', 'gravityflow' ),
								'value' => 'Voided',
							),
						),
					),
				),
				'payment_amount' => array(
					'label'  => esc_html__( 'Payment Amount', 'gravityflow' ),
					'filter' => array(
						'operators' => array( 'is', 'isnot', '>', '<', 'contains' ),
					),
				),
				'transaction_id' => array(
					'label'  => esc_html__( 'Transaction ID', 'gravityflow' ),
					'filter' => array(
						'operators' => array( 'is', 'isnot', '>', '<', 'contains' ),
					),
				),
				'created_by' => array(
					'label'  => esc_html__( 'Created By', 'gravityflow' ),
					'filter' => array(
						'operators' => array( 'is', 'isnot' ),
						'choices'   => $user_choices,
					),
				),
			);
		}

		/**
		 * Fork of GFCommon::evaluate_conditional_logic which supports evaluating logic based on entry properties.
		 *
		 * @since 1.7.1-dev
		 *
		 * @param array $logic The conditional logic to be evaluated.
		 * @param array $form The current form.
		 * @param array $entry The current entry.
		 *
		 * @return bool
		 */
		public function evaluate_conditional_logic( $logic, $form, $entry ) {
			if ( ! $logic || ! is_array( rgar( $logic, 'rules' ) ) ) {
				return true;
			}

			$entry_meta      = array_merge( $this->get_feed_condition_entry_meta(), $this->get_feed_condition_entry_properties() );
			$entry_meta_keys = array_keys( $entry_meta );
			$match_count     = 0;

			if ( is_array( $logic['rules'] ) ) {
				foreach ( $logic['rules'] as $rule ) {

					if ( in_array( $rule['fieldId'], $entry_meta_keys ) ) {
						$is_value_match = GFFormsModel::is_value_match( rgar( $entry, $rule['fieldId'] ), $rule['value'], $rule['operator'], null, $rule, $form );
					} else {
						$source_field   = GFFormsModel::get_field( $form, $rule['fieldId'] );
						$field_value    = empty( $entry ) ? GFFormsModel::get_field_value( $source_field, array() ) : GFFormsModel::get_lead_field_value( $entry, $source_field );
						$is_value_match = GFFormsModel::is_value_match( $field_value, $rule['value'], $rule['operator'], $source_field, $rule, $form );
					}

					if ( $is_value_match ) {
						$match_count ++;
					}
				}
			}

			$do_action = ( $logic['logicType'] == 'all' && $match_count == sizeof( $logic['rules'] ) ) || ( $logic['logicType'] == 'any' && $match_count > 0 );

			return $do_action;
		}

		/**
		 * Target for the gform_pre_replace_merge_tags filter. Replaces the workflow_timeline and created_by merge tags.
		 *
		 *
		 * @param string $text
		 * @param array $form
		 * @param array $entry
		 * @param bool $url_encode
		 * @param bool $esc_html
		 * @param bool $nl2br
		 * @param string $format
		 *
		 * @return string
		 */
		public function replace_variables( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {

			if ( strpos( $text, '{' ) === false  || empty( $entry ) ) {
				return $text;
			}

			$step = gravity_flow()->get_current_step( $form, $entry );
			$args = compact( 'form', 'entry', 'url_encode', 'esc_html', 'nl2br', 'format', 'step' );
			$merge_tags = Gravity_Flow_Merge_Tags::get_all( $args );

			foreach ( $merge_tags as $merge_tag ) {
				$text = $merge_tag->replace( $text );
			}

			return $text;
		}

		public function fields_have_conditional_logic( $form ) {
			$has_conditional_logic = false;
			if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					if ( is_array( $field->conditionalLogic ) ) {
						$has_conditional_logic = true;
						break;
					}
				}
			}
			return $has_conditional_logic;
		}

		public function pages_have_conditional_logic( $form ) {
			$has_conditional_logic = false;
			if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					if ( $field->type == 'page' && is_array( $field->conditionalLogic ) ) {
						$has_conditional_logic = true;
						break;
					}
				}
			}
			return $has_conditional_logic;
		}

		/**
		 * Returns the current form object based on the id query var. Otherwise returns false
		 */
		public function get_current_form() {

			return rgempty( 'id', $_GET ) ? false : GFFormsModel::get_form_meta( rgget( 'id' ) );
		}

		/**
		 * Returns the mergeTagLabels property of the strings for form-settings.js.
		 *
		 * @since 1.4.3-dev
		 *
		 * @used-by Gravity_Flow::scripts()
		 * @uses    esc_html__()
		 *
		 * @return array
		 */
		public function get_form_settings_js_merge_tag_labels() {
			return array(
				'group'                  => esc_html__( 'Workflow', 'gravityflow' ),
				'workflow_entry_link'    => esc_html__( 'Entry Link', 'gravityflow' ),
				'workflow_entry_url'     => esc_html__( 'Entry URL', 'gravityflow' ),
				'workflow_inbox_link'    => esc_html__( 'Inbox Link', 'gravityflow' ),
				'workflow_inbox_url'     => esc_html__( 'Inbox URL', 'gravityflow' ),
				'workflow_cancel_link'   => esc_html__( 'Cancel Link', 'gravityflow' ),
				'workflow_cancel_url'    => esc_html__( 'Cancel URL', 'gravityflow' ),
				'workflow_note'          => esc_html__( 'Note', 'gravityflow' ),
				'workflow_timeline'      => esc_html__( 'Timeline', 'gravityflow' ),
				'assignees'              => esc_html__( 'Assignees', 'gravityflow' ),
				'workflow_approve_link'  => esc_html__( 'Approve Link', 'gravityflow' ),
				'workflow_approve_url'   => esc_html__( 'Approve URL', 'gravityflow' ),
				'workflow_approve_token' => esc_html__( 'Approve Token', 'gravityflow' ),
				'workflow_reject_link'   => esc_html__( 'Reject Link', 'gravityflow' ),
				'workflow_reject_url'    => esc_html__( 'Reject URL', 'gravityflow' ),
				'workflow_reject_token'  => esc_html__( 'Reject Token', 'gravityflow' ),
			);
		}

		/**
		 * Target for the gravityview_adv_filter_field_filters filter.
		 *
		 * Adds the Gravity Flow assignees as field filters.
		 *
		 * @since 1.5.1-dev
		 *
		 * @param $field_filters
		 * @param $post_id
		 *
		 * @return array
		 */
		public function filter_gravityview_adv_filter_field_filters( $field_filters, $post_id ) {
			$form_id = gravityview_get_form_id( $post_id );

			$steps = $this->get_steps( $form_id );

			$workflow_assignees = array();

			foreach ( $steps as $step ) {
				if ( empty( $step ) || ! $step->is_active() ) {
					continue;
				}

				$step_assignees = $step->get_assignees();

				$step_assignee_choices = array();

				foreach ( $step_assignees as $assignee ) {
					$step_assignee_choices[] = array(
						'value' => $assignee->get_key(),
						'text'  => $assignee->get_display_name(),
					);
				}

				$workflow_assignees = array_merge( $workflow_assignees, $step_assignee_choices );
			}
			// Remove duplicate assignees
			$workflow_assignees = array_map( 'unserialize', array_unique( array_map( 'serialize', $workflow_assignees ) ) );
			$workflow_assignees = array_values( $workflow_assignees );

			$workflow_assignees[] = array(
				'value' => 'current_user',
				'text'  => esc_html__( 'Current User', 'gravityflow' ),
			);

			$filter                    = array();
			$filter['key']             = 'workflow_assignee';
			$filter['preventMultiple'] = false;
			$filter['text']            = esc_html__( 'Workflow Assignee', 'gravityflow' );
			$filter['operators']       = array( 'is' );
			$filter['values']          = $workflow_assignees;
			$field_filters[]           = $filter;

			return $field_filters;
		}

		/**
		 * Target for the gravityview_search_criteria filter.
		 *
		 * @since 1.5.1-dev
		 *
		 * @param $search_criteria
		 * @param $form_ids
		 * @param $view_id
		 *
		 * @return mixed
		 */
		public function filter_gravityview_search_criteria( $search_criteria, $form_ids, $view_id ) {
			if ( isset( $search_criteria['search_criteria']['field_filters'] ) && is_array( $search_criteria['search_criteria']['field_filters'] ) ) {
				$field_filters = $search_criteria['search_criteria']['field_filters'];
				foreach ( $field_filters as &$field_filter ) {
					if ( is_array( $field_filter ) && $field_filter['key'] == 'workflow_assignee' ) {
						$assignee_key          = $field_filter['value'] == 'current_user' ? gravity_flow()->get_current_user_assignee_key() : $field_filter['value'];
						$field_filter['key']   = 'workflow_' . str_replace( '|', '_', $assignee_key );
						$field_filter['value'] = 'pending';
					}
				}
				$search_criteria['search_criteria']['field_filters'] = $field_filters;
			}

			return $search_criteria;
		}

		/**
		 * Target for the gravityview/common/get_entry/check_entry_display filter.
		 *
		 * Performs the permission check if a Gravity Flow assignee key is specified in the criteria.
		 *
		 * @since 1.5.1-dev
		 *
		 * @param $check_entry_display
		 * @param $entry
		 *
		 * @return bool
		 */
		public function filter_gravityview_common_get_entry_check_entry_display( $check_entry_display, $entry ) {

			global $_fields;

			$criteria = GVCommon::calculate_get_entries_criteria();

			$keys = array();

			// Add the workflow assignee entry meta to the entry.
			// This is necessary because assignee meta keys are not registered so they're not added automatically to the entry.
			if ( isset( $criteria['search_criteria']['field_filters'] ) && is_array( $criteria['search_criteria']['field_filters'] ) ) {
				foreach ( $criteria['search_criteria']['field_filters'] as $filter ) {
					if ( is_array( $filter ) && strpos( $filter['key'], 'workflow_' ) !== false && ! isset( $entry[ $filter['key'] ] ) ) {
						$meta_value              = gform_get_meta( $entry['id'], $filter['key'] );
						$entry[ $filter['key'] ] = $meta_value;
						$keys[]                  = $filter['key'];
					}
				}
			}

			if ( empty( $keys ) ) {
				return $check_entry_display;
			}

			// Hack to ensure that the meta values for assignees are returned when rule matching in GVCommon::check_entry_display().
			foreach ( $keys as $key ) {
				$_fields[ $entry['form_id'] . '_' . $key ] = array( 'id' => $key );
			}

			$entry = GVCommon::check_entry_display( $entry );

			// Clean up the hack
			foreach ( $keys as $key ) {
				unset( $_fields[ $entry['form_id'] . '_' . $key ] );
			}

			// GVCommon::check_entry_display() returns the entry if permission is granted otherwise false or maybe a WP_Error instance.
			// If permission is granted then we can tell GravityView not to check permissions again.
			$check_entry_display = ! $entry || is_wp_error( $entry );

			return $check_entry_display;
		}

		/**
		 * Register the Gravity Flow capabilities group with the Members plugin.
		 *
		 * @since 1.8.1-dev
		 */
		public function members_register_cap_group() {
			members_register_cap_group(
				'gravityflow',
				array(
					'label' => $this->get_short_title(),
					'icon'  => 'dashicons-gravityflow',
					'caps'  => array(),
				)
			);
		}

		/**
		 * Register the capabilities and their human readable labels with the Members plugin.
		 *
		 * @since 1.8.1-dev
		 */
		public function members_register_caps() {
			$caps = $this->get_members_caps();

			foreach ( $caps as $cap => $label ) {
				members_register_cap(
					$cap,
					array(
						'label' => $label,
						'group' => 'gravityflow'
					)
				);
			}
		}

		/**
		 * Get the capabilities and their human readable labels to be registered with the Members plugin.
		 *
		 * @since 1.8.1-dev
		 */
		public function get_members_caps() {
			$status_label = $this->translate_navigation_label( 'status' );
			$caps         = array(
				'gravityflow_inbox'                         => $this->translate_navigation_label( 'inbox' ),
				'gravityflow_workflow_detail_admin_actions' => __( 'Entry Detail Admin Actions', 'gravityflow' ),
				'gravityflow_submit'                        => $this->translate_navigation_label( 'submit' ),
				'gravityflow_status'                        => $status_label,
				'gravityflow_status_view_all'               => $status_label . ' - ' . __( 'View All', 'gravityflow' ),
				'gravityflow_reports'                       => $this->translate_navigation_label( 'reports' ),
				'gravityflow_activity'                      => $this->translate_navigation_label( 'activity' ),
				'gravityflow_settings'                      => __( 'Manage Settings', 'gravityflow' ),
				'gravityflow_uninstall'                     => __( 'Uninstall', 'gravityflow' ),
				'gravityflow_create_steps'                  => __( 'Manage Form Steps', 'gravityflow' ),
			);

			return apply_filters( 'gravityflow_members_capabilities', $caps );
		}

		/**
		 * Renders the header for the tabs UI.
		 *
		 * Fixes an issue in the add-on framework where tab links don't clean existing params.
		 *
		 * @param        $tabs
		 * @param        $current_tab
		 * @param        $title
		 * @param string $message
		 */
		public function app_tab_page_header( $tabs, $current_tab, $title, $message = '' ) {

			// Print admin styles
			wp_print_styles( array( 'jquery-ui-styles', 'gform_admin' ) );

			?>

			<div class="wrap <?php echo GFCommon::get_browser_class() ?>">

				<?php if ( $message ) { ?>
					<div id="message" class="updated"><p><?php echo $message; ?></p></div>
				<?php } ?>

				<h2><?php echo esc_html( $title ) ?></h2>

				<div id="gform_tab_group" class="gform_tab_group vertical_tabs">
					<ul id="gform_tabs" class="gform_tabs">
						<?php
						foreach ( $tabs as $tab ) {
							if ( isset( $tab['permission'] ) && ! $this->current_user_can_any( $tab['permission'] ) ) {
								continue;
							}
							$label = isset( $tab['label'] ) ? $tab['label'] : $tab['name'];
							?>
							<li <?php echo urlencode( $current_tab ) == $tab['name'] ? "class='active'" : '' ?>>
								<a href="<?php echo esc_url( add_query_arg( array(
									'page' => 'gravityflow_settings',
									'view' => $tab['name'],
								), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ) ?></a>
							</li>
							<?php
						}
						?>
					</ul>

					<div id="gform_tab_container" class="gform_tab_container">
						<div class="gform_tab_content" id="tab_<?php echo $current_tab ?>">

		<?php
		}

		/**
		 * Get the site cookie path.
		 *
		 * @return string
		 */
		public function get_cookie_path() {
			$site_cookie_path = SITECOOKIEPATH;

			/**
			 * Allow the site cookie path to be overridden.
			 *
			 * @since 1.9.2-dev
			 *
			 * @param string $site_cookie_path The site cookie path.
			 */
			return apply_filters( 'gravityflow_site_cookie_path', $site_cookie_path );
		}
	}
}
