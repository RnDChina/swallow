<?php
/**
 * ----------------------
 * Swallow.php
 * 
 * User: jian0307@icloud.com
 * Date: 2015/7/8
 * Time: 16:13
 * ----------------------
 */
namespace swallow;

use swallow\core\Router;

class Swallow
{
    /**
     * @var
     */
    public $config;

    /**
     * @var
     */
    public $loader;

    /**
     * @var
     */
    public $router;

    private static $instance = null;

    public static function ins()
    {
        if (self::$instance == null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * 启动框架
     */
    public function run()
    {
        if (!$this->router) {
            $this->router = new Router();
        }
        $this->startMvc();
        $this->router->run();
    }

    /**
     * @param $router
     */
    public function setRouter($router)
    {
        $this->router = $router;
    }

    /**
     * 启动mvc框架
     */
    public function startMvc()
    {
        $routerMvc = function ($module=null, $controller=null, $action=null) {
            $module = empty($module) ? 'home' : $module;
            $controller = empty($controller) ? 'index' : $controller;
            $action = empty($action) ? 'index' : $action;

            if (file_exists(BASE_PATH.'/apps/'.$module)) {
                $c = BASE_PATH.'/apps/'.$module.'/controllers/'.ucwords($controller).'Controller.php';
                if( file_exists($c) && is_file($c) ) {
                    require $c;
                    $cls = strstr(basename($c),'.',true);
                    $cls = 'apps\\'.$module.'\\controllers\\'.$cls;
                    $cins = new $cls();
                    if (is_callable([$cins,$action])) {
                        call_user_func_array(array($cins,$action),[]);
                    } else {
                        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
                        echo "404 Not Found.";die;
                    }
                } else {
                    echo $c ." handler class cannot be loaded";
                }
            } else {
                echo "module '".$module."' is not exist.";
            }
        };
        $this->router->get('/',$routerMvc);
        $this->router->get('/(:string)',$routerMvc);
        $this->router->get('/(:string)/(:string)',$routerMvc);
        $this->router->get('/(:string)/(:string)/(:string)',$routerMvc);
    }

    private function __constuct(){}
    private function __clone(){}
}