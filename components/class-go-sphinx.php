<?php

class GO_Sphinx
{

	public $client = FALSE;

	public function __construct()
	{
	}

	public function client( $config )
	{
		if ( ! $this->client )
		{
			require_once __DIR__ . '/externals/sphinx.php';

			$this->client = new SphinxClient();
		}

		$config = wp_parse_args( $config, apply_filters( 'go_config', array(
			'server'      => 'localhost',
			'port'        => 9312,
			'timeout'     => 1,
			'arrayresult' => TRUE,
		), 'go-sphinx' ) );

		$this->client->SetServer( $config['host'], $config['port'] );
		$this->client->SetConnectTimeout( $config['timeout'] );
		$this->client->SetArrayResult( $config['arrayresult'] );

		return $this->client;
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