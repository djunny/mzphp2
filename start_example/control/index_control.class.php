<?php
/*
common_control from external core path = FRAMEWORK_EXTEND_PATH
*/
class index_control extends common_control{
	
	function __construct(&$conf) {
		parent::__construct($conf);
	}
	
	function on_index(){
		$cache = CACHE::get('aaa');
		// write cache
		if($cache === false){
			CACHE::set('aaa', $_SERVER, 5);
			$cache = CACHE::get('aaa');
		}
		// read cache
		VI::assign('cache', $cache);
		
		VI::assign_value('test', 'this is VI::assign_value');
		
		VI::display($this, 'index.htm');
	}
	
	public static function process_relation($list){
		//process $list
		return $list;
	}
	
	public static function process_relation_item($item){
		$item['text'] = str_replace('魔爪', '小说', $item['text']);
		return $item;
	}
	
	function on_spider(){
		$key = C::G('key', '魔爪小说阅读器');
		$url = 'http://www.sogou.com/web?query='.urlencode($key).'&ie=utf8';
		$html = spider::fetch_url($url, '', array('Referer'=>'http://www.sogou.com/'));
		$result = spider::match($html, array(
												'relation_text' => 'DOM::#hint_container',
												'relation_list' => array(
													//selector 
													'selector'  => '.hintBox a',
													'link' => 'DOM:abs-href:',
													'text' => 'DOM:text:',
													//process_item
													'process_item' => 'index_control::process_relation_item',
													//process
													'process' => 'index_control::process_relation',
												),
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
												'first_title' => 'DOM::a[name=dttl]:first',
												'first_link' => 'name="dttl" target="_blank" href="(*)"',
											)
									, array('url'=>$url));
		// like first_summary
		$result['first_title'] = strip_tags($result['first_title']);
		VI::assign('result', $result);
		$this->show('index.htm');	
	}
	
	function on_fetch(){
		$url = C::P('url', 'http://baidu.com/');
		$html = spider::fetch_url($url);
		VI::assign('url', $url);
		VI::assign('html', $html);
		//default show index_fetch.htm
		$this->show();
	}
}
?>