<?php
/**
 * Plugin Name: Limitedq
 * Description: Woocommerce add on - Add quantity buttons to each product component on product list (e.g. category page).
 * Allow admin to limit the maximum allowed quantity for that product
 * Author: luciodiri
 * Text Domain: woo-limitedq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Check if WooCommerce is active and start
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	if( !class_exists('Limitedq' )) {
		include_once dirname( __FILE__ ) . '/admin-limitedq.php';
		include_once dirname( __FILE__ ) . '/front-limitedq.php';
	}
	$GLOBALS['limitedq'] = new Limitedq();
	$GLOBALS['limitedqAdmin'] = new LimitedqAdmin();
}