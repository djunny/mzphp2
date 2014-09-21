<?php
if(!defined('DEBUG')) {
	define('DEBUG', 0);
}

if(DEBUG > 0) {
	// 包含基础的类：初始化相关
	$inc_files = glob(FRAMEWORK_PATH.'*/*.class.php');
	foreach ($inc_files as $inc_file) {
		include $inc_file;
	}
	if(defined('FRAMEWORK_EXTEND_PATH') && is_dir(FRAMEWORK_EXTEND_PATH)){
		$inc_files = glob(FRAMEWORK_EXTEND_PATH.'*.class.php');
		foreach ($inc_files as $inc_file) {
			include $inc_file;
		}
	}
	unset($inc_files, $inc_file);
	
} else {
	// 语义同上段，优先读取应用定义的目录下的 runtime 文件
	$content = '';
	$runtimefile = FRAMEWORK_TMP_PATH.'_runtime.php';
	if (!(@include($runtimefile))) {
		// 最低版本需求判断
		PHP_VERSION < '5.0' && exit('Required PHP version 5.0.* or later.');
		//make runtime file
		$inc_files = glob(FRAMEWORK_PATH.'*/*.class.php');
		foreach ($inc_files as $inc_file) {
			if(strpos($inc_file, 'debug/') === false){
				$content .= php_strip_whitespace($inc_file);
			}
		}
		if(defined('FRAMEWORK_EXTEND_PATH') && is_dir(FRAMEWORK_EXTEND_PATH)){
			$inc_files = glob(FRAMEWORK_EXTEND_PATH.'*.class.php');
			foreach ($inc_files as $inc_file) {
				$content .= php_strip_whitespace($inc_file);
			}
		}
		unset($inc_files, $inc_file);
		file_put_contents($runtimefile, $content);
		unset($content);
		include $runtimefile;
	}
}



?>