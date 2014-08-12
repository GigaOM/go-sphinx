<?php
class GO_Sphinx
{
	public $id_base = 'go-sphinx';
	public $admin  = FALSE;
	public $wpdb   = NULL; // our wpdb interface to sphinxql
	public $log_debug_info = FALSE;
	public $error_429_on_query_error = FALSE;
	public $test = FALSE;
	public $version = 3;
	public $messages = array();
	public $index_name = FALSE;
	public $filter_args = array();
	public $query_modified = FALSE; // did another plugin modify the current query?
	public $search_stats = array();
	public $posts_per_page = 10;
	public $max_results = 1000;
	public $secondary_index_postfix = '_delta';
	public $qv_debug = 'go-sphinx-debug';
	public $qv_use_sphinx = 'go-sphinx-use';
	public $options = NULL;

	public $filters_to_watch = array(
		'posts_search',
		'posts_where',
		'posts_join',
		'posts_where_paged',
		'posts_groupby',
		'posts_join_paged',
		'posts_orderby',
		'posts_distinct',
		'posts_limits',
		'posts_fields',
		'posts_clauses',
		'posts_where_request',
		'posts_groupby_request',
		'posts_join_request',
		'posts_orderby_request',
		'posts_distinct_request',
		'posts_fields_request',
		'posts_limits_request',
		'posts_clauses_request',
		'posts_request',
	);
	public $supported_query_vars = array(
		'author',
		'authors',
		'author_name',
		'category__in',
		'category__not_in',
		'category__and',
		'offset',
		'order',
		'orderby',
		'paged',
		'post__in',
		'post__not_in',
		'post_parent',
		'post_status',
		'post_type',
		'posts_per_page',
		'numberposts',
		's',
		'tag',
		'tag_id',
		'tag__in',
		'tag__not_in',
		'tag__and',
		'tag_slug__in',
		'tag_slug__and',
		'tax_query',

		// these are allowed to pass through either because we allow them
		// or because they're alternate keys used by WP
		'exclude',
		'feed',
		'fields',
		'ignore_sticky_posts',
		'include',
		'no_found_rows',
		'numberposts',
		'output',
		'suppress_filters',
		'wijax',
	);
	// supported orderby keywords
	public $supported_order_by = array(
		'comment_count',
		'date',
		'post_date',
		'ID',
		'modified',
		'post_modified',
		'none',
		'parent',
		'post_parent',
		'rand',
		'title',
		'post_title',
	);

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 10 );

		global $wpdb;
		$this->index_name = $wpdb->posts . $this->secondary_index_postfix . ',' . $wpdb->posts;

		// the admin settings page
		if ( is_admin() )
		{
			$this->admin();

			// optionally enable sphinx in admin dashboard/ajax. this allows
			// us to run our tests in two modes: one to compare sphinx
			// results with wp query results (without any query param), and
			// one to compare the results of our implementation with the
			// results of direct sphinx queries (with the
			// $this->qv_use_sphinx=1 url param).
			if ( $this->force_use_sphinx() )
			{
				$this->add_filters();
			}
		}//END if
		else
		{
			$this->add_filters();
		}
	}//END __construct

	public function init()
	{
		if ( $this->is_debug() )
		{
			$plugin_url = untrailingslashit( plugin_dir_url( __FILE__ ) );
			wp_register_script( 'go-sphinx-js', $plugin_url . '/js/go-sphinx.js', array( 'jquery' ), $this->version, TRUE );
			wp_enqueue_script( 'go-sphinx-js' );
		}
	}//END init

	/**
	 * plugin options getter
	 */
	public function options()
	{
		if ( empty( $this->options ) )
		{
			$this->options = (object) apply_filters(
				'go_config',
				wp_parse_args( (array) get_option( $this->id_base ), (array) $this->options_default() ),
				$this->id_base
			);
		}

		return $this->options;
	}// END options

	public function options_default()
	{
		return (object) array(
			'server'      => '127.0.0.1',
			'port'        => 9312,
			'mysql_port'  => 9306,
			'timeout'     => 1,
			'arrayresult' => TRUE,
			'log_debug_info' => FALSE,
			'error_429_on_query_error' => TRUE,
			'ranker' => 'proximity_bm25',
		);
	}//END options_default

	/**
	 * get an instance of our wpdb connection to sphinx. (not to be confused
	 * with global $wpdb)
	 */
	public function wpdb()
	{
		if ( ! $this->wpdb )
		{
			$this->wpdb = new wpdb(
				'unused_username',
				'unused_password',
				'unused_dbname',
				$this->options()->server . ':' . $this->options()->mysql_port
			);
		}//END if

		return $this->wpdb;
	}//END wpdb

	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-go-sphinx-admin.php';
			$this->admin = new GO_Sphinx_Admin;
		}

		return $this->admin;
	}//END admin

	public function search_test()
	{
		$this->test()->search_test();
	}//END search_test

	public function test()
	{
		if ( ! $this->test )
		{
			require_once __DIR__ . '/class-go-sphinx-test.php';
			$this->test = new GO_Sphinx_Test;
		}

		return $this->test;
	}//END test

	public function add_filters()
	{
		add_filter( 'parse_query', array( $this, 'parse_query' ), 1 );

		$this->add_tester_filters();

		// filters to inject sphinx search results if applicable
		add_filter( 'split_the_query', array( $this, 'split_the_query' ), 101, 2 );
		add_filter( 'posts_request_ids', array( $this, 'posts_request_ids' ), 101, 2 );
		add_filter( 'found_posts_query', array( $this, 'found_posts_query' ), 101 );
		add_filter( 'found_posts', array( $this, 'found_posts' ), 101, 2 );

		// used for scriblio facets integration
		add_filter( 'scriblio_pre_get_matching_post_ids', array( $this, 'scriblio_pre_get_matching_post_ids' ), 10, 2 );
	}//END add_filters

	// initialize our states in this callback, which is invoked at the
	// beginning of each query.
	public function parse_query( $query )
	{
		$this->messages[] = 'parse_query(): parsing a new query';

		//TODO: add all filters here if we're going to remove them all
		// after the query's over
		$this->query_modified = FALSE;
		$this->filter_args = array();
		$this->search_stats = array();

		return $query;
	}//END parse_query

	// filters to check if another plugin has modified wp query
	public function add_tester_filters()
	{
		// hook our callback before and after all other callbacks
		foreach ( $this->filters_to_watch as $filter )
		{
			add_filter( $filter, array( $this, 'check_query' ), 1 );
			add_filter( $filter, array( $this, 'check_query' ), 101 );
		}
	}//END add_tester_filters

	/**
	 * check if user supplied an override to use sphinx
	 */
	public function force_use_sphinx()
	{
		return ( isset( $_GET[ $this->qv_use_sphinx ] ) && '1' == $_GET[ $this->qv_use_sphinx ] );
	}//END force_use_sphinx

	/**
	 * check if user supplied an override to NOT use sphinx
	 */
	public function force_no_sphinx()
	{
		return ( isset( $_GET[ $this->qv_use_sphinx ] ) && '1' != $_GET[ $this->qv_use_sphinx ] );
	}//END force_no_sphinx

	// check if we're in debug mode or not
	public function is_debug()
	{
		return ( isset( $_GET[ $this->qv_debug ] ) && current_user_can( 'edit_others_posts' ) );
	}//END is_debug

	// the callback to track whether another plugin has altered the
	// query being processed
	public function check_query( $request )
	{
		if ( $this->query_modified )
		{
			return $request; // already decided to not use
		}

		$current_filter = current_filter();

		if ( ! isset( $this->filter_args[ $current_filter ] ) )
		{
			$this->filter_args[ $current_filter ] = $request;
		}
		elseif ( $request != $this->filter_args[ $current_filter ] )
		{
			// a plugin has altered the query in some way
			$this->query_modified = TRUE;
		}

		return $request;
	}//END check_query

	// check if we can use sphinx on this query or not
	public function use_sphinx( $wp_query )
	{
		// cannot use sphinx if the query has been modified by another plugin
		if ( $this->query_modified )
		{
			$this->messages[] = 'use_sphinx() FALSE: query_modified is TRUE';
			return FALSE;
		}

		// parse $wp_query to determine if we can use sphinx for this
		// search or not.
		// check manual override first
		if ( $this->force_no_sphinx() && current_user_can( 'edit_others_posts' ) )
		{
			$this->messages[] = 'use_sphinx() FALSE: get_sphinx_override() is TRUE';
			return FALSE;
		}

		// do not enable sphinx if we receive an unsupported query var.
		// note we must handle query vars which're taxonomy names.
		$queried_taxonomies = $this->extract_taxonomies( $wp_query );
		foreach ( $wp_query->query as $key => $val )
		{
			if (
				! in_array( $key, $this->supported_query_vars ) &&
				! empty( $wp_query->query[ $key ] ) &&
				! in_array( $key, $queried_taxonomies )
			)
			{
				$this->messages[] = 'use_sphinx() FALSE: query contains unsupported query_var: ' . $key . '=' . var_export( $val, TRUE );
				return FALSE;
			}
		}//END foreach

		if (
			isset( $wp_query->query['orderby'] ) &&
			! in_array( $wp_query->query['orderby'], $this->supported_order_by )
		)
		{
			$this->messages[] = 'use_sphinx() FALSE: query contains unsupported order_by: ' . var_export( $wp_query->query['orderby'], TRUE );
			return FALSE;
		}

		$this->messages[] = 'use_sphinx() TRUE';
		return TRUE;
	}//END use_sphinx

	// returns TRUE if we want the query to be "split", which means
	// WP will first get the result post ids, and then look up the
	// corresponding objects. we want to split the query when we
	// use sphinx for the search results
	public function split_the_query( $split_the_query, $wp_query )
	{
		return $this->use_sphinx( $wp_query ) ? TRUE : $split_the_query;
	}//END split_the_query

	// replace the request (SQL) to come up with search result post ids
	public function posts_request_ids( $request, $wp_query )
	{
		if ( ! $this->use_sphinx( $wp_query ) )
		{
			if ( $this->options()->log_debug_info )
			{
				$this->messages[] = 'posts_request_ids() use_sphinx() returned FALSE';
				error_log( print_r( $this->messages, TRUE ) . "\n" . print_r( $wp_query, TRUE ) );
			} // END if

			return $request;
		}

		global $wpdb;

		// return a SQL query that encodes the sphinx search results like
		// SELECT ID from wp_posts
		// WHERE ID IN ( 5324, 1231 ) ORDER BY FIELD( ID, 5324, 1231 )
		$t0 = microtime( TRUE );
		$result_ids = $this->sphinx_query( $wp_query );

		$this->search_stats['elapsed_time'] = round( microtime( TRUE ) - $t0, 6 );
		// save the original request before overriding it.
		$this->search_stats['wp_request'] = $request;

		if ( is_wp_error( $result_ids ) )
		{
			$this->search_stats['error'] = $result_ids;

			if ( $this->options()->log_debug_info )
			{
				$this->messages[] = 'posts_request_ids() got an error from sphinx_query()';
				error_log( print_r( $this->messages, TRUE ) . "\n" . print_r( $wp_query, TRUE ) );
			} // END if

			return $request;
		}//END if

		if ( $this->is_debug() )
		{
			$this->search_stats['posts_mysql'] = $wpdb->get_col( $request );
			$this->search_stats['posts_sphinx'] = $result_ids;
			$this->search_stats['posts_equality'] = ( $this->search_stats['posts_mysql'] == $this->search_stats['posts_sphinx'] );

			wp_localize_script( 'go-sphinx-js', 'sphinx_results', (array) $this->search_stats );
			wp_localize_script( 'go-sphinx-js', 'sphinx_messages', (array) $this->messages );
		}

		if ( 0 < count( $result_ids ) )
		{
			$this->messages[] = 'posts_request_ids() returning post_ids from Sphinx';

			$request = "SELECT ID FROM $wpdb->posts WHERE ID IN (" . implode( ',', $result_ids ) . ') ORDER BY FIELD ( ID, ' . implode( ',', $result_ids ) . ')';
		}
		else
		{
			$this->messages[] = 'posts_request_ids() returning a query intended to fail';

			// return a sql that returns nothing
			$request = "SELECT ID FROM $wpdb->posts WHERE 1 = 0";
		}

		return $request;
	}//END posts_request_ids

	// set the query to find out how many posts were found by the query.
	// we replace the incoming query with a simple static sql query
 	// since we'll set the found_posts # ourselves and don't want to
	// make WP/mysql do any unnecessary additional work.
	public function found_posts_query( $param )
	{
		if ( ! isset( $this->total_found ) || ! $this->total_found )
		{
			return $param;
		}

		return 'SELECT 0';
	}//END found_posts_query

	// overrides the number of posts found, this affects pagination.
	public function found_posts( $found_posts, $unused_wp_query )
	{
		if ( isset( $this->total_found ) && $this->total_found )
		{
			$found_posts = $this->total_found;
		}

		return $found_posts;
	}//END found_posts

	// used for scriblio facets integration. this filter callback is
	// invoked before scriblio facets tries to execute a sql query
	// to get the query results. if we have scriblio results here then
	// we can return it in $result_ids, up to $max number of them.
	// we return $result_ids if we're able to use sphinx results, else
	// we return FALSE.
	//
	// caveat: we will only return the smaller of $max or $this->max_results
	//
	public function scriblio_pre_get_matching_post_ids( $unused, $unused_max )
	{
		if ( ! isset( $this->matched_posts ) || empty( $this->matched_posts ) )
		{
			return FALSE;
		}

		return $this->matched_posts;
	}//END scriblio_pre_get_matching_post_ids

	// perform a sphinxql query that's equivalent to the $wp_query
	// returns WP_Error if we cannot use sphinx for this query.
	public function sphinx_query( $wp_query )
	{
		// parts of the sphinxql query string w're building
		$sphinxql_select = array( 'id' );
		$sphinxql_where = array();
		$sphinxql_orderby = '';
		$sphinxql_limit = '';

		// these WP query vars are implemented as sphinxql WHERE clauses
		// *** author ***
		$res = $this->sphinx_query_author( $wp_query );
		if ( ! empty( $res ) )
		{
			$sphinxql_where[] = $res;
		}

		// *** order and orderby ***
		if ( is_wp_error( $res = $this->sphinx_query_ordering( $wp_query ) ) )
		{
			$this->messages[] = 'sphinx_query(): sphinx_query_ordering() returned an error';
			return $res;
		}
		if ( ! empty( $res['orderby'] ) )
		{
			$sphinxql_orderby = 'ORDER BY ' . $res['orderby'];
			if ( ! empty( $res['ordering'] ) )
			{
				$sphinxql_orderby .= ' ' . $res['ordering'];

				// we need to select the field we're ordering by unless it's
				// 'rand()' or 'id' (already included)
				if ( 'rand()' != $res['orderby'] && 'id' != $res['orderby'] )
				{
					$sphinxql_select[] = $res['orderby'];
				}
			}//END if
		}//END if

		// *** tax_query ***
		$res = $this->sphinx_query_taxonomy( $wp_query );
		if ( ! empty( $res ) )
		{
			$sphinxql_where = array_merge( $sphinxql_where, $res );
		}

		// *** post__in and post__not_in ***
		$res = $this->sphinx_query_post_in_not_in( $wp_query );
		if ( ! empty( $res ) )
		{
			$sphinxql_where = array_merge( $sphinxql_where, $res );
		}

		// *** post_parent ***
		$res = $this->sphinx_query_post_parent( $wp_query );
		if ( is_wp_error( $res ) )
		{
			$this->messages[] = 'sphinx_query(): sphinx_query_post_parent() returned an error';
			return $res;
		}
		if ( ! empty( $res ) )
		{
			$sphinxql_where[] = $res;
		}

		// pagination
		$sphinxql_limit = $this->sphinx_query_pagination( $wp_query );

		// build the MATCH() conditions
		$query_strs = array();
		$query_strs[] = $this->sphinx_query_keyword( $wp_query );
		$query_strs[] = $this->sphinx_query_post_type( $wp_query );
		$query_strs[] = $this->sphinx_query_post_status( $wp_query );
		$query_strs = array_filter( $query_strs );

		if ( ! empty( $query_strs ) )
		{
			$sphinxql_where[] = 'MATCH( \'' . implode( ' ', $query_strs ) . '\' )';
		}

		$the_query =
			'SELECT ' . implode( ', ', $sphinxql_select ) .
			' FROM ' . $this->index_name .
			' WHERE ' . implode( ' AND ', $sphinxql_where ) . ' ' .
			$sphinxql_orderby . ' ' .
			$sphinxql_limit .
			' OPTION ranker=' . $this->options()->ranker . ', max_matches=' . $this->max_results .';';

		$this->total_found = 0;
		$results = $this->wpdb()->get_col( $the_query );

		if ( ! empty( $this->wpdb()->last_error ) )
		{
			$error_message = 'sphinx_query(): sphinxql error: ' . $this->wpdb()->last_error . ' for query: ' . $the_query;

			$this->messages[] = $error_message;

			error_log( 'go-sphinx wpdb error: ' . $error_message );

			// 429 on all errors
			if ( $this->options()->error_429_on_query_error )
			{
				wp_die( '<p>Wow, it\'s hot in here!</p><p>The servers are really busy right now, please try your request again in a moment.</p>', 'Whoa Nelly!', array( 'response' => 429 ) );
			} // END if

			return new WP_Error( 'sphinx query error', $error_message );
		}//END if

		if ( empty( $results ) )
		{
			$this->messages[] = 'sphinx_query(): sphinxql wpdb client returned an empty result set';
			return array();
		}

		$meta = $this->wpdb()->get_results( 'SHOW META;' );
		if ( ! empty( $meta ) )
		{
			foreach ( $meta as $meta_val )
			{
				if ( 'total_found' == $meta_val->Variable_name )
				{
					$this->total_found = (int) $meta_val->Value;
					break;
				}
			}
		}//END if

		if ( $this->is_debug() )
		{
			$this->search_stats['sphinx_results'] = $results;
		}

		$this->matched_posts = $results;
		$this->messages[] = 'sphinx_query(): the sphinx client returned ' . $this->total_found .' total results';

		return array_slice( $this->matched_posts, 0, $this->posts_per_page );
	}//END sphinx_query


	/**
	 * parse the author param in $wp_query and build a sphinxql WHERE
	 * clause for the author query if it exists.
	 *
	 * author param could be one of:
	 *  1) not set
	 *  2) single neg
	 *  3) list of 1 or more pos ints
	 *
	 * @retval a sphinxql WHERE clause if there is an author query
	 * @retval NULL if $wp_query does not contain an author query
	 *
	 * No WP_Error need be returned, but if not set or NOT operator, set the sphinx filter appropriately.
	 */
	public function sphinx_query_author( $wp_query )
	{
		if ( ! $wp_query->is_author || ( ! $wp_query->get( 'author' ) && ! $wp_query->get( 'author_name' ) ) )
		{
			return NULL;
		}

		if ( isset( $wp_query->query['author'] ) || isset( $wp_query->query['author_name'] ) )
		{
			$author_id = -1;    // default must be an invalid author id
			$exclusion = FALSE; // is this an exclusion search?

			if ( isset( $wp_query->query['author'] ) )
			{
				// we expect this to be an author id
				if ( is_numeric( $wp_query->query['author'] ) )
				{
					$author_id = (int) $wp_query->query['author'];

					// check for existence of NOT operator ("-"):
					if ( ( 0 > $author_id ) && ( -1 != $author_id ) )
					{
						$exclusion = TRUE;
						$author_id = abs( $author_id );
					}
				}//END if
			}//END if
			else
			{
				// get an author id from author name
				$author_name = $wp_query->query['author_name'];

				$exclude_position = strpos( $author_name, '-' );
				if ( FALSE !== $exclude_position )
				{
					$author_name = substr( $author_name, $exclude_position + 1 );
					$exclusion = TRUE;
				}

				$user = get_user_by( 'slug', $author_name );
				if ( FALSE !== $user )
				{
					$author_id = $user->ID;
				}
			}//END else

			return '( post_author ' . ( $exclusion ? '!= ' : '= ' ) . $author_id . ' )';
		} // END if

		return NULL;
	}//END sphinx_query_author

	/**
	 * parse order and orderby params in $wp_query and convert them into a
	 * sphinxql ORDER BY clause.
	 *
	 * @retval an array containing the ORDER BY field ('orderby') and the
	 *  ordering ('ordering') if all goes well
	 * @retval WP_Error if we encounter an error or if the wp_query is not
	 *  supported.
	 */
	public function sphinx_query_ordering( $wp_query )
	{
		$res = array(
			'orderby' => NULL,
			'ordering' => isset( $wp_query->query['order'] ) ? $wp_query->query['order'] : 'DESC',
		);

		if ( isset( $wp_query->query['orderby'] ) )
		{
			switch ( $wp_query->query['orderby'] )
			{
				case 'none':
					break;

				case 'ID':
					$res['orderby'] = 'id';
					break;

				case 'date':
				case 'post_date':
					$res['orderby'] = 'post_date_gmt';
					break;

				case 'modified':
					$res['orderby'] = 'post_modified_gmt';
					break;

				case 'parent':
					$res['orderby'] = 'post_parent';
					break;

				case 'rand':
					$res['orderby'] = 'rand()';
					$res['ordering'] = NULL; // not valid in combination with rand()
					break;

				case 'comment_count':
					$res['orderby'] = 'comment_count';
					break;

				default:
					return new WP_Error( 'unsupported sphinx query orderby ' . $wp_query->query['orderby'], 'unsupported sphinx query orderby value' );
			}//END switch
		}//END if
		else
		{
			// the default ordering depends on whether this is a keyword
			// search or not. in general we use the same default ordering
			// as WP_Query, which's the post date
			$res['orderby'] = 'post_date_gmt';

			// but if this is a keyword query then we don't want to set
			// a default order which will fall back to using sphinx's ranking
			if ( isset( $wp_query->query['s'] ) && ! empty( $wp_query->query['s'] ) )
			{
				$res['orderby'] = FALSE;
			}
		}//END else

		return $res;
	}//END sphinx_query_ordering

	/**
	 * parse the tax_query params in $wp_query and convert them into
	 * sphinxql WHERE clauses.
	 *
	 * @retval TRUE if we're able to parse the wp_query.
	 * @retval WP_Error if we encounter an error or if the wp_query is not
	 *  supported.
	 */
	public function sphinx_query_taxonomy( $wp_query )
	{
		$wheres = array();

		if ( ! is_array( $wp_query->tax_query->queries ) || empty( $wp_query->tax_query->queries ) )
		{
			return $wheres;
		}

		if ( 'AND' == $wp_query->tax_query->relation )
		{
			foreach ( $wp_query->tax_query->queries as $query )
			{
				// we've seen $wp_query->tax_query->queries to contain
				// WP_Errors (https://github.com/GigaOM/legacy-pro/issues/2231)
				// which may result from url manipulation by users
				if ( is_wp_error( $query ) )
				{
					$this->messages[] = 'sphinx_query_taxonomy(): found a WP_Error in $wp_query->tax_query->queries (' . print_r( $query, TRUE ) . ')';
					continue;
				}

				// use WP_Tax_Query::transform_query() to find term tax ids
				$wp_query->tax_query->transform_query( $query, 'term_taxonomy_id' );
				if ( is_wp_error( $query ) )
				{
					//TODO: report back that one or more requested terms
					// were excluded from the search criteria
					$this->messages[] = 'WP_Tax_Query::transform_query() returned an error: ' . print_r( $query, TRUE );
					continue;
				}

				if ( empty( $query['terms'] ) )
				{
					// if a tax query has no term then it means some or all
					// of the terms could not be resolved. in this case we
					// set a filter that'll block all results to ensure
					// the final query result will be empty
					// see https://github.com/Gigaom/legacy-pro/issues/673
					$wheres[] = '( tt_id = -1 )';
					break;
				}
				if ( 'AND' == $query['operator'] )
				{
					// one filter per ttid
					foreach ( $query['terms'] as $ttid )
					{
						$wheres[] = '( tt_id = ' . $ttid . ' )';
					}
				}
				else
				{
					// operator = "IN" or "NOT IN"
					$wheres[] = '( tt_id ' . $query['operator'] . ' ( ' . implode( ', ', $query['terms'] ) . ' ) )';
				}
			}//END foreach
		}//END if
		else
		{
			// the OR relation:
			// we do not support the outer OR + inner AND case nor the
			// outer OR + inner "NOT IN" case
			$ttids = array();
			foreach ( $wp_query->tax_query->queries as $query )
			{
				if ( empty( $query['terms'] ) )
				{
					// see notes above about github issue 673
					$wheres[] = '( tt_id = -1 )';
					break;
				}
				if ( 'IN' != $query['operator'] )
				{
					return new WP_Error( 'unsupported sphinx query', 'unsupported sphinx query (OR relation with AND or "NOT IN" operator(s))' );
				}
				$wp_query->tax_query->transform_query( $query, 'term_taxonomy_id' );
				$ttids = array_merge( $ttids, $query['terms'] );
			}//END foreach

			$wheres[] = '( tt_id IN ( ' . implode( ', ', $ttids ) . ' ) )';
		}//END else

		return $wheres;
	}//END sphinx_query_taxonomy

	/**
	 * parse the post__in and post__not_in params in $wp_query and convert
	 * them into a sphinxql WHERE clause.
	 *
	 * @retval an array of sphinxql WHERE clauses if $wp_query contains
	 *  post__in and/or post__not_in params
	 * @retval an empty array if post__in and post__not_in are not found in
	 *  $wp_query
	 */
	public function sphinx_query_post_in_not_in( $wp_query )
	{
		$wheres = array();

		if ( ! isset( $wp_query->query['post__in'] ) && ! isset( $wp_query->query['post__not_in'] ) )
		{
			return $wheres;
		}

		if ( ! empty( $wp_query->query['post__in'] ) )
		{
			$wheres[] = '( id IN ( ' . implode( ', ', $wp_query->query['post__in'] ) . ' ) )';
		}

		if ( ! empty( $wp_query->query['post__not_in'] ) )
		{
			$wheres[] = '( id NOT IN ( ' . implode( ', ', $wp_query->query['post__not_in'] ) . ' ) )';
		}

		return $wheres;
	}//END sphinx_query_post_in_not_in

	/**
	 * parse the post_parent param in $wp_query and 
	 *
	 * @retval a sphinxql WHERE clause to filter the post parent if $wp_query
	 *  contains the 'post_parent' param
	 * @retval WP_Error if we encounter an error or if the wp_query is not
	 *  supported.
	 */
	public function sphinx_query_post_parent( $wp_query )
	{
		if ( ! isset( $wp_query->query['post_parent'] ) )
		{
			return NULL;
		}

		// WP_Query only allows a single post_parent id
		if ( ! is_numeric( $wp_query->query['post_parent'] ) )
		{
			return new WP_Error( 'invalid post_parent id', 'invalid post_parent id (' . $wp_query->query['post_parent'] . ')' );
		}

		return '( post_parent = ' . $wp_query->query['post_parent'] . ' )';
	}//END sphinx_query_post_parent

	/**
	 * parse the pagination params in $wp_query and return the sphinxql
	 * LIMIT clause. we will always return a LIMIT clause using default
	 * values even if $wp_query doesn't contain any posts_per_page,
	 * offset or paged params.
	 */
	public function sphinx_query_pagination( $wp_query )
	{
		// defaults
		$offset = 0;
		$this->posts_per_page = 10;

		if ( isset( $wp_query->query['posts_per_page'] ) )
		{
			if ( 0 < $wp_query->query['posts_per_page'] )
			{
				$this->posts_per_page = $wp_query->query['posts_per_page'];
			}
			elseif ( -1 == $wp_query->query['posts_per_page'] )
			{
				$this->posts_per_page = $this->max_results;
			}
		}//END if

		if ( isset( $wp_query->query['offset'] ) && ( 0 < $wp_query->query['offset'] ) )
		{
			$offset = $wp_query->query['offset'];
		}
		elseif ( isset( $wp_query->query['paged'] ) && ( 0 < $wp_query->query['paged'] ) )
		{
			$offset = ( $wp_query->query['paged'] - 1 ) * $this->posts_per_page;
		}

		return 'LIMIT ' . $offset . ', ' . $this->max_results;
	}//END sphinx_query_pagination

	/**
	 * parse the keyword ('s') param in $wp_query and convert it to an
	 * equivalent sphinx query string.
	 *
	 * @retval the equivalent sphinx query string
	 */
	public function sphinx_query_keyword( $wp_query )
	{
		// If a search pattern is specified, load the posts that match
		if ( ! isset( $wp_query->query['s'] ) || empty( $wp_query->query['s'] ) )
		{
			return '';
		}

		// the keyword string is already escaped by WP (backslashes, quotes,
		// etc.). we just have to trim the query string before feeding it
		// into sphinxql. (not using wp_kses() here because the query string
		// is not going to be consumed by a browser but by sphinx.)
		return '@(post_content,content) ' . $this->sanitize_sphinx_query( $wp_query->query['s'] );
	}//END sphinx_query_keyword

	/**
	 * parse the post_type param in $wp_query and convert it to an equivalent
	 * sphinx query string.
	 *
	 * @retval the equivalent sphinx query string
	 */
	public function sphinx_query_post_type( $wp_query )
	{
		$query_str = '';
		if ( isset( $wp_query->query['post_type'] ) )
		{
			if ( 'any' != $wp_query->query['post_type'] )
			{
				if ( is_array( $wp_query->query['post_type'] ) )
				{
					$query_str = '@post_type ' . implode( ' | ', $wp_query->query['post_type'] );
				}
				else
				{
					$query_str = '@post_type ' . $wp_query->query['post_type'];
				}
			}//END if
		}//END if
		else
		{
			$query_str = '@post_type post'; // the WP default
		}

		return $query_str;
	}//END sphinx_query_post_type

	/**
	 * parse the post_status param in $wp_query and convert it to an equivalent
	 * sphinx query string.
	 *
	 * @retval the equivalent sphinx query string
	 */
	public function sphinx_query_post_status( $wp_query )
	{
		$query_str = '';
		if ( isset( $wp_query->query['post_status'] ) )
		{
			if ( is_array( $wp_query->query['post_status'] ) )
			{
				if ( ! in_array( 'any', $wp_query->query['post_status'] ) )
				{
					$query_str = '@post_status ' . implode( ' | ', $wp_query->query['post_status'] );
				}
			}
			elseif ( 'any' != $wp_query->query['post_status'] )
			{
				$query_str = '@post_status ' . $wp_query->query['post_status'];
			}
		}//END if
		else
		{
			$query_str = '@post_status publish'; // the WP default
		}
		return $query_str;
	}//END sphinx_query_post_status

	// find all taxonomies in wp_query's tax_query array and return them
	// in an array
	public function extract_taxonomies( $wp_query )
	{
		$taxonomies = array();

		if ( ! isset( $wp_query->tax_query->queries ) )
		{
			return $taxonomies;
		}

		foreach ( $wp_query->tax_query->queries as $tax_query )
		{
			$taxonomies[] = $tax_query['taxonomy'];
		}
		return $taxonomies;
	}//END extract_taxonomies

	/**
	 * remove special characters from $string that're not indexed by default.
	 * the special characters are taken from:
 	 * https://code.google.com/p/sphinxsearch/source/browse/branches/rel21/api/sphinxapi.php#1607
	 *
	 * @param $string string the string to be escaped
	 * @return string content of $string with the sphinx special characters
	 *  removed
	 */
	public function sanitize_sphinx_query( $string )
	{
		// characters to remove
		return preg_replace( '#[()|!@~&\/^$=?\\\\-]#', ' ', trim( $string ) );
	}//END sanitize_sphinx_query
}//END class

/**
 * Singleton
 */
function go_sphinx()
{
	global $go_sphinx;

	if ( ! $go_sphinx )
	{
		$go_sphinx = new GO_Sphinx();
	}//end if

	return $go_sphinx;
}//end go_sphinx