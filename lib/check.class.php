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

class check {
	static function is_email($s) {
		return preg_match("/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/", $s);
	}
	
	static function is_url($s) {
		return preg_match('#^(https?://[^\'"\\\\<>:\s]+(:\d+)?)?([^\'"\\\\<>:\s]+?)*$#is', $s);  //url已http://开头  i 忽略大小写
	}
	
	static function is_qq($s) {
		return preg_match('#^\d+$#', $s);
	}
	
	static function is_tel($s) {
		return preg_match('#^[\d\-]+$#', $s);
	}
	
	static function is_mobile($s) {
		return preg_match('#^\d{11}$#', $s);
	}
	
	static function is_version($s) {
		return preg_match('#^\d+(\.\d+)+$#', $s);
	}
	
	
}

/** 用法

Check::check_email();

*/

?>