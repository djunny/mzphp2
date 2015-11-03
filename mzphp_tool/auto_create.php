<?php
/*
	@author : djunny
	useage :
	
	1、create a new directory(创建一个新目录)
	2、move this file in new directory(将本文件移至新目录)
	3、open browser and request this file(打开浏览器运行本文件)
	
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


function show_message($msg){
	header('Content-Type: text/html; charset=UTF-8');
	echo $msg;
	//echo '请创建一个新目录，将本文件拷至新目录，然后通过URL访问此文件。';
	exit;
}

function get_url_abpath(){
	return substr(gpc('PHP_SELF', 'S'), 0, strrpos(gpc('PHP_SELF', 'S'), '/'));
}

// 目录名
define('PATH', str_replace('\\', '/', dirname(__FILE__)).'/');
define('ROOT_PATH', preg_replace('#\/([\w\-]+)\/$#i', '/', PATH));
preg_match_all('#\/([\w\-]+)\/$#i', PATH, $dir);
$dir = str_replace('', '', $dir[1][0]);
define('APP_NAME', $dir);

if(!is_file(PATH.'../mzphp/mzphp.php')) {
	show_message('没有找到 mzphp 目录，请确定应用和 mzphp 框架是在同一级目录');
}


if(!APP_NAME){
	show_message('错误的目录名，请重试');
}

if(APP_NAME == 'mzphp_tool') {
	show_message('请创建一个新目录，将本文件拷至新目录，然后通过URL访问此文件。');
}

$appname = APP_NAME;
$APP_PATH = strtoupper('ROOT_PATH');
$app_url = get_url_path();


$datadir = PATH.'data';
$confdir = PATH.'conf';
$logdir = $datadir.'/log';
$tmpdir = $datadir.'/tmp';
$cachedir = $datadir.'/cache';
$viewdir = PATH.'view';
$controldir = PATH.'control';
$modeldir = PATH.'model';
$coredir = PATH.'core';
$staticdir = PATH.'static';

!is_dir($datadir) && mkdir($datadir, 0777);
!is_dir($confdir) && mkdir($confdir, 0777);
!is_dir($logdir) && mkdir($logdir, 0777);
!is_dir($tmpdir) && mkdir($tmpdir, 0777);
!is_dir($cachedir) && mkdir($cachedir, 0777);
!is_dir($viewdir) && mkdir($viewdir, 0777);
!is_dir($controldir) && mkdir($controldir, 0777);
!is_dir($modeldir) && mkdir($modeldir, 0777);
!is_dir($coredir) && mkdir($coredir, 0777);
!is_dir($staticdir) && mkdir($staticdir, 0777);

$conffile = PATH.'conf/conf.debug.php';
$indexfile = PATH.'index.php';
$view_header_file = PATH.'view/header.htm';
$view_index_file = PATH.'view/index.htm';
$view_footer_file = PATH.'view/footer.htm';
$static_jquery_js = PATH.'static/jquery.js';
$static_common_js = PATH.'static/common.js';
$static_reset_css = PATH.'static/reset.css';
$static_common_css = PATH.'static/common.css';

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
\$app_dir = get_url_abpath().'/';
\$app_dir_reg = preg_quote(\$app_dir);

return array(
	//db support： mysql/pdo_mysql/pdo_sqlite(数据库支持:mysql/pdo_mysql/pdo_sqlite)
	'db' => array(
			'mysql' => array(
				'host' => '127.0.0.1',
				'user' => 'root',
				'pass' => '',
				'name' =>  'test',
				'charset' => 'utf8',
				'tablepre' => 'bbs_',
				'engine'=> 'MYISAM',
			),
			//other example 
			/*
			'pdo_mysql' => array(
				'host' => '127.0.0.1',
				'user' => 'root',
				'pass' => '',
				'name' =>  'test',
				'charset' => 'utf8',
				'tablepre' => 'bbs_',
				'engine'=> 'MYISAM',
			),
			'pdo_sqlite' => array(
				'host' => ROOT_PATH.'data/tmp/sqlite_test.db',			
				'tablepre' => 'bbs_',	
			),
			*/
		),
	// cache support: memcache/file(缓存支持：memcache/文件缓存)
	'cache' => array(
		/*
		'memcache' => array(
			'host' => '127.0.0.1:11211',
			'pre' => 'bbs_',
		),
		*/
		'file' => array(
			'dir' => ROOT_PATH.'data/cache".md5(time())."/',
			'pre' => 'bbs_',
		),
	),

	// 唯一识别ID
	'app_id' => '".$appname."',
	
	//网站名称
	'app_name' => '".$appname."',
	
	// cookie 前缀
	'cookie_pre' => '".$appname."',
	
	// cookie 域名
	'cookie_domain' => '',
	
	//是否开启 gzip
	'gzip' => 0,
	
	// 应用的绝对路径： 如: http://www.domain.com/bbs/
	'app_url' => '".get_url_path()."',
	
	// 应用的所在路径： 如: http://www.domain.com/bbs/
	'app_dir' => \$app_dir,
	
	// CDN 缓存的静态域名，如 http://static.domain.com/
	'static_url' => '".get_url_path()."static/',
	
	// CDN 本地缓存的静态目录，如 http://static.domain.com/
	'static_dir' => ROOT_PATH.'static/',

	// 应用内核扩展目录，一些公共的库需要打包进 _runtime.php （减少io）
	'core_path' => $APP_PATH.'core/',
	
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
	
	// 模板支持 static 插件，支持 scss、css、js 打包
	'tpl' => array(
		'plugins' => array(
			'tpl_static' => FRAMEWORK_PATH.'plugin/tpl_static.class.php',
		),
	),
	
	// 开启rewrite
	'url_rewrite' => 1,

	// 是否不压缩 html代码(如果不开启，html中的<script>片段不能有//行注释，只能用块注释/**/)
	'html_no_compress' => 0,
	
	//url rewrite params
	'rewrite_info' => array(
		'comma' => '/', // options: / \ - _  | . , 
		'ext' => '.html',// for example : .htm
	),
	'str_replace' => array(),
	
	'reg_replace' => array(),
);
	";
	
	file_put_contents($conffile, $s);
	
	$conffile = str_replace('.debug', '.online', $conffile);
	if(!is_file($conffile)){
		file_put_contents($conffile, $s);
	}
}

//str_replace('\\\\', '/', )

if(!is_file($indexfile)) {
	$s = "<?php
\$_SERVER['ENV'] = isset(\$_SERVER['ENV']) ? \$_SERVER['ENV'] : 'debug';
// 调试模式: 0:关闭; 1:调试模式; 参数开启调试, URL中带上：{$appname}_debug
define('DEBUG', ((isset(\$argc) && \$argc) || strstr(\$_SERVER['REQUEST_URI'], '{$appname}_debug')) ? 1:0);
// 站点根目录
define('ROOT_PATH', dirname(__FILE__).'/');
// 框架的物理路径
define('FRAMEWORK_PATH', $APP_PATH.'../mzphp/');
// 404
\$page_setting = array(
	404 => function(\$control = ''){
		header(\$_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
		include('404.htm');
		exit;
	},
);

if(!(\$conf = include(ROOT_PATH.'conf/conf.'.\$_SERVER['ENV'].'.php'))) {
	\$page_setting[404]();
}

// 错误页面设置
\$conf['page_setting'] = isset(\$conf['page_setting']) ? array_merge(\$page_setting, \$conf['page_setting']) : \$page_setting;

\$conf['env'] = \$_SERVER['ENV'];

// 核心扩展目录
if(isset(\$conf['core_path'])){
	define('FRAMEWORK_EXTEND_PATH', \$conf['core_path']);
}

// 临时目录
define('FRAMEWORK_TMP_PATH', \$conf['tmp_path']);

// 日志目录
define('FRAMEWORK_LOG_PATH', \$conf['log_path']);

//扩展核心目录（该目录文件会一起打包入 runtime.php 文件）
//define('FRAMEWORK_EXTEND_PATH', ROOT_PATH.'model/');

// 包含核心框架文件，转交给框架进行处理。
include FRAMEWORK_PATH.'mzphp.php';

core::run(\$conf);

?>";
	file_put_contents($indexfile, $s);
}

if(!is_file($view_header_file)) {
	$s = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Title</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<!--{static ../static/reset.css _global.css}-->
	<!--{static ../static/common.css _global.css}-->
	<!--{static ../static/jquery.js _global.js}-->
	<!--{static ../static/common.js _global.js}-->
</head>
<body>
<h3>mzPHP Framework</h3>
<hr />
';
	file_put_contents($view_header_file, $s);
}

if(!is_file($view_index_file)) {
	$s = '<!--{template header.htm}-->
	<h1>Hello, mzPHP! Hello, $username.</h1>
<!--{template footer.htm}-->';
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
	
	public function on_index() {
		
		\$username = 'Jobs';
		VI::assign('username', \$username);
		VI::display(\$this, 'index.htm');
	
	}
}
		
?>";
	
	file_put_contents($control_index_file, $s);
}

if(!is_file($static_jquery_js)){
	$s = '//jquery code
console.log("jquery Code");';
	file_put_contents($static_jquery_js, $s);
}

if(!is_file($static_common_js)){
	$s = '//common code
console.log("common Code");';
	file_put_contents($static_common_js, $s);
}

if(!is_file($static_reset_css)){
	$s = '/*reset code*/body{margin:0;padding:0}';
	file_put_contents($static_reset_css, $s);
}

if(!is_file($static_common_css)){
	$s = '/*common code*/a{color:red}';
	file_put_contents($static_common_css, $s);
}


$url = $app_url."?c=index-index";
@unlink('./auto_create.php');
show_message("<a href='{$url}'>应用框架代码生成完毕！ 发布时请记得带上同级目录的 mzphp 目录（本文件已删除）</a>");


