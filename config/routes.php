<?php

use swallow\core\Router;

$router = new Router();

$router->setPatterns(array(
    ':num' => '[0-9]+',
    ':string' => '[a-zA-Z]+',
    ':any' => '[^/]+',
    ':all' => '.*'
));

$router->get('/user/:num/:string/:num/',function(){
    echo 'welcome';
});

$router->get('/user/:num/:string/:num/',function(){
    echo 'welcome2';
});

//$router->get('/test','apps/controller/HomeController@index');

//$router->addRoute(['GET','POST'],'/abc',function(){
//   echo "abc";
//});

//$router->any('/([0-9]+)',function($a){
//    echo $a;
//});

$router->run();