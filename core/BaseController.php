<?php
namespace Nanoframe\Core;

use Nanoframe\Core\Loader;
use Nanoframe\Core\Input;


class BaseController
{
	public $frmValidationData;

	/**
	 * @var Loader
	 */
	public $load;

	/**
	 * @var Input
	 */
	public $input;

	public function __construct()
	{
		$this->load = new Loader();
		$this->input = new Input();

	}



}
