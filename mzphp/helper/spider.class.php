<?php
class spider {
	
	public static function no_html($html){
		return self::reg_replace($html, array('<(*)>' => ''));
	}
	
	public static function cut_str($html, $start='', $end=''){
		if($start){
			$html = strstr($html, $start, false);
			$html = substr($html, strlen($start));
		}
		if($end){
			$html = strstr($html, $end, true);
		}
		return $html;
	}
	// mask match string 
	/*
		 spider::mask_match('123abc123', '123(*)123') = abc
		 spider::mask_match('abc123', '(*)123') = abc
		 spider::mask_match('123abcabc', '(*)abc') = 123
		 spider::mask_match('123abcdef', '(*)abc', true) = 123abc
	*/
	public static function mask_match($html, $pattern, $returnfull = false){
		$part = explode('(*)', $pattern);
		if(count($part)==1){
			return '';
		}else{
			if($part[0] && $part[1]){
				$res = self::cut_str($html, $part[0], $part[1]);
				if($res){
					return $returnfull ? $part[0].$res.$part[1] : $res;
				}
			}else{
				//pattern=xxx(*)
				if($part[0]){
					$html = explode($part[0], $html);
					if($html[1]){
						return $returnfull ? $part[0].$html[1] : $html[1];
					}
				}else if ($part[1]){
					//pattern=(*)xxx
					$html = explode($part[1], $html);
					if($html[0]){
						return $returnfull ? $html[0].$part[1] : $html[0];
					}
				}
			}
			return '';
		}
	}
	
	//replace by array key => value, support reg & str & mask
	/*
		//replace single mode
		spider::reg_replace('abcdefg', 'e(*)') = abcd
		spider::reg_replace('abcdefg', array('#e.+$#is'=> 'hij')) = abcdhij
		spider::reg_replace('abcd123', array('#\d+#s'=> '')) = abcd
		spider::reg_replace('abcd123', array('cd'=> 'dc')) = abdc123
		//replace multi pattern
		spider::reg_replace('abcd123', array(
										'cd'=> 'dc',
										'1(*)'=> '321',
										'#\d+#s'=> '111',
										)) = abdc111
	*/
	public static function reg_replace($html, $patterns){
		if(!is_array($patterns)){
			$patterns = array($patterns=>'');
		}
		foreach($patterns as $search=>$replace){
			// mask mastch replace
			if(strpos($search, '(*)')!== false){
				$i = 0;
				while($searchhtml = self::mask_match($html, $search, true)){
					if($searchhtml){
						$html = str_replace($searchhtml, $replace, $html);
						continue;
					}
					break;
				}
			}else if(preg_match('/^([\#\/\|\!\@]).+\\1([ismSMI]+)?$/is', $search)){
				//regexp replace
				$html = preg_replace($search, $replace, $html);
			}else{
				//str replace
				$html = str_replace($search, $replace, $html);
			}
		}
		return $html;
	}
	
	
	//match
	/*
		#useage 1
		spider::match($html, array(
			//pre process
			'_replace' => array(
				''
			),
			// list block is list array
			'listblock' => array(
				// set cut param can run pattern faster
				'cut' => array('<body>(*)</body>', '<html>(*)</html>'),
				'pattern'  => '/<a href="(?<url>.*?)"/is',
			),
			//reg match
			'title' => '/<title>(.*?)<\/title>/is',
			//mask match
			'title2' => '<title>(*)</title>',
			// match content, pattern is 'extract', means extract content by no rule
			'content' => 'extract',
			// match title, pattern is 'extract_title', mean extract title by no rule
			'title' => 'extract_title',
		));
		
		
		#useage 2
		
		$url = 'http://www.sogou.com/web?query='.urlencode($key).'&ie=utf8';
		$html = spider::fetch_url($url, '', array('Referer'=>'http://www.sogou.com/'));
		$keywordlist = spider::match($html, array('list'=>array(
			'cut' => '相关搜索</caption>(*)</tr></table>',
			'pattern' => '#id="sogou_\d+_\d+">(?<key>[^>]*?)</a>#is',
		)));
		$newarr = array();
		foreach($keywordlist['list'] as $key=>$val){
			$newarr[$val['key']] = array('key'=>$val['key']);
		}
	*/
	public static function match($html, $patterns){
		$tmplist = array();
		//sleep
		if(isset($patterns['_sleep'])){
			usleep($patterns['_sleep']);
			unset($patterns['_sleep']);
		}
		//pre process =replace
		if(isset($patterns['_replace'])){
			if(!is_array($patterns['_replace'])){
				$patterns['_replace'] = array($patterns['_replace']=>'');
			}
			$html = self::reg_replace($html, $patterns['_replace']);
			unset($patterns['_replace']);
		}
		$extractor = NULL;
		//next fetch
		$fetchqueue = array();
		foreach($patterns as $key=>$val){
			$value = NULL;
			if(!is_array($val)){
				$val = array($val);
			}
			if(isset($val['pattern'])){
				//pre process
				$matchhtml = self::match_pre_process($html, $val);
				//support multi pattern
				if(!is_array($val['pattern'])){
					$val['pattern'] = array($val['pattern']);
				}
				//regexp match it
				foreach($val['pattern'] as $pattern){
					if(strpos($pattern, '(*)') === false){
						$value = self::reg_match($matchhtml, $pattern);
						if($value){
							//取key中，包含 _fetch 结尾
							foreach($value[0] as $vkey=>$vval){
								if(substr($vkey, -6)=='_fetch'){
									foreach($value as $vindex=>$vurl){
										$fetchqueue[] = array(
											'key' => $key,
											'index' => $vindex,
											'url' => $vurl[$vkey],
											'patterns' => &$patterns[$key]['fetched'],
										);
										//add new key
										$value[$vindex][substr($vkey, 0, -6)] = $vval;
										//unset _fetch key
										unset($value[$vindex][$vkey]);
									}
								}
								
							}
							break;
						}
					}else{
						// match field by mask_match
						$value = self::mask_match($matchhtml, $pattern);
						
						if($value){
							self::match_process($value, $val);
							break;
						}
					}
				}
			}else{
				//multi mask match pattern
				foreach($val as &$pattern_array){
					if(!is_array($pattern_array) || !isset($pattern_array['pattern'])){
						$pattern_array = array(
											array('pattern' => array($pattern_array))
										);
					}
					$find_value = false;
					foreach($pattern_array as $pattern_info){
						if(!isset($pattern_info['pattern'])){
							continue;
						}
						//pre process
						$matchhtml = self::match_pre_process($html, $val);
						//not html to match then match next pattern
						if(!$matchhtml) {
							continue;
						}
						
						foreach($pattern_info['pattern'] as $pattern){
							if($pattern == 'extract'){
								// get extract 
								if($extractor == NULL){
									$extractor = new textExtract($html);
								}
								$value = $extractor->getContent();
								$value = $value['content'];
								break;
							}elseif($pattern == 'extract_title'){
								// get title
								if($extractor == NULL){
									$extractor = new textExtract($html);
								}
								$value = $extractor->getTitle();
								break;
							}else{
								// string match
								$value = self::str_match($html, $pattern);
							}
						
							if($value){
								$find_value = true;
								// when find processor
								self::match_process($value, $process_info);
								break;
							}
							//or match next pattern
						}
					}
					if($find_value){
						break;
					}
				}
			}
			$tmplist[$key] = $value;
		}
		
		//next fetch
		if($fetchqueue){
			foreach($fetchqueue as $url){
				$html = self::fetch_url($url['url']);
				$matches = self::match($html, $url['patterns']);
				$tmplist[$url['key']][$url['index']]['fetched'] = $matches; 
			}
		}
		return $tmplist;
	}
	
	// after match value process
	private static function match_process(&$value, &$pattern_info){
		if(isset($pattern_info['process']) ){
			if(!is_array($pattern_info['process'])){
				$pattern_info['process'] = array($pattern_info['process']);
			}
			foreach($pattern_info['process'] as $index=>$processor){
				$value = call_user_func($processor, $value);
			}
		}
	}
	
	// before match value process
	private static function match_pre_process($html, &$pattern_info){
		$matchhtml = $html;
		// cut it short and run faster
		if(isset($pattern_info['cut'])){
			// support multi patterns
			if(!is_array($pattern_info['cut'])){
				$pattern_info['cut'] = array($pattern_info['cut']);
			}
			// until find match html 
			foreach($pattern_info['cut'] as $pattern){
				$matchhtml = self::mask_match($html, $pattern);
				if($matchhtml){
					break;
				}
			}
		}
		//replace html
		if(isset($pattern_info['_replace'])){
			if(!is_array($pattern_info['_replace'])){
				$pattern_info['_replace'] = array($pattern_info['_replace']=>'');
			}
			$matchhtml = self::reg_replace($matchhtml, $pattern_info['_replace']);
		}
		return $matchhtml;
	}
	
	
	//string match
	/*
		spider::str_match('123', '1(*)3') = 2
		spider::str_match('123', '1(\d+)3') = 2
	*/
	public static function str_match($str, $pattern){
		$value = '';
		//array mask pattern
		if(strpos($pattern, '(*)') !== false){
			$value = self::mask_match($str, $pattern);
		}elseif(strpos($pattern, '(') !== false){
			//has reg match field
			preg_match_all($pattern, $str, $value);
			//return first match group
			$value = $value[1][0];
		}
		return $value;
	}
	
	//reg match 
	public static function reg_match($html, $reg, $returnindex = -1){
		$list = array();
		preg_match_all($reg, $html, $list);
		self::filter_list($list);
		if($returnindex == -1){
			return $list;
		}else{

			return $list[$returnindex];
		}
	}
	
	//filter number index in list
	private static function filter_list(&$list){
		foreach($list as $key=>$val){
			if(is_numeric($key)){
				unset($list[$key]);
			}
		}
		$keys = array_keys($list);
		foreach($keys as $idx=>$key){
			if(is_numeric($key))continue;
			foreach($list[$key] as $index=>$value){
				$list[$index][$key] = $value;
			}
			unset($list[$key]);
		}
	}
	

	//fetch url 
	public static function fetch_url($url, $post = '', $headers = array(), $timeout = 5, $deep = 0) {
		if($deep > 5) throw new Exception('超出 fetch_url() 最大递归深度！');
		static $stream_wraps = null;
		if($stream_wraps == null){
			$stream_wraps = stream_get_wrappers();
		}
		static $allow_url_fopen = null;
		if($allow_url_fopen == null){
			$allow_url_fopen = strtolower(ini_get('allow_url_fopen'));
			$allow_url_fopen = (empty($allow_url_fopen) || $allow_url_fopen == 'off') ? 0 : 1;
		}
		//headers
		$HTTP_USER_AGENT = core::gpc('$HTTP_USER_AGENT', 'S');
		// default ua
		empty($HTTP_USER_AGENT) && $HTTP_USER_AGENT = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0)';
		
		$matches = parse_url($url);
		$host = $matches['host'];
		$path = isset($matches['path']) ? $matches['path'].(!empty($matches['query']) ? '?'.$matches['query'] : '') : '/';
		$port = !empty($matches['port']) ? $matches['port'] : 80;
		$https = $matches['scheme'] == 'https' ? true : false;
		$charset = '';
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
		if(!$https && function_exists('fsockopen')) {
			$limit = 10240000;
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
							}else if(strpos($header, 'content-type:') !== false){
								preg_match( '@Content-Type:\s+([\w/+]+)(;\s+charset=([\w-]+))?@i', $header, $charsetmatch);
								if (isset($charsetmatch[3])){
									$charset = $charsetmatch[3];
								}
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
				return self::convert_html_charset($return, $charset);
			}
		} elseif(function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_ENCODING, ''); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			if(!$deep){
				$deep = 5;
			}
			curl_setopt($ch, CURLOPT_MAXREDIRS , $deep);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			//must use curlopt_cookie param to set 
			if(isset($defheaders['Cookie'])){
				curl_setopt($ch, CURLOPT_COOKIE, $defheaders['Cookie']);
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $defheaders);
			if($https){
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 从证书中检查SSL加密算法是否存在
			}
			if($post) {
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			}
			$data = curl_exec($ch);
			
			if(curl_errno($ch)) {
				//throw new Exception('Errno'.curl_error($ch));//捕抓异常
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
			//match charset
			preg_match('@Content-Type:\s+([\w/+]+)(;\s+charset=([\w-]+))?@is', $header, $charsetmatch);
			if (isset($charsetmatch[3])){
				$charset = $charsetmatch[3];
			}
			return self::convert_html_charset($data, $charset);
		} elseif($https && $allow_url_fopen && in_array('https', $stream_wraps)) {
			if(extension_loaded('openssl')){
				return file_get_contents($url);
			}else{
				 throw new Exception('unopen openssl extension');
			}
		} elseif($allow_url_fopen && empty($post) && empty($cookie) 
				&& in_array('http', $stream_wraps)) {
			// 尝试连接
			$opts = array ('http'=>array('method'=>'GET', 'timeout'=>$timeout)); 
			$context = stream_context_create($opts);  
			$html = file_get_contents($url, false, $context);  
			return convert_html_charset($html, $charset);
		} else {
			log::write('fetch_url() failed: '.$url);
			return FALSE;
		}
	}
	
	// gzdecode
	private static function gzdecode($data){
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
	
	//detect html coding
	private static function convert_html_charset($html, $charset, $tocharset ='utf-8'){
		//取html中的charset
		$detect_charset = '';
		//html file
		if(stripos($html, '<html')!==false){
			if(stripos($html, 'charset=') !==false){
				$head = self::mask_match($html, '(*)</head>');
				if($head){
					$head = strtolower($head);
					$head = self::reg_replace($head, array(
									'<script(*)/script>' => '',
									'<style(*)/style>' => '',
									'<link(*)>' => '',
									"\r" => '',
									"\n" => '',
									"\t" => '',
									" " => '',
									//"'" => '',
									//"\"" => '',
								));
					preg_match_all('/charset=([-\w]+)/', $head, $matches);
					if(isset($matches[1][0]) && !empty($matches[1][0])){
						$detect_charset = $matches[1][0];
					}
				}
			}
		}
		//xml file
		if(stripos($html, '<xml')!==false){
			//<?xml version="1.0" encoding="UTF-8"
			if(stripos($html, 'encoding=') !==false){
				$head = self::mask_match($html, '<'.'?xml(*)?'.'>');
				preg_match_all('/encoding=([-\w]+)/is', $head, $matches);
				if(isset($matches[1][0]) && !empty($matches[1][0])){
					$detect_charset = $matches[1][0];
				}
			}
		}
		//取 http header中的charset
		if(!$detect_charset && $charset){
			if(strtolower($charset) =='iso-8859-1'){
				$charset = 'gbk';
			}
			$detect_charset = $charset;
		}
		
		if($detect_charset){
			return iconv($detect_charset, $tocharset, $html);
		}else{
			return $html;
		}
	} 
	
	
	// multi thread fetch url
	private static function multi_fetch_url($urls) {
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
}






class textExtract {
    // 保存判定结果的标记位名称
    const ATTR_CONTENT_SCORE = "contentScore";

    // DOM 解析类目前只支持 UTF-8 编码
    const DOM_DEFAULT_CHARSET = "utf-8";

    // 当判定失败时显示的内容
    const MESSAGE_CAN_NOT_GET = "textextract was unable to parse this page for content.";

    // DOM 解析类（PHP5 已内置）
    protected $DOM = null;

    // 需要解析的源代码
    protected $source = "";

    // 章节的父元素列表
    private $parentNodes = array();

    // 需要删除的标签
    // Note: added extra tags from https://github.com/ridcully
    private $junkTags = Array("style", "form", "iframe", "script", "button", "input", "textarea", 
                                "noscript", "select", "option", "object", "applet", "basefont",
                                "bgsound", "blink", "canvas", "command", "menu", "nav", "datalist",
                                "embed", "frame", "frameset", "keygen", "label", "marquee", "link");

    // 需要删除的属性
    private $junkAttrs = Array("style", "class", "onclick", "onmouseover", "align", "border", "margin");


    /**
     * 构造函数
     *      @param $input_char 字符串的编码。默认 utf-8，可以省略
     */
    function __construct($source, $input_char = "utf-8") {
        $this->source = $source;

        // DOM 解析类只能处理 UTF-8 格式的字符
        //$source = mb_convert_encoding($source, 'HTML-ENTITIES', $input_char);

        // 预处理 HTML 标签，剔除冗余的标签等
        $source = $this->preparSource($source);

        // 生成 DOM 解析类
        $this->DOM = new DOMDocument('1.0', $input_char);
        try {
            //libxml_use_internal_errors(true);
            // 会有些错误信息，不过不要紧 :^)
            if (!@$this->DOM->loadHTML('<?xml encoding="'.textextract::DOM_DEFAULT_CHARSET.'">'.$source)) {
                throw new Exception("Parse HTML Error!");
            }

            foreach ($this->DOM->childNodes as $item) {
                if ($item->nodeType == XML_PI_NODE) {
                    $this->DOM->removeChild($item); // remove hack
                }
            }

            // insert proper
            $this->DOM->encoding = textextract::DOM_DEFAULT_CHARSET;
        } catch (Exception $e) {
            // ...
        }
    }


    /**
     * 预处理 HTML 标签，使其能够准确被 DOM 解析类处理
     *
     * @return String
     */
    private function preparSource($string) {
        // 剔除多余的 HTML 编码标记，避免解析出错
		/*
        preg_match("/charset=([\w|\-]+);?/", $string, $match);
        if (isset($match[1])) {
            $string = preg_replace("/charset=([\w|\-]+);?/", "", $string, 1);
        }
		*/
		
		$i = stripos($string, '<body');
		if($i === false){
			$i = stripos($string, '</head>');
		}
		if($i > 0){
			$string = substr($string, $i);
		}
		
        // Replace all doubled-up <BR> tags with <P> tags, and remove fonts.
        $string = preg_replace("/<br\/?>[ \r\n\s]*<br\/?>/i", "</p><p>", $string);
        $string = preg_replace("/<\/?font[^>]*>/i", "", $string);

        // @see https://github.com/feelinglucky/php-readability/issues/7
        //   - from http://stackoverflow.com/questions/7130867/remove-script-tag-from-html-content
        $string = preg_replace("#<script([^>]*?)>([\S\s]*?)</script>#is", "", $string);
		
		$string = spider::reg_replace($string, 
									array(
										'<!--[if(*)<![endif]-->' => '',
										'<!--(*)-->' => '',
										'<meta(*)>' => '',
										'<link(*)>' => '',
										"\r" => '',
										"\n" => '',
										'#>\s+<#is' => '><',
									)
								);
        return trim($string);
    }


    /**
     * 删除 DOM 元素中所有的 $TagName 标签
     *
     * @return DOMDocument
     */
    private function removeJunkTag($RootNode, $TagName) {
        
        $Tags = $RootNode->getElementsByTagName($TagName);
        
        //Note: always index 0, because removing a tag removes it from the results as well.
        while($Tag = $Tags->item(0)){
            $parentNode = $Tag->parentNode;
            $parentNode->removeChild($Tag);
        }
        
        return $RootNode;
        
    }

    /**
     * 删除元素中所有不需要的属性
     */
    private function removeJunkAttr($RootNode, $Attr) {
        $Tags = $RootNode->getElementsByTagName("*");

        $i = 0;
        while($Tag = $Tags->item($i++)) {
            $Tag->removeAttribute($Attr);
        }

        return $RootNode;
    }

    /**
     * 根据评分获取页面主要内容的盒模型
     *      判定算法来自：http://code.google.com/p/arc90labs-readability/
     *
     * @return DOMNode
     */
    private function getTopBox() {
        // 获得页面所有的章节
        $allParagraphs = $this->DOM->getElementsByTagName("p");

        // Study all the paragraphs and find the chunk that has the best score.
        // A score is determined by things like: Number of <p>'s, commas, special classes, etc.
        $i = 0;
        while($paragraph = $allParagraphs->item($i++)) {
            $parentNode   = $paragraph->parentNode;
            $contentScore = intval($parentNode->getAttribute(textextract::ATTR_CONTENT_SCORE));
            $className    = $parentNode->getAttribute("class");
            $id           = $parentNode->getAttribute("id");

            // Look for a special classname
            if (preg_match("/(comment|meta|footer|footnote|header)/i", $className)) {
                $contentScore -= 50;
            } else if(preg_match(
                "/((^|\\s)(post|hentry|entry[-]?(content|text|body)?|article[-]?(content|text|body)?)(\\s|$))/i",
                $className)) {
                $contentScore += 25;
            }

            // Look for a special ID
            if (preg_match("/(comment|meta|footer|footnote|header)/i", $id)) {
                $contentScore -= 50;
            } else if (preg_match(
                "/^(post|hentry|entry[-]?(content|text|body)?|article[-]?(content|text|body)?)$/i",
                $id)) {
                $contentScore += 25;
            }

            // Add a point for the paragraph found
            // Add points for any commas within this paragraph
            if (strlen($paragraph->nodeValue) > 10) {
                $contentScore += strlen($paragraph->nodeValue);
            }

            // 保存父元素的判定得分
            $parentNode->setAttribute(textextract::ATTR_CONTENT_SCORE, $contentScore);

            // 保存章节的父元素，以便下次快速获取
            array_push($this->parentNodes, $parentNode);
        }

        $topBox = null;
        
        // Assignment from index for performance. 
        //     See http://www.peachpit.com/articles/article.aspx?p=31567&seqNum=5 
        for ($i = 0, $len = sizeof($this->parentNodes); $i < $len; $i++) {
            $parentNode      = $this->parentNodes[$i];
            $contentScore    = intval($parentNode->getAttribute(textextract::ATTR_CONTENT_SCORE));
            $orgContentScore = intval($topBox ? $topBox->getAttribute(textextract::ATTR_CONTENT_SCORE) : 0);

            if ($contentScore && $contentScore > $orgContentScore) {
                $topBox = $parentNode;
            }
        }
        
        // 此时，$topBox 应为已经判定后的页面内容主元素
        return $topBox;
    }


    /**
     * 获取 HTML 页面标题
     *
     * @return String
     */
    public function getTitle() {
        $split_point = ' - ';
        $titleNodes = $this->DOM->getElementsByTagName("title");

        if ($titleNodes->length 
            && $titleNode = $titleNodes->item(0)) {
            // @see http://stackoverflow.com/questions/717328/how-to-explode-string-right-to-left
            $title  = trim($titleNode->nodeValue);
            $result = array_map('strrev', explode($split_point, strrev($title)));
            return sizeof($result) > 1 ? array_pop($result) : $title;
        }

        return null;
    }


    /**
     * Get Leading Image Url
     *
     * @return String
     */
    public function getLeadImageUrl($node) {
        $images = $node->getElementsByTagName("img");

        if ($images->length && $leadImage = $images->item(0)) {
            return $leadImage->getAttribute("src");
        }

        return null;
    }


    /**
     * 获取页面的主要内容（Readability 以后的内容）
     *
     * @return Array
     */
    public function getContent() {
        if (!$this->DOM) return false;

        // 获取页面标题
        $ContentTitle = $this->getTitle();

        // 获取页面主内容
        $ContentBox = $this->getTopBox();
        
        //Check if we found a suitable top-box.
        if($ContentBox === null)
            throw new RuntimeException(textextract::MESSAGE_CAN_NOT_GET);
        
        // 复制内容到新的 DOMDocument
        $Target = new DOMDocument;
		$Target->substituteEntities = false;
        $Target->appendChild($Target->importNode($ContentBox, true));

        // 删除不需要的标签
        foreach ($this->junkTags as $tag) {
            $Target = $this->removeJunkTag($Target, $tag);
        }

        // 删除不需要的属性
        foreach ($this->junkAttrs as $attr) {
            $Target = $this->removeJunkAttr($Target, $attr);
        }

        $content = $Target->saveHTML();
		//$content = mb_convert_encoding($Target->saveHTML(), textextract::DOM_DEFAULT_CHARSET, "HTML-ENTITIES");

        // 多个数据，以数组的形式返回
        return Array(
            'lead_image_url' => $this->getLeadImageUrl($Target),
            'word_count' => mb_strlen(strip_tags($content), textextract::DOM_DEFAULT_CHARSET),
            'title' => $ContentTitle ? $ContentTitle : null,
            'content' => $content
        );
    }

    function __destruct() { }
}

?>