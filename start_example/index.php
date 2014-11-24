<?php
define('DEBUG', (strstr($_SERVER['REQUEST_URI'], 'debug') || $argc > 0) ? 1:0);
// 站点根目录，在单元测试时候，此文件可能被包含
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__)).'/');

if(!($conf = include(ROOT_PATH.'conf/conf.php'))) {
	exit('config file not exists');
}
// 框架的物理路径
define('FRAMEWORK_PATH', ROOT_PATH.'../mzphp/');

// 临时目录
define('FRAMEWORK_TMP_PATH', $conf['tmp_path']);

// 日志目录
define('FRAMEWORK_LOG_PATH', $conf['log_path']);

// 包含核心框架文件，转交给框架进行处理。
include FRAMEWORK_PATH.'mzphp.php';

core::run($conf);
?>