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

		echo "1.\n";
		$this->ten_most_recent_posts_test();

		echo "2.\n";
		$this->most_recent_by_term_test( 10 );

		echo "3.\n";
		$this->most_recent_by_term_test( 53 );

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
	 * "Repeat the query from #2, but change the posts_per_page value to 53.
	 * The query should return up to 53 posts; the MySQL and Sphinx results
	 * should be indistinguishable."
	 */
	public function most_recent_by_term_test( $num_posts )
	{
		if ( FALSE === ( $result = $this->get_most_used_term( $this->ten_most_recent_hits_wp ) ) )
		{
			echo "no term found for most recent posts by term tests\n\n";
			return;
		}
		$wpq_results = $this->wp_query_most_recent_by_term( $result['term'], $num_posts );
		if ( $wpq_results && in_array( $result['post_id'], $wpq_results ) )
		{
			echo 'source post (' . $result['post_id'] . ") found\n\n";
		}
		else
		{
			echo 'source post (' . $result['post_id'] . ") NOT found\n\n";
		}

		$spx_results = $this->sphinx_most_recent_by_term( $result['term'], $num_posts );
		if ( $spx_results && in_array( $result['post_id'], $spx_results ) )
		{
			echo 'source post (' . $result['post_id'] . ") found\n\n";
		}
		else
		{
			echo 'source post (' . $result['post_id'] . ") NOT found\n\n";
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

	// find the most frequently used term in the first post in $posts
	// that contains any taxonomy. return a term object or FALSE if
	// not found.
	public function get_most_used_term( $posts )
	{
		$post_id = FALSE;    // set to first post with any taxonomy
		$taxonomies = FALSE; // taxonomies associated with $post_id

		foreach( $posts as $post )
		{
			$taxonomies = get_object_taxonomies( $post->post_type );
			if ( ! empty( $taxonomies ) )
			{
				$post_id = $post->ID;
				break;
			}
		}

		$terms = wp_get_object_terms( $post_id, $taxonomies, array(
										  'orderby' => 'count',
										  'order'   => 'DESC',
									) );
		if ( is_wp_error( $terms ) )
		{
			print_r( $terms ) . "\n\n";
			return FALSE;
		}

		// order = DESC seems to list count of 0 first so we have to
		// make sure to skip posts with count of 0
		foreach( $terms as $term )
		{
			if ( $term->count != 0 )
			{
				return array( 'post_id' => $post_id, 'term' => $term );
			}
		}
		return FALSE;
	}

	public function wp_query_most_recent_by_term( $term, $num_results )
	{
		echo "WP_Query of ten most recent posts with ttid $term->term_taxonomy_id:\n\n";

		$results = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => $num_results,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => array(
					array(
						'taxonomy' => $term->taxonomy,
						'field' => 'id',
						'terms' => $term->term_id,
						) )
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

	public function sphinx_most_recent_by_term( $term, $num_results )
	{
		echo "\nSphinx query of ten most recent posts with ttid $term->term_taxonomy_id:\n\n";

		$client = $this->client();
		$client->SetLimits( 0, $num_results, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$client->SetFilter( 'tt_id', array( $term->term_taxonomy_id ) );
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