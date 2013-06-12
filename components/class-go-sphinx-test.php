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

		echo "<pre>\n";

		echo "$this->test_count.\n";
		$this->ten_most_recent_posts_test();
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->most_recent_by_terms_test( 10 );
		++$this->test_count;
/*
		echo "$this->test_count.\n";
		$this->most_recent_by_terms_test( 53 );
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->most_recent_by_two_terms_test();
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->most_recent_by_two_terms_paged_test();
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->mutually_exclusive_posts_test();
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->mutually_exclusive_posts_IN_test();
		++$this->test_count;
		
		echo "$this->test_count.\n";
		$this->most_recent_by_term_name_test();
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->author_id_test();
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->post_not_in_test();
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->post_in_single_test();
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->post_in_test();
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->author_ids_test();
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->not_author_id_test();
		++$this->test_count;
*/
		echo "$this->test_count.\n";
		$this->category_test( TRUE );
		++$this->test_count;

		echo "$this->test_count.\n";
		$this->category_test( FALSE );
		++$this->test_count;

		echo "</pre>\n";
		die;
	}

	/**
	 * "1. Query for the 10 most recent posts (not necessarily post_type=post)
	 *  in the posts table. The MySQL and Sphinx results should be
	 * indistinguishable."
	 *
	 * query_var: posts_per_page, order, orderby, post_type, post_status
	 */
	public function ten_most_recent_posts_test()
	{
		// these tests also populate $this->ten_most_recent_hits_wp and
		// $this->ten_most_recent_hits_spx which we'll need for other tests
		$wpq_results = $this->wp_query_ten_most_recent_posts();
		$spx_results = $this->sphinx_ten_most_recent_posts();
		$this->compare_results( $wpq_results, $spx_results );
		echo "---\n\n";
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
	 */
	public function most_recent_by_terms_test( $num_posts )
	{
		$terms = $this->get_most_used_terms( $this->ten_most_recent_hits_wp );
		if ( empty( $terms ) )
		{
			echo "no term found for most recent posts by term tests\n\n";
			return;
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

		$this->compare_results( $wpq_results, $spx_results );

		echo "---\n\n";
	}//END most_recent_by_terms_test

	/**
	 * "4. Using the same post from #1, pick the most frequently used two
	 * terms on that post and do a new query for posts with those terms.
	 * The exemplar post from #1 should be returned in this query."
	 *
	 * query_var: tax_query
	 */
	public function most_recent_by_two_terms_test()
	{
		$terms = $this->get_most_used_terms( $this->ten_most_recent_hits_wp, 2 );
		if ( empty( $terms ) )
		{
			echo "no term found for most recent posts by term tests\n\n";
			return;
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

		$this->compare_results( $wpq_results, $spx_results );

		echo "---\n\n";
	}//END most_recent_by_two_terms_test

	/**
	 * "5. Repeat the query from #4, but change the posts_per_page value to
	 *  3 and paged to 3. The query should return up to 3 posts starting
	 *  with the last post returned in #4; the MySQL and Sphinx results 
	 *  should be indistinguishable."
	 *
	 * query_var: tax_query, paged
	 */
	public function most_recent_by_two_terms_paged_test()
	{
		$terms = $this->get_most_used_terms( $this->ten_most_recent_hits_wp, 2 );
		if ( empty( $terms ) )
		{
			echo "no term found for most recent posts by term tests\n\n";
			return;
		}

		$term_objs = array( $terms[0]['term'], $terms[1]['term'] );

		$wpq_results = $this->wp_query_most_recent_by_terms( $term_objs, 3, 4 );

		$spx_results = $this->sphinx_most_recent_by_terms( $term_objs, 3, 4 );

		$this->compare_results( $wpq_results, $spx_results );

		echo "---\n\n";
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

	// compare wpq and spx results
	public function compare_results( $wpq_results, $spx_results )
	{
		if ( ( $wpq_results === FALSE ) || ( $spx_results === FALSE ) )
		{
			echo "Comparing one or more FALSE results.\n\n";
			return;
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
	}//END compare_results

	/**
	 * 6. Using the query from #1, pick two posts. Pick the most frequently
	 * used term on each post that doesn’t appear on the other post. Do a
	 * new AND query with those terms. Neither of the two exemplar posts
	 * should be returned in the result.
	 *
	 * query_var: tax_query
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

		echo 'WP_Query for test ' . $this->test_count . ' ' . ( ( $test_failed ) ? "FAILED" : "PASSED" ) . ".\n\n";
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
		
	}//END SP_mutually_exclusive_posts_test

	/**
	 * 7. Using the terms from #6, do a new IN query with those terms.
	 * Both the exemplar posts from #4 should appear in the results
	 *
	 * query_var: tax_query
	 */
	public function mutually_exclusive_posts_IN_test()
	{
		$query_terms = $this->setup_mutually_exclusive_posts_test();

		$this->WP_mutually_exclusive_posts_test( $query_terms, TRUE );
		$this->SP_mutually_exclusive_posts_test( $query_terms, TRUE );
		echo "---\n\n";
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
			return;
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
			return;
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
		if ( count( $diff ) > ( $source_len / 3 ) )
		{
			echo 'test FAILED: WP_Query and Sphinx results differ by ' . count( $diff ) . " out of $source_len posts.\n\n" ;
		}
		else
		{
			echo "test PASSED.\n\n";
		}
		echo "\n---\n\n";
	}//END most_recent_by_term_name_test

	/**
	 * 9. Using the author ID from #1, do a new query for all results by
	 * that author. The MySQL and Sphinx results should be indistinguishable.
	 *
	 * query_var: author, single id
	 */
	public function author_id_test()
	{
		$wp_author_results = $this->wp_query_all_author_posts( array( $this->ten_most_recent_hits_wp[0]->post_author ) );

		echo "\n";
		$sp_author_results = $this->sphinx_query_all_author_posts( array( $this->ten_most_recent_hits_wp[0]->post_author ) );

		$this->compare_results( $wp_author_results, $sp_author_results );
		echo "---\n\n";
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
	 */
	public function post_not_in_test()
	{
		// get the ten most recent posts as in test #2.
		$terms = $this->get_most_used_terms( $this->ten_most_recent_hits_wp );
		if ( empty( $terms ) )
		{
			echo "no term found for most recent posts by term tests\n\n";
			return;
		}

		// find the posts to exclude from our search
		$excluded_posts = $this->wp_query_most_recent_by_terms( $terms[0]['term'], 10 );
		if ( 10 > count( $excluded_posts ) )
		{
			echo "could not find enough posts to exclude to complete the test\n\n";
			return;
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

		if ( $wpq_results->post_count != 10 )
		{
			echo "did not find expected number of WP_Query results (10). FAILED\n\n";
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
			return;
		}

		if ( 10 > count( $spx_results['matches'] ) )
		{
			echo "did not find expected number of Sphinx results (10). FAILED\n\n";
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
			}
		}

		echo "---\n\n";
	}//END post_not_in_test

	/**
	 * 11. Using the posts from #1, repeat the query using the ID of the 3rd ordinal post as a post__in argument.
	 * Only one post should be returned, it should match the post ID used as the post__in argument.
	 *
	 * query_var: post__in
	 */
	public function post_in_single_test()
	{
		// get the ten most recent posts as in test #1.
		$posts = $this->ten_most_recent_hits_wp;
		if ( empty( $posts ) )
		{
			echo "no posts found \n\n";
			return;
		}
		
		//wp_dbug($posts);
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
			return;
		}
		
		if ( 1 > count( $spx_results['matches'] ) )
		{
			echo "did not find expected number of Sphinx results (1). FAILED\n\n";
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
			}
		}

		echo "---\n\n";

	}//END post_in_single_test

	/**
	 * "12. Repeat the query from #6, but include the post IDs from #7 as 
	 * a post__in argument.
	 * "The results of this query should be the same as #6, with neither of
	 * the exemplar posts used to generate #6 returned."
	 *
	 * query_var: post__in
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
			return;
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
	}//END post_in_test

	/**
	 * 13. Using two author IDs from #1, do a new query for all results by
	 * those authors. The MySQL and Sphinx results should be indistinguishable
	 *
	 * query var tested: author (multi-value)
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
		$this->compare_results( $wp_author_results, $sp_author_results );
		echo "---\n\n";
	}//END author_ids_test	

	/**
	 * 14. Using an author ID from #1, do a new query for all results not
	 * by that author. The MySQL and Sphinx results should be indistinguishable
	 *
	 * query var tested: author (exclude)
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

		$this->compare_results( $wp_author_results, $sp_author_results );
		echo "---\n\n";
	}//END not_author_id_test	

	/**
	 * 15. Pick the first of those posts from #1 that has a category, then
	 * pick the ten most recent posts in that category. The exemplar post
	 * from #1 should be returned in this query.
	 *
	 * 16. With the same category from #15, query for the ten most recent
	 * posts not in that category. The exemplar post from #1 should not be
	 * returned in the results.
	 *
	 * query var tested: category__in, category__not_in
	 *
	 * @param $category_in test with category__in query_var if TRUE, else
	 *  test with category__not_in.
	 */
	public function category_test( $category_in )
	{
		$the_post = FALSE;
		$category = FALSE;
		foreach( $this->ten_most_recent_hits_wp as $post )
		{
			$terms = wp_get_object_terms( $post->ID, 'category' );
			if ( ! empty( $terms ) )
			{
				$the_post = $post;
				$category = $terms[0];
				break;
			}
		}

		if ( ( FALSE === $the_post) || ( FALSE === $category ) )
		{
			echo "post or category not found. cannot complete test.\n\n";
			echo "---\n\n";
			return;
		}

		$category_query_var = $category_in ? 'category__in' : 'category__not_in';
		$wp_results = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
				$category_query_var   => $category->term_id,
				'fields'         => 'ids',
		) );

		if ( ( $wp_results->post_count == 0 ) ||
			 ( $category_in && ! in_array( $the_post->ID, $wp_results->posts ) ) ||
			 ( ! $category_in && in_array( $the_post->ID, $wp_results->posts ) ) )
		{
			if ( $category_in )
			{
				echo "did not find expected post ($the_post->ID) in WP_Query results. FAILED.\n\n";
			}
			else
			{
				echo "found unexpected post ($the_post->ID) in WP_Query results. FAILED.\n\n";
			}
		}
		else
		{
			echo 'WP_Query results ' . ( $category_in ? 'included' : 'excluded' ) . " expected post ($the_post->ID). PASSED\n\n";
		}

		// now with sphinx
		$this->client = FALSE; // ensure we get a new instance
		$client = $this->client();
		$client->SetLimits( 0, 10, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$client->SetFilter( 'tt_id', array( $category->term_taxonomy_id ), ! $category_in );
		$sp_results = $client->Query( '@post_status publish' );

		$sp_result_ids = $this->extract_sphinx_matches_ids( $sp_results );

		if ( empty( $sp_result_ids ) ||
			 ( $category_in && ! in_array( $the_post->ID, $sp_result_ids ) ) ||
			 ( ! $category_in && in_array( $the_post->ID, $sp_result_ids ) ) )
		{
			if ( $category_in )
			{
				echo "did not find expected post ($the_post->ID) in Sphinx results. FAILED.\n\n";
			}
			else
			{
				echo "found unexpected post ($the_post->ID) in Sphinx results. FAILED.\n\n";
			}
		}
		else
		{
			echo 'Sphinx results ' . ( $category_in ? 'included' : 'excluded' ) . " expected post ($the_post->ID). PASSED\n\n";
		}

		$this->compare_results( $wp_results->posts, $sp_result_ids );
		echo "---\n\n";
	}//END category_in_test

}//END GO_Sphinx_Test