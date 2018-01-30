<?php
/*
Plugin Name: payments4G - 4Geeks Payments
Plugin URI: https://4geeks.io/payments
Description: 4Geeks Payments integration Woocommerce
Version: 2.0.17
*/
add_action( 'plugins_loaded', 'cw_gpayments_init', 0 );
function cw_gpayments_init() {
    //if condition use to do nothin while WooCommerce is not installed
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	include_once( 'gpayments-connector-woocommerce.php' );
	// class add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'cw_add_gpayments_gateway' );
	function cw_add_gpayments_gateway( $methods ) {
		$methods[] = 'WC_GPayments_Connection';
		return $methods;
	}
}
// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cw_gpayments_gateway_action_links' );
function cw_gpayments_gateway_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'wc-4gpayments' ) . '</a>',
	);
	return array_merge( $plugin_links, $links );
}
?>
