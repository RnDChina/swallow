<?php
/**
 * Created by PhpStorm.
 * User: jianzi0307
 * Date: 15/7/9
 * Time: 下午10:50
 */

namespace swallow\library;


class Logger
{

    protected $enabled = true;

    protected $writer = null;

    /**
     * 是否启用日志
     * @return bool
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * 设置是否启用日志
     * @param $value
     */
    public function setEnabled($value)
    {
        $this->enabled = $value;
    }

    /**
     * 设置写日志类
     * @param $writer
     */
    public function setWriter($writer)
    {
        $this->writer = $writer;
    }

    /**
     * 严重错误
     * 导致系统崩溃无法使用
     * @param $message
     * @param array $context
     */
    public function emergency($message,array $context=array())
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * 提醒，必须被立即修改的错误
     * 例如：数据库不可用等，应该触发短信提醒并叫醒你
     * @param $message
     * @param array $context
     */
    public function alert($message, array $context = array())
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * 临界值错误
     * 例如：超过临界值的错误，例如一天24小时，而输入的是25小时,应用程序组件不可用，意外异常等；
     * @param $message
     * @param array $context
     */
    public function critical($message, array $context = array())
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * 运行时错误
     * 不需要立即采取行动,但通常应该被记录和监测。
     * @param $message
     * @param array $context
     */
    public function error($message, array $context = array())
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * 警告，并非错误
     * 例如：使用过时的API等
     * @param $message
     * @param array $context
     */
    public function warning($message, array $context = array())
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * 通知
     * 程序可以运行但是还不够完美的错误
     * @param $message
     * @param array $context
     */
    public function notice($message, array $context = array())
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * 感兴趣的事件
     * @param $message
     * @param array $context
     */
    public function info($message, array $context = array())
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * 调试信息
     * @param $message
     * @param array $context
     */
    public function debug($message, array $context = array())
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * 记录任意级别的日志
     * @param $level
     * @param $object
     * @param array $context
     * @return bool
     */
    public function log($level, $object, array $context = array())
    {
        if ($this->enabled && $this->writer) {
            if (is_array($object) || (is_object($object) && !method_exists($object, "__toString"))) {
                $message = print_r($object, true);
            } else {
                $message = (string) $object;
            }
            if (count($context) > 0) {
                if (isset($context['exception']) && $context['exception'] instanceof \Exception) {
                    $message .= ' - ' . $context['exception'];
                    unset($context['exception']);
                }
                $message = $this->interpolate($message, $context);
            }
            return $this->writer->write($message, $level);
        } else {
            return false;
        }
    }

    /**
     * 插值替换
     * @param $message
     * @param array $context
     * @return string
     */
    protected function interpolate($message, $context = array())
    {
        $replace = array();
        foreach ($context as $key => $value) {
            $replace['{' . $key . '}'] = $value;
        }
        return strtr($message, $replace);
    }
}

class LogLevel
{
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';
}