<?php

class redis_cache {

    /**
     * @var config of instance
     */
    public $conf;

    /**
     * @param $conf
     * @throws Exception
     */
    public function __construct(&$conf) {
        $this->conf = $conf;
        if (extension_loaded('Redis')) {
            $this->redis = new Redis;
        } else {
            throw new Exception('Redis Extension not loaded.');
        }
        if (!$this->redis) {
            throw new Exception('PHP.ini Error: Redis extension not loaded.');
        }
        if ($this->redis->connect($this->conf['host'], $this->conf['port'])) {
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
            $this->conf['timeout'] && $this->redis->setOption(Redis::OPT_READ_TIMEOUT, $this->conf['timeout']);
            return $this->redis;
        } else {
            throw new Exception('Can not connect to Redis host.');
        }
    }

    /**
     * @return bool
     */
    public function init() {
        return $this->redis === false ? false : true;
    }

    /**
     * @param $key
     * @return string
     */
    public function key($key) {
        return strlen($key) > 16 ? substr(md5($key), 9, 16) : $key;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function decode($value) {
        return substr($value, 0, 4) == '#V1#' ? json_decode(substr($value, 4), 1) : $value;
    }

    /**
     * @param $value
     * @return string
     */
    public function encode($value) {
        return is_string($value) ? $value : '#V1#' . json_encode($value);
    }

    /**
     * @param $key
     * @return array|bool|mixed|string
     */
    public function get($key) {
        $data = array();
        if (is_string($key)) {
            $key = $this->key($key);
            $res = $this->redis->get($key);
            $res = $this->decode($res);
            return $res;
        } else {
            foreach ($key as $k) {
                $arr = $this->redis->get($k);
                $data[$k] = $this->decode($arr);
            }
            return $data;
        }
    }

    /**
     * @param     $key
     * @param     $val
     * @param int $life
     * @return bool
     */
    public function set($key, $val, $life = 0) {
        $key = $this->key($key);
        $ret = $this->redis->setex($key, $life, $this->encode($val));
        return $ret;
    }

    /**
     * @param $key
     * @param $val
     * @return bool
     */
    public function update($key, $val) {
        $arr = $this->get($key);
        if ($arr !== FALSE) {
            is_array($arr) && is_array($val) && $arr = array_merge($arr, $val);
            return $this->hset($key, $arr);
        }
        return FALSE;
    }

    /**
     * @param $key
     * @return int
     */
    public function delete($key) {
        $key = $this->key($key);
        return $this->redis->del($key);
    }

    /**
     * @param string $pre
     * @return bool
     */
    public function truncate($pre = '') {
        return $this->redis->flushdb();
    }
}

?>