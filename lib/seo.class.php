<?php
class seo{
	function get_referer_keyword(){
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
				$keyword  = self::get_keyword($url, $querystr);
				$truekey  = urldecode($keyword);
				//convert utf-8
				if ($item['charset'] == "gbk") {
					$truekey = iconv('gbk', 'utf-8//IGNORE', $truekey);
				}
				$res[$item['domain']] = $keys;
				return $res;
			}
		}
	}
	
	//函数作用：从url中提取关键词。参数说明：url及关键词前的字符。 
	private static function get_keyword($url, $kw_start){
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
	
}

?>