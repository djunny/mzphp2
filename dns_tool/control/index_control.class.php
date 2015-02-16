<?php
/*
cmd line:
php index.php index start
*/
class index_control extends base_control{
	
	function __construct(&$conf) {
		parent::__construct($conf);
		if(core::is_cmd()){
			$this->on_start();
			exit;
		}
	}
	
	function on_index(){
		echo 'Plz run Cli CommandLine:<Br>';
		echo '<font color="blue">php index.php index</font>';
	}
	
	
	function on_start(){
		global $argv;
		$domain_list = explode("\r\n", trim(file_get_contents(ROOT_PATH.'conf/domains.txt')));
		$ip_list = explode("\r\n", trim(file_get_contents(ROOT_PATH.'conf/ips.txt')));
		if(count($domain_list) != count($ip_list)){
			log::info('DomainAndIpListNotSameCount', count($domain_list), count($ip_list));
			exit;
		}
		$mode = 'default';
		if(isset($argv[3]) && $argv[3]){
			$mode = $argv[3];
		}
		
		switch($mode){
			case 'rand':
				shuffle($ip_list);
			break;
			default:
			break;
		}
		$dns = new mzdns();
		foreach($domain_list as $domain_data){
			$arr = explode("\t", $domain_data);
			$site = $arr[0];
			$domains = explode("|", $arr[1]);
			$top_domain = $domains[0];
			//取出一条ip
			$ip = trim(array_pop($ip_list));
			if(!$ip){
				log::info('IpConfigFileError', $domain);
				continue;
			}
			$dp = $dns->get_dns($site);
			// 先添加域名 取得id.
			$domain_id = $dns->add_domain($site, $top_domain);
			if($domain_id){
				// 取得所有record
				$record_list = $dns->record_list($site, array($dp->domain_id => $domain_id, 'domain' => $top_domain));
				$record_list = $record_list[$dp->records];
				$_record = array();
				foreach($record_list as $record){
					if($record[$dp->record_type] == 'A'){
						$_record[$record[$dp->record_name]] = $record;
					}
				}
				$record_list = $_record;
				foreach($domains as $domain){
					$sub_domain = str_replace($top_domain, '', $domain);
					if(substr($sub_domain, -1, 1) == '.'){
						$sub_domain = substr($sub_domain, 0, -1);
					}
					if(!$sub_domain){
						$sub_domain = '@';
					}
					// 编辑 record
					$extra_data = array();
					if(isset($record_list[$sub_domain])){
						$record_id = $record_list[$sub_domain][$dp->record_id];
						$record_id = $record_id ? $record_id : $record_list[$sub_domain]['id'];
						$extra_data = array($dp->record_id => $record_id);
					}
					$record_data = $dns->edit_record($site, $domain_id, $sub_domain, $ip, $extra_data);
				}
			}
		}
	}
	
	
}
?>