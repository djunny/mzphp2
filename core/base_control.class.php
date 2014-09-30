<?php

/*
 * XiunoPHP v1.2
 * http://www.xiuno.com/
 *
 * Copyright 2010 (c) axiuno@gmail.com
 * GNU LESSER GENERAL PUBLIC LICENSE Version 3
 * http://www.gnu.org/licenses/lgpl.html
 *
 */

class base_control {
	
	// 当前应用的配置
	public $conf = array();
	
	function __construct(&$conf) {
		$this->conf = &$conf;	// 这里需要引用，因为会把一些全局需要传递的变量放进去，比如 runtime.
	}
	
	public function __get($var) {
		if($var == 'view') {
			// 传递 全局的 $conf
			$this->view = new template($this->conf);
			return $this->view;
			
		// 不建议直接在 control 直接操作 DB!!! 留给插件作者应急之用。
		// 用法：$this->db->query("SHOW STATUS");
		} elseif($var == 'db') {
			$conf = $this->conf;
			$type = $conf['db']['type'];
			$dbname = "db_$type";
			return new $dbname($conf['db'][$type]);
			
		} else {
			// 遍历全局的 conf，包含 model
			$this->$var = core::model($this->conf, $var);
			if(!$this->$var) {
				throw new Exception('Not found model:'.$var);
			}
			return $this->$var;
		}
	}
	
	public function message($msg, $jumpurl = '') {
		if(core::gpc('ajax')) {
			$arr = array('servererror'=>'', 'status'=>1, 'message'=>$msg);
			echo core::json_encode($arr);
			exit;
		} else {
			echo $msg;
			exit;
		}
	}
	
	public function __call($method, $args) {
		throw new Exception('base_control.class.php: Not implement method：'.$method.': ('.var_export($args, 1).')');
	}
}
?>