<?php

class GO_Sphinx
{
	public $admin  = FALSE;
	public $client = FALSE;
	public $test   = FALSE;
	public $version = 1;
	public $index_name = FALSE;
	public $filter_args = array();
	public $query_modified = FALSE; // did another plugin modify the current query?
	public $use_sphinx = TRUE; // can we use sphinx for the current query?
	public $search_stats = array();
	public $admin_cap = 'manage_options';
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
		'category__in',
		'category__not_in',
		'category__and',
		/* 'fields', */
		/* ignore_sticky_posts, */
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
		's',
		'tag_id',
		'tag__in',
		'tag__not_in',
		'tag__and',
		'tag_slug__in',
		'tag_slug__and',
		'tax_query',
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
		$this->index_name = $wpdb->posts;

		// the admin settings page
		if ( is_admin() )
		{
			$this->admin();
		}
		else
		{
			$this->add_filters();
		}
	}

	public function init()
	{
		if ( isset( $_GET['show_source'] ) )
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
				'arrayresult' => TRUE,
			), 'go-sphinx' ) );

			$this->client->SetServer( $config['server'], $config['port'] );
			$this->client->SetConnectTimeout( $config['timeout'] );
			$this->client->SetArrayResult( $config['arrayresult'] );
		}
		// TODO: else set up the client with info from $config

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
		add_filter( 'split_the_query', array( $this, 'split_the_query' ), 9999, 2 );
		add_filter( 'posts_request_ids', array( $this, 'posts_request_ids' ), 9999, 2 );
		add_filter( 'found_posts_query', array( $this, 'found_posts_query' ), 9999 );
		add_filter( 'found_posts', array( $this, 'found_posts' ), 9999, 2 );

	}

	// initialize our states in this callback, which is invoked at the
	// beginning of each query.
	public function parse_query( $query )
	{
		//TODO: add all filters here if we're going to remove them all
		// after the query's over
		$this->query_modified = FALSE;
		$this->use_sphinx = TRUE;
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
			add_filter( $filter, array( $this, 'check_query' ), 9999 );
		}
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

	// returns TRUE if we want the query to be "split", which means
	// WP will first get the result post ids, and then look up the
	// corresponding objects. we want to split the query when we
	// use sphinx for the search results
	public function split_the_query( $split_the_query, $wp_query )
	{
		// cannot use sphinx if the query has been modified by another
		// plugin

		if ( $this->query_modified )
		{
			$this->use_sphinx = FALSE;
			return $split_the_query;
		}

		// check if we can use sphinx for this query or not
		$this->use_sphinx = $this->is_supported_query( $wp_query );

		if ( $this->use_sphinx )
		{
			return TRUE;
		}

		return $split_the_query;
	}

	// replace the request (SQL) to come up with search result post ids
	public function posts_request_ids( $request, $wp_query )
	{
		if ( ! $this->use_sphinx )
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
			$this->use_sphinx = FALSE;
			return $request;
		}

		if ( isset( $_GET['show_source'] ) )
		{
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
		if ( ! $this->use_sphinx )
		{
			return $param;
		}

		return 'SELECT 0';
	}

	// overrides the number of posts found. in search this affects
	// pagination.
	public function found_posts( $found_posts, $wp_query )
	{
		if ( $this->use_sphinx && $this->results )
		{
			$found_posts = $this->results['total_found'];
		}

		return $found_posts;
	}

	// parse $wp_query to determine if we can use sphinx for this
	// search or not.
	public function is_supported_query( $wp_query )
	{
		// check manual override first
		if ( isset( $_GET['no_sphinx'] ) )
		{
			return FALSE;
		}

		if (
			isset( $wp_query->query['orderby'] ) &&
			! in_array( $wp_query->query['orderby'], $this->supported_order_by )
			)
		{
			return FALSE;
		}

		//TODO: detect and block queries we cannot support here
		return TRUE;
	}

	// perform a sphinx query that's equivalent to the $wp_query
	// returns WP_Error if we cannot use sphinx for this query.
	public function sphinx_query( $wp_query )
	{
		$ids = array(); // our results
		$this->client = NULL;
		$client = $this->client();

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

		// pagination
		$this->sphinx_query_pagination( $client, $wp_query );

		// post_type
		$query_strs = array();
		$query_strs[] = $this->sphinx_query_post_type( $wp_query );

		// post_status
		$query_strs[] = $this->sphinx_query_post_status( $wp_query );

		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$this->results = $client->Query( implode( ' ', $query_strs ), $this->index_name );

		if ( FALSE == $this->results )
		{
			return new WP_Error( 'sphinx query error', $client->GetLastError() );
		}

		if ( isset( $this->results['matches'] ) )
		{
			foreach( $this->results['matches'] as $match )
			{
				$ids[] = $match['id'];
			}
		}

		$this->search_stats['sphinx_results'] = $this->results;

		return $ids;
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
		if ( 'AND' == $wp_query->tax_query->relation )
		{
			foreach( $wp_query->tax_query->queries as $query )
			{
				// use WP_Tax_Query::transform_query() to look up the term tax ids
				$wp_query->tax_query->transform_query( $query, 'term_taxonomy_id' );
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
				if ( 'IN' != $query['operator'] )
				{
					return new WP_Error( 'unsupported sphinx query', 'unsupported sphinx query (OR relation with AND or "NOT IN" operator(s))' );
				}
				$wp_query->tax_query->transform_query( $query, 'term_taxonomy_id' );
				$ttids[] = array_merge( $ttids, $query['terms'] );
			}
			$client->SetFilter( 'tt_id', $ttids );
		}//END else

		return TRUE;
	}//END sphinx_query_taxonomy

	/**
	 * parse the pagination params in $wp_query and set the appropriate
	 * flags in the sphinx client $client.
	 */
	public function sphinx_query_pagination( &$client, $wp_query )
	{
		// defaults
		$offset = 0;
		$posts_per_page = 10;

		if ( isset( $wp_query->query['paged'] ) && ( 0 < $wp_query->query['paged'] ) )
		{
			if ( isset( $wp_query->query_vars['posts_per_page'] ) )
			{
				$posts_per_page = $wp_query->query_vars['posts_per_page'];
			}

			$offset = ($wp_query->query['paged'] - 1 ) * $posts_per_page;
		}//END if
		$client->SetLimits( $offset, $posts_per_page, 1000 );
	}//END sphinx_query_pagination

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
			if ( is_array( $wp_query->query['post_type'] ) )
			{
				$query_str = '@post_type ' . implode( ' | ', $wp_query->query['post_type'] );
			}
			else
			{
				$query_str = '@post_type ' . $wp_query->query['post_type'];
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