<?php


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
	
	private $tag_search = array();
	private $tag_replace = array();
	
	private $sub_tpl = array();
	
	function __construct() {
	}


	private function set_conf(&$conf){
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
	public function show(&$conf, $file, $makefile='', $charset=''){
		$this->set_conf($conf);
		$this->display($file, $makefile, $charset);
	}

	// 约定必须主模板调用其他应用模板，不能反其道而行之
	public function display($file, $makefile='', $charset ='') {
		extract($this->vars, EXTR_SKIP);
		$_SERVER['warning_info'] = ob_get_contents();
		if($_SERVER['warning_info']){
			ob_end_clean();
		}
		// render page
		ob_start();
		include $this->get_complie_tpl($file);
		$body = ob_get_contents();
		ob_end_clean();
		
		// rewrite process
		core::process_urlrewrite($this->conf, $body);

		//make file
		//$makefile = self::gpc('makefile', 'S');
		if($makefile){
			return file_put_contents($makefile, $body);
		}
		// old ob_start
		core::ob_start(isset($conf['gzip']) && $conf['gzip'] ? $conf['gzip'] : false);
		//check charset
		//$charset = self::gpc('charset','S');
		if($charset && $charset != 'utf-8'){
			header('Content-Type: text/html; charset='.$charset);
			$body = iconv('utf-8', $charset, $body);
		}
		echo $body;
	}
	
	public function get_complie_tpl($filename) {
		if(strpos($filename, '.') === false){
			$filename .= '.htm';
		}
		$objfile = $this->conf['tmp_path'].$this->conf['app_id'].'_view_'.$filename.'.php';
		if(!$this->force) return $objfile;
		
		$existsfile = is_file($objfile);
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
			foreach($this->conf['view_path'] as $path) {
				if(is_file($path.$filename)) {
					$file = $path.$filename;
					break;
				}
			}
		}
		if(empty($file)) {
			throw new Exception("template not found: $filename");
		}
		
		if($existsfile){
			//存在，对比文件时间
			$filemtime = filemtime($file);
			if(!$filemtime) {
				throw new Exception("template stat error: $filename ");
			}
			$filemtimeold = $existsfile ? filemtime($objfile) : 0;
		}
		
		if(!$existsfile || $filemtimeold < $filemtime || DEBUG > 0) {
			$s = $this->complie($file, $objfile);
		}
		return $objfile;
	}
	
	public function complie($viewfile, $objfile) {
		$conf = $this->conf;
		
		$this->sub_tpl = array();
			
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
			$s = preg_replace("/<!--\{(.+?)\}-->/s", "{\\1}", $s);
			// template , include file 减少 io
			//$s = preg_replace('#\{template\s+([^}]*?)\}#is', "<?php include \$this->get_complie_tpl('\\1');?".">", $s);
			$s = preg_replace_callback("#\{template\s+([^}]*?)\}#i", array($this, 'get_tpl'), $s);
			$s = preg_replace_callback("#(<!--\{eval)\s+?(.*?)\s*\}-->#is", array($this, 'stripvtag_callback'), $s);
			$s = preg_replace("/<!--\{(.+?)\}-->/s", "{\\1}", $s);
			//$s = preg_replace_callback('#\{hook\s+([^}]+)\}#is', array($this, 'process_hook'), $s); // 不允许嵌套！
			//$s = preg_replace_callback('#\t*//\s*hook\s+([^\s]+)#is', array($this, 'process_hook'), $s);// (\$conf, '\\1')"
		}
		
		
		/*$s = preg_replace("/(?:\{?)($this->var_regexp)(?(1)\}|)/", "<?=\\1?>", $s);*/
		$s = preg_replace("/($this->var_regexp|\{$this->var_regexp\})/", "<?=\\1?>", $s);
		$s = preg_replace("/\<\?=\{(.+?)\}\?\>/", "<?=\\1?>", $s);//
		$s = preg_replace("/\{($this->const_regexp)\}/", "<?=\\1?>", $s);
		
		// 修正 $data[key] -> $data['key']
		$s = preg_replace_callback("/\<\?=(\@?\\\$[a-zA-Z_]\w*)((\[[^\]]+\])+)\?\>/is", array($this, 'arrayindex'), $s);

		/*$s = preg_replace("/(?<!\<\?\=|\\\\)$this->var_regexp/", "<?=\\0?>", $s);*/
		
		// view 开头的目录, plugin/view 前面增加 static_url
		$s = preg_replace('#([\'"])(view\w*)/#i', '\\1'.$this->conf['static_url'].'\\2/', $s);
		// 分布式部署 http://www.static.com/plugin/view_xxx/common.css
		$s = preg_replace('#([\'"])(plugin/view\w*)/#i', '\\1'.$this->conf['static_url'].'\\2/', $s);
		
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
		//
		if($this->tag_search){
			$s = str_replace($this->tag_search, $this->tag_replace, $s);
		}
		// 翻译段标签为全标签
		$s = preg_replace('#<\?=(\w+.*?)\?>#', "<?php echo \\1;?>", $s);// 常量
		$s = preg_replace('#<\?=(\$\w+.*?)\?>#', "<?php echo isset(\\1) ? \\1 : '';?>", $s); // 变量
		$s = preg_replace('#<\? (.*?)\?>#', "<?php \\1?>", $s); // else if ...
		
		$s = "<?php !defined('FRAMEWORK_PATH') && exit('Access Denied');".
			"\$this->sub_tpl_check('".implode('|', $this->sub_tpl)."', '{$_SERVER['starttime']}', '$viewfile', '$objfile');?>$s";

		// 此处不锁，多个进程并发写入可能会有问题。
		// PHP 5.1 以后加入了 LOCK_EX 参数
		file_put_contents($objfile, $s);
		return true;
	}
	
		
	/**
	 *  子模板更新检查 
	 *
	 * @param string $subfiles 模板路径
	 * @param int $mktime 时间  （未知，需了解 uican）
	 * @param string $tpl  当前页面模板
	 */
	function sub_tpl_check($subfiles, $mktime, $tpl, $objfile) {
		if(mt_rand(1, 5) == 1) {
			$subfiles = explode('|', $subfiles);
			foreach ($subfiles as $tplfile) {
				$submktime = @filemtime($tplfile);
				if($submktime > $mktime) {
					$this->complie($tpl, $objfile);
					break;
				}
			}
		}
	}
	
	/*
	private function process_lang($k) {
		$s = core::process_lang($k);
		return $s;
	}*/
	
	public function get_tpl($filename){
		if(!isset($filename[1])){
			return '';
		}
		$filename = $filename[1];
		if(strpos($filename, '.') === false){
			$filename .= '.htm';
		}
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
			foreach($this->conf['view_path'] as $path) {
				if(is_file($path.$filename)) {
					$file = $path.$filename;
					break;
				}
			}
		}
		
		if($file){
			$this->sub_tpl[] = $file;
			return file_get_contents($file);
		}
		return '';
	}
	
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
			foreach($this->conf['view_path'] as $path) {
				if(is_file($path.$filename)) {
					$file = $path.$filename;
					break;
				}
			}
		}
		if(empty($file)) {
			throw new Exception("template not found:$filename ");
		}
		return file_get_contents($file);
	}
	
	private function process_hook($matchs) {
		$hookfile = $matchs[1];
		//$s = core::process_hook($this->conf, $hookfile);
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