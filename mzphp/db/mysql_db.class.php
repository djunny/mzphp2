<?php
class mysql_db {
	
	var $querynum = 0;
	var $link;
	var $charset;
	var $init_db = 0;
	
	function __construct(&$db_conf) {
		if(!function_exists('mysql_connect')){
			die('mysql extension was not installed!');
		}
		$this->connect($db_conf);
	}
	
	function connect(&$db_conf) {
		if($this->init_db){
			return ;
		}
		if(isset($db_conf['pconnect']) && $db_conf['pconnect']) {
			$this->link = @mysql_pconnect($db_conf['host'], $db_conf['user'], $db_conf['pass']);
		} else {
			$this->link = @mysql_connect($db_conf['host'], $db_conf['user'], $db_conf['pass'], 1);
		}
		if(!$this->link) {
			exit('[mysql]Can not connect to MySQL server, error='.$this->errno().':'.$this->error());  
		}
		
		//INNODB
		if(strtoupper($db_conf['engine']) == 'INNODB') {
			mysql_query("SET innodb_flush_log_at_trx_commit=no", $this->link);
		}
		
		$version = $this->version();
		if($version > '4.1'){
			if(isset($db_conf['charset'])) {
				mysql_query("SET character_set_connection={$db_conf['charset']}, character_set_results={$db_conf['charset']}, character_set_client=binary", $this->link);
			}
			if($version > '5.0.1') {
				//mysql_query("SET sql_mode=''", $link);
			}
		}
		$this->select_db($db_conf['name'], $this->link);
		$this->init_db = 1;
	}

	function select_db($dbname) {
		return mysql_select_db($dbname, $this->link);
	}


	function query($sql, $type = '') {
		if(DEBUG) {
			$sqlstarttime = $sqlendttime = 0;
			$mtime = explode(' ', microtime());
			$sqlstarttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
		}
		static $unbuffered_exists = NULL;
		if($type == 'UNBUFFERED' && $unbuffered_exists == NULL){
			$unbuffered_exists = function_exists('mysql_unbuffered_query') ? 1 : 0;
		}
		$func = ($type == 'UNBUFFERED' && $unbuffered_exists) ? 'mysql_unbuffered_query' : 'mysql_query';
		$query = $func($sql, $this->link);
		if($query) {
	        new Exception('MySQL Query Error, error='.$this->errno().':'.$this->error());    
		}
		if(DEBUG) {
			$mtime = explode(' ', microtime());
			$sqlendttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
			$sqltime = round(($sqlendttime - $sqlstarttime), 3);
			$explain = array();
			$info = mysql_info();
			if($query && preg_match("/^(select )/i", $sql)) {
				$explain = mysql_fetch_assoc(mysql_query('EXPLAIN '.$sql, $this->link));
			}
			$_SERVER['sqls'][] = array('sql'=>$sql, 'type'=>'mysql', 'time'=>$sqltime, 'info'=>$info, 'explain'=>$explain);
		}
		$this->querynum++;
		return $query;
	}

	function fetch_array($query, $result_type = MYSQL_ASSOC) {
		return mysql_fetch_array($query, $result_type);
	}
	
	function fetch_all($query){
		$list = array();
		while($val = $this->fetch_array($query)){
			$list[] = $val;
		}
		return $list;
	}

	function affected_rows() {
		return mysql_affected_rows($this->link);
	}

	function error() {
		return ($this->link ? mysql_error($this->link) : mysql_error());
	}

	function errno() {
		return intval($this->link ? mysql_errno($this->link) : mysql_errno());
	}

	function result($query, $row=0) {
		$query = @mysql_result($query, $row);
		return $query;
	}

	function num_rows($query) {
		$query = mysql_num_rows($query);
		return $query;
	}

	function num_fields($query) {
		return mysql_num_fields($query);
	}

	function free_result($query) {
		return mysql_free_result($query);
	}

	function insert_id() {
		return ($id = mysql_insert_id($this->link)) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
	}

	function fetch_row($query) {
		$query = mysql_fetch_row($query);
		return $query;
	}

	function fetch_fields($query) {
		return mysql_fetch_field($query);
	}

	function version() {
		return mysql_get_server_info($this->link);
	}

	function close() {
		return mysql_close($this->link);
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
	function insert($table, $data, $return_id, $replace = false){
		$data_sql = $this->build_set_sql($data);
		if(!$data_sql){
			return 0;
		}
		$method = $replace ? 'REPLACE':'INSERT';
		$sql = $method.' INTO '.$table.' '.$data_sql;
		$this->query($sql);
		if($replace){
			return 0;
		}else{
			return $this->insert_id();
		}
	}
	
	// replace
	function replace($table, $data){
		return $this->insert($table, $data, 0, true);
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
}
?>