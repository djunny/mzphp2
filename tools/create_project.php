<?php
/*
	@author : djunny
	useage :
	
	1、create a new directory(创建一个新目录)
	2、move this file in new directory(将本文件移至新目录)
	3、open browser and request this file(打开浏览器运行本文件)
	
*/

function gpc($k, $var = 'G') {
    switch ($var) {
        case 'G':
            $var = &$_GET;
        break;
        case 'P':
            $var = &$_POST;
        break;
        case 'C':
            $var = &$_COOKIE;
        break;
        case 'R':
            $var = isset($_GET[$k]) ? $_GET : (isset($_POST[$k]) ? $_POST : $_COOKIE);
        break;
        case 'S':
            $var = &$_SERVER;
        break;
    }
    return isset($var[$k]) ? $var[$k] : NULL;
}

function get_url_path() {
    $port = gpc('SERVER_PORT', 'S');
    $portadd = $port == 80 ? '' : ':80';
    $host = gpc('HTTP_HOST', 'S');
    //$schme = self::gpc('SERVER_PROTOCOL', 'S');
    $path = get_url_abpath();
    return $host ? "http://$host$portadd$path/" : "http://localhost$path/";
}


function show_message($msg) {
    header('Content-Type: text/html; charset=UTF-8');
    echo $msg;
    //echo '请创建一个新目录，将本文件拷至新目录，然后通过URL访问此文件。';
    exit;
}

function get_url_abpath() {
    return substr(gpc('PHP_SELF', 'S'), 0, strrpos(gpc('PHP_SELF', 'S'), '/'));
}

// 目录名
define('PATH', str_replace('\\', '/', dirname(__FILE__)).'/');
define('ROOT_PATH', preg_replace('#\/([\w\-]+)\/$#i', '/', PATH));
preg_match_all('#\/([\w\-]+)\/$#i', PATH, $dir);
$dir = str_replace('', '', $dir[1][0]);
define('APP_NAME', $dir);

$init_path = '../mzphp/';
$init_file = PATH.'../mzphp/mzphp.php';
if (!is_file($init_file)) {
    $init_file = PATH.'./mzphp/mzphp.php';
    $init_path = './mzphp/';
    if (!is_file($init_file)) {
        $init_file = '';
    }
}
if (!$init_file) {
    show_message('没有找到 mzphp 目录，请确定：本应用和 mzphp 框架是在同一级目录，或者 mzphp 在当前同级目录');
}

if (!APP_NAME) {
    show_message('错误的目录名，请重试');
}

if (APP_NAME == 'mzphp_tool') {
    show_message('请创建一个新目录，将本文件拷至新目录，然后通过URL访问此文件。');
}

$appname = APP_NAME;
$APP_PATH = strtoupper('ROOT_PATH');
$app_url = get_url_path();


$datadir = PATH.'data';
$confdir = PATH.'conf';
$logdir = $datadir.'/log';
$tmpdir = $datadir.'/tmp';
$cachedir = $datadir.'/cache';
$viewdir = PATH.'view';
$controldir = PATH.'control';
$modeldir = PATH.'model';
$coredir = PATH.'core';
$staticdir = PATH.'static';

!is_dir($datadir) && mkdir($datadir, 0777);
!is_dir($confdir) && mkdir($confdir, 0777);
!is_dir($logdir) && mkdir($logdir, 0777);
!is_dir($tmpdir) && mkdir($tmpdir, 0777);
!is_dir($cachedir) && mkdir($cachedir, 0777);
!is_dir($viewdir) && mkdir($viewdir, 0777);
!is_dir($controldir) && mkdir($controldir, 0777);
!is_dir($modeldir) && mkdir($modeldir, 0777);
!is_dir($coredir) && mkdir($coredir, 0777);
!is_dir($staticdir) && mkdir($staticdir, 0777);

$conffile = PATH.'conf/conf.debug.php';
$indexfile = PATH.'index.php';
$view_header_file = PATH.'view/header.htm';
$view_index_file = PATH.'view/index.htm';
$view_footer_file = PATH.'view/footer.htm';
$static_jquery_js = PATH.'static/jquery.js';
$static_common_js = PATH.'static/common.js';
$static_reset_css = PATH.'static/reset.css';
$static_common_css = PATH.'static/common.css';
$static_404_file = PATH.'static/404.htm';

if (!is_file($conffile)) {
    $s = "<?php
/*
********请不尽量不要使用记事本编辑本文件，请务必移除 BOM 头*****
*/

function get_url_abpath(){
    return substr(\$_SERVER['PHP_SELF'], 0, strrpos(\$_SERVER['PHP_SELF'], '/'));
}
\$app_dir = get_url_abpath().'/';
\$app_dir_reg = preg_quote(\$app_dir);

return array(
    //db support： mysql/pdo_mysql/pdo_sqlite(数据库支持:mysql/pdo_mysql/pdo_sqlite)
    'db' => array(
        'mysql' => array(
            'tablepre' => '".$appname."_',
            'master' => array(
                'host' => '127.0.0.1',
                'user' => 'root',
                'pass' => '',
                'name' => '".$appname."',
                'charset' => 'utf8',
                'engine' => 'MYISAM',
            ),
    		/*
            'slaves' => array(
                array(
                    'host' => '127.0.0.1:3066',
                    'user' => 'root',
                    'pass' => '',
                    'name' => '".$appname."',
                    'charset' => 'utf8',
                    'engine' => 'MYISAM',
                ),
            ),
    		*/
        ),
        //other example
        /*
        'pdo_mysql' => array(
            'master' => array(
                'host' => '127.0.0.1',
                'user' => 'root',
                'pass' => '',
                'name' => '".$appname."',
                'charset' => 'utf8',
                'engine' => 'MYISAM',
            ),
            'slaves' => array(
                array(
                    'host' => '127.0.0.1',
                    'user' => 'root',
                    'pass' => '',
                    'name' => '".$appname."',
                    'charset' => 'utf8',
                    'engine' => 'MYISAM',
                ),
            ),
            'tablepre' => '".$appname."_',
        ),
        'pdo_sqlite' => array(
            'host' => ROOT_PATH.'data/tmp/sqlite_test.db',
            'tablepre' => '".$appname."_',
        ),
        */
    ),
    // cache support: memcache/file(缓存支持：memcache/文件缓存)
    'cache' => array(
        /*
        'memcache' => array(
            'host' => '127.0.0.1:11211',
            'pre' => '".$appname."_',
        ),
        'file' => array(
            'dir' => ROOT_PATH.'data/cache".md5(time())."/',
            'pre' => '".$appname."_',
        ),
		'redis' => array(
			'host' => '127.0.0.1',
			'port' => 19000,
			'table' => 'www',
			'pre' => '".$appname."_',
		),
        */
    ),

    // 唯一识别ID
    'app_id' => '".$appname."',

    //网站名称
    'app_name' => '".$appname."',

    // cookie 前缀
    'cookie_pre' => '".$appname."',

    // cookie 域名
    'cookie_domain' => '',

    //是否开启 gzip
    'gzip' => 0,

    //是否接受 x_forwarded_for 传过来的ip(反代的时候需要)
    //正常单机外网运行下，建议关掉，因为能伪造 ip
    //'ip_x_forward' => 1,

    // 应用的绝对路径： 如: http://www.domain.com/app/
    'app_url' => '".get_url_path()."',

    // 应用的所在路径： 如: http://www.domain.com/app/
    'app_dir' => \$app_dir,

    // 404 等错误设置
    'page_setting' => array(
        '404' => 'static/404.htm',
    ),

    // CDN 缓存的静态域名，如 http://static.domain.com/
    'static_url' => '".get_url_path()."static/',

    // CDN 本地缓存的静态目录，如 http://static.domain.com/
    'static_dir' => ROOT_PATH.'static/',

    // 应用内核扩展目录，一些公共的库需要打包进 _runtime.php （减少io）
    'core_path' => $APP_PATH.'core/',

    // 模板使用的目录，按照顺序搜索，这样可以支持风格切换,结果缓存在 data/tmp
    'view_path' => array($APP_PATH.'view/'),

    // 数据模块的路径，按照数组顺序搜索目录
    'model_path' => array($APP_PATH.'model/'),

    // 自动加载 model 的映射表， 在 model_path 中未找到 model 的时, modelname=>array(tablename, primarykey, maxcol)
    'model_map' => array(),

    // 控制器的路径，按照数组顺序搜索目录
    'control_path' => array($APP_PATH.'control/'),

    // 站群域名配置文件
    // 生成模板前缀，站群模式需要用到，子域名可以重新定义一个前缀用于区分不同目录下，相同文件的问题
    // 'tpl_prefix' => '".$appname."_',
    // 'domain_path' => ROOT_PATH.'domain/',
    // 用于站群不同域名指向不同的 view/model/control 目录
    /*
        domain/admin.".$appname.".com.php 例子：
        return array(
            // 最好重新定义 app_id ， 因为模板引擎会根据 app_id 生成前缀
            // 否则两个站模板一旦有同样名字会覆盖
            'app_id' => '".$appname."_admin',
            'control_path' => array(ROOT_PATH.'control/admin/'),
            'model_path' => array(ROOT_PATH.'model/'),
            'view_path' => array(ROOT_PATH.'view/admin/'),
        ),
    */

    // 临时目录，需要可写，可以指定为 linux /dev/shm/ 目录提高速度,
    'tmp_path' => $APP_PATH.'data/tmp/',

    // 日志目录，需要可写
    'log_path' => $APP_PATH.'data/log/',

    // 服务器所在的时区
    'timeoffset' => ' + 8',

    // 模板插件
    'tpl' => array(
        'plugins' => array(
        	// 支持 static 语法插件，支持 scss、css、js 打包
            'tpl_static' => FRAMEWORK_PATH.'plugin/tpl_static.class.php',
        ),
    ),

    // 开启rewrite
    'url_rewrite' => 1,

    // 是否不压缩 html代码(如果不开启，html中的<script>片段不能有//行注释，只能用块注释/**/)
    'html_no_compress' => 0,

    // 地址重写的分隔符和后缀设置
    'rewrite_info' => array(
        'comma' => '/', // options:/\ - _  |.,
        'ext' => '.html',// for example : .htm
    ),
    'str_replace' => array(),

    'reg_replace' => array(),
);
	";

    file_put_contents($conffile, $s);

    $conffile = str_replace('.debug', '.online', $conffile);
    if (!is_file($conffile)) {
        file_put_contents($conffile, $s);
    }
}


if (!is_file($indexfile)) {
    $s = "<?php
\$_SERVER['ENV'] = isset(\$_SERVER['ENV']) ? \$_SERVER['ENV'] : 'debug';
// 调试模式: 0:关闭; 1:调试模式; 参数开启调试, URL中带上：{$appname}_debug
// 线上请务必将此参数修改复杂不可猜出
define('DEBUG', ((isset(\$argc) && \$argc) || strstr(\$_SERVER['REQUEST_URI'], '{$appname}_debug')) ? 1:0);
// 站点根目录
define('ROOT_PATH', dirname(__FILE__).'/');
// 框架的物理路径
define('FRAMEWORK_PATH', $APP_PATH.'$init_path');

\$conf = include(ROOT_PATH.'conf/conf.'.\$_SERVER['ENV'].'.php');
//定义运行环境
\$conf['env'] = \$_SERVER['ENV'];

// 扩展核心目录（该目录文件会一起打包入 runtime.php 文件）
if(isset(\$conf['core_path'])){
    define('FRAMEWORK_EXTEND_PATH', \$conf['core_path']);
}

// 临时目录
define('FRAMEWORK_TMP_PATH', \$conf['tmp_path']);

// 日志目录
define('FRAMEWORK_LOG_PATH', \$conf['log_path']);

// 包含核心框架文件，转交给框架进行处理。
include FRAMEWORK_PATH.'mzphp.php';

core::run(\$conf);

?>";
    file_put_contents($indexfile, $s);
}

if (!is_file($view_header_file)) {
    $s = ' <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns = "http://www.w3.org/1999/xhtml" >
<head>
    <title>mzphp</title >
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <!-- static 第一个参数是相对当前模板的路径 第二个是基于 static 目录的路径-->
    <!--{static ../static/reset.css _global.css}-->
    <!--{static ../static/common.css _global.css}-->
    <!--{static ../static/jquery.js _global.js}-->
    <!--{static ../static/common.js _global.js}-->
</head>
<body>
<h3> mzphp Framework </h3>
<hr/>
';
    file_put_contents($view_header_file, $s);
}

if (!is_file($view_index_file)) {
    $s = '<!--{template header.htm}-->
<!--{block hello($name, $username)}-->
<h1> Hello, $name!Hello, $username.</h1>
<!--{/block}-->

{block_hello(\'mzphp\', $username)}

<!--{template footer.htm}-->';
    file_put_contents($view_index_file, $s);
}

if (!is_file($view_footer_file)) {
    $s = '<br>processTime: {print_r(core::usedtime())}ms
<Br><br><a href="?'.$appname.'_debug">[Open Debug]</a>
</body>
</html>
';
    file_put_contents($view_footer_file, $s);
}

if (!is_file($static_404_file)) {
    $s = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>404 Not Found.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <meta name="author" content="mzPHP Framework" />
    <style>html,body{margin:0 auto;padding:0;text-align: center}.pages{margin:10px auto;padding:10px;border:1px solid #CCC;background:#DEDEDE;}h1{font-size:36px;margin:10px;}.copyright{color:#999;}</style>
</head>
<body>
<div class="pages">
    <h1>Page Not Found (Error:404)</h1>
    <div class="copyright">
    &copy; mzPHP Framework
    </div>
</div>
</body>
</html>';
    file_put_contents($static_404_file, $s);
}

$control_index_file = PATH.'control/index_control.class.php';

if (!is_file($control_index_file)) {
    $s = "<?php

!defined('FRAMEWORK_PATH') && exit('Access Deined.');

class index_control extends base_control {

    function __construct(&\$conf) {
		parent::__construct(\$conf);
	}

public function on_index() {

    \$username = 'Jobs';
    VI::assign('username', \$username);
    \$this->show('index.htm');

}
}

?>";

    file_put_contents($control_index_file, $s);
}

if (!is_file($static_jquery_js)) {
    $s = '//jquery code
console.log("jquery Code");';
    file_put_contents($static_jquery_js, $s);
}

if (!is_file($static_common_js)) {
    $s = '//common code
console.log("common Code");';
    file_put_contents($static_common_js, $s);
}

if (!is_file($static_reset_css)) {
    $s = '/*reset code*/body{margin:0;padding:0}';
    file_put_contents($static_reset_css, $s);
}

if (!is_file($static_common_css)) {
    $s = '/*common code*/a{color:red}';
    file_put_contents($static_common_css, $s);
}

$url = $app_url."?c=index-index";
@unlink('./create_project.php');
show_message("<a href='{$url}'>应用框架代码生成完毕！ 发布时请记得带上同级目录的 mzphp 目录（本文件已删除）</a>");


