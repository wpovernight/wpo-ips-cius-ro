<?php

namespace WPO\IPS\CIUS_RO\Handlers\Common;

use WPO\IPS\UBL\Handlers\UblHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PaymentTermsHandler extends UblHandler {

	public function handle( $data, $options = array() ) {
		$note = '';
		
		if ( $this->document->order->is_paid() ) {
			$note = __( 'Invoice has been paid.', 'wpo-ips-cius_ro' );
		} else {
			$due_date_days = is_callable( array( $this->document->order_document, 'get_setting' ) ) ? absint( $this->document->order_document->get_setting( 'due_date_days' ) ) : 0;
			
			if ( $due_date_days > 0 ) {
				$note = sprintf(
					/* translators: %d: Due date days */
					__( 'Net within %d days.', 'wpo-ips-cius_ro' ),
					$due_date_days
				);
			}
		}
		
		$note = apply_filters( 'wpo_ips_cius_ro_payment_terms_note', $note, $data, $options, $this );
		
		if ( ! empty( $note ) ) {
			$paymentTerms = array(
				'name'  => 'cac:PaymentTerms',
				'value' => array(
					array(
						'name'  => 'cbc:Note',
						'value' => wpo_ips_ubl_sanitize_string( $note ),
					),
				),
			);
	
			$data[] = apply_filters( 'wpo_ips_cius_ro_handle_PaymentTerms', $paymentTerms, $data, $options, $this );
		}

		return $data;
	}

}
