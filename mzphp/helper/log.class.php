<?php
class log {
	public static $log_file = 0;
	public static $log_fp = 0;
	
	public static function setLogFile($file){
		self::$log_file = $file;
		self::$log_fp = fopen($file, 'a+');
	}
	
	public static function dump_var($data){
		if(is_array($data)){
			$str = '';
			foreach($data as $k=>$v){
				if(is_array($v)){
					$str .= '['.$k.'='.self::dump_var($v).']';
				}else{
					$str .= '['.$k.'='.$v.']';
				}
			}
			return $str;
		}else{
			return '['.$data.']';
		}
	}
	
	public static function info(){
		$arg_list = func_get_args();
		$log = '';
		for($i=0, $l=func_num_args(); $i<$l; $i++){
			$log .= self::dump_var($arg_list[$i]);
		}
		$log .= '['.core::usedtime()."ms]";
		$log = "[".date('H:i:s')."]". $log. "\r\n";
		if(core::is_cmd()){
			echo $log;
		}
		if(self::$log_fp){
			fputs(self::$log_fp, $log);
		}
	}
}
?>