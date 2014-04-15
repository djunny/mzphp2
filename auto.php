<?php

/*
 * Copyright (C) xiuno.com
 */

function gpc($k, $var = 'G') {
	switch($var) {
		case 'G': $var = &$_GET; break;
		case 'P': $var = &$_POST; break;
		case 'C': $var = &$_COOKIE; break;
		case 'R': $var = isset($_GET[$k]) ? $_GET : (isset($_POST[$k]) ? $_POST : $_COOKIE); break;
		case 'S': $var = &$_SERVER; break;
	}
	return isset($var[$k]) ? $var[$k] : NULL;
}

function get_url_path() {
	$port = gpc('SERVER_PORT', 'S');
	$portadd = $port == 80 ? '' : ':80';
	$host = gpc('HTTP_HOST', 'S');
	//$schme = self::gpc('SERVER_PROTOCOL', 'S');
	$path = get_url_abpath();
	return  "http://$host$portadd$path/";
}

function get_url_abpath(){
	return substr(gpc('PHP_SELF', 'S'), 0, strrpos(gpc('PHP_SELF', 'S'), '/'));
}

// 目录名
define('PATH', str_replace('\\', '/', dirname(__FILE__)).'/');
if(is_file(PATH.'core.php')) {
	header('Content-Type: text/html; charset=UTF-8');
	echo '请拷贝此文件到应用目录下，然后通过URL访问此文件。';
	exit;
}

$appname = strrchr(substr(PATH, 0, -1), '/');
$appname = str_replace('.', '_', substr($appname, 1));
$APP_PATH = strtoupper($appname.'_PATH');
$app_url = get_url_path();


$datadir = PATH.'data';
$confdir = PATH.'conf';
$logdir = $datadir.'/log';
$tmpdir = $datadir.'/tmp';
$viewdir = PATH.'view';
$controldir = PATH.'control';
$modeldir = PATH.'model';

!is_dir($datadir) && mkdir($datadir, 0777);
!is_dir($confdir) && mkdir($confdir, 0777);
!is_dir($logdir) && mkdir($logdir, 0777);
!is_dir($tmpdir) && mkdir($tmpdir, 0777);
!is_dir($viewdir) && mkdir($viewdir, 0777);
!is_dir($controldir) && mkdir($controldir, 0777);
!is_dir($modeldir) && mkdir($modeldir, 0777);
	

$conffile = PATH.'conf/conf.php';
$indexfile = PATH.'index.php';
$view_header_file = PATH.'view/header.htm';
$view_index_file = PATH.'view/index_hello.htm';
$view_footer_file = PATH.'view/footer.htm';

if(!is_file($conffile)) {
	$s = "<?php 
 
 
/**************************************************************************************************
 	【注意】：
 		1. 请不要使用 Windows 的记事本编辑此文件！此文件的编码为UTF-8编码，不带有BOM头！
 		2. 建议使用UEStudio, Notepad++ 类编辑器编辑此文件！
***************************************************************************************************/

function get_url_abpath(){
	return substr(\$_SERVER['PHP_SELF'], 0, strrpos(\$_SERVER['PHP_SELF'], '/'));
}
$app_dir = get_url_abpath().'/';
$app_dir_reg = preg_quote($app_dir);
return array(

	// ------------------> 以下为框架依赖:
	// 数据库配置， type 为默认的数据库类型，可以支持多种数据库: mysql|pdo_mysql|pdo_oracle|mongodb	
	'db' => array(				
		'type' => 'mysql',			
		'mysql' => array(			
			'master' => array(								
				'host' => 'localhost',								
				'user' => 'root',				
				'password' => 'root',				
				'name' => 'test',				
				'charset' => 'utf8',				
				'tablepre' => 'bbs_',								
				'engine'=>'MyISAM',
			),			
			'slaves' => array()
		)
	),
	
	// 缓存服务器的配置，支持: memcache|ea|apc|redis，
	// 分布式部署我们建议采用以下两种方案，用来简化程序
	// 1. 局域网内多台 cache server, 本机(127.0.0.1)，写操作通过UDP同步来保持一致性（Memcached UDP组播服务，可能存在安全性问题）。
	// 2. 单台 proxy 管理多台 worker。
	'cache' => array(
		'enable'=>0,
		'type'=>'memcache',
		'memcache'=>array (
			'multi'=>0,
			'host'=>'127.0.0.1',
			'port'=>'11211',
		)
	),
		
	// 唯一识别ID
	'app_id' => '".$appname."',
	
	//网站名称
	'app_name' => '".$appname."',
	
	// 应用的绝对路径： 如: http://www.domain.com/bbs/
	'app_url' => '".get_url_path()."',
	
	// 应用的所在路径： 如: http://www.domain.com/bbs/
	'app_dir' => \$app_dir,
	
	// CDN 缓存的静态域名，如 http://static.domain.com/
	'static_url' => '".get_url_path()."',
	
	// 模板使用的目录，按照顺序搜索，这样可以支持风格切换,结果缓存在 tmp/bbs_xxx.htm.php
	'view_path' => array($APP_PATH.'view/'), 
	
	// 数据模块的路径，按照数组顺序搜索目录
	'model_path' => array($APP_PATH.'model/'),
	
	// 自动加载 model 的配置， 在 model_path 中未找到 modelname 的时候尝试扫描此项, modelname=>array(tablename, primarykey, maxcol)
	'model_map' => array(),
	
	// 业务控制层的路径，按照数组顺序搜索目录，结果缓存在 tmp/bbs_xxx_control.class.php
	'control_path' => array($APP_PATH.'control/'),
	
	// 临时目录，需要可写，可以指定为 linux /dev/shm/ 目录提高速度, 支持 file_put_contents() file_get_contents(), 不支持 fseek(),  SAE: saekv://
	'tmp_path' => $APP_PATH.'data/tmp/',

	// 日志目录，需要可写
	'log_path' => $APP_PATH.'data/log/',
	
	// 服务器所在的时区
	'timeoffset' => '+8',
	
	'plugin_disable'=>0,			// 禁止掉所有插件
	
	'urlrewrite' => 0,			// 手工开启 URL-Rewrite 后，需要清空下 tmp 目录！
	
	'str_replace' => array(),
	
	'reg_replace' => array(
		//'#\<a href=\\\"('.$app_dir_reg.')?(?:index\.php)?\?m=index&id=(\d+)\\\"#ie' => 'core::rewrite(\"\\1\", \"index\", \"\\2\", \"/\", \".htm\")'
	),
);
	";
	
	file_put_contents($conffile, $s);
}

if(!is_file($indexfile)) {
	$s = "<?php

//sae_xhprof_start();

// 调试模式: 0:关闭; 1: 线上调试模式; 2: 本地开发详细调试模式;
define('DEBUG', strstr(\$_SERVER['REQUEST_URI'], 'debug')?1:0);
define('DEBUG_INFO', strstr(\$_SERVER['REQUEST_URI'], 'dinfo')?1:0);
// 有些环境关闭了错误显示
DEBUG && function_exists('ini_set') && @ini_set('display_errors', 'On');
// 站点根目录，在单元测试时候，此文件可能被包含
define('\$APP_PATH', str_replace('\\', '/', dirname(__FILE__)).'/');

if(!($conf = include './conf/conf.php')) {
	exit('config file not exists');
}
// 框架的物理路径
define('FRAMEWORK_PATH', \$APP_PATH.'mzphp/');

// 临时目录
define('FRAMEWORK_TMP_PATH', \$conf['tmp_path']);

// 日志目录
define('FRAMEWORK_LOG_PATH', \$conf['log_path']);

// 包含核心框架文件，转交给框架进行处理。
include FRAMEWORK_PATH.'core.php';

core::init($conf);
core::ob_start();
core::run($conf);

//sae_xhprof_end();
// 完毕


?>";
	file_put_contents($indexfile, $s);
}

if(!is_file($view_header_file)) {
	$s = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Title</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="keywords" content="" />
	<meta name="description" content=" " />
	<meta name="generator" content="" />
	<meta name="author" content="" />
	<meta name="copyright" content="" />
	<meta name="MSSmartTagsPreventParsing" content="True" />
	<meta http-equiv="MSThemeCompatible" content="Yes" />
</head>
<body>
<h3>The Header .</h3>
<hr />
';
	file_put_contents($view_header_file, $s);
}

if(!is_file($view_index_file)) {
	$s = '<!--{include header.htm}-->
	<h1>Hello, world! Hello, $username.</h1>
<!--{include footer.htm}-->';
	file_put_contents($view_index_file, $s);
}

if(!is_file($view_footer_file)) {
	$s = '</body>
</html>
';
	file_put_contents($view_footer_file, $s);
}

$control_index_file = PATH.'control/index_control.class.php';

if(!is_file($control_index_file)) {
	$s = "<?php

!defined('FRAMEWORK_PATH') && exit('FRAMEWORK_PATH not defined.');

class index_control extends base_control {
	
	function __construct(&\$conf) {
		parent::__construct(\$conf);
	}
	
	public function on_hello() {
		
		\$username = 'Jobs';
		\$this->view->assign('username', \$username);
		\$this->view->display('index_hello.htm');
	
	}
}
		
?>";
	
	file_put_contents($control_index_file, $s);
}

$url = $app_url."?index-hello.htm";

echo "应用框架代码生成完毕！请拷贝 xiunophp 到当前目录！然后访问：<a href=\"$url\">$url</a>";

?>