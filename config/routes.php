<?php
use swallow\core\Router;

$router = new Router();
/*
//设置路由参数
$router->setPatterns(array(
    ':num' => '[0-9]+',
    ':string' => '[a-zA-Z]+',
    ':any' => '[^/]+',
    ':all' => '.*'
))

->get('/user/:num/:string/:num/',function(){
    echo 'welcome';
})

->get('/user/:num/:string/:num/',function(){
    echo 'welcome2';
})

->get('/test/:num','apps\home\controllers\IndexController@index')

//路由分组
->group('/about',function() use ($router) {
    $router->get('/company',function() {
        echo "公司简介";
    });

    $router->get('/picture',function() {
        echo "员工风采";
    });

    $router->group('/desktop',function() use ($router) {
        $router->get('/my/(:string)/(:num)',function($name,$id){
            echo "我的桌面>".$name.">".$id;
        });
    });
})

//自定义支持的http方法
->addRoute(['GET','POST'],'/abc',function(){
    print_r($_SERVER['PATH_INFO']);
   echo "abc";
})

//支持任意http方法
->any('/([0-9]+)',function($a){
    echo $a;
});
*/
return $router;
