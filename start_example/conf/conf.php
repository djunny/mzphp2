<?php 
function get_url_abpath(){
	return substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'));
}
$app_dir = get_url_abpath().'/';
$app_dir_regexp = preg_quote($app_dir);

return array(
	'db' => array(
		/*
		'mysql' => array(
			'host' => '127.0.0.1',
			'user' => 'root',
			'pass' => '',
			'name' =>  'test',
			'charset' => 'utf8',
			'tablepre' => 'bbs_',
			'engine'=> 'MYISAM',
		),
						
		'pdo_mysql' => array(
			'host' => '127.0.0.1',
			'user' => 'root',
			'pass' => '',
			'name' =>  'test',
			'charset' => 'utf8',
			'tablepre' => 'bbs_',
			'engine'=> 'MYISAM',
		),
		*/
		'pdo_sqlite' => array(
			'host' => ROOT_PATH.'data/tmp/sqlite_test.db',			
			'tablepre' => 'bbs_',	
		),
	),
	'cache' => array(
		/*
		'memcache' => array(
			'host' => '127.0.0.1:11211',
			'pre' => 'bbs_',
		),
		*/
		'file' => array(
			'dir' => ROOT_PATH.'data/cache/',
			'pre' => 'bbs_',
		),
	),
	// 唯一识别ID
	'app_id' => 'start_example',
	
	//网站名称
	'app_name' => 'start_example',
	
	// 应用的绝对路径： 如: http://www.domain.com/bbs/
	'app_url' => 'http://localhost/mzphp2/start_example/',
	
	// 应用的所在路径： 如: http://www.domain.com/bbs/
	'app_dir' => $app_dir,
	
	// CDN 缓存的静态域名，如 http://static.domain.com/
	'static_url' => 'http://localhost/mzphp2/start_example/',
	
	// 模板使用的目录，按照顺序搜索，这样可以支持风格切换,结果缓存在 tmp/bbs_xxx.htm.php
	'view_path' => array(ROOT_PATH.'view/'), 
	
	//是否开启 gzip
	'gzip' => 0,
	
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

	// cookie 前缀
	'cookie_pre' => 'rnd_',
	
	// cookie 域名
	'cookie_domain' => '',
	
	// 服务器所在的时区
	'timeoffset' => '+8',
	
	'plugin_disable'=>0,// 禁止掉所有插件
	
	'url_rewrite' => 1,// 开启rewrite
	
	//url rewrite params
	'rewrite_info' => array(
		'comma' => '/', // options: / \ - _  | . , 
		'ext' => '.html',// for example : .htm
	),
	'str_replace' => array(),
	
	'reg_replace' => array(
	),
);
	