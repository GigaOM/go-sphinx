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
	/**
	 * 6. Using the query from #1, pick two posts. Pick the most frequently
	 * used term on each post that doesn’t appear on the other post. Do a
	 * new AND query with those terms. Neither of the two exemplar posts
	 * should be returned in the result.
	 */
	public function mutually_exclusive_posts_test()
	{
		$query_terms = $this->setup_mutually_exclusive_posts_test();
		$this->WP_mutually_exclusive_posts_test( $query_terms, FALSE );
		$this->SP_mutually_exclusive_posts_test( $query_terms, FALSE );
		echo "---\n\n";
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
	} // END WP_mutually_exclusive_posts_test

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
		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();

		$ttids = array();
		if ( $is_IN_test )
		{
			$ttids = array();
			foreach( $query_terms as $term )
			{
				$ttids[] = $term->term_taxonomy_id;
			}
			$client->SetFilter( 'tt_id', $ttids );
		}
		else
		{
			foreach( $query_terms as $term )
			{
				// set a filter for each ttid to filter to get the AND behavior
				$client->SetFilter( 'tt_id', array( $term->term_taxonomy_id ) );
			}
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
	/**
	 * 7. Using the terms from #6, do a new IN query with those terms.
	 * Both the exemplar posts from #4 should appear in the results
	 */
	public function mutually_exclusive_posts_IN_test()
	{
		$query_terms = $this->setup_mutually_exclusive_posts_test();

		$this->WP_mutually_exclusive_posts_test( $query_terms, TRUE );
		$this->SP_mutually_exclusive_posts_test( $query_terms, TRUE );
		echo "---\n\n";
	}//END mutually_exclusive_posts_IN_test
	
	/**
	 * 9. Using the author ID from #1, do a new query for all results by that author
	 * The MySQL and Sphinx results should be indistinguishable
	 */
	public function author_id_test()
	{
		//	do the wp version . . .
		echo "\n";
		$wp_author_results = $this->wp_query_all_author_posts( $this->ten_most_recent_hits_wp[0]->post_author );
		echo "\n";
		//	do the sphinx version . . .
		$sp_author_results = $this->sphinx_query_all_author_posts( $this->ten_most_recent_hits_spx[0]['attrs']['post_author'] );
		$this->compare_results( $wpq_results, $spx_results );		
		echo "---\n\n";
	}//END author_id_test	
	
	public function wp_query_all_author_posts($author_id)
	{
		echo "WP_Query of all results for a given author" . '(' . $author_id . ')' . ":\n\n";

		$results = new WP_Query(
			array(
				'author'         => $author_id,
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date', // or 'modified'?
				'order'          => 'DESC',
		) );

		if ( $results->posts )
		{
			$ids = array();
			foreach ( $results->posts as $hit )
			{
				$ids[] = $hit->ID;
			}
			echo implode( ', ', $ids ) . "\n\n";
			return $ids;
		}
		else
		{
			echo 'no author posts found';
			return FALSE;
		}
	}
	
	public function sphinx_query_all_author_posts($author_id)
	{
		echo "Sphinx query of all results for a given author" . '(' . $author_id . ')' . ":\n\n";

		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();
		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$client->SetFilter( 'post_author', array( $author_id ) );
		$results = $client->Query( '@post_status publish'); // @post_author ' . $author_id 
		
		if ( FALSE !== $results )
		{
			$ids = array();
			foreach( $results['matches'] as $match )
			{
				$ids[] = $match['id'];
			}
			echo implode( ', ', $ids ) . "\n\n";
			return $ids;
		}
		else
		{
			echo "query error: ";
			print_r( $client->GetLastError() );
			echo "\n\n";
			return FALSE;
		}
	}

}//END GO_Sphinx_Test2