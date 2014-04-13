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

class db_saekv implements db_interface {

	private $conf;
	public $tablepre;	// 方便外部读取
	
	public function __construct($conf) {
		$this->conf = $conf;
	}
		
	public function __get($var) {
		if($var == 'saekv') {
			$this->saekv = new SaeKV();
			$this->saekv->init();
			return $this->saekv;
		}
	}
	
	/**
		get('user-uid-123');
		get('user-fid-123-uid-123');
		get(array(
			'user-fid-123-uid-111',
			'user-fid-123-uid-222',
			'user-fid-123-uid-333'
		));
		
		返回：
		array('uid'=>134, 'username'=>'abc')
		或:
		array(
			'user-uid-123'=>array('uid'=>123, 'username'=>'abc')
			'user-uid-234'=>array('uid'=>234, 'username'=>'bcd')
		)
	
	*/
	public function get($key) {
		if(!is_array($key)) {
			$s = $this->saekv->get($key);
			return core::json_decode($s);
		} else {
			$s = $this->saekv->mget($key);
			return core::json_decode($s);
		}
	}
	
	// insert & update 整行更新
	public function set($key, $data) {
		return $this->saekv->set($key, core::json_encode($data));
	}
	
	// update 整行更新
	public function update($key, $data) {
		return $this->saekv->set($key, core::json_encode($data));
	}

	public function delete($key) {
		return $this->saekv->delete($key);
	}
	
	/**
	 * 
	 * maxid('user-uid') 返回 user 表最大 uid
	 * maxid('user-uid', '+1') maxid + 1, 占位，保证不会重复
	 * maxid('user-uid', 10000) 设置最大的 maxid 为 10000
	 *
	 */
	public function maxid($key, $val = FALSE) {
		list($table, $col) = explode('-', $key);
		$maxidkey = "kv_maxid_{$table}";
		if($val === FALSE) {
			return $this->saekv->get($maxidkey);
		} elseif(is_string($val) && $val{0} == '+') {
			$val = intval($val);
			$maxid = intval($this->saekv->get($maxidkey)) + $val;
			$this->saekv->set($maxidkey, $maxid);
			return $maxid;
		} else {
			$this->saekv->set($maxidkey, $val);
			return $val;
		}
	}
	
	public function count($key, $val = FALSE) {
		$countkey = "kv_count_{$key}";
		if($val === FALSE) {
			return $this->saekv->get($countkey);
		} elseif(is_string($val) && $val{0} == '+') {
			$val = intval($val);
			$count = intval($this->saekv->get($countkey)) + $val;
			$this->saekv->set($countkey, $count);
			return $count;
		} else {
			$this->saekv->set($countkey, $val);
			return $val;
		}
	}
	
	public function truncate($table) {
		$arr = $this->saekv->pkrget($table.'-', 200);
		if($arr) {
			foreach($arr as $k) {
				$this->saekv->delete($k);
			}
		}
		return TRUE;
	}
	
	public function version() {
		return 1;
	}

	public function index_fetch($table, $keyname, $cond = array(), $orderby = array(), $start = 0, $limit = 0) {
		return array();
	}
	
	public function index_fetch_id($table, $keyname, $cond = array(), $orderby = array(), $start = 0, $limit = 0) {
		return array();
	}
	
	public function index_update($table, $cond, $update, $lowprority = FALSE) {
		return 0;
	}
	
	public function index_delete($table, $cond, $lowprority = FALSE) {
		return 0;
	}
	
	public function index_maxid($key) {
		return $this->maxid($key);
	}
	
	public function index_count($table, $cond = array()) {
		return $this->count($table);
	}
	
	public function index_create($table, $index) {
		return 0;
	}
	
	// 删除索引
	public function index_drop($table, $index) {
		return 0;
	}
}
?>