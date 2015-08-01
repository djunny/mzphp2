<?php

class base_control {

	/**
	 * config for current
	 * @var array
	 */
	public $conf = array();

	/**
	 * @param $conf
	 */
	function __construct(&$conf) {
		$this->conf = &$conf;
	}

	/**
	 * @param $var
	 * @return template
	 * @throws Exception
	 */
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

	/**
	 * @param $method
	 * @param $args
	 * @throws Exception
	 */
	public function __call($method, $args) {
		throw new Exception('base_control.class.php: Not implement method：'.$method.': ('.var_export($args, 1).')');
	}

	/**
	 * @param string $template
	 * @param string $make_file
	 * @param string $charset
	 */
	public function show($template='', $make_file='', $charset=''){
		$template = $template ? $template : core::R('c').'_'.core::R('a').'.htm';
		return VI::display($this, $template, $make_file, $charset);
	}

	/**
	 * @param $var
	 * @param $val
	 */
	public function assign($var, &$val){
		VI::assign($var, $val);
	}

	/**
	 * @param $var
	 * @param $val
	 */
	public function assign_value($var, $val){
		VI::assign_value($var, $val);
	}
}
?>