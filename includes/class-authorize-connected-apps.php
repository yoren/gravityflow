<?php

/**
 * Gravity Flow
 *
 * @package     GravityFlow
 * @copyright   Copyright (c) 2015-2017, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.8.1
 */
class Authorize_Connected_Apps {

	/**
	 * Holds connection status keys and details
	 *
	 * @var array
	 **/
	protected $oauth_connection_statuses = array();
	/**
	 * A holder for the instance
	 *
	 * @var object
	 **/
	public static $_instance;

	/**
	 * Constructor for class Authorize Connected Apps adds actions and sets up connection statuses
	 *
	 * @return void
	 **/
	function __construct() {
		$this->oauth_connection_statuses = array(
			'get_temporary_credentials' => __( 'Using Consumer Key and Secret to Get Temporary Credentials', 'gravityflow' ),
			'user_authorize_app' => __( 'Redirecting for user authorization - you may need to login first', 'gravityflow' ),
			'get_access_credentials' => __( 'Using credentials from user authorization to get permanent credentials', 'gravityflow' ),
		);
		add_action( 'admin_init', array( $this, 'process_auth_flow' ) );
		add_action( 'wp_ajax_gravity_flow_reauth_app', array( $this, 'reauthorize_app' ) );
	}

	/**
	 * A handy little function to wrap status messages in styled spans, saves storing html in database
	 *
	 * @param string $message Incoming status message should be one-two word string.
	 *
	 * @return string
	 **/
	public function wrap_status_message( $message ) {
		return '<span class="oauth-' . strtolower( str_replace( ' ', '-', $message ) ) . '">' . esc_html( $message ) . '</span>';
	}

	/**
	 * Doesn't actually reauth the app, but clears the current credentials so that it can be reauthed
	 *
	 * @return void
	 **/
	function reauthorize_app() {
		check_admin_referer( 'gflow_settings_js', 'security' );
		$connected_apps = get_option( 'gravityflow_app_settings_connected_apps' );
		$app = $connected_apps[ sanitize_text_field( $_POST['app'] ) ];
		$new_app = array(
			'app_id' => $app['app_id'],
			'app_name' => $app['app_name'],
			'api_url' => $app['api_url'],
			'oauth_type' => $app['oauth_type'],
			'status' => 'Not Verified',
		);
		$connected_apps[ sanitize_text_field( $_POST['app'] ) ] = $new_app;
		update_option( 'gravityflow_app_settings_connected_apps', $connected_apps );
		wp_send_json( array( 
			'success' => true, 
			'app' => 'ready for reauth' 
		) );
	}


	/**
	 * Processes Auth settings, initial run creates unique_id and app
	 * subsequent run processes the authorization
	 *
	 * @return void
	 **/
	function process_auth_flow() {
		$this->connected_apps = get_option( 'gravityflow_app_settings_connected_apps' );
		if ( $_POST['gflow_authorize_app'] == 'Authorize App' || isset( $_GET['oauth_verifier'] ) ) {
			$this->app_ident = sanitize_text_field( $_GET['app'] );
			$this->current_app = $this->connected_apps[ $this->app_ident ];
			if ( $_POST['gflow_authorize_app'] == 'Authorize App' && !wp_verify_nonce( $_REQUEST['_wpnonce'], 'nonce_authorize_app' ) ) {
				wp_die( 'Failed Security Check - refresh page and try again' );
			}
			if ( isset( $_POST['oauth_type'] ) ) {
				$process_func = sprintf( 'process_auth_%s', sanitize_text_field( $_POST['oauth_type'] ) );
			} else {
				$process_func = sprintf( 'process_auth_%s', $this->current_app['oauth_type'] );
			}
			if ( is_callable( array( $this, $process_func ) ) ) {
				$this->$process_func();
			}
			else {
				gravity_flow()->log_debug( __METHOD__ . '() - processing function ' . $process_func . ' not callable' );
			}
				
		} else if ( $_POST['gflow_add_app'] == 'Next' ) {
			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'nonce_create_app' ) ) {
				wp_die( 'Failed Security Check - refresh page and try again' );
			}

			$this->connected_apps = get_option( 'gravityflow_app_settings_connected_apps' );
			$app_name = $_POST['app_name'];
			$unique_name = $this->get_unique_name( $app_name );			
			$app_auth_type = $_POST['oauth_type'];
			$app_api_url = $_POST['api_url'];

			$this->connected_apps[$unique_name] = array(
				'app_id' => $unique_name,
				'app_name' => $app_name,
				'api_url' => $app_api_url,
				'oauth_type' => $app_auth_type,
				'status' => 'Not Verified',
			);
			update_option( 'gravityflow_app_settings_connected_apps', $this->connected_apps );
			wp_safe_redirect( add_query_arg( 'app', esc_js( $unique_name ) ) );
		}

	}

	/**
	 * Gives unique name to the application for storage
	 *
	 * @param string $app_name Name to generate unique ident from.
	 * @return string
	 **/
	function get_unique_name( $app_name ) {
		$unique_name = md5( $app_name );

		if ( '' !== $this->connected_apps && is_array( $this->connected_apps ) ) {
			$connected_app_keys = array_keys( $this->connected_apps );
			if ( in_array( $unique_name, $connected_app_keys, true ) ) {
				while ( in_array( $unique_name, $connected_app_keys, true ) ) {
					$unique_name = md5( $unique_name );
				}
			}
		}
		return $unique_name;
	}


	/**
	 * Handles OAuth1 authentication
	 * !Note - the callback_url in the client constructor must be registered in the WP-Api application callback_url
	 * So for the docs the current web address of the step setting form is taken and used to setup the application and put into
	 * the callback url field.
	 *
	 * @return void
	 **/
	public function process_auth_wp_oauth1() {
		require( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'class-gravityflow-oauth1-client.php' );

		if ( isset( $_POST['consumer_key'] ) || isset( $_POST['app_name'] ) ) {
			$reauth = false;
			if ( $_POST['app_name'] !== $current_app['app_name'] || $_POST['api_url'] !== $current_app['api_url'] ) {
				$this->connected_apps[ $this->app_ident ]['app_name'] = sanitize_text_field( $_POST['app_name'] );
				$this->connected_apps[ $this->app_ident ]['api_url'] = sanitize_text_field( $_POST['api_url'] );
			}
			//$app_ident = sanitize_text_field( $_POST['authorizing_app'] );
			if ( $_POST['consumer_key'] !== $current_app['consumer_key'] || $_POST['consumer_secret'] !== $current_app['consumer_secret'] ) {
				$this->connected_apps[ $this->app_ident ]['consumer_key'] = sanitize_text_field( $_POST['consumer_key'] );
				$this->connected_apps[ $this->app_ident ]['consumer_secret'] = sanitize_text_field( $_POST['consumer_secret'] );
				$reauth = true;
			}
			update_option( 'gravityflow_app_settings_connected_apps', $this->connected_apps );
			if ( ! $reauth ) {
				wp_safe_redirect( remove_query_arg() );
			}
		}
		$statuses = array_keys( $this->oauth_connection_statuses );
		$status = $statuses[0];
		gravity_flow()->log_debug( __METHOD__ . '() - building oauth config for ' . $app_ident . ' from ' . print_r( $this->connected_apps[ $this->app_ident ], true) );

		$this->setup_oauth1_client();
		$this->status_keys = array_keys( $this->oauth_connection_statuses );
		if ( ! isset( $_GET['oauth_verifier'] ) ) {
			$this->process_oauth1_outward_leg();
		} else {
			$this->process_oauth1_return_legs(); 
		}

	}

	/**
	 * Process oauth1 - authorize returning user
	 *
	 * @return void
	 **/
	function process_oauth1_return_legs() {
		$status = $this->status_keys[1];
		$app_ident = $this->app_ident;
		if ( empty( $_GET['oauth_verifier'] ) || ! isset( $_GET['oauth_token'] ) || empty($_GET['oauth_token'] ) ) {
			update_option( "{$app_ident}_{$status}", 'FAILED' );
		} elseif ( ! get_transient( $app_ident . '_temp_creds_secret_' . get_current_user_id() ) ) {
			update_option( "{$app_ident}_{$status}", 'FAILED' );
		} else {
			update_option( "{$app_ident}_{$status}", 'SUCCESS' );
			$status = $this->status_keys[2];
			try {
				$this->oauth1_client->config['token'] = $_GET['oauth_token'];
				$this->oauth1_client->config['token_secret'] = get_transient( $app_ident . '_temp_creds_secret_' . get_current_user_id() );
				$access_credentials = $this->oauth1_client->request_access_token( $_GET['oauth_verifier'] );
				update_option( "{$app_ident}_{$status}", 'SUCCESS' );
				$this->connected_apps[ $app_ident ]['access_creds'] = $access_credentials;
				$this->connected_apps[ $app_ident ]['status'] = 'Verified';
				update_option( 'gravityflow_app_settings_connected_apps', $this->connected_apps );
						
			} catch ( Exception $e ) {
				update_option( "{$app_ident}_{$status}", 'FAILED' );
				gravity_flow()->log_debug( __METHOD__ . '() - Exception caught ' . $e->getMessage() );
			}
		}
		$url = remove_query_arg( array( 'oauth_token', 'oauth_verifier', 'wp_scope' ) );
		wp_safe_redirect( $url );
	}

	/**
	 * Process oauth1 - sending user to site for authorization
	 *
	 * @return void
	 **/
	function process_oauth1_outward_leg() {
		$status = $this->status_keys[0];
		$app_ident = $this->app_ident;
		$temp_creds = $this->get_temp_creds( $app_ident );

		if ( false === $temp_creds ) {
			update_option( "{$app_ident}_{$status}", 'FAILED' );
			wp_safe_redirect( remove_query_arg() );
			exit;
		} else {
			set_transient( $app_ident . '_temp_creds_secret_' . get_current_user_id(), $temp_creds['oauth_token_secret'], HOUR_IN_SECONDS );
			update_option( "{$app_ident}_{$status}", 'SUCCESS' );
			$auth_creds = array(
				'oauth_consumer_key' => $consumer_key,
				'oauth_consumer_secret' => $consumer_secret,
			) + $temp_creds;

			$auth_app_page = add_query_arg( $auth_creds, $this->oauth1_client->api_auth_urls['oauth1']['authorize'] );
			?><script>
				window.onload = function() {
					window.location = '<?php echo $auth_app_page; ?>'; // XSS OK.
				}
			</script>
			<?php
		}
	}

	/**
	 * Helper to use the consumer key and secret entered to get temporary credentials
	 * and then allow the user to authorize the webhook's connection using those credentials.
	 *
	 * @param string $app_ident the app getting creds for.
	 *
	 * @return string
	 */
	function get_temp_creds( $app_ident ) {
		try {
			$temporary_credentials = $this->oauth1_client->request_token();
			return $temporary_credentials;
		} catch ( Exception $e ) {
			gravity_flow()->log_debug( __METHOD__ . '() - Exception caught ' . $e->getMessage() );
			return false;
		}
		return false;
	}

	/**
	 * Configure and construct oauth1 client.
	 *
	 * @return void
	 */
	function setup_oauth1_client() {
		try {
			$this->oauth1_client = new GravityFlow_Oauth1_Client(
				array(
					'consumer_key' => $this->connected_apps[ $this->app_ident ]['consumer_key'],
					'consumer_secret' => $this->connected_apps[ $this->app_ident ]['consumer_secret'],
					'callback_url' => add_query_arg( array( 
						'page' => 'gravityflow_settings',
						'view' => 'connected_apps',
						'app' => $this->app_ident,
					), esc_url( admin_url( 'admin.php' ) ) ),
				),
				'gravi_flow_' . $this->connected_apps[ $this->app_ident ]['consumer_key'],
				$this->connected_apps[ $this->app_ident ]['api_url']
			);

		} catch ( Exception $e ) {
			gravity_flow()->log_debug( __METHOD__ . '() - Exception caught ' . $e->getMessage() );
			update_option( "{$app_ident}_{$status}", 'FAILED' );
			$url = remove_query_arg( array( 'oauth_token', 'oauth_verifier', 'wp_scope' ) );
			wp_safe_redirect( $url );

		}
	}

	/**
	 * Instantiate class from outside
	 *
	 * @return object
	 **/
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Gets statuses from outside
	 *
	 * @return array
	 **/
	function get_connection_statuses() {
		return $this->oauth_connection_statuses;
	}
}

/**
 * Gets statuses from outside
 *
 * @return object
 **/
function gf_conn_apps() {
	return Authorize_Connected_Apps::instance();
}

gf_conn_apps();
