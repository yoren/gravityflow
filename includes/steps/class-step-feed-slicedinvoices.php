<?php
/**
 * Gravity Flow Step Feed Sliced Invoices
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow_Step_Feed_Sliced_Invoices
 * @copyright   Copyright (c) 2016-2017, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.3-dev
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Feed_Sliced_Invoices extends Gravity_Flow_Step_Feed_Add_On {
	public $_step_type = 'slicedinvoices';

	protected $_class_name = 'Sliced_Invoices_GF';
	protected $_slug = 'slicedinvoices';

	public function get_label() {
		return esc_html__( 'Sliced Invoices', 'gravityflow' );
	}

	/**
	 * Returns the settings for this step.
	 *
	 * @since 1.6.1-dev-2 Added the Step Completion setting.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = parent::get_settings();

		if ( ! $this->is_supported() ) {
			return $settings;
		}

		$settings_api = $this->get_common_settings_api();

		$fields = array(
			array(
				'name'    => 'product_line_items',
				'type'    => 'checkbox',
				'label'   => __( 'Line Items', 'gravityflow' ),
				'choices' => array(
					array(
						'label' => __( 'Set the line items from the entry Order Summary', 'gravityflow' ),
						'name'  => 'product_line_items_enabled'
					),
				),
			),
			$settings_api->get_setting_assignee_type(),
			$settings_api->get_setting_assignees(),
			$settings_api->get_setting_assignee_routing(),
			$settings_api->get_setting_notification_tabs( array(
				array(
					'label'  => __( 'Assignee Email', 'gravityflow' ),
					'id'     => 'tab_assignee_notification',
					'fields' => $settings_api->get_setting_notification( array(
						'type' => 'assignee',
					) ),
				)
			) ),
			$settings_api->get_setting_instructions(),
			$settings_api->get_setting_display_fields(),
			array(
				'name'    => 'post_feed_completion',
				'type'    => 'select',
				'label'   => __( 'Step Completion', 'gravityflow' ),
				'choices' => array(
					array( 'label' => __( 'Immediately following feed processing', 'gravityflow' ), 'value' => '' ),
					array( 'label' => __( 'Delay until invoices are paid', 'gravityflow' ), 'value' => 'delayed' ),
				),
			)
		);

		$settings['fields'] = array_merge( $settings['fields'], $fields );

		return $settings;
	}

	/**
	 * Processes this step.
	 *
	 * @since 1.6.1-dev-2
	 *
	 * @return bool Is the step complete?
	 */
	public function process() {
		$complete = parent::process();
		$this->assign();

		return $complete;
	}

	/**
	 * Processes the given feed for the add-on.
	 *
	 * @since 1.6.1-dev-2
	 *
	 * @param array $feed The feed to be processed.
	 *
	 * @return bool Returning false if the feed created an invoice to ensure the next step is not processed until after the invoice is paid.
	 */
	public function process_feed( $feed ) {
		if ( $this->product_line_items_enabled ) {
			$feed['meta']['mappedFields_line_items'] = '';
		}

		add_action( 'sliced_gravityforms_feed_processed', array( $this, 'sliced_gravityforms_feed_processed' ), 1, 3 );
		parent::process_feed( $feed );
		remove_action( 'sliced_gravityforms_feed_processed', array( $this, 'sliced_gravityforms_feed_processed' ), 1 );

		return rgars( $feed, 'meta/post_type' ) !== 'invoice' || $this->post_feed_completion !== 'delayed' ? true : false;

	}

	/**
	 * If applicable, store the line items, entry, feed, and step IDs in the post meta.
	 *
	 * @since 1.6.1-dev-2
	 *
	 * @param int   $id    The invoice (post) ID.
	 * @param array $feed  The feed which created the invoice.
	 * @param array $entry The entry which created the invoice.
	 */
	public function sliced_gravityforms_feed_processed( $id, $feed, $entry ) {
		if ( rgars( $feed, 'meta/post_type' ) === 'invoice' && $this->post_feed_completion === 'delayed' ) {
			update_post_meta( $id, '_gform-entry-id', rgar( $entry, 'id' ) );
			update_post_meta( $id, '_gform-feed-id', rgar( $feed, 'id' ) );
			update_post_meta( $id, '_gravityflow-step-id', $this->get_id() );
		}

		if ( ! $this->product_line_items_enabled ) {
			return;
		}

		$products = GFCommon::get_product_fields( $this->get_form(), $entry );

		if ( empty( $products['products'] ) ) {
			return;
		}

		$line_items = array();

		foreach ( $products['products'] as $product ) {
			$options = array();
			if ( is_array( rgar( $product, 'options' ) ) ) {
				foreach ( $product['options'] as $option ) {
					$options[] = $option['option_name'];
				}
			}

			$description = '';
			if ( ! empty( $options ) ) {
				$description = esc_html__( 'options: ', 'gravityflow' ) . ' ' . implode( ', ', $options );
			}

			$line_items[] = array(
				'qty'         => esc_html( rgar( $product, 'quantity' ) ),
				'title'       => esc_html( rgar( $product, 'name' ) ),
				'description' => wp_kses_post( $description ),
				'amount'      => GFCommon::to_number( rgar( $product, 'price', 0 ), $entry['currency'] ),
			);
		}

		if ( ! empty( $products['shipping']['name'] ) ) {
			$line_items[] = array(
				'qty'         => 1,
				'title'       => esc_html( $products['shipping']['name'] ),
				'description' => '',
				'amount'      => GFCommon::to_number( rgar( $products['shipping'], 'price', 0 ), $entry['currency'] ),
			);
		}

		if ( ! empty( $line_items ) ) {
			update_post_meta( $id, '_sliced_items', $line_items );
		}
	}

	/**
	 * Display the workflow detail box for this step.
	 *
	 * @since 1.6.1-dev-2
	 *
	 * @param array $form The current form.
	 * @param array $args The page arguments.
	 */
	public function workflow_detail_box( $form, $args ) {
		$args = array(
			'post_type'  => 'sliced_invoice',
			'meta_query' => array(
				array(
					'key'   => '_gform-entry-id',
					'value' => $this->get_entry_id(),
				),
				array(
					'key'   => '_gravityflow-step-id',
					'value' => $this->get_id(),
				),
			),
		);

		$invoices = get_posts( $args );

		if ( ! empty( $invoices ) ) {
			echo sprintf( '<h4 style="margin-bottom:10px;">%s</h4>', $this->get_label() );

			$assignee_key = gravity_flow()->get_current_user_assignee_key();
			$can_edit     = $this->is_assignee( $assignee_key ) && current_user_can( 'edit_posts' );

			/* @var WP_Post $invoice */
			foreach ( $invoices as $invoice ) {
				$title = $invoice->post_title;

				if ( ! $title ) {
					$feed_id = get_post_meta( $invoice->ID, '_gform-feed-id', true );
					$feed    = gravity_flow()->get_feed( $feed_id );
					$title   = rgar( $feed['meta'], 'feedName' );
				}

				echo sprintf( '%s: %s<br><br>', esc_html__( 'Title', 'gravityflow' ), esc_html( $title ) );
				echo sprintf( '%s: %s<br><br>', esc_html__( 'Number', 'gravityflow' ), esc_html( get_post_meta( $invoice->ID, '_sliced_invoice_number', true ) ) );

				if ( function_exists( 'sliced_get_invoice_total' ) ) {
					echo sprintf( '%s: %s<br><br>', esc_html__( 'Total', 'gravityflow' ), esc_html( sliced_get_invoice_total( $invoice->ID ) ) );
				}

				if ( class_exists( 'Sliced_Shared' ) ) {
					echo sprintf( '%s: %s<br><br>', esc_html__( 'Status', 'gravityflow' ), esc_html( Sliced_Shared::get_status( $invoice->ID, 'invoice' ) ) );
				}

				echo '<div class="gravityflow-action-buttons">';
				if ( $can_edit ) {
					echo sprintf( '<a href="%s" target="_blank" class="button button-large button-primary">%s</a> ', get_edit_post_link( $invoice->ID ), esc_html__( 'Edit', 'gravityflow' ) );
				}
				echo sprintf( '<a href="%s" target="_blank" class="button button-large button-primary">%s</a><br><br>', get_permalink( $invoice ), esc_html__( 'Preview', 'gravityflow' ) );
				echo '</div>';

			}

		}
	}

}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Sliced_Invoices() );

/**
 * Resume the workflow if the invoice is paid and originated from a feed processed by one of our steps.
 *
 * @since 1.6.1-dev-2
 *
 * @param string $id     The invoice (post) ID.
 * @param string $status The invoice status.
 */
function gravity_flow_step_sliced_invoice_status_update( $id, $status ) {
	if ( $status !== 'paid' ) {
		return;
	}

	$entry_id = get_post_meta( $id, '_gform-entry-id', true );
	$feed_id  = get_post_meta( $id, '_gform-feed-id', true );
	$step_id  = get_post_meta( $id, '_gravityflow-step-id', true );

	if ( ! $entry_id || ! $feed_id || ! $step_id ) {
		return;
	}

	$entry = GFAPI::get_entry( $entry_id );

	if ( ! is_wp_error( $entry ) && rgar( $entry, 'workflow_final_status' ) === 'pending' ) {
		$api = new Gravity_Flow_API( $entry['form_id'] );

		/* @var Gravity_Flow_Step_Feed_Sliced_Invoices $step */
		$step = $api->get_current_step( $entry );

		if ( $step && $step->get_id() == $step_id ) {
			$feed  = gravity_flow()->get_feed( $feed_id );
			$label = $step->get_feed_label( $feed );
			$note  = sprintf( esc_html__( 'Invoice paid: %s', 'gravityflow' ), $label );
			$step->log_debug( __METHOD__ . '() - Feed processing complete: ' . $label );
			$step->add_note( $note, 0, $step->get_type() );

			$add_on_feeds = $step->get_processed_add_on_feeds( $entry_id );
			if ( ! in_array( $feed_id, $add_on_feeds ) ) {
				$add_on_feeds[] = $feed_id;
				$step->update_processed_feeds( $add_on_feeds, $entry_id );
				$form = GFAPI::get_form( $entry['form_id'] );
				gravity_flow()->process_workflow( $form, $entry_id );
			}
		}
	}

}

add_action( 'sliced_invoice_status_update', 'gravity_flow_step_sliced_invoice_status_update', 10, 2 );
