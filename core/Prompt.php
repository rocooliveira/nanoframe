<?php
namespace Nanoframe\Core;

class Prompt
{
	
	public function printSuccess($str)
	{
		echo "\033[32m" . $str ."\033[0m" .  PHP_EOL;
	}

	public function printWarning($str)
	{
		
		echo "\033[33m" . $str ."\033[0m" .  PHP_EOL;
	}

	public function printDanger($str)
	{
		echo "\033[31m" . $str ."\033[0m" .  PHP_EOL;
	}


	public function readLine($str, $outputType = 'default')
	{

		switch ($outputType) {
			case 'success':
				echo "\033[32m" . $str ."\033[0m";
				break;

			case 'warning':
				echo "\033[33m" . $str ."\033[0m";
				break;

			case 'danger':
				echo "\033[31m" . $str ."\033[0m";
				break;
			
			default:
				echo $str;
				break;
		}
		

		return strtolower(trim(fgets(STDIN)));
	}
}