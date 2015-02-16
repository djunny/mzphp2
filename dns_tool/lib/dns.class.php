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
class dns {
	public function add_domain($site, $domain){
		$dp = $this->get_dns($site);
		$data = $dp->domain_create(array('domain'=>$domain));
		// 取得 domain_id create or get exists domain
		if(!$data['domain']['id']){
			$data = $dp->domain_info(array('domain'=>$domain));
			if(!$data['domain']['id']){
				log::info("CreateDomainFail", $site, $domain, $data);
				return 0;
			}
		}
		return $data['domain']['id'];
	}
	
	public function add_record($site, $domain_id, $sub_domain, $value, $record_type='A', $record_line='默认'){
		$dp = $this->get_dns($site);
		// 添加 record
		$record_data = array(
			'domain_id' => $domain_id,
			'sub_domain' => $sub_domain,
			'record_type' => $record_type, 
			'record_line' => $record_line,
			'value' => $value,
		);
		$data = $dp->record_create($record_data);
		if($data['status']['code'] == 1){
			log::info("CreateDomainRecordSucc", $site, $domain_id, $sub_domain, $value, $record_type, $record_line);
		}else{
			log::info("CreateDomainRecordFail", $site, $domain_id, $sub_domain, $value, $record_type, $record_line, $data);
		}
		return $data;
	}
	
	
	function get_dns($cls){
		static $instances = array();
		$cls = preg_replace('#\W+#is', '', $cls);
		if(!isset($instances[$cls])){
			$conf_file = ROOT_PATH.'conf/'.$cls.'.php';
			if(!is_file($conf_file)){
				log::info('Please ReName And Modify conf/'.$cls.'.php.default');
				exit;
			}
			$conf = $this->get_conf($conf_file);
			$instances[$cls] = new $cls($conf);
		}
		return $instances[$cls];
	}
	
	function get_conf($file){
		if(!is_file($file)){
			return '';
		}
		$fp = fopen($file, 'r+');
		fgets($fp);
		$body = array();
		while(!feof($fp)){
			$line = trim(fgets($fp));
			if(!$line || $line[0] == '#'){
				continue;
			}
			$line = explode('=', $line, 2);
			$body[$line[0]] = $line[1];
		}
		fclose($fp);
		return $body;
	}
}
?>