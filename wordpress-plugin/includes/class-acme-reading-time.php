<?php
/**
 * Reading time calculation, kept free of WordPress calls so it can be unit tested.
 *
 * @package Acme_Reading_Time
 */

/**
 * Estimates reading time for plain text.
 */
final class Acme_Reading_Time {

	/**
	 * Returns the estimated reading time in whole minutes, never less than one.
	 *
	 * @param string $text             Plain text, with markup already removed.
	 * @param int    $words_per_minute Reading speed. Must be positive.
	 * @return int
	 * @throws InvalidArgumentException When the reading speed is not positive.
	 */
	public static function minutes( string $text, int $words_per_minute = 200 ): int {
		if ( $words_per_minute < 1 ) {
			throw new InvalidArgumentException( 'Reading speed must be at least one word per minute.' );
		}

		$words = preg_match_all( '/\S+/u', $text );

		if ( false === $words || 0 === $words ) {
			return 1;
		}

		return max( 1, (int) ceil( $words / $words_per_minute ) );
	}
}
