<?php
define('BASE_PATH', __DIR__);

ini_set("display_errors","On");
error_reporting(E_ALL);

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/config/config.php';
require BASE_PATH . '/config/routes.php';