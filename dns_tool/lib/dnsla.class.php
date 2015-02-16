<?php
class dnsla {
	// dnsla 默认配置
	var $config = array(
		'rtype' => 'json',
	);
	
	var $server_url = 'https://api.dns.la/api/%s.ashx';
	
	public $domain_id = "domainid";
	
	public $record_id = "recordid";
	
	public $record_type = "record_type";
	
	public $records = "datas";
	
	public $record_name = 'host';
	
	function __construct($conf) {
		$this->config = array_merge($this->config, $conf);
	}
	
	public function get_domain_id(&$data){
		return $data['data']['domainid'];
	}
	
	public function build_record_data($data){
		$data = array(
			'domainid' => $data['domain_id'],
			'host' => $data['sub_domain'],
			'recordtype' => $data['record_type'],
			'recordline' => 'Def',
			'recorddata' => $data['value'],
			'recordid' => $data[$this->record_id],
		);
		if(!$data['host']){
			unset($data['host']);
		}
		return $data;
	}

	public function __call($method, $args) {
		$api_alias = array(
			'domain_info' => 'domain_get',
			'record_modify' => 'record_edit',
		);
		$method = strtr($method, $api_alias);
		$api_array = explode("_", $method);
		$data = $this->request($api_array[0], $api_array[1], $args[0]);
		return $data;
	}

	public function request($api_name, $api_action, $post){
		/*
		dnsla 有请求限制。
		$dir = ROOT_PATH.'data/dnsla/';
		!is_dir($dir) && mkdir($dir, 0777, 1);
		$request_file = $dir.date('H').'.dat';
		$request_count = (int)file_get_contents($request_file);
		if(++$request_count >= 300){
			log::info('dnsla', 'maxRequest>=300');
		}
		file_put_contents($request_file, $request_count);
		*/
		$url = sprintf($this->server_url, $api_name);
		$post['cmd'] = $api_action;
		$post = array_merge($post, $this->config);
		$post = http_build_query($post);
		$headers = array(
			'UserAgent' => 'MZPHP DNSLA CLIENT/1.0',
		);
		log::info($url.'?'.$post);
		$resp = spider::POST($url, $post, $headers, 30);
		$data = json_decode($resp, 1);
		//code alias
		if($data['status']['code'] == 300){
			$data['status']['code'] = 1;
		}
		
		return $data;
	}
}
?>