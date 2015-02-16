<?php
class mzdns {
	public function add_domain($site, $domain){
		$dp = $this->get_dns($site);
		$data = $dp->domain_create(array('domain'=>$domain));
		// get domain_id create or get exists domain
		if(!$dp->get_domain_id($data)){
			$data = $dp->domain_info(array('domain'=>$domain));
			if(!$dp->get_domain_id($data)){
				log::info("CreateDomainFail", $site, $domain, $data);
				return 0;
			}
		}
		$domain_id = $dp->get_domain_id($data);
		log::info("GotDomainId", $site, $domain, $domain_id);
		return $domain_id;
	}
	
	public function edit_record($site, $domain_id, $sub_domain, $value, $extra = array()){
		$dp = $this->get_dns($site);
		if(!is_array($extra)){
			$extra = array();
		}
		// 添加 record
		$record_data = array_merge(array(
			'domain_id' => $domain_id,
			'sub_domain' => $sub_domain,
			'value' => $value,
			'record_type'=> 'A',
			'record_line'=>'默认',
		), $extra);
		// unset default host
		if($sub_domain == '@'){
			unset($record_data['sub_domain']);
		}
		// build record data
		if(method_exists($dp, 'build_record_data')){
			$record_data = $dp->build_record_data($record_data);
		}
		//record_id
		if($record_data[$dp->record_id]){
			$data = $dp->record_modify($record_data);
		}else{
			$data = $dp->record_create($record_data);
		}
		if($data['status']['code'] == 1){
			log::info("DomainRecordEditSucc", $site, $record_data);
		}else{
			log::info("DomainRecordEditFail", $site, $record_data, $data);
		}
		return $data;
	}
	
	public function __call($method, $args) {
		$dp = $this->get_dns($args[0]);
		$data = $dp->$method($args[1]);
		return $data;
	}
	
	public function get_dns($cls){
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