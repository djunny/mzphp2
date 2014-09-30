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

class db_pdo_sqlite implements db_interface {

	private $conf;
	//private $link;	// 不支持主从，一个连接足够。
	public $tablepre;	// 方便外部读取
	
	public function __construct($conf) {
		$this->conf = $conf['master'];
		$this->tablepre = $this->conf['tablepre'];
	}
		
	public function __get($var) {
		$conf = $this->conf;
		if($var == 'link') {
			$conf = $this->conf;
			$this->link = $this->connect($conf['host']);
			return $this->link;
		}
	}
	
	// 仅安装时使用，接口中并未定义
	public function connect($host) {//为满足sqlite特性 $host修改为 "BBS_PATH/xxx"
		$sqlitedb = "sqlite:$host";
		try {
			$link = new PDO($sqlitedb);//连接sqlite
			$link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (Exception $e) {
	        	throw new Exception('连接数据库服务器失败:'.$e->getMessage());
	        }
	        //$link->setFetchMode(PDO::FETCH_ASSOC);
		return $link;
	}
	
	public function get($key) {
		if(!is_array($key)) {
			list($table, $keyarr, $sqladd) = $this->parse_key($key);
			$tablename = $this->tablepre.$table;
       			return $this->fetch_first("SELECT * FROM $tablename WHERE $sqladd");
		} else {
			// 此处可以递归调用，但是为了效率，单独处理
			$sqladd = $_sqladd = $table =  $tablename = '';
			$data = $return = $keyarr = array();
			$keys = $key;
			foreach($keys as $key) {
				$return[$key] = array();	// 定序，避免后面的 OR 条件取出时顺序混乱
				list($table, $keyarr, $_sqladd) = $this->parse_key($key);
				$tablename = $this->tablepre.$table;
				$sqladd .= "$_sqladd OR ";
			}
			$sqladd = substr($sqladd, 0, -4);
			if($sqladd) {
				$sql = "SELECT * FROM $tablename WHERE $sqladd";
				$result = $this->query($sql);
				$result->setFetchMode(PDO::FETCH_ASSOC);
				$datalist = $result->fetchAll();
				foreach($datalist as $data) {
					$keyname = $table;
					foreach($keyarr as $k=>$v) {
						$keyname .= "-$k-".$data[$k];
					}
					$return[$keyname] = $data;
				}
			}
			return $return;
		}
	}
	
	// insert -> replace
	public function set($key, $data) {
		list($table, $keyarr, $sqladd) = $this->parse_key($key);
		$tablename = $this->tablepre.$table;
		if(is_array($data)) {
			// 覆盖主键的值
			$data += $keyarr;
			$s = $this->arr_to_sqladd($data);
			// 判断是否存在，如果存在，则更新
			$exists = $this->get($key);
			if(empty($exists)) {
				return $this->query("INSERT INTO $tablename($s[key]) VALUES ($s[values])");
			} else {
				return $this->update($key, $data);
			}
		} else {
			return FALSE;
		}
	}
	
	public function update($key, $data) {
		list($table, $keyarr, $sqladd) = $this->parse_key($key);
		$tablename = $this->tablepre.$table;
		$s = $this->arr_to_sqladd($data);
		return $this->query("UPDATE $tablename SET $s[sqldata] WHERE $sqladd");
	}
	
	public function delete($key) {
		list($table, $keyarr, $sqladd) = $this->parse_key($key);
		$tablename = $this->tablepre.$table;
		return $this->query("DELETE FROM $tablename WHERE $sqladd");
	}
	
	/**
	 * 
	 * maxid('user-uid') 返回 user 表最大 userid
	 * maxid('user-uid', '+1') maxid + 1, 占位，保证不会重复
	 * maxid('user-uid', 10000) 设置最大的 maxid 为 10000
	 *
	 */
	public function maxid($key, $val = FALSE) {
		list($table, $col) = explode('-', $key);
		$maxid = $this->table_maxid($key);
		if($val === FALSE) {
			return $maxid;
		} elseif(is_string($val) && $val{0} == '+') {
			$val = intval($val);
			$this->query("UPDATE {$this->tablepre}framework_maxid SET maxid=maxid+'$val' WHERE name='$table'", $this->link);
			return $maxid += $val;
		} else {
			$this->query("UPDATE {$this->tablepre}framework_maxid SET maxid='$val' WHERE name='$table'", $this->link);
			// ALTER TABLE Auto_increment 这个不需要改，REPLACE INTO 直接覆盖
			return $val;
		}
		
	}
	public function count($key, $val = FALSE) {
		$count = $this->table_count($key);
		if($val === FALSE) {
			return $count;
		} elseif(is_string($val)) {
			if($val{0} == '+') {
				$val = $count + abs(intval($val));
				$this->query("UPDATE {$this->tablepre}framework_count SET count='$val' WHERE name='$key'", $this->link);
				return $val;
			} else {
				$val = max(0, $count - abs(intval($val)));
				$this->query("UPDATE {$this->tablepre}framework_count SET count='$val' WHERE name='$key'", $this->link);
				return $val;
			}
		} else {
			$this->query("UPDATE {$this->tablepre}framework_count SET count='$val' WHERE name='$key'", $this->link);
			return $val;
		}
	}
	
	public function truncate($table) {//sqlite清空方法,为保持清楚数据时主索引只归0,修改sqlite系统表下面的sqlite_sequence (seq)值；(系统表不可见
		$table = $this->tablepre.$table;
		try {
			$this->query("DELETE FROM $table");
		} catch (Exception $e) {}
	}

	public function index_fetch($table, $keyname, $cond = array(), $orderby = array(), $start = 0, $limit = 0) {
		$keynames = $this->index_fetch_id($table, $keyname, $cond, $orderby, $start, $limit);
		if(!empty($keynames)) {
			return $this->get($keynames);			
		} else {
			return array();
		}
	}
	
	public function index_fetch_id($table, $keyname, $cond = array(), $orderby = array(), $start = 0, $limit = 0) {
		$tablename = $this->tablepre.$table;
		$keyname = (array)$keyname;
		$sqladd = implode(',', $keyname);
		$s = "SELECT $sqladd FROM $tablename";
		$s .= $this->cond_to_sqladd($cond);
		if(!empty($orderby)) {
			$s .= ' ORDER BY  ';
			$comma = '';
			foreach($orderby as $k=>$v) {
				$s .= $comma."$k ".($v == 1 ? ' ASC ' : ' DESC ');
				$comma = ',';
			}
		}
		$s .= ($limit ? " LIMIT $start, $limit" : '');
		$sql = $s;
		$result = $this->query($sql);
		
		if(!$result) {
			return array();
		}
		$result->setFetchMode(PDO::FETCH_ASSOC);
		$return = array();
		$datalist = $result->fetchAll();
		foreach($datalist as $data) {
			$keyadd = '';
			foreach($keyname as $k) {
				$keyadd .= "-$k-".$data[$k];
			}
			$return[] = $table.$keyadd;
		}
		return $return;
	}
	
	// 原生的 count
	public function index_maxid($key) {
		list($table, $col) = explode('-', $key);
		$tablename = $this->tablepre.$table;
		$arr = $this->fetch_first("SELECT MAX($col) AS num FROM $tablename");
		if(empty($arr)) {
			throw new Exception("get maxid from $tablename Failed!");
		}
		return isset($arr['num']) ? intval($arr['num']) : 0;
	}
	
	// 原生的 count
	public function index_count($table, $cond = array()) {
		$tablename = $this->tablepre.$table;
		$where = $this->cond_to_sqladd($cond);
		$arr = $this->fetch_first("SELECT COUNT(*) AS num FROM $tablename $where");
		if(empty($arr)) {
			throw new Exception("get count from $tablename Failed!");
		}
		return isset($arr['num']) ? intval($arr['num']) : 0;
	}
	
	// 根据条件更新，不鼓励使用。
	public function index_update($table, $cond, $update, $lowprority = FALSE) {
		$where = $this->cond_to_sqladd($cond);
		$set = $this->arr_to_sqladd($update);
		$table = $this->tablepre.$table;
		$sqladd = $lowprority ? 'LOW_PRIORITY' : '';
		return $this->exec("UPDATE $sqladd $table SET $set[sqldata] $where", $this->link);
	}
	
	// 根据条件删除，不鼓励使用。
	public function index_delete($table, $cond, $lowprority = FALSE) {
		$where = $this->cond_to_sqladd($cond);
		$table = $this->tablepre.$table;
		$sqladd = $lowprority ? 'LOW_PRIORITY' : '';
		return $this->exec("DELETE $lowprority FROM $table $where", $this->link);
	}
	
	public function index_create($table, $index) {
		$table = $this->tablepre.$table;
		$keys = implode(', ', array_keys($index));
		$keyname = implode('', array_keys($index));
		return $this->query("CREATE INDEX {$table}_$keyname ON $table($keys)", $this->link);
	}
	
	public function index_drop($table, $index) {
		$table = $this->tablepre.$table;
		$keys = implode(', ', array_keys($index));
		$keyname = implode('', array_keys($index));
		return $this->query("DROP INDEX {$table}_$keyname", $this->link);
	}
	
	// 创建表
	public function table_create($table, $cols, $engineer = '') {
		$sql = "CREATE TABLE IF NOT EXISTS {$this->tablepre}$table (\n";
		$sep = '';
		foreach($cols as $col) {
			if(strpos($col[1], 'int') !== FALSE) {
				$sql .= "$sep$col[0] $col[1] NOT NULL DEFAULT '0'";
			} else {
				$sql .= "$sep$col[0] $col[1] NOT NULL DEFAULT ''";
			}
			$sep = ",\n";
		}
		$sql .= ")";
		return $this->query($sql, $this->wlink);
	}

	// DROP table
	public function table_drop($table) {
		$sql = "DROP TABLE IF EXISTS {$this->tablepre}$table";
		return $this->query($sql, $this->wlink);
	}
	
	// -----------------> 非接口方法
	
	// 返回的是结果集，判断是否为写入
	public function query($sql, $link = NULL) {
		log::trace($sql);
		empty($link) && $link = $this->link;
		$type = strtolower(substr($sql, 0, 4));
		if($type == 'sele' || $type == 'show') {
			$result = $link->query($sql);
			defined('DEBUG') && DEBUG && isset($_SERVER['sqls']) && count($_SERVER['sqls']) < 1000 && $_SERVER['sqls'][] = htmlspecialchars(stripslashes($sql));// fixed: 此处导致的轻微溢出后果很严重，已经修正。
		} else {
			$result = $link->exec($sql);
		}
		if($result === FALSE) {
			$error = $link->errorInfo();
			throw new Exception('Sqlite Query Error:'.$sql.' '.(isset($error[2]) ? "Errstr: $error[2]" : ''));
		}
		return $result;
	}
	
	// 返回行数
	public function exec($sql) {
		$n = $this->link->exec($sql);
		defined('DEBUG') && DEBUG && isset($_SERVER['sqls']) && count($_SERVER['sqls']) < 1000 && $_SERVER['sqls'][] = htmlspecialchars(stripslashes($sql));// fixed: 此处导致的轻微溢出后果很严重，已经修正。
		return $n;
	}
	
	private function cond_to_sqladd($cond) {
		$s = '';
		if(!empty($cond)) {
			$s = ' WHERE ';
			foreach($cond as $k=>$v) {
				if(!is_array($v)) {
					$v = $this->addslashes($v);
					$s .= "$k = '$v' AND ";
				} else {
					foreach($v as $k1=>$v1) {
						$v1 = $this->addslashes($v1);
						$k1 == 'LIKE' && ($k1 = ' LIKE ') && $v1 = "%$v1%";
						$s .= "$k$k1'$v1' AND ";
					}
				}
			}
			$s = substr($s, 0, -4);
		}
		return $s;
	}
	
	private function arr_to_sqladd($arr) {// key and value 分离  sqlite insert into 专用格式(insert into tablename (key, key, key) values ('values', 'values', 'values'))
		$s = array();
		$sqlkey = '';
		$sqlvalue = '';
		$sqldata = '';
		foreach($arr as $k=>$v) {
			$v = $this->addslashes($v);
			$sqlkey .= (empty($sqlkey) ? '' : ',')."$k";
			$sqlvalue .= (empty($sqlvalue) ? '' : ',')."'$v'";
			$sqldata .= (empty($sqldata) ? '' : ',')."$k='$v'";
		}
		$s['key'] = $sqlkey;
		$s['values'] = $sqlvalue;
		$s['sqldata'] = $sqldata;
		return $s;
	}
	
	public function fetch_first($sql, $link = NULL) {
		$result = $this->query($sql);
		if($result) {
			$result->setFetchMode(PDO::FETCH_ASSOC);
			return $result->fetch();
		} else {
			$error = $link->errorInfo();
			throw new Exception("Errno: $error[0], Errstr: $error[2]");
		}
	}
	
	public function fetch_all($sql, $link = NULL) {
		$result = $this->query($sql);
		if($result) {
			$result->setFetchMode(PDO::FETCH_ASSOC);
			$return = array();
			$datalist = $result->fetchAll();
			return $datalist;
		} else {
			$error = $link->errorInfo();
			throw new Exception("Errno: $error[0], Errstr: $error[2]");
		}
	}
	
	/*
		例子：
		table_count('forum');
		table_count('forum-fid-1');
		table_count('forum-fid-2');
		table_count('forum-stats-12');
		table_count('forum-stats-1234');
		
		返回：总数值
	*/
	private function table_count($key) {
		$count = 0;
		try {
			$arr = $this->fetch_first("SELECT count FROM {$this->tablepre}framework_count WHERE name='$key'", $this->link);
			if($arr === FALSE) {
				$this->query("INSERT INTO {$this->tablepre}framework_count (name, count) VALUES ('$key', 0)", $this->link);
			} else {
				$count = intval($arr['count']);
			}
		} catch (Exception $e) {
			if(strpos($e->getMessage(), 'no such table') !== FALSE) {
				$this->query("CREATE TABLE {$this->tablepre}framework_count (
					`name` char(32) NOT NULL default '',
					`count` int(11) NOT NULL default '0',
					PRIMARY KEY (`name`)
					)", $this->link);
				$this->query("INSERT INTO {$this->tablepre}framework_count (name, count) VALUES ('$key', 0)", $this->link);
			} else {
				throw new Exception($e->getMessage());
			}
		}
		return $count;
	}
	
	/*
		例子：只能为表名
		table_maxid('forum-fid');
		table_maxid('thread-tid');
	*/
	private function table_maxid($key) {
		list($table, $col) = explode('-', $key);
		$maxid = 0;
		try {
			$arr = $this->fetch_first("SELECT maxid FROM {$this->tablepre}framework_maxid WHERE name='$table'", $this->link);
			if($arr === FALSE) {
				$arr = $this->fetch_first("SELECT MAX($col) as maxid FROM {$this->tablepre}$table", $this->link);
				$maxid = $arr['maxid'];
				$this->query("INSERT INTO {$this->tablepre}framework_maxid (name, maxid) VALUES ('$table', '$maxid')", $this->link);
			} else {
				$maxid = intval($arr['maxid']);
			}
			
		} catch (Exception $e) {
			$r = $this->query("CREATE TABLE {$this->tablepre}framework_maxid (
				name char(32) NOT NULL default '',
				maxid int(11) NOT NULL default '0',
				PRIMARY KEY (`name`)
				)", $this->link);
			$arr = $this->fetch_first("SELECT MAX($col) as maxid FROM {$this->tablepre}$table", $this->link);
			$maxid = $arr['maxid'];
			$this->query("INSERT INTO {$this->tablepre}framework_maxid (name, maxid) VALUES ('$table', '$maxid')", $this->link);
		}
		return $maxid;
	}
	
	private function error($link) {    
		if($link->errorCode() != '00000') {    
			$error = $link->errorInfo();    
			return $error[2];    
		}
		return 0;
	}
	
	private function parse_key($key) {
		$sqladd = '';
		$arr = explode('-', $key);
		$len = count($arr);
		$keyarr = array();
		for($i = 1; $i < $len; $i = $i + 2) {
			if(isset($arr[$i + 1])) {
				$sqladd .= ($sqladd ? ' AND ' : '').$arr[$i]."='".$this->addslashes($arr[$i + 1])."'";
				$t = $arr[$i + 1];// mongodb 识别数字和字符串
				$keyarr[$arr[$i]] = is_numeric($t) ? intval($t) : $t;
			} else {
				$keyarr[$arr[$i]] = NULL;
			}
		}
		$table = $arr[0];
		if(empty($table)) {
			throw  new Exception("parse_key($key) failed, table is empty.");
		}
		if(empty($sqladd)) {
			throw  new Exception("parse_key($key) failed, sqladd is empty.");
		}
		return array($table, $keyarr, $sqladd);
	}
	
	public function __destruct() {
		if(isset($this->link)) {
			$this->link = NULL;
		}
		if(isset($this->link) && $this->link != $this->link) {
			$this->link = NULL;
		}
	}
	
	public function version() {
		return '';// select version()
	}
	
	private function addslashes($s) {
		$s = str_replace('\'', '\'\'', $s);
		/*$s = str_replace('/', '//', $s);
		$s = str_replace('[', '/[', $s);
		$s = str_replace(']', '/]', $s);
		$s = str_replace('%', '/%', $s);
		$s = str_replace('&', '/&', $s);
		$s = str_replace('_', '/_', $s);
		$s = str_replace('(', '/(', $s);
		$s = str_replace(')', '/)', $s);*/
		return $s;
	}
	
}
?>