<?php

/**
 * Class base_control
 */
class base_control {

    /**
     * config for current
     *
     * @var array
     */
    public $conf = array();

    /**
     * @param $conf
     */
    function __construct(&$conf) {
        $this->conf = &$conf;
    }

    /**
     * @param $var
     * @return template
     * @throws Exception
     */
    public function __get($var) {
        // class_exists 会自动去取 spl_autoload_register 注册的方法
        if (class_exists($var)) {
            $this->$var = new $var();
            return $this->$var;
        } else {
            // 如果没有取到 model
            // 读取 $conf['model_map'] 中配置了对应的 model 映射
            $this->$var = core::model($this->conf, $var);
            if (!$this->$var) {
                throw new Exception('Not Found Model:' . $var);
            } else {
                return $this->$var;
            }
        }
    }

    /**
     * @param $method
     * @param $args
     * @throws Exception
     */
    public function __call($method, $args) {
        throw new Exception('base_control.class.php Not implement method：' . $method . ': (' . var_export($args, 1) . ')');
    }

    /**
     * @param string $template
     * @param string $make_file
     * @param string $charset
     */
    public function show($template = '', $make_file = '', $charset = '', $compress = 6, $by_return = 0) {
        $template = $template ? $template : core::R('c') . '_' . core::R('a') . '.htm';
        return VI::display($this, $template, $make_file, $charset, $compress, $by_return);
    }
}

?>