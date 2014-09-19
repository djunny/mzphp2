<?php
class memcache_cache{
	// private $memcache;
	private $support_getmulti;
	// link
	private $link = NULL;
	
	function __construct(&$conf) {
		if(extension_loaded('Memcached')) {
			$this->link = new Memcached;
		} elseif(extension_loaded('Memcache')) {
			$this->link = new Memcache;
		} else {
			throw new Exception('Memcache Extension not loaded.');
		}
		if(!$this->link) {
			throw new Exception('PHP.ini Error: Memcache extension not loaded.');
		}
		if(strpos($conf['host'], ':') !== false){
			list($host, $port) = explode(':', $conf['host']);
		}else{
			$host = $conf['host'];
			$port = 11211;
		}
		if($this->link->connect($host, $port)) {
			$this->support_getmulti = method_exists($this->link, 'getMulti');
			return $this->link;
		} else {
			throw new Exception('Can not connect to Memcached host.');
		}
		return false;
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