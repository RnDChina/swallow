<?php
use swallow\core\Router;

//路由配置
Router::get('/','HomeController@index');

Router::get('/(:any)','/test@getName');

Router::get('/admin/login',function(){
    echo "管理后台/用户登录";
});

Router::get('/admin/logout',function(){
    echo "管理后台/注销";
});

Router::dispatch();