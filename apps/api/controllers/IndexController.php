<?php
namespace apps\api\controllers;
/**
 * ----------------------
 * IndexController.php
 * 
 * User: jian0307@icloud.com
 * Date: 2015/7/8
 * Time: 13:39
 * ----------------------
 */

class IndexController {
    public function index() {
        print_r($_GET);exit;
        echo 'api->index->index';
    }
}