<?php
class VI {
	private static $instance = NULL;
	
	public static function instance(){
		static $inited = 0;;
		if (!$inited){
			self::$instance = new template();
			$inited = 1;
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