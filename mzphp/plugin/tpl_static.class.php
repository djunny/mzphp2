<?php

class tpl_static {
    /**
     * @var array
     */
    static $load_class = array();
    private $version = "1.0";
    /**
     * @var
     */
    private $conf;
    /**
     * @var array
     */
    private $css_file = array();
    /**
     * @var array
     */
    private $js_file = array();
    /**
     * @var array
     */
    private $sprite_file = array();
    /**
     * @var string
     */
    private $static_dir = '';
    /**
     * @var array
     */
    private $exists_file = array();
    /**
     * @var bool|string
     */
    private $expire_key = '';

    /**
     * @param $conf
     */
    public function __construct(&$conf) {
        $this->conf = &$conf;
        // make static directory
        $this->static_dir = $conf['static_dir'];//ROOT_PATH . 'static/' . HOST . '/';
        // use in css background url
        $this->static_url = $conf['static_url'];
        $this->expire_key = date(isset($conf['static_version']) ? $conf['static_version'] : 'ym', $_SERVER['time']);
    }

    /**
     * process js / scss / sprite
     *
     * @param $s template content
     */
    public function process(&$s) {
        $this->css_file = $this->js_file = $this->sprite_file = $this->exists_file = array();
        $changed        = 0;

        // static
        if (strpos($s, '<!--{static') !== false) {
            $s       = preg_replace_callback("#<!--{static\s+(\S*?)\s+?([^}]*?)}-->#is", array($this, 'get_compress'), $s);
            $changed = 1;
        }

        if ($changed) {
            !is_dir($this->static_dir) && mkdir($this->static_dir, 0777, 1);

            // make css file
            if ($this->css_file) {
                foreach ($this->css_file as $filename => $body) {
                    if ($body) {
                        file_put_contents($this->static_dir . $filename, $body);
                    }
                }
            }
            // make js file
            if ($this->js_file) {
                foreach ($this->js_file as $filename => $body) {
                    if ($body) {
                        file_put_contents($this->static_dir . $filename, $body);
                    }
                }
            }
            // copy sprite file
            if ($this->sprite_file) {
                foreach ($this->sprite_file as $filename) {
                    copy($filename, $this->static_dir . basename($filename));
                }
            }

            unset($this->css_file, $this->js_file);
        }
    }

    /**
     * @param $filename
     * @return string
     */
    private function get_compress($filename) {
        if (!isset($filename[1]) || !isset($filename[2])) {
            return '';
        }
        list($compile, $compress) = explode(' ', $filename[2]);
        $compress = is_numeric($compress) ? $compress : 1;
        $filename = $filename[1];
        $mask     = basename($filename);
        // css sprite
        if ($mask == '*') {
            $dirname   = substr($filename, 0, -1);
            $path      = $this->get_template_path($dirname, 0);
            $file      = $this->static_dir . $compile;
            $scss_file = $file . '.scss';

            if ($this->conf['env'] == 'online' && $this->check_file_exists($scss_file)) {

            } else {
                $this->load_lib('sprite');
                $sprite_conf = array(
                    'path'   => $path,
                    'output' => $file,
                );
                $result      = sprite::process($sprite_conf);

                file_put_contents($scss_file, $result['css']);
                // to copy img file
                if ($result['img']) {
                    $this->sprite_file += $result['img'];
                }
            }
        } else {
            // css + js
            $file = $this->get_template_path($filename, 1);
        }
        if (empty($file)) {
            return '';
        }
        $file_url   = $this->static_url . $compile;
        $return_tag = '';

        $is_css = strpos($filename, '.css') !== false ? 1 : 0;
        //header('file_'.$filename.': 1');
        if (strpos($filename, '.scss') !== false || $is_css) {
            if (!isset($this->css_file[$compile])) {
                $this->css_file[$compile] = '/*[tplStatic ' . $this->version . ' - ' . $this->expire_key . ']*/' . "\n";
                $return_tag               = '<link rel="stylesheet" href="' . $file_url . '?' . $this->expire_key . '" />';
            }

            // skip make in online
            if ($this->conf['env'] == 'online' && $this->check_file_exists($compile)) {
                $this->css_file[$compile] = 0;
                return $return_tag;
            }
            // get content of css or scss
            $css_body = file_get_contents($file);
            $this->load_lib('scss');
            if ($is_css) {
                $css_body = scssc::css_compress($css_body, dirname($file) . '/');
            } else {
                if ($compress) {
                    $css_body = scssc::css($css_body, dirname($file) . '/');
                }
            }
            // link static image
            if (strpos($css_body, 'url(') !== false) {
                // remove url quote
                $css_body = preg_replace('#url\s*\(\s*([\'"])([^\)]*?)\\1\s*\)#is', 'url($2)', $css_body);
                // fix img prefix
                //$css_body = str_replace('url(', 'url('.$this->static_url, $css_body);
                $css_body = str_replace('/./', '/', $css_body);
            }
            $this->css_file[$compile] .= $css_body;
        } else if (strpos($filename, '.js') !== false) {
            if (!isset($this->js_file[$compile])) {
                $this->js_file[$compile] = '/*[tplStatic ' . $this->version . ' - ' . $this->expire_key . ']*/' . "\n";
                $return_tag              = '<script src="' . $file_url . '?' . $this->expire_key . '"></' . 'script>';
            }

            // skip make in online
            if ($this->conf['env'] == 'online' && $this->check_file_exists($compile)) {
                $this->js_file[$compile] = 0;
                return $return_tag;
            }
            // add ; fix js concat bug
            // add : .min.js file detect
            if (strpos($file, '.min.') === false) {
                $min_file = substr($file, 0, -3) . '.min.js';
                if (is_file($min_file)) {
                    $compress = 0;
                    $js_body  = file_get_contents($min_file);
                }else{
                    $js_body = file_get_contents($file);
                }
            } else {
                $js_body = file_get_contents($file);
            }
            if ($compress == 1) {
                $this->load_lib('js');
                $js_body = jsMin::minify($js_body);
            }
            $this->js_file[$compile] .= $js_body . ";\n";
        }
        return $return_tag;
    }

    /**
     * get template path
     *
     * @param     $filename
     * @param int $check_file
     * @return string
     */
    private function get_template_path($filename, $check_file = 1) {
        $file = '';
        foreach ($this->conf['view_path'] as $path) {
            if (($check_file && is_file($path . $filename)) ||
                (!$check_file && is_dir($path . $filename))
            ) {
                return $path . $filename;
            }
        }
        return $file;
    }


    /**
     * check file exists
     *
     * @param $file
     * @return mixed
     */
    private function check_file_exists($file) {
        $file = $this->static_dir . $file;
        if (!isset($this->exists_file[$file])) {
            $this->exists_file[$file] = is_file($file);
        }
        return $this->exists_file[$file];
    }

    /**
     * @param $var
     */
    public function load_lib($var) {
        if (!self::$load_class[$var]) {
            include FRAMEWORK_PATH . 'plugin/' . $var . '.class.php';
            self::$load_class[$var] = 1;
        }
    }
}

?>