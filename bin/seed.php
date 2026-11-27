<?php
/**
 * Seeds the test site: pages, posts and books with a spread of templates, including one
 * that points at a template the theme no longer has. Run with `wp eval-file bin/seed.php`.
 *
 * @package Page_Template_Column
 */

$items = array(
	// Post type, title, template (null = no meta at all), status.
	array( 'page', 'About', null, 'publish' ),
	array( 'page', 'Home', 'templates/landing.php', 'publish' ),
	array( 'page', 'Pricing', 'templates/landing.php', 'publish' ),
	array( 'page', 'Contact', 'templates/full-width.php', 'publish' ),
	array( 'page', 'Old Promo', 'templates/retired-promo.php', 'publish' ),
	array( 'page', 'Careers', 'default', 'draft' ),
	array( 'post', 'Release notes', 'templates/full-width.php', 'publish' ),
	array( 'post', 'Patch day', null, 'publish' ),
	array( 'book', 'The Long Game', 'templates/book-feature.php', 'publish' ),
	array( 'book', 'Field Guide', 'templates/book-feature.php', 'publish' ),
	array( 'book', 'Short Stories', null, 'publish' ),
	array( 'note', 'A note', null, 'publish' ),
);

foreach ( $items as list( $type, $title, $template, $status ) ) {
	$id = wp_insert_post(
		array(
			'post_type'   => $type,
			'post_title'  => $title,
			'post_status' => $status,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	// update_post_meta, not page_template: wp_insert_post rejects templates the theme lacks.
	if ( null !== $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
}

// WordPress's default post, Sample Page and Privacy Policy would only add noise to the counts.
foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_name__in' => array( 'hello-world', 'sample-page', 'privacy-policy' ), 'post_status' => 'any' ) ) as $post ) {
	wp_delete_post( $post->ID, true );
}

WP_CLI::success( count( $items ) . ' items seeded.' );
