<?php
namespace Nanoframe\Core;

use \Exception;

// Carrega arquivos utilitarios
class Loader
{

	public static function utils($utilFile)
	{
		$utilsPath = APP_PATH . '/Utils';

		if( !is_array($utilFile) ){
			$utilFile = [$utilFile];
		}

		foreach ($utilFile as $key => $value) {
			$filePath = "$utilsPath/{$value}.php";
			if( file_exists($filePath) ){
				include_once $filePath;
			}else{

				throw new Exception("Arquivo Util não encontrado", 1);
				
			}
		}
	}


	public static function view($view, $data = [], $returnHtmlString  = FALSE)
	{

		$viewPath = APP_PATH . '/View';

		if( ! file_exists("$viewPath/{$view}.php") ){
      http_response_code(404);
      echo "<h1>Erro 404 - View não encontrada</h1>";
      exit;
		}

		extract($data);

		$html = ob_start();
		
		include( "$viewPath/{$view}.php");

		$html = ob_get_contents();

		ob_end_clean();

		if(! $returnHtmlString ) {
			echo $html;
		}else{
			return $html;
		}		
	}


}