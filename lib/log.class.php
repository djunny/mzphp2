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

if(!isset($_SERVER['time'])) {
	$_SERVER['time'] = time();
	$_SERVER['time_fmt'] = gmdate('y-n-j H:i', time() + 8 * 3600);
}

class log {
	public static function write($s, $file = 'phperror.php') {
		$s = self::safe_str($s);
		$ip = $_SERVER['ip'];
		$time = $_SERVER['time_fmt'];
		$url = $_SERVER['REQUEST_URI'];
		$url = self::safe_str($url);
		$s = '<?php exit;?>'."	$time	$ip	$url	$s	\r\n";
		self::real_write($s, $file);
		return TRUE;
	}
	
	// 直接写入
	public static function real_write($s, $file = 'phperror.php') {
		if(IN_SAE) {
			sae_set_display_errors(false);
			sae_debug($s);
			return TRUE;
		}
		$logpath = FRAMEWORK_LOG_PATH;
		$logfile = $logpath.$file;
		try {
			$fp = fopen($logfile, 'ab+');
			if(!$fp) {
				throw new Exception('写入日志失败，可能磁盘已满，或者文件'.$logfile.'不可写。');
			}
			fwrite($fp, $s);
			fclose($fp);
		} catch (Exception $e) {}
		return TRUE;
	}
	
	public static function safe_str($s) {
		$s = str_replace("\r\n", ' ', $s);
		$s = str_replace("\r", ' ', $s);
		$s = str_replace("\n", ' ', $s);
		$s = str_replace("\t", ' ', $s);
		return $s;
	}
	
	// 跟踪变量的值
	public static function trace($s) {
		if(!DEBUG) return;
		$processtime = number_format(microtime(1) - $_SERVER['time'], 3, '.', '');
		empty($_SERVER['trace']) && $_SERVER['trace'] = '';
		$_SERVER['trace'] .= "$s - $processtime\r\n";
	}
	
	// 保存 trace
	public static function trace_save() {
		$s = "\r\n\r\n---------------------------------------------------------------------------------\r\n<?php exit;?>\r\n---------------------------------------------------------------------------------\r\n$_SERVER[REQUEST_URI]\r\nPOST:".print_r($_POST, 1)."\r\nSQL:".print_r($_SERVER['sqls'], 1)."\r\n";
		$s .= $_SERVER['trace'];
		self::real_write($s, 'trace.php');
	}
}
?>