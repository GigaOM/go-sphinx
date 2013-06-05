<?php

class GO_Sphinx_Admin extends GO_Sphinx
{
	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'plugin_action_links_go-sphinx/go-sphinx.php' , array( $this, 'plugin_action_links' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	public function admin_init()
	{
		// load the test suite if the user has permissions
		if( current_user_can( $this->admin_cap ))
		{
			$this->test();
		}
	}

	public function admin_menu()
	{
		add_submenu_page( 'options-general.php', 'Sphinx Configuration', 'Sphinx' , $this->admin_cap , 'go-sphinx' , array( $this, 'config_page' ) );
	}

	public function plugin_action_links( $actions )
	{
		$actions[] = '<a href="' . admin_url( 'options-general.php?page=go-sphinx' ) . '">'. __( 'Settings' ) .'</a>';
		return $actions;
	}

	public function config_page()
	{
		if( ! function_exists( 'bcms_search' ) )
		{
			echo '<h2>Please install bCMS and activate the full text search</h2>';
			return;
		}

		global $wpdb;
		$c = (object) array(
			'name'   => $wpdb->posts,
			'host'   => $wpdb->dbhost,
			'user'   => $wpdb->dbuser,
			'pass'   => $wpdb->dbpassword,
			'db'     => $wpdb->dbname,

			'posts_table' => $wpdb->posts,
			'term_relationships_table' => $wpdb->term_relationships,
			'search_table' => bcms_search()->search_table,
		);

		require __DIR__ . '/templates/config-page.php';

		require __DIR__ . '/templates/test-page.php';
	}
}
