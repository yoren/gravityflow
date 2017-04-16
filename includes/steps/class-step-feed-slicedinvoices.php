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
	 * Processes the given feed for the add-on.
	 *
	 * @since 1.6.1-dev-2
	 *
	 * @param array $feed The feed to be processed.
	 *
	 * @return bool Returning false if the feed created an invoice to ensure the next step is not processed until after the invoice is paid.
	 */
	public function process_feed( $feed ) {
		if ( rgars( $feed, 'meta/post_type' ) !== 'invoice' ) {
			return parent::process_feed( $feed );
		}

		add_action( 'sliced_gravityforms_feed_processed', array( $this, 'sliced_gravityforms_feed_processed' ), 10, 3 );
		parent::process_feed( $feed );
		remove_action( 'sliced_gravityforms_feed_processed', array( $this, 'sliced_gravityforms_feed_processed' ) );

		return false;
	}

	/**
	 * Store the entry, feed, and step IDs in the invoice (post) meta.
	 *
	 * @since 1.6.1-dev-2
	 *
	 * @param int   $id    The invoice (post) ID.
	 * @param array $feed  The feed which created the invoice.
	 * @param array $entry The entry which created the invoice.
	 */
	public function sliced_gravityforms_feed_processed( $id, $feed, $entry ) {
		update_post_meta( $id, '_gform-entry-id', rgar( $entry, 'id' ) );
		update_post_meta( $id, '_gform-feed-id', rgar( $feed, 'id' ) );
		update_post_meta( $id, '_gravityflow-step-id', $this->get_id() );
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
