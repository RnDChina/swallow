<?php
/**
 * ----------------------
 * Config.php
 * 
 * User: jian0307@icloud.com
 * Date: 2015/7/9
 * Time: 18:05
 * ----------------------
 */

namespace swallow\core;


class Config {

    private static $configs = array();

    public static function get($key)
    {
        if (isset(self::$configs[$key])) {
            return self::$configs[$key];
        }
        return null;
    }

    public static function set($key,$value)
    {
        self::$configs[$key] = $value;
    }
}