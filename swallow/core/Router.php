<?php
/**
 * ----------------------
 * SwRouter.php
 * 
 * User: jian0307@icloud.com
 * Date: 2015/7/7
 * Time: 9:50
 * ----------------------
 */

namespace swallow\core;


class Router
{
    /**
     * 支持的http请求方法
     * @var array
     */
    private static $HTTP_REQUEST_METHODS = array('GET','POST','PUT','DELETE','OPTIONS','PATCH','HEAD');

    /**
     * 路由参数
     * @var array
     */
    private $patterns = array();

    /*array(
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    );*/

    /**
     * 路由列表
     * @var array
     */
    private $router = array();

    /**
     * @var array
     */
    private $befores = array();

    /**
     * 路由未发现
     * @var
     */
    protected $notFound;

    /**
     * 基路由
     * @var string
     */
    private $baseroute = '';

    /**
     * 当前的http方法
     * @var
     */
    private $requestMethod;

    /**
     * 设置404处理函数
     * @param $action
     */
    public function set404($action)
    {
        $this->notFound = $action;
    }

    /**
     * 利用魔法函数 __call特性，当请求的函数不存在时，
     * 将请求方法，请求的方法类型，匹配规则，回调函数压入routes数组
     * @param string $name 请求的方法名
     * @param array $arguments 请求的方法参数
     */
    public function __call($name, $arguments)
    {
        if (is_array($arguments) && count($arguments) > 1) {
            $pattern = $arguments[0];
            $action = $arguments[1];
            if (in_array(strtoupper($name),self::$HTTP_REQUEST_METHODS)) {
                $this->addRoute($name, $pattern, $action);
            } else {
                if ($name == 'any') {
                    $this->addRoute(self::$HTTP_REQUEST_METHODS,$pattern,$action);
                }
            }
        }
    }

    /**
     * 定义路由参数
     * @param $key
     * @param $value
     */
    public function setPattern($key,$value)
    {
        $this->patterns[$key] = $value;
    }

    /**
     * 批量定义路由参数
     * @param array $patterns
     */
    public function setPatterns($patterns)
    {
        foreach ($patterns as $key => $pattern) {
            $this->setPattern($key, $pattern);
        }
    }

    /**
     * 匹配路由前预处理
     * @param array $requestMethods HTTP请求方法 GET/POST/PUT/DELETE/PATCH/OPTIONS/HEAD
     * @param string $pattern 匹配规则
     * @param string $action 处理函数
     */
    public function before($requestMethods,$pattern,$action)
    {
        $requestMethods = is_array($requestMethods) ? $requestMethods : [$requestMethods];
        $pattern = $this->parsePatten($pattern);
        foreach ($requestMethods as $method) {
            $this->befores[$method][] = array(
                'pattern' => $pattern,
                'action' => $action
            );
        }
    }

    /**
     * 匹配
     * @param array $requestMethods HTTP请求方法 GET/POST/PUT/DELETE/PATCH/OPTIONS/HEAD
     * @param string $pattern 匹配规则
     * @param string $action 处理函数
     */
    public function addRoute($requestMethods, $pattern, $action)
    {
        $requestMethods = is_array($requestMethods) ? $requestMethods : [$requestMethods];
        $pattern = $this->parsePatten($pattern);
        foreach ($requestMethods as $method) {
            $method = strtoupper($method);
            if (!isset($this->router[$method])) {
                $this->router[$method] = array();
            }
            array_push($this->router[$method],array(
                'pattern' => $pattern,
                'action' => $action
            ));
        }
    }

    public function mount($baseroute, $action)
    {
        $curBaseroute = $this->baseroute;
        $this->baseroute .= $baseroute;
        call_user_func($action);
        $this->baseroute = $curBaseroute;
    }

    /**
     * 获取请求头
     * @return array
     */
    public function getRequestHeaders()
    {
        if (function_exists(getallheaders)) {
            return getallheaders();
        }

        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    /**
     * 获取请求方法
     * @return string
     */
    public function getRequestMethod()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_start();
            $method = 'GET';
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $header = $this->getRequestHeaders();
            if (
                isset($headers['X-HTTP-Method-Override']) &&
                in_array($headers['X-HTTP-Method-Override'], array('PUT', 'DELETE', 'PATCH'))
            ) {
                $method = $headers['X-HTTP-Method-Override'];
            }
        }
        return $method;
    }

    /**
     * 执行路由调度
     * @param object|callable $callback
     * @return bool
     */
    public function run($callback = null)
    {
        $this->requestMethod = $this->getRequestMethod();

        if (isset($this->befores[$this->requestMethod])) {
            $this->handle($this->befores[$this->requestMethod]);
        }

        $numHandled = 0;
        if ( isset($this->router[$this->requestMethod]) ) {
            $numHandled = $this->handle($this->router[$this->requestMethod],true);
        }

        if ($numHandled === 0) {
            //not found
            if ($this->notFound && is_callable($this->notFound)) {
                call_user_func($this->notFound);
            } else {
                header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
                echo "404 Not Found.";die;
            }
        } else {
            if ($callback != null) {
                $callback();
            }
        }

        if ( $this->requestMethod == 'HEAD') {
           ob_end_clean();
        }

        if ( $numHandled === 0 ) {
            return false;
        }
        return true;
    }

    private function parsePatten($pattern)
    {
        if (strpos($pattern,':') !== false) {
            $keys = array_keys($this->patterns);
            $values = array_values($this->patterns);
            $pattern = str_replace($keys,$values,$pattern);
        }
        $pattern = $this->baseroute . '/' . trim($pattern, '/');
        $pattern = $this->baseroute ? rtrim($pattern, '/') : $pattern;
        return $pattern;
    }

    /**
     * 匹配路由
     * @param $routes
     * @param bool $quitAfterRun 匹配规则后不再继续
     * @return int
     */
    private function handle($routes, $quitAfterRun = false)
    {
        $numHandled = 0;
        $uri = $this->getCurrentUri();
        foreach ($routes as $route) {
            if (preg_match_all('~^'. $route['pattern'] .'$~',$uri,$matches,PREG_OFFSET_CAPTURE)) {
                $matches = array_slice($matches, 1);

                $params = array_map(function ($match, $index) use ($matches) {
                    if (
                        isset($matches[$index+1]) &&
                        isset($matches[$index+1][0]) &&
                        is_array($matches[$index+1][0]
                    )) {
                        return trim(substr($match[0][0], 0, $matches[$index+1][0][1] - $match[0][1]), '/');
                    } else {
                        return (isset($match[0][0]) ? trim($match[0][0], '/') : null);
                    }
                }, $matches, array_keys($matches));

                if (is_object($route['action']) && is_callable($route['action'])) {
                    call_user_func_array($route['action'], $params);
                } else {
                    $parts = explode('//',$route['action']);
                    $last = end($parts);
                    $segments = explode('@',$last);
                    $controller = new $segments[0]();
                    $controller->$segments[1]();
                }

                $numHandled++;

                if ($quitAfterRun) {
                    break;
                }
            }
        }

        return $numHandled;
    }

    private function getCurrentUri()
    {
        $basepath = implode('/',array_slice(explode('/',$_SERVER['SCRIPT_NAME']),0,-1)) . '/';
        $uri = substr($_SERVER['REQUEST_URI'],strlen($basepath));
        print_r($uri);exit;
        if (strstr($uri,'?')) {
            $uri = substr($uri,0,strpos($uri,'?'));
        }
        $uri = '/'.trim($uri,'/');
        return $uri;
    }
}