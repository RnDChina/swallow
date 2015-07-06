<?php
/**
 * Swallow - A Lightweight,Open Source PHP Framework For Mobile App
 *
 * @version     1.0.0
 * @package     core
 * @author      Swallow Dev Team
 * @license	http://opensource.org/licenses/MIT	MIT License
 *
 * This content is released under the MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace swallow\core;

class Router
{
    /**
     * 路由列表
     * @var array
     */
    public static $routes = array();

    /**
     * 请求方法类型
     * @var array
     */
    public static $methods = array();

    /**
     * 回调函数
     * @var array
     */
    public static $callbacks = array();

    /**
     * 出错回调函数
     * @var
     */
    public static $error_callback;

    /**
     * 模式匹配
     * @var array
     */
    public static $patterns = array(
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    );

    /**
     * 利用魔法函数 __callstatic特性，当请求的静态函数不存在时，
     * 将请求方法，请求的uri和回调函数压入成员变量数组
     * @param $method
     * @param $params
     */
    public static function __callstatic($method, $params)
    {
        $uri = $params[0];
        $callback = $params[1];

        if (strtolower($method) == 'any') {
            self::pushToArray($uri, 'get', $callback);
            self::pushToArray($uri, 'post', $callback);
        } else {
            self::pushToArray($uri, $method, $callback);
        }
    }

    /**
     * 设置出错回调函数
     * @param $callback
     */
    public static function error($callback)
    {
        self::$error_callback = $callback;
    }

    /**
     * 路由调度处理
     */
    public static function dispatch()
    {
        $uri = self::detectUri();
        $method = $_SERVER['REQUEST_METHOD'];

        $searches = array_keys(static::$patterns);
        $replaces = array_values(static::$patterns);

        $route_is_existed = false;

        if (in_array($uri, self::$routes)) {
            $route_pos = array_keys(self::$routes, $uri);
            foreach ($route_pos as $route) {
                if (self::$methods[$route] == $method) {
                    $route_is_existed = true;
                    if(!is_object(self::$callbacks[$route])){
                        $parts = explode('/',self::$callbacks[$route]);
                        $last = end($parts);
                        $segments = explode('@',$last);
                        $controller = new $segments[0]();
                        $controller->$segments[1]();
                    } else {
                        call_user_func(self::$callbacks[$route]);
                    }
                }
            }
        } else {
            $pos = 0;
            foreach (self::$routes as $route) {
                if (strpos($route, ':') !== false) {
                    $route = str_replace($searches, $replaces, $route);
                }
                if (preg_match("~^" . $route . "$~", $uri, $matched)) {
                    if (self::$methods[$pos] == $method) {
                        $route_is_existed = true;
                        array_shift($matched);
                        if(!is_object(self::$callbacks[$pos])){
                            $parts = explode('/',self::$callbacks[$pos]);
                            $last = end($parts);
                            $segments = explode('@',$last);
                            $controller = new $segments[0]();
                            $controller->$segments[1](implode(",", $matched));
                        } else {
                            call_user_func_array(self::$callbacks[$pos],$matched);
                        }
                    }
                }
                $pos++;
            }
        }

        //未找到路由
        if ($route_is_existed == false) {
            if ( !self::$error_callback ) {
                self::$error_callback = function () {
                    header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
                    echo '<h1>404 Not Found</h1>';
                };
            }
            call_user_func(self::$error_callback);
        }
    }

    private static function pushToArray($uri, $method, $callback)
    {
        array_push(self::$routes, $uri);
        array_push(self::$methods, strtoupper($method));
        array_push(self::$callbacks, $callback);
    }

    private static function detectUri()
    {
        $uri = $_SERVER['REQUEST_URI'];
        if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
            $uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
        } elseif (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0) {
            $uri = substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
        }
        if ($uri == '/' || empty($uri)) {
            return '/';
        }
        $uri = parse_url($uri, PHP_URL_PATH);
        return str_replace(array('//', '../'), '/', trim($uri, '/'));
    }
}