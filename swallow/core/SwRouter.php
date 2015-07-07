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


class SwRouter
{
    /**
     * 支持的http请求方法
     * @var array
     */
    private static $HTTP_REQUEST_METHODS = array('GET','POST','PUT','DELETE','OPTIONS','PATCH','HEAD');

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
     * @param $fn
     */
    public function set404($fn)
    {
        $this->notFound = $fn;
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
            $fn = $arguments[1];
            if (in_array(strtoupper($name),self::$HTTP_REQUEST_METHODS)) {
                if ($name == 'any') {
                    $this->match('GET|POST|PUT|DELETE|PATCH|OPTIONS|HEAD',$pattern,$fn);
                } else {
                    $this->match($name, $pattern, $fn);
                }
            }
        }
    }

    /**
     * 匹配路由前预处理
     * @param $methods
     * @param $pattern
     * @param $fn
     */
    public function before($methods,$pattern,$fn)
    {
        $pattern = $this->baseroute . '/' . trim($pattern, '/');
        $pattern = $this->baseroute ? rtrim($pattern, '/') : $pattern;
        foreach (explode('|', $methods) as $method) {
            $this->befores[$method][] = array(
                'pattern' => $pattern,
                'fn' => $fn
            );
        }
    }

    /**
     * 匹配
     * @param $methods
     * @param $pattern
     * @param $fn
     */
    public function match($methods, $pattern, $fn)
    {
        $pattern = $this->baseroute . '/' . trim($pattern, '/');
        $pattern = $this->baseroute ? rtrim($pattern, '/') : $pattern;
        foreach (explode('|', $methods) as $method) {
            $this->routes[$method][] = array(
                'pattern' => $pattern,
                'fn' => $fn
            );
        }
    }

    public function mount($baseroute, $fn)
    {
        $curBaseroute = $this->baseroute;
        $this->baseroute .= $baseroute;
        call_user_func($fn);
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
            $numHandled = $this->handle($this->router[$this->requestMethod]);
        }

        if ($numHandled === 0) {
            //not found
            if ($this->notFound && is_callable($this->notFound)) {
                call_user_func($this->notFound);
            } else {
                header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
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

                call_user_func_array($route['fn'], $params);

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
        if (strstr($uri,'?')) {
            $uri = substr($uri,0,strpos($uri,'?'));
        }
        $uri = '/'.trim($uri,'/');
        return $uri;
    }
}