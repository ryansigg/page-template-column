=== Page Template Column ===
Contributors: ryansigg
Tags: page template, admin columns, templates, custom post types
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See which template every page, post and custom post type uses, right in the admin list. Sort by it, filter by it, and spot templates that no longer exist.

== Description ==

Page Template Column adds a Template column to the admin list for any post type whose posts can use a template.

* Shows the template's name ("Landing Page"), not its file name, or "Default template".
* Click the column header to sort by template name. The default template sorts first.
* Filter the list with the "All templates" dropdown. It lists every template the theme offers for that post type with a post count, including templates nothing uses yet.
* Click a template name in the column to see every post that uses it.
* Flags posts whose template the theme no longer has (after a theme switch or a removed file). WordPress quietly falls back to the default template for those, so they are easy to miss.
* Works with classic themes (Template Name headers, including Template Post Type) and block themes (custom templates).
* Works with Quick Edit: the row redraws with the new template.

No settings. Post types without any templates get no column.

== Frequently Asked Questions ==

= How do I hide the column on one post type? =

Use the `page_template_column_enabled` filter:

`add_filter( 'page_template_column_enabled', fn( $enabled, $post_type ) => 'post' === $post_type ? false : $enabled, 10, 2 );`

Each user can also hide it under Screen Options.

= Does it store anything? =

No. It reads the `_wp_page_template` value WordPress already keeps for each post.

== Changelog ==

= 2.0.0 =
* Rewritten. Detects post types automatically, sorts and filters by template, shows post counts, flags missing templates, supports block themes and Quick Edit.

= 1.0 =
* First version: a read-only Template column for a fixed list of post types.
