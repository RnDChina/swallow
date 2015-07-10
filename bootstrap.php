<?php
use swallow\Swallow;

ini_set("display_errors","On");
error_reporting(E_ALL);

define('BASE_PATH', __DIR__);
require BASE_PATH . '/vendor/autoload.php';

$config = require BASE_PATH . '/config/config.php';
Swallow::ins()->setConfig($config);

$router = require BASE_PATH . '/config/routes.php';
Swallow::ins()->setRouter($router);
Swallow::ins()->run();
