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

/**
 * 对配置文件进行读写，仅仅在安装，升级程序中使用。
 *
 */
class xn_conf  {
	
	private $configfile = '';
	private $content = '';
	public $kv = array();
	
	public function __construct($configfile) {
		$this->configfile = $configfile;
		if(!is_file($configfile)) {
			$this->kv = array();
		} else {
			$this->content = trim(file_get_contents($configfile));
			preg_match_all("#'(\w+)'\s*=>\s*'([^']+)'#", $this->content, $m1);
			preg_match_all('#"(\w+)"\s*=>\s*"([^"]+)"#', $this->content, $m2);
			$arr1 = empty($m1[2]) ? array() : array_combine($m1[1], $m1[2]);
			$arr2 = empty($m2[2]) ? array() : array_combine($m2[1], $m2[2]);
			$this->kv = array_merge($arr1, $arr2);
		}
	}
	
	// 保存 key值 到配置文件
	public function set($k, $v) {
		$s = &$this->content;
		
		// 如果没有，则追加
		if(!preg_match("#'$k'\s*=>#", $s)) {
			if(substr($v, 0, 5) != 'array') {
				$v = var_export($v, 1);
				$v = preg_replace('#[\\\\]+\'#', '\\\'', $v);
			}
			$s = preg_replace('#\);\s*\?>#', "\t'$k' => $v,\r\n);\r\n?>", $s);
		} else {
			if(substr($v, 0, 5) == 'array') {
				$s = preg_replace('#\''.$k.'\'\s*=>\s*array\([^)]+\),(\s*//[^\r\n]+[\r\n]+)?#is', "'$k' => $v,\\1", $s);
			} elseif(!is_string($v)){
				$s = preg_replace('#\''.$k.'\'\s*=>\s*\'?\d+\'?,(\s*//[^\r\n]+[\r\n]+)?#is', "'$k' => $v,\\1", $s);
			} else {
				$v = var_export($v, 1);
				$v = preg_replace('#[\\\\]+\'#', '\\\'', $v);
				$s = preg_replace('#\''.$k.'\'\s*=>\s*\'.*?\',(\s*//[^\r\n]+[\r\n]+)?#is', "'$k' => $v,\\1", $s);
			}
		}
		
		$this->kv[$k] = $v;
		return TRUE;
	}
	
	private function get($k) {
		return isset($this->kv[$k]) ? $this->kv[$k] : NULL;
	}
	
	public function save() {
		return file_put_contents($this->configfile, $this->content);
	}
}

?>