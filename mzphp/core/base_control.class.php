<?php

class base_control {
	
	// 当前应用的配置
	public $conf = array();
	
	function __construct(&$conf) {
		$this->conf = &$conf;
	}
	public function __get($var) {
		if($var == 'view') {
			// 传递 全局的 $conf
			$this->view = new template($this->conf);
			return $this->view;	
		} else if(class_exists($var)){
			$this->$var = new $var();
			return $this->$var;
		} else {
			// 遍历全局的 conf，包含 model
			$this->$var = core::model($this->conf, $var);
			if(!$this->$var) {
				throw new Exception('Not found model:'.$var);
			}
			return $this->$var;
		}
	}
	
	public function __call($method, $args) {
		throw new Exception('base_control.class.php: Not implement method：'.$method.': ('.var_export($args, 1).')');
	}
	
	public function show($template='', $makefile='', $charset=''){
		$template = $template ? $template : core::R('c').'_'.core::R('a').'.htm';
		VI::display($this, $template, $makefile, $charset);
	}
}
?>