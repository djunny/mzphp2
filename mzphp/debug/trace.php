<?php
defined('ROOT_PATH') || exit; 
?>
<style type="text/css">
#mzphp_trace_win{display:none;z-index:99999;position:fixed;left:1%;bottom:10px;width:98%;min-width:300px;border-radius:5px;box-shadow:-2px 2px 20px #555;background:#fff;border:1px solid #ccc}
#mzphp_trace_win,#mzphp_trace_win,#mzphp_trace_win div,#mzphp_trace_win h6,#mzphp_trace_win ol,#mzphp_trace_win li{margin:0;padding:0;font:14px/1.6 'Microsoft YaHei',Verdana,Arial,sans-serif}
#mzphp_trace_open{display:none;z-index:99999;position:fixed;right:5px;bottom:5px;width:80px;height:24px;line-height:24px;text-align:center;border:1px solid #ccc;border-radius:5px;background:#eee;cursor:pointer;box-shadow:0 0 12px #555}
#mzphp_trace_size,#mzphp_trace_close{float:right;display:inline;margin:3px 5px 0 0!important;border:1px solid #ccc;border-radius:5px;background:#eee;width:24px;height:24px;line-height:24px;text-align:center;cursor:pointer}
#mzphp_trace_title{height:32px;overflow:hidden;padding:0 3px;border-bottom:1px solid #ccc}
#mzphp_trace_title h6{float:left;display:inline;width:100px;height:32px;line-height:32px;font-size:16px;font-weight:700;text-align:center;color:#999;cursor:pointer;text-shadow:1px 1px 0 #F2F2F2}
#mzphp_trace_cont{width:100%;height:240px;overflow:auto}
#mzphp_trace_cont ol{list-style:none;padding:5px;overflow:hidden;word-break:break-all}
#mzphp_trace_cont ol.ktun{display:none}
#mzphp_trace_cont ol li{padding:0 3px;clear:both}
#mzphp_trace_cont ol li span{float:left;display:inline;width:100px}
#mzphp_trace_cont ol li.even{background:#ddd}
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
<div id="mzphp_trace_open"><?php echo $runtime = core::usedtime();?></div>
<div id="mzphp_trace_win">
	<div id="mzphp_trace_title">
		<div id="mzphp_trace_close">关</div>
		<div id="mzphp_trace_size">大</div>
		<h6 style="color:#000">基本信息</h6>
		<h6>SQL</h6>
		<h6>$_GET</h6>
		<h6>$_POST</h6>
		<h6>$_COOKIE</h6>
		<h6>$_SERVER</h6>
		<h6>包含文件</h6>
	</div>
	<div id="mzphp_trace_cont">
		<ol>
			<li><span>当前时间:</span> <?php echo date('Y-m-d H:i:s', core::S('time'));?></li>
			<li><span>当前页面:</span> <?php echo $_SERVER['SCRIPT_FILENAME'];?></li>
			<li><span>控制文件:</span> <font color="red"><?php echo $_GET['c'];?>_control.class.php</font></li>
			<li><span>当前网协:</span> <?php echo core::ip();?></li>
			<li><span>请求路径:</span> <?php echo $_SERVER['REQUEST_URI'];?></li>
			<li><span>运行时间:</span> <?php echo core::usedtime();?>ms</li>
			<li><span>内存开销:</span> <?php echo misc::human_size(core::runmem());?></li>
		</ol>
		<ol class="ktun">
		
		
		<?php
			$tdclass = '';
			$class = 'tclass2';
			($class == 'tclass')?$class = 'tclass2':$class = 'tclass';
			foreach ($_SERVER['sqls'] as $dkey => $debug) {
				echo '<table class="'.$class.'"><tr><th rowspan="2" width="20">'.($dkey+1).'</th><td width="100">'.$debug['time'].' ms</td><td class="bold">'.core::htmlspecialchars($debug['sql']).'</td></tr>';
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
							($ekey == 'selectid')? $tdclass = ' class="firsttd"':$tdclass='';
							if(empty($explain)) $explain = '-';
							echo '<td'.$tdclass.'>'.$explain.'</td>';
						}
						echo '</tr></table></td></tr>';
					}
				}
				echo '</table>';
			//echo self::arr2str($sql, 1, FALSE);
			}
		?></ol>
		<ol class="ktun"><?php echo self::arr2str($_GET);?></ol>
		<ol class="ktun" style="white-space:pre"><?php echo print_r(core::htmlspecialchars($_POST), 1);?></ol>
		<ol class="ktun"><?php echo self::arr2str($_COOKIE);?></ol>
		<ol class="ktun"><?php echo self::arr2str(core::htmlspecialchars($_SERVER));?></ol>
		<ol class="ktun"><?php echo self::arr2str(get_included_files(), 1);?></ol>
	</div>
</div>
<script type="text/javascript">
(function(){
var isIE = !!window.ActiveXObject;
var isIE6 = window.VBArray && !window.XMLHttpRequest;
var isQuirks = document.compatMode == 'BackCompat';
var isDisable = (isIE && isQuirks) || isIE6;
var win = document.getElementById('mzphp_trace_win');
var size = document.getElementById('mzphp_trace_size');
var open = document.getElementById('mzphp_trace_open');
var close = document.getElementById('mzphp_trace_close');
var cont = document.getElementById('mzphp_trace_cont');
var tab_tit = document.getElementById('mzphp_trace_title').getElementsByTagName('h6');
var tab_cont = document.getElementById('mzphp_trace_cont').getElementsByTagName('ol');
var cookie = document.cookie.match(/mzphp_trace_page_show=(\d\|\d\|\d)/);
var history = (cookie && typeof cookie[1] != 'undefined' && cookie[1].split('|')) || [0,0,0];
var is_size = 0;
var set_cookie = function() {
	document.cookie = 'kongphp_trace_page_show=' + history.join('|');
}
open.onclick = function() {
	win.style.display='block';
	this.style.display='none';
	history[0] = 1;
	set_cookie();
}
close.onclick = function() {
	win.style.display = 'none';
	open.style.display = 'block';
	history[0] = 0;
	set_cookie();
}
size.onclick = function() {
	if(is_size == 0) {
		this.innerHTML = "小";
		//win.style.top = "10px";
		var H = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight;
		H = H > window.screen.availHeight ? window.screen.availHeight - 200 : H;
		H = H < 350 ? 350 : H;
		cont.style.height = H - 63 +"px";
		is_size = 1;
		history[1] = 1;
	}else{
		this.innerHTML = "大";
		//win.style.top = "auto";
		cont.style.height = "240px";
		is_size = 0;
		history[1] = 0;
	}
	set_cookie();
}
for(var i = 0; i < tab_tit.length; i++) {
	tab_tit[i].onclick = (function(i) {
		return function() {
			for(var j = 0; j < tab_cont.length; j++) {
				tab_cont[j].style.display = 'none';
				tab_tit[j].style.color = '#999';
			}
			tab_cont[i].style.display = 'block';
			tab_tit[i].style.color = '#000';
			history[2] = i;
			set_cookie();
		};
	})(i);
}
if(!isDisable) {
	open.style.display = 'block';

	if(typeof open.click == 'function') {
		parseInt(history[0]) && open.click();
		parseInt(history[1]) && size.click();
		tab_tit[history[2]].click();
	}else{
		parseInt(history[0]) && open.onclick();
		parseInt(history[1]) && size.onclick();
		tab_tit[history[2]].onclick();
	}
}
})();
</script>
