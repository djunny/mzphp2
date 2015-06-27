<?php
class memcache_cache{
	// private $memcache;
	private $support_getmulti;
	// link
	private $link = NULL;
	// how many servers connect
	private $servers = 0;
	
	function __construct(&$conf) {
		$this->support_getmulti = false;
		if(extension_loaded('Memcached')) {
			$this->link = new Memcached;
			$this->support_getmulti = true;
		} elseif(extension_loaded('Memcache')) {
			$this->link = new Memcache;
		} else {
			throw new Exception('Memcache Extension not loaded.');
		}
		
		$hosts = $conf['host'];
		if(!is_array($hosts)){
			$hosts = explode('|', $conf['host']);
		}
		
		$this->servers = 0;
		foreach($hosts as $host){
			$host = $this->get_host_by_str($host);
			if($this->link->addServer($host['host'], $host['port'])) {
				$this->servers++;
			}
		}
		
		if($this->servers) {
			return $this->link;
		}
		
		return false;
	}
	
	private function get_host_by_str($host){
		list($host, $port) = explode(':', $host);
		return array(
			'host' => $host,
			'port' => $port ? $port : 11211,
		);
	}
	
	public function init(){
		return $this->link === false ? false : true;
	}
	
	public function get($key) {
		$data = array();
		if(is_array($key)) {
			// 安装的时候要判断 Memcached 版本！ getMulti()
			if($this->support_getmulti) {
				$arrlist = $this->link->getMulti($key);
				// 会丢失 key!，补上 key
				foreach($key as $k) {
					!isset($arrlist[$k]) && $arrlist[$k] = FALSE;
				}
				return $arrlist;
			} else {
				foreach($key as $k) {
					$arr = $this->link->get($k);
					$data[$k] = $arr;
				}
				return $data;
			}
		} else {
			$data = $this->link->get($key);
			return $data;
		}
	}

	public function set($key, $value, $life = 0) {
		return $this->link->set($key, $value, 0, $life);
	}

	public function update($key, $value) {
		$arr = $this->get($key);
		if($arr !== FALSE) {
			is_array($arr) && is_array($value) && $arr = array_merge($arr, $value);
			return $this->set($key, $arr);
		}
		return 0;
	}

	public function delete($key) {
		return $this->link->delete($key);
	}
	
	public function truncate($pre = '') {
		return $this->link->flush();
	}
	
}
?>