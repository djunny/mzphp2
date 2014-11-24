<?php
// 调试模式: 0:关闭; 1: 调试模式
define('DEBUG', ((isset($argc) && $argc) || strstr($_SERVER['REQUEST_URI'], 'debug')) ? 1:0);
// 站点根目录
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__)).'/');

if(!($conf = include('./conf/conf.php'))) {
	exit('config file not exists');
}
// 框架的物理路径
define('FRAMEWORK_PATH', ROOT_PATH.'../mzphp/');

// 临时目录
define('FRAMEWORK_TMP_PATH', $conf['tmp_path']);

// 日志目录
define('FRAMEWORK_LOG_PATH', $conf['log_path']);
// 日志目录
if(isset($conf['core_path'])){
	define('FRAMEWORK_EXTEND_PATH', $conf['core_path']);
}
// 包含核心框架文件，转交给框架进行处理。
include FRAMEWORK_PATH.'mzphp.php';

core::run($conf);

?>