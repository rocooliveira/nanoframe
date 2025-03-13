<?php

namespace Nanoframe\Core;

use App\Config\Routes;


class Router 
{
  public function __construct()
  {

  }

  public static function dispatch()
  {

    $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/index';
    $pathInfo = substr($pathInfo, 1);

    $method = $_SERVER['REQUEST_METHOD'];

    $routes = new Routes;

    foreach ($routes->getRoutes() as $routePattern => $handler) {

      list($route, $allowedMethods) = SELF::parseRoute($routePattern);

      $regex = self::buildRegex($route);


      if (preg_match($regex, $pathInfo, $matches) && in_array($method, $allowedMethods)) {
        array_shift($matches);

        $handlerParts = explode('/', $handler);

        $method = array_pop($handlerParts);

        self::callControllerMethod($handlerParts, $method, $matches);

        return;
      }
    }

    http_response_code(404);
    echo "<h1>Erro 404 - Página não encontrada</h1>";
    exit;
  }


  public static function cliDispatch()
  {
      global $argv;

      // Se não houver argumentos, exiba uma mensagem de erro
      if (empty($argv)) {
          echo "Erro: Nenhum argumento fornecido para a linha de comando.\n";
          exit;
      }

      // O primeiro argumento é a rota, os próximos são os parâmetros
      $pathInfo = explode( '/',  array_shift($argv) );

      $controllerMethod = array_shift($argv);

      $params = $argv;

      self::callControllerMethod($pathInfo, $controllerMethod, $params, TRUE);
      
  }


  public static function buildRegex($routePattern)
  {
    // Processa grupos opcionais primeiro
    $regex = preg_replace_callback(
      '/\((.*?)\)\?/', 
      function ($matches) {
        // Substitui placeholders DENTRO do grupo opcional
        $inner = preg_replace('/:num/', '(\d+)', $matches[1]);
        $inner = preg_replace('/:any/', '(.+)', $inner);
        // Preserva padrões como \b (ex: \b(edit|delete)\b)
        $inner = str_replace(['\b', '\B'], ['\\b', '\\B'], $inner);
        return '(?:' . $inner . ')?';
      }, 
      $routePattern
    );

    // Substitui placeholders FORA de grupos opcionais
    $regex = preg_replace('/\(:num\)/', '(\d+)', $regex);
    $regex = preg_replace('/\(:any\)/', '(.+)', $regex);

    // Escapa barras e ajustar a regex final
    $regex = preg_replace('/\//', '\/', $regex);
    $regex = '/^' . $regex . '\/?$/';

    return $regex;
  }

  public static function callControllerMethod($handlerParts, $method, $params, $isCli = FALSE)
  {
    $path = implode('/', $handlerParts);

    $namespace = "";

    // Verifica se há uma notação especial no namespace
    if (preg_match('/\[(.+?)\]/', $path, $matches)) {
      $namespaceParts = explode('/', trim($matches[1], '/'));
      $className = array_pop($namespaceParts);
      $namespace =  "\\" . implode('\\', $namespaceParts);


      $pathUtil = self::getUriPart($path);
      
      $filePath = APP_PATH . "/Controller/{$pathUtil}/{$className}.php";

    } else {
        $filePath = APP_PATH . "/Controller/{$path}.php";
        $className = array_pop($handlerParts);


    }

    
    $classWithNamespace = !$isCli 
    ? "App\\Controller{$namespace}\\{$className}"
    : "App\\Controller\\Command{$namespace}\\{$className}";


    if (file_exists($filePath)) {
      include_once $filePath;

    }

    if (class_exists($classWithNamespace)) {


      $instance = new $classWithNamespace();
    } else {
      http_response_code(503);
      echo "<h1>Erro 503 - Rota não encontrada</h1>";
      exit;
    }

    if (method_exists($instance, $method)) {
      call_user_func_array([$instance, $method], $params);
    } else {
      http_response_code(503);
      echo "<h1>Erro 503 - Rota não encontrada</h1>";
      exit;
    }
  }


  private static function getUriPart($string)
  {
    // Encontrar todas as partes dentro dos colchetes
    preg_match_all("/\[([^\]]*)\]/", $string, $matches);

    // Substituir as partes dentro dos colchetes por marcadores temporários
    $placeholder = '__TEMP_PLACEHOLDER__';
    $replacement = preg_replace("/\[([^\]]*)\]/", $placeholder, $string);

    // Dividir a string usando "/"
    $parts = explode('/', $replacement);

    // Restaurar as partes dentro dos colchetes usando os marcadores temporários
    $parts = preg_replace_callback("/$placeholder/", function () use ($matches) {
        return array_shift($matches[0]);
    }, $parts);

    // Remover marcadores temporários
    $parts = array_map(function ($part) use ($placeholder) {
        return str_replace($placeholder, '', $part);
    }, $parts);

    // Remover elementos vazios do array resultante
    $parts = array_filter($parts);

    array_pop($parts);

    $pathUtil = implode(DIRECTORY_SEPARATOR, $parts);

    return $pathUtil;
  }

  protected static function parseRoute($routePattern)
  {
      // Verifica se há métodos HTTP definidos na rota
      preg_match('/(.+)\[(.+)\]/', $routePattern, $matches);

      if (count($matches) === 3) {
          $route = trim($matches[1]);
          $allowedMethods = explode(',', strtoupper($matches[2]));
      } else {
          $route = trim($routePattern);
          $allowedMethods = ['GET']; // método padrão
      }

      return [$route, $allowedMethods];
  }
  
}
