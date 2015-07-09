<?php
namespace apps\home\controllers;

class IndexController extends BaseController {

    protected static $namespaces;
    protected static $_aliases = array();

    public function index() {

        //print_r($_POST);

        //print_r($_GET);exit;

        $namespace = str_replace('\\','.',ltrim('apps\home\controllers\IndexController','\\'));
        echo $namespace;exit;
        if(($path=self::getPathOfAlias($namespace))!==false && is_file($path.'.php')) {
            echo $path.'.php';
        }
        exit;

        $root           =   strstr('apps\home\controllers\IndexController', '\\', true);
        print_r($root);exit;

        self::$namespaces['apps'] = 'apps';
        $root = explode('\\', trim('apps\home\controllers\IndexController', '\\'), 2);
        print_r($root[0]);
        echo "<br/>";
        if (count($root) > 1 and isset(self::$namespaces[$root[0]])) {
            echo self::$namespaces[$root[0]].'/'.str_replace('\\', '/', $root[1]).'.php';
        }
    }

    public function abc() {
        echo "abc";
    }

    public function getIndex()
    {
        echo ";sss";
    }
}