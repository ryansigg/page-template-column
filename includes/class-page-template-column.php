<?php
/**
 * The Template column, its filter dropdown and its sorting.
 *
 * @package Page_Template_Column
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a Template column to post list screens in the admin.
 *
 * Everything is wired up per screen: on edit.php for the post type being listed, and on
 * the Quick Edit save request, which redraws a single row and needs the same columns.
 */
final class Page_Template_Column {

	/** Post meta WordPress uses to store the assigned template. */
	const META_KEY = '_wp_page_template';

	/** Column key in the list table. */
	const COLUMN = 'template';

	/** Query string argument for the dropdown and the links in the column. Not "page_template": Quick Edit and Bulk Edit already post that name. */
	const QUERY_VAR = 'template_filter';

	/** Value WordPress stores (or implies, when the meta is absent) for "no template". */
	const DEFAULT_TEMPLATE = 'default';

	/**
	 * Post type this request is listing.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Available templates for the post type, slug => name.
	 *
	 * @var array<string, string>|null
	 */
	private $templates = null;

	/**
	 * Number of posts per template slug, including slugs whose template no longer exists.
	 *
	 * @var array<string, int>|null
	 */
	private $counts = null;

	/**
	 * Hooks the plugin into the screens that need it.
	 */
	public static function boot() {
		add_action(
			'load-edit.php',
			static function () {
				global $typenow;
				self::for_post_type( (string) $typenow );
			}
		);

		// Quick Edit redraws the saved row through admin-ajax; give it the same columns.
		add_action(
			'wp_ajax_inline-save',
			static function () {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core verifies the nonce in wp_ajax_inline_save(); this only reads which post type is being saved.
				$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
				self::for_post_type( $post_type );
			},
			0
		);
	}

	/**
	 * Registers the column hooks for one post type, if it uses templates.
	 *
	 * @param string $post_type Post type being listed.
	 * @return self|null The instance, or null when the post type gets no column.
	 */
	public static function for_post_type( $post_type ) {
		$object = get_post_type_object( $post_type );
		if ( ! $object || ! $object->show_ui ) {
			return null;
		}

		$instance = new self( $post_type );
		if ( ! $instance->is_enabled() ) {
			return null;
		}

		$instance->register_hooks();
		return $instance;
	}

	/**
	 * Constructor.
	 *
	 * @param string $post_type Post type being listed.
	 */
	private function __construct( $post_type ) {
		$this->post_type = $post_type;
	}

	/**
	 * Whether this post type gets the column.
	 *
	 * True when the theme offers templates for the post type, or when any of its posts
	 * already has a template assigned (so a template removed from the theme still shows up).
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$counts   = $this->counts();
		$assigned = array_diff_key( $counts, array( self::DEFAULT_TEMPLATE => true ) );
		$enabled  = ! empty( $this->templates() ) || ! empty( $assigned );

		/**
		 * Filters whether a post type's admin list shows the Template column.
		 *
		 * @param bool   $enabled   Whether the column is shown.
		 * @param string $post_type Post type being listed.
		 */
		return (bool) apply_filters( 'page_template_column_enabled', $enabled, $this->post_type );
	}

	/**
	 * Adds the WordPress hooks for this screen.
	 */
	private function register_hooks() {
		$type = $this->post_type;

		add_filter( "manage_edit-{$type}_columns", array( $this, 'add_column' ) );
		add_filter( "manage_edit-{$type}_sortable_columns", array( $this, 'add_sortable_column' ) );
		add_action( "manage_{$type}_posts_custom_column", array( $this, 'render_cell' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_filter' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );
		add_filter( 'posts_clauses', array( $this, 'sort_clauses' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles' ) );
	}

	/**
	 * Templates the theme offers for this post type.
	 *
	 * Works for classic themes (Template Name headers) and block themes (custom templates
	 * from theme.json and the Site Editor) alike, since core merges both here.
	 *
	 * @return array<string, string> Template slug => display name.
	 */
	public function templates() {
		if ( null === $this->templates ) {
			$this->templates = wp_get_theme()->get_page_templates( null, $this->post_type );
			asort( $this->templates, SORT_NATURAL | SORT_FLAG_CASE );
		}
		return $this->templates;
	}

	/**
	 * How many posts use each template, in one query.
	 *
	 * Trashed posts and auto-drafts are left out, to match the "All" view. Posts with no
	 * template meta, an empty value or "default" all count as the default template.
	 *
	 * @return array<string, int> Template slug => number of posts.
	 */
	public function counts() {
		if ( null !== $this->counts ) {
			return $this->counts;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one grouped count per screen load; there is no core API for this.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS template, COUNT(*) AS total
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft')
				GROUP BY pm.meta_value",
				self::META_KEY,
				$this->post_type
			)
		);

		$this->counts = array();
		foreach ( (array) $rows as $row ) {
			$slug                  = self::normalize( $row->template );
			$this->counts[ $slug ] = ( $this->counts[ $slug ] ?? 0 ) + (int) $row->total;
		}
		return $this->counts;
	}

	/**
	 * Maps the stored meta value to a template slug, folding every "no template" form together.
	 *
	 * @param mixed $value Stored meta value.
	 * @return string
	 */
	public static function normalize( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		return '' === $value ? self::DEFAULT_TEMPLATE : $value;
	}

	/**
	 * Whether a slug is assigned to posts but no longer offered by the theme.
	 *
	 * @param string $slug Template slug.
	 * @return bool
	 */
	public function is_missing( $slug ) {
		return self::DEFAULT_TEMPLATE !== $slug && ! isset( $this->templates()[ $slug ] );
	}

	/**
	 * Human-readable name for a template slug.
	 *
	 * @param string $slug Template slug.
	 * @return string
	 */
	public function label( $slug ) {
		if ( self::DEFAULT_TEMPLATE === $slug ) {
			return __( 'Default template', 'page-template-column' );
		}
		$templates = $this->templates();
		if ( isset( $templates[ $slug ] ) ) {
			return $templates[ $slug ];
		}
		/* translators: %s: template file or slug that is assigned but no longer exists. */
		return sprintf( __( '%s (missing)', 'page-template-column' ), $slug );
	}

	/**
	 * Adds the Template column after the Title column.
	 *
	 * @param array<string, string> $columns List table columns.
	 * @return array<string, string>
	 */
	public function add_column( $columns ) {
		$label  = __( 'Template', 'page-template-column' );
		$result = array();
		foreach ( $columns as $key => $name ) {
			$result[ $key ] = $name;
			if ( 'title' === $key ) {
				$result[ self::COLUMN ] = $label;
			}
		}
		if ( ! isset( $result[ self::COLUMN ] ) ) {
			$result[ self::COLUMN ] = $label;
		}
		return $result;
	}

	/**
	 * Makes the column sortable.
	 *
	 * @param array<string, mixed> $columns Sortable columns.
	 * @return array<string, mixed>
	 */
	public function add_sortable_column( $columns ) {
		$columns[ self::COLUMN ] = self::COLUMN;
		return $columns;
	}

	/**
	 * Prints the template name for one row, linked to the list filtered by that template.
	 *
	 * @param string $column  Column being rendered.
	 * @param int    $post_id Post in this row.
	 */
	public function render_cell( $column, $post_id ) {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$slug = self::normalize( get_post_meta( $post_id, self::META_KEY, true ) );
		$url  = add_query_arg(
			array(
				'post_type'     => $this->post_type,
				self::QUERY_VAR => rawurlencode( $slug ),
			),
			admin_url( 'edit.php' )
		);

		if ( $this->is_missing( $slug ) ) {
			printf(
				'<a class="page-template-column-missing" href="%1$s" title="%2$s"><span class="dashicons dashicons-warning" aria-hidden="true"></span> %3$s</a>',
				esc_url( $url ),
				esc_attr__( 'This template is assigned but the active theme no longer has it, so the default template is used.', 'page-template-column' ),
				esc_html( $this->label( $slug ) )
			);
			return;
		}

		printf(
			'<a class="%1$s" href="%2$s">%3$s</a>',
			esc_attr( self::DEFAULT_TEMPLATE === $slug ? 'page-template-column-default' : 'page-template-column-name' ),
			esc_url( $url ),
			esc_html( $this->label( $slug ) )
		);
	}

	/**
	 * Prints the "All templates" dropdown above the list, with a post count per template.
	 *
	 * Lists every template the theme offers, used or not, then any missing ones.
	 *
	 * @param string $post_type Post type being listed.
	 * @param string $which     Which tablenav, top or bottom.
	 */
	public function render_filter( $post_type, $which = 'top' ) {
		if ( $post_type !== $this->post_type || 'top' !== $which ) {
			return;
		}

		$counts  = $this->counts();
		$current = $this->requested_template();
		$options = array( self::DEFAULT_TEMPLATE => $this->label( self::DEFAULT_TEMPLATE ) ) + $this->templates();
		foreach ( array_keys( $counts ) as $slug ) {
			if ( ! isset( $options[ $slug ] ) ) {
				$options[ $slug ] = $this->label( $slug );
			}
		}

		echo '<label class="screen-reader-text" for="page-template-column-filter">' . esc_html__( 'Filter by template', 'page-template-column' ) . '</label>';
		echo '<select name="' . esc_attr( self::QUERY_VAR ) . '" id="page-template-column-filter">';
		echo '<option value="">' . esc_html__( 'All templates', 'page-template-column' ) . '</option>';
		foreach ( $options as $slug => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $slug ),
				selected( $current, (string) $slug, false ),
				/* translators: 1: template name, 2: number of posts using it. */
				esc_html( sprintf( __( '%1$s (%2$s)', 'page-template-column' ), $label, number_format_i18n( $counts[ $slug ] ?? 0 ) ) )
			);
		}
		echo '</select>';
	}

	/**
	 * Template slug requested in the query string, if it is one this screen knows about.
	 *
	 * @return string Slug, or an empty string for no filter.
	 */
	public function requested_template() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, like core's own dropdowns.
		$value = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';
		if ( '' === $value ) {
			return '';
		}
		$known = array( self::DEFAULT_TEMPLATE => true ) + $this->templates() + $this->counts();
		return isset( $known[ $value ] ) ? $value : '';
	}

	/**
	 * Whether a query is this screen's main list query.
	 *
	 * @param WP_Query $query Query.
	 * @return bool
	 */
	private function is_list_query( $query ) {
		return is_admin() && $query->is_main_query() && $this->post_type === $query->get( 'post_type' );
	}

	/**
	 * Applies the template filter to the list query.
	 *
	 * @param WP_Query $query Query being prepared.
	 */
	public function filter_query( $query ) {
		if ( ! $this->is_list_query( $query ) ) {
			return;
		}

		$slug = $this->requested_template();
		if ( '' === $slug ) {
			return;
		}

		if ( self::DEFAULT_TEMPLATE === $slug ) {
			$clause = array(
				'relation' => 'OR',
				array(
					'key'     => self::META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META_KEY,
					'value'   => array( '', self::DEFAULT_TEMPLATE ),
					'compare' => 'IN',
				),
			);
		} else {
			$clause = array(
				'key'   => self::META_KEY,
				'value' => $slug,
			);
		}

		$meta_query   = (array) $query->get( 'meta_query' );
		$meta_query[] = $clause;
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Sorts by template name (not file name) when the column header is clicked.
	 *
	 * The default template sorts first; missing templates sort by their slug, after the rest.
	 *
	 * @param array<string, string> $clauses SQL clauses.
	 * @param WP_Query              $query   Query.
	 * @return array<string, string>
	 */
	public function sort_clauses( $clauses, $query ) {
		if ( ! $this->is_list_query( $query ) || self::COLUMN !== $query->get( 'orderby' ) ) {
			return $clauses;
		}

		global $wpdb;
		$order = 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC';
		$value = "COALESCE(NULLIF(tplcol.meta_value, ''), 'default')";

		$cases = array( $wpdb->prepare( 'WHEN %s THEN %s', self::DEFAULT_TEMPLATE, '' ) );
		foreach ( $this->templates() as $slug => $name ) {
			$cases[] = $wpdb->prepare( 'WHEN %s THEN %s', $slug, '1' . strtolower( $name ) );
		}

		$clauses['join'] .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} tplcol ON tplcol.post_id = {$wpdb->posts}.ID AND tplcol.meta_key = %s",
			self::META_KEY
		);
		// Prefixes keep the groups in order: default (""), named templates ("1…"), missing ("2…").
		$clauses['orderby'] = "CASE {$value} " . implode( ' ', $cases ) . " ELSE '2' END {$order}, "
			. "{$value} {$order}, {$wpdb->posts}.post_title ASC";

		return $clauses;
	}

	/**
	 * Adds the few styles the column needs.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function add_styles( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}
		wp_add_inline_style(
			'list-tables',
			'.fixed .column-template{width:15%}'
			. '.column-template .page-template-column-default{color:#646970}'
			. '.column-template .page-template-column-missing{color:#b32d2e}'
			. '.column-template .page-template-column-missing .dashicons{font-size:16px;width:16px;height:16px;vertical-align:text-bottom}'
		);
	}
}
