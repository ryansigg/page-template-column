<?php
/**
 * Fallback template for the test theme.
 *
 * @package Template_Column_Test
 */

get_header();
while ( have_posts() ) {
	the_post();
	the_title( '<h1>', '</h1>' );
	the_content();
}
get_footer();
