<?php

class CACHE {
	
	private static $cache_type = '';
	private static $cache_pre = '';
	private static $cache_conf = '';
	
	private static $instance = NULL;
	
	public static function init_cache_config(&$conf){
		self::$cache_conf = $conf;
	}
	
    public static function instance(){
		if (is_null(self::$instance)){
			//find cache engine
			foreach(self::$cache_conf as $type=>$conf){	
				$cache_enigne = $type.'_cache';
				self::$cache_type = $type;
				self::$cache_pre = isset($conf['pre']) ? $conf['pre'] : '';
				self::$instance = new $cache_enigne($conf);
				if(!self::$instance->init()){
					self::$instance = false;
				}else{
					break;
				}
			}
		}
		return self::$instance;
    }
	
	public static function key($key){
		if(is_array($key)){
			foreach($key as &$k){
				$k = self::key($k);
			}
		}else{
			$key = self::$cache_pre.$key;
		}
		return $key;
	}
	
	public static function opened(){
		return self::instance() == false ? false:true;
	}
	
	public static function get($key){
		return call_user_func(array(self::instance(), 'get'), self::key($key));
	}
	
	public static function set($key, $val, $expire = 0){
		return call_user_func(array(self::instance(), 'set'), self::key($key), $val, $expire);
	}
	
	public static function update($key, $val, $expire){
		return call_user_func(array(self::instance(), 'update'), self::key($key), $val, $expire);
	}
	
	public static function delete($key){
		return call_user_func(array(self::instance(), 'delete'), self::key($key));
	}
	
	public static function truncate($pre = ''){
		return call_user_func(array(self::instance(), 'truncate'), $pre);
	}
}

?>