<?php
//for example Domain.Create
/*
$dp = new dnspod(array('login_email'=>'', 'login_password'=>'xxx'));
$data = $dp->domain_create(array('domain'=>'xxx.com'));
if($data['status']['code'] != 1){
	echo "error:", $data['status']['message'];
}else{
	echo "success, domain_id=", $data['domain']['id'];
}
*/
class dnspod {
	// dnspod 默认配置
	var $config = array(
		'login_email' => '',
		'login_password' => '',
		'format' => 'json',
	);
	
	var $server_url = 'https://dnsapi.cn/';
	
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
		$api_name = strtoupper($api_array[0][0]).substr($api_array[0], 1). '.'.strtoupper($api_array[1][0]).substr($api_array[1], 1);
		$data = $this->request($api_name, $args[0]);
		return $data;
	}
	
	public function get_domain_id(&$data){
		return $data['domain']['id'];
	}

	public function request($api_name, $post){
		$url = $this->server_url.$api_name;
		$post = array_merge($post, $this->config);
		$headers = array(
			'UserAgent' => 'MZPHP DNSPOD CLIENT/1.0',
		);
		$resp = spider::POST($url, $post, $headers, 30);
		$data = json_decode($resp, 1);
		return $data;
	}
}
?>