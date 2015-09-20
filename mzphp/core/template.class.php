<?php


class template {

    /**
     * plugin class loaded
     *
     * @var array
     */
    static $plugin_loaded = array();
    /**
     * config for template
     *
     * @var array
     */
    public $conf = array();

    /**
     * template variables
     *
     * @var array
     */
    private $vars = array();
    /**
     * check template by force
     *
     * @var int
     */
    private $force = 1;
    /**
     *
     * @var string
     * $abc[a][b][$c] 合法
     * $abc[$a[b]]   不合法
     */
    private $var_regexp = "\@?\\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\$]+\])*";
    /**
     * variable tag regexp
     *
     * @var string
     */
    private $vtag_regexp = "\<\?=(\@?\\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\[\]\$]+\])*)\?\>";
    /*private $isset_regexp = '<\?php echo isset\(.+?\) \? (?:.+?) : \'\';\?>';*/

    /**
     * const regexp
     *
     * @var string
     */
    private $const_regexp = "\{([\w]+)\}";
    /**
     * eval regexp
     *
     * @var string
     */
    private $eval_regexp = "#(?:<!--\{(eval))\s+?(.*?)\s*\}-->#is";
    /**
     * tag search
     *
     * @var array
     */
    private $tag_search = array();
    /**
     * tag replace
     *
     * @var array
     */
    private $tag_replace = array();

    /**
     * sub templates
     *
     * @var array
     */
    private $sub_tpl = array();

    function __construct() {
    }

    /**
     * assign variable(variable must references)
     *
     * @param $k
     * @param $v
     */
    public function assign($k, &$v) {
        $this->vars[$k] = &$v;
    }

    /**
     * assign value
     *
     * @param $k
     * @param $v
     */
    public function assign_value($k, $v) {
        $this->vars[$k] = $v;
    }

    /**
     *
     * show template by config
     *
     * @param        $conf
     * @param        $file
     * @param string $makefile
     * @param string $charset
     * @return string template render body
     */
    public function show(&$conf, $file, $makefile = '', $charset = '') {
        $this->set_conf($conf);
        return $this->display($file, $makefile, $charset);
    }

    /**
     * set_conf
     *
     * @param $conf
     */
    private function set_conf(&$conf) {
        $this->conf = &$conf;
        if (!defined('DIR')) {
            define('DIR', $conf['app_dir']);
        }
        VI::assign('conf', $conf);
    }

    /**
     * display template
     *
     * @param            $file
     * @param string     $makefile
     * @param string     $charset
     * @param int        $compress
     * @param bool|false $by_return
     * @return string
     * @throws Exception
     */
    public function display($file, $makefile = '', $charset = '', $compress = 6, $by_return = false) {
        extract($this->vars, EXTR_SKIP);
        $_SERVER['warning_info'] = ob_get_contents();
        if ($_SERVER['warning_info']) {
            ob_end_clean();
        }
        // render page
        ob_start();
        include $this->get_compile_tpl($file);
        $body = ob_get_contents();
        ob_end_clean();

        // rewrite process
        core::process_urlrewrite($this->conf, $body);

        $is_xml = strpos($file, '.xml') !== false ? true : false;

        //check charset
        if ($charset && $charset != 'utf-8') {
            header('Content-Type: text/' . ($is_xml ? 'xml' : 'html') . '; charset=' . $charset);
            $body = mb_convert_encoding($body, $charset, 'utf-8');
        }

        $body = DEBUG ? $body : $this->compress_html($body);
        if ($makefile) {
            if ($compress) {
                $save_body = gzencode($body, $compress);
            } else {
                $save_body = $body;
            }
            // cache current content 600s in memcache use 'url_key'
            // CACHE:memcache:url_key:600
            // CACHE:namespace:memcache_key:time
            if (substr($makefile, 0, 6) == 'CACHE:') {
                list(, , $key, $time) = explode(':', $makefile);
                CACHE::set($key, $save_body, $time);
            } else {
                $dir = dirname($makefile);
                !is_dir($dir) && mkdir($dir, 0777, 1);
                file_put_contents($makefile, $save_body);
            }
        }
        // old ob_start
        core::ob_start(isset($conf['gzip']) && $conf['gzip'] ? $conf['gzip'] : false);

        if ($by_return) {
            return $body;
        }
        echo $body;
    }

    /**
     * find template in view path & get compile template
     *
     * @param $filename
     * @return string
     * @throws Exception
     */
    public function get_compile_tpl($filename) {
        if (strpos($filename, '.') === false) {
            $filename .= '.htm';
        }
        $fix_filename = strtr($filename, array('/' => '#', '\\' => '#'));
        $obj_file = $this->conf['tmp_path'] . $this->conf['app_id'] . '_view_' . $fix_filename . '.php';
        if (!$this->force) return $obj_file;

        $exists_file = is_file($obj_file);
        // 模板目录搜索顺序：view_xxx/, view/, plugin/*/
        $file = '';
        if (!empty($this->conf['first_view_path'])) {
            foreach ($this->conf['first_view_path'] as $path) {
                if (is_file($path . $filename)) {
                    $file = $path . $filename;
                    break;
                }
            }
        }
        if (empty($file)) {
            foreach ($this->conf['view_path'] as $path) {
                if (is_file($path . $filename)) {
                    $file = $path . $filename;
                    break;
                }
            }
        }
        if (empty($file)) {
            throw new Exception("template not found: $filename");
        }

        $file_mtime_old = $file_mtime = 0;
        if ($exists_file) {
            //存在，对比文件时间
            $file_mtime = filemtime($file);
            if (!$file_mtime) {
                throw new Exception("template stat error: $filename ");
            }
            $file_mtime_old = $exists_file ? filemtime($obj_file) : 0;
        }

        if (!$exists_file || $file_mtime_old < $file_mtime || DEBUG > 0) {
            // create tmp path
            !is_dir($this->conf['tmp_path']) && mkdir($this->conf['tmp_path'], 0777, 1);
            $this->compile($file, $obj_file);
        }
        return $obj_file;
    }

    /**
     * compile template from view_file to obj_file
     *
     * @param $view_file
     * @param $obj_file
     * @return bool
     */
    public function compile($view_file, $obj_file) {
        $this->sub_tpl = array();

        $s = file_get_contents($view_file);

        /* TODO 去掉JS中的注释  // ，否则JS传送会有错误 */
        //$s = preg_replace('#\r\n\s*//[^\r\n]*#ism', '', $s);
        //$s = str_replace('{DIR}', $this->conf['app_dir'], $s);

        // hook, 最多允许三层嵌套
        for ($i = 0; $i < 4; $i++) {
            // template , include file 减少 io
            $s = preg_replace_callback("#<!--{template\s+([^}]*?)}-->#i", array($this, 'get_tpl'), $s);
        }

        // load plugin to complie
        if (!empty($this->conf['tpl']['plugins'])) {
            //$this->conf['tpl']['plugins'] = array('class' => 'class_real_path');
            foreach ($this->conf['tpl']['plugins'] as $plugin => $plugin_file) {
                if (!isset(self::$plugin_loaded[$plugin])) {
                    include $plugin_file;
                    self::$plugin_loaded[$plugin] = new $plugin($this->conf);
                }
                self::$plugin_loaded[$plugin]->process($s);
            }
        }

        $this->do_tpl($s);


        /*$s = preg_replace("/(?:\{?)($this->var_regexp)(?(1)\}|)/", "<?=\\1?>", $s);*/
        /*
        $s = preg_replace("/($this->var_regexp|\{$this->var_regexp\})/", "<?=\\1?>", $s);
        $s = preg_replace("/\<\?=\{(.+?)\}\?\>/", "<?=\\1?>", $s);//
        $s = preg_replace("/\{($this->const_regexp)\}/", "<?=\\1?>", $s);
        */

        $s = preg_replace("#(\{" . $this->var_regexp . "\}|" . $this->var_regexp . ")#", "<?=\\1?>", $s);
        if (strpos($s, '<?={') !== false) {
            $s = preg_replace("/\<\?={(.+?)}\?\>/", "<?=\\1?>", $s);//
        }


        // 修正 $data[key] -> $data['key']
        $s = preg_replace_callback("/\<\?=(\@?\\\$[a-zA-Z_]\w*)((\[[^\]]+\])+)\?\>/is", array($this, 'array_index'), $s);

        /*$s = preg_replace("/(?<!\<\?\=|\\\\)$this->var_regexp/", "<?=\\0?>", $s);*/

        // 分布式部署 http://www.static.com/plugin/view_xxx/common.css
        //$s = preg_replace('#([\'"])(plugin/view\w*)/#i', '\\1'.$this->conf['static_url'].'\\2/', $s);

        /*$isset = '<\?php echo isset(?:+*?) ? (?:+*?) : ;\?>';*/
        //$s = preg_replace_callback("/\{for (.*?)\}/is", array($this, 'stripvtag_callback'), $s); //  "\$this->stripvtag('<? for(\\1) {

        for ($i = 0; $i < 4; $i++) {
            $s = preg_replace_callback("/\{loop\s+$this->vtag_regexp\s+$this->vtag_regexp\s+$this->vtag_regexp\}(.+?)\{\/loop\}/is", array($this, 'loop_section'), $s);
            $s = preg_replace_callback("/\{loop\s+$this->vtag_regexp\s+$this->vtag_regexp\}(.+?)\{\/loop\}/is", array($this, 'loop_section'), $s);
        }
        $s = preg_replace_callback("/\{(if|elseif|for)\s+(.*?)\}/is", array($this, 'stripvtag_callback'), $s);

        //$s = preg_replace_callback("/\{if\s+(.+?)\}/is", array($this, 'strip_vtag_callback'), $s);

        $s = preg_replace("/\{else\}/is", "<?}else { ?>", $s);
        $s = preg_replace("/\{\/(if|for)\}/is", "<?}?>", $s);
        //{else} 也符合常量格式，此处要注意先后顺??
        $s = preg_replace("/" . $this->const_regexp . "/", "<?=\\1?>", $s);

        // 给数组KEY加上判断
        $s = preg_replace_callback("/\<\?=\@(\\\$[a-zA-Z_]\w*)((\[[\\$\[\]\w\']+\])+)\?\>/is", array($this, 'array_keyexists'), $s);
        //
        if ($this->tag_search) {
            $s = str_replace($this->tag_search, $this->tag_replace, $s);
            // 可能js中还有eval
            if (strpos($s, '<!--[') !== false) {
                $s = str_replace($this->tag_search, $this->tag_replace, $s);
            }
        }

        // 翻译段标签为全标签
        $s = preg_replace('#<\?=(\$\w+.*?)\?>#', "<?php echo isset(\\1) ? \\1 : '';?>", $s); // 变量
        // static 目录 前面增加 static_url
        if ($this->conf['static_url']) {
            $s = preg_replace('#([\'"])(static\w*)/#i', '\\1' . $this->conf['static_url'] . '\\2/', $s);
        }
        $s = "<?php !defined('FRAMEWORK_PATH') && exit('Access Denied');" .
            "\$this->sub_tpl_check('" . implode('|', $this->sub_tpl) . "', '{$_SERVER['starttime']}', '$view_file', '$obj_file');?>$s";

        // 此处不锁，多个进程并发写入可能会有问题。
        // PHP 5.1 以后加入了 LOCK_EX 参数
        file_put_contents($obj_file, $s);
        return true;
    }

    /**
     * process tpl
     *
     * @param $s
     */
    private function do_tpl(&$s) {
        //优化eval tag 先替换成对应标签，稍后再换回(eval中的变量会和下边变量替换冲突)
        $s = preg_replace_callback($this->eval_regexp, array($this, 'stripvtag_callback'), $s);
        /*
        $s = preg_replace_callback("#<script([^><}]*?)>([\s\S]*?)</script>#is", array($this, 'striptag_callback'), $s);
        */
        // remove template comment
        $s = preg_replace("#<!--\#(.+?)-->#s", "", $s);
        // replace dynamic tag
        $s = preg_replace("#<!--{(.+?)}-->#s", "{\\1}", $s);
        // replace function
        $s = preg_replace_callback('#{([\w\:]+\([^}]*?\);?)}#is', array($this, 'funtag_callback'), $s);
    }

    /**
     * compress html
     *
     * @param $html_source
     * @return string
     */
    private function compress_html($html_source) {
        $chunks = preg_split('/(<pre.*?\/pre>)/ms', $html_source, -1, PREG_SPLIT_DELIM_CAPTURE);
        $compress_html_source = '';
        // compress html : clean new line , clean tab, clean comment
        foreach ($chunks as $c) {
            if (stripos($c, '<pre') !== 0) {
                // remove new lines & tabs
                $c = preg_replace('/[\\n\\r\\t]+/', ' ', $c);
                // remove inter-tag newline
                $c = preg_replace('/>[\\r\\n]+</', '><', $c);
                // remove inter-tag whitespace
                $c = preg_replace('/>\\s+</s', '> <', $c);
                // remove extra whitespace
                $c = preg_replace('/\\s{2,}/', ' ', $c);
                // remove CSS & JS comments
                $c = preg_replace('/\\/\\*.*?\\*\\//i', '', $c);
            }
            if (strpos($c, '<!--') !== false) {
                $c = preg_replace('/<!--[\s\S]*?-->/is', '', $c);
            }
            //short tag
            $compress_html_source .= $c;
        }
        return $compress_html_source;
    }

    /**
     * get sub template & check compile
     *
     * @param $sub_files   sub template file paths
     * @param $make_time   template last modified time
     * @param $tpl         template
     * @param $obj_file    object template
     */
    function sub_tpl_check($sub_files, $make_time, $tpl, $obj_file) {
        if (mt_rand(1, 5) == 1) {
            $sub_files = explode('|', $sub_files);
            foreach ($sub_files as $tpl_file) {
                $sub_make_time = @filemtime($tpl_file);
                if ($sub_make_time > $make_time) {
                    $this->compile($tpl, $obj_file);
                    break;
                }
            }
        }
    }

    /**
     * search view path to find tpl path
     *
     * @param $filename
     * @return string
     */
    public function get_tpl($filename) {
        if (!isset($filename[1])) {
            return '';
        }
        $filename = $filename[1];
        if (strpos($filename, '.') === false) {
            $filename .= '.htm';
        }
        $file = '';
        if (!empty($this->conf['first_view_path'])) {
            foreach ($this->conf['first_view_path'] as $path) {
                if (is_file($path . $filename)) {
                    $file = $path . $filename;
                    break;
                }
            }
        }
        if (empty($file)) {
            foreach ($this->conf['view_path'] as $path) {
                if (is_file($path . $filename)) {
                    $file = $path . $filename;
                    break;
                }
            }
        }

        if ($file) {
            $this->sub_tpl[$file] = $file;
            return file_get_contents($file);
        }
        return '';
    }

    /**
     * fix array index
     *
     * @param $matches
     * @return string
     */
    private function array_index($matches) {
        $name = $matches[1];
        $items = $matches[2];
        if (strpos($items, '$') === FALSE) {
            $items = preg_replace("/\[([\$a-zA-Z_][\w\$]*)\]/is", "['\\1']", $items);
        } else {
            $items = preg_replace("/\[([\$a-zA-Z_][\w\$]*)\]/is", "[\"\\1\"]", $items);
        }
        return "<?=$name$items?>";
    }

    /**
     * fix echo array index key
     *
     * @param $name
     * @param $items
     * @return string
     */
    private function array_keyexists($name, $items) {
        return "<?php echo isset($name$items)?$name$items:'';?>";
    }

    /**
     * strip tag
     *
     * @param $matchs
     * @return mixed|string
     */
    private function stripvtag_callback($matchs) {
        $pre = $matchs[1];
        $s = $matchs[2];
        switch ($pre) {
            case 'for':
                $s = '<? for(' . $s . ') {?>';
            break;
            case 'eval':
                $s = '<? ' . $s . '?' . '>';
                $search = '<!--[eval=' . count($this->tag_search) . ']-->';
                $this->tag_search[] = $search;
                $this->tag_replace[] = $this->stripvtag($s);
                return $search;
            break;
            case 'elseif':
                $s = '<? } elseif(' . $s . ') { ?>';
            break;
            case 'if':
                $s = '<? if(' . $s . ') { ?>';
            break;
        }
        return $this->stripvtag($s);
    }

    /**
     * @param            $s
     * @param bool|FALSE $instring
     * @return mixed
     */
    private function stripvtag($s, $instring = FALSE) {
        if (strpos($s, '<? echo isset') !== false) {
            $s = preg_replace('#<\? echo isset\((.*?)\) \? (\\1) : \'\';\?>#', $instring ? '{\\1}' : '\\1', $s);
        }
        return preg_replace("/" . $this->vtag_regexp . "/is", "\\1", str_replace("\\\"", '"', $s));
    }

    /**
     * @param $matches
     * @return string
     */
    private function striptag_callback($matches) {
        if (trim($matches[2]) == '') {
            return $matches[0];
        } else {
            if (stripos($matches[1], ' type="tpl"') !== false) {
                return $matches[0];
            }
            $search = '<!--[script=' . count($this->tag_search) . ']-->';
            $this->tag_search[] = $search;
            // filter script comment
            $matches[0] = preg_replace('#(//[^\'";><]*$|/\*[\s\S]*?\*/)#im', '', $matches[0]);
            // replace variable and constant
            // e.g.
            // {$a} {$a[1]} {$a[desc]} {ROOT}
            $matches[0] = preg_replace('#{((?:\$[\w\[\]]+)|(?:[A-Z_]+))}#s', '<' . '?php echo $1;?' . '>', $matches[0]);
            $this->tag_replace[] = $matches[0];
            return $search;
        }
    }

    /**
     * function tag callback
     *
     * @param $matchs
     * @return string
     */
    private function funtag_callback($matchs) {
        $search = '<!--[func=' . count($this->tag_search) . ']-->';
        $this->tag_search[] = $search;
        $this->tag_replace[] = '<?php if(false !== ($_val=' . $matchs[1] . '))echo $_val;?>';
        return $search;
    }

    /**
     * for loop
     *
     * @param $matchs
     * @return string
     */
    private function loop_section($matchs) {
        if (isset($matchs[4])) {
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