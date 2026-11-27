<?php
/**
 * Test theme setup, plus the post types the tests list: Books (with templates) and Notes (without).
 *
 * @package Template_Column_Test
 */

add_action(
	'init',
	static function () {
		register_post_type(
			'book',
			array(
				'label'    => 'Books',
				'public'   => true,
				'show_ui'  => true,
				'supports' => array( 'title', 'editor' ),
			)
		);
		register_post_type(
			'note',
			array(
				'label'    => 'Notes',
				'public'   => false,
				'show_ui'  => true,
				'supports' => array( 'title' ),
			)
		);
	}
);
