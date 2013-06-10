<?php

//TODO: remove once commits are all merged into the same branch
require_once __DIR__ . '/class-go-sphinx-test.php';

class GO_Sphinx_Test2 extends GO_Sphinx_Test
{
	public function __construct()
	{
		parent::__construct();
	}

	// virtual from GO_Sphinx_Test
	public function mutually_exclusive_posts_test()
	{
		echo "$this->test_count.\n";
		$query_terms = $this->setup_mutually_exclusive_posts_test( $results );
		$this->WP_mutually_exclusive_posts_test( $query_terms, FALSE );
		$this->SP_mutually_exclusive_posts_test( $query_terms, FALSE );
		echo "---\n\n";
		++$this->test_count;
	}//END mutually_exclusive_posts_test

	// find the two posts with terms that're not in any other posts
	public function setup_mutually_exclusive_posts_test()
	{
		$post_ids_to_terms = array();
		foreach ( $this->ten_most_recent_hits_wp as $post )
		{
			$post_ids_to_terms[ $post->ID ] = $this->get_most_used_terms( array($post) );
		}

		$post_ids_to_ttids = array(); // maps post ids to ttid lists
		$ttids_to_terms = array();    // maps ttids to term objects

		foreach ( $post_ids_to_terms as $post_id => $terms_list ) 
		{
			$ttids = array();
			foreach ($terms_list as $term_obj)
			{
				$ttids[] = $term_obj['term']->term_taxonomy_id;
				$ttids_to_terms[ $term_obj['term']->term_taxonomy_id ] = $term_obj['term'];
			}

			$post_ids_to_ttids[ $post_id ] = $ttids;
		}

		// now look for two posts and a term in each post that only appears
		// in that post.
		$query_terms = array();
		foreach ( $post_ids_to_ttids as $post_id => $ttid_list )
		{
			foreach ( $ttid_list as $ttid )
			{
				if ( ! $this->is_ttid_in_array( $post_ids_to_ttids, $post_id, $ttid ) )
				{
					$query_terms[ $post_id ] =  $ttids_to_terms[ $ttid ];

					if ( 2 <= count( $query_terms ) )
					{
						break; // we found enough results
					}
				}
			}
			if ( 2 <= count( $query_terms ) ) 
			{
				break;
			}
		}

		return $query_terms;
	} //END setup_mutually_exclusive_posts_test

	/**
	 * @param $query_terms array of post id mapped to term objects to search
	 * @param $is_IN_test whether to use the "IN" (OR) test or not.
	 */		
	public function WP_mutually_exclusive_posts_test( $query_terms, $is_IN_test )
	{
		$tax_query = $is_IN_test ? array( 'relation' => 'OR' ) : array( 'relation' => 'AND' );
		foreach( $query_terms as $term )
		{
			$tax_query[] = array(
				'taxonomy' => $term->taxonomy,
				'field'    => 'id',
				'terms'	   => $term->term_id,
				);
		}

		$query_results = new WP_Query(
			array(
				'fields'         => 'ids',
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => $tax_query,
				) );

		// make sure keys from $query_terms are not in $query_results...
		$test_failed = FALSE;
		foreach ( $query_terms as $post_id => $term_obj )
		{
			if ( ( in_array( $post_id, $query_results->posts ) && ! $is_IN_test ) ||
				 ( ! in_array( $post_id, $query_results->posts ) && $is_IN_test ) )
			{
				// we shouldn't find any post from $query_terms in the search
				// results when not performing the "IN" test,
				// and we should find all posts from $query_terms in the search
				// results when performing the "IN" test
				$test_failed = TRUE;
				break;
			}
		}

		echo 'WP_Query() for test ' . $this->test_count . ' ' . ( ( $test_failed ) ? "FAILED" : "PASSED" ) . ".\n\n";
	} // END setup_mutually_exclusive_posts_test

	public function is_ttid_in_array($post_ids, $post_to_ignore, $ttid)
	{
		foreach ( $post_ids as $post_id => $ttid_list ) 
		{
			if ( $post_id == $post_to_ignore ) continue;
			if ( in_array($ttid, $ttid_list) )
			{
				return TRUE;
			}
		}
		
		return FALSE;
	} // END is_ttid_in_array
	
	/**
	 * @param $query_terms array of post id mapped to term objects to search
	 * @param $is_IN_test whether to use the "IN" (OR) test or not.
	 */		
	public function SP_mutually_exclusive_posts_test( $query_terms, $is_IN_test )
	{
		$client = $this->client();

		$ttids = array();
		foreach( $query_terms as $term )
		{
			// set a filter for each ttid to filter to get the AND behavior
			$client->SetFilter( 'tt_id', array( $term->term_taxonomy_id ) );
		}

		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$results = $client->Query( '@post_status publish' );

		if ( FALSE === $results )
		{
			echo "query error: ";
			print_r( $client->GetLastError() );
			echo "\n\n";
			return;
		}

		$matched_post_ids = array();
		if ( isset( $results['matches'] ) )
		{
			foreach( $results['matches'] as $match )
			{
				$matched_post_ids[] = $match['id'];
			}
		}

		// make sure keys from $results are not in $terms...
		$test_failed = FALSE;
		foreach ( $query_terms as $post_id => $term_obj )
		{
			if ( ( in_array( $post_id, $matched_post_ids ) && ! $is_IN_test ) ||
				 ( ! in_array( $post_id, $matched_post_ids ) && $is_IN_test ) )
			{
				// we shouldn't find any post from $query_terms in the search
				// results when not performing the "IN" test,
				// and we should find all posts from $query_terms in the search
				// results when performing the "IN" test
				$test_failed = TRUE;
				break;
			}
		}

		echo 'Sphinx query for test ' . $this->test_count . ' ' . ( ( $test_failed ) ? "FAILED" : "PASSED" ) . ".\n\n";
		
	}//END SP_mutually_exclusive_posts_test


	// virtual from GO_Sphinx_Test
	public function mutually_exclusive_posts_IN_test()
	{
		echo "$this->test_count.\n";

		$query_terms = $this->setup_mutually_exclusive_posts_test( $results );

		$this->WP_mutually_exclusive_posts_test( $query_terms, TRUE );
		$this->SP_mutually_exclusive_posts_test( $query_terms );
		echo "---\n\n";
		++$this->test_count;
	}//END mutually_exclusive_posts_IN_test

}//END GO_Sphinx_Test2