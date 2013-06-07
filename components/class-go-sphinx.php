<?php

class GO_Sphinx
{

	public $admin = FALSE;
	public $client = FALSE;
	public $test = FALSE;

	public $admin_cap = 'manage_options';

	public function __construct()
	{
		// the admin settings page
		if ( is_admin() )
		{
			$this->admin();
		}
	}

	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-go-sphinx-admin.php';
			$this->admin = new GO_Sphinx_Admin;
		}

		return $this->admin;
	}

	public function client( $config = array() )
	{
		if ( ! $this->client || ! empty( $config ) )
		{
			require_once __DIR__ . '/externals/sphinxapi.php';
			$this->client = new SphinxClient();

			$config = wp_parse_args( $config, apply_filters( 'go_config', array(
				'server'      => 'localhost',
				'port'        => 9312,
				'timeout'     => 1,
				'arrayresult' => TRUE,
			), 'go-sphinx' ) );

			$this->client->SetServer( $config['server'], $config['port'] );
			$this->client->SetConnectTimeout( $config['timeout'] );
			$this->client->SetArrayResult( $config['arrayresult'] );
		}

		return $this->client;
	}

	public function test()
	{
		if ( ! $this->test )
		{
			//TODO: enable after commits are merged from the two test files
			//require_once __DIR__ . '/class-go-sphinx-test.php';
			//$this->test = new GO_Sphinx_Test;
			require_once __DIR__ . '/class-go-sphinx-test-2.php';
			$this->test = new GO_Sphinx_Test2;			
		}

		return $this->test;
	}

}

/**
 * Singleton
 */
function go_sphinx()
{
	global $go_sphinx;

	if ( ! $go_sphinx )
	{
		$go_sphinx = new GO_Sphinx();
	}//end if

	return $go_sphinx;
}//end go_sphinx