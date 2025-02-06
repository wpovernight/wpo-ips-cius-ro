<?php

namespace WPO\IPS\CIUS_RO\Handlers\Common;

use WPO\IPS\UBL\Handlers\UblHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CustomizationIdHandler extends UblHandler {

	public function handle( $data, $options = array() ) {
		$customizationID = array(
			'name'  => 'cbc:CustomizationID',
			'value' => 'urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1',
		);

		$data[] = apply_filters( 'wpo_ips_cius_ro_handle_CustomizationID', $customizationID, $data, $options, $this );

		return $data;
	}
}
