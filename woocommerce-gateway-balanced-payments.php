<?php
/**
 * Plugin Name: WooCommerce - Balanced Payments Gateway
 * Plugin URI: http://www.woothemes.com/woocommerce/
 * Description: Integrate Balanced Payments into your WooCommerce checkout form.
 * Version: 1.0.0
 * Author: Patrick Garman
 * Author URI: https://pmgarman.me
 * Text Domain: wc-balanced-payments
 * Domain Path: /languages/
 * License: GPLv2
 */

/**
 * Copyright 2014  Patrick Garman  (email: patrick@pmgarman.me)
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

add_action( 'plugins_loaded', 'init_wc_balanced_payments', 0 );
function init_wc_balanced_payments() {
	/**
	 * If the class already exists, return.
	 */
	if( class_exists( 'WC_Balanced_Payments_Base' ) || class_exists( 'WC_Balanced_Payments_Subscriptions' ) )
		return

	/**
	 * Load translations if they exist.
	 */
	load_plugin_textdomain( 'wc-balanced-payments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	/**
	 * Required Files
	 */
	require_once 'classes/class-wc-balanced-payments-base.php';
	require_once 'classes/class-wc-balanced-payments-cc.php';

	/**
	 * Start The Management Class
	 */
	global $WC_Balanced_Payments_Base;
	$WC_Balanced_Payments_Base = new WC_Balanced_Payments_Base( __FILE__ );

	/**
	 * Add Gateways to WooCommerce.
	 */
	function add_wc_balanced_payments( $methods ) {
		$methods[] = 'WC_Balanced_Payments_CC';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_wc_balanced_payments' );
}