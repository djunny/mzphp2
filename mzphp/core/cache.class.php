<?php

class CACHE {
    /**
     * cache type
     *
     * @var string
     */
    private static $cache_type = '';
    /**
     * cache prefix
     *
     * @var string
     */
    private static $cache_pre = '';
    /**
     * cache config
     *
     * @var string
     */
    private static $cache_conf = array();
    /**
     * instance of cache
     *
     * @var null
     */
    private static $instance = NULL;

    /**
     * init cache
     *
     * @param $conf
     */
    public static function init_cache_config(&$conf) {
        self::$cache_conf = $conf;
    }

    /**
     * @return object instance of cache
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            //find cache engine
            foreach (self::$cache_conf as $type => $conf) {
                $cache_enigne = $type . '_cache';
                self::$cache_type = $type;
                self::$cache_pre = isset($conf['pre']) ? $conf['pre'] : '';
                self::$instance = new $cache_enigne($conf);
                if (!self::$instance->init()) {
                    self::$instance = false;
                } else {
                    break;
                }
            }
        }
        return self::$instance;
    }

    /**
     * @param $key
     * @return string
     */
    public static function key($key) {
        if (is_array($key)) {
            foreach ($key as &$k) {
                $k = self::key($k);
            }
        } else {
            $key = self::$cache_pre . $key;
        }
        return $key;
    }

    /**
     * check cache open
     *
     * @return bool
     */
    public static function opened() {
        return self::instance() == false ? false : true;
    }

    /**
     * get cache by key
     *
     * @param $key
     * @return mixed
     */
    public static function get($key) {
        if (DEBUG) {
            $_SERVER['cache']['get'][] = $key;
        }
        return call_user_func(array(self::instance(), 'get'), self::key($key));
    }

    /**
     * set cache
     *
     * @param     $key
     * @param     $val
     * @param int $expire
     * @return mixed
     */
    public static function set($key, $val, $expire = 0) {
        if (DEBUG) {
            $_SERVER['cache']['set'][] = func_get_args();
        }
        return call_user_func(array(self::instance(), 'set'), self::key($key), $val, $expire);
    }

    /**
     * update cache
     *
     * @param $key
     * @param $val
     * @param $expire
     * @return mixed
     */
    public static function update($key, $val, $expire) {
        if (DEBUG) {
            $_SERVER['cache']['update'][] = func_get_args();
        }
        return call_user_func(array(self::instance(), 'update'), self::key($key), $val, $expire);
    }

    /**
     * update cache
     *
     * @param $key
     * @return mixed
     */
    public static function delete($key) {
        if (DEBUG) {
            $_SERVER['cache']['delete'][] = func_get_args();
        }
        return call_user_func(array(self::instance(), 'delete'), self::key($key));
    }

    /**
     * truncate cache
     *
     * @param string $pre
     * @return mixed
     */
    public static function truncate($pre = '') {
        if (DEBUG) {
            $_SERVER['cache']['truncate'][] = func_get_args();
        }
        return call_user_func(array(self::instance(), 'truncate'), $pre);
    }

    /**
     *  lock by cache provider
     *
     * @param     $key
     * @param int $expire        expire time form lock
     * @param int $max_lock_time max lock time
     * @return bool
     */
    public static function lock($key, $expire = 10000, $max_lock_time = 1000) {
        $key = '_lock_' . $key;
        $sleep_time = 5000;
        $sleep_count = 0;
        if (self::get($key)) {
            while (true) {
                usleep($sleep_time);
                // until lock
                if (!self::get($key)) {
                    break;
                }
                $sleep_count += $sleep_time / 1000;
                if ($max_lock_time && $max_lock_time >= $sleep_count) {
                    return false;
                }
            }
        }
        self::set($key, 1, $expire);
        return true;
    }

    /**
     * unlock by cache
     *
     * @param $key
     */
    public static function unlock($key) {
        $key = '_lock_' . $key;
        return self::delete($key);
    }
}

?>