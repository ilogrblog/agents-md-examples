<?php
/**
 * Unit tests for Acme_Free_Shipping_Nudge.
 *
 * @package Acme_Free_Shipping_Nudge
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers the arithmetic only; the cart hook is exercised in a WooCommerce install.
 */
final class FreeShippingNudgeTest extends TestCase {

	/**
	 * Below the threshold, the difference is returned.
	 */
	public function test_remaining_below_threshold(): void {
		$this->assertSame( 1, Acme_Free_Shipping_Nudge::remaining( 4999, 5000 ) );
	}

	/**
	 * At or above the threshold, nothing remains.
	 */
	public function test_nothing_remaining_at_threshold(): void {
		$this->assertSame( 0, Acme_Free_Shipping_Nudge::remaining( 5000, 5000 ) );
		$this->assertSame( 0, Acme_Free_Shipping_Nudge::remaining( 7200, 5000 ) );
	}

	/**
	 * Decimal strings convert exactly, without float rounding.
	 */
	public function test_minor_units_are_exact(): void {
		$this->assertSame( 1999, Acme_Free_Shipping_Nudge::to_minor_units( '19.99', 2 ) );
		$this->assertSame( 5000, Acme_Free_Shipping_Nudge::to_minor_units( '50', 2 ) );
		$this->assertSame( 1230, Acme_Free_Shipping_Nudge::to_minor_units( '12.3', 2 ) );
		$this->assertSame( 57, Acme_Free_Shipping_Nudge::to_minor_units( '0.57', 2 ) );
		$this->assertSame( 1500, Acme_Free_Shipping_Nudge::to_minor_units( '1500', 0 ) );
	}

	/**
	 * Negative or malformed amounts are programming errors.
	 */
	public function test_rejects_bad_amounts(): void {
		$this->expectException( InvalidArgumentException::class );
		Acme_Free_Shipping_Nudge::to_minor_units( '-1.00', 2 );
	}

	/**
	 * A negative subtotal is rejected.
	 */
	public function test_rejects_negative_subtotal(): void {
		$this->expectException( InvalidArgumentException::class );
		Acme_Free_Shipping_Nudge::remaining( -1, 5000 );
	}

	/**
	 * Amounts given as decimal strings give the remaining minor units.
	 */
	public function test_remaining_from_amounts(): void {
		$this->assertSame( 3001, Acme_Free_Shipping_Nudge::remaining_from_amounts( '19.99', '50.00', 2 ) );
		$this->assertSame( 0, Acme_Free_Shipping_Nudge::remaining_from_amounts( '50.00', '50.00', 2 ) );
	}

	/**
	 * A malformed stored threshold gives null, never an exception.
	 */
	public function test_remaining_from_amounts_returns_null_for_bad_input(): void {
		$this->assertNull( Acme_Free_Shipping_Nudge::remaining_from_amounts( '19.99', '-5.00', 2 ) );
		$this->assertNull( Acme_Free_Shipping_Nudge::remaining_from_amounts( '19.99', '', 2 ) );
		$this->assertNull( Acme_Free_Shipping_Nudge::remaining_from_amounts( '19.99', 'abc', 2 ) );
	}
}
