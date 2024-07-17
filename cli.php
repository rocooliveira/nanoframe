#!/usrc/bin/php
<?php
namespace Nanoframe;

// sobrescreve os limites padrao
set_time_limit(0);
ini_set('memory_limit', '256M');

require __DIR__ . '/app/Config/constants.php';
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Nanoframe\Core\Router;


$dotenv = Dotenv::createImmutable(__DIR__ . '/');
$dotenv->load();


// restringe a chamada direta pelo navegador
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');

// constate para identificacao de origem
define('CMD', 1);

// define manualmente o caminho do URI com base nos argumentos da linha de comando
unset($argv[0]); //exceto o primeiro
$_SERVER['QUERY_STRING'] =  $_SERVER['PATH_INFO'] = $_SERVER['REQUEST_URI'] = '/' . implode('/', $argv) . '/';


Router::cliDispatch();
