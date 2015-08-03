<?php

class core {
    /**
     * config for core
     *
     * @var array
     */
    public static $conf = array();

    /**
     * get variables from GET|POST|COOKIE|REQUEST|SERVER
     *
     * @param        $k
     * @param string $var
     * @return null|string
     */
    public static function gpc($k, $var = 'G') {
        // get param type
        $type = 'str';
        if (strpos($k, ':') !== false) {
            list($k, $type) = explode(':', $k);
        }

        switch ($var) {
            case 'G':
                $var = &$_GET;
                break;
            case 'P':
                $var = &$_POST;
                break;
            case 'C':
                //处理COOKIE
                $k = $_SERVER['cookie_pre'] . $k;
                $var = &$_COOKIE;
                break;
            case 'R':
                $var = isset($_GET[$k]) ? $_GET : (isset($_POST[$k]) ? $_POST : array());
                break;
            case 'S':
                $var = &$_SERVER;
                break;
        }
        if (isset($var[$k])) {
            return $type == 'str' ? $var[$k] : self::get_gpc_value(strtolower($type), $var[$k]);
        } else {
            return NULL;
        }
    }

    /**
     * GET variable
     *
     * @param        $key
     * @param string $default
     * @return null|string
     */
    public static function G($key, $default = '') {
        $val = self::gpc($key, 'G');
        return $val ? $val : $default;
    }

    /**
     * POST variable
     *
     * @param        $key
     * @param string $default
     * @return null|string
     */
    public static function P($key, $default = '') {
        $val = self::gpc($key, 'P');
        return $val ? $val : $default;
    }

    /**
     * get or set Cookie
     *
     * @param            $key
     * @param string     $value
     * @param int        $time
     * @param string     $path
     * @param string     $domain
     * @param bool|FALSE $httponly
     * @return null|string
     */
    public static function C($key, $value = '__GET__', $time = -1, $path = '/', $domain = '', $httponly = FALSE) {
        if ($value === '__GET__') {
            return self::gpc($key, 'C');
        } else {
            $key = $_SERVER['cookie_pre'] . $key;
            //add server time
            if ($time > 0) {
                $time = $_SERVER['time'] + $time;
                $_COOKIE[$key] = $value;
            } else {
                unset($_COOKIE[$key]);
            }

            if (!is_null($domain) && $domain == '' && $_SERVER['cookie_domain']) {
                $domain = $_SERVER['cookie_domain'];
            }

            if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
                setcookie($key, $value, $time, $path, $domain, FALSE, $httponly);
            } else {
                setcookie($key, $value, $time, $path, $domain, FALSE);
            }
        }
    }

    /**
     * REQUEST variable
     *
     * @param        $key
     * @param string $default
     * @return null|string
     */
    public static function R($key, $default = '') {
        $val = self::gpc($key, 'R');
        return $val ? $val : $default;
    }

    /**
     * SERVER
     *
     * @param        $key
     * @param string $default
     * @return null|string
     */
    public static function S($key, $default = '') {
        $val = self::gpc($key, 'S');
        return $val ? $val : $default;
    }

    /**
     * addslashes for object
     *
     * @param $var
     * @return string
     */
    public static function addslashes(&$var) {
        if (is_array($var)) {
            foreach ($var as $k => &$v) {
                self::addslashes($v);
            }
        } else {
            $var = addslashes($var);
        }
        return $var;
    }

    /**
     * stripslashes for object
     *
     * @param $var
     * @return string
     */
    public static function stripslashes(&$var) {
        if (is_array($var)) {
            foreach ($var as $k => &$v) {
                self::stripslashes($v);
            }
        } else {
            $var = stripslashes($var);
        }
        return $var;
    }

    /**
     * htmlspecialchas for object
     *
     * @param $var
     * @return mixed
     */
    public static function htmlspecialchars(&$var) {
        if (is_array($var)) {
            foreach ($var as $k => &$v) {
                self::htmlspecialchars($v);
            }
        } else {
            $var = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $var);
        }
        return $var;
    }

    /**
     * fix urlencode
     *
     * @param $s
     * @return mixed
     */
    public static function urlencode($s) {
        $s = urlencode($s);
        return str_replace('-', '%2D', $s);
    }

    /**
     * fix urldecode
     *
     * @param $s
     * @return string
     */
    public static function urldecode($s) {
        return urldecode($s);
    }

    /**
     * json_decode
     *
     * @param $s
     * @return bool|mixed
     */
    public static function json_decode($s) {
        return $s === FALSE ? FALSE : json_decode($s, 1);
    }

    /**
     * json_encode
     *
     * @param $data
     * @return string
     */
    public static function json_encode($data, $utf8_encode = false) {
        if (is_array($data) || is_object($data)) {
            $is_list = is_array($data) && (empty($data) || array_keys($data) === range(0, count($data) - 1));
            if ($is_list) {
                $json = '[' . implode(',', array_map(array('core', 'json_encode'), $data, $utf8_encode)) . ']';
            } else {
                $items = Array();
                foreach ($data as $key => $value) $items[] = self::json_encode("$key", $utf8_encode) . ':' . self::json_encode($value, $utf8_encode);
                $json = '{' . implode(',', $items) . '}';
            }
        } elseif (is_string($data)) {
            $string = '"' . addcslashes($data, "\\\"\n\r\t/" . chr(8) . chr(12)) . '"';
            $json = '';
            $len = strlen($string);
            for ($i = 0; $i < $len; $i++) {
                $char = $string[$i];
                $c1 = ord($char);
                if ($c1 < 128) {
                    $json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1);
                    continue;
                }
                $json .= $char;
                if ($utf8_encode) {
                    $c2 = ord($string[++$i]);
                    if (($c1 & 32) === 0) {
                        $json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128);
                        continue;
                    }
                    $c3 = ord($string[++$i]);
                    if (($c1 & 16) === 0) {
                        $json .= sprintf("\\u%04x", (($c1 - 224) << 12) + (($c2 - 128) << 6) + ($c3 - 128));
                        continue;
                    }
                    $c4 = ord($string[++$i]);
                    if (($c1 & 8) === 0) {
                        $u = (($c1 & 15) << 2) + (($c2 >> 4) & 3) - 1;
                        $w1 = (54 << 10) + ($u << 6) + (($c2 & 15) << 2) + (($c3 >> 4) & 3);
                        $w2 = (55 << 10) + (($c3 & 15) << 6) + ($c4 - 128);
                        $json .= sprintf("\\u%04x\\u%04x", $w1, $w2);
                    }
                }
            }
        } else {
            $json = strtolower(var_export($data, true));
        }
        return $json;
    }


    /**
     * get ip by format
     *
     * @param int $format
     * @return null|string
     */
    public static function ip($format = 0) {
        static $ip = '';
        if (empty($ip)) {
            $server_addr = self::gpc('REMOTE_ADDR', 'S');
            if (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
                $ip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
                $ip = getenv('REMOTE_ADDR');
            } elseif ($server_addr && strcasecmp($server_addr, 'unknown')) {
                $ip = $server_addr;
            }
            preg_match("/[\d\.]{7,15}/", $ip, $ipmatches);
            $ip = isset($ipmatches[0]) && $ipmatches[0] ? $ipmatches[0] : 'unknown';
            $_SERVER['REMOTE_ADDR'] = &$ip;
            $_SERVER['IP'] = &$ip;
        }
        if ($format) {
            $ips = explode('.', $ip);
            for ($i = 0; $i < 3; $i++) {
                $ips[$i] = intval($ips[$i]);
            }
            return sprintf('%03d%03d%03d', $ips[0], $ips[1], $ips[2]);
        } else {
            return $ip;
        }
    }

    /**
     * init ip
     *
     * @param int $format
     * @return null|string
     */
    public static function init_ip($format = 0) {
        return self::ip($format);
    }

    /**
     * get process time
     *
     * @return string
     */
    public static function usedtime() {
        return number_format(microtime(1) - $_SERVER['starttime'], 6) * 1000;
    }

    /**
     * get memory
     *
     * @return int
     */
    public static function runmem() {
        return memory_get_usage() - $_SERVER['start_memory'];
    }

    /**
     * is cli mode
     *
     * @return bool
     */
    public static function is_cmd() {
        if (php_sapi_name() == 'cli' && (empty($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR'] == 'unknown')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * ob start callback
     *
     * @param $s
     * @return mixed|string
     */
    public static function ob_handle($s) {
        if (!empty($_SERVER['ob_stack'])) {
            $gzipon = array_pop($_SERVER['ob_stack']);
        } else {
            $gzipon = 0;
        }

        //rewrite
        if (self::gpc('str_search', 'S')) {
            $s = str_replace(self::gpc('str_search', 'S'), self::gpc('str_replace', 'S'), $s);
        }

        if (self::gpc('reg_search', 'S')) {
            $s = preg_replace(self::gpc('reg_search', 'S'), self::gpc('reg_replace', 'S'), $s);
        }

        if (self::is_cmd()) {
            return $s;
        }
        $isfirst = count($_SERVER['ob_stack']) == 0;
        if ($gzipon && !ini_get('zlib.output_compression') && function_exists('gzencode') && strpos(self::gpc('HTTP_ACCEPT_ENCODING', 'S'), 'gzip') !== FALSE) {
            $s = gzencode($s, 5); // 0 - 9 级别, 9 最小，最耗费 CPU
            $isfirst && header("Content-Encoding: gzip");
            //$isfirst && header("Vary: Accept-Encoding");	// 下载的时候，IE 6 会直接输出脚本名，而不是文件名！非常诡异！估计是压缩标志混乱。
            $isfirst && header("Content-Length: " . strlen($s));
        } else {
            // PHP 强制发送的 gzip 头
            if (ini_get('zlib.output_compression')) {
                $isfirst && header("Content-Encoding: gzip");
            } else {
                $isfirst && header("Content-Encoding: none");
                $isfirst && header("Content-Length: " . strlen($s));
            }
        }
        return $s;
    }

    /**
     * rewrite replace
     *
     * @param        $pre
     * @param        $para
     * @param string $ds
     * @param string $ext
     * @return string
     */
    public static function rewrite_url($pre, $para, $ds = '_', $ext = '.htm') {
        global $conf;
        if ($pre) {
            $pre .= $ds;
        }
        $para = str_replace(array('&', '='), array($ds, '_'), $para);
        return '<a href="' . $conf['app_dir'] . $pre . $para . $ext . '"';
    }


    /**
     * ob_start
     *
     * @param bool|TRUE $gzip
     */
    public static function ob_start($gzip = TRUE) {
        if ($gzip) {
            !isset($_SERVER['ob_stack']) && $_SERVER['ob_stack'] = array();
            array_push($_SERVER['ob_stack'], $gzip);
        }
        ob_start($gzip ? array('core', 'ob_handle') : 0);
    }

    /**
     * ob end clean
     */
    public static function ob_end_clean() {
        !empty($_SERVER['ob_stack']) && count($_SERVER['ob_stack']) > 0 && ob_end_clean();
    }

    /**
     * ob clean
     */
    public static function ob_clean() {
        !empty($_SERVER['ob_stack']) && count($_SERVER['ob_stack']) > 0 && ob_clean();
    }

    /**
     * init setting
     */
    public static function init_set() {
        //----------------------------------> 全局设置:
        // 错误报告
        if (DEBUG) {
            debug::init();
        } else {
            error_reporting(0);
            //error_reporting(E_ALL ^ E_DEPRECATED);
            //error_reporting(E_ALL & ~(E_NOTICE | E_STRICT));
            //@ini_set('display_errors', 'E_ALL & ~E_NOTICE & ~E_DEPRECATED');
        }

        // 关闭运行期间的自动增加反斜线
        //@set_magic_quotes_runtime(0);
    }

    /**
     * init super variable
     *
     * @param $conf
     */
    public static function init_supevar(&$conf) {
        // 将更多有用的信息放入 $_SERVER 变量
        $_SERVER['starttime'] = microtime(1);
        $starttime = explode(' ', $_SERVER['starttime']);
        $_SERVER['time'] = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : $starttime[1];
        $_SERVER['ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $_SERVER['sqls'] = array();// debug
        $_SERVER['app_url'] = $conf['app_url'];
        $_SERVER['cookie_pre'] = $conf['cookie_pre'];
        $_SERVER['cookie_domain'] = $conf['cookie_domain'];
        if (function_exists('memory_get_usage')) {
            $_SERVER['start_memory'] = memory_get_usage();
        }
        // ajax 判断
        if (isset($_SERVER['X-Requested-With']) && $_SERVER['X-Requested-With']) {
            $_REQUEST['ajax'] = 1;
            $_GET['ajax'] = 1;
            $_POST['ajax'] = 1;
        }
        // 兼容IIS $_SERVER['REQUEST_URI']
        (!isset($_SERVER['REQUEST_URI']) || (isset($_SERVER['HTTP_X_REWRITE_URL']) && $_SERVER['REQUEST_URI'] != $_SERVER['HTTP_X_REWRITE_URL'])) && self::fix_iis_request();

        self::init_get($conf);
    }

    /**
     * init  handler
     */
    public static function init_handle() {
        // 自动 include
        spl_autoload_register(array('core', 'autoload_handle'));

        // 自定义错误处理函数，设置后 error_reporting 将失效。因为要保证 ajax 输出格式，所以必须触发 error_handle
        if (DEBUG || self::gpc('ajax', 'R')) {
            //set_error_handler(array('core', 'error_handle'));
        }
    }

    /**
     * init auto load handler
     *
     * @param $classname
     * @return bool
     * @throws Exception
     */
    public static function autoload_handle($classname) {
        $libclasses = array('db', 'template', 'base_control');
        if (substr($classname, 0, 3) == 'db_') {
            include FRAMEWORK_PATH . 'db/' . $classname . '.class.php';
            return class_exists($classname, false);
        } elseif (substr($classname, 0, 6) == 'cache_') {
            include FRAMEWORK_PATH . 'cache/' . $classname . '.class.php';
            return class_exists($classname, false);

        } elseif (in_array($classname, $libclasses)) {
            include FRAMEWORK_PATH . 'core/' . $classname . '.class.php';
            return class_exists($classname, false);
        } elseif ($classname == 'debug') {
            include FRAMEWORK_PATH . 'debug/debug.class.php';
            return class_exists($classname, false);
        } else {
            global $conf;
            if (!class_exists($classname)) {
                $modelfile = self::model_file($conf, $classname);
                if ($modelfile && is_file($modelfile)) {
                    include_once $modelfile;
                }
            }
            if (!class_exists($classname, false)) {
                throw new Exception('class ' . $classname . ' does not exists');
            }
        }
        return true;
    }

    /**
     * process url rewrite
     *
     * @param $conf
     * @param $s
     */
    public static function process_urlrewrite(&$conf, &$s) {
        if ($conf['url_rewrite']) {
            static $init_replace = 0;
            static $reg_search = array();
            static $reg_replace = array();
            static $str_search = array();
            static $str_replace = array();
            if (!$init_replace) {
                if (isset($conf['str_replace'])) {
                    foreach ($conf['str_replace'] as $k => $v) {
                        $str_search[] = $k;
                        $str_replace[] = $v;
                    }
                }
                if (isset($conf['reg_replace'])) {
                    foreach ($conf['reg_replace'] as $k => $v) {
                        $reg_search[] = $k;
                        $reg_replace[] = $v;
                    }
                }
                $app_dir_regex = preg_quote($conf['app_dir']);
                $init_replace = 1;
            }
            $comma = $conf['rewrite_info']['comma'];

            $reg_search[] = '#\<a href=\"(' . $app_dir_regex . ')?(?:index\.php)?\?c=(\w+)-(\w+)([^"]*?)\"#ie';
            $reg_replace[] = 'core::rewrite("' . $conf['app_dir'] . '", "\\2' . $comma . '\\3", "\\4", "' . $comma . '", "' . $conf['rewrite_info']['ext'] . '")';

            $reg_search[] = '#\<a href=\"(' . $app_dir_regex . ')?(?:index\.php)?\?c=(\w+)([^"]+?)\"#ie';
            $reg_replace[] = 'core::rewrite("' . $conf['app_dir'] . '", "\\2", "\\3", "' . $comma . '", "' . $conf['rewrite_info']['ext'] . '")';

            if ($str_search) {
                $s = str_replace($str_search, $str_replace, $s);
            }

            if ($reg_search) {
                $s = preg_replace($reg_search, $reg_replace, $s);
            }
        }
    }

    /**
     * rewrite url
     *
     * @param        $path
     * @param        $pre
     * @param        $para
     * @param string $ds
     * @param string $ext
     * @return string
     */
    public static function rewrite($path, $pre, $para, $ds = '_', $ext = '.htm') {
        if ($pre) {
            $pre .= $ds;
        }
        if (substr($para, 0, 1) == '&') {
            $para = substr($para, 1);
        }
        //a=
        if (substr($para, 0, 2) == 'a=') {
            $para = substr($para, 2);
        }

        $para = str_replace(array('&', '='), $ds, $para);

        if (!$para) {
            // delete last comma
            $pre = substr($pre, 0, -1);
        }
        return '<a href="' . $path . $pre . $para . $ext . '"';
    }

    /**
     * get paths from $path
     *
     * @param            $path
     * @param bool|FALSE $fullpath
     * @return array
     */
    public static function get_paths($path, $fullpath = FALSE) {
        $arr = array();
        $df = opendir($path);
        while ($dir = readdir($df)) {
            if ($dir == '.' || $dir == '..' || $dir[0] == '.' || !is_dir($path . $dir)) continue;
            $arr[] = $fullpath ? $path . $dir . '/' : $dir;
        }
        sort($arr);// 根据名称从低到高排序
        return $arr;

    }

    /**
     * load model
     *
     * @param        $conf
     * @param        $model
     * @param array  $primarykey
     * @param string $maxcol
     * @return base_model
     * @throws Exception
     * example :
     * self::model($conf, 'userext');
     * self::model($conf, 'userext', 'uid', 'uid');
     */
    public static function model(&$conf, $model, $primarykey = array(), $maxcol = '') {
        if (class_exists($model)) {
            $new = new $model();
            return $new;
        }
        $modelname = 'model_' . $model . '.class.php';
        if (isset($_SERVER['models'][$modelname])) {
            return $_SERVER['models'][$modelname];
        }

        // 隐式加载 model，从配置文件中加载
        if (empty($primarykey)) {
            // 自动配置 model, 不再以来 model/xxx.class.php
            if (isset($conf['model_map'][$model])) {
                $arr = $conf['model_map'][$model];
                $new = new base_model($conf);
                $new->table = $arr[0];
                $new->primarykey = (array)$arr[1];
                $new->maxcol = isset($arr[2]) ? $arr[2] : '';
                $_SERVER['models'][$modelname] = $new;
                return $new;
                // 搜索 model_path, plugin_path
            } else {
                $modelfile = self::model_file($conf, $model);
                if ($modelfile) {
                    include_once $modelfile;
                    $new = new $model($conf);
                    $_SERVER['models'][$modelname] = $new;
                    return $new;
                } else {
                    throw new Exception("Not found model: $model.");
                }
            }
            //throw new Exception("$model 在配置文件中的 model_map 中没有定义过。");
            // 显式加载 model
        } else {
            $new = new base_model($conf);
            $new->table = $model;
            $new->primarykey = (array)$primarykey;
            $new->maxcol = $maxcol;
            $_SERVER['models'][$modelname] = $new;
            return $new;
        }
    }

    /**
     * get model file search model path
     *
     * @param $conf
     * @param $model
     * @return string
     */
    public static function model_file($conf, $model) {
        $modelname = 'model_' . $model . '.class.php';
        //search model file
        $orgfile = '';
        foreach ($conf['model_path'] as &$path) {
            if (is_file($path . $model . '.class.php')) {
                $orgfile = $path . $model . '.class.php';
                break;
            }
        }
        return $orgfile;
    }

    /**
     * init time zone
     *
     * @param array $conf
     */
    public static function init_timezone($conf = array()) {
        // 初始化时区
        // setcookie 的时候，依赖此设置。在浏览器的头中 HTTP HEADER Cookie: xxx, expiry: xxx
        // 这里初始值，后面可以设置正确的值。
        if (!empty($conf['timeoffset'])) {
            $zones = array(
                '-12' => 'Kwajalein',
                '-11' => 'Pacific/Midway',
                '-10' => 'Pacific/Honolulu',
                '-9' => 'America/Anchorage',
                '-8' => 'America/Los_Angeles',
                '-7' => 'America/Denver',
                '-6' => 'America/Tegucigalpa',
                '-5' => 'America/New_York',
                '-4' => 'America/Halifax',
                '-3' => 'America/Sao_Paulo',
                '-2' => 'Atlantic/South_Georgia',
                '-1' => 'Atlantic/Azores',
                '0' => 'Europe/Dublin',
                '+1' => 'Europe/Belgrade',
                '+2' => 'Europe/Minsk',
                '+3' => 'Asia/Tehran',
                '+4' => 'Asia/Muscat',
                '+5' => 'Asia/Katmandu',
                '+6' => 'Asia/Rangoon',
                '+7' => 'Asia/Krasnoyarsk',
                '+8' => 'Asia/Shanghai',
                '+9' => 'Australia/Darwin',
                '+10' => 'Australia/Canberra',
                '+11' => 'Asia/Magadan',
                '+12' => 'Pacific/Fiji',
                '+13' => 'Pacific/Tongatapu',
            );
            // php 5.4 以后，不再支持 Etc/GMT+8 这种格式！
            if (isset($zones[$conf['timeoffset']])) {
                date_default_timezone_set($zones[$conf['timeoffset']]);
            }
        }

    }


    /**
     * init core
     *
     * @param array $conf
     */
    public static function init($conf = array()) {
        // init
        self::init_timezone($conf);
        self::init_supevar($conf);
        self::init_ip();
        self::init_set();
        self::init_handle();
        DB::init_db_config($conf['db']);
        if (isset($conf['cache']) && $conf['cache']) {
            CACHE::init_cache_config($conf['cache']);
        }
        // GPC 安全过滤，关闭，数据的正确性可能会受到影响。
        if (get_magic_quotes_gpc()) {
            self::stripslashes($_GET);
            self::stripslashes($_POST);
            self::stripslashes($_COOKIE);
        }

        if (self::is_cmd()) {
            //flush console output
            ob_implicit_flush(1);
        } else {
            //	header("Expires: 0");
            //	header("Cache-Control: private, post-check=0, pre-check=0, max-age=0");
            //	header("Pragma: no-cache");
            //	header('Content-Type: text/html; charset=UTF-8');
            self::ob_start(isset($conf['gzip']) && $conf['gzip'] ? $conf['gzip'] : false);
        }
    }

    /**
     * debug
     */
    public static function debug() {
        if (self::is_cmd() || core::R('ajax')) return;
        if (defined('NO_DEBUG_INFO')) return;
        //debug
        if (DEBUG || (defined('DEBUG_INFO') && DEBUG_INFO)) {
            debug::process();
        }
    }

    /**
     * core run
     *
     * @param $conf
     * @throws Exception
     */
    public static function run(&$conf) {
        self::init($conf);
        $control = str_replace(array('.', '\\', '/'), '', self::R('c'));
        $obj_file = '';
        // find control file
        foreach ($conf['control_path'] as $control_dir) {
            $tmp_file = $control_dir . $control . '_control.class.php';
            if (is_file($tmp_file)) {
                $obj_file = $tmp_file;
                break;
            }
        }

        if (empty($obj_file)) {
            if ($conf['page_setting'][404]) {
                $conf['page_setting'][404]($control);
            }
            throw new Exception("Invaild URL : {$control} control not exists.");
        }

        if (include $obj_file) {
            $controlclass = "{$control}_control";
            $newcontrol = new $controlclass($conf);
            // control can run hook before on_cation
            $onaction = "on_" . self::G('a');
            if (method_exists($newcontrol, $onaction)) {
                //$newcontrol->$onaction();
                call_user_func(array($newcontrol, $onaction));
                self::debug();
            } else {
                throw new Exception("Invaild URL : $onaction method not exists.");
            }
        } else {
            throw new Exception("Invaild URL : {$control} control file not exists");
        }

        unset($newcontrol, $control, $action);
    }

    /**
     * get gpc value by string type
     *
     * @param $type
     * @param $value
     * @return float|int|string
     */
    private static function get_gpc_value($type, $value) {
        switch ($type) {
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'email':
                return preg_match("/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/", $value) ? $value : '';
            case 'url':
                return preg_match('#^(https?://[^\'"\\\\<>:\s]+(:\d+)?)?([^\'"\\\\<>:\s]+?)*$#is', $value) ? $value : '';
            case 'qq':
                $value = trim($value);
                return preg_match('#^\d+{5,18}$#', $value) ? $value : '';
            case 'tel':
                $value = trim($value);
                return preg_match('#^[\d-]+$#', $value) ? $value : '';
            case 'mobile':
                $value = trim($value);
                return preg_match('#^\d{13}$#', $value) ? $value : '';
            case 'version':
                $value = trim($value);
                return preg_match('#^\d(\.\d+)+$#', $value) ? $value : '';
            default:
                return $value;
        }
    }

    /**
     * fix  IIS  $_SERVER[REQUEST_URI]
     *
     */
    private static function fix_iis_request() {
        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
            $_SERVER['REQUEST_URI'] = &$_SERVER['HTTP_X_REWRITE_URL'];
        } else if (isset($_SERVER['HTTP_REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = &$_SERVER['HTTP_REQUEST_URI'];
        } else {
            if (isset($_SERVER['SCRIPT_NAME'])) {
                $_SERVER['HTTP_REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
            } else {
                $_SERVER['HTTP_REQUEST_URI'] = $_SERVER['PHP_SELF'];
            }
            if (isset($_SERVER['QUERY_STRING'])) {
                $_SERVER['REQUEST_URI'] = '?' . $_SERVER['QUERY_STRING'];
            } else {
                $_SERVER['REQUEST_URI'] = '';
            }
        }
    }

    /**
     * init GET for request_uri or cli mode run
     *
     * @param $conf
     */

    private static function init_get(&$conf) {
        global $argv, $argc;
        $get = &$_GET;
        //parse query string
        $query = explode('?', self::gpc('REQUEST_URI', 'S'), 2);
        if (isset($query[1])) {
            parse_str($query[1], $queryget);
            $get = array_merge($get, $queryget);
        }
        // rewrite varialbles
        if ($conf['url_rewrite'] && isset($get['rewrite'])) {
            //replace last ext
            if ($conf['rewrite_info']['ext']) {
                $get['rewrite'] = preg_replace('#' . preg_quote($conf['rewrite_info']['ext']) . '$#i', '', $get['rewrite']);
            }
            //地址中只会存在一种分隔符，判断地址中，优先级 / _ - 三种分隔符顺序
            //解决了地址中如果分隔符为/，参数可加_的bug
            $url_sp = '';
            if (strpos($get['rewrite'], '/') !== false) {
            } elseif (strpos($get['rewrite'], '_') !== false) {
                $url_sp = '_';
            } elseif (strpos($get['rewrite'], '-') !== false) {
                $url_sp = '-';
            }
            $url_sp && $get['rewrite'] = str_replace($url_sp, '/', $get['rewrite']);
            $get['rewrite'] = preg_replace('/^\//is', '', $get['rewrite']);
            $rws = explode('/', $get['rewrite']);
            if (isset($rws[0])) {
                $rw_count = count($rws);
                for ($rw_i = 0; $rw_i < $rw_count; $rw_i = $rw_i + 2) {
                    $key = $rws[$rw_i];
                    // support url : &arr[query]=1&arr[dateline]=1
                    $pos = strpos($key, '[');
                    if ($pos !== false) {
                        $arr = substr($key, $pos + 1, -1);
                        if ($arr) {
                            $get[substr($key, 0, $pos)][$arr] = $rws[$rw_i + 1];
                        } else {
                            $get[substr($key, 0, $pos)][] = $rws[$rw_i + 1];
                        }
                    } else {
                        $get[$key] = empty($rws[$rw_i + 1]) ? '' : $rws[$rw_i + 1];
                    }
                }
            }
            unset($get['rewrite']);
        }
        // cmd:
        // php index.php "c=index-index&b=2&c=3"
        // php index.php "c=index&a=index&b=2&c=3"
        // php index.php index index
        if ($argc == 2) {
            parse_str($argv[1], $get);
        }
        //fix cmd
        $tmpval = isset($get['c']) ? $get['c'] : (isset($argv[1]) ? $argv[1] : '');
        // switch url mode
        $tmppos = strpos($tmpval, '-');
        if ($tmppos !== false) {
            $tmpact = substr(strstr($tmpval, '-'), 1);
            $tmpval = substr($tmpval, 0, $tmppos);
        } else {
            $tmpact = isset($get['a']) ? $get['a'] : (isset($argv[2]) ? $argv[2] : '');
        }

        $get['c'] = $tmpval && preg_match("/^\w+$/", $tmpval) ? $tmpval : 'index';
        $get['a'] = $tmpact && preg_match("/^\w+$/", $tmpact) ? $tmpact : 'index';
    }
}

class C extends core {
}

;

?>