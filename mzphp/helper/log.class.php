<?php

class log {
    /**
     * @var int log file name
     */
    public static $log_file = 0;
    /**
     * @var int log file file pointer
     */
    public static $log_fp = 0;

    /**
     * init to log
     *
     * @param $file
     */
    public static function set_logfile($file) {
        self::$log_file = $file;
        self::$log_fp = fopen($file, 'a+');
    }

    /**
     * dump variable for log
     *
     * @param $data
     * @return string
     */
    public static function dump_var($data) {
        if (is_array($data)) {
            $str = '';
            foreach ($data as $k => $v) {
                if (is_array($v)) {
                    $str .= '[' . $k . '=' . self::dump_var($v) . ']';
                } else {
                    $str .= '[' . $k . '=' . $v . ']';
                }
            }
            return $str;
        } else {
            return '[' . $data . ']';
        }
    }

    /**
     * log::info($arg1,$arg2....$argn);
     *
     * @param mixed
     */
    public static function info() {
        $arg_list = func_get_args();
        $log = '';
        for ($i = 0, $l = func_num_args(); $i < $l; $i++) {
            $log .= self::dump_var($arg_list[$i]);
        }
        $log .= '[' . core::usedtime() . "ms]";
        $log = "[" . date('H:i:s') . "]" . $log . "\r\n";
        if (core::is_cmd()) {
            echo $log;
        }
        if (self::$log_fp) {
            fputs(self::$log_fp, $log);
        }
    }
}

?>