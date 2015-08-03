<?php

class VI {
    /**
     * instance for VI
     *
     * @var null
     */
    private static $instance = NULL;

    /**
     * get instance
     *
     * @return template
     */
    public static function instance() {
        static $inited = 0;;
        if (!$inited) {
            self::$instance = new template();
            $inited = 1;
        }
        return self::$instance;
    }

    /**
     * reset for instance
     */
    public static function reset() {
        unset(self::$instance);
        self::$instance = new template();
    }

    /**
     * @param $var
     * @param $val
     */
    public static function assign($var, &$val) {
        self::instance()->assign($var, $val);
    }

    /**
     * assign value
     *
     * @param $var
     * @param $val
     */
    public static function assign_value($var, $val) {
        self::instance()->assign_value($var, $val);
    }

    /**
     * display template
     *
     * @param        $control
     * @param        $template
     * @param string $makefile
     * @param string $charset
     */
    public static function display($control, $template, $makefile = '', $charset = '') {
        return self::instance()->show($control->conf, $template, $makefile, $charset);
    }
}

?>