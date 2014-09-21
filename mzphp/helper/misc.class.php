<?php

if(!defined('FORM_HASH_KEY')){
	define('FORM_HASH_KEY', $_SERVER['HTTP_USER_AGENT']);
}

class misc {
	
	//跳转
	public static function R($url, $code=301){
		ob_end_clean();
		header('Location: '.$url, true, $code);
		exit;
	}
	
	
	public static function form_hash($auth_key = '') {
		return substr(md5(substr($_SERVER['time'], 0, -5).$auth_key), 16);
	}
	
	// 校验 formhash
	public static function form_submit($auth_key = '') {
		$hash = core::gpc('FORM_HASH', 'R');
		return $hash == self::form_hash($auth_key);
	}
	
	
	// 返回格式：http://www.domain.com/blog/，这里不考虑 https://
	public static function get_url_path() {
		$port = core::gpc('SERVER_PORT', 'S');
		$host = core::gpc('HTTP_HOST', 'S');	// host 里包含 port
		$path = substr(core::gpc('PHP_SELF', 'S'), 0, strrpos(core::gpc('PHP_SELF', 'S'), '/'));
		$http = (($port == 443) || (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off')) ? 'https' : 'http';
		return  "$http://$host$path/";
	}
	
	// 返回格式：http://www.domain.com/path/script.php?a=b&c=d
	public static function get_uri() {
		$port = core::gpc('SERVER_PORT', 'S');
		//$portadd = $port == 80 ? '' : ':80';
		$host = core::gpc('HTTP_HOST', 'S');
		//$schme = self::gpc('SERVER_PROTOCOL', 'S');
		
		// [SERVER_SOFTWARE] => Microsoft-IIS/6.0
		// [REQUEST_URI] => /index.php
		// [HTTP_X_REWRITE_URL] => /?a=b
		// iis
		if(isset($_SERVER['HTTP_X_REWRITE_URL'])) {
			$request_uri = $_SERVER['HTTP_X_REWRITE_URL'];
		} else {
			$request_uri = $_SERVER['REQUEST_URI'];
		}
		$http = (($port == 443) || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')) ? 'https' : 'http';
		return  "$http://$host".$request_uri;
		//if(isset($_SERVER['SCRIPT_URI']) && 0) {
		//	return $_SERVER['SCRIPT_URI'];// 会漏掉 query_string, .core::gpc('QUERY_STRING', 'S');
		//}
	}
	
	public static function human_date($timestamp) {
		$seconds = $_SERVER['time'] - $timestamp;
		if($seconds > 31536000) {
			return date('Y-n-j', $timestamp);
		} elseif($seconds > 2592000) {
			return ceil($seconds / 2592000).'月前';
		} elseif($seconds > 86400) {
			return ceil($seconds / 86400).'天前';
		} elseif($seconds > 3600) {
			return ceil($seconds / 3600).'小时前';
		} elseif($seconds > 60) {
			return ceil($seconds / 60).'分钟前';
		} else {
			return $seconds.'秒前';
		}
	}
	
	public static function human_num($num) {
		$num > 100000 && $num = ceil($num / 10000).'万';
		return $num;
	}
	
	public static function human_size($num) {
		if($num > 1073741824) {
			return number_format($num / 1073741824, 2, '.', '').'G';
		} elseif($num > 1048576) {
			return number_format($num / 1048576, 2, '.', '').'M';
		} elseif($num > 1024) {
			return number_format($num / 1024, 2, '.', '').'K';
		} else {
			return $num.'B';
		}
	}
	
	public static function hexdump($data, $newline = "\n") {
		static $from = '';
		static $to = '';

		static $width = 16; // 每行宽度
		static $pad = '.';
		if($from === '') {
			for($i=0; $i <= 0xFF; $i++) {
				$from .= chr($i);
				$to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
			}
		}

		$hex = str_split(bin2hex($data), $width * 2);
		$chars = str_split(strtr($data, $from, $to), $width);

		$offset = 0;
		foreach($hex as $i => $line) {
			echo sprintf('%6X',$offset).' : '.implode(' ', str_split($line, 2)).' ['.$chars[$i].']'.$newline;
			$offset += $width;
		}
	}
	
	
	public static function is_robot() {
		$robots = array('robot', 'spider', 'slurp');
		foreach($robots as $robot) {
			if(strpos(core::gpc('HTTP_USER_AGENT', 'S'), $robot) !== FALSE) {
				return TRUE;
			}
		}
		return FALSE;
	}
	
	
	public static function is_writable($file) {
		// 主要是兼容 windows
		try {
			if(is_file($file)) {
				if(strpos(strtoupper(PHP_OS), 'WIN') !== FALSE) {
					$fp = @fopen($file, 'rb+');
					@fclose($fp);
					return (bool)$fp;
				} else {
					return is_writable($file);
				}
			} elseif(is_dir($file)) {
				$tmpfile = $file.'/____tmp.tmp';
				$n = @file_put_contents($tmpfile, 'a');
				if($n > 0) {
					unlink($tmpfile);
					return TRUE;
				} else {
					return FALSE;
				}
			}
		} catch(Exception $e) {
			return false;
		}
	}
}

?>