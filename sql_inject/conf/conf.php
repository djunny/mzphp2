<?php 
 
/**************************************************************************************************
 	【注意】：
 		1. 请不要使用 Windows 的记事本编辑此文件！此文件的编码为UTF-8编码，不带有BOM头！
 		2. 建议使用UEStudio, Notepad++ 类编辑器编辑此文件！
***************************************************************************************************/

function get_url_abpath(){
	return substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'));
}
$app_dir = get_url_abpath().'/';
$app_dir_reg = preg_quote($app_dir);
return array(
	'db' => array(
		),
	'cache' => array(
		'file' => array(
			'dir' => ROOT_PATH.'data/cache/',
			'pre' => 'bbs_',
		),
	),

	// 唯一识别ID
	'app_id' => 'sql_inject',
	
	//网站名称
	'app_name' => 'sql_inject',
	
	// cookie 前缀
	'cookie_pre' => 'sql_inject',
	
	//是否开启 gzip
	'gzip' => 0,
	
	// 应用的绝对路径： 如: http://www.domain.com/bbs/
	'app_url' => 'http://localhost/',
	
	'cookie_domain' => '',
	
	// 应用的所在路径： 如: http://www.domain.com/bbs/
	'app_dir' => $app_dir,
	
	// CDN 缓存的静态域名，如 http://static.domain.com/
	'static_url' => 'http://localhost/',
	
	// 模板使用的目录，按照顺序搜索，这样可以支持风格切换,结果缓存在 tmp/bbs_xxx.htm.php
	'core_path' => ROOT_PATH.'core/', 
	
	// 模板使用的目录，按照顺序搜索，这样可以支持风格切换,结果缓存在 tmp/bbs_xxx.htm.php
	'view_path' => array(ROOT_PATH.'view/'), 
	
	// 数据模块的路径，按照数组顺序搜索目录
	'model_path' => array(ROOT_PATH.'model/'),
	
	// 自动加载 model 的配置， 在 model_path 中未找到 modelname 的时候尝试扫描此项, modelname=>array(tablename, primarykey, maxcol)
	'model_map' => array(),
	
	// 业务控制层的路径，按照数组顺序搜索目录，结果缓存在 tmp/bbs_xxx_control.class.php
	'control_path' => array(ROOT_PATH.'control/'),
	
	// 临时目录，需要可写，可以指定为 linux /dev/shm/ 目录提高速度, 支持 file_put_contents() file_get_contents(), 不支持 fseek(),  SAE: saekv://
	'tmp_path' => ROOT_PATH.'data/tmp/',

	// 日志目录，需要可写
	'log_path' => ROOT_PATH.'data/log/',
	
	// 服务器所在的时区
	'timeoffset' => '+8',
	
	//
	'cookie_key' => 'SQL',
	
	// Cookie在线时间
	'online_hold' => 86400,
	
	// 开启rewrite
	'url_rewrite' => 1,
	
	//url rewrite params
	'rewrite_info' => array(
		'comma' => '/', // options: / \ - _  | . , 
		'ext' => '.html',// for example : .htm
	),
	'str_replace' => array(),
	
	'reg_replace' => array(),
);
	