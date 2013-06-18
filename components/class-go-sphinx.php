<?php

//require_once('wlog.php');

class GO_Sphinx
{

	public $admin = FALSE;
	public $client = FALSE;
	public $test = FALSE;

	public $admin_cap = 'manage_options';

	public function __construct()
	{
		$this->add_filters();

		// the admin settings page
		if ( is_admin() )
		{
			$this->admin();
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

		return $this->client;
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
		// first add filters to monitor any changes to the query
		//add_filter( 'posts_search', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_search', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_where', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_where', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_join', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_join', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_where_paged', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_where_paged', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_groupby', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_groupby', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_join_paged', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_join_paged', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_orderby', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_orderby', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_distinct', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_distinct', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_limits', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_limits', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_fields', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_fields', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_clauses', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_clauses', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_where_request', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_where_request', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_groupby_request', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_groupby_request', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_join_request', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_join_request', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_orderby_request', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_orderby_request', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_distinct_request', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_distinct_request', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_fields_request', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_fields_request', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_limits_request', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_limits_request', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_clauses_request', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_clauses_request', array( $this, 'check_query' ), 9999, 2 );
		//add_filter( 'posts_request', array( $this, 'check_query' ), 1, 2 );
		add_filter( 'posts_request', array( $this, 'check_query' ), 9999, 2 );


		// then add filters to inject sphinx search results if applicable
		add_filter( 'split_the_query', array( $this, 'split_the_query' ), 9999, 2 );
		add_filter( 'posts_request_ids', array( $this, 'posts_request_ids' ), 9999, 2 );
		add_filter( 'found_posts_query', array( $this, 'found_posts_query' ), 9999 );
		add_filter( 'found_posts', array( $this, 'found_posts' ), 9999, 2 );
	}

	// the callback to track whether another plugin has altered the
	// query being processed
	public function check_query( $request, $wp_query )
	{
		//wlog( 'request: ' . print_r($request, true));
		//wlog( 'wp_query: ' . print_r($wp_query, true));
		return $request;
	}


	// returns TRUE if we want the query to be "split", which means
	// WP will first get the result post ids, and then look up the
	// corresponding objects. we want to split the query when we
	// use sphinx for the search results
	public function split_the_query( $split_the_query, $wp_query )
	{
		if ( ( 'nav_menu_bar' != $wp_query->query_vars['post_type'] ) &&
			 ( 'nav_menu_item' != $wp_query->query_vars['post_type'] ) &&
			 ( ! empty( $wp_query->query ) ) )
		{
			//wlog('split_the_query: ' . ( $split_the_query ? 'TRUE' : 'FALSE'));
		}

		// if we can process this query then return TRUE for further
		// processing
		if ( empty( $wp_query->query_vars['post_type']) )
		{
			return TRUE;
		}
		return $split_the_query;
	}

	// replace the request (SQL) to come up with search result post ids
	public function posts_request_ids( $request, $wp_query )
	{
		if ( ! empty( $wp_query->query ) &&
			 empty( $wp_query->query_vars['post_type'] ) )
		{
			return 'SELECT 176060 AS ID UNION ALL SELECT 175439';
		}
		return $request;
	}

	// set the query to find out how many posts were found by the query.
	// we replace the incoming query with a simple static sql query
 	// since we'll set the found_posts # ourselves and don't want to
	// make WP/mysql do any unnecessary additional work.
	public function found_posts_query( $param )
	{
		if ( ( 'nav_menu_bar' != $wp_query->query_vars['post_type'] ) &&
			 ( 'nav_menu_item' != $wp_query->query_vars['post_type'] ) &&
			 ( ! empty( $wp_query->query ) ) )
		{
			$param = 'SELECT 0';
		}
		return $param;
	}

	// overrides the number of posts found. in search this affects
	// pagination.
	public function found_posts( $found_posts, $wp_query )
	{
		if ( 'nav_menu_item' != $wp_query->query_vars['post_type'] )
		{
			//wlog('found_posts: ' . print_r( $found_posts, TRUE ) );
		}
		return $found_posts;
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