<?php
/**
 * Free shipping threshold arithmetic, kept free of WooCommerce calls so it can be unit tested.
 *
 * @package Acme_Free_Shipping_Nudge
 */

/**
 * Works in integer minor units (cents, pence) so no float rounding reaches the customer.
 */
final class Acme_Free_Shipping_Nudge {

	/**
	 * Returns how much more the customer must spend, in minor units, or 0 when the threshold is met.
	 *
	 * @param int $subtotal  Cart subtotal in minor units.
	 * @param int $threshold Free shipping threshold in minor units. Zero disables the nudge.
	 * @return int
	 * @throws InvalidArgumentException When either amount is negative.
	 */
	public static function remaining( int $subtotal, int $threshold ): int {
		if ( $subtotal < 0 || $threshold < 0 ) {
			throw new InvalidArgumentException( 'Amounts must not be negative.' );
		}

		return max( 0, $threshold - $subtotal );
	}

	/**
	 * Returns how much more the customer must spend, in minor units, or null when either amount is malformed.
	 *
	 * Lets a bad stored threshold produce no notice instead of an error on the cart page.
	 *
	 * @param string $subtotal  Cart subtotal as a decimal string such as "19.99".
	 * @param string $threshold Free shipping threshold as a decimal string. Zero disables the nudge.
	 * @param int    $decimals  Number of decimal places the store uses.
	 * @return int|null
	 */
	public static function remaining_from_amounts( string $subtotal, string $threshold, int $decimals ): ?int {
		try {
			return self::remaining( self::to_minor_units( $subtotal, $decimals ), self::to_minor_units( $threshold, $decimals ) );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Converts a decimal amount string, as WooCommerce stores prices, to integer minor units.
	 *
	 * @param string $amount   Decimal amount such as "49.99".
	 * @param int    $decimals Number of decimal places the store uses.
	 * @return int
	 * @throws InvalidArgumentException When the amount is not a plain decimal number.
	 */
	public static function to_minor_units( string $amount, int $decimals ): int {
		if ( 1 !== preg_match( '/^\d+(?:\.\d+)?$/', $amount ) ) {
			throw new InvalidArgumentException( 'Amount must be a non-negative decimal number.' );
		}

		list( $whole, $fraction ) = array_pad( explode( '.', $amount, 2 ), 2, '' );
		$fraction                 = substr( str_pad( $fraction, $decimals, '0' ), 0, $decimals );

		return (int) ( $whole . $fraction );
	}
}
