<?php

class GO_Sphinx
{
	const SPHINX_OVERRIDE_ON  = 1;
	const SPHINX_OVERRIDE_OFF = 2;

	public $admin  = FALSE;
	public $client = FALSE;
	public $test   = FALSE;
	public $version = 2;
	public $index_name = FALSE;
	public $filter_args = array();
	public $query_modified = FALSE; // did another plugin modify the current query?
	public $search_stats = array();
	public $posts_per_page = 10;
	public $max_results = 1000;
	public $secondary_index_postfix = '_delta';
	public $admin_cap = 'manage_options';
	public $qv_debug = 'go-sphinx-debug';
	public $qv_use_sphinx = 'go-sphinx-use';
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
		'tag_id',
		'tag__in',
		'tag__not_in',
		'tag__and',
		'tag_slug__in',
		'tag_slug__and',
		'tax_query',

		/* these are allowed to pass through either because we allow them
		   or because they're alternate keys used by WP */
		'ignore_sticky_posts',
		'numberposts',
		'include',
		'exclude',
		'fields',
		'suppress_filters',
	);
	// supported orderby keywords
	public $supported_order_by = array(
		'none',
		'ID',
		'title',
		'date',
		'modified',
		'parent',
		'rand',
		'comment_count',
	);

	public function __construct()
	{
		add_action( 'init' , array( $this, 'init' ) , 10 );

		global $wpdb;
		$this->index_name = $wpdb->posts . ',' . $wpdb->posts . $this->secondary_index_postfix;

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
			if ( GO_Sphinx::SPHINX_OVERRIDE_ON == $this->get_sphinx_override() )
			{
				$this->add_filters();
			}
		}
		else
		{
			$this->add_filters();
		}
	}

	public function init()
	{
		if ( $this->is_debug() )
		{
			$plugin_url = untrailingslashit( plugin_dir_url( __FILE__ ) );
			wp_register_script( 'go-sphinx-js', $plugin_url . '/js/go-sphinx.js', array( 'jquery' ), $this->version, TRUE );
			wp_enqueue_script( 'go-sphinx-js');
		}
	}

	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-go-sphinx-admin.php';
			$this->admin = new GO_Sphinx_Admin;
		}

		return $this->admin;
	}

	public function client( $config = array() )
	{
		// TODO: cache client by query params -- we can't simply
		// reuse the same client if another caller has set some
		// of its search params or it would affect the next search
		// in unexpected ways.
		if ( ! $this->client || ! empty( $config ) )
		{
			require_once __DIR__ . '/externals/sphinxapi.php';
			$this->client = new SphinxClient();

			$config = wp_parse_args( $config, apply_filters( 'go_config', array(
				'server'      => 'localhost',
				'port'        => 9312,
				'timeout'     => 1,
			), 'go-sphinx' ) );

			$this->client->SetServer( $config['server'], $config['port'] );
			$this->client->SetConnectTimeout( $config['timeout'] );
			$this->client->SetArrayResult( FALSE ); // other methods depend on the result array key being the post_id
		}

		return $this->client;
	}

	public function search_test()
	{
		$this->test()->search_test();
	}

	public function test()
	{
		if ( ! $this->test )
		{
			require_once __DIR__ . '/class-go-sphinx-test.php';
			$this->test = new GO_Sphinx_Test;
		}

		return $this->test;
	}

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
	}

	// initialize our states in this callback, which is invoked at the
	// beginning of each query.
	public function parse_query( $query )
	{
		//TODO: add all filters here if we're going to remove them all
		// after the query's over
		$this->query_modified = FALSE;
		$this->filter_args = array();
		$this->search_stats = array();

		return $query;
	}

	// filters to check if another plugin has modified wp query
	public function add_tester_filters()
	{
		// hook our callback before and after all other callbacks
		foreach( $this->filters_to_watch as $filter )
		{
			add_filter( $filter, array( $this, 'check_query' ), 1 );
			add_filter( $filter, array( $this, 'check_query' ), 101 );
		}
	}

	// check if there is a user override to turn sphinx on or off
	// using query params.
	// @retval FALSE if there is no override
	// @retval 1 if sphinx should be turned on
	// @retval 2 if sphinx should be turned off
	public function get_sphinx_override()
	{
		if ( ! isset( $_GET[ $this->qv_use_sphinx ] ) )
		{
			return FALSE;
		}

		if ( '1' == $_GET[ $this->qv_use_sphinx ] )
		{
			return GO_Sphinx::SPHINX_OVERRIDE_ON;
		}

		return GO_Sphinx::SPHINX_OVERRIDE_OFF;
	}

	// check if we're in debug mode or not
	public function is_debug()
	{
		return ( isset( $_GET[ $this->qv_debug ] ) && current_user_can( 'edit_others_posts' ) );
	}

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
	}

	// check if we can use sphinx on this query or not
	public function use_sphinx( $wp_query )
	{
		// cannot use sphinx if the query has been modified by another plugin
		if ( $this->query_modified )
		{
			return FALSE;
		}

		// parse $wp_query to determine if we can use sphinx for this
		// search or not.
		// check manual override first
		if ( ( GO_Sphinx::SPHINX_OVERRIDE_OFF == $this->get_sphinx_override() ) && current_user_can( 'edit_others_posts' ) )
		{
			return FALSE;
		}

		// do not enable sphinx if we receive an unsupported query var.
		// note we must handle query vars which're taxonomy names.
		$queried_taxonomies = $this->extract_taxonomies( $wp_query );
		foreach( $wp_query->query as $key => $val )
		{
			if (
				! in_array( $key, $this->supported_query_vars ) &&
				! empty( $wp_query->query[ $key ] ) &&
				! in_array( $key, $queried_taxonomies )
				)
			{
				return FALSE;
			}
		}

		if (
			isset( $wp_query->query['orderby'] ) &&
			! in_array( $wp_query->query['orderby'], $this->supported_order_by )
			)
		{
			return FALSE;
		}

		return TRUE;
	}

	// returns TRUE if we want the query to be "split", which means
	// WP will first get the result post ids, and then look up the
	// corresponding objects. we want to split the query when we
	// use sphinx for the search results
	public function split_the_query( $split_the_query, $wp_query )
	{
		return $this->use_sphinx( $wp_query ) ? TRUE : $split_the_query;
	}

	// replace the request (SQL) to come up with search result post ids
	public function posts_request_ids( $request, $wp_query )
	{
		if ( ! $this->use_sphinx( $wp_query ) )
		{
			return $request;
		}

		global $wpdb;

		// return a SQL query that encodes the sphinx search results like
		// SELECT ID from wp_posts
		// WHERE ID IN ( 5324, 1231) ORDER BY FIELD( ID, 5324, 1231)
		$t0 = microtime( TRUE );
		$result_ids = $this->sphinx_query( $wp_query );

		$this->search_stats['elapsed_time'] = round( microtime( TRUE ) - $t0, 6 );
		// save the original request before overriding it.
		$this->search_stats['wp_request'] = $request;

		if ( is_wp_error( $result_ids ) )
		{
			$this->search_stats['error'] = $result_ids;
			return $request;
		}

		if ( $this->is_debug() )
		{
			$this->search_stats['posts_mysql'] = $wpdb->get_col( $request );
			$this->search_stats['posts_sphinx'] = $result_ids;
			$this->search_stats['posts_equality'] = ( $this->search_stats['posts_mysql'] == $this->search_stats['posts_sphinx'] );

			wp_localize_script( 'go-sphinx-js', 'sphinx_results', (array) $this->search_stats );
		}

		if ( 0 < count( $result_ids ) )
		{
			$request = "SELECT ID FROM $wpdb->posts WHERE ID IN (" . implode( ',', $result_ids ) . ') ORDER BY FIELD ( ID, ' . implode( ',', $result_ids ) . ')';
		}
		else
		{
			// return a sql that returns nothing
			$request = "SELECT ID FROM $wpdb->posts WHERE 1 = 0";
		}

		return $request;
	}

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
	}

	// overrides the number of posts found, this affects pagination.
	public function found_posts( $found_posts, $wp_query )
	{
		if ( isset( $this->total_found ) && $this->total_found )
		{
			$found_posts = $this->total_found;
		}

		return $found_posts;
	}

	// used for scriblio facets integration. this filter callback is
	// invoked before scriblio facets tries to execute a sql query
	// to get the query results. if we have scriblio results here then
	// we can return it in $result_ids, up to $max number of them.
	// we return $result_ids if we're able to use sphinx results, else
	// we return FALSE.
	//
	// caveat: we will only return the smaller of $max or $this->max_results
	//
	public function scriblio_pre_get_matching_post_ids( $ignorable, $max )
	{
		if ( ! isset( $this->matched_posts ) || empty( $this->matched_posts ) )
		{
			return FALSE;
		}

		return $this->matched_posts;
	}

	// perform a sphinx query that's equivalent to the $wp_query
	// returns WP_Error if we cannot use sphinx for this query.
	public function sphinx_query( $wp_query )
	{
		$ids = array(); // our results
		$this->client = NULL;
		$client = $this->client();

		// these WP query vars are implemented as sphinx filters
		// author
		if ( is_wp_error( $res = $this->sphinx_query_author( $client, $wp_query ) ) )
		{
			return $res;
		}

		// order and orderby
		if ( is_wp_error( $res = $this->sphinx_query_ordering( $client, $wp_query ) ) )
		{
			return $res;
		}

		// tax_query
		if ( is_wp_error( $res = $this->sphinx_query_taxonomy( $client, $wp_query ) ) )
		{
			return $res;
		}

		// post__in and post__not_in
		if ( is_wp_error( $res = $this->sphinx_query_post_in_not_in( $client, $wp_query ) ) )
		{
			return $res;
		}

		// post_parent
		if ( is_wp_error( $res = $this->sphinx_query_post_parent( $client, $wp_query ) ) )
		{
			return $res;
		}

		// pagination
		$this->sphinx_query_pagination( $client, $wp_query );

		// these quyery vars are implemented as sphinx query string

		$query_strs = array();

		$query_strs[] = $this->sphinx_query_keyword( $wp_query );

		$query_strs[] = $this->sphinx_query_post_type( $wp_query );

		$query_strs[] = $this->sphinx_query_post_status( $wp_query );

		$client->SetRankingMode( SPH_RANK_PROXIMITY_BM25 );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$results = $client->Query( implode( ' ', $query_strs ), $this->index_name );

		if ( FALSE == $results )
		{
			return new WP_Error( 'sphinx query error', $client->GetLastError() );
		}

		if ( $this->is_debug() )
		{
			$this->search_stats['sphinx_results'] = $results;
		}

		if ( isset( $results['matches'] ) )
		{
			$this->matched_posts = array_keys( $results['matches'] );
			$this->total_found = $results['total_found'];
			return array_slice( $this->matched_posts , 0, $this->posts_per_page );
		}
		else
		{
			return array();
		}
	}

	/**
	 * parse order and orderby params in $wp_query and set the appropriate
	 * flags in the sphinx client $client.
	 *
	 * @retval TRUE if we're able to parse the wp_query.
	 * @retval WP_Error if we encounter an error or if the wp_query is not
	 *  supported.
	 */
	public function sphinx_query_ordering( &$client, $wp_query )
	{
		$order = isset( $wp_query->query['order'] ) ? $wp_query->query['order'] : 'DESC';

		if ( isset( $wp_query->query['orderby'] ) )
		{
			switch ( $wp_query->query['orderby'] )
			{
				case 'none':
					break;

				case 'ID':
					$client->SetSortMode( SPH_SORT_EXTENDED, '@id ' . $order );
					break;

				case 'date':
				case 'post_date':
					$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt ' . $order );
					break;

				case 'modified':
					$client->SetSortMode( SPH_SORT_EXTENDED, 'post_modified_gmt ' . $order );
					break;

				case 'parent':
					$client->SetSortMode( SPH_SORT_EXTENDED, 'post_parent ' . $order );
					break;

				case 'rand':
					$client->SetSortMode( SPH_SORT_EXTENDED, '@random ' . $order );
					break;

				case 'comment_count':
					$client->SetSortMode( SPH_SORT_EXTENDED, 'comment_count ' . $order );
					break;

				default:
					return new WP_Error( 'unsupported sphinx query orderby ' . $wp_query->query['orderby'], 'unsupported sphinx query orderby value' );
			}//END switch
		}//END if
		else
		{
			$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt ' . $order );
		}

		return TRUE;
	}//END sphinx_query_ordering

	/**
	 * parse the tax_query param in $wp_query and set the appropriate
	 * flags in the sphinx client $client.
	 *
	 * @retval TRUE if we're able to parse the wp_query.
	 * @retval WP_Error if we encounter an error or if the wp_query is not
	 *  supported.
	 */
	public function sphinx_query_taxonomy( &$client, $wp_query )
	{
		if ( ! is_array( $wp_query->tax_query->queries ) || empty( $wp_query->tax_query->queries ) )
		{
			return FALSE;
		}

		if ( 'AND' == $wp_query->tax_query->relation )
		{
			foreach( $wp_query->tax_query->queries as $query )
			{
				// use WP_Tax_Query::transform_query() to look up the term tax ids
				$wp_query->tax_query->transform_query( $query, 'term_taxonomy_id' );
				if ( empty( $query['terms'] ) )
				{
					// if a tax query has no term then it means some or all
					// of the terms could not be resolved. in this case we
					// set a filter that'll block all results to ensure
					// the final query result will be empty
					// see https://github.com/Gigaom/legacy-pro/issues/673
					$client->SetFilter( 'tt_id', array( -1 ) );
					break;
				}
				if ( 'AND' == $query['operator'] )
				{
					// one filter per ttid
					foreach( $query['terms'] as $ttid )
					{
						$client->SetFilter( 'tt_id', array( $ttid ) );
					}
				}
				else
				{
					// operator = "IN" or "NOT IN"
					$client->SetFilter( 'tt_id', $query['terms'], ( 'NOT IN' == $query['operator'] ) );
				}
			}//END foreach
		}
		else
		{
			// the OR relation:
			// we do not support the outer OR + inner AND case nor the
			// outer OR + inner "NOT IN" case
			$ttids = array();
			foreach( $wp_query->tax_query->queries as $query )
			{
				if ( empty( $query['terms'] ) )
				{
					// see notes above about github issue 673
					$client->SetFilter( 'tt_id', array( -1 ) );
					break;
				}
				if ( 'IN' != $query['operator'] )
				{
					return new WP_Error( 'unsupported sphinx query', 'unsupported sphinx query (OR relation with AND or "NOT IN" operator(s))' );
				}
				$wp_query->tax_query->transform_query( $query, 'term_taxonomy_id' );
				$ttids = array_merge( $ttids, $query['terms'] );
			}

			$client->SetFilter( 'tt_id', $ttids );
		}//END else

		return TRUE;
	}//END sphinx_query_taxonomy

	/**
	 * parse the post__in and post__not_in params in $wp_query and set the
	 * appropriate flags in the sphinx client $client.
	 *
	 * @retval TRUE if we're able to parse the wp_query.
	 */
	public function sphinx_query_post_in_not_in( &$client, $wp_query )
	{
		if ( ! isset( $wp_query->query['post__in'] ) && ! isset( $wp_query->query['post__not_in'] ) )
		{
			return TRUE;
		}

		if ( ! empty( $wp_query->query['post__in'] ) )
		{
			$client->SetFilter( '@id', $wp_query->query['post__in'] );
		}

		if ( ! empty( $wp_query->query['post__not_in'] ) )
		{
			$client->SetFilter( '@id', $wp_query->query['post__not_in'], TRUE );
		}

		return TRUE;
	}

	/**
	 * parse the post_parent param in $wp_query and set the appropriate
	 * flags in the sphinx client $client.
	 *
	 * @retval TRUE if we're able to parse the wp_query.
	 * @retval WP_Error if we encounter an error or if the wp_query is not
	 *  supported.
	 */
	public function sphinx_query_post_parent( &$client, $wp_query )
	{
		if ( ! isset( $wp_query->query['post_parent'] ) )
		{
			return TRUE;
		}

		// WP_Query only allows a single post_parent id
		if ( ! is_numeric( $wp_query->query['post_parent'] ) )
		{
			return new WP_Error( 'invalid post_parent id', 'invalid post_parent id (' . $wp_query->query['post_parent'] . ')' );
		}

		$client->SetFilter( 'post_parent', $wp_query->query['post_parent'] );

		return TRUE;
	}

	/**
	 * parse the pagination params in $wp_query and set the appropriate
	 * flags in the sphinx client $client.
	 */
	public function sphinx_query_pagination( &$client, $wp_query )
	{
		// defaults
		$offset = 0;
		$this->posts_per_page = 10;

		if ( isset( $wp_query->query['posts_per_page'] ) && ( 0 < $wp_query->query['posts_per_page'] ) )
		{
			$this->posts_per_page = $wp_query->query['posts_per_page'];
		}

		if ( isset( $wp_query->query['offset'] ) && ( 0 < $wp_query->query['offset'] ) )
		{
			$offset = $wp_query->query['offset'];
		}
		elseif ( isset( $wp_query->query['paged'] ) && ( 0 < $wp_query->query['paged'] ) )
		{
			$offset = ($wp_query->query['paged'] - 1 ) * $this->posts_per_page;
		}

		$client->SetLimits( $offset, $this->max_results, $this->max_results );
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

		return '@(post_content,content) ' . $wp_query->query['s'];
	}

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
			}
		}
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
				$query_str = '@post_status ' . implode( ' | ', $wp_query->query['post_status'] );
			}
			else
			{
				$query_str = '@post_status ' . $wp_query->query['post_status'];
			}
		}
		else
		{
			$query_str = '@post_status publish'; // the WP default
		}
		return $query_str;
	}//END sphinx_query_post_type


	/**
	 * parse author param in $wp_query and set the appropriate
	 * flags in the sphinx client $client.
	 *
	 * author param could be one of:
	 *  1) not set
	 *  2) single neg
	 *  3) list of 1 or more pos ints
	 *
	 * @retval TRUE in all cases...
	 * No WP_Error need be returned, but if not set or NOT operator, set the sphinx filter appropriately.
	 */
	public function sphinx_query_author( &$client, $wp_query )
	{
		if ( isset( $wp_query->query['author'] ) || isset( $wp_query->query['author_name'] ) )
		{
			$author_id = NULL;
			if ( isset( $wp_query->query['author'] ) )
			{
				$author_id = $wp_query->query['author'];
				// check for existence of NOT operator ("-"):
				$exclude_position = strpos( $author_id, '-' );
				if ( FALSE !== $exclude_position )
				{
					$author_id = substr( $author_id, $exclude_position + 1 );
				}
			}
			else
			{
				// get an author id from author_nicename if necessary
				$author_name = $wp_query->query['author_name'];
				$exclude_position = strpos( $author_name , '-' );
				if ( FALSE !== $exclude_position )
				{
					$author_name = substr( $author_name, $exclude_position + 1 );
				}
				$user = get_user_by( 'slug', $author_name );
				if ( FALSE !== $user )
				{
					$author_id = $user->ID;
				}
			}

			if ( $author_id )
			{
				$client->SetFilter( 'post_author', array( (int) $author_id ), FALSE !== $exclude_position );
			}
		} // END if

		return TRUE;
	}//END sphinx_query_author

	// find all taxonomies in wp_query's tax_query array and return them
	// in an array
	public function extract_taxonomies( $wp_query )
	{
		$taxonomies = array();

		foreach( $wp_query->tax_query->queries as $tax_query )
		{
			$taxonomies[] = $tax_query['taxonomy'];
		}
		return $taxonomies;
	}

}//END GO_Sphinx

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