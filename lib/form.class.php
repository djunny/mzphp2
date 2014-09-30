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

class form {
	
	public static function get_radio_yes_no($name, $checkedkey = 0) {
		$checkedkey = intval($checkedkey);
		return self::get_radio($name, array(1=>'是', 0=>'否'), $checkedkey);
	}
	
	public static function get_checkbox_yes_no($name, $checkedkey = 0) {
		$checkedkey = intval($checkedkey);
		return self::get_checkbox($name,  array(1=>'', 0=>''), $checkedkey);
	}
	
	/**
		用法 Form::get_radio('ishidden', array('否', '是'), 0);
	*/
	public static function get_radio($name, $arr, $checkedkey = 0) {
		empty($arr) && $arr = array('否', '是');
		$s = '';
		foreach((array)$arr as $k=>$v) {
			$checked = $k == $checkedkey ? ' checked="checked"' : '';
			$s .= "<input type=\"radio\" name=\"$name\" value=\"$k\" class=\"noborder\"$checked />$v &nbsp; \r\n";
		}
		return $s;
	}
	
	public static function get_select($name, $arr, $checkedkey = 0, $id = TRUE) {
		if(empty($arr)) return '';
		$idadd = $id === TRUE ? "id=\"$name\"" : ($id ? "id=\"$id\"" : '');
		$s = "<select name=\"$name\" $idadd> \r\n";
		$s .= self::get_options($arr, $checkedkey);
		$s .= "</select> \r\n";
		return $s;
	}
	
	public static function get_options($arr, $checkedkey = 0) {
		$s = '';
		foreach((array)$arr as $k=>$v) {
			$checked = $k == $checkedkey ? ' selected="selected"' : '';
			$s .= "<option value=\"$k\"$checked>$v</option> \r\n";
		}
		return $s;
	}
	
	public static function get_checkbox($name, $arr, $ischecked) {
		$s = '';
		$checked = $ischecked ? ' checked="checked"' : '';
		$checkedtext = $arr[$ischecked];
		$s .= "<input type=\"checkbox\" name=\"$name\" value=\"1\" class=\"noborder\"$checked />$checkedtext \r\n";
		return $s;
	}
	
	public static function get_text($name, $value, $width = 150) {
		$s = "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$value\" style=\"width: {$width}px\" />";
		return $s;
	}
	
	public static function get_hidden($name, $value) {
		$s = "<input type=\"hidden\" name=\"$name\" id=\"$name\" value=\"$value\" />";
		return $s;
	}
	
	public static function get_textarea($name, $value, $width = 600,  $height = 300) {
		$s = "<textarea name=\"$name\" id=\"$name\" style=\"width: {$width}px; height: {$height}px;\">$value</textarea>";
		return $s;
	}
	
	public static function get_password($name, $value, $width = 150) {
		$s = "<input type=\"password\" name=\"$name\" id=\"$name\" value=\"$value\" style=\"width: {$width}px\" />";
		return $s;
	}
	
	public static function get_time($name, $value, $width = 150) {
		$s = "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$value\" style=\"width: {$width}px\" />";
		return $s;
	}
	
}


/**用法

$f = new Form();
echo $f->get_radio_yes_no('radio1', 0); 
echo $f->get_checkbox('aaa', array('无', '有'), 0);

echo $f->get_radio_yes_no('aaa', 0);
echo $f->get_radio('aaa', array('无', '有'), 0);
echo $f->get_radio('aaa', array('a'=>'aaa', 'b'=>'bbb', 'c'=>'ccc', ), 'b');

echo $f->get_select('aaa', array('a'=>'aaa', 'b'=>'bbb', 'c'=>'ccc', ), 'a');

*/

?>