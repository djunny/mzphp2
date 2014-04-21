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

class template {

	// 全局的
	private $vars = array();			//变量表
	private $force = 1;		// 强制判断文件是否过期，会影响效率
	
	// 每个模板目录对应一个配置文件！
	
	// $abc[a][b][$c] 合法
	// $abc[$a[b]]   不合法
	private $var_regexp = "\@?\\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\$]+\])*";// \[\]
	private $vtag_regexp = "\<\?=(\@?\\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\[\]\$]+\])*)\?\>";
	private $const_regexp = "\{([\w]+)\}";
	/*private $isset_regexp = '<\?php echo isset\(.+?\) \? (?:.+?) : \'\';\?>';*/

	// 存放全局的 $conf，仅仅用来处理 hook
	public $conf = array();
	
	public $json = array();			// 模板额外输出的 json，仅在 ajax=1 时有效。
	
	private $tag_search = array();
	private $tag_replace = array();
	
	function __construct(&$conf) {
		$this->conf = &$conf;
		if(!defined('DIR')){
			define('DIR', $conf['app_dir']);
		}
	}

	// publlic
	public function assign($k, &$v) {
		$this->vars[$k] = &$v;
	}

	public function assign_value($k, $v) {
		$this->vars[$k] = $v;
	}

	// 约定必须主模板调用其他应用模板，不能反其道而行之
	public function display($file, $json = array(), $makefile='', $charset ='') {
		if(!core::gpc('ajax', 'R')) {
			extract($this->vars, EXTR_SKIP);
			$warninfo = ob_get_contents();
			//ob_end_clean();
			ob_start();
			include $this->gettpl($file);
			$body = ob_get_contents();
			ob_end_clean();
			core::process_urlrewrite($this->conf, $body);
			if($charset != 'utf-8'){
				header('Content-Type: text/html; charset='.$charset);
				$body = iconv('utf-8', $charset, $body);
			}
			if(!$makefile){
				echo $body;
				//debug
				debug::process();
			}else{
				return file_put_contents($makefile, $body);
			}
		// json 格式，约定为格式。
		} else {
			ob_start();
			extract($this->vars, EXTR_SKIP);
			include $this->gettpl($file);
			$body = ob_get_contents();
			ob_end_clean();

			$json = array('servererror'=>'', 'status'=>1, 'message'=>array('width'=>400, 'height'=>300, 'pos'=>'center', 'title'=>'默认窗口标题', 'body'=>''));
			$json['message'] = array_merge($json['message'], $this->json);
			$this->fetch_json_header($body, $json['message']);
			$json['message']['body'] = $body;
			echo core::json_encode($json);		
		}
	}
	
	public function gettpl($filename) {
		if(strpos($filename, '.') === false){
			$filename .= '.htm';
		}
		$objfile = $this->conf['tmp_path'].$this->conf['app_id'].'_view_'.$filename.'.php';
		if(!$this->force) return $objfile;
		
		// 此处可能会影响效率，如果确认所有模板的已经缓存，可以跳过此步。
		if(!is_file($objfile) || DEBUG > 0 && !IN_SAE) {
			
			// empty($_SERVER['lang']) && $_SERVER['lang'] = include $this->conf['lang_path'].'lang.php';
			
			// 模板目录搜索顺序：view_xxx/, view/, plugin/*/
			$file = '';
			if(!empty($this->conf['first_view_path'])) {
				foreach($this->conf['first_view_path'] as $path) {
					if(is_file($path.$filename)) {
						$file = $path.$filename;
						break;
					}
				}
			}
			if(empty($file) && empty($this->conf['plugin_disable'])) {
				$plugins = core::get_enable_plugins($this->conf);
				$pluginnames = array_keys($plugins);
				foreach($pluginnames as $v) {
					$path = $this->conf['plugin_path'].$v.'/';
					// 如果有相关的 app path, 这只读取该目录
					if(is_file($path.$this->conf['app_id'].'/'.$filename)) {
						$file = $path.$this->conf['app_id'].'/'.$filename;
						break;
					}
					if(is_file($path.$filename)) {
						$file = $path.$filename;
						break;
					}
				}
			}
			if(empty($file)) {
				foreach($this->conf['view_path'] as $path) {
					if(is_file($path.$filename)) {
						$file = $path.$filename;
						break;
					}
				}
			}
			if(empty($file)) {
				throw new Exception("模板文件 $filename 不存在。");
			}
			$filemtime = filemtime($file);
			if(!$filemtime) {
				throw new Exception("模板文件 $filename 最后更新时间读取失败。");
			}
			$filemtimeold = is_file($objfile) ? filemtime($objfile) : 0;
			
			//判断是否比较过期
			if($filemtimeold < $filemtime || DEBUG > 1) {
				$s = $this->complie($file);
				// 此处不锁，多个进程并发写入可能会有问题。// PHP 5.1 以后加入了 LOCK_EX 参数
				file_put_contents($objfile, $s);
			}
		}
		return $objfile;
	}
	
	public function complie($viewfile) {
		$conf = $this->conf;
		$s = file_get_contents($viewfile);
		
		// TODO 去掉JS中的注释 // ，否则JS传送会有错误
		//$s = preg_replace('#\r\n\s*//[^\r\n]*#ism', '', $s);
		//$s = str_replace('{DIR}', $this->conf['app_dir'], $s);
		
		//优化eval tag 先替换成对应标签，稍后再换回(eval中的变量会和下边变量替换冲突)
		$s = preg_replace_callback("#(<!--\{eval)\s+?(.*?)\s*\}-->#is", array($this, 'stripvtag_callback'), $s);
		//$s = preg_replace_callback("/\{eval\s+(.+?)\s*\}/is", array($this, 'stripvtag_callback'), $s);
		$s = preg_replace("/<!--\{(.+?)\}-->/s", "{\\1}", $s);
		
		// hook, 最多允许三层嵌套
		for($i = 0; $i < 4; $i++) {
			// 子模板
			if(DEBUG > 0) {
				$s = preg_replace("/<!--\{(.+?)\}-->/s", "{\\1}", $s);
				$s = preg_replace('#\{require\s+([^}]*?)\}#is', "{include \\1}", $s);
			} else {
				$s = preg_replace("/<!--\{(.+?)\}-->/s", "{\\1}", $s);
				$s = preg_replace('#\{template\s+([^}]*?)\}#is', "{require \\1}", $s);
				$s = preg_replace_callback('#\{require\s+([^}]*?)\}#is', array($this, 'requiretpl'), $s); // php5.2 支持 array(), php 5.3 支持 self::requiretpl
			}
			$s = preg_replace_callback("#(<!--\{eval)\s+?(.*?)\s*\}-->#is", array($this, 'stripvtag_callback'), $s);
			$s = preg_replace("/<!--\{(.+?)\}-->/s", "{\\1}", $s);
			$s = preg_replace_callback('#\{hook\s+([^}]+)\}#is', array($this, 'process_hook'), $s); // 不允许嵌套！
			$s = preg_replace_callback('#\t*//\s*hook\s+([^\s]+)#is', array($this, 'process_hook'), $s);// (\$conf, '\\1')"
		}
		
		// 美化 button, 自动转换 <input class="button" ... /> 为 <a><span></span></a>
		!empty($this->conf['view_convert_button']) && $s = $this->convert_button($s);
		
		
		// 去掉 if(0) /if
		
		// lang
		// $s = preg_replace('#\{lang (\w+?)\}#ies', "\$this->process_lang('\\1')", $s);
		
		/*$s = preg_replace("/(?:\{?)($this->var_regexp)(?(1)\}|)/", "<?=\\1?>", $s);*/
		$s = preg_replace("/($this->var_regexp|\{$this->var_regexp\})/", "<?=\\1?>", $s);
		$s = preg_replace("/\<\?=\{(.+?)\}\?\>/", "<?=\\1?>", $s);//
		$s = preg_replace("/\{($this->const_regexp)\}/", "<?=\\1?>", $s);
		
		// 修正 $data[key] -> $data['key']
		$s = preg_replace_callback("/\<\?=(\@?\\\$[a-zA-Z_]\w*)((\[[^\]]+\])+)\?\>/is", array($this, 'arrayindex'), $s);

		/*$s = preg_replace("/(?<!\<\?\=|\\\\)$this->var_regexp/", "<?=\\0?>", $s);*/
		
		// view 开头的目录, plugin/view 前面增加 static_url
		$s = preg_replace('#([\'"])(view\w*)/#i', '\\1'.$this->conf['static_url'].'\\2/', $s);
		$s = preg_replace('#([\'"])(plugin/view\w*)/#i', '\\1'.$this->conf['static_url'].'\\2/', $s); // 分布式部署 http://www.static.com/plugin/view_xxx/common.css
		
		// include file 不允许有变量。宁可写上一堆if, 为了减化模板语法和解析
		$s = preg_replace('#\{template\s+([^}]*?)\}#is', "<?php include \$this->gettpl('\\1');?>", $s);
		
		$isset = '<\?php echo isset(?:+*?) ? (?:+*?) : ;\?>';
		$s = preg_replace_callback("/\{for (.*?)\}/is", array($this, 'stripvtag_callback'), $s); //  "\$this->stripvtag('<? for(\\1) {
		
		$s = preg_replace_callback("/\{elseif\s+(.+?)\}/is", array($this, 'stripvtag_callback'), $s);
		for($i=0; $i<4; $i++) {
			$s = preg_replace_callback("/\{loop\s+$this->vtag_regexp\s+$this->vtag_regexp\s+$this->vtag_regexp\}(.+?)\{\/loop\}/is", array($this, 'loopsection'), $s);
			$s = preg_replace_callback("/\{loop\s+$this->vtag_regexp\s+$this->vtag_regexp\}(.+?)\{\/loop\}/is", array($this, 'loopsection'), $s);
		}
		$s = preg_replace_callback("/\{if\s+(.+?)\}/is", array($this, 'stripvtag_callback'), $s);
		
		$s = preg_replace("/\{else\}/is", "<? } else { ?>", $s);
		$s = preg_replace("/\{\/if\}/is", "<? } ?>", $s);
		$s = preg_replace("/\{\/for\}/is", "<? } ?>", $s);

		$s = preg_replace("/$this->const_regexp/", "<?=\\1?>", $s);//{else} 也符合常量格式，此处要注意先后顺??
		
		// 给数组KEY加上判断
		$s = preg_replace_callback("/\<\?=\@(\\\$[a-zA-Z_]\w*)((\[[\\$\[\]\w\']+\])+)\?\>/is", array($this, 'array_keyexists'), $s);
		$s = "<? !defined('FRAMEWORK_PATH') && exit('Access Denied');?>$s";

		//
		if($this->tag_search){
			$s = str_replace($this->tag_search, $this->tag_replace, $s);
		}
		// 翻译段标签为全标签
		$s = preg_replace('#<\?=(\w+.*?)\?>#', "<?php echo \\1;?>", $s);// 常量
		$s = preg_replace('#<\?=(\$\w+.*?)\?>#', "<?php echo isset(\\1) ? \\1 : '';?>", $s); // 变量
		$s = preg_replace('#<\? (.*?)\?>#', "<?php \\1?>", $s); // else if ...
		
		// 还原 json 注释
		$s = preg_replace('#\{json (.*?)\}#', '<!--{json \\1}-->', $s);
		
		// 去掉空格 废弃
		/*
		if(0) {
			$s = str_replace("/\r\n+/", "\n", $s);
			$s = preg_replace("/\n+/", "\n", $s);
			$s = preg_replace("/[ \t]+/", " ", $s);
		}
		*/
		/*
		//地址重写 废弃
		if($this->conf['urlrewrite']) {
			$s = preg_replace('#([\'"])\?(.+?\.htm)#i', '\\1'.$this->conf['app_url'].'\\2', $s);
		} else {
			$s = preg_replace('#([\'"])\?(.+?\.htm)#i', '\\1'.$this->conf['app_url'].'?\\2', $s);
		}
		*/
		
		// 修正美国免费空间异常BUG chr(0xEF).chr(0xBF).chr(0xBD);
		//$s = str_replace(chr(0xEF).chr(0xBF).chr(0xBD), '', $s);
		
		return $s;
	}
	
	/*
	private function process_lang($k) {
		$s = core::process_lang($k);
		return $s;
	}*/
	
	public function requiretpl($matchs) {
		$filename = $matchs[1]; 
		//补全.htm
		if(strpos($filename, '.') == false){
			$filename .= '.htm';
		}
		// 模板目录搜索顺序：view_xxx/, view/, plugin/*/
		$file = '';
		if(!empty($this->conf['first_view_path'])) {
			foreach($this->conf['first_view_path'] as $path) {
				if(is_file($path.$filename)) {
					$file = $path.$filename;
					break;
				}
			}
		}
		if(empty($file)) {
			$plugins = core::get_enable_plugins($this->conf);
			$pluginnames = array_keys($plugins);
			foreach($pluginnames as $v) {
				// 如果有相关的 app path, 这只读取该目录
				$path = $this->conf['plugin_path'].$v.'/';
				if(is_file($path.$filename)) {
					$file = $path.$filename;
					break;
				}
			}
		}
		if(empty($file)) {
			foreach($this->conf['view_path'] as $path) {
				if(is_file($path.$filename)) {
					$file = $path.$filename;
					break;
				}
			}
		}
		if(empty($file)) {
			throw new Exception("require 模板文件 $filename 不存在。");
		}
		return file_get_contents($file);
	}
	
	private function process_hook($matchs) {
		$hookfile = $matchs[1];
		$s = core::process_hook($this->conf, $hookfile);
		return $s;
	}

	private function arrayindex($matchs) {
		$name = $matchs[1];
		$items = $matchs[2];
		if(strpos($items, '$') === FALSE) {
			$items = preg_replace("/\[([\$a-zA-Z_][\w\$]*)\]/is", "['\\1']", $items);
		} else {
			$items = preg_replace("/\[([\$a-zA-Z_][\w\$]*)\]/is", "[\"\\1\"]", $items);
		}
		return "<?=$name$items?>";
	}

	private function array_keyexists($name, $items) {
		// 此处不能有空格，美国免费空间居然会在中间插入乱码 ED A7 A0 / ED 9E BA /， 如此诡异的空间！最终导致 jquery .html() 出错。
		return "<?php echo isset($name$items)?$name$items:'';?>";
	}
	
	private function stripvtag_callback($matchs) {
		$arr = explode(' ', $matchs[0]);
		$pre = $arr[0];
		$s = $matchs[1];
		if($pre == '{for') {
			$s = '<? for('.$s.') {?>';
		} elseif($s == '<!--{eval') {
			$s = '<? '.$matchs[2].'?'.'>';
			$search = '<!--[eval='.count($this->tag_search).']-->';
			$this->tag_search[] = $search;
			$this->tag_replace[] = $this->stripvtag($s);
			return $search;
		} elseif($pre == '{elseif') {
			$s = '<? } elseif('.$s.') { ?>';
		} elseif($pre == '{if') {
			$s = '<? if('.$s.') { ?>';
		}
		return $this->stripvtag($s);
	}

	private function stripvtag($s, $instring = FALSE) {
		$s = preg_replace('#<\?php echo isset\((.*?)\) \? (\\1) : \'\';\?>#', $instring ? '{\\1}' : '\\1', $s);
		return preg_replace("/$this->vtag_regexp/is", "\\1", str_replace("\\\"", '"', $s));
	}

	// 提取 ajax header
	// 格式：<!--#ajax width="300" height="400" title="用户登录"-->
	private function fetch_json_header(&$s, &$arr) {
		preg_match('#<!--\{json (.*?)\}-->#', $s, $m);
		if(isset($m[1])) {
			preg_match_all('#(\w+):"(.*?)"#', $m[1], $m2);
			foreach($m2[1] as $k=>$v) {
				$arr[$m2[1][$k]] = $m2[2][$k];
			}
			$s = preg_replace('#<!--\{json (.*?)\}-->#', '', $s);
		}
		return $arr;
	}

	private function loopsection($matchs) {
		if(isset($matchs[4])) {
			$arr = $matchs[1];
			$k = $matchs[2];
			$v = $matchs[3];
			$statement = $matchs[4];
		} else {
			$arr = $matchs[1];
			$k = '';
			$v = $matchs[2];
			$statement = $matchs[3];
		}
		
		$arr = $this->stripvtag($arr);
		$k = $this->stripvtag($k);
		$v = $this->stripvtag($v);
		$statement = str_replace("\\\"", '"', $statement);
		return $k ? "<? if(!empty($arr)) { foreach($arr as $k=>&$v) {?>$statement<? }}?>" : "<? if(!empty($arr)) { foreach($arr as &$v) {?>$statement<? }} ?>";
	}
	
	/*
		转换 <input class="button" 为 <a><span></span></a>
		the button <input type="button" class="button bigblue" id="button2" value="确定22"/>
		<input type="button" class="button bigblue" id="button1" value="确定333"/>
	*/
	private function convert_button($s) {
		$r = '';
		$p = '#<input ([^<]*?)>#is';
		// 一直匹配，替换
		$offset = 0;
		while(preg_match($p, $s, $m, PREG_OFFSET_CAPTURE)) {
			$start = $m[0][1];
			$len = strlen($m[0][0]);
			preg_match_all('#(\w+)\s*=\s*"(.*?)"#', $m[1][0], $m2);
			if(!empty($m2[1]) && !empty($m2[2])) {
				$arr = array_combine($m2[1], $m2[2]);
			} else {
				$arr = array();
			}
			$offset = $len + $start;
			if(!isset($arr['class']) || strpos($arr['class'], 'button') === FALSE) {
				$r .= substr($s, 0, $offset);
				$s = substr($s, $offset);
				continue;
			}
			
			$value = $arr['value'];
			//unset($arr['type'], $arr['value']);
			$attrs = '';
			!isset($arr['href']) && $arr['href'] = 'javascript:void(0)';
			!isset($arr['role']) && $arr['role'] = 'button';
			foreach($arr as $k=>$v) {
				// FIX ie6
				$k == 'onclick' && stripos($v, 'return false') === FALSE && $v .= ";return false;";
				$attrs .= " $k=\"$v\"";
			}
			$r .= substr($s, 0, $start)."<a$attrs><span>$value</span></a>";
			$s = substr($s, $offset);
		}
		$r .= $s;
		return $r;
	}
}

/*

Usage:
require_once 'lib/template.class.php';
$this->view = new template($conf);
$this->view->assign('page', $page);
$this->view->assign('userlist', $userlist);
$this->view->assign_value('totaluser', 123);
$this->view->display("user_login.htm");

*/

?>