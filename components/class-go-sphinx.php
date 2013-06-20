<?php

class GO_Sphinx
{
	public $admin  = FALSE;
	public $client = FALSE;
	public $test   = FALSE;
	public $index_name = FALSE;
	public $filter_args = array();
	public $use_sphinx = TRUE;
	public $admin_cap = 'manage_options';

	public function __construct()
	{
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
		$this->use_sphinx = TRUE;
		$this->filter_args = array();

		return $query;
	}

	// filters to check if another plugin has modified wp query
	public function add_tester_filters()
	{
		$filters_to_hook = array(
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

		// hook our callback before and after all other callbacks
		foreach( $filters_to_hook as $filter )
		{
			add_filter( $filter, array( $this, 'check_query' ), 1 );
			add_filter( $filter, array( $this, 'check_query' ), 9999 );
		}
	}

	// the callback to track whether another plugin has altered the
	// query being processed
	public function check_query( $request )
	{
		if ( ! $this->use_sphinx )
		{
			return; // already decided to not use
		}

		$current_filter = current_filter();

		if ( ! isset( $this->filter_args[ $current_filter ] ) )
		{
			$this->filter_args[ $current_filter ] = $request;
		}
		elseif ( $request != $this->filter_args[ $current_filter ] )
		{
			// a plugin has altered the query in some way
			$this->use_sphinx = FALSE;
		}

		return $request;
	}

	// returns TRUE if we want the query to be "split", which means
	// WP will first get the result post ids, and then look up the
	// corresponding objects. we want to split the query when we
	// use sphinx for the search results
	public function split_the_query( $split_the_query, $wp_query )
	{
		if ( ! $this->use_sphinx )
		{
			return $split_the_query;
		}

		// check if we know how to process this query
		if ( $this->wp_to_sphinx( $wp_query ) )
		{
			return TRUE;
		}

		$this->use_sphinx = FALSE;

		return $split_the_query;
	}

	// replace the request (SQL) to come up with search result post ids
	public function posts_request_ids( $request, $wp_query )
	{
		if ( $this->use_sphinx )
		{
			global $wpdb;

			// return a SQL query that encodes the sphinx search results like
			// SELECT ID from wp_posts
			// WHERE ID IN ( 5324, 1231) ORDER BY FIELD( ID, 5324, 1231)
			$results = $this->sphinx_query( $request, $wp_query );

			if ( 0 < count( $results ) )
			{
				$request = "SELECT ID FROM $wpdb->posts WHERE ID IN (" . implode( ',', $results ) . ') ORDER BY FIELD ( ID, ' . implode( ',', $results ) . ')';
			}
			else
			{
				// return a sql that returns nothing
				$request = "SELECT ID FROM $wpdb->posts WHERE 1 = 0";
			}
		}

		return $request;
	}

	// set the query to find out how many posts were found by the query.
	// we replace the incoming query with a simple static sql query
 	// since we'll set the found_posts # ourselves and don't want to
	// make WP/mysql do any unnecessary additional work.
	public function found_posts_query( $param )
	{
		if ( $this->use_sphinx )
		{
			$param = 'SELECT 0';
		}
		return $param;
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

	// check if we can convert the wp_query to a sphinx query
	public function wp_to_sphinx( $wp_query )
	{
		if ( isset( $_GET['no_sphinx'] ) )
		{
			return FALSE;
		}

		// TODO: implement the actual checks. for now this is just accepts
		// simple company searches to test how to inject sphinx results
		// into WP_Query results
		if ( ! empty( $wp_query->query ) &&
			 empty( $wp_query->query_vars['post_type']) &&
			 ! empty( $wp_query->tax_query->queries ) &&
			 isset( $wp_query->query_vars['company'] ) )
		{
			return TRUE;
		}

		return FALSE;
	}

	// perform a sphinx query that's equivalent to the $wp_query
	public function sphinx_query( $request, $wp_query )
	{
		$ids = array();

		// company search
		if ( empty( $wp_query->query_vars['post_type']) &&
			 ! empty( $wp_query->tax_query->queries ) &&
			 isset( $wp_query->query_vars['company']
			) )
		{
			$this->client = NULL;
			$client = $this->client();
			if ( isset( $wp_query->query_vars['paged'] ) && ( 0 < $wp_query->query_vars['paged'] ) )
			{
				$posts_per_page = 10;
				if ( isset( $wp_query->query_vars['posts_per_page'] ) )
				{
					$posts_per_page = $wp_query->query_vars['posts_per_page'];
				}
				$offset = ($wp_query->query_vars['paged'] - 1 ) * $posts_per_page;
				$client->SetLimits( $offset, $posts_per_page, 1000 );
			}
			else
			{
				$client->SetLimits( 0, 10, 1000 );
			}

			$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
			$client->SetMatchMode( SPH_MATCH_EXTENDED );

			if ( 'AND' == $wp_query->tax_query->relation )
			{
				// set a filter for each ANDed tax query
				foreach( $wp_query->tax_query->queries as $tax_query )
				{
					$ttids = $this->get_tax_query_ids( $tax_query );
					if ( ! empty( $ttids ) )
					{
						if( 'AND' == $tax_query['operator'] )
						{
							foreach( $ttids as $ttid )
							{
								$client->SetFilter( 'tt_id', array( $ttid ) );
							}
						}
						else
						{
							$client->SetFilter( 'tt_id', $ttids, ( 'NOT IN' == $tax_query['operator'] ) );
						}
					}
				}
			}
			else
			{
				// NOTE: i'm not sure if sphinx's SetFilter() can support
				// this case exactly?
				$ttids = array();
				foreach( $wp_query->tax_query->queries as $tax_query )
				{
					//TODO: figure out how to implement this correctly
					// (as in ORing tax queries with possible inner booleans
					$ttids[] = $this->get_tax_query_ids( $tax_query );
				}
				$client->SetFilter( 'tt_id', $ttids );
			}

			$this->results = $client->Query( '@post_status publish', $this->index_name );

			if ( isset( $this->results['matches'] ) )
			{
				foreach( $this->results['matches'] as $match )
				{
					$ids[] = $match['id'];
				}
			}
		}

		return $ids;
	}

	// convert slugs or ids in the 'terms' field of $tax_query into an
	// array of taxonomy_term_ids
	public function get_tax_query_ids( $tax_query )
	{
		$ttids = array();
		foreach( $tax_query['terms'] as $term )
		{
			$term = get_term_by( $tax_query['field'], $term, $tax_query['taxonomy'] );
			if ( $term )
			{
				$ttids[] = $term->term_taxonomy_id;
			}
		}

		return $ttids;
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