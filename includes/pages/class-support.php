<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Support {

	public static function display() {

		$message = '';

		if ( isset( $_POST['gravityflow_send_feedback'] ) ) {
			check_admin_referer( 'gravityflow_beta_feedback' );

			$system_info = isset( $_POST['gravityflow_debug_info'] ) ? self::get_site_info() : '';

			$body = array(
				'input_values' => array(
					'input_1' => rgpost( 'gravityflow_name' ),
					'input_2' => rgpost( 'gravityflow_email' ),
					'input_4' => rgpost( 'gravityflow_subject' ),
					'input_3' => rgpost( 'gravityflow_description' ),
					'input_5' => $system_info,
				)
			);
			$body_json = json_encode( $body );

			$options = array(
				'method' => 'POST',
				'timeout' => 30,
				'redirection' => 5,
				'blocking' => true,
				'sslverify' => false,
				'headers' => array(),
				'body' => $body_json,
				'cookies' => array()
			);

			$raw_response = wp_remote_post( 'https://gravityflow.io/gravityformsapi/forms/3/submissions/', $options );

			if ( is_wp_error( $raw_response ) ) {
				$message = '<div class="error notice notice-error is-dismissible below-h2"><p>There was a problem submitting your feedback. Please send it by email to stevehenty@gmail.com.</p></div>';
			}
			$response_json = wp_remote_retrieve_body( $raw_response );

			$response = json_decode( $response_json, true );

			if ( rgar( $response, 'status' ) == '200' ) {
				$message = '<div class="updated notice notice-success is-dismissible below-h2"><p>Thank you! I\'ll be in touch soon</p></div>';
			}
		}

		$user = wp_get_current_user();

		?>
		<div class="wrap gf_entry_wrap">
			<style>
				.beta_feedback_form  label{
					padding: 20px 0 10px;
					display:block;
					font-weight: bold;
				}
			</style>

			<h2 class="gf_admin_page_title">
				<span><?php esc_html_e( 'Support', 'gravityflow' ); ?></span>

			</h2>
			<p>
				<?php esc_html_e( 'Please check the documentation before submitting a support request', 'gravityflow' ); ?>
			</p>
			<p>
				<a href="http://docs.gravityflow.io">http://docs.gravityflow.io</a>
			</p>
			<hr />

			<?php echo $message; ?>
			<form action="" method="POST">
				<?php
				wp_nonce_field( 'gravityflow_beta_feedback' );
				?>
				<div class="beta_feedback_form">

					<label for="gravityflow_name">
						<?php esc_html_e( 'Name', 'gravityflow' ); ?>
					</label>

					<input id="gravityflow_name" type="text" class="regular-text" name="gravityflow_name" value="<?php echo $user->display_name; ?>"/>

					<label for="gravityflow_email">
						<?php esc_html_e( 'Email', 'gravityflow' ); ?>
					</label>

					<input id="gravityflow_email" type="email" class="regular-text" name="gravityflow_email" value="<?php echo get_option( 'admin_email' ); ?>"/>

					<label for="gravityflow_subject_suggestion">
						<input id="gravityflow_subject_suggestion" type="radio" name="gravityflow_subject" value="suggestion" checked="checked"/>
						<?php esc_html_e( 'General comment or suggestion', 'gravityflow' ); ?>
					</label>

					<label for="gravityflow_subject_feature_request">
						<input id="gravityflow_subject_feature_request" type="radio" name="gravityflow_subject" value="feature request"/>
						<?php esc_html_e( 'Feature request', 'gravityflow' ); ?>
					</label>


					<label for="gravityflow_subject_bug_report">
						<input id="gravityflow_subject_bug_report" type="radio" name="gravityflow_subject" value="bug report"/>
						<?php esc_html_e( 'Bug report', 'gravityflow' ); ?>
					</label>

					<label for="gravityflow_description">
						<?php esc_html_e( 'Suggestion or steps to reproduce the issue.', 'gravityflow' ); ?>
					</label>

					<textarea id="gravityflow_description" name="gravityflow_description" class="widefat" cols="50" rows="10"></textarea>
					<label>
						<input type="checkbox" name="gravityflow_debug_info" value="1" checked="checked"/>
						<?php esc_html_e( 'Send debugging information. (This includes some system information and a list of active plugins. No forms or entry data will be sent.)', 'gravityflow' ); ?>
					</label>
					</br /><br />
					<input id="gravityflow_send" type="submit" class="button button-primary button-large" name="gravityflow_send_feedback" value="<?php esc_html_e( 'Send', 'gravityflow' ); ?>" />

				</div>
			</form>
		</div>
	<?php

	}

	public static function get_site_info() {

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$plugin_list = get_plugins();
		$site_url    = get_bloginfo( 'url' );
		$plugins     = array();

		$active_plugins = get_option( 'active_plugins' );

		foreach ( $plugin_list as $key => $plugin ) {
			$is_active = in_array( $key, $active_plugins );
			if ( $is_active ) {
				$name      = substr( $key, 0, strpos( $key, '/' ) );
				$plugins_details = array( 'name: ' . $name, 'version: ' . $plugin['Version'] );
				$plugins[] = join( PHP_EOL, $plugins_details );
			}
		}
		$plugins = join( PHP_EOL, $plugins );

		//get theme info
		$theme            = wp_get_theme();
		$theme_name       = $theme->get( 'Name' );
		$theme_uri        = $theme->get( 'ThemeURI' );
		$theme_version    = $theme->get( 'Version' );
		$theme_author     = $theme->get( 'Author' );
		$theme_author_uri = $theme->get( 'AuthorURI' );

		$form_counts    = GFFormsModel::get_form_count();
		$active_count   = $form_counts['active'];
		$inactive_count = $form_counts['inactive'];
		$fc             = abs( $active_count ) + abs( $inactive_count );
		$entry_count    = GFFormsModel::get_lead_count_all_forms( 'active' );
		$im             = is_multisite();

		global $wpdb;

		$info = array(
			'site: ' . $site_url,
			'GF version' . GFCommon::$version,
			'Gravity Flow version' . gravity_flow()->_version,
			'WordPress version: ' . get_bloginfo( 'version' ),
			'php version' . phpversion(),
			'mysql version: ' . $wpdb->db_version(),
			'theme name:' . $theme_name,
			'theme url' . $theme_uri,
			'theme version:' . $theme_version,
			'theme author: ' . $theme_author,
			'theme author URL:' . $theme_author_uri,
			'is multisite' . $im,
			'form count: ' . $fc,
			'entry count: ' . $entry_count,
			'plugins: ' . $plugins,
		);

		return join( PHP_EOL, $info );
	}
}