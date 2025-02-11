<?php
/**
 * Plugin Name:      PDF Invoices & Packing Slips for WooCommerce - CIUS-RO
 * Requires Plugins: woocommerce-pdf-invoices-packing-slips
 * Plugin URI:       https://github.com/wpovernight/wpo-ips-cius-ro
 * Description:      CIUS-RO add-on for PDF Invoices & Packing Slips for WooCommerce plugin.
 * Version:          1.0.1
 * Update URI:       https://github.com/wpovernight/wpo-ips-cius-ro
 * Author:           WP Overnight
 * Author URI:       https://wpovernight.com
 * License:          GPLv3
 * License URI:      https://opensource.org/licenses/gpl-license.php
 * Text Domain:      wpo-ips-cius-ro
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WPO_IPS_CIUS_RO' ) ) {

	class WPO_IPS_CIUS_RO {

		/**
		 * Plugin version
		 *
		 * @var string
		 */
		public $version = '1.0.1';
		
		/**
		 * Base plugin version
		 *
		 * @var string
		 */
		public $base_plugin_version = '3.9.5';
		
		/**
		 * UBL format
		 *
		 * @var string
		 */
		public $ubl_format = 'cius-ro';
		
		/**
		 * Format name
		 *
		 * @var string
		 */
		public $format_name = 'EN16931 CIUS-RO';
		
		/**
		 * Root element
		 *
		 * @var string
		 */
		public $root_element = '{urn:oasis:names:specification:ubl:schema:xsd:Invoice-2}Invoice';
		
		/**
		 * Plugin path
		 * 
		 * @var string
		 */
		public $plugin_path;
		
		/**
		 * Plugin instance
		 *
		 * @var WPO_IPS_CIUS_RO
		 */
		private static $_instance;

		/**
		 * Plugin instance
		 * 
		 * @return WPO_IPS_CIUS_RO
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->plugin_path   = plugin_dir_path( __FILE__ );
			$plugin_file         = basename( $this->plugin_path ) . '/wpo-ips-cius-ro.php';
			$github_updater_file = $this->plugin_path . 'github-updater/GitHubUpdater.php';
			$autoloader_file     = $this->plugin_path . 'vendor/autoload.php';
			
			if ( ! class_exists( '\\WPO\\GitHubUpdater\\GitHubUpdater' ) && file_exists( $github_updater_file ) ) {
				require_once $github_updater_file;
			}
			
			if ( class_exists( '\\WPO\\GitHubUpdater\\GitHubUpdater' ) ) {
				$gitHubUpdater = new \WPO\GitHubUpdater\GitHubUpdater( $plugin_file );
				$gitHubUpdater->setChangelog( 'CHANGELOG.md' );
				$gitHubUpdater->add();
			}

			if ( class_exists( 'WPO_WCPDF' ) && version_compare( WPO_WCPDF()->version, $this->base_plugin_version, '<' ) ) {
				add_action( 'admin_notices', array( $this, 'base_plugin_dependency_notice' ) );
				return;
			}
			
			if ( file_exists( $autoloader_file ) ) {
				require_once $autoloader_file;
			}
			
			add_action( 'init', array( $this, 'load_translations' ) );
			add_action( 'before_woocommerce_init', array( $this, 'custom_order_tables_compatibility' ) );
			
			add_filter( 'wpo_ips_ubl_is_country_format_extension_active', '__return_true' );
			add_filter( 'wpo_ips_en16931_handle_CustomizationID', array( $this, 'make_customization_id_compliant' ), 10, 4 );
			add_filter( 'wpo_wcpdf_document_ubl_settings_formats', array( $this, 'add_format_to_ubl_settings' ), 10, 2 );
			add_filter( 'wpo_wc_ubl_document_root_element', array( $this, 'add_root_element' ), 10, 2 );
			add_filter( 'wpo_wc_ubl_document_format', array( $this, 'set_document_format' ), 10, 2 );
			add_filter( 'wpo_wc_ubl_document_namespaces', array( $this, 'set_document_namespaces' ), 10, 2 );
			add_filter( 'wpo_ips_en16931_handle_AccountingSupplierParty', array( $this, 'add_country_subentity' ), 10, 4 );
			add_filter( 'wpo_ips_en16931_handle_AccountingCustomerParty', array( $this, 'add_country_subentity' ), 10, 4 );
		}
		
		/**
		 * Base plugin dependency notice
		 * 
		 * @return void
		 */
		public function base_plugin_dependency_notice(): void {
			$error = sprintf( 
				/* translators: plugin version */
				__( 'PDF Invoices & Packing Slips for WooCommerce - CIUS RO requires PDF Invoices & Packing Slips for WooCommerce version %s or higher.', 'wpo-ips-cius-ro' ), 
				$this->base_plugin_version
			);

			$message = sprintf( 
				'<div class="notice notice-error"><p>%s</p></div>', 
				$error, 
			);

			echo $message;
		}
		
		/**
		 * Load translations
		 * 
		 * @return void
		 */
		public function load_translations(): void {
			load_plugin_textdomain( 'wpo-ips-cius-ro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			load_plugin_textdomain( 'wpo-ips-en16931', false, dirname( plugin_basename( __FILE__ ) ) . '/en16931/languages/' );
		}
		
		/**
		 * Add HPOS compatibility
		 * 
		 * @return void
		 */
		public function custom_order_tables_compatibility(): void {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
		
		/**
		 * Make customization ID compliant
		 *
		 * @param array $customization_id
		 * @param array $data
		 * @param array $options
		 * @param \WPO\IPS\EN16931\Handlers\Common\CustomizationIdHandler $handler
		 * @return array
		 */
		public function make_customization_id_compliant( array $customization_id, array $data, array $options, \WPO\IPS\EN16931\Handlers\Common\CustomizationIdHandler $handler ): array {
			if ( $this->is_cius_ro_ubl_document( $handler->document ) ) {
				$customization_id['value'] .= '#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1';
			}
			return $customization_id;
		}
		
		/**
		 * Add format to UBL settings
		 *
		 * @param array $formats
		 * @param \WPO\IPS\Documents\OrderDocument $document
		 * @return array
		 */
		public function add_format_to_ubl_settings( array $formats, \WPO\IPS\Documents\OrderDocument $document ): array {
			if ( $document && 'invoice' === $document->get_type() ) {
				$formats[ $this->ubl_format ] = $this->format_name;
			}
			
			return $formats;
		}
		
		/**
		 * Check if UBL document is CIUS RO
		 *
		 * @param \WPO\IPS\UBL\Documents\UblDocument $ubl_document
		 * @return bool
		 */
		private function is_cius_ro_ubl_document( \WPO\IPS\UBL\Documents\UblDocument $ubl_document ): bool {
			return (
				is_callable( array( $ubl_document->order_document, 'get_ubl_format' ) ) &&
				$this->ubl_format === $ubl_document->order_document->get_ubl_format()
			);
		}
		
		/**
		 * Add root element
		 *
		 * @param string $root_element
		 * @param \WPO\IPS\UBL\Documents\UblDocument $ubl_document
		 * @return string
		 */
		public function add_root_element( string $root_element, \WPO\IPS\UBL\Documents\UblDocument $ubl_document ): string {
			if ( $this->is_cius_ro_ubl_document( $ubl_document ) ) {
				$root_element = $this->root_element;
			}
			
			return $root_element;
		}
		
		/**
		 * Set document format
		 *
		 * @param array $format
		 * @param \WPO\IPS\UBL\Documents\UblDocument $ubl_document
		 * @return array
		 */
		public function set_document_format( array $format, \WPO\IPS\UBL\Documents\UblDocument $ubl_document ): array {
			if ( $this->is_cius_ro_ubl_document( $ubl_document ) ) {
				$format = apply_filters( 'wpo_ips_cius_ro_document_format', array(
					'customizationid' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\EN16931\Handlers\Common\CustomizationIdHandler::class,
					),
					'id' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\UBL\Handlers\Common\IdHandler::class,
					),
					'issuedate' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\UBL\Handlers\Common\IssueDateHandler::class,
					),
					'duedate' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\EN16931\Handlers\Common\DueDateHandler::class,
					),
					'invoicetypecode' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\EN16931\Handlers\Invoice\InvoiceTypeCodeHandler::class,
					),
					'documentcurrencycode' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\EN16931\Handlers\Common\DocumentCurrencyCodeHandler::class,
					),
					'buyerreference' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\UBL\Handlers\Common\BuyerReferenceHandler::class,
					),
					'additionaldocumentreference' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\UBL\Handlers\Common\AdditionalDocumentReferenceHandler::class,
					),
					'accountsupplierparty' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\EN16931\Handlers\Common\AddressHandler::class,
						'options' => array(
							'root' => 'cac:AccountingSupplierParty',
						),
					),
					'accountingcustomerparty' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\EN16931\Handlers\Common\AddressHandler::class,
						'options' => array(
							'root' => 'cac:AccountingCustomerParty',
						),
					),
					'delivery' => array(
						'enabled' => false,
						'handler' => \WPO\IPS\UBL\Handlers\Common\DeliveryHandler::class,
					),
					'paymentmeans' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\UBL\Handlers\Common\PaymentMeansHandler::class,
					),
					'paymentterms' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\EN16931\Handlers\Common\PaymentTermsHandler::class,
					),
					'allowancecharge' => array(
						'enabled' => false,
						'handler' => \WPO\IPS\UBL\Handlers\Common\AllowanceChargeHandler::class,
					),
					'taxtotal' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\EN16931\Handlers\Common\TaxTotalHandler::class,
					),
					'legalmonetarytotal' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\EN16931\Handlers\Common\LegalMonetaryTotalHandler::class,
					),
					'invoiceline' => array(
						'enabled' => true,
						'handler' => \WPO\IPS\EN16931\Handlers\Invoice\InvoiceLineHandler::class,
					),
				), $ubl_document );
			}
			
			return $format;
		}
		
		/**
		 * Set document namespaces
		 *
		 * @param array $namespaces
		 * @param \WPO\IPS\UBL\Documents\UblDocument $ubl_document
		 * @return array
		 */
		public function set_document_namespaces( array $namespaces, \WPO\IPS\UBL\Documents\UblDocument $ubl_document ): array {
			if ( $this->is_cius_ro_ubl_document( $ubl_document ) ) {
				$namespaces = apply_filters( 'wpo_ips_cius_ro_document_namespaces', array(
					'ubl' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
					'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
					'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
					'ns4' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
				), $ubl_document );
			}
			
			return $namespaces;
		}
		
		/**
		 * Add country subentity
		 *
		 * @param array $party
		 * @param array $data
		 * @param array $options
		 * @param \WPO\IPS\EN16931\Handlers\Common\AddressHandler $handler
		 * @return array
		 */
		public function add_country_subentity( array $party, array $data, array $options, \WPO\IPS\EN16931\Handlers\Common\AddressHandler $handler ): array {
			if ( $this->is_cius_ro_ubl_document( $handler->document ) && isset( $party[0]['value'] ) && is_array( $party[0]['value'] ) ) {
				foreach ( $party[0]['value'] as $key => $value ) {
					if ( 'cac:PostalAddress' === $value['name'] ) {
						$countrySubentity = array(
							array(
								'name'  => 'cbc:CountrySubentity',
								'value' => $handler->document->order->get_billing_state(),
							)
						);
						array_splice( $party[0]['value'][ $key ]['value'], 3, 0, $countrySubentity );
						break;
					}
				}
			}
			
			return $party;
		}

	}
	
}

/**
 * Plugin instance
 * 
 * @return WPO_IPS_CIUS_RO
 */
function WPO_IPS_CIUS_RO() {
	return WPO_IPS_CIUS_RO::instance();
}
add_action( 'plugins_loaded', 'WPO_IPS_CIUS_RO', 99 );