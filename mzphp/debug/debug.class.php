<?php

// debug class
class debug {

    public static function init() {
        error_reporting(E_ALL ^ E_DEPRECATED);
        function_exists('ini_set') && ini_set('error_reporting', E_ALL) && ini_set('display_errors', 'ON');
        register_shutdown_function(array('debug', 'shutdown_handler'));    // 程序关闭时执行
        set_error_handler(array('debug', 'error_handler'));    // 设置错误处理方法
        set_exception_handler(array('debug', 'exception_handler'));    // 设置异常处理方法

    }


    /**
     * 程序关闭时执行
     */
    public static function shutdown_handler() {
        if (empty($_SERVER['_exception'])) {
            if ($e = error_get_last()) {
                core::ob_clean();
                $message = $e['message'];
                $file = $e['file'];
                $line = $e['line'];
                if (core::R('ajax')) {
                    if (!DEBUG) {
                        $len = strlen($_SERVER['DOCUMENT_ROOT']);
                        $file = substr($file, $len);
                    }
                    $mz_error = "[error] : $message File: $file [$line]";
                    echo json_encode(array('mz_error' => $mz_error));
                } else {
                    self::sys_error('[error] : ' . $message, $file, $line);
                }
            }
        }
    }


    /**
     * 输出系统错误
     *
     * @param string $message 错误消息
     * @param string $file    错误文件
     * @param int    $line    错误行号
     */
    public static function sys_error($message, $file, $line) {
        include FRAMEWORK_PATH . 'debug/error.php';
    }

    /**
     * exception
     */
    public static function exception_handler($e) {
        DEBUG && $_SERVER['_exception'] = 1;    // 只输出一次

        // 第1步正确定位
        $trace = $e->getTrace();
        if (!empty($trace) && $trace[0]['function'] == 'error_handler' && $trace[0]['class'] == 'debug') {
            $message = $e->getMessage();
            $file = $trace[0]['args'][2];
            $line = $trace[0]['args'][3];
        } else {
            $message = '[exception] : ' . $e->getMessage();
            $file = $e->getFile();
            $line = $e->getLine();
        }
        $message = strip_tags($message);

        // 第2步写日志 (暂不使用 error_log() )
        //log::write("$message File: $file [$line]");

        // 第3步根据情况输出错误信息
        try {
            core::ob_clean();
            self::exception($message, $file, $line, $e->getTraceAsString());
        } catch (Exception $e) {
            echo get_class($e) . " thrown within the exception handler. Message: " . $e->getMessage() . " on line " . $e->getLine();
        }
    }


    /**
     * 错误处理
     *
     * @param string $errno   错误类型
     * @param string $errstr  错误消息
     * @param string $errfile 错误文件
     * @param int    $errline 错误行号
     */
    public static function error_handler($errno, $errstr, $errfile, $errline) {
        if (!empty($_SERVER['exception'])) return;
        // 兼容 php 5.3 以下版本
        defined('E_DEPRECATED') || define('E_DEPRECATED', 8192);
        defined('E_USER_DEPRECATED') || define('E_USER_DEPRECATED', 16384);

        $error_type = array(
            E_ERROR => '运行错误',
            E_WARNING => '运行警告',
            E_PARSE => '语法错误',
            E_NOTICE => '运行通知',
            E_CORE_ERROR => '初始错误',
            E_CORE_WARNING => '初始警告',
            E_COMPILE_ERROR => '编译错误',
            E_COMPILE_WARNING => '编译警告',
            E_USER_ERROR => '用户定义的错误',
            E_USER_WARNING => '用户定义的警告',
            E_USER_NOTICE => '用户定义的通知',
            E_STRICT => '代码标准建议',
            E_RECOVERABLE_ERROR => '致命错误',
            E_DEPRECATED => '代码警告',
            E_USER_DEPRECATED => '用户定义的代码警告',
        );

        $errno_str = isset($error_type[$errno]) ? $error_type[$errno] : '未知错误';
        $s = "[$errno_str] : $errstr";
        // 线上模式放宽一些，只记录日志，不中断程序执行
        if (!in_array($errno, array(E_WARNING, E_NOTICE, E_USER_NOTICE, E_DEPRECATED))) {
            //log::write($s);
            throw new Exception($s);
        }
    }

    public static function process() {
        if (DEBUG || DEBUG_INFO) {
            include FRAMEWORK_PATH . 'debug/trace.php';
        }

    }

    /**
     * 数组转换成HTML代码 (支持双行变色)
     *
     * @param array $arr  一维数组
     * @param int   $type 显示类型
     * @param boot  $html 是否转换为 HTML 实体
     * @return string
     */
    public static function arr2str($arr, $type = 3, $html = TRUE) {
        $s = '';
        $i = 0;
        if (!$arr) {
            return '';
        }
        foreach ($arr as $k => $v) {
            switch ($type) {
                case 0:
                    $k = '';
                    break;
                case 1:
                    $k = "$k ";
                    break;
                case 2:
                    $k = "<span>$k</span>";
                    break;
                default:
                    $k = "$k = ";
            }

            $i++;
            $c = $i % 2 == 0 ? ' class="even"' : '';
            $html && is_string($v) && $v = htmlspecialchars($v);
            if (is_array($v) || is_object($v)) {
                $v = print_r($v, 1);
            }
            $s .= "\r\n<li$c>$k$v</li>";
        }
        return $s;
    }

    /**
     * 输出异常信息
     *
     * @param string $message  异常消息
     * @param string $file     异常文件
     * @param int    $line     异常行号
     * @param string $tracestr 异常追踪信息
     */
    public static function exception($message, $file, $line, $tracestr) {
        if (core::is_cmd()) {
            include FRAMEWORK_PATH . 'debug/exception_cmd.php';
        } else {
            include FRAMEWORK_PATH . 'debug/exception.php';
        }
    }

    /*
    // 异常CODE,用来标示是哪个模块产生的异常。暂时不需要。
    const APP_EXCEPTION_CODE_ERROR_HANDLE = 1;
    const APP_EXCEPTION_CODE_MYSQL = 2;
    const APP_EXCEPTION_CODE_LOG = 3;
    */
    public static function format_exception($e) {
        $trace = $e->getTrace();

        // 如果是 error_handle ，弹出第一个元素。
        if (!empty($trace) && $trace[0]['function'] == 'error_handle' && $trace[0]['class'] == 'core') {
            //array_shift($trace);
            $line = $trace[0]['args'][3];
            $file = $trace[0]['args'][2];
            $message = $trace[0]['args'][1];
        } else {
            $line = $e->getLine();
            $file = $e->getFile();
            $message = $e->getMessage();
        }
        $backtracelist = array();
        foreach ($trace as $k => $v) {
            $args = $comma = '';
            if (!empty($v['args'])) {
                if (DEBUG) {
                    if ($v['function'] == 'error_handle') {
                        $v['class'] = '';
                        $v['function'] = '';
                        $args = '';
                    } else {
                        foreach ((array)$v['args'] as $arg) {
                            if (is_string($arg)) {
                                $args .= $comma . "'$arg'";
                            } elseif (is_object($arg)) {
                                $args .= $comma . "Object";
                            } elseif (is_array($arg)) {
                                // 针对XN 优化
                                if (!isset($arg['db'])) {
                                    $arg = print_r($arg, 1);
                                } else {
                                    $arg = '$conf';
                                }
                                //$arg = str_replace(array("\t", ' '), array('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', '&nbsp;'), $arg);
                                //$arg = nl2br($arg);
                                $args .= $comma . $arg;
                            } else {
                                $args .= $comma . '' . ($arg === NULL ? 'NULL' : $arg);
                            }
                            $comma = ', ';
                        }
                    }
                } else {
                    $args = '';
                }
            }
            !isset($v['file']) && $v['file'] = '';
            !isset($v['line']) && $v['line'] = '';
            !isset($v['function']) && $v['function'] = '';
            !isset($v['class']) && $v['class'] = '';
            !isset($v['type']) && $v['type'] = '';

            $backtracelist[] = array(
                'file' => $v['file'],
                'line' => $v['line'],
                'function' => $v['function'],
                'class' => $v['class'],
                'type' => $v['type'],
                'args' => $args,
            );
        }

        // array_shift($backtracelist);
        // array_shift($backtracelist);

        $codelist = self::get_code($file, $line);

        return array(
            'line' => $line,
            'file' => $file,
            'codelist' => $codelist,
            'message' => $message,
            'backtracelist' => $backtracelist,
        );
    }

    public static function get_code($file, $line) {
        $arr = file($file);
        $arr2 = array_slice($arr, max(0, $line - 5), 10, true);
        if (!core::is_cmd()) {
            /*
            foreach ($arr2 as &$v) {
                $v = htmlspecialchars($v);
                $v = str_replace(' ', '&nbsp;', $v);
                $v = str_replace('	', '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $v);
            }
            */
        }
        return $arr2;
    }

}

?>