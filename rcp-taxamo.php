<?php
/*
Plugin Name: Restrict Content Pro - Taxamo Integration
Plugin URL: https://github.com/danieliser/rcp-taxamo
Description: Integrate Restrict Content Pro with Taxamo service for VAT storage and calculation.
Version: 1.0.0
Author: Daniel Iser
Author URI: http://danieliser.com
Contributors: danieliser, mordauk
*/

if ( !defined( 'RCP_TAXAMO_PLUGIN_DIR' ) ) {
	define( 'RCP_TAXAMO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) . '/' );
}
if ( !defined( 'RCP_TAXAMO_PLUGIN_URL' ) ) {
	define( 'RCP_TAXAMO_PLUGIN_URL', plugin_dir_url( __FILE__ ) . '/' );
}
if ( !defined( 'RCP_TAXAMO_PLUGIN_FILE' ) ) {
	define( 'RCP_TAXAMO_PLUGIN_FILE', __FILE__ );
}
if ( !defined( 'RCP_TAXAMO_PLUGIN_VERSION' ) ) {
	define( 'RCP_TAXAMO_PLUGIN_VERSION', '1.0.0' );
}

class RCP_Taxamo {

	private $payments, $admin, $public;

	public function __construct() {

		$this->includes();
		$this->payments = new RCP_Taxamo_Payments;
		if(is_admin()) {
			$this->admin = new RCP_Taxamo_Admin;
		}
		else {
			$this->public = new RCP_Taxamo_Public;
		}
		
	}

	public function includes() {

		require RCP_TAXAMO_PLUGIN_DIR . 'includes/class-rcp-taxamo-payments.php';

		if(is_admin()) {
			require RCP_TAXAMO_PLUGIN_DIR . 'includes/class-rcp-taxamo-admin.php';
		}
		else {
			require RCP_TAXAMO_PLUGIN_DIR . 'includes/class-rcp-taxamo-public.php';
		}
		
	}

}

/**
* Initialize & Access the RCP_Taxamo Instance.
* @return instance of RCP_Taxamo.
*/
function rcp_taxamo() {
	global $rcp_taxamo;
	if(!$rcp_taxamo && defined('RCP_PLUGIN_DIR')) {
		$rcp_taxamo = new RCP_Taxamo;
	}
	return $rcp_taxamo;
}
add_action('plugins_loaded', 'rcp_taxamo');