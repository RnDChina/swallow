<?php
/**
 * Created by PhpStorm.
 * User: jianzi0307
 * Date: 15/7/9
 * Time: 下午10:50
 */

namespace swallow\core;


class Logger
{

    protected $enabled = true;

    protected $writer = null;

    public function getEnabled()
    {
        return $this->enabled;
    }

    public function setEnabled($value)
    {
        $this->enabled = $value;
    }

    public function setWriter($writer)
    {
        $this->writer = $writer;
    }

    public function emergency($message,array $context=array())
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = array())
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = array())
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = array())
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = array())
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = array())
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = array())
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = array())
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

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
    protected function write($object, $level)
    {
        return $this->log($level, $object);
    }

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