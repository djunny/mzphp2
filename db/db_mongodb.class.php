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

class db_mongodb implements db_interface {

	private $conf;
	// private $wlink;	// 读写分离
	// private $rlink;	// 读写分离
	// private $xlink;	// 单点服务器
	// private wdb;
	// private rdb;
	// private xdb;		// 单点服务器
	// private $tablepre;	// 废弃，在Mongodb 里，考虑到性能，一个库只能装一个应用。
	
	private $dbname;
	
	public function __construct($conf) {
		$this->conf = $conf;
		$this->dbname = $conf['master']['name'];
		
		// 判断 Mongo 扩展存在否
		if(!extension_loaded('Mongo')) {
			throw new Exception('Mongo Extension not loaded.');
		}
	}
	
	public function __get($var) {
		$conf = $this->conf;
		if($var == 'rlink') {
			// 如果没有指定从数据库，则使用 master
			if(empty($this->conf['slaves'])) {
				$this->rlink = $this->wlink;
			} else {
				$n = rand(0, count($this->conf['slaves']) - 1);
				$conf = $this->conf['slaves'][$n];
				$this->rlink = $this->connect($conf['host'], $conf['user'], $conf['password'], $conf['name']);
			}
			return $this->rlink;
		} elseif($var == 'wlink') {
			$conf = $this->conf['master'];
			$this->wlink = $this->connect($conf['host'], $conf['user'], $conf['password'], $conf['name']);
			return $this->wlink;
		} elseif($var == 'xlink') {
			if(empty($this->conf['arbiter'])) {
				$this->xlink = $this->wlink;
			} else {
				$conf = $this->conf['arbiter'];
				$this->xlink = $this->connect($conf['host'], $conf['user'], $conf['password'], $conf['name']);
			}
			return $this->xlink;
		} elseif($var == 'wdb') {
			$this->wdb = $this->wlink->selectDB($conf['master']['name']);
			return $this->wdb;
		} elseif($var == 'rdb') {
			if(empty($this->conf['slaves'])) {
				$this->rdb = $this->wdb;
			} else {
				$this->rdb = $this->rlink->selectDB($conf['master']['name']);
			}
			return $this->rdb;
		} elseif($var == 'xdb') {
			if(empty($this->conf['arbiter'])) {
				$this->xdb = $this->wdb;
			} else {
				$this->xdb = $this->xlink->selectDB($conf['arbiter']['name']);
			}
			return $this->xdb;
		}
	}
	
	public function get($key, $db = NULL) {
		empty($db) && $db = $this->rdb;
		if(!is_array($key)) {
			list($table, $keyarr, $sqladd) = $this->parse_key($key);
			$coll = $db->selectCollection($table);
			$data = $coll->findOne($keyarr);
			$this->debug('findOne', $key, $data);
			return $data;
		} else {
			$return = array();
			foreach($key as $k) {
				$return[$k] = $this->get($k, $db);
			}
			return $return;
		}
	}
	
	public function set($key, $data, $db = NULL) {
		empty($db) && $db = $this->wdb;
		list($table, $keyarr, $sqladd) = $this->parse_key($key);
		if(is_array($data)) {
			$coll = $db->selectCollection($table);
			$data += $keyarr;
		} else {
			throw new Exception('set data must be a array.');
		}
		if(!$this->get($key)) {
			$this->debug('insert', $key, $data);
			$coll->insert($data);
		} else {
			unset($data['_id']);// mongodb 自带的id
			$this->debug('update', $key, $data);
			$coll->update($keyarr, array('$set' => $data));
		}
		return TRUE;
	}
	
	public function update($key, $data) {
		list($table, $keyarr, $sqladd) = $this->parse_key($key);
		unset($data['_id']);// mongodb 自带的id
		$this->debug('update', $key, $data);
		$coll = $this->wdb->selectCollection($table);
		$coll->update($keyarr, array('$set' => $data));
		return TRUE;
	}
	
	public function delete($key) {
		list($table, $keyarr, $sqladd) = $this->parse_key($key);
		$coll = $this->wdb->selectCollection($table);
		$this->debug('delete', $key, array());
		return $coll->remove($keyarr, true);
	}
	
	/**
	 * 
	 * maxid('user-uid') 返回 user 表最大 uid
	 * maxid('user-uid', '+1') maxid + 1, 占位，保证不会重复
	 * maxid('user-uid', 10000) 设置最大的 maxid 为 10000
	 *
	 */
	public function maxid($key, $val = FALSE) {
		$key = 'framework_maxid-'.str_replace('-', '_', $key);
		$maxid = $this->get($key, $this->xdb);
		$maxid = $maxid['data'];
		if($val === FALSE) {
			return intval($maxid);
		} elseif(is_string($val) && $val{0} == '+') {
			$val = intval($val) + $maxid;
			$this->set($key, array('data'=>$val), $this->xdb);
			return $val;
		} else {
			$this->set($key, array('data'=>$val), $this->xdb);
			return $val;
		}
	}
	
	/*
	* count('forum')
	* count('forum-fid-1')
	* count('forum-fid-2')
	* count('forum-fid-2')
	* count('forum-fid-2', 123)
	*/
	public function count($key, $val = FALSE) {
		$key = 'framework_count-'.str_replace('-', '_', $key);
		$count = $this->get($key, $this->xdb);
		$count = $count['data'];
		if($val === FALSE) {
			return $count;
		} elseif(is_string($val)) {
			if($val{0} == '+') {
				$val = intval($val);
				$count = max(0, intval($key) + $val);
				$this->set($key, array('data'=>$count), $this->xdb);
				return $count;
			} else {
				$val = abs(intval($val));
				$count = max(0, intval($count) - $val);
				$this->set($key, array('data'=>$count), $this->xdb);
				return $count;
			}
		} else {
			$this->set($key, array('data'=>$val), $this->xdb);
			return $val;
		}
	}
	
	public function truncate($table) {
		// 清除该表的统计数据
		try {
			$this->wdb->selectCollection($table)->drop();
			return TRUE;
		} catch (Exception $e) {
			return FALSE;
		}
	}
	
	public function index_fetch_id($table, $keyname, $cond = array(), $orderby = array(), $start = 0, $limit = 0) {
		$ids = array();
		$keyname = (array)$keyname;
		$arrlist = $this->index_fetch($table, $keyname, $cond, $orderby, $start, $limit);
		if(!empty($arrlist)) {
			foreach($arrlist as $k=>$arr) {
				$ids[] = $k;
			}
		}
		return $ids;
	}
	
	/*
		// LIKE 正则表达式
		array('name'=> array('LIKE' => '%m%')
		array('name'=> array('$regex' => 'm')
	*/
	public function index_fetch($table, $keyname, $cond = array(), $orderby = array(), $start = 0, $limit = 0) {
		
		$coll = $this->rdb->selectCollection($table);
		$return = array();
		
		// 处理语法
		$cond = $this->cond_to_mongo($cond);
		if($limit) {		
			$cursor = $coll->find($cond)->sort($orderby)->skip($start)->limit($limit);
		} else {
			$cursor = $coll->find($cond)->sort($orderby);
		}
		$this->debug('find', $table, $cond);
		
		while($cursor->hasNext()) {
			$data = $cursor->getNext();
			
			$keyadd = '';
			foreach($keyname as $k) {
				$keyadd .= "-$k-".$data[$k];
			}
			
			$key = $table.$keyadd;
			$return[$key] = $data;
		}  
		return $return;
	}
	
	// 原生的 count
	public function index_maxid($key) {
		list($table, $col) = explode('-', $key);
		$coll = $this->rdb->selectCollection($table);
		// todo: 未测试
		$cursor = $coll->find(array(), array("$col"=>1))->sort(array("$col"=>-1))->limit(1);
		$arr = $cursor->getNext();
		return !empty($arr["$col"]) ? intval($arr["$col"]) : 0;
	}
	
	public function index_count($table, $cond = array()) {
		$cond = $this->cond_to_mongo($cond);
		$coll = $this->rdb->selectCollection($table);
		return intval($coll->count($cond));			// 支持么？未测试
	}
	
	// 2.4 新增接口, 根据条件更新，不鼓励使用。
	public function index_update($table, $cond, $update, $lowprority = FALSE, $limit = 0) {
		$cond = $this->cond_to_mongo($cond);
		$coll = $this->wdb->selectCollection($table);
		$r = $coll->update($cond, array('$set'=>$update), array('safe'=>1, 'multiple'=>1));
		return $r['n'];
	}
	
	// 2.4 新增接口, 根据条件删除，不鼓励使用。
	public function index_delete($table, $cond, $lowprority = FALSE, $limit = 0) {
		$cond = $this->cond_to_mongo($cond);
		$coll = $this->wdb->selectCollection($table);
		$r = $coll->remove($cond);
		return $r['n'];
	}
	
	// $index = array('uid'=>1, 'dateline'=>-1)
	// $index = array('uid'=>1, 'dateline'=>-1, 'unique'=>TRUE, 'dropDups'=>TRUE)
	// 创建索引
	public function index_create($table, $index) {
		//$index = array_keys($index);
		$coll = $this->wdb->selectCollection($table);
		return $coll->ensureIndex($index);
	}
	
	// 删除索引
	public function index_drop($table, $index) {
		//$index = array_keys($index);
		$coll = $this->wdb->selectCollection($table);
		//return $coll->dropIndex($index);
		return $coll->deleteIndex($index);
	}
	
	private function cond_to_mongo($cond) {
		// 处理语法
		foreach($cond as &$v) {
			if(is_array($v)) {
				foreach($v as $k1=>$v1) {
					unset($v[$k1]);
					$k1 = str_replace(array('>=', '<=', '>', '<', 'LIKE'), array('$gte', '$lte', '$gt', '$lt', '$regex'), $k1);
					$v[$k1] = $v1;
				}
			}
		}
		return $cond;
	}
	

	// 创建表
	public function table_create($table, $cols, $engineer = '') {
		return TRUE;
	}
	
	// DROP table
	public function table_drop($table) {
		return TRUE;
	}
	
	// -------------> 私有方法
	private function connect($host, $user, $password, $name) {
		$useradd = empty($user) ? '' : "$user:$password@";
		try {
			$link = new Mongo("mongodb://{$useradd}$host/", array('timeout'=>100));//"persist"=>"onename", 
		} catch(Exception $e) {
			throw new Exception('不能连接到 Mongodb 服务器，Error:'.$e->getMessage());
			exit;
		}
		// $link->selectDB($name);
		// $conn = new Mongo("mongodb://localhost:27017,localhost:27018");   //多个服务器
		return $link;
	}
	
	/*
		in: 'forum-fid-1-uid-2'
		out: array('forum', 'fid=1 AND uid=2', array('fid'=>1, 'uid'=>2))
	*/
	private function parse_key($key) {
		$sqladd = '';
		$arr = explode('-', $key);
		$len = count($arr);
		$keyarr = array();
		for($i = 1; $i < $len; $i = $i + 2) {
			if(isset($arr[$i + 1])) {
				$sqladd .= ($sqladd ? ' AND ' : '').$arr[$i]."='".addslashes($arr[$i + 1])."'";
				$t = $arr[$i + 1];// mongodb 区分数字和字符串
				$keyarr[$arr[$i]] = is_numeric($t) ? intval($t) : $t;
			} else {
				$keyarr[$arr[$i]] = NULL;
			}
		}
		$table = $arr[0];
		if(empty($table)) {
			throw  new Exception("parse_key($key) failed, table is empty.");
		}
		/*
		if(empty($sqladd)) {
			throw  new Exception("parse_key($key) failed, sqladd is empty.");
		}
		*/
		return array($table, $keyarr, $sqladd);
	}
	
	public function __destruct() {
		if(!empty($this->wlink)) {
			$this->wlink->close();
		}
		if(!empty($this->rlink) && $this->rlink != $this->wlink) {
			$this->rlink->close();
		}
	}
	
	private function debug($operation, $key, $data) {
		if(defined('DEBUG') && DEBUG && isset($_SERVER['sqls']) && count($_SERVER['sqls']) < 1000) {
			$s = "$operation $key  array(";
			if(!empty($data)) {
				foreach($data as $k=>$v) {
					$s2 = '';
					is_string($v) && $s2 = "'$v'";
					is_array($v) && $s2 = "'".print_r($v, 1)."'";
					$s .= "'$k'=>$s2, ";
				}
				$s = substr($s, 0, -2).')';
			}
			$s .= ')';
			$_SERVER['sqls'][] = htmlspecialchars(stripslashes($s));
		}
	}
	
	public function version() {
		return '';// select version()
	}
}

?>