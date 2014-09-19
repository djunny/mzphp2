<?php
/*
if you create file cache please create crontab:
 
*/

class file_cache {
	
	//filename=pre_
	function __construct(&$conf) {
		$this->conf = &$conf;
		//.$this->conf['pre']
		$dir = $this->conf['dir'];
		if(!is_dir($dir)) {
			@mkdir($dir, 0777, true);
			if(!is_dir($dir)){
				throw new Exception('FileCache Dir cant created!');
			}
		}
		$this->conf['dir'] = $dir;
		return true;
	}
	
	private function get_file($key){
		static $dir_exists = array();
		$key_name = substr($key, -2);
		$key_path = $this->conf['dir'].$key_name.'/';
		if(isset($dir_exists[$key_path])){
			return $key_path.$key.'';
		}
		if(is_dir($key_path)){
			$dir_exists[$key_path] = 1;
			return $key_path.$key.'';
		}
		
		mkdir($key_path, 0777, true);
		
		if(is_dir($key_path)){
			$dir_exists[$key_path] = 1;
			return $key_path.$key.'';
		}else{
			throw new Exception('Cant Create FileCache Path');
		}
	}
	
	private function get_time(){
		static $time;
		if(!$time){
			$time = time();
		} 
		return $time;
	}
	
	// test json is performance
	private function gen_file_body($list){
		//return '<?php return '.self::array_eval($list, '').';';
		return json_encode($list);
	}
	
	private function get_file_data($path){
		//return include($path);
		return json_decode(file_get_contents($path), 1);
	}
	
	public function get($key) {
		$data = array();
		if(is_array($key)) {
			//get each key
			foreach($key as $k) {
				$arr = $this->get($k);
				$data[$k] = $arr;
			}
			return $data;
		} else {
			$file_path = $this->get_file($key);
			if(is_file($file_path)){
				$res = $this->get_file_data($file_path);
				//file is expired 
				if($this->get_time() >= $res['expire']) {
					$this->delete($key);
					return false;
				}else{
					return $res['body'];
				}
			}else{
				return false;
			}
		}
	}
	

	public function set($key, $value, $life = 0) {
		$file_path = $this->get_file($key);
		$life = $life == 0 ? 600 : $life;
		$res = array(
			'expire' => $this->get_time() + $life,
			'body' => &$value,
		);
		try {
			file_put_contents($file_path, $this->gen_file_body($res));
			//$link->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
			return true;
		} catch (Exception $e) {
			throw new Exception('[file_cache]Cant write file cache:'.$e->getMessage());
			return false;
		}
		
	}

	public function update($key, $value, $life = 0) {
		return $this->set($key, $value, $life);
	}

	public function delete($key) {
		$file_path = $this->get_file($key);
		return @unlink($file_path);
	}
	
	public function truncate($pre = '') {
		
	}
	
		
	//数组转换成字串
	public static function array_eval($array, $space_line = "\n", $level = 0) {
		$space_str = '';
		for($i = 0; $i <= $level; $i++) {
			$space_str .= "\t";
		}
		$evaluate = "Array{$space_line}$space_str{$space_line}(";
		$comma = $space_str;
		foreach($array as $key => $val) {
			$key = is_string($key) ? '\''.addcslashes($key, '\'\\').'\'' : $key;
			$val = !is_array($val) && (!preg_match("/^\-?\d+$/", $val) || strlen($val) > 12 || substr($val, 0, 1)=='0') ? '\''.addcslashes($val, '\'\\').'\'' : $val;
			if(is_array($val)) {
				$evaluate .= "$comma$key => ".self::array_eval($val, $space_str, $level + 1);
			} else {
				$evaluate .= "$comma$key => $val";
			}
			$comma = ",{$space_line}$space_str";
		}
		$evaluate .= "{$space_line}$space_str)";
		return $evaluate;
	}
}
?>