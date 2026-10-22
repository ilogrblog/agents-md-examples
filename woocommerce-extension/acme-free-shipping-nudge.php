<?php
/**
 * Plugin Name:          Acme Free Shipping Nudge
 * Description:          Tells shoppers how much more to spend for free shipping.
 * Version:              0.1.0
 * Requires at least:    6.5
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * WC requires at least: 9.0
 * WC tested up to:      11.2
 * License:              GPL-2.0-or-later
 * Text Domain:          acme-free-shipping-nudge
 *
 * @package Acme_Free_Shipping_Nudge
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-acme-free-shipping-nudge.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Prints a notice above the cart while the free shipping threshold is not met.
 */
function acme_free_shipping_nudge_cart_notice() {
	if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
		return;
	}

	$decimals  = wc_get_price_decimals();
	$subtotal  = wc_format_decimal( WC()->cart->get_subtotal(), $decimals );
	$threshold = wc_format_decimal( get_option( 'acme_free_shipping_nudge_threshold', '50' ), $decimals );
	$remaining = Acme_Free_Shipping_Nudge::remaining_from_amounts( $subtotal, $threshold, $decimals );

	// A malformed threshold option shows no notice instead of breaking the cart page.
	if ( empty( $remaining ) ) {
		return;
	}

	wc_print_notice(
		sprintf(
			/* translators: %s: formatted amount still to spend. */
			esc_html__( 'Spend %s more for free shipping.', 'acme-free-shipping-nudge' ),
			wc_price( $remaining / ( 10 ** $decimals ) )
		),
		'notice'
	);
}

// This hook runs on the classic cart ([woocommerce_cart] shortcode), not on the Cart block.
add_action( 'woocommerce_before_cart', 'acme_free_shipping_nudge_cart_notice' );
