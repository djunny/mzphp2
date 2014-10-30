<?php

class DB {
	private static $db_type;
	private static $db_conf;
	private static $db_table_pre;
	
	private static $instance = NULL;
	
	public static function init_db_config(&$conf){
		self::$db_conf = $conf;
	}
	
    public static function instance(){
		if (is_null(self::$instance)){
			//find db engine
			foreach(self::$db_conf as $type=>$conf){	
				$db_enigne = $type.'_db';
				self::$db_type = $type;
				self::$db_table_pre = isset($conf['tablepre']) ? $conf['tablepre'] : '';
				self::$instance = new $db_enigne($conf);
				break;
			}
		}
		return self::$instance;
    }
	
	public static function table($table){
		self::instance();
		return (self::$db_table_pre).$table;
	}
	
	public static function query($sql, $fetch = 0){
		$query = call_user_func(array(self::instance(), 'query'), $sql);
		if($fetch){
			return self::fetch($query);
		}else{
			return $query;
		}
	}
	
	public static function fetch($query){
		return call_user_func(array(self::instance(), 'fetch_array'), $query);
	}
	
	public static function fetch_all($query){
		return call_user_func(array(self::instance(), 'fetch_all'), $query);
	}
	
	public static function fetch_array($query){
		return self::fetch($query);
	}
	
	//$table = table:field
	//example : 'table_name:*'
	public static function select($table, $where, $order, $perpage=-1, $page=1){
		if(strpos($table, ':')===false){
			$fields = '*';
		}else{
			list($table, $fields) = explode(':', $table);
		}
		if($perpage==-2){
			$fields = 'count(*) AS C';
		}
		$result = call_user_func(array(self::instance(), 'select'), self::table($table), $where, $order, $perpage, $page, $fields);
		if($perpage == -2){
			return $result[0]['C'];
		}else{
			return $result;
		}
	}
	
	public static function insert($table, $data, $return_id){
		return call_user_func(array(self::instance(), 'insert'), self::table($table), $data, $return_id);
	}
	
	public static function replace($table, $data){
		return call_user_func(array(self::instance(), 'replace'), self::table($table), $data);
	}
	
	
	public static function update($table, $data, $where){
		return call_user_func(array(self::instance(), 'update'), self::table($table), $data, $where);
	}
	
	public static function delete($table, $where){
		return call_user_func(array(self::instance(), 'delete'), self::table($table), $where);
	}
	
}

?>