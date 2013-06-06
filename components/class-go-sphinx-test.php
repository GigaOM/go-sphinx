<?php

class GO_Sphinx_Test extends GO_Sphinx
{
	public function __construct()
	{
		add_action( 'wp_ajax_go-sphinx-search-test', array( $this, 'search_test' ));
	}

	public function search_test()
	{
		// permissions check
		if( ! current_user_can( $this->admin_cap ))
		{
			wp_die();
		}

		echo "<pre>\n";
		$this->wp_query_ten_most_recent_posts();
		$this->sphinx_ten_most_recent_posts();
		echo "</pre>\n";
		die;
	}


	public function wp_query_ten_most_recent_posts()
	{
		echo "WP_Query of ten most recent posts:\n\n";

		$query = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date', // or 'modified'?
				'order'          => 'DESC',
				'fields'         => 'ids',
		) );

		echo implode( ', ', $query->posts ) . "\n\n";
		echo "done.\n";
	}


	public function sphinx_ten_most_recent_posts()
	{
		echo "\n---\nSphinx query of ten most recent posts:\n\n";

		$client = $this->client();
		$client->SetLimits( 0, 1000, 1000 );
		$client->SetSortMode( SPH_SORT_EXTENDED, 'post_date_gmt DESC' );
		//$client->SetMatchMode( SPH_MATCH_EXTENDED );
		$res = $client->Query();
		$hits = 0;
		if ( FALSE !== $res )
		{
			foreach( $res['matches'] as $match )
			{
				if ( 'publish' != $match['attrs']['post_status'] )
				{
					continue;
				}
				echo $match['id'] . ', ' . $match['attrs']['post_status'] . ': ' . $match['attrs']['post_date_gmt'] . "\n";
				$hits ++;
				if ( 10 == $hits )
				{
					break;
				}
			}
		}
		else
		{
			echo "query error!\n";
			print_r( $client->GetLastError() );
			echo "\n\n";
		}
		//echo implode( ', ', $query->posts ) . "\n\n";
		echo "done.\n";
	}

}//END GO_Sphinx_Test