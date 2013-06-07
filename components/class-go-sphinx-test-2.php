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
	}

}//END GO_Sphinx_Test2