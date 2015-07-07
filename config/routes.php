<?php

use swallow\core\SwRouter as Router;

$router = new Router();

$router->get('/','HomeController@index');

$router->run();