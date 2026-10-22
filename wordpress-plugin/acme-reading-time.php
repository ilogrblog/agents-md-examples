<?php
/**
 * Plugin Name:       Acme Reading Time
 * Description:       Shows an estimated reading time above single posts.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-reading-time
 *
 * @package Acme_Reading_Time
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-acme-reading-time.php';

/**
 * Prepends the reading time to the content of a single post.
 *
 * @param string $content Post content.
 * @return string
 */
function acme_reading_time_prepend( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$minutes = Acme_Reading_Time::minutes( wp_strip_all_tags( $content ) );

	$label = sprintf(
		/* translators: %d: estimated reading time in minutes. */
		_n( '%d minute read', '%d minutes read', $minutes, 'acme-reading-time' ),
		$minutes
	);

	return '<p class="acme-reading-time">' . esc_html( $label ) . '</p>' . $content;
}
add_filter( 'the_content', 'acme_reading_time_prepend' );
