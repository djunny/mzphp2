<?php

/*
 * XiunoPHP v1.2
 * http://www.xiuno.com/
 *
 * Copyright 2010 (c) axiuno@gmail.com
 * GNU LESSER GENERAL PUBLIC LICENSE Version 3
 * http://www.gnu.org/licenses/lgpl.html
 *
 */

if(!defined('FRAMEWORK_PATH')) {
	exit('FRAMEWORK_PATH not defined.');
}

/*
	doc: http://cn2.php.net/memcached.getmulti
	Memcached::getMulti
	PECL memcached >= 0.1.0

*/
class cache_memcache implements cache_interface {
	
	public $conf;
	public function __construct($conf) {
		$this->conf = $conf;
	}
	
	//private $memcache;
	private $support_getmulti;
		
	// 仅仅寻找 model 目录
	public function __get($var) {
		if($var == 'memcache') {
			// 判断 Mongo 扩展存在否
			if(extension_loaded('Memcached')) {
				$this->memcache = new Memcached;
			} elseif(extension_loaded('Memcache')) {
				$this->memcache = new Memcache;
			} else {
				throw new Exception('Memcache Extension not loaded.');
			}
			if(!$this->memcache) {
				throw new Exception('PHP.ini Error: Memcache extension not loaded.');
			}
	 		if($this->memcache->connect($this->conf['host'], $this->conf['port'])) {
	 			$this->support_getmulti = !empty($this->conf['multi']) || method_exists($this->memcache, 'getMulti');
	 			return $this->memcache;
	 		} else {
	 			throw new Exception('Can not connect to Memcached host.');
	 		}
		}
	}

	public function get($key) {
		$data = array(); 
		if(is_array($key)) {
			// 安装的时候要判断 Memcached 版本！ getMulti()
			if($this->support_getmulti) {
				$arrlist = $this->memcache->getMulti($key);
				// 会丢失 key!，补上 key
				foreach($key as $k) {
					!isset($arrlist[$k]) && $arrlist[$k] = FALSE;
				}
				return $arrlist;
			} else {
				foreach($key as $k) {
					$arr = $this->memcache->get($k);
					$data[$k] = $arr;
				}
				return $data;
			}
		} else {
			$data = $this->memcache->get($key);
			return $data;
		}
	}

	public function set($key, $value, $life = 0) {
		return $this->memcache->set($key, $value, 0, $life);
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
		return $this->memcache->delete($key);
	}
	
	public function truncate($pre = '') {
		return $this->memcache->flush();
	}
	
	public function maxid($table, $val = FALSE) {
		$key = $table.'-Auto_increment';
		if($val === FALSE) {
			return intval($this->get($key));
		} elseif(is_string($val) && $val{0} == '+') {
			$val = intval($val);
			$val += intval($this->get($key));
			$this->set($key, $val);
			return $val;
		} else {
			 $this->set($key, $val);
			 return $val;
		}
	}
	
	public function count($table, $val = FALSE) {
		$key = $table.'-Rows';
		if($val === FALSE) {
			return intval($this->get($key));
		} elseif(is_string($val)) {
			if($val{0} == '+') {
				$val = intval($val);
				$n = intval($this->get($key)) + $val;
				$this->set($key, $n);
				return $n;
			} else {
				$val = abs(intval($val));
				$n = max(0, intval($this->get($key)) - $val);
				$this->set($key, $n);
				return $n;
			}
		} else {
			$this->set($key, $val);
			return $val;
		}
	}
	
}
?>