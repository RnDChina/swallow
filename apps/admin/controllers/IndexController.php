<?php
namespace apps\admin\controllers;
/**
 * ----------------------
 * IndexController.php
 * 
 * User: jian0307@icloud.com
 * Date: 2015/7/9
 * Time: 14:55
 * ----------------------
 */

class IndexController {
    public function index()
    {
        print_r($_POST);

        print_r($_GET);
        echo "admin->index->index";
    }

    public function test()
    {
        echo "admin->index->test";
    }
}