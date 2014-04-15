<?php
class seo {
	//get keyword
	public static function get_keyword(){
		$url = core::gpc('HTTP_REFERER', 'S');
		static $config = array(
			array(
				"domain" => "google.com",
				"kw" => "q",
				"charset" => "utf-8"
			),
			array(
				"domain" => "google.cn",
				"kw" => "q",
				"charset" => "utf-8"
			),
			array(
				"domain" => "baidu.com",
				"kw" => "wd",
				"charset" => "gbk"
			),
			array(
				"domain" => "soso.com",
				"kw" => "q",
				"charset" => "utf-8"
			),
			array(
				"domain" => "yahoo.com",
				"kw" => "q",
				"charset" => "utf-8"
			),
			array(
				"domain" => "bing.com",
				"kw" => "q",
				"charset" => "utf-8"
			),
			array(
				"domain" => "sogou.com",
				"kw" => "query",
				"charset" => "gbk"
			),
			array(
				"domain" => "youdao.com",
				"kw" => "q",
				"charset" => "utf-8"
			)
		);
		$res = array();
		foreach ($config as $item) {
			if (preg_match("/\b{$item['domain']}\b/", $url)) {
				$querystr = $item['kw']."=";
				$keyword  = self::parse_keyword($url, $querystr);
				$truekey  = urldecode($keyword);
				//convert utf-8
				$truekey = self::detect_encoding($truekey, 'utf-8');
				
				$res[$item['domain']] = $truekey;
				return $res;
			}
		}
	}
	
	public static function detect_encoding($data, $to){
		$encode_arr = array('UTF-8','ASCII','GBK','GB2312','BIG5','JIS','eucjp-win','sjis-win','EUC-JP');
		$encoded = mb_detect_encoding($data, $encode_arr);
		$data = mb_convert_encoding($data,$to,$encoded);
		return $data;
	}
	 
	private static function parse_keyword($url, $kw_start){
		$start = stripos($url, $kw_start);
		$url   = substr($url, $start + strlen($kw_start));
		$start = stripos($url, '&');
		if ($start > 0) {
			$start       = stripos($url, '&');
			$keyword = substr($url, 0, $start);
		} else {
			$keyword = substr($url, 0);
		}
		return $keyword;
	}
	
	//ping to search engine
	public static function pingall(&$conf, $url){
		$pingContent = "<"."?xml version=\"1.0\" encoding=\"utf-8\"?".">".
						"<methodCall><methodName>weblogUpdates.extendedPing</methodName>".
						"<params><param><value><string>".$conf['app_name']."</string></value></param>".
						"<param><value><string>".$conf['app_url']."</string></value></param>".
						"<param><value><string>".$conf['app_url'].preg_replace('|^\/|is', '', $url)."</string></value></param>".
						"<param><value><string>".$conf['app_url']."rss</string></value></param>".
						"</params>".
						"</methodCall>";
		$res = array();
		$res['baidu'] = self::pingbaidu($pingContent);
		$res['google'] = self::pinggoogle($pingContent);
		return $res;
	}
	
	public static function pingbaidu(&$xml){
		$res = self::pingurl('http://ping.baidu.com/ping/RPC2', $xml);
		echo $res;
		return strpos($res, "<int>0</int>") ? true : false;
	}
	
	public static function pinggoogle(&$xml){
		$res = self::pingurl('http://blogsearch.google.com/ping/RPC2', $xml);
		echo $res;
		return strpos($res, "<boolean>0</boolean>") ? true : false;
	}
	
	private static function pingurl($url, $postvar) {
		return misc::fetch_url($url, 5, $postvar, 
						array(
							'Content-Type'=>'text/xml'	
						));
	}
}

?>