<?php
class index_control extends base_control{
	
	function __construct(&$conf) {
		parent::__construct($conf);
	}
	
	function on_index(){
		
		VI::assign_value('test', 'this is VI::assign_value');
		
		VI::display($this, 'index.htm');
	}
	
	function on_spider(){
		$key = C::G('key', '魔爪小说阅读器');
		$url = 'http://www.sogou.com/web?query='.urlencode($key).'&ie=utf8';
		$html = spider::fetch_url($url, '', array('Referer'=>'http://www.sogou.com/'));
		
		$result = spider::match($html, array(
												'keys' => array(
													'cut' => '相关搜索</caption>(*)</tr></table>',
													'pattern' => '#id="sogou_\d+_\d+">(?<key>[^>]*?)</a>#is',
												),
												'first_summary' => array(
													'pattern' => array(
														'<!--summary_beg-->(*)<!--summary_end-->',
													),
													'process' => array(
														'spider::no_html'
													),
												),
												'first_title' => 'uigs__1">(*)</a>',
												'first_link' => 'name="dttl" target="_blank" href="(*)"',
											)
									);
		// like first_summary
		$result['first_title'] = strip_tags($result['first_title']);
		
		VI::assign('result', $result);
		
		VI::display($this, 'index.htm');
		
	}
}
?>