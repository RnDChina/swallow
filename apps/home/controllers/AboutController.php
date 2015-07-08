<?php
/**
 * ----------------------
 * AboutController.php
 * 
 * User: jian0307@icloud.com
 * Date: 2015/7/8
 * Time: 17:51
 * ----------------------
 */

namespace apps\home\controllers;


class AboutController {
    public function index()
    {
        echo "<a href='/home/about/company' target='_blank'>公司简介</a>&nbsp;";
        echo "<a href='/home/about/us' target='_blank'>关于我们</a>&nbsp;";
        echo "<a href='/home/about/contact' target='_blank'>联系我们</a>";
    }

    public function company() {
        echo "公司简介";
    }

    public function us() {
        echo "关于我们";
    }

    public function contact() {
        echo "联系我们";
    }
}