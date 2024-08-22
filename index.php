<?php
namespace Nanoframe;


require __DIR__ . '/app/Config/constants.php';
require __DIR__ . '/vendor/autoload.php';


session_name(SESSION_NAME);
session_start();

use Dotenv\Dotenv;
use Nanoframe\Core\Router;

$dotenv = Dotenv::createImmutable(__DIR__ . '/');
$dotenv->load();


// ------------------------------------------------------------------------
// Configurações de exibição e reporte de erros com base no ambiente
// ------------------------------------------------------------------------
$environment = isset($_ENV['ENVIRONMENT']) ? $_ENV['ENVIRONMENT'] : 'production';

if ($environment === 'development' || $environment === 'staging' || $environment === 'homolog') {
  // Ambiente de desenvolvimento ou homologação
  ini_set('display_errors', '1');
  error_reporting(E_ALL); // Exibir todos os tipos de erros
} else {
  // Ambiente de produção
  ini_set('display_errors', '0');
  error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED); // Suprimir alguns tipos de erros
}
// ------------------------------------------------------------------------


Router::dispatch();

