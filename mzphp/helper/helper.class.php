<?php
/**
 * User: djunny
 * Date: 2015-11-27
 * Time: 16:45
 * Mail: 199962760@qq.com
 */

/**
 * Hook Module
 * 使用例子：
 * index.php 中
 * define('HOOK_PATH', ROOT_PATH.'hook/');
 * 在对应目录下，创建一个 hook 目录
 * hook 目录下建立一个 hook_xxx.php
 * class hook_xxx {
 *    static function func($abc = ''){
 *        return '<a>';
 *    }
 * }
 * 此时，清理 tmp 目录后，
 * 在任意代码地方调用：hook('func');
 * 也支持参数传入：hook('func', 'abc');
 * 即调用对应 hook 到所有类包含的 func 静态方法，返回结果为：Array。
 * 循环输出或者在模板中调用 {out_hook(hook('func'), '<Br>')} 即可。
 * 注：hook 可以按文件名顺序来排序，加排序方法：
 *
 * 1.hook_xxx.php
 * 2.hook_aaa.php
 * 3.hook_ccc.php
 *
 * 数字越大，越优先加载, 非特殊情况建议不要使用
 *
 * 如果调用 hook name 途中，需要中止加载其它类的调用。
 * 请在方法中返回：
 * return array(
 *    'HOOK' => HOOK_STOP,
 *    'return' => 'xxxxx', //返回的结果
 * );
 *
 */
define('E_HOOK', -999999);
define('HOOK_STOP', -999998);
// 定义 hook 目录
!defined('HOOK_PATH') && define('HOOK_PATH', ROOT_PATH . 'hook/');
// 定义一个随机数来判断当前 hook runtime 文件是否过期
!defined('HOOK_EXPIRE') && define('HOOK_EXPIRE', 3);


/**
 * load hook cls
 *
 * @param $name
 */
function load_hook_cls($name) {
    static $loaded = array();
    if (DEBUG) {
        if (!isset($loaded[$name])) {
            $loaded[$name] = 1;
            require HOOK_PATH . $name . '.php';
        }
    } else {
        if (!$loaded) {
            $loaded   = 1;
            $run_file = FRAMEWORK_TMP_PATH . 'hook_runtime.php';
            if (!(@include($run_file))) {
                $content = '';
                // make runtime file
                $inc_files = glob(HOOK_PATH . '*.php');
                // load source code
                foreach ($inc_files as $inc_file) {
                    if (strpos($inc_file, 'hook_') !== false) {
                        $content .= php_strip_whitespace($inc_file);
                    }
                }
                //写入 runtime 文件
                file_put_contents($run_file, $content);
                require $run_file;
            }
        }
    }
}

/**
 * get hook
 *
 * @param $name
 *
 * @return mixed
 */
function get_hook($name) {
    static $hook_data = NULL;
    $cache_file = FRAMEWORK_TMP_PATH . 'hook_cache.php';
    if ($hook_data == NULL) {
        $hook_hash = '';
        $file_time = is_file($cache_file) ? filemtime($cache_file) : 0;
        $reload    = $file_time ? 0 : 1;
        if ($file_time) {
            $load_data = json_decode(file_get_contents($cache_file), 1);
            $hook_data = $load_data['data'];
            $hook_time = isset($_SERVER['time']) ? $_SERVER['time'] : time();
            if ($hook_time - $file_time > 3600 && rand(0, HOOK_EXPIRE) == 1) {
                $hook_files = misc::scandir(HOOK_PATH);
                rsort($hook_files, SORT_NUMERIC);
                $hook_hash = array();
                foreach ($hook_files as $hook_file) {
                    if (strpos($hook_file, 'hook_') !== false) {
                        $hook_hash[] = crc32(file_get_contents(HOOK_PATH . $hook_file));
                    }
                }
                $hook_hash = implode('|', $hook_hash);
                if ($load_data['hash'] != $hook_hash) {
                    $reload = true;
                } else {
                    // reset now time
                    touch($cache_file);
                }
            }
        }
        if ($reload) {
            if (!isset($hook_files)) {
                $hook_files = misc::scandir(HOOK_PATH);
                rsort($hook_files, SORT_NUMERIC);
                $hook_hash = array();
                foreach ($hook_files as $hook_file) {
                    if (strpos($hook_file, 'hook_') !== false) {
                        $hook_hash[] = crc32(file_get_contents(HOOK_PATH . $hook_file));
                    }
                }
                $hook_hash = implode('|', $hook_hash);
            }
            $hook_data = array();
            foreach ($hook_files as $file) {
                if (strpos($file, 'hook_') !== false) {
                    // 类名
                    $cls_name = str_replace('.php', '', $file);
                    // 加载类文件
                    load_hook_cls($cls_name);
                    // 去除文件名中用于排序的数字.
                    $cls_name = preg_replace('#^\d+\.#is', '', $cls_name);
                    $methods  = get_class_methods($cls_name);
                    foreach ($methods as $hook) {
                        if ($hook[0] == '_') {
                            // 跳过下划线开头的方法
                            continue;
                        }
                        if (!isset($hook_data[$hook])) {
                            $hook_data[$hook] = array();
                        }
                        $hook_data[$hook][] = $cls_name;
                    }
                }
            }
            $save_data = array(
                'data' => $hook_data,
                'hash' => $hook_hash,
            );
            file_put_contents($cache_file, json_encode($save_data));
        }
    }
    return isset($hook_data[$name]) ? $hook_data[$name] : false;
}

/**
 * run hook
 *
 * @param $name
 *
 * @return array|int
 */
function hook($name) {
    $funcs = get_hook($name);
    if ($funcs) {
        $returns   = array();
        $args      = array_slice(func_get_args(), 1);
        $arg_count = count($args);
        foreach ($funcs as $cls) {
            load_hook_cls($cls);
            switch ($arg_count) {
                case 5:
                    $return = call_user_func($cls . '::' . $name, $args[0], $args[1], $args[2], $args[3], $args[4]);
                    break;
                case 4:
                    $return = call_user_func($cls . '::' . $name, $args[0], $args[1], $args[2], $args[3]);
                    break;
                case 3:
                    $return = call_user_func($cls . '::' . $name, $args[0], $args[1], $args[2]);
                    break;
                case 2:
                    $return = call_user_func($cls . '::' . $name, $args[0], $args[1]);
                    break;
                case 1:
                    $return = call_user_func($cls . '::' . $name, $args[0]);
                    break;
                case 0:
                    $return = call_user_func($cls . '::' . $name);
                    break;
                default:
                    $return = call_user_func($cls . '::' . $name, $args);
            }
            if (is_array($return) && isset($return['HOOK'])) {
                // get hook stop flag
                if ($return['HOOK'] == HOOK_STOP) {
                    $returns[] = $return['return'];
                    break;
                }
            }
            $returns[] = $return;
        }
        return $returns;
    }
    return E_HOOK;
}

/**
 * output  hook result as string
 *
 * @param        $returns
 * @param string $implode
 */
function out_hook($returns, $implode = '') {
    if ($returns == E_HOOK) {
        return '';
    }
    $return = implode($implode, $returns);
    return $return;
}

/**
 * generate origin url or rewrite url
 *
 * @param       $control string control name like 'index' or 'index-index'
 * @param       $action  string action name
 * @param array $params  array other params if control has '-', params will bind action value
 */
function url($control, $action = '', $params = array()) {
    if (strpos($control, '-') === false) {
        $control_action = $control . '-' . $action;
    } else {
        $control_action = $control;
        $params         = $action;
    }
    // http build query support array
    if ($params) {
        $queries = array();
        if (is_array($params)) {
            foreach ($params as $query => $value) {
                if (is_array($value)) {
                    foreach ($value as $key => $val) {
                        if (is_array($val)) {
                            foreach ($val as $son_key => $son_val) {
                                $queries[] = $query . '[' . $key . '][' . $son_key . ']=' . rawurldecode($son_val);
                            }
                        } else {
                            $queries[] = $query . '[' . $key . ']=' . rawurlencode($val);
                        }
                    }
                } else {
                    $queries[] = $query . '=' . rawurlencode($value);
                }
            }
            $queries = '&' . implode('&', $queries);
        } else {
            $queries = ($params[0] == '&' ? '' : '&') . $params;
        }
    }
    if (core::$conf['url_rewrite']) {
        $rewrite_info = core::$conf['rewrite_info'];
        $comma        = $rewrite_info['comma'] ? $rewrite_info['comma'] : '-';
        $extension    = $rewrite_info['ext'];
        return core::rewrite(core::$conf['app_dir'], str_replace('-', $comma, $control_action), $queries, $comma, $extension, 0);
    } else {
        return core::$conf['app_dir'] . '?c=' . $control_action . $queries;
    }
}

?>