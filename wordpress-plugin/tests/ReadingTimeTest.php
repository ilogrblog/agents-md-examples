<?php
/**
 * Unit tests for Acme_Reading_Time.
 *
 * @package Acme_Reading_Time
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers the pure calculation only; hooks are exercised in a WordPress install.
 */
final class ReadingTimeTest extends TestCase {

	/**
	 * Empty text still reads as one minute.
	 */
	public function test_empty_text_is_one_minute(): void {
		$this->assertSame( 1, Acme_Reading_Time::minutes( '' ) );
		$this->assertSame( 1, Acme_Reading_Time::minutes( "  \n\t " ) );
	}

	/**
	 * Partial minutes round up.
	 */
	public function test_partial_minutes_round_up(): void {
		$this->assertSame( 1, Acme_Reading_Time::minutes( str_repeat( 'word ', 200 ) ) );
		$this->assertSame( 2, Acme_Reading_Time::minutes( str_repeat( 'word ', 201 ) ) );
	}

	/**
	 * Words are split on any whitespace, including non-ASCII text.
	 */
	public function test_counts_unicode_words(): void {
		$this->assertSame( 1, Acme_Reading_Time::minutes( 'Größe café naïve', 3 ) );
		$this->assertSame( 2, Acme_Reading_Time::minutes( 'Größe café naïve déjà', 3 ) );
	}

	/**
	 * A non-positive reading speed is a programming error.
	 */
	public function test_rejects_non_positive_speed(): void {
		$this->expectException( InvalidArgumentException::class );
		Acme_Reading_Time::minutes( 'text', 0 );
	}
}
