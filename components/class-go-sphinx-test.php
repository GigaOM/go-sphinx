<?php

/**
 * tests to compare WP_Query and Sphinx search results.
 */
class GO_Sphinx_Test extends GO_Sphinx
{
	public $ten_most_recent_hits_wp  = FALSE;
	public $ten_most_recent_hits_spx = FALSE;
	public $taxonomies = FALSE; // all taxonomies in the system
	public $test_count = 1;

	public function __construct()
	{
		add_action( 'wp_ajax_go_sphinx_search_test', array( $this, 'search_test' ));
	}

	public function search_test()
	{
		// permissions check
		if( ! current_user_can( $this->admin_cap ))
		{
			wp_die();
		}

		$num_failed = 0;

		echo "<pre>\n";

		echo "$this->test_count.\n";
		if ( ! $this->ten_most_recent_posts_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->most_recent_by_terms_test( 10 ) )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->most_recent_by_terms_test( 53 ) )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->most_recent_by_two_terms_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->most_recent_by_two_terms_paged_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->mutually_exclusive_posts_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->mutually_exclusive_posts_IN_test() )
		{
			++$num_failed;
		}
		++$this->test_count;
		
		echo "$this->test_count.\n";
		if ( ! $this->most_recent_by_term_name_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->author_id_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->post_not_in_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->post_in_single_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->post_in_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->author_ids_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->not_author_id_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->category_in_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->category_not_in_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->category_and_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->tags_in_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->tags_not_in_test() )
		{
			++$num_failed;
		}
		++$this->test_count;

		echo "$this->test_count.\n";
		if ( ! $this->tag_and_test() )
		{
			++$num_failed;
		}

		echo "</pre>\n";

		if ( 0 < $num_failed )
		{
			echo '<h2><font color="red">' . $num_failed . ' out of ' . $this->test_count . " tests failed</font></h2>\n\n";
		}
		else
		{
			echo '<h2><font color="green">all ' . $this->test_count . " tests passed.</font></h2>\n\n";
		}
		die;
	}

	/**
	 * "1. Query for the 10 most recent posts (not necessarily post_type=post)
	 *  in the posts table. The MySQL and Sphinx results should be
	 * indistinguishable."
	 *
	 * query_var: posts_per_page, order, orderby, post_type, post_status
	 *
	 * @retval TRUE if the test passed. FALSE if the test failed or if
	 *  we encountered an error.
	 */
	public function ten_most_recent_posts_test()
	{
		// these tests also populate $this->ten_most_recent_hits_wp and
		// $this->ten_most_recent_hits_spx which we'll need for other tests
		$wpq_results = $this->wp_query_ten_most_recent_posts();
		$spx_results = $this->sphinx_ten_most_recent_posts();
		$res = $this->compare_results( $wpq_results, $spx_results );
		echo "---\n\n";
		return $res;
	}//END ten_most_recent_posts_test

	/**
	 * "2. Pick the first of those rows [from 1.] that has taxonomy terms on
	 * it, then pick the most frequently used of those taxonomy terms, then
	 * do a query for posts that have that term; The exemplar post from #1
	 * should be returned in this query."
	 *
	 * query_var: tax_query, posts_per_page, order, orderby, post_type,
	 *            post_status
	 *
	 * "3. Repeat the query from #2, but change the posts_per_page value to
	 * 53. The query should return up to 53 posts; the MySQL and Sphinx
	 * results should be indistinguishable."
	 *
	 * query_var: tax_query, posts_per_page
	 *
	 * @retval TRUE if the test passed. FALSE if the test failed or if
	 *  we encountered an error.
	 */
	public function most_recent_by_terms_test( $num_posts )
	{
		$terms = $this->get_most_used_terms( $this->ten_most_recent_hits_wp );
		if ( empty( $terms ) )
		{
			echo "no term found for most recent posts by term tests\n\n";
			return FALSE;
		}
		$wpq_results = $this->wp_query_most_recent_by_terms( $terms[0]['term'], $num_posts );
		if ( $wpq_results && in_array( $terms[0]['post_id'], $wpq_results ) )
		{
			echo 'source post (' . $terms[0]['post_id'] . ") found\n\n";
		}
		else
		{
			echo 'source post (' . $terms[0]['post_id'] . ") NOT found\n\n";
		}

		$spx_results = $this->sphinx_most_recent_by_terms( $terms[0]['term'], $num_posts );
		if ( $spx_results && in_array( $terms[0]['post_id'], $spx_results ) )
		{
			echo 'source post (' . $terms[0]['post_id'] . ") found\n\n";
		}
		else
		{
			echo 'source post (' . $terms[0]['post_id'] . ") NOT found\n\n";
		}

		$res = $this->compare_results( $wpq_results, $spx_results );

		echo "---\n\n";

		return $res;
	}//END most_recent_by_terms_test

	/**
	 * "4. Using the same post from #1, pick the most frequently used two
	 * terms on that post and do a new query for posts with those terms.
	 * The exemplar post from #1 should be returned in this query."
	 *
	 * query_var: tax_query
	 *
	 * @retval TRUE if the test passed. FALSE if the test failed or if
	 *  we encountered an error.
	 */
	public function most_recent_by_two_terms_test()
	{
		$terms = $this->get_most_used_terms( $this->ten_most_recent_hits_wp, 2 );
		if ( empty( $terms ) )
		{
			echo "no term found for most recent posts by term tests\n\n";
			return FALSE;
		}

		$term_objs = array( $terms[0]['term'], $terms[1]['term'] );
		$wpq_results = $this->wp_query_most_recent_by_terms( $term_objs, 10 );
		if ( $wpq_results && in_array( $terms[0]['post_id'], $wpq_results ) )
		{
			echo 'source post (' . $terms[0]['post_id'] . ") found\n\n";
		}
		else
		{
			echo 'source post (' . $terms[0]['post_id'] . ") NOT found\n\n";
		}

		$spx_results = $this->sphinx_most_recent_by_terms( $term_objs, 10 );
		if ( $spx_results && in_array( $terms[0]['post_id'], $spx_results ) )
		{
			echo 'source post (' . $terms[0]['post_id'] . ") found\n\n";
		}
		else
		{
			echo 'source post (' . $terms[0]['post_id'] . ") NOT found\n\n";
		}

		$res = $this->compare_results( $wpq_results, $spx_results );

		echo "---\n\n";

		return $res;
	}//END most_recent_by_two_terms_test

	/**
	 * "5. Repeat the query from #4, but change the posts_per_page value to
	 *  3 and paged to 3. The query should return up to 3 posts starting
	 *  with the last post returned in #4; the MySQL and Sphinx results 
	 *  should be indistinguishable."
	 *
	 * query_var: tax_query, paged
	 *
	 * @retval TRUE if the test passed. FALSE if the test failed or if
	 *  we encountered an error.
	 */
	public function most_recent_by_two_terms_paged_test()
	{
		$terms = $this->get_most_used_terms( $this->ten_most_recent_hits_wp, 2 );
		if ( empty( $terms ) )
		{
			echo "no term found for most recent posts by term tests\n\n";
			return FALSE;
		}

		$term_objs = array( $terms[0]['term'], $terms[1]['term'] );

		$wpq_results = $this->wp_query_most_recent_by_terms( $term_objs, 3, 4 );

		$spx_results = $this->sphinx_most_recent_by_terms( $term_objs, 3, 4 );

		$res = $this->compare_results( $wpq_results, $spx_results );

		echo "---\n\n";

		return $res;
	}//END most_recent_by_two_terms_paged_test

	public function wp_query_ten_most_recent_posts()
	{
		echo "WP_Query of ten most recent posts:\n\n";

		$results = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date', // or 'modified'?
				'order'          => 'DESC',

		) );

		if ( $results->posts )
		{
			$this->ten_most_recent_hits_wp = $results->posts;
		}

		if ( $this->ten_most_recent_hits_wp )
		{
			$ids = array();
			foreach ( $this->ten_most_recent_hits_wp as $hit )
			{
				$ids[] = $hit->ID;
			}
			echo implode( ', ', $ids ) . "\n\n";
			return $ids;
		}
		else
		{
			echo 'no post found';
			return FALSE;
		}
	}//END wp_query_ten_most_recent_posts

	public function sphinx_ten_most_recent_posts()
	{
		echo "\nSphinx query of ten most recent posts:\n\n";

		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();
		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$results = $client->Query( '@post_status publish' );

		if ( FALSE !== $results )
		{
			$this->ten_most_recent_hits_spx = $results['matches'];
			$ids = $this->extract_sphinx_matches_ids( $results );
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
	}//END sphinx_ten_most_recent_posts

	public function extract_sphinx_matches_ids( $sp_results )
	{
		$ids = array();
		if ( ! isset( $sp_results['matches'] ) )
		{
			return $ids;
		}

		foreach( $sp_results['matches'] as $match )
		{
			$ids[] = $match['id'];
		}

		return $ids;
	}//END extract_sphinx_matches_ids

	/**
	 * find the most frequently used terms in the first post in $posts
	 * that contains any taxonomy. return an array of term objects or
	 * FALSE if not found.
	 * @param $min_terms minimum number of terms a post must have before
	 *  we return the terms.
	 */
	public function get_most_used_terms( $posts, $min_terms = 1 )
	{
		$post_id = FALSE;    // set to first post with any taxonomy
		$taxonomies = FALSE; // taxonomies associated with $post_id

		foreach( $posts as $post )
		{
			$taxonomies = get_object_taxonomies( $post->post_type );
			if ( ! empty( $taxonomies ) )
			{
				$post_id = $post->ID;

				$terms = wp_get_object_terms( $post_id, $taxonomies, array(
												  'orderby' => 'count',
												  'order'   => 'DESC',
											) );
				if ( is_wp_error( $terms ) )
				{
					print_r( $terms ) . "\n\n";
					return FALSE;
				}

				if ( count( $terms ) >= $min_terms )
				{
					break;
				}
			}
		}

		// order = DESC seems to list count of 0 first so we have to
		// make sure to skip posts with count of 0
		$results = array();
		foreach( $terms as $term )
		{
			if ( $term->count != 0 )
			{
				$results[] = array( 'post_id' => $post_id, 'term' => $term );
			}
		}
		return $results;
	}// END get_most_used_terms

	public function wp_query_most_recent_by_terms( $terms, $num_results, $page_num = FALSE, $use_in_query = FALSE )
	{
		if ( is_array( $terms ) )
		{
			$tax_query = array( 'relation' => 'AND' );
			$ttids = array();
			foreach( $terms as $term )
			{
				$query_arg = array(
					'taxonomy' => $term->taxonomy,
					'field' => 'id',
					'terms' => $term->term_id,
					);
				if ( $use_in_query )
				{
					$query_arg['operator'] = 'IN';
				}
				$tax_query[] = $query_arg;
				$ttids[] = $term->term_taxonomy_id;
			}
		}
		else
		{
			// in this case $terms is just a single term object
			$tax_query = array( array(
								'taxonomy' => $terms->taxonomy,
								'field' => 'id',
								'terms' => $terms->term_id,
								) );
			$ttids = array( $terms->term_taxonomy_id );
		}

		// pages start at 1
		if ( $page_num === 0 )
		{
			$page_num = 1;
		}
		if ( $page_num !== FALSE )
		{
			echo "WP_Query of $num_results posts on page $page_num of most recent posts with ttid(s) " . implode( ', ', $ttids ) . ":\n\n";
		}
		else
		{
			echo "WP_Query of $num_results most recent posts with ttid(s) " . implode( ', ', $ttids ) . ":\n\n";
		}

		$query_arg = array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => $num_results,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => $tax_query,
			);
		if ( $page_num !== FALSE )
		{
			$query_arg['paged'] = $page_num;
		}
		$results = new WP_Query( $query_arg );

		if ( empty( $results->posts ) )
		{
			echo "NULL\n";
			return FALSE;
		}

		$ids = array();
		foreach ( $results->posts as $hit )
		{
			$ids[] = $hit->ID;
		}
		echo implode( ', ', $ids ) . "\n\n";

		return $ids;
	}//END wp_query_most_recent_by_terms

	public function sphinx_most_recent_by_terms( $terms, $num_results, $page_num = FALSE )
	{
		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();
		$ttids = array();

		if ( is_array( $terms ) )
		{
			foreach( $terms as $term )
			{
				$ttids[] = $term->term_taxonomy_id;

				// calling SetFilter() on an array of tt_ids differs
				// semantically than calling SetFilter() once on each
				// tt_id to be filtered. SetFilter() on all the tt_ids means
				// a document only has to match any of the tt_ids in the array
				// (OR), whereas calling SetFilter() once for each tt_id means
				// the docuemnt must match all tt_ids (AND). here we want
				// documents that match all terms passed in so we need to
				// call SetFilter() once on each term/tt_id.
				$client->SetFilter( 'tt_id', array( $term->term_taxonomy_id ) );
			}
		}
		else
		{
			$ttids[] = $terms->term_taxonomy_id;
			$client->SetFilter( 'tt_id', array( $terms->term_taxonomy_id ) );
		}

		// pages start at 1
		if ( $page_num === 0 )
		{
			$page_num = 1;
		}
		if ( $page_num !== FALSE )
		{
			echo "\nSphinx query of $num_results posts on page $page_num of most recent posts with ttid(s) " . implode( ', ', $ttids ) . ":\n\n";
			$offset = ( $page_num - 1 ) * $num_results;
		}
		else
		{
			echo "\nSphinx query of $num_results most recent posts with ttid(s) " . implode( ', ', $ttids ) . ":\n\n";
			$offset = 0;
		}

		$client->SetLimits( $offset, $num_results, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$results = $client->Query( '@post_status publish' );

		if ( FALSE === $results )
		{
			echo "query error: ";
			print_r( $client->GetLastError() );
			echo "\n\n";
			return FALSE;
		}

		$ids = $this->extract_sphinx_matches_ids( $results );

		echo implode( ', ', $ids ) . "\n\n";

		return $ids;
	}//END sphinx_most_recent_by_terms

	/*
	 * compare wpq and spx results
	 *
	 * @retval TRUE if the two results are the same. FALSE if not.
	 */
	public function compare_results( $wpq_results, $spx_results )
	{
		if ( ( $wpq_results === FALSE ) || ( $spx_results === FALSE ) )
		{
			echo "Comparing one or more FALSE results.\n\n";
			return FALSE;
		}

		if ( count( $wpq_results ) > count( $spx_results ) )
		{
			$results_diff = array_diff( $wpq_results, $spx_results );
		}
		else
		{
			$results_diff = array_diff( $spx_results, $wpq_results );
		}

		echo 'WP_Query and Sphinx results ' . ( empty( $results_diff ) ? '' : 'DO NOT ' ) . "match\n\n";

		return empty( $results_diff );
	}//END compare_results

	/**
	 * 6. Using the query from #1, pick two posts. Pick the most frequently
	 * used term on each post that doesn’t appear on the other post. Do a
	 * new AND query with those terms. Neither of the two exemplar posts
	 * should be returned in the result.
	 *
	 * query_var: tax_query
	 *
	 * @retval TRUE if the test passed. FALSE if the test failed or if
	 *  we encountered an error.
	 */
	public function mutually_exclusive_posts_test()
	{
		$query_terms = $this->setup_mutually_exclusive_posts_test();
		$res = $this->WP_mutually_exclusive_posts_test( $query_terms, FALSE );
		$res = ( $res && $this->SP_mutually_exclusive_posts_test( $query_terms, FALSE ) );
		echo "---\n\n";
		return $res;
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
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
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

		echo 'WP_Query for test ' . $this->test_count . ' ' . ( ( $test_failed ) ? "FAILED" : "PASSED" ) . ".\n\n";

		return ( ! $test_failed );
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
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
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
			return FALSE;
		}

		$matched_post_ids = $this->extract_sphinx_matches_ids( $results );

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

		return ( ! $test_failed );
	}//END SP_mutually_exclusive_posts_test

	/**
	 * 7. Using the terms from #6, do a new IN query with those terms.
	 * Both the exemplar posts from #4 should appear in the results
	 *
	 * query_var: tax_query
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function mutually_exclusive_posts_IN_test()
	{
		$query_terms = $this->setup_mutually_exclusive_posts_test();

		$res = $this->WP_mutually_exclusive_posts_test( $query_terms, TRUE );
		$res = ( $res && $this->SP_mutually_exclusive_posts_test( $query_terms, TRUE ) );
		echo "---\n\n";
		return $res;
	}//END mutually_exclusive_posts_IN_test
	
	/**
	 * "8. Using the term from #1, do a new query using the term name as the
	 *  keyword search string. The MySQL and Sphinx results should be
	 *  similar, though differences in sort order might be expected."
	 *
	 * query_var: s (keyword search)
	 */
	public function most_recent_by_term_name_test()
	{
		$terms = $this->get_most_used_terms( $this->ten_most_recent_hits_wp );
		if ( empty( $terms ) )
		{
			echo "no term found for most recent posts by term name test\n\n";
			return FALSE;
		}

		$term = $terms[0]['term'];

		// most recent ten hits, non-paged, using $term->name as a keyword
		$wpq_results = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date', // or 'modified'?
				'order'          => 'DESC',
				'fields'         => 'ids',
				's'              => '"' . $term->name . '"',
		) );

		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();
		$client->SetLimits( 0, 10, 1000 );
		//$client->SetRankingMode( SPH_RANK_SPH04 );
		//$client->SetSortMode( SPH_SORT_EXTENDED, '@rank DESC, post_date_gmt DESC' );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC, @rank DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$spx_results = $client->Query( '@post_content "' . $term->name . '" @post_status publish' );

		if ( FALSE === $spx_results )
		{
			echo "query error: ";
			print_r( $client->GetLastError() );
			echo "\n\n";
			return FALSE;
		}

		$spx_result_ids = $this->extract_sphinx_matches_ids( $spx_results );

		if ( count( $wpq_results->posts ) >= count( $spx_result_ids ) )
		{
			$diff = array_diff( $wpq_results->posts, $spx_result_ids );
			$source_len = count( $wpq_results->posts );
		}
		else
		{
			$diff = array_diff( $spx_result_ids, $wpq_results->posts );
			$source_len = count( $spx_result_ids );
		}

		echo "search term: \"$term->name\"\n";
		echo "WP_Query results:\n";
		print_r( $wpq_results->posts );
		echo "Sphinx results:\n";
		print_r( $spx_result_ids );
		echo "difference:\n";
		print_r( $diff );

		// our rough definition of similar: the difference should not be
		// more than 1/3 of the length of the longer result array
		echo "\n";
		$test_failed = FALSE;
		if ( count( $diff ) > ( $source_len / 3 ) )
		{
			echo 'test FAILED: WP_Query and Sphinx results differ by ' . count( $diff ) . " out of $source_len posts.\n\n" ;
			$test_failed = TRUE;
		}
		else
		{
			echo "test PASSED.\n\n";
		}
		echo "\n---\n\n";

		return ( ! $test_failed );
	}//END most_recent_by_term_name_test

	/**
	 * 9. Using the author ID from #1, do a new query for all results by
	 * that author. The MySQL and Sphinx results should be indistinguishable.
	 *
	 * query_var: author, single id
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function author_id_test()
	{
		$wp_author_results = $this->wp_query_all_author_posts( array( $this->ten_most_recent_hits_wp[0]->post_author ) );

		echo "\n";
		$sp_author_results = $this->sphinx_query_all_author_posts( array( $this->ten_most_recent_hits_wp[0]->post_author ) );

		$res = $this->compare_results( $wp_author_results, $sp_author_results );
		echo "---\n\n";

		return $res;
	}//END author_id_test	

	// @param $author_ids
	public function wp_query_all_author_posts( $author_ids, $exclude = FALSE )
	{
		echo 'WP_Query of all results for author(s) (' . implode( ', ', $author_ids ) . "):\n\n";

		if ( $exclude && ( 1 < count( $author_ids ) ) )
		{
			echo "cannot exclude more than one author id.\n";
			return FALSE;
		}
		if ( $exclude )
		{
			$author_ids_str = '-'.$author_ids[0];
		}
		else
		{
			$author_ids_str = implode( ',', $author_ids );
		}

		$results = new WP_Query(
			array(
				'author'         => $author_ids_str,
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
	}//END wp_query_all_author_posts
	
	public function sphinx_query_all_author_posts( $author_ids, $exclude = FALSE )
	{
		echo 'Sphinx query of all results for author(s) (' . implode( ', ', $author_ids ) . "):\n\n";

		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();
		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$client->SetFilter( 'post_author', $author_ids, $exclude );

		$results = $client->Query( '@post_status publish'); // @post_author ' . $author_id 
		
		if ( FALSE !== $results )
		{
			$ids = $this->extract_sphinx_matches_ids( $results );
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
	}//END sphinx_query_all_author_posts

	/**
	 * "10. Using the post IDs from #2, do a new query for the 10 most
	 *  recent posts that excludes the post IDs from the earlier query
	 *  using post__not_in. Both the MySQL and Sphinx results should
	 *  exclude those posts.
	 *
	 * query_var: post__not_in
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function post_not_in_test()
	{
		// get the ten most recent posts as in test #2.
		$terms = $this->get_most_used_terms( $this->ten_most_recent_hits_wp );
		if ( empty( $terms ) )
		{
			echo "no term found for most recent posts by term tests\n\n";
			return FALSE;
		}

		// find the posts to exclude from our search
		$excluded_posts = $this->wp_query_most_recent_by_terms( $terms[0]['term'], 10 );
		if ( 10 > count( $excluded_posts ) )
		{
			echo "could not find enough posts to exclude to complete the test\n\n";
			return FALSE;
		}

		$wpq_results = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date', // or 'modified'?
				'order'          => 'DESC',
				'fields'         => 'ids',
				'post__not_in'   => $excluded_posts,
		) );

		$test_failed = FALSE;

		if ( $wpq_results->post_count != 10 )
		{
			echo "did not find expected number of WP_Query results (10). FAILED\n\n";
			$test_failed = TRUE;
		}
		else
		{
			$diff = array_diff( $wpq_results->posts, $excluded_posts );
			if ( count( $diff ) == 10 )
			{
				echo "WP_Query results do not include any id from the exclusion list. PASSED.\n\n";
			}
			else
			{
				echo "FAILED: some excluded posts were still found in search results.\n\n";
				$test_failed = TRUE;
			}
		}

		// sphinx search
		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();
		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC, @rank DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$client->SetFilter( '@id', $excluded_posts, TRUE );
		$spx_results = $client->Query( '@post_status publish' );

		if ( FALSE === $spx_results )
		{
			echo "query error: ";
			print_r( $client->GetLastError() );
			echo "\n---\n\n";
			return FALSE;
		}

		if ( 10 > count( $spx_results['matches'] ) )
		{
			echo "did not find expected number of Sphinx results (10). FAILED\n\n";
			$test_failed = TRUE;
		}
		else
		{
			$matched_ids = $this->extract_sphinx_matches_ids( $spx_results );

			$diff = array_diff( $matched_ids, $excluded_posts );
			if ( count( $diff ) == 10 )
			{
				echo "Sphinx results do not include any id from the exclusion list. PASSED.\n\n";
			}
			else
			{
				echo "FAILED: some excluded posts were still found in search results.\n\n";
				$test_failed = TRUE;
			}
		}

		echo "---\n\n";

		return ( ! $test_failed );
	}//END post_not_in_test

	/**
	 * 11. Using the posts from #1, repeat the query using the ID of the 3rd ordinal post as a post__in argument.
	 * Only one post should be returned, it should match the post ID used as the post__in argument.
	 *
	 * query_var: post__in
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function post_in_single_test()
	{
		// get the ten most recent posts as in test #1.
		$posts = $this->ten_most_recent_hits_wp;
		if ( empty( $posts ) )
		{
			echo "no posts found \n\n";
			return FALSE;
		}
		
		$id_to_test = $posts[2]->ID;

		$wpq_results = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date', // or 'modified'?
				'order'          => 'DESC',
				'fields'         => 'ids',
				'post__in'       => array($id_to_test),
		) );

		
		if ( $wpq_results->post_count != 1 )
		{
			echo "did not find expected number of WP_Query results (1). FAILED\n\n";
			$test_failed = TRUE;
		}
		else
		{
			if ( $wpq_results->posts[0] == $id_to_test )
			{
				echo "WP_Query results for test $this->test_count PASSED.\n\n";
			}
			else
			{
				echo "FAILED: unexpected id found in search results.\n\n";
				$test_failed = TRUE;
			}
		}

		// sphinx search
		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();
		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC, @rank DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$client->SetFilter( '@id', array( $id_to_test ) );
		$spx_results = $client->Query( '@post_status publish' );

		if ( FALSE === $spx_results )
		{
			echo "query error: ";
			print_r( $client->GetLastError() );
			echo "\n---\n\n";
			return FALSE;
		}
		
		if ( 1 > count( $spx_results['matches'] ) )
		{
			echo "did not find expected number of Sphinx results (1). FAILED\n\n";
			$test_failed = TRUE;
		}
		else
		{
			if ( $wpq_results->posts[0] == $id_to_test )
			{
				echo "Sphinx results for test $this->test_count: PASSED.\n\n";
			}
			else
			{
				echo "FAILED: unexpected id found in search results.\n\n";
				$test_failed = TRUE;
			}
		}

		echo "---\n\n";

		return ( ! $test_failed );
	}//END post_in_single_test

	/**
	 * "12. Repeat the query from #6, but include the post IDs from #7 as 
	 * a post__in argument.
	 * "The results of this query should be the same as #6, with neither of
	 * the exemplar posts used to generate #6 returned."
	 *
	 * query_var: post__in
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function post_in_test()
	{
		$tax_query = array( 'relation' => 'AND' );
		$posts_in = array();

		$query_terms = $this->setup_mutually_exclusive_posts_test();
		foreach( $query_terms as $post_id => $term )
		{
			$tax_query[] = array(
				'taxonomy' => $term->taxonomy,
				'field'    => 'id',
				'terms'	   => $term->term_id,
				);
			$posts_in[] = $post_id;
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
				'post__in'       => $posts_in,
				) );

		// make sure keys (post_id) from $query_terms are not in $query_results
		$test_failed = FALSE;
		foreach ( $query_terms as $post_id => $term_obj )
		{
			if ( in_array( $post_id, $query_results->posts ) )
			{
				$test_failed = TRUE;
				break;
			}
		}

		echo 'WP_Query for test ' . $this->test_count . ' ' . ( ( $test_failed ) ? "FAILED" : "PASSED" ) . ".\n\n";

		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();

		$ttids = array();
		foreach( $query_terms as $term )
		{
			// set a filter for each ttid to filter to get the AND behavior
			$client->SetFilter( 'tt_id', array( $term->term_taxonomy_id ) );
		}
		$client->SetFilter( '@id', $posts_in);
		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$results = $client->Query( '@post_status publish' );

		if ( FALSE === $results )
		{
			echo "query error: ";
			print_r( $client->GetLastError() );
			echo "\n\n";
			return FALSE;
		}

		// make sure ids from $posts_in are not in $results['matches']
		$matched_post_ids = $this->extract_sphinx_matches_ids( $results );

		$test_failed = FALSE;
		foreach ( $query_terms as $post_id => $term_obj )
		{
			if ( in_array( $post_id, $matched_post_ids ) )
			{
				$test_failed = TRUE;
				break;
			}
		}

		echo 'Sphinx query for test ' . $this->test_count . ' ' . ( ( $test_failed ) ? "FAILED" : "PASSED" ) . ".\n\n";
		echo "---\n\n";

		return ( ! $test_failed );
	}//END post_in_test

	/**
	 * 13. Using two author IDs from #1, do a new query for all results by
	 * those authors. The MySQL and Sphinx results should be indistinguishable
	 *
	 * query var tested: author (multi-value)
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function author_ids_test()
	{
		// find two author ids
		$author_ids = array();
		foreach( $this->ten_most_recent_hits_wp as $post )
		{
			if ( ! in_array( $post->post_author, $author_ids ) )
			{
				$author_ids[] = $post->post_author;
				if ( count( $author_ids ) >= 2 )
				{
					break;
				}
			}
		}

		$wp_author_results = $this->wp_query_all_author_posts( $author_ids );

		echo "\n";

		$sp_author_results = $this->sphinx_query_all_author_posts( $author_ids );
		$res = $this->compare_results( $wp_author_results, $sp_author_results );
		echo "---\n\n";

		return $res;
	}//END author_ids_test	

	/**
	 * 14. Using an author ID from #1, do a new query for all results not
	 * by that author. The MySQL and Sphinx results should be indistinguishable
	 *
	 * query var tested: author (exclude)
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function not_author_id_test()
	{
		$post = $this->ten_most_recent_hits_wp[2];
		$author_id = $post->post_author;

		$wp_author_results = $this->wp_query_all_author_posts( array( $author_id ), TRUE );
		if ( in_array( $post->ID, $wp_author_results ) )
		{
			echo "post $post->ID should not be in query results\n";
		}
		echo "\n";

		$sp_author_results = $this->sphinx_query_all_author_posts( array( $author_id ), TRUE );
		if ( in_array( $post->ID, $sp_author_results ) )
		{
			echo "post $post->ID should not be in query results\n";
		}

		$res = $this->compare_results( $wp_author_results, $sp_author_results );
		echo "---\n\n";

		return $res;
	}//END not_author_id_test	

	/**
	 * 15. Pick the 3rd most popular category, or the most popular one if
	 * there’re not enough categories in the system, and then find the ten
	 * most recent posts in that category. the WP_Query and Sphinx results
	 * should match.
	 *
	 * query var tested: category__in
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function category_in_test()
	{
		return $this->terms_test( 'category', TRUE );
	}

	/**
	 * 16. With the same category from #15, query for the ten most recent
	 * posts *not* in that category. the WP_Query and Sphinx results should
	 * match
	 *
	 * query var tested: category__not_in
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function category_not_in_test()
	{
		return $this->terms_test( 'category', FALSE );
	}

	/**
	 * 18. similar to #15 but use a tag instead of category.
	 *
	 * query var tested: tag__in
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function tags_in_test()
	{
		return $this->terms_test( 'post_tag', TRUE );
	}

	/**
	 * 19. similar to #16 but use a tag instead of category.
	 *
	 * query var tested: tag__not_in
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function tags_not_in_test()
	{
		return $this->terms_test( 'post_tag', FALSE );
	}

	/*
	 * @param $taxonomy the taxonomy to use. only 'category' and
	 *  'post_tag' are supported.
	 * @param $include query for posts the includes the taxonomy term
	 *  if TRUE. else query for posts without the taxonomy term.
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function terms_test( $taxonomy, $include )
	{
		if ( ( $taxonomy != 'category' ) && ( $taxonomy != 'post_tag' ) )
		{
			echo "unsupported taxonomy $taxonomy.\n\n";
			return FALSE;
		}

		$the_term = FALSE;

		$terms = get_terms( array( $taxonomy ), array(
								'orderby' => 'count',
								'order'   => 'DESC',
						  ) );

		if ( empty( $terms ) )
		{
			echo "$taxonomy terms not found. cannot complete test.\n\n";
			echo "---\n\n";
			return;
		}

		// take the 3rd most popular term if possible. else just take
		// the most popular one.
		if ( 3 <= count( $terms ) )
		{
			$the_term = $terms[2];
		}
		else
		{
			$the_term = $terms[0];
		}

		if ( $taxonomy == 'category' )
		{
			$query_var = $include ? 'category__in' : 'category__not_in';
		}
		else
		{
			$query_var = $include ? 'tag__in' : 'tag__not_in';
		}

		$test_type = $include ? '' : 'of "not_in" search ';

		echo 'comparing search results ' . $test_type . "for taxonomy '$taxonomy' on term '$the_term->name'\n\n";

		$wp_results = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
				$query_var       => $the_term->term_id,
				'fields'         => 'ids',
		) );

		$test_failed = FALSE;
		if ( $wp_results->post_count == 0 )
		{
			echo "did not find any post from WP_Query. FAILED.\n\n";
			$test_failed = TRUE;
		}
		else
		{
			echo 'WP_Query results: ' . implode( ', ', $wp_results->posts ). "\n\n";
		}

		// now with sphinx
		$this->client = FALSE; // get a new instance
		$client = $this->client();
		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$client->SetFilter( 'tt_id', array( $the_term->term_taxonomy_id ), ! $include );
		$sp_results = $client->Query( '@post_status publish' );

		$sp_result_ids = $this->extract_sphinx_matches_ids( $sp_results );

		if ( empty( $sp_result_ids ) )
		{
			echo "did not find any post from Sphinx query. FAILED.\n\n";
			$test_failed = TRUE;
		}
		else
		{
			echo '  Sphinx results: ' . implode( ', ', $sp_result_ids ). "\n\n";
		}

		$test_failed = ( $test_failed || ( ! $this->compare_results( $wp_results->posts, $sp_result_ids ) ) );
		echo "---\n\n";

		return ( ! $test_failed );
	}//END category_test

	/**
	 * 17. Pick two categories from one of the posts from #1, then query
	 * for the ten most recent posts in those two categories. The examplar
	 * post from #1 should be in the results and the WP and Sphinx results
	 * should match.
	 *
	 * query var tested: category__and
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function category_and_test()
	{
		$the_post = FALSE;
		$categories = array();
		foreach( $this->ten_most_recent_hits_wp as $post )
		{
			$terms = wp_get_object_terms( $post->ID, 'category' );
			if ( ! empty( $terms ) && 2 <= count( $terms ) )
			{
				$the_post = $post;
				$category = $terms;
				break;
			}
		}

		if ( FALSE === $the_post)
		{
			echo "post or category not found. cannot complete test.\n\n";
			echo "---\n\n";
			return FALSE;
		}

		$category_ids = array();
		$category_ttids = array();
		foreach( $categories as $category_term )
		{
			$category_ids[] = $category_term->term_id;
			$category_ttids[] = $category_term->term_taxonomy_id;
		}

		$wp_results = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'category__and'  => $category_ids,
				'fields'         => 'ids',
		) );

		$test_failed = FALSE;
		if ( ( $wp_results->post_count == 0 ) ||
			 ! in_array( $the_post->ID, $wp_results->posts ) )
		{
			echo "did not find expected post ($the_post->ID) in WP_Query results. FAILED.\n\n";
			$test_failed = TRUE;
		}
		else
		{
			echo "WP_Query results included the expected post ($the_post->ID). PASSED\n\n";
		}

		// now with sphinx
		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();
		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		foreach( $category_ttids as $ttid )
		{
			$client->SetFilter( 'tt_id', array( $ttid ) );
		}
		$sp_results = $client->Query( '@post_status publish' );

		$sp_result_ids = $this->extract_sphinx_matches_ids( $sp_results );

		if ( empty( $sp_result_ids ) ||
			 ! in_array( $the_post->ID, $sp_result_ids ) )
		{
			echo "did not find expected post ($the_post->ID) in Sphinx results. FAILED.\n\n";
			$test_failed = TRUE;
		}
		else
		{
			echo "Sphinx results included the expected post ($the_post->ID). PASSED\n\n";
		}

		$test_failed = ( $test_failed || ( ! $this->compare_results( $wp_results->posts, $sp_result_ids ) ) );
		echo "---\n\n";

		return ( ! $test_failed );
	}//END category_and_test

	/**
	 * 20. Pick two tags from the ten most frequently used tags and query
	 * for the most recent ten posts having both of those tags. the WP_Query
	 * and Sphinx results should match exactly
	 *
	 * query var tested: tag__and
	 *
	 * @retval TRUE if the test passed.
	 * @retval FALSE if the test failed or if we encountered an error.
	 */
	public function tag_and_test()
	{
		$terms = get_terms( array( 'post_tag' ), array(
								'orderby' => 'count',
								'order'   => 'DESC',
						  ) );

		if ( empty( $terms ) || ( 2 > count( $terms ) ) )
		{
			echo "not enough post_tags found. cannot complete test.\n\n";
			echo "---\n\n";
			return FALSE;
		}

		// take the 3rd and 5th most popular tags if possible. else just take
		// the first two.
		if ( 5 <= count( $terms ) )
		{
			$the_terms = array( $terms[2], $terms[4] );
		}
		else
		{
			$the_terms = array( $terms[0], $terms[1] );
		}
		$term_ids = array( $the_terms[0]->term_id, $the_terms[1]->term_id );
		$term_names = array( $the_terms[0]->name, $the_terms[1]->name );
		$ttids = array( $the_terms[0]->term_taxonomy_id, $the_terms[1]->term_taxonomy_id );

		echo 'comparing search results on terms ' . implode( ' and ', $term_ids ) . ' ("' . implode( '" and "', $term_names ) . "\")\n\n";

		$wp_results = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tag__and'       => $term_ids,
				'fields'         => 'ids',
		) );

		$test_failed = FALSE;
		if ( $wp_results->post_count == 0 )
		{
			echo "did not find any post from WP_Query. FAILED.\n\n";
			$test_failed = TRUE;
		}
		else
		{
			echo 'WP_Query results: ' . implode( ', ', $wp_results->posts ). "\n\n";
		}

		// now with sphinx
		$this->client = FALSE; // get a new instance
		$client = $this->client();
		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		foreach( $ttids as $ttid )
		{
			$client->SetFilter( 'tt_id', array( $ttid ) );
		}
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$sp_results = $client->Query( '@post_status publish' );

		$sp_result_ids = $this->extract_sphinx_matches_ids( $sp_results );

		if ( empty( $sp_result_ids ) )
		{
			echo "did not find any post from Sphinx query. FAILED.\n\n";
			$test_failed = TRUE;
		}
		else
		{
			echo '  Sphinx results: ' . implode( ', ', $sp_result_ids ). "\n\n";
		}

		$test_failed = ( $test_failed || ( ! $this->compare_results( $wp_results->posts, $sp_result_ids ) ) );
		echo "---\n\n";

		return ( ! $test_failed );
	}
}//END GO_Sphinx_Test