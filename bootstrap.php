<?php
use swallow\Swallow;

ini_set("display_errors","On");
error_reporting(E_ALL);

define('BASE_PATH', __DIR__);
define('MODULES_DIR','apps');
require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/config/config.php';

$router = require BASE_PATH . '/config/routes.php';
Swallow::ins()->setRouter($router);
Swallow::ins()->run();
