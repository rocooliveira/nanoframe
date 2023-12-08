<?php

namespace Nanoframe\Core;


class Input
{


	public function __construct()
	{
		// $this->processGlobalInput();
	}

	/**
	 * @param  string|array|null  $index  indice a ser buscado (string)
	 * @param  boolean $clearData 				aplica filtros de XSS e outros filtros basicos
	 * @return array
	 */
	public function post($index = NULL, $clearData = TRUE)
	{
		return $this->getRequestData($_POST, $index, $clearData);
	}

	/**
	 * @param  string|array|null  $index	indice a ser buscado (string)
	 * @param  boolean $clearData 				aplica filtros de XSS e outros filtros basicos
	 * @return array
	 */
	public function get($index = NULL, $clearData = TRUE)
	{
		return $this->getRequestData($_GET, $index, $clearData);
	}


	/**
	 * @param  string|array|null  $index	indice a ser buscado (string)
	 * @param  boolean $clearData 				aplica filtros de XSS e outros filtros basicos
	 * @return array
	 */
	public function getPost($index = NULL, $clearData = TRUE)
	{
		$requestData = !empty($_GET) ? $_GET : $_POST;

		return $this->getRequestData($requestData, $index, $clearData);
	}


	/**
	 * @param  string|array|null  $index	indice a ser buscado (string)
	 * @param  boolean $clearData 				aplica filtros de XSS e outros filtros basicos
	 * @return array
	 */
	public function inputStream($index = NULL, $clearData = TRUE)
	{
		if ( ! in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'DELETE', 'PATCH']) ) {
			return [];
		}

		parse_str(file_get_contents('php://input'), $requestData);

		return $this->getRequestData($requestData, $index, $clearData);
	}


	/**
	 * @param  string|array|null  $index	indice a ser buscado (string)
	 * @param  boolean $clearData 				aplica filtros de XSS e outros filtros basicos
	 * @return array
	 */
	public function cookie($index = NULL, $clearData = TRUE)
	{
		return $this->getRequestData($_COOKIE, $index, $clearData);
	}


	/**
	 * @param  string|array|null  $index	indice a ser buscado (string)
	 * @param  boolean $clearData 				aplica filtros de XSS e outros filtros basicos
	 * @return array
	 */
	public function server($index = NULL, $clearData = TRUE)
	{
		return $this->getRequestData($_SERVER, $index, $clearData);
	}



	private function getRequestData($request, $index = NULL, $clearData = TRUE)
	{

		$data = $clearData ? self::sanitizeArray($request) : $request;

		if( ! $index ) return $data;

		if(! is_array($index) ){
			$index = [$index];
		}

		return array_filter($data, function($item, $key) use ($index){
			return in_array($key, $index);
		}, ARRAY_FILTER_USE_BOTH);

	}


	private static function sanitizeArray($array)
	{
    // Implementação básica de sanitização
		$sanitizedArray = array_map('strip_tags', $array);
		$sanitizedArray = array_map('htmlspecialchars', $sanitizedArray);

    // Remover caracteres invisíveis
    $sanitizedArray = array_map('self::removeInvisibleCharacters', $sanitizedArray);

    // Prevenir SQL Injection (verificação básica)
		$sanitizedArray = array_map('self::preventSqlInjection', $sanitizedArray);

		return $sanitizedArray;
	}

  private static function removeInvisibleCharacters($value)
  {
    // Remover caracteres invisíveis
    return preg_replace('/[\p{C}]/u', '', $value);
  }

	private static function preventSqlInjection($value)
	{
    // Implementação básica para prevenir SQL Injection
		if (is_numeric($value)) {
			return $value;
		} else {
			return addslashes($value);
		}
	}
}


// Agora, os dados de entrada foram pré-processados com medidas básicas de segurança.
