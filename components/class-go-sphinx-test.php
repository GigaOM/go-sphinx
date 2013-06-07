<?php

class GO_Sphinx_Test extends GO_Sphinx
{
	public $ten_most_recent_hits_wp  = FALSE;
	public $ten_most_recent_hits_spx = FALSE;
	public $taxonomies = FALSE; // all taxonomies in the system

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
		$test_count = 1;

		echo "$test_count.\n";
		++$test_count;
		$this->ten_most_recent_posts_test();

		echo "$test_count.\n";
		++$test_count;
		$this->most_recent_by_terms_test( 10 );

		echo "$test_count.\n";
		++$test_count;
		$this->most_recent_by_terms_test( 53 );

		echo "$test_count.\n";
		++$test_count;
		$this->most_recent_by_two_terms_test( 10 );

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

	public function wp_query_most_recent_by_terms( $terms, $num_results )
	{
		if ( is_array( $terms ) )
		{
			$tax_query = array( 'relation' => 'AND' );
			$ttids = array();
			foreach( $terms as $term )
			{
				$tax_query[] = array(
					'taxonomy' => $term->taxonomy,
					'field' => 'id',
					'terms' => $term->term_id,
					'operator' => 'IN',
					);
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
		echo "WP_Query of $num_results most recent posts with ttid(s) " . implode( ', ', $ttids ) . ":\n\n";

		$results = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => $num_results,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => $tax_query,
				) );

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

	public function sphinx_most_recent_by_terms( $terms, $num_results )
	{
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

		echo "\nSphinx query of $num_results most recent posts with ttid(s) " . implode( ', ', $ttids ) . ":\n\n";

		$client->SetLimits( 0, $num_results, 1000 );
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
		$results_diff = array_diff( $wpq_results, $spx_results );
		echo 'WP_Query and Sphinx results ' . ( empty( $results_diff ) ? '' : 'DO NOT ' ) . "match\n\n";
	}

}//END GO_Sphinx_Test