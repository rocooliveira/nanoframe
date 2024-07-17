<?php

namespace App\Config;


class Routes
{
	private $routes;
	
	function __construct()
	{
		$this->define();
	}

	public function getRoutes()
	{
		return $this->routes;
	}

	private function define()
	{
		$this->routes = [
		 'index' 		=> 'HomeController/index',
		];

	}
}