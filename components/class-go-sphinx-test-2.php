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
	public function test_6()
	{
		echo "\nTest #6!\n\n---\n\n";
		$results = $this->setup_test_6( $results );
		$wpq_results = $this->WP_test_6( $results );
		$spx_results = $this->SP_test_6( $results );
		$this->compare_results( $wpq_results, $spx_results );
		echo "---\n\n";
	}		

	public function setup_test_6()
	{		
		echo "\nSetup Test #6!\n\n---\n\n";
		//wp_dbug($this->ten_most_recent_hits_wp);
		//echo "<hr>";
		//wp_dbug($this->ten_most_recent_hits_spx);
		
		foreach ( $this->ten_most_recent_hits_wp as $post ) 
		{
			$post_ids_to_terms[ $post->ID ] = $this->get_most_used_terms( array($post) );
		}
			
		//wp_dbug( $post_ids_to_terms );
		
		$subset_post_ids_to_terms = array();
		$term_ids_to_str = array();			
		
		foreach ( $post_ids_to_terms as $post_id => $terms_list ) 
		{
			// echo ($post_id);
			// echo "\n";
			$term_ids = array();
			foreach ($terms_list as $elem) {
				//echo $elem['term']->term_id;
				//echo "\n";
				$term_ids[] = $elem['term']->term_taxonomy_id;
				$term_ids_to_str[ $elem['term']->term_taxonomy_id ] = $elem['term'];
			}
			
			$subset_post_ids_to_terms[ $post_id ] = $term_ids;
		}
		
		//wp_dbug($post_ids_to_terms);
		//wp_dbug($term_ids_to_str);
		
		$results = array();
		
		foreach ( $subset_post_ids_to_terms as $post_id => $ttid_list ) 
		{
			foreach ( $ttid_list as $ttid ) 
			{
				if ( !$this->is_ttid_in_array( $subset_post_ids_to_terms, $post_id, $ttid ) )
				{
					$results[ $post_id ] =  $term_ids_to_str[ $ttid ];
					
					if ( 2 <= count( $results ) ) 
					{
						break;
					}
						 
				}				
			}
			if ( 2 <= count( $results ) ) 
			{
				break;
			}
		}
		
		return $results;
	} //END setup_test_6
		
	public function WP_test_6( $results )
	{
		echo "\nWP Test #6!\n\n---\n\n";
		$tax_query = array( 'relation' => 'AND' );
		foreach( $results as $term )
		{
			$tax_query[] = array(
				'taxonomy' => $term->taxonomy,
				'field' => 'id',
				'terms' => $term->term_id,
				);
		}
		
		$query_arg = array(
			'fields'		=> 'ids',
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => $tax_query,
			);
		$query_results = new WP_Query( $query_arg );	
		
		//WP_DBug( $query_results->posts );
		
		$test_failed = FALSE;
		foreach ( $results as $post_id => $term_obj ) {
			if ( in_array( $post_id, $query_results->posts ) )
			{
				$test_failed = TRUE;
				break;
			}
		}
		// make sure keys from $results are not in $query_results...
		echo ( $test_failed ) ? "FAILED" : "PASSED"; 
	} // END WP_test_6
	
	public function is_ttid_in_array($post_ids, $post_to_ignore, $ttid)
	{
		foreach ( $post_ids as $post_id => $ttid_list ) 
		{
			if ( $post_id == $post_to_ignore ) continue;
			if ( in_array($ttid, $ttid_list) )
			{
				return true;
			}
		}
		
		return false;
	} // END is_ttid_in_array
	
	
	public function SP_test_6( $terms_objects )
	{
		echo "\nWP Test #6!\n\n---\n\n";
		
		$client = $this->client();

		$ttids = array();
		foreach( $terms_objects as $term )
		{
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
		
		// make sure keys from $results are not in $terms...
		$test_failed = FALSE;
		foreach ( $terms_objects as $post_id ) 
		{
			if ( in_array( $post_id, $results ) )
			{
				$test_failed = TRUE;
				break;
			}
		}
		echo ( $test_failed ) ? "FAILED \n" : "PASSED \n"; 		
		
	}//END SP_test_6

}//END GO_Sphinx_Test2