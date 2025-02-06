<?php

namespace WPO\IPS\CIUS_RO\Handlers\Common;

use WPO\IPS\UBL\Handlers\UblHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class DocumentCurrencyCodeHandler extends UblHandler {

	public function handle( $data, $options = array() ) {
		$documentCurrencyCode = array(
			'name'  => 'cbc:DocumentCurrencyCode',
			'value' => $this->document->order->get_currency(),
		);

		$data[] = apply_filters( 'wpo_ips_cius_ro_handle_DocumentCurrencyCode', $documentCurrencyCode, $data, $options, $this );

		return $data;
	}

}
