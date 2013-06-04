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
		if( ! current_user_can( 'activate_plugins' ))
		{
			die(0);
		}

		die;
	}
}
