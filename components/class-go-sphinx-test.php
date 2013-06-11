<?php

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
		++$this->test_count;
		$this->ten_most_recent_posts_test();

		echo "$this->test_count.\n";
		++$this->test_count;
		$this->most_recent_by_terms_test( 10 );

		echo "$this->test_count.\n";
		++$this->test_count;
		$this->most_recent_by_terms_test( 53 );

		echo "$this->test_count.\n";
		++$this->test_count;
		$this->most_recent_by_two_terms_test();

		echo "$this->test_count.\n";
		++$this->test_count;
		$this->most_recent_by_two_terms_paged_test();

		// invoke all tests in GO_Sphinx_Test2 from one function for
		// easier merge later
		$this->group_2_tests();

		echo "$this->test_count.\n";
		++$this->test_count;
		$this->most_recent_by_term_name_test();

		$this->test_count = 9;
		echo "$this->test_count.\n";
		++$this->test_count;
		$this->post_not_in_test();

		echo "</pre>\n";
		die;
	}

	/**
	 * "1. Query for the 10 most recent posts (not necessarily post_type=post)
	 *  in the posts table. The MySQL and Sphinx results should be
	 * indistinguishable."
	 */
	public function ten_most_recent_posts_test()
	{
		// these tests also populate $this->ten_most_recent_hits_wp and
		// $this->ten_most_recent_hits_spx which we'll need for other tests
		$wpq_results = $this->wp_query_ten_most_recent_posts();
		$spx_results = $this->sphinx_ten_most_recent_posts();
		$this->compare_results( $wpq_results, $spx_results );
		echo "---\n\n";
	}

	/**
	 * "2. Pick the first of those rows [from 1.] that has taxonomy terms on
	 * it, then pick the most frequently used of those taxonomy terms, then
	 * do a query for posts that have that term; The exemplar post from #1
	 * should be returned in this query."
	 *
	 * "3. Repeat the query from #2, but change the posts_per_page value to
	 * 53. The query should return up to 53 posts; the MySQL and Sphinx
	 * results should be indistinguishable."
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
	}

	/**
	 * "4. Using the same post from #1, pick the most frequently used two
	 * terms on that post and do a new query for posts with those terms.
	 * The exemplar post from #1 should be returned in this query."
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
	}

	/**
	 * "5. Repeat the query from #4, but change the posts_per_page value to
	 *  3 and paged to 3. The query should return up to 3 posts starting
	 *  with the last post returned in #4; the MySQL and Sphinx results 
	 *  should be indistinguishable."
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
	}

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
	}


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
			$ids = array();
			foreach( $this->ten_most_recent_hits_spx as $match )
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
	}

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
	}

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

		$ids = array();
		foreach( $results['matches'] as $match )
		{
			$ids[] = $match['id'];
		}

		echo implode( ', ', $ids ) . "\n\n";

		return $ids;
	}

	// compare wpq and spx results
	public function compare_results( $wpq_results, $spx_results )
	{
		if ( count( $wpq_results ) > count( $spx_results ) )
		{
			$results_diff = array_diff( $wpq_results, $spx_results );
		}
		else
		{
			$results_diff = array_diff( $spx_results, $wpq_results );
		}

		echo 'WP_Query and Sphinx results ' . ( empty( $results_diff ) ? '' : 'DO NOT ' ) . "match\n\n";
	}

	/**
	 * run all tests from GO_Sphinx_Test2 from this function.
	 */
	public function group_2_tests()
	{
		// here's an example of invoking test functions in GO_Sphinx_Test2
		// via a virtual function in this class. make sure the function has
		// the same signature in both GO_Sphinx_Test (this class) and
		// GO_Sphinx_Test2 (the subclass).
		$this->mutually_exclusive_posts_test();

		$this->mutually_exclusive_posts_IN_test();
		
		$this->author_test();
	}

	public function mutually_exclusive_posts_test()
	{
		// virtual
	}

	public function mutually_exclusive_posts_IN_test()
	{
		// virtual
	}

	public function author_id_test()
	{
		// virtual
	}

	/**
	 * "8. Using the term from #1, do a new query using the term name as the
	 *  keyword search string. The MySQL and Sphinx results should be
	 *  similar, though differences in sort order might be expected."
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

		$spx_result_ids = array();
		if ( isset( $spx_results['matches'] ) )
		{
			foreach( $spx_results['matches'] as $match )
			{
				$spx_result_ids[] = $match['id'];
			}
		}

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
	}

	/**
	 * "10. Using the post IDs from #2, do a new query for the 10 most
	 *  recent posts that excludes the post IDs from the earlier query
	 *  using post__not_in. Both the MySQL and Sphinx results should
	 *  exclude those posts.
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
				echo "WP_Query results PASSED.\n\n";
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
		//$spx_results = $client->Query( '@!id ' . implode( ' ', $excluded_posts ) . ' @post_status publish' );
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
			$matched_ids = array();
			foreach( $spx_results['matches'] as $match )
			{
				$matched_ids[] = $match['id'];
			}

			$diff = array_diff( $matched_ids, $excluded_posts );
			if ( count( $diff ) == 10 )
			{
				echo "Sphinx results PASSED.\n\n";
			}
			else
			{
				echo "FAILED: some excluded posts were still found in search results.\n\n";
			}
		}

		echo "---\n\n";
	}

}//END GO_Sphinx_Test