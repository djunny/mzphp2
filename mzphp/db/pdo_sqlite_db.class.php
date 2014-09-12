<?php

//可能没有安装 PDO 用常量来表示
define('PDO_SQLITE_FETCH_ASSOC', 2);

class pdo_sqlite_db {
	
	var $querynum = 0;
	var $link;
	var $charset;
	var $init_db = 0;
	
	function __construct(&$db_conf) {
		if(!class_exists('PDO')){
			die('PDO extension was not installed!');
		}
		$this->connect($db_conf);
	}
	/*
		
	*/
	function connect(&$db_conf) {
		if($this->init_db){
			return;
		}
		$sqlitedb = "sqlite:{$db_conf['host']}";
		try {
			$link = new PDO($sqlitedb);//连接sqlite
			$link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (Exception $e) {
	        exit('[pdo_sqlite]cant connect sqlite:'.$e->getMessage().$sqlitedb);
	    }
		$this->link = $link;
		return $link;
	}
	
	
	// 返回行数
	public function exec($sql, $link = NULL) {
		empty($link) && $link = $this->link;
		$n = $link->exec($sql);
		return $n;
	}

	function query($sql) {
		if(DEBUG) {
			$sqlstarttime = $sqlendttime = 0;
			$mtime = explode(' ', microtime());
			$sqlstarttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
		}
		$link = &$this->link;
		
		$type = strtolower(substr(trim($sql), 0, 4));
		if($type == 'sele' || $type == 'show') {
			$result = $link->query($sql);
		} else {
			$result = $this->exec($sql, $link);
		}
		
		if(DEBUG) {
			$mtime = explode(' ', microtime());
			$sqlendttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
			$sqltime = round(($sqlendttime - $sqlstarttime), 3);
			$explain = array();
			$info = array();
			if($result && $type == 'sele') {
				$explain = $this->fetch_array($link->query('EXPLAIN QUERY PLAN '.$sql));
			}
			$_SERVER['sqls'][] = array('sql'=>$sql, 'type'=>'sqlite', 'time'=>$sqltime, 'info'=>$info, 'explain'=>$explain);
		}
		
		if($result === FALSE) {
			$error = $this->error();
			throw new Exception('[pdo_sqlite]Query Error:'.$sql.' '.(isset($error[2]) ? "Errstr: $error[2]" : ''));
		}
		$this->querynum++;
		
		return $result;
	}
	
	function fetch_array(&$query, $result_type = PDO_SQLITE_FETCH_ASSOC/*PDO::FETCH_ASSOC*/) {
		return $query->fetch($result_type);
	}
	
	function fetch_all(&$query, $result_type = PDO_SQLITE_FETCH_ASSOC){
		return $query->fetchAll($result_type);
	}

	function result(&$query){
		return $query->fetchColumn(0);
	}

	function affected_rows() {
		return $this->link->rowCount();
	}
	

	function error() {
		return (($this->link) ? $this->link->errorInfo() : 0);
	}

	function errno() {
		return intval(($this->link) ? $this->link->errorCode() : 0);
	}


	function insert_id() {
		return ($id = $this->link->lastInsertId()) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
	}

	
	//simple select
	function select($table, $where, $perpage = 20, $page = 1, $fields = array()){
		$where_sql = $this->build_where_sql($where);
		$selectsql = '*';
		if(is_array($fields)){
			$selectsql = implode(',', $fields);
		}else{
			$selectsql = $fields;
		}
		$start = ($page - 1) * $perpage;
		$fetch_first = $perpage == 0 ? true : false;
		$fetch_all = $perpage == -1 ? true : false;
		$limit_sql = '';
		if(!$fetch_first && !$fetch_all){
			$limit_sql = ' LIMIT '.$start.','.$perpage;
		}
		
		
		$sql = 'SELECT '.$selectsql.' FROM '.$table.' '.$where_sql.$limit_sql;
		$query = $this->query($sql);;
		if($fetch_first){
			return $this->fetch_array($query);
		}else{
			return $this->fetch_all($query);
		}
	}
	
	
	// insert and replace
	function insert($table, $data, $return_id){
		$data_sql = $this->build_insert_sql($data);
		if(!$data_sql){
			return 0;
		}
		$sql = 'INSERT INTO '.$table.' '.$data_sql;
		$this->query($sql);
		return $this->insert_id();
	}
	
	
	// update
	function update($table, $data, $where){
		$data_sql = $this->build_set_sql($data);
		$where_sql = $this->build_where_sql($where);
		if($where_sql){
			$sql = 'UPDATE '.$table.' SET '.$data_sql.$where_sql;
			return $this->query($sql);
		}else{
			return 0;
		}
	}
	
	// delete
	function delete($table, $where){
		$where_sql = $this->build_where_sql($where);
		if($where_sql){
			$sql = 'DELETE FROM '.$table.$where_sql;
			return $this->query($sql);
		}else{
			return 0;
		}
	}

	// build where sql
	function build_where_sql($where){
		$where_sql = '';
		if(is_array($where)){
			foreach($where as $key=>$value){
				if(is_array($value)){
					$where_sql .= ' AND '.$key.' IN (\''.implode("', '", $value).'\')';
				}else{
					$where_sql .= ' AND '.$key.' = \''.$value.'\'';
				}
			}
		}else if($where){
			$where_sql = ' AND '.$where;
		}
		return $where_sql ? ' WHERE 1 '.$where_sql : '';
	}
	// build where sql
	function build_set_sql($data){
		$setkeysql = $comma = '';
		foreach ($data as $set_key => $set_value) {
			$setkeysql .= $comma.'`'.$set_key.'`=\''.$set_value.'\'';
			$comma = ',';
		}
		return 'SET '.$setkeysql;
	}	
	
	
	// build where sql
	function build_insert_sql($data){
		$setkeyvar = $setkeyval = $comma = '';
		foreach ($data as $set_key => $set_value) {
			$setkeyvar .= $comma.'`'.$set_key.'`';
			$setkeyval .= $comma.'\''.$set_value.'\'';
			$comma = ',';
		}
		return '('.$setkeyvar.') VALUES('.$setkeyval.')';
	}	

}
?>