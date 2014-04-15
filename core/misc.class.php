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

class misc {

	public static function page($key = 'page') {
		return max(1, intval(core::gpc($key, 'R')));
	}
	
	/*
		misc::pages('?thread-index.htm', 100, 1, 20);
		misc::pages('thread-index.htm', 100, 1, 20);
		misc::pages('index.php', 100, 1, 20);
		misc::pages('index.php?a=b', 100, 1, 20);
	*/
	public static function pages($url, $totalnum, $page, $pagesize = 20, $pagename = 'page') {
		// ?xxx.htm 认为也是支持 rewrite 格式的
		$urladd = '';
		if(strpos($url, '.htm') !== FALSE) {
			list($url, $urladd) = explode('.htm', $url);
			$urladd = '.htm'.$urladd;
			$rewritepage = "-$pagename-";
		} else {
			$url .= strpos($url, '?') === FALSE ? '?' : '&';
			$rewritepage = "$pagename=";
		}

		$totalpage = ceil($totalnum / $pagesize);
		if($totalpage < 2) return '';
		$page = min($totalpage, $page);
		$shownum = 5;	// 显示多少个页 * 2
		
		$start = max(1, $page - $shownum);
		$end = min($totalpage, $page + $shownum);
		
		// 不足 $shownum，补全左右两侧
		$right = $page + $shownum - $totalpage;
		$right > 0 && $start = max(1, $start -= $right);
		$left = $page - $shownum;
		$left < 0 && $end = min($totalpage, $end -= $left);
		
		$s = '';
		$page != 1 && $s .= '<a href="'.$url.$rewritepage.($page - 1).$urladd.'">◀</a>';
		if($start > 1) $s .= '<a href="'.$url.$rewritepage.'1'.$urladd.'">1 '.($start > 2 ? '... ' : '').'</a>';
		for($i=$start; $i<=$end; $i++) {
			if($i == $page) {
				$s .= '<a href="'.$url.$rewritepage.$i.$urladd.'" class="checked">'.$i.'</a>';// checked
			} else {
				$s .= '<a href="'.$url.$rewritepage.$i.$urladd.'">'.$i.'</a>';
			}
		}
		if($end != $totalpage) $s .= '<a href="'.$url.$rewritepage.$totalpage.$urladd.'">'.($totalpage - $end > 1 ? '... ' : '').$totalpage.'</a>';
		$page != $totalpage && $s .= '<a href="'.$url.$rewritepage.($page + 1).$urladd.'">▶</a>';
		return $s;
	}
	
	// 简单的上一页，下一页，比较省资源，不用count(), 推荐使用。
	public static function simple_pages($url, $totalnum, $page, $pagesize = 20, $pagename = 'page') {
		// ?xxx.htm 认为也是支持 rewrite 格式的
		$urladd = '';
		if(strpos($url, '.htm') !== FALSE) {
			list($url, $urladd) = explode('.htm', $url);
			$urladd = '.htm'.$urladd;
			$rewritepage = "-$pagename-";
		} else {
			$url .= strpos($url, '?') === FALSE ? '?' : '&';
			$rewritepage = "$pagename=";
		}
		
		$s = '';
		$page > 1 && $s .= '<a href="'.$url.$rewritepage.($page - 1).$urladd.'">上一页</a>';
		$totalnum >= $pagesize && $s .= '<a href="'.$url.$rewritepage.($page + 1).$urladd.'">下一页</a>';
		return $s;
	}
	
	public static function setcookie($key, $value, $time = 0, $path = '', $domain = '', $httponly = FALSE) {
		// 计算时差，服务器时间和客户端时间不一致的时候，最好由客户端写入。
		$_COOKIE[$key] = $value;
		if($value != NULL) {
			if(version_compare(PHP_VERSION, '5.2.0') >= 0) {
				setcookie($key, $value, $time, $path, $domain, FALSE, $httponly);
			} else {
				setcookie($key, $value, $time, $path, $domain, FALSE);
			}
		} else {
			if(version_compare(PHP_VERSION, '5.2.0') >= 0) {
				setcookie($key, '', $time, $path, $domain, FALSE, $httponly);
			} else {
				setcookie($key, '', $time, $path, $domain, FALSE);
			}
		}
	}
	
	public static function form_hash($auth_key) {
		return substr(md5(substr($_SERVER['time'], 0, -5).$auth_key), 16);
	}
	
	// 校验 formhash
	public static function form_submit($auth_key) {
		$hash = core::gpc('FORM_HASH', 'R');
		return $hash == self::form_hash($auth_key);
	}
	
	// 返回格式：http://www.domain.com/blog/，这里不考虑 https://
	public static function get_url_path() {
		$port = core::gpc('SERVER_PORT', 'S');
		//$portadd = ($port == 80 ? '' : ':'.$port);
		$host = core::gpc('HTTP_HOST', 'S');	// host 里包含 port
		$path = substr(core::gpc('PHP_SELF', 'S'), 0, strrpos(core::gpc('PHP_SELF', 'S'), '/'));
		$http = (($port == 443) || (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off')) ? 'https' : 'http';
		return  "$http://$host$path/";
	}
	
	// 返回格式：http://www.domain.com/path/script.php?a=b&c=d
	public static function get_script_uri() {
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
	
	// 依赖于 $_SERVER['time_today']
	public static function minidate($time) {
		$sub = $_SERVER['time_today'] - $time;
		if($sub < 0) {
			$format = 'H:i';
		// todo: 此处可能会有BUG，一年最后一个月
		/*} elseif($sub > 31536000) {
			$format = 'Y-n-j';
		} elseif($sub > 86400) {
			$format = 'Y-n-j';*/
		} else {
			$format = 'Y-n-j';
		}
		return date($format, $time);
	}
	
	public static function humandate($timestamp) {
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
	
	public static function humannumber($num) {
		$num > 100000 && $num = ceil($num / 10000).'万';
		return $num;
	}
	
	public static function humansize($num) {
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
	
	public static function mid($n, $min, $max) {
		if($n < $min) return $min;
		if($n > $max) return $min;
		return $n;
	}
	
	/*
	for ($i = 0; $i < strlen($string); $i++) {
	    echo dechex(ord($string[$i]));
	}
	*/
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
	
	public static function array_to_urladd($arr) {
		$s = '';
		foreach((array)$arr as $k=>$v) {
			$s .= "-$k-".urlencode($v);
		}
		return $s;
	}
	
	// 从一个二维数组中取出一个 key=>value 格式的一维数组
	public static function arrlist_key_values($arrlist, $key, $value) {
		$return = array();
		if($key) {
			foreach((array)$arrlist as $arr) {
				$return[$arr[$key]] = $arr[$value];
			}
		} else {
			foreach((array)$arrlist as $arr) {
				$return[] = $arr[$value];
			}
		}
		return $return;
	}
	
	// 从一个二维数组中取出一个 values() 格式的一维数组，某一列key
	public static function arrlist_values($arrlist, $key) {
		$return = array();
		foreach($arrlist as &$arr) {
			$return[] = $arr[$key];
		}
		return $return;
	}
	
	// 将 key 更换为某一列的值，在对多维数组排序后，数字key会丢失，需要此函数
	public static function arrlist_change_key(&$arrlist, $key) {
		$return = array();
		if(empty($arrlist)) return $return;
		foreach($arrlist as &$arr) {
			$return[$arr[$key]] = $arr;
		}
		$arrlist = $return;
	}
	
	/* 对多维数组排序
		$data = array();
		$data[] = array('volume' => 67, 'edition' => 2);
		$data[] = array('volume' => 86, 'edition' => 1);
		$data[] = array('volume' => 85, 'edition' => 6);
		$data[] = array('volume' => 98, 'edition' => 2);
		$data[] = array('volume' => 86, 'edition' => 6);
		$data[] = array('volume' => 67, 'edition' => 7);
		arrlist_multisort($data, 'edition', TRUE);
	*/
	public static function arrlist_multisort(&$arrlist, $col, $asc = TRUE) {
		$colarr = array();
		foreach($arrlist as $k=>$arr) {
			$colarr[$k] = $arr[$col];
		}
		$asc = $asc ? SORT_ASC : SORT_DESC;
		array_multisort($colarr, $asc, $arrlist);
		return $arrlist;
	}
	
	/*
		功能：将两个以空格隔开的字符串合并
		实例：echo str_merge('a b c', 'a1 a2');
		结果：a b c a1 a2
	*/
	public static function key_str_merge($haystack, $needle) {
		$haystack .= ' '.$needle;
		$arr = explode(' ', $haystack);
		$arr = array_unique($arr);
		return trim(implode(' ', $arr));
	}
	
	/*
		功能：将字符 $s2 从 $haystack 中去掉
		实例：echo key_str_strip('a b c', 'a b');
		结果：c
	*/
	public static function key_str_strip($haystack, $needle) {
		$haystack = " {$haystack} ";
		$arr = explode(' ', trim($needle));
		foreach($arr as $v) {
			$haystack = str_replace(' '.$v.' ', ' ', $haystack);
		}
		return trim($haystack);
	}
	
	public static function in_key_str($needle, $haystack) {
		return strpos(" {$needle} ", " {$haystack} ") !== FALSE;
	}
	
	// 安全过滤，过滤掉所有特殊字符，仅保留英文下划线，中文。其他语言需要修改U的范围
	public static function safe_str($s, $ext = '') {
		$ext = preg_quote($ext);
		$s = preg_replace('#[^'.$ext.'\w\x{4e00}-\x{9fa5}]+#u', '', $s);
		return $s;
	}
	
	// 转换空白字符, $onlytab 仅仅转换 \t
	public static function html_space($s) {
		$s = str_replace('  ', ' &nbsp;', $s);
		$s = str_replace('  ', ' &nbsp;', $s);
		$s = str_replace('  ', ' &nbsp;', $s);
		$s = str_replace("\t", '&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ', $s);
		$s = str_replace("\r\n", "\n", $s);
		$s = str_replace("\n", "<br />", $s);
		return $s;
	}
	
	// 对 key-value 数组进行组合 ('a'=>'a1', 'b'=>'b1')
	/*
		implode('=', '&', ('a'=>'a1', 'b'=>'b1'))
		a=a1&b=b1
	*/
	public static function implode($glue1, $glue2, $arr) {
		$s = '';
		foreach($arr as $k=>$v) {
			$s .= ($s ? $glue2 : '').$k.($v ? $glue1.$v : '');
		}
		return $s;
	}
	
	// 对 key-value 数组进行组合
	public static function explode($sep1, $sep2, $s) {
		$arr = $arr2 = $arr3 = array();
		$arr = explode($sep2, $s);
		foreach($arr as $v) {
			$arr2 = explode($sep1, $v);
			$arr3[$arr2[0]] = (isset($arr2[1]) ? $arr2[1] : '');
		}
		return $arr3;
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

	// https request
	public static function https_fetch_url($url, $timeout=30, $header=array()) {
		if(substr($url, 0, 5) == 'http:') {
			return self::fetch_url($url, $timeout);
		}
		$w = stream_get_wrappers();
		$allow_url_fopen = strtolower(ini_get('allow_url_fopen'));
		$allow_url_fopen = (empty($allow_url_fopen) || $allow_url_fopen == 'off') ? 0 : 1;
		if(extension_loaded('openssl') && in_array('https', $w) && $allow_url_fopen) {
			return file_get_contents($url);
		} elseif (!function_exists('curl_init')) {
			throw new Exception('server not installed curl.');
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, core::gpc('HTTP_USER_AGENT', 'S'));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
		//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转, 安全模式不允许
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		if(!empty($header)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		}
		$data = curl_exec($ch);
		if(curl_errno($ch)) {
			throw new Exception('Errno'.curl_error($ch));//捕抓异常
		}
		if(!$data) {
			curl_close($ch);
			return '';
		}
		
		list($header, $data) = explode("\r\n\r\n", $data);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($http_code == 301 || $http_code == 302) {
			$matches = array();
			preg_match('/Location:(.*?)\n/', $header, $matches);
			$url = trim(array_pop($matches));
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, false);
			$data = curl_exec($ch);
		}
		curl_close($ch);
		return $data;  
	}
	
	public static function gzdecode($data){
        $flags = ord(substr($data, 3, 1));
        $headerlen = 10;
        $extralen = 0;
        $filenamelen = 0;
        if ($flags & 4) {
            $extralen = unpack('v' ,substr($data, 10, 2));
            $extralen = $extralen[1];
            $headerlen += 2 + $extralen;
        }
        if ($flags & 8) $headerlen = strpos($data, chr(0), $headerlen) + 1;
        if ($flags & 16) $headerlen = strpos($data, chr(0), $headerlen) + 1;
        if ($flags & 2) $headerlen += 2;
        $unpacked = @gzinflate(substr($data, $headerlen));
        if ($unpacked === FALSE) $unpacked = $data;
        return $unpacked;
    }//gzdecode end

	// SAE 重载了 file_get_contents()
	public static function fetch_url($url, $timeout = 5, $post = '', $headers = array(), $deep = 0) {
		if($deep > 5) throw new Exception('超出 fetch_url() 最大递归深度！');
		if(substr($url, 0, 5) == 'https') {
			return self::https_fetch_url($url, $timeout);
		}
		$w = stream_get_wrappers();
		$allow_url_fopen = strtolower(ini_get('allow_url_fopen'));
		$allow_url_fopen = (empty($allow_url_fopen) || $allow_url_fopen == 'off') ? 0 : 1;
		//headers
		$HTTP_USER_AGENT = core::gpc('$HTTP_USER_AGENT', 'S');
		empty($HTTP_USER_AGENT) && $HTTP_USER_AGENT = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0)';
		
		$matches = parse_url($url);
		$host = $matches['host'];
		$path = $matches['path'] ? $matches['path'].(!empty($matches['query']) ? '?'.$matches['query'] : '') : '/';
		$port = !empty($matches['port']) ? $matches['port'] : 80;
		
		$defheaders = array(
			'Accept' => '*/*',
			'User-Agent' => $HTTP_USER_AGENT,
			'Accept-Encoding' => 'gzip, deflate',
			'Host' => $host,
			'Connection' => 'Close',
			'Accept-Language' => 'zh-cn',
		);
		
		if(!empty($post)){
			$defheaders['Cache-Control'] = 'no-cache';
			$defheaders['Content-Type'] = 'application/x-www-form-urlencoded';
			$defheaders['Content-Length'] = strlen($post);
			$out = "POST {$path} HTTP/1.0\r\n";
		}else{
			$out = "GET {$path} HTTP/1.0\r\n";
		}
		//merge headers
		$defheaders = array_merge($defheaders, $headers);
		foreach($defheaders as $hkey=>$hval){
			$out .= $hkey.': '.$hval."\r\n";
		}
		
		if( function_exists('fsockopen')) {
			$limit = 500000;
			$ip = '';
			$return = '';
			
			$out .= "\r\n";
			//append post body
			if(!empty($post)) {
				$out .= $post;
			}
			

			$host == 'localhost' && $ip = '127.0.0.1';
			$fp = @fsockopen(($ip ? $ip : $host), $port, $errno, $errstr, $timeout);
			if(!$fp) {
				return FALSE;
			} else {
				stream_set_blocking($fp, TRUE);
				stream_set_timeout($fp, $timeout);
				@fwrite($fp, $out);
				$status = stream_get_meta_data($fp);
				$gzip = false;
				if(!$status['timed_out']) {
					$starttime = time();
					while (!feof($fp)) {
						if(($header = @fgets($fp)) && ($header == "\r\n" ||  $header == "\n")) {
							break;
							//Location: http://plugin.xiuno.net/upload/plugin/66/b0c35647c63b8b880766b50c06586c13.zip
						} else {
							$header = strtolower($header);
							if(substr($header, 0, 9) == 'location:') {
								$location = trim(substr($header, 9));
								return self::fetch_url($location, $timeout, $post, $headers, $deep + 1);
							}else if(strpos($header, 'content-encoding:') !== false 
								&& strpos($header, 'gzip') !==  false) {
								//is gzip
								$gzip = true;
							}
						}
					}
					$stop = false;
					while(!feof($fp) && !$stop) {
						$data = fread($fp, ($limit == 0 || $limit > 8192 ? 8192 : $limit));
						$return .= $data;
						if($limit) {
							$limit -= strlen($data);
							$stop = $limit <= 0;
						}
						if(time() - $starttime > $timeout) break;
					}
					if($gzip){
						$return = self::gzdecode($return);
					}
				}
				@fclose($fp);
				return $return;
			}
		} elseif(function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_ENCODING, ''); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_MAXREDIRS , $deep);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $defheaders);
			if($post) {
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			}
			$data = curl_exec($ch);
			
			if(curl_errno($ch)) {
				throw new Exception('Errno'.curl_error($ch));//捕抓异常
			}
			if(!$data) {
				curl_close($ch);
				return '';
			}
			
			list($header, $data) = explode("\r\n\r\n", $data, 2);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if($http_code == 301 || $http_code == 302) {
				$matches = array();
				preg_match('/Location:(.*?)\n/', $header, $matches);
				$url = trim(array_pop($matches));
				curl_close($ch);
				return self::fetch_url($url, $timeout, $post, $headers, $deep + 1);
			}
			return $data;  
		} elseif($allow_url_fopen && empty($post) && empty($cookie) && in_array('http', $w)) {
			// 尝试连接
			$opts = array ('http'=>array('method'=>'GET', 'timeout'=>$timeout)); 
			$context = stream_context_create($opts);  
			$html = file_get_contents($url, false, $context);  
			return $html;
		} else {
			log::write('fetch_url() failed: '.$url);
			return FALSE;
		}
	}
	
	
	// 多线程抓取数据，需要CURL支持，一般在命令行下执行，此函数收集互联网，由 xiuno 整理。
	public static function multi_fetch_url($urls) {
		if(!function_exists('curl_multi_init')) {
			$data = array();
			foreach($urls as $k=>$url) {
				$data[$k] = self::fetch_url($url);
			}
			return $data;
		}

		$multi_handle = curl_multi_init();
		foreach ($urls as $i => $url) {
			$conn[$i] = curl_init($url);
			curl_setopt($conn[$i], CURLOPT_ENCODING, ''); 
			curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, 1);
			$timeout = 3;
			curl_setopt($conn[$i], CURLOPT_CONNECTTIMEOUT, $timeout); // 超时 seconds
			curl_setopt($conn[$i], CURLOPT_FOLLOWLOCATION, 1);
			//curl_easy_setopt(curl, CURLOPT_NOSIGNAL, 1);
			curl_multi_add_handle($multi_handle, $conn[$i]);
		}
		do {
			$mrc = curl_multi_exec($multi_handle, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		while ($active and $mrc == CURLM_OK) {
			if (curl_multi_select($multi_handle) != - 1) {
				do {
					$mrc = curl_multi_exec($multi_handle, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}
		foreach ($urls as $i => $url) {
			$data[$i] = curl_multi_getcontent($conn[$i]);
			curl_multi_remove_handle($multi_handle, $conn[$i]);
			curl_close($conn[$i]);
		}
		return $data;
	}
	
	// 替代 scandir, safe_mode
	public static function scandir($dir) {
		if(function_exists('scan_dir')) return scandir($dir);
		$df = opendir($dir);
		$arr = array();
		while($file = readdir($df)) {
			if($file == '.' || $file == '..') continue;
			$arr[] = $file;
		}
		closedir($df);
		return $arr;
	}
	
	// 递归删除目录，这个函数比较危险，传参一定要小心
	public static function rmdir($dir, $keepdir = 0) {
		if($dir == '/' || $dir == '../') return FALSE;// 不允许删除根目录，避免程序意外删除数据。
		if(!is_dir($dir)) return FALSE;
		substr($dir, -1, 1) != '/' && $dir .= '/';
		$files = self::scandir($dir);
		foreach($files as $file) {
			if($file == '.' || $file == '..') continue;
			$filepath = $dir.$file;
			if(!is_dir($filepath)) {
				try {unlink($filepath);} catch (Exception $e) {}
			} else {
				self::rmdir($filepath.'/');
			}
		}
		try {if(!$keepdir) rmdir($dir);} catch (Exception $e) {}
		return TRUE;
	}
	
	
}

?>