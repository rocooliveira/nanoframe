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


Router::dispatch();

