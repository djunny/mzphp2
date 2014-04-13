<?php

/*
 * XiunoPHP v1.2
 * http://www.xiuno.com/
 *
 * Copyright 2010 (c) axiuno@gmail.com
 * GNU LESSER GENERAL PUBLIC LICENSE Version 3
 * http://www.gnu.org/licenses/lgpl.html
 *
 */

// 静态类，提供各种全局方法
class core {

	/*
	GET|POST|COOKIE|REQUEST|SERVER
	HTML|SAFE
	*/
	public static function gpc($k, $var = 'G') {
		switch($var) {
			case 'G': $var = &$_GET; break;
			case 'P': $var = &$_POST; break;
			case 'C': $var = &$_COOKIE; break;
			case 'R': $var = isset($_GET[$k]) ? $_GET : (isset($_POST[$k]) ? $_POST : $_COOKIE); break;
			case 'S': $var = &$_SERVER; break;
		}
		return isset($var[$k]) ? $var[$k] : NULL;
	}
		
	public static function addslashes(&$var) {
		if(is_array($var)) {
			foreach($var as $k=>&$v) {
				self::addslashes($v);
			}
		} else {
			$var = addslashes($var);
		}
		return $var;
	}
	
	public static function stripslashes(&$var) {
		if(is_array($var)) {
			foreach($var as $k=>&$v) {
				self::stripslashes($v);
			}
		} else {
			$var = stripslashes($var);
		}
		return $var;
	}
	
	public static function htmlspecialchars(&$var) {
		if(is_array($var)) {
			foreach($var as $k=>&$v) {
				self::htmlspecialchars($v);
			}
		} else {
			$var = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $var);
		}
		return $var;
	}
	
	public static function urlencode($s) {
		$s = urlencode($s);
		return str_replace('-', '%2D', $s);
	}
	
	public static function urldecode($s) {
		return urldecode($s);
	}
	
	public static function json_decode($s) {
		return $s === FALSE ? FALSE : json_decode($s, 1);
	}
	
	// 替代 json_encode
	public static function json_encode($data) {
		if(is_array($data) || is_object($data)) {
			$islist = is_array($data) && (empty($data) || array_keys($data) === range(0,count($data)-1));
			if($islist) {
				$json = '['.implode(',', array_map(array('core', 'json_encode'), $data)).']';
			} else {
				$items = Array();
				foreach($data as $key => $value) $items[] = self::json_encode("$key").':'.self::json_encode($value);
				$json = '{'.implode(',', $items).'}';
			}
		} elseif(is_string($data)) {
			$string = '"'.addcslashes($data, "\\\"\n\r\t/".chr(8).chr(12)).'"';
			$json   = '';
			$len    = strlen($string);
			for($i = 0; $i < $len; $i++ ) {
				$char = $string[$i];
				$c1   = ord($char);
				if($c1 <128 ) { 
					$json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1);
					continue;
				}
				$c2 = ord($string[++$i]);
				if (($c1 & 32) === 0) {
					$json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128);
					continue;
				}
				$c3 = ord($string[++$i]);
				if(($c1 & 16) === 0) {
					$json .= sprintf("\\u%04x", (($c1 - 224) <<12) + (($c2 - 128) << 6) + ($c3 - 128));
					continue;
				}
				$c4 = ord($string[++$i]);
				if(($c1 & 8 ) === 0) {
					$u = (($c1 & 15) << 2) + (($c2>>4) & 3) - 1;
					$w1 = (54<<10) + ($u<<6) + (($c2 & 15) << 2) + (($c3>>4) & 3);
					$w2 = (55<<10) + (($c3 & 15)<<6) + ($c4-128);
					$json .= sprintf("\\u%04x\\u%04x", $w1, $w2);
				}
			}
		}  else {
			$json = strtolower(var_export( $data, true ));
		}
		return $json;
	}
	
	
	//获取在线IP
	public function init_ip($format=0) {
		static $ip = '';	
		if(empty($ip)) {
			$serveraddr = core::gpc('REMOTE_ADDR', 'S');
			if(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
				$ip = getenv('HTTP_X_FORWARDED_FOR');
			} elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
				$ip = getenv('REMOTE_ADDR');
			} elseif($serveraddr && strcasecmp($serveraddr, 'unknown')) {
				$ip = $serveraddr;
			}
			preg_match("/[\d\.]{7,15}/", $ip, $ipmatches);
			$ip = $ipmatches[0] ? $ipmatches[0] : 'unknown';
			$_SERVER['REMOTE_ADDR'] = &$ip;
			$_SERVER['IP'] = &$ip;
		}
		if($format) {
			$ips = explode('.', $ip);
			for($i=0;$i<3;$i++) {
				$ips[$i] = intval($ips[$i]);
			}
			return sprintf('%03d%03d%03d', $ips[0], $ips[1], $ips[2]);
		} else {
			return $ip;
		}
	}

	public static function usedtime(){
		return number_format(microtime(1) - $_SERVER['starttime'], 6) * 1000;
	}

	// 是否为命令行模式
	public static function is_cmd() { 
		if(php_sapi_name() == 'cli' && (empty($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR'] == 'unknown')) {
			return true;
     	} else {
			return false;
     	}
	}
	
	public static function ob_handle($s) {
		if(!empty($_SERVER['ob_stack'])) {
			$gzipon = array_pop($_SERVER['ob_stack']);
		} else {
			// throw new Exception('');
			$gzipon = 0;	
		}
		//rewrite
		if(core::gpc('str_search','S')){
			$s = str_replace(core::gpc('str_search','S'), core::gpc('str_replace','S'), $s);
		}
		
		if(core::gpc('reg_search','S')){
			$s = preg_replace(core::gpc('reg_search','S'), core::gpc('reg_replace','S'), $s);
		}
		
		$isfirst = count($_SERVER['ob_stack']) == 0;
		if($gzipon && !ini_get('zlib.output_compression') && function_exists('gzencode') && strpos(core::gpc('HTTP_ACCEPT_ENCODING', 'S'), 'gzip') !== FALSE) {
			$s = gzencode($s, 5);   		// 0 - 9 级别, 9 最小，最耗费 CPU 
			$isfirst && header("Content-Encoding: gzip");
			//$isfirst && header("Vary: Accept-Encoding");	// 下载的时候，IE 6 会直接输出脚本名，而不是文件名！非常诡异！估计是压缩标志混乱。
			$isfirst && header("Content-Length: ".strlen($s));
		} else {
			// PHP 强制发送的 gzip 头
			if(ini_get('zlib.output_compression')) {
				$isfirst && header("Content-Encoding: gzip");
			} else {
				$isfirst && header("Content-Encoding: none");
	       			$isfirst && header("Content-Length: ".strlen($s));
			}
		}
		return $s;
	}
	
	
	//替换回调
	public static function rewrite_url($pre, $para, $ds = '_', $ext = '.htm'){
		global $conf;
		if($pre){
			$pre .= $ds;
		}
		$para = str_replace(array('&','='), array($ds, '_'), $para);
		return '<a href="'.$conf['app_dir'].$pre.$para.$ext.'"';
	}

	public static function ob_start($gzip = TRUE) {
		!isset($_SERVER['ob_stack']) && $_SERVER['ob_stack'] = array();
		array_push($_SERVER['ob_stack'], $gzip);
		ob_start(array('core', 'ob_handle'));
	}
	
	public static function ob_end_clean() {
		!empty($_SERVER['ob_stack']) && count($_SERVER['ob_stack']) > 0 && ob_end_clean();
	}
	
	public static function ob_clean() {
		!empty($_SERVER['ob_stack']) && count($_SERVER['ob_stack']) > 0 && ob_clean();
	}
	
	public static function init_set() {
		//----------------------------------> 全局设置:
		// 错误报告
		if(DEBUG) {
			// E_ALL | E_STRICT
			error_reporting(E_ALL | E_STRICT);
			//error_reporting(E_ALL ^ E_NOTICE);
			ini_set('error_reporting', E_ALL | E_STRICT);
			//error_reporting(E_ALL & ~(E_NOTICE | E_STRICT));
			@ini_set('display_errors', 'ON');
		} else {
			error_reporting(E_ALL ^ E_NOTICE);
		}
		
		// 关闭运行期间的自动增加反斜线
		@set_magic_quotes_runtime(0);
	}

	public static function init_supevar() {
		// 将更多有用的信息放入 $_SERVER 变量
		$_SERVER['starttime'] = microtime(1);
		$_SERVER['time'] = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
		$_SERVER['ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$_SERVER['sqls'] = array();// debug
		
		// 兼容IIS $_SERVER['REQUEST_URI']
		(!isset($_SERVER['REQUEST_URI']) || (isset($_SERVER['HTTP_X_REWRITE_URL']) && $_SERVER['REQUEST_URI'] != $_SERVER['HTTP_X_REWRITE_URL'])) && self::fix_iis_request();
		
		// 重新初始化 $_GET
		//$_GET = array();
		self::init_get();
	}
	
	public static function init_handle() {
		// 自动 include
		spl_autoload_register(array('core', 'autoload_handle'));
		
		// 异常处理类
		//set_exception_handler(array('core', 'exception_handle'));
		
		// 自定义错误处理函数，设置后 error_reporting 将失效。因为要保证 ajax 输出格式，所以必须触发 error_handle
		//if(DEBUG || core::gpc('ajax', 'R')) {
			set_error_handler(array('core', 'error_handle'));
		//}
		
	}
	
	// new class 不存在，触发
	public static function autoload_handle($classname) {
		$libclasses = ' check, log, form, utf8, image, template, ';
		if(substr($classname, 0, 3) == 'db_') {
			include FRAMEWORK_PATH.'db/'.$classname.'.class.php';
			return class_exists($classname, false);
		} elseif(substr($classname, 0, 6) == 'cache_') {
			include FRAMEWORK_PATH.'cache/'.$classname.'.class.php';
			return class_exists($classname, false);
		} elseif(strpos($libclasses, ' '.$classname.', ') !== FALSE || substr($classname, 0, 3) == 'xn_') {
			include FRAMEWORK_PATH.'lib/'.$classname.'.class.php';
			return class_exists($classname, false);
		} else {
			// 在未 include xxx.class.php 直接 new xxx() 的时候，会跳转到此处。只是在特殊场合（如升级程序）方便使用。
			// model 调用 model ，采用 core::model($conf, $modelname) 调用，正确的传递 $conf;
			global $conf;
			if(!class_exists($classname)) {
				$modelfile = core::model_file($conf, $classname);
				if($modelfile && is_file($modelfile)) {
					include_once $modelfile;
				}
			}
			if(!class_exists($classname, false)) {
				throw new Exception('class '.$classname.' does not exists');
			}
		}
		return true;
	}
	
	public static function exception_handle($e) {
		
		// 避免死循环
		DEBUG && $_SERVER['exception'] = 1;
		
		core::ob_clean();
		
		print_r($e);exit;
		
		log::write($e->getMessage().' File: '.$e->getFile().' ['.$e->getLine().']');
		
		$s = '';
		if(DEBUG) {
			try {
				if(self::gpc('ajax', 'R')) {
					$s = xn_exception::to_json($e);
				} else {
					//!core::is_cmd() && header('Content-Type: text/html; charset=UTF-8');
					$s = xn_exception::to_html($e);
				}
			} catch (Exception $e) {
				$s = get_class($e)." thrown within the exception handler. Message: ".$e->getMessage()." on line ".$e->getLine();
			}
		} else {
			if(self::gpc('ajax', 'R')) {
				$s = core::json_encode(array('servererror'=>$e->getMessage()));
			} else {
				$s = $e->getMessage();
			}
		}
		
		echo $s;
		exit;
	}
	
	public static function console_log($msg){
		trigger_error ('['.date('h:i:a').'] - '.print_r($msg), E_USER_NOTICE );
	}
	
	public static function error_handle($errno, $errstr, $errfile, $errline) {
		
		// 防止死循环
		$errortype = array (
			E_ERROR	      => 'Error',
			E_WARNING	    => 'Warning',
			E_PARSE	      => 'Parsing Error',	# uncatchable
			E_NOTICE	     => 'Notice',
			E_CORE_ERROR	 => 'Core Error',		# uncatchable
			E_CORE_WARNING       => 'Core Warning',		# uncatchable
			E_COMPILE_ERROR      => 'Compile Error',	# uncatchable
			E_COMPILE_WARNING    => 'Compile Warning',	# uncatchable
			E_USER_ERROR	 => 'User Error',
			E_USER_WARNING       => 'User Warning',
			E_USER_NOTICE	=> 'User Notice',
			E_STRICT	     => 'Runtime Notice',
			//E_RECOVERABLE_ERRROR => 'Catchable Fatal Error'
		);
		
		$errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
	
		// 运行时致命错误，直接退出。并且 debug_backtrace()
		$s = "[$errnostr] : $errstr in File $errfile, Line: $errline";
		
		// 抛出异常，记录到日志
		//echo $errstr;
		if(DEBUG && empty($_SERVER['exception'])) {
			throw new Exception($s);
		} else {
			log::write($s);
			//$s = preg_replace('# \S*[/\\\\](.+?\.php)#', ' \\1', $s);
			if(self::gpc('ajax', 'R')) {
				core::ob_clean();
				//$s = preg_replace('#[\\x80-\\xff]{2}#', '?', $s);// 替换掉 gbk， 否则 json_encode 会报错！
				// 判断错误级别，决定是否退出。
				
				if($errno != E_NOTICE && $errno != E_USER_ERROR && $errno != E_USER_NOTICE && $errno != E_USER_WARNING) {
					$s = self::json_encode(array('servererror'=>$s));
					throw new Exception($s);
					exit;
				} else {
					$_SERVER['notice_error'] .= $s;
					// 继续执行。
				}
			} else {
				//echo $s;
				// 继续执行。
			}
		}
		return 0;
	}
	
	/**
	 * 修正 IIS  $_SERVER[REQUEST_URI]
	 *
	 */
	private static function fix_iis_request() {
		if(isset($_SERVER['HTTP_X_REWRITE_URL'])) {
			$_SERVER['REQUEST_URI'] = &$_SERVER['HTTP_X_REWRITE_URL'];
		} else if(isset($_SERVER['HTTP_REQUEST_URI'])) {
			$_SERVER['REQUEST_URI'] = &$_SERVER['HTTP_REQUEST_URI'];
		} else {
			if(isset($_SERVER['SCRIPT_NAME'])) {
				$_SERVER['HTTP_REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
			} else {
				$_SERVER['HTTP_REQUEST_URI'] = $_SERVER['PHP_SELF'];
			}
			if(isset($_SERVER['QUERY_STRING'])) {
				$_SERVER['REQUEST_URI'] = '?' . $_SERVER['QUERY_STRING'];
			} else {
				$_SERVER['REQUEST_URI'] = '';
			}
		}
	}
	
	/**
	 * URL 隐射，结果保存到 $_GET
	 */
	private static function init_get() {
		global $argv;
		$get = &$_GET;
		//parse query string
		$query = explode('?', self::gpc('REQUEST_URI', 'S'), 2);
		if(isset($query[1])){
			parse_str($query[1], $queryget);
			$get = array_merge($get, $queryget);
		}
		//write rewrite 
		if(isset($get['rewrite'])) {
			$get['rewrite'] = str_replace(array('\\','//','-','_'), '/', $get['rewrite']);
			$get['rewrite'] = preg_replace('/^\//is','', $get['rewrite']);
			$rws = explode('/', $get['rewrite']);
			if(isset($rws[0])) {
				$rw_count = count($rws);
				for ($rw_i=0; $rw_i<$rw_count; $rw_i=$rw_i+2) {
					$get[$rws[$rw_i]] = empty($rws[$rw_i+1]) ? '' : $rws[$rw_i+1];
				}
			}
			unset($get['rewrite']);
		}
		//fix cmd 
		$tmpval = isset($get['c']) ? $get['c'] : (isset($argv[1]) ? $argv[1] : '');
		$get['c'] = $tmpval && preg_match("/^\w+$/", $tmpval) ? $tmpval : 'index';
		
		$tmpval = isset($get['a']) ? $get['a'] : (isset($argv[2]) ? $argv[2] : '');
		$get['a'] = $tmpval && preg_match("/^\w+$/", $tmpval) ? $tmpval : 'index';
		
		
	}
	
	public static function process_hook(&$conf, $hookfile) {
		$s = '';
		// 遍历插件目录，如果有该 hook
		$plugins = core::get_enable_plugins($conf);
		$pluginnames = array_keys($plugins);
		foreach($pluginnames as $v) {
			$path = $conf['plugin_path'].$v;
			if(!is_file($path.'/'.$hookfile)) continue;
			if(empty($plugins[$v])) continue; // 特殊情况
			
			$s2 = file_get_contents($path.'/'.$hookfile);
			
			// 去掉第一行 
			$s2 = preg_replace('#^<\?php\s*exit;\?>\s{0,2}#i', '', $s2);
			if(substr($s2, 0, 5) == '<?php' && substr($s2, -2, 2) == '?>') {
				$s2 = substr($s2, 5, -2);		
			}
			/*$s2 = preg_replace('#^\s*<\?php(.*?)\?>\s*$#ism', '\\1', $s2);*/
			
			$s .= $s2;
		}
		
		core::process_urlrewrite($conf, $s);
		
		return $s;
	}
	
	public static function process_hook_callback($matchs) {
		$s = $matchs[1];
		return self::process_hook($_SERVER['preg_replace_callback_arg'], $s);
	}
	
	
	public static function process_urlrewrite(&$conf, &$s) {
		if($conf['urlrewrite']) {
			static $init_replace = 0;
			static $reg_search  = array();
			static $reg_replace = array();
			static $str_search = array();
			static $str_replace = array();	
			if(!$init_replace){
			 	if(isset($conf['str_replace'])){
					foreach($conf['str_replace'] as $k=>$v){
						$str_search[] = $k;
						$str_replace[] = $v;
					}
				}
			 	if(isset($conf['reg_replace'])){
					foreach($conf['reg_replace'] as $k=>$v){
						$reg_search[] = $k;
						$reg_replace[] = $v;
					}
				}
				$init_replace = 1;
			}
			if($str_search){
				$s = str_replace($str_search, $str_replace, $s);
			}
			if($reg_search){
				$s = preg_replace($reg_search, $reg_replace, $s);
			}
		}
	}
	
	//替换回调
	/*Options 
RewriteEngine On


RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^.*(\.css|\.js|\.gif|\.png|\.jpg|\.jpeg)$


RewriteRule ^index/(\d+)\.htm$ index.php?m=index&a=index&id=$1 [L]
	*/
	public static function rewrite($path, $pre, $para, $ds = '_', $ext = '.htm'){
		if($pre){
			$pre .= $ds;
		}
		$para = str_replace(array('&','='), array($ds, '_'), $para);
		return '<a href="'.$path.$pre.$para.$ext.'"';
	}
	
	// 对于包含的目标文件进行处理，生成 bbs_common_control.class.php 
	// 约定 include BBS_PATH.'xxx/xxx.php'; 这样的格式。避免 eval() 解析。
	public static function process_include(&$conf, &$s) {
		preg_match_all('#[\r\n]{1,2}\s*include\s+(\w+)\.[\'"]([^;]+)[\'"];#is', $s, $m);
		if(!empty($m[1])) {
			foreach($m[1] as $k=>$path) {
				$realpath = constant($m[1][$k]).$m[2][$k];
				$file = 'control_'.basename($m[2][$k]); // include 为公共部分，不加 app_id 区分，$conf['app_id'].
				$tmpfile = $conf['tmp_path'].$file;
				$tmptmpfile = FRAMEWORK_TMP_TMP_PATH.$file;
				$s2 = file_get_contents($realpath);
				// 需要 php5.3 以后才支持匿名函数: function($matchs) use ($conf) {}
				// 这里不得不用全局变量来解决这个问题
				$_SERVER['preg_replace_callback_arg'] = $conf;
				$s2 = preg_replace_callback('#\t*\/\/\s*hook\s+([^\s]+)#is', 'core::process_hook_callback', $s2);
				core::process_urlrewrite($conf, $s2);
				file_put_contents($tmptmpfile, $s2);
				$s = str_replace($m[0][$k], "\r\n\tinclude '$tmpfile';", $s);
			}
		}
		
		// 直接包含内容会加速PHP，但是代码阅读性差，不利于调试。
		//$s = preg_replace('#\r\ninclude\s+\'(\S+)\';\s*\r\n#ies', "file_get_contents('\\2')", $s);
		//$s = preg_replace('#\r\ninclude\s+(\w+)\.\'(\S+)\';\s*\r\n#ies', "substr(file_get_contents(constant('\\1').'\\2'), 5, -2)", $s);	// 直接包含内容，可以加速 include，要求必须 < ? p h p   ? > 的文档格式
		
		return $s;
	}
	
	// 获取已经开启的 plugin, ，专门用来扫描插件目录
	public static function get_plugins(&$conf, $force = 0) {
		// 缓存结果
		static $plugins = array();
		if(!empty($plugins) && !$force) return $plugins;
		
		if(empty($conf['plugin_path'])) return array();
		$path = $conf['plugin_path'];
		if(!is_dir($path)) return array();
		$settingfile = $conf['upload_path'].'plugin.json';
		$setting = is_file($settingfile) ? (array)core::json_decode(file_get_contents($settingfile)) : array();
		
		$arr = self::get_paths($path);
		foreach($arr as $v) {
			$conffile = $path.$v.'/conf.php';
			$pconf = is_file($conffile) ? (array)include($conffile) : array();
			!isset($pconf['enable']) && $pconf['enable'] = isset($setting[$v]['enable']) ? $setting[$v]['enable'] : 0;
			!isset($pconf['installed']) && $pconf['installed'] = isset($setting[$v]['installed']) ? $setting[$v]['installed'] : 0;
			!isset($pconf['pluginid']) && $pconf['pluginid'] = isset($setting[$v]['pluginid']) ? $setting[$v]['pluginid'] : 0;
			!isset($pconf['rank']) && $pconf['rank'] = isset($setting[$v]['rank']) ? $setting[$v]['rank'] : 100; // 按照正序排序
			$plugins[$v] = $pconf;
		}
		//第二次根据 rank 排序
		misc::arrlist_multisort($plugins, 'rank');
		
		return $plugins;
	}
	
	public static function get_enable_plugins(&$conf, $force = 0) {
		$plugins = core::get_plugins($conf, $force);
		
		static $enable_plugins = array();
		if(!empty($enable_plugins) && !$force) return $enable_plugins;
		
		foreach($plugins as $k=>$plugin) {
			if(!empty($plugin['installed']) && !empty($plugin['enable'])) {
				$enable_plugins[$k] = $plugin;
			}
		}
		return $enable_plugins;
	}

	public static function get_paths($path, $fullpath = FALSE) {
		$arr = array();
		$df = opendir($path);
		while($dir = readdir($df)) {
			if($dir == '.' || $dir == '..' || $dir[0] == '.' || !is_dir($path.$dir)) continue;
			$arr[] = $fullpath ? $path.$dir.'/' : $dir;
		}
		sort($arr);// 根据名称从低到高排序
		return $arr;
		
	}

	/*
		加载 model：
		$muser = core::model($conf, 'userext');				// 隐式加载 model，从配置文件中加载
		$muser = core::model($conf, 'userext', 'uid', 'uid');		// 显式加载 model，不需要配置文件中申明
	*/
	public static function model(&$conf, $model, $primarykey = array(), $maxcol = '') {
		$modelname = 'model_'.$model.'.class.php';
		if(isset($_SERVER['models'][$modelname])) {
			return $_SERVER['models'][$modelname];
		}
		
		// 隐式加载 model，从配置文件中加载
		if(empty($primarykey)) {
			// 自动配置 model, 不再以来 model/xxx.class.php
			if(isset($conf['model_map'][$model])) {
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
				if($modelfile) {
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
	
	public static function model_file($conf, $model) {
		$modelname = 'model_'.$model.'.class.php';
		$modelfile = $conf['tmp_path'].$modelname;
		if((!is_file($modelfile) || DEBUG > 1) && !IN_SAE) {
			// 开始从以下路径查找 model： plugin/*/ , model/, 
			$orgfile = '';
			if(empty($conf['plugin_disable'])) {
				$plugins = self::get_enable_plugins($conf);
				$pluginnames = array_keys($plugins);
				foreach($pluginnames as &$v) {
					$path = $conf['plugin_path'].$v.'/';
					if(is_file($path.$conf['app_id'].'/'."$model.class.php")) {
						$orgfile = $path.$conf['app_id'].'/'."$model.class.php";
						break;
					}
					if(is_file($path."$model.class.php")) {
						$orgfile = $path."$model.class.php";
						break;
					}
				}
			}
			if(empty($orgfile)) {
				foreach($conf['model_path'] as &$path) {
					if(is_file($path.$model.'.class.php')) {
						$orgfile = $path.$model.'.class.php';
						break;
					}
				}
			}
			if(empty($orgfile)) {
				return FALSE;
			}
			$s = file_get_contents($orgfile);
			$_SERVER['preg_replace_callback_arg'] = $conf;
			$s = preg_replace_callback('#\t*\/\/\s*hook\s+([^\s]+)#is', 'core::process_hook_callback', $s);
			file_put_contents($modelfile, $s);
		}
		return $modelfile;
	}
	
	public static function init_timezone($conf = array()) {
		// 初始化时区
		// setcookie 的时候，依赖此设置。在浏览器的头中 HTTP HEADER Cookie: xxx, expiry: xxx
		// 这里初始值，后面可以设置正确的值。
		if(!empty($conf['timeoffset'])) {
			$zones = array (
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
			if(isset($zones[$conf['timeoffset']])) {
				date_default_timezone_set($zones[$conf['timeoffset']]);
			}
		}
		
	}
		
	public static function init($conf = array()) {
		// ---------------------> 初始化
		core::init_timezone($conf);
		core::init_supevar();
		core::init_ip();
		core::init_set();
		core::init_handle();
		
		
		// GPC 安全过滤，关闭，数据的正确性可能会受到影响。
		if(get_magic_quotes_gpc()) {
			core::stripslashes($_GET);
			core::stripslashes($_POST);
			core::stripslashes($_COOKIE);
		}
		
		// 如果非命令行，则输出 header 头
		if(!core::is_cmd()) {
			
			header("Expires: 0");
			header("Cache-Control: private, post-check=0, pre-check=0, max-age=0");
			header("Pragma: no-cache");
			header('Content-Type: text/html; charset=UTF-8');
			//header('X-Powered-By: XiunoPHP;'); // 隐藏 PHP 版本 X-Powered-By: PHP/5.2.5
		}
		
	}
	
	public static function run(&$conf) {
		
		//---------------------------------->  包含相关的 control，并实例化
		$control = core::gpc('c');
		$action = core::gpc('a');
		
		$objfile = $conf['tmp_path'].$conf['app_id']."_control_{$control}_control.class.php";
		
		// 如果缓存文件不存在，则搜索目录
		if(!is_file($objfile) || (DEBUG > 0 && !IN_SAE)) {
			$controlfile = '';
			if(empty($conf['plugin_disable'])) {
				$plugins = core::get_enable_plugins($conf);
				$pluginnames = array_keys($plugins);
				foreach($pluginnames as $v) {
					// 如果有相关的 app path, 这只读取该目录, plugin/xxx/abc_control.class.php, plugin/xxx/admin/abc_control.class.php
					$path = $conf['plugin_path'].$v.'/';
					if(is_file($path.$conf['app_id'].'/'."{$control}_control.class.php")) {
						$controlfile = $path.$conf['app_id'].'/'."{$control}_control.class.php";
						break;
					}
					if(is_file($path."{$control}_control.class.php")) {
						$controlfile =  $path."{$control}_control.class.php";
						break;
					} else {
						$controlfile = '';
					}
				}
			}
			if(empty($controlfile)) {
				$paths = $conf['control_path'];
				foreach($paths as $path) {
					$controlfile = $path."{$control}_control.class.php";
					if(is_file($controlfile)) {
						break;
					} else {
						$controlfile = '';
					}
				}
			}
			if(empty($controlfile)) {
				throw new Exception("Invaild URL : {$control} control not exists.");
			}
			
			// 处理 hook  urlrewrite, static_url
			if(!is_file($controlfile)) {
				throw new Exception("Invaild URL : {$control} control not exists.");
			}
			$s = file_get_contents($controlfile);
			core::process_include($conf, $s);
			$_SERVER['preg_replace_callback_arg'] = $conf;
			$s = preg_replace_callback('#\t*\/\/\s*hook\s+([^\s]+)#is', 'core::process_hook_callback', $s);

			core::process_urlrewrite($conf, $s);
			file_put_contents($objfile, $s);
		}
		
		if(include $objfile) {
			$controlclass = "{$control}_control";
			$onaction = "on_$action";
			
			$newcontrol = new $controlclass($conf);
			
			if(method_exists($newcontrol, $onaction)) {
				
				$newcontrol->$onaction();
			} else {
				throw new Exception("Invaild URL : $action method not exists.");
			}
		} else {
			throw new Exception("Invaild URL : {$control} control file not exists");
		}
		unset($newcontrol, $control, $action);
	}
}

?>