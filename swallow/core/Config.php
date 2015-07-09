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

    /**
     * 获取配置项
     * @param string $key
     * @return null|mixed
     */
    public static function get($key)
    {
        if (isset(self::$configs[$key])) {
            return self::$configs[$key];
        }
        return null;
    }

    /**
     * 设置配置项
     * @param string $key
     * @param mixed $value
     */
    public static function set($key,$value)
    {
        self::$configs[$key] = $value;
    }

    /**
     * 合并配置项
     * @param array $extconfig
     */
    public static function merge($extconfig)
    {
        self::$configs = array_merge(self::$configs,$extconfig);
    }

    private function __contruct(){}
    private function __clone(){}
}