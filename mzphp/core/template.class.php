<?php

/**
 * Class template
 */
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
    public $force = 5;
    /**
     *
     * @var string
     * $abc[a][b][$c] 合法
     * $abc[$a[b]]   不合法
     */
    private $var_regexp = "\@?\\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\-\$]+\])*";
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

    /**
     * construct template engine
     */
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
    public function show(&$conf, $file, $makefile = '', $charset = '', $compress = 6, $by_return = 0) {
        $this->set_conf($conf);
        return $this->display($file, $makefile, $charset, $compress, $by_return);
    }

    /**
     * set_conf
     *
     * @param $conf
     * $conf[app_dir] : appliation path ,for example /
     * $conf[gzip] : gzip content for display
     * $conf[tmp_path] : compile template temp directory
     * $conf[html_no_compress] : close compress html feature
     * $conf[url_rewrite] : to rewrite link
     * $conf[app_id] : uniq app id for template cache build
     * $conf[first_view_path] : Priority search the template find in directory
     * $conf[view_path] : normal search the template find in directory
     * $conf[tpl_prefix] : prefix for compile template filename
     * $conf[tpl][plugins] : plugin setting
     */
    public function set_conf(&$conf) {
        $this->conf = &$conf;
        // relative path use in template
        if (!defined('DIR')) {
            define('DIR', $conf['app_dir']);
        }
        // static path use in template
        if (!defined('CDN')) {
            define('CDN', $conf['static_url']);
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

        $body = (DEBUG || $this->conf['html_no_compress']) ? $body : $this->compress_html($body);
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
                !is_dir($dir) && mkdir($dir, 0755, 1);
                file_put_contents($makefile, $save_body);
            }
        }
        // old ob_start
        core::ob_start(isset($this->conf['gzip']) && $this->conf['gzip'] ? $this->conf['gzip'] : false);

        if ($by_return) {
            return $body;
        }
        echo $body;
    }

    /**
     * get complie template object file
     *
     * @param $filename
     * @return string
     */
    public function get_complie_name(&$filename) {
        if (strpos($filename, '.') === false) {
            $filename .= '.htm';
        }
        $fix_filename = strtr($filename, array('/' => '#', '\\' => '#'));
        $obj_file     = $this->conf['tmp_path'] . (isset($this->conf['tpl_prefix']) ? $this->conf['tpl_prefix'] : $this->conf['app_id']) . '_view_' . $fix_filename . '.php';
        return $obj_file;
    }

    /**
     * find template in view path & get compile template
     *
     * @param $filename
     * @return string
     * @throws Exception
     */
    public function get_compile_tpl($filename) {
        $obj_file = $this->get_complie_name($filename);
        if (!$this->force) return $obj_file;

        $exists_file = is_file($obj_file);
        // 搜索目录
        $file = '';
        foreach ($this->conf['view_path'] as $path) {
            if (is_file($path . $filename)) {
                $file = $path . $filename;
                break;
            }
        }
        // 删除原始模板后，如果编译的模板文件在还存在，则直接返回
        if (empty($file)) {
            if ($exists_file) {
                return $obj_file;
            }
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
            !is_dir($this->conf['tmp_path']) && mkdir($this->conf['tmp_path'], 0755, 1);
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

        // 加载模板正文，load template content
        $s = file_get_contents($view_file);

        /* TODO 去掉JS中的注释  // ，否则压缩html时 JS 会有错误 */
        //$s = preg_replace('#\r\n\s*//[^\r\n]*#ism', '', $s);
        //$s = str_replace('{DIR}', $this->conf['app_dir'], $s);

        // hook, 最多允许三层嵌套
        for ($i = 0; $i < 4; $i++) {
            // template , include file 减少 io
            $s = preg_replace_callback("#<!--{template\s+([^}]*?)}-->#i", array($this, 'get_tpl'), $s);
        }

        // 加载插件开始执行，load plugin to complie
        if (!empty($this->conf['tpl']['plugins'])) {
            //$this->conf['tpl']['plugins'] = array('class' => 'class_real_path');
            foreach ($this->conf['tpl']['plugins'] as $plugin => $plugin_file) {
                if (!isset(self::$plugin_loaded[$plugin])) {
                    include $plugin_file;
                    if (class_exists($plugin, 0)) {
                        self::$plugin_loaded[$plugin] = new $plugin($this->conf);
                    } else {
                        self::$plugin_loaded[$plugin] = 0;
                    }
                }
                self::$plugin_loaded[$plugin] && self::$plugin_loaded[$plugin]->process($s);
            }
        }

        // 替换区块元素，compile block from template
        $this->compile_block($s);

        // replace variable by regexp
        $s = preg_replace("#(\{" . $this->var_regexp . "\}|" . $this->var_regexp . ")#i", "<?=\\1?>", $s);
        if (strpos($s, '<?={') !== false) {
            $s = preg_replace("#\<\?={(.+?)}\?\>#", "<?=\\1?>", $s);//
        }

        // 修正 $data[key] -> $data['key']
        $s = preg_replace_callback("#\<\?=(\@?\\\$[a-zA-Z_]\w*)((\[[^\]]+\])+)\?\>#is", array($this, 'array_index'), $s);

        // loop
        for ($i = 0; $i < 4; $i++) {
            $s = preg_replace_callback("#\{loop\s+$this->vtag_regexp\s+$this->vtag_regexp\s+$this->vtag_regexp\}(.+?)\{\/loop\}#is", array($this, 'loop_section'), $s);
            $s = preg_replace_callback("#\{loop\s+$this->vtag_regexp\s+$this->vtag_regexp\}(.+?)\{\/loop\}#is", array($this, 'loop_section'), $s);
        }
        // if / elseif
        $s = preg_replace_callback("#\{(if|elseif)\s+(.*?)\}#is", array($this, 'stripvtag_callback'), $s);

        // else
        $s = preg_replace("#\{else\}#is", "<?}else { ?>", $s);
        // end if
        $s = preg_replace("#\{\/(if)\}#is", "<?}?>", $s);
        // end block
        $s = preg_replace("#\{\/(block)\}#is", "<?}}?>", $s);
        //{else} 也符合常量格式，此处要注意先后顺??
        $s = preg_replace("#" . $this->const_regexp . "#", "<?=\\1?>", $s);

        // 给数组KEY加上判断
        $s = preg_replace_callback("#\<\?=\@(\\\$[a-zA-Z_]\w*)((\[[\\$\[\]\w\']+\])+)\?\>#is", array($this, 'array_keyexists'), $s);
        // 将特殊标签替换回来
        if ($this->tag_search) {
            $s = str_replace($this->tag_search, $this->tag_replace, $s);
            // 可能js中还有eval
            if (strpos($s, '<!--[') !== false) {
                $s = str_replace($this->tag_search, $this->tag_replace, $s);
            }
        }

        // 替换成原始标签（暂时不需要了）
        // $s = preg_replace('#<\?=(\$\w+.*?)\?'.'>#', "<"."?php echo isset(\\1) ? \\1 : '';?".">", $s);
        // static 目录前面增加 static_url
        if ($this->conf['static_url']) {
            $s = preg_replace('#([\'"])static\/(\w*)/#i', '\\1' . $this->conf['static_url'] . '\\2/', $s);
        }
        // 添加头，用于判断所有子模板改过后模板重新编译
        $s = "<?php !defined('ROOT_PATH') && exit('Access Denied');" .
            "\$this->sub_tpl_check('" . implode('|', $this->sub_tpl) . "', '{$_SERVER['starttime']}', '$view_file', '$obj_file');?>$s";

        file_put_contents($obj_file, $s);
        return true;
    }

    /**
     * process tpl
     *
     * @param $s
     */
    private function compile_block(&$s) {
        //优化eval tag 先替换成对应标签，稍后再换回(eval中的变量会和下边变量替换冲突)
        $s = preg_replace_callback($this->eval_regexp, array($this, 'stripvtag_callback'), $s);
        /*
        $s = preg_replace_callback("#<script([^><}]*?)>([\s\S]*?)</script>#is", array($this, 'striptag_callback'), $s);
        */
        // remove template comment
        $s = preg_replace("#<!--\#(.+?)-->#s", "", $s);
        // replace dynamic tag
        $s = preg_replace("#<!--{(.+?)}-->#s", "{\\1}", $s);
        // replace block
        $s = preg_replace_callback("#{block\s+(\w+[^\r\n]+)}#is", array($this, 'blocktag_callback'), $s);
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
        $chunks               = preg_split('#(<(pre|textarea)[\s\S]*?<\/\2>)#is', $html_source, -1, PREG_SPLIT_DELIM_CAPTURE);
        $compress_html_source = '';
        // compress html : clean new line , clean tab, clean comment
        $skip = 0;
        foreach ($chunks as $index => $c) {
            if ($skip) {
                //skip capture like pre and textarea
                $skip = 0;
                continue;
            }
            if (stripos($c, '<pre') === 0 || stripos($c, '<textarea') === 0) {
                $skip = 1;
            } else {
                while (strpos($c, "\r") !== false) {
                    $c = str_replace("\r", "\n", $c);
                }
                while (strpos($c, "\n\n") !== false) {
                    $c = str_replace("\n\n", "\n", $c);
                }
                // remove inter-tag newline
                $c = preg_replace('#>\\n<(/?\w)#is', '><$1', $c);
                // remove extra whitespace
                $c = preg_replace('#\\n[\\t ]+#is', "\n", $c);
                $c = preg_replace('#[\\t ]{2,}#', ' ', $c);
                // remove CSS & JS comments
                if (strpos($c, '/*') !== false) {
                    $c = preg_replace('#/\*[\s\S]*?\*/#i', '', $c);
                }
            }
            if (strpos($c, '<!--') !== false) {
                $c = preg_replace('#\s*<!--[\s\S]*?-->\s*#is', '', $c);
            }
            while (strpos($c, "\n\n") !== false) {
                $c = str_replace("\n\n", "\n", $c);
            }
            //short tag
            $compress_html_source .= trim($c);
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
        // 随机种子，如果转到了，重新检测
        if ($this->force && mt_rand(1, $this->force) == 1) {
            $sub_files = explode('|', $sub_files);
            foreach ($sub_files as $tpl_file) {
                $sub_make_time = @filemtime($tpl_file);
                if ($sub_make_time && $sub_make_time > $make_time) {
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
        foreach ($this->conf['view_path'] as $path) {
            if (is_file($path . $filename)) {
                $file                 = $path . $filename;
                $this->sub_tpl[$file] = $file;
                return file_get_contents($file);
                break;
            }
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
        $name  = $matches[1];
        $items = $matches[2];
        if (strpos($items, '$') === FALSE) {
            $items = preg_replace("#\[([\$a-zA-Z_][\w\$]*)\]#is", "['\\1']", $items);
        } else {
            $items = preg_replace("#\[([\$a-zA-Z_][\w\$]*)\]#is", "[\"\\1\"]", $items);
        }
        return '<?=' . $name . $items . '?>';
    }

    /**
     * fix echo array index key
     *
     * @param $name
     * @param $items
     * @return string
     */
    private function array_keyexists($name, $items) {
        return "<? echo isset($name$items)?$name$items:'';?>";
    }

    /**
     * strip tag
     *
     * @param $matchs
     * @return mixed|string
     */
    private function stripvtag_callback($matchs) {
        $pre = $matchs[1];
        $s   = $matchs[2];
        switch ($pre) {
            case 'eval':
                $s                   = '<? ' . $s . '?' . '>';
                $search              = '<!--[eval=' . count($this->tag_search) . ']-->';
                $this->tag_search[]  = $search;
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
            $s = preg_replace('#<\? echo isset\((.*?)\) \? (\\1) : \'\';\?>#is', $instring ? '{\\1}' : '\\1', $s);
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
            // skip script type is tpl
            if (stripos($matches[1], ' type="tpl"') !== false) {
                return $matches[0];
            }
            $search             = '<!--[script=' . count($this->tag_search) . ']-->';
            $this->tag_search[] = $search;
            // filter script comment
            $matches[0] = preg_replace('#(//[^\'";><]*$|/\*[\s\S]*?\*/)#im', '', $matches[0]);
            // replace variable and constant
            // e.g.
            // {$a} {$a[1]} {$a[desc]} {ROOT}
            $matches[0]          = preg_replace('#{((?:\$[\w\[\]]+)|(?:[A-Z_]+))}#s', '<' . '?php echo $1;?' . '>', $matches[0]);
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
        $search              = '<!--[func=' . count($this->tag_search) . ']-->';
        $this->tag_search[]  = $search;
        $this->tag_replace[] = '<? if(false !== ($_val=' . $matchs[1] . '))echo $_val;?>';
        return $search;
    }

    /**
     * block tag callback
     *
     * @param $matchs
     * @return string
     */
    private function blocktag_callback($matchs) {
        $search              = '<!--[block=' . count($this->tag_search) . ']-->';
        $func                = 'block_' . $matchs[1];
        $this->tag_search[]  = $search;
        $this->tag_replace[] = '<? if(!function_exists(\'' . substr($func, 0, strpos($func, '(')) . '\')){function ' . $func . '{?>';
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
            $arr       = $matchs[1];
            $k         = $matchs[2];
            $v         = $matchs[3];
            $statement = $matchs[4];
        } else {
            $arr       = $matchs[1];
            $k         = '';
            $v         = $matchs[2];
            $statement = $matchs[3];
        }

        $arr       = $this->stripvtag($arr);
        $k         = $this->stripvtag($k);
        $v         = $this->stripvtag($v);
        $statement = str_replace("\\\"", '"', $statement);
        return $k ? "<? if(!empty($arr)) { foreach($arr as $k=>&$v) {?>$statement<? }}?>" : "<? if(!empty($arr)) { foreach($arr as &$v) {?>$statement<? }} ?>";
    }
}

?>