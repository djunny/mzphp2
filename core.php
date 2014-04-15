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

/*
	说明：
	 	该文件为框架入口，加载了核心框架类，初始化了基础数据，并且根据$_GET参数的请求，自动加载了相关文件，实例化运行。
	 	可以在命令行下运行。
	 	依赖于以下参数，测试的时候需要模拟以下参数:
	环境变量：
	 	$_SERVER
	 	$_GET
	常量：
	 	FRAMEWORK_PATH
	 	FRAMEWORK_TMP_PATH
	 	FRAMEWORK_LOG_PATH
*/

//----------------------------------> 依赖关系检查:

if(strstr($_SERVER['REQUEST_URI'], 'clean')){
	$rmfiles = glob(FRAMEWORK_TMP_PATH.'*');
    foreach ($rmfiles as $g) {
        !is_dir($g) && unlink($g);
    }
}

if(!defined('DEBUG')) {
	define('DEBUG', 1);
}

// 是否在Sina云平台
define('IN_SAE', class_exists('SaeKV'));

if(!defined('FRAMEWORK_PATH')) {
	define('FRAMEWORK_PATH', './');
}

// 临时目录
if(!defined('FRAMEWORK_TMP_PATH')) {
	define('FRAMEWORK_TMP_PATH', './');
}

// 临时目录，仅会话期间有效
if(!defined('FRAMEWORK_TMP_TMP_PATH')) {
	define('FRAMEWORK_TMP_TMP_PATH', IN_SAE ? SAE_TMP_PATH : FRAMEWORK_TMP_PATH);
}

if(!defined('FRAMEWORK_LOG_PATH')) {
	define('FRAMEWORK_LOG_PATH', './');
}

// runtime file
if(DEBUG > 0) {
	
	// 包含基础的类：初始化相关
	include FRAMEWORK_PATH.'core/core.class.php';
	
	// 相对 core.class.php 不太常用的静态方法
	include FRAMEWORK_PATH.'core/misc.class.php';
	
	// 包含基础的类：初始化相关
	include FRAMEWORK_PATH.'core/base_control.class.php';
	
	// 包含基础数据模型
	include FRAMEWORK_PATH.'core/base_model.class.php';
	// debug class
	include FRAMEWORK_PATH.'core/debug.class.php';
	
	// 日志，在需要的时候会被包含
	include FRAMEWORK_PATH.'lib/log.class.php';
	
	// 异常处理，依赖于 log，在需要的时候会被包含
	include FRAMEWORK_PATH.'lib/xn_exception.class.php';
	
	// 包含加密解密库
	include FRAMEWORK_PATH.'lib/encrypt.func.php';
	
	// 模板
	include FRAMEWORK_PATH.'lib/template.class.php';
	// seo
	include FRAMEWORK_PATH.'lib/seo.class.php';
	
	// db
	include FRAMEWORK_PATH.'db/db.interface.php';
	include FRAMEWORK_PATH.'db/db_mysql.class.php';
	
	// cache
	include FRAMEWORK_PATH.'cache/cache.interface.php';
	include FRAMEWORK_PATH.'cache/cache_memcache.class.php';
	
} else {
	// 语义同上段，优先读取应用定义的目录下的 runtime 文件
	$content = '';
	$runtimefile = FRAMEWORK_TMP_PATH.'_runtime.php';
	if (!is_file($runtimefile)) {
		// 最低版本需求判断
		PHP_VERSION < '5.0' && exit('Required PHP version 5.0.* or later.');
		
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'core/core.class.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'core/misc.class.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'core/base_control.class.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'core/base_model.class.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'core/debug.class.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'lib/log.class.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'lib/xn_exception.class.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'lib/encrypt.func.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'lib/template.class.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'lib/seo.class.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'db/db.interface.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'db/db_mysql.class.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'cache/cache.interface.php');
		$content .= php_strip_whitespace(FRAMEWORK_PATH.'cache/cache_memcache.class.php');
		file_put_contents($runtimefile, $content);
		unset($content);
	}
	include $runtimefile;
}

?>