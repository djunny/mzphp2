<?php

class VI {
	private static $instance = NULL;
    public static function instance(){
		if (is_null(self::$instance)){
			self::$instance = new template();
		}
		return self::$instance;
    }
	public static function reset(){
		self::$instance = new template();
	}
	public static function assign($var, &$val){
		self::instance()->assign($var, $val);
	}
	public static function assign_value($var, $val){
		self::instance()->assign_value($var, $val);
	}
	public static function display($control, $template, $makefile = '', $charset = ''){
		self::instance()->show($control->conf, $template, $makefile, $charset);
	}
}

?>