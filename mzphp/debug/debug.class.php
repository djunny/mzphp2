<?php
// debug class
class debug {
	public static function process(){
		if(DEBUG || DEBUG_INFO) {
			print<<<EOF
<style>
.tclass, .tclass2 {
text-align:left;width:100%;border:0;border-collapse:collapse;margin-bottom:5px;table-layout: fixed; word-wrap: break-word;background:#FFF;}
.tclass table, .tclass2 table {width:100%;border:0;table-layout: fixed; word-wrap: break-word;}
.tclass table td, .tclass2 table td {border-bottom:0;border-right:0;border-color: #ADADAD;}
.tclass th, .tclass2 th {border:1px solid #000;background:#CCC;padding: 2px;font-family: Courier New, Arial;font-size: 11px;}
.tclass td, .tclass2 td {border:1px solid #000;background:#FFFCCC;padding: 2px;font-family: Courier New, Arial;font-size: 11px;}
.tclass2 th {background:#D5EAEA;}
.tclass2 td {background:#FFFFFF;}
.firsttr td {border-top:0;}
.firsttd {border-left:none !important;}
.bold {font-weight:bold;}
</style>
<div style="display:;">
EOF;
			$warning_info = isset($_SERVER['warning_info']) ? '<br /><br />BeforePage:<br />'.$_SERVER['warning_info'] : '';
			echo '<table class="tclass2"><tr><td class="tclass"><b>Page Used Time:';
			echo core::usedtime();
			echo 'ms'.$warning_info.'</b></td></tr></table>';
			
			$class = 'tclass2';
			
			($class == 'tclass')?$class = 'tclass2':$class = 'tclass';
			if(empty($_SERVER['sqls'])) $_SERVER['sqls'] = array();
			foreach ($_SERVER['sqls'] as $dkey => $debug) {
				($class == 'tclass')?$class = 'tclass2':$class = 'tclass';
				echo '<table class="'.$class.'"><tr><th rowspan="2" width="20">'.($dkey+1).'</th><td width="60">'.$debug['time'].' ms</td><td class="bold">'.core::htmlspecialchars($debug['sql']).'</td></tr>';
				if(!empty($debug['info'])) {
					echo '<tr><td>Info</th><td>'.$debug['info'].'</td></tr>';
				}
				if(!empty($debug['explain'])) {
					if($debug['type'] == 'mysql'){
						echo '<tr><td>Explain</td><td><table cellspacing="0"><tr class="firsttr"><td width="5%" class="firsttd">id</td><td width="10%">select_type</td><td width="12%">table</td><td width="5%">type</td><td width="20%">possible_keys</td><td width="10%">key</td><td width="8%">key_len</td><td width="5%">ref</td><td width="5%">rows</td><td width="20%">Extra</td></tr><tr>';
						foreach ($debug['explain'] as $ekey => $explain) {
							($ekey == 'id')?$tdclass = ' class="firsttd"':$tdclass='';
							if(empty($explain)) $explain = '-';
							echo '<td'.$tdclass.'>'.$explain.'</td>';
						}
						echo '</tr></table></td></tr>';
					}else if ($debug['type'] == 'sqlite'){
						echo '<tr><td>Explain</td><td><table cellspacing="0"><tr class="firsttr"><td width="10%" class="firsttd">selectid</td><td width="10%">order</td><td width="20%">from</td><td width="60%">detail</td></tr><tr>';
						foreach ($debug['explain'] as $ekey => $explain) {
							($ekey == 'selectid')?$tdclass = ' class="firsttd"':$tdclass='';
							if(empty($explain)) $explain = '-';
							echo '<td'.$tdclass.'>'.$explain.'</td>';
						}
						echo '</tr></table></td></tr>';
					}
				}
				echo '</table>';
			}
			
			($class == 'tclass')?$class = 'tclass2':$class = 'tclass';
			if($files = get_included_files()) {
				($class == 'tclass')?$class = 'tclass2':$class = 'tclass';
				echo '<table class="'.$class.'">';
					foreach ($files as $ckey => $cval) {
						echo '<tr><th width="20">'.($ckey+1).'</th><td>'.$cval.'</td></tr>';
					}
				echo '</table>';
			}
			if($values = $_GET) {
				($class == 'tclass')?$class = 'tclass2':$class = 'tclass';
				$i = 1;
				echo '<table class="'.$class.'">';
					foreach ($values as $ckey => $cval) {
						echo '<tr><th width="20">'.$i.'</th><td width="250">$_GET[\''.$ckey.'\']</td><td>';
						print_r($cval);
						echo '</td></tr>';
						$i++;
					}
				echo '</table>';
			}
			if($values = $_POST) {
				($class == 'tclass')?$class = 'tclass2':$class = 'tclass';
				$i = 1;
				echo '<table class="'.$class.'">';
					foreach ($values as $ckey => $cval) {
						echo '<tr><th width="20">'.$i.'</th><td width="250">$_POST[\''.$ckey.'\']</td><td>';
						print_r($cval);
						echo '</td></tr>';
						$i++;
					}
				echo '</table>';
			}
			
			if($values = $_COOKIE) {
				($class == 'tclass')?$class = 'tclass2':$class = 'tclass';
				$i = 1;
				echo '<table class="'.$class.'">';
					foreach ($values as $ckey => $cval) {
						echo '<tr><th width="20">'.$i.'</th><td width="250">$_COOKIE[\''.$ckey.'\']</td><td>';
						print_r($cval);
						echo '</td></tr>';
						$i++;
					}
				echo '</table>';
			}
			
			unset($_SERVER['sqls']);
			if($values = $_SERVER) {
				unset($values['preg_replace_callback_arg']);
				($class == 'tclass')?$class = 'tclass2':$class = 'tclass';
				$i = 1;
				echo '<table class="'.$class.'">';
					foreach ($values as $ckey => $cval) {
						echo '<tr><th width="20">'.$i.'</th><td width="250">$_SERVER[\''.$ckey.'\']</td><td>';
						print_r($cval);
						echo '</td></tr>';
						$i++;
					}
				echo '</table>';
			}
		}

	}
	
		/*
	// 异常CODE,用来标示是哪个模块产生的异常。暂时不需要。
	const APP_EXCEPTION_CODE_ERROR_HANDLE = 1;
	const APP_EXCEPTION_CODE_MYSQL = 2;
	const APP_EXCEPTION_CODE_LOG = 3;
	*/
	public static function format_exception($e) {
		$trace = $e->getTrace();
		
		// 如果是 error_handle ，弹出第一个元素。
		if(!empty($trace) && $trace[0]['function'] == 'error_handle' && $trace[0]['class'] == 'core') {
			//array_shift($trace);
			$line = $trace[0]['args'][3];
			$file = $trace[0]['args'][2];
			$message = $trace[0]['args'][1];
		} else {
			$line = $e->getLine();
			$file = $e->getFile();
			$message = $e->getMessage();
		}
		$backtracelist = array();
		foreach($trace as $k=>$v) {
			$args = $comma = '';
			if(!empty($v['args'])) {
				if(DEBUG) {
					if($v['function'] == 'error_handle') {
						$v['class'] = '';
						$v['function'] = '';
						$args = '';
					} else {
						foreach((array)$v['args'] as $arg) {
							if(is_string($arg)) {
								$args .= $comma."'$arg'";
							} elseif(is_object($arg)) {
								$args .= $comma."Object";
							} elseif(is_array($arg)) {
								// 针对XN 优化
								if(!isset($arg['db'])) {
									$arg = print_r($arg, 1);
								} else {
									$arg = '$conf';
								}
								//$arg = str_replace(array("\t", ' '), array('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', '&nbsp;'), $arg);
								//$arg = nl2br($arg);
								$args .= $comma.$arg;
							} else {
								$args .= $comma.''.($arg === NULL ? 'NULL' : $arg);
							}
							$comma = ', ';
						}
					}
				} else {
					$args = '';
				}
			}
			!isset($v['file']) && $v['file'] = '';
			!isset($v['line']) && $v['line'] = '';
			!isset($v['function']) && $v['function'] = '';
			!isset($v['class']) && $v['class'] = '';
			!isset($v['type']) && $v['type'] = '';
			
			$backtracelist[] = array (
				'file'=>$v['file'],
				'line'=>$v['line'],
				'function'=>$v['function'],
				'class'=>$v['class'],
				'type'=>$v['type'],
				'args'=>$args,
			);
		}
		
		// array_shift($backtracelist);
		// array_shift($backtracelist);
		
		$codelist = self::get_code($file, $line);
		
		return array(
			'line'=>$line,
			'file'=>$file,
			'codelist'=>$codelist,
			'message'=>$message,
			'backtracelist'=>$backtracelist,
		);
	}
	
	public static function get_code($file, $line) {
		$arr = file($file);
		$arr2 = array_slice($arr, max(0, $line - 5), 10, true);
		if(!core::is_cmd()) {
			foreach ($arr2 as &$v) {
				$v = htmlspecialchars($v);
				$v = str_replace(' ', '&nbsp;', $v);
				$v = str_replace('	', '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $v);
			}
		}
		return $arr2;
	}
	
	public static function to_text($e) {
		return self::to_string();
	}
	
	public static function to_html($e) {
		// $s 可能是一个字符串，也可能是一个对象
		return self::to_string($e);
	}
	
	public static function to_string($e) {
		if(is_object($e)) {
			$arr = self::format_exception($e);
			$line = $arr['line'];
			$file = $arr['file'];
			$codelist = $arr['codelist'];
			$message = $arr['message'];
			$backtracelist = $arr['backtracelist'];
			
			ob_start();
			if(core::is_cmd()) {
				include FRAMEWORK_PATH.'debug/exception_cmd.htm';
			} else {
				include FRAMEWORK_PATH.'debug/exception.htm';
			}
			$s = ob_get_contents();
			ob_end_clean();
			return $s;
		} elseif(is_string($e)) {
			return $e;
		}
	}
	
	public static function to_json($e) {
		if(DEBUG) {
			$arr = self::format_exception($e);
			$backinfo = '';
			foreach($arr['backtracelist'] as $v) {
				$backinfo .= "$v[file] [$v[line]] : $v[class]$v[type]$v[function]($v[args])\r\n";
			}
			$s = "$arr[message]\r\n File: $arr[file] [$arr[line]] \r\n \r\n $backinfo";
			$s = str_replace(array('&nbsp;', '<br />', '&gt;', '&lt;'), array(' ', "\r\n", '>', '<'), $s);
			$s = preg_replace('#[\r\n]+#', "\n", $s);
		} else {
			$s = $e->getMessage();
		}
		$s = preg_replace('# \S*[/\\\\](.+?\.php)#', ' \\1', $s);
		//$s = preg_replace('#[\\x80-\\xff]{2}#', '?', $s);// 替换掉 gbk， 否则 json_encode 会报错！
		$arr = array('servererror' => $s);
		return core::json_encode($arr);
	}
}
?>