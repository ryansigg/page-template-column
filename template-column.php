<?php
/**
 * Plugin Name:       Template Column
 * Plugin URI:        https://github.com/ryansigg/template-column
 * Description:       Shows which template every page, post and custom post type uses, right in the admin list. Sort by it, filter by it, and spot templates that no longer exist.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Ryan Sigg
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       template-column
 *
 * @package Template_Column
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-template-column.php';

add_action( 'admin_init', array( Template_Column::class, 'boot' ) );
