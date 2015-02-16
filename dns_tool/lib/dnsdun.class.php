<?php
//for example Domain.Create
/*
$dp = new dnsdun(array('uid'=>'123', 'api_key'=>'xxx'));
$data = $dp->domain_add(array('domain'=>'xxx.com'));
if($data['status']['code'] != 1){
	echo "error:", $data['status']['message'];
}else{
	echo "success, domain_id=", $data['domain']['id'];
}
*/
class dnsdun {
	// dnsdun 默认配置
	var $config = array(
		'uid' => '',
		'api_key' => '',
		'format' => 'json',
		'lang' => 'en',
	);
	
	var $server_url = 'https://api.dnsdun.com/?';
	
	public $domain_id = "domain_id";
	
	public $record_id = "record_id";
	
	public $record_type = "type";
	
	public $records = "records";
	
	public $record_name = 'name';
	
	function __construct($conf) {
		$this->config = array_merge($this->config, $conf);
	}
	
	public function __call($method, $args) {
		$api_array = explode("_", $method);
		$api_alias = array(
			'create' => 'add',
		);
		$data = $this->request('c='.$api_array[0].'&a='.strtr($api_array[1], $api_alias), $args[0]);
		return $data;
	}
	
	public function get_domain_id(&$data){
		return $data['domain']['id'];
	}

	public function request($api_name, $post){
		$url = $this->server_url.$api_name;
		$post = array_merge($post, $this->config);
		$headers = array(
			'UserAgent' => 'MZPHP DNSDUN CLIENT/1.0',
		);
		$resp = spider::POST($url, $post, $headers, 30);
		$data = json_decode($resp, 1);
		return $data;
	}
}
?>