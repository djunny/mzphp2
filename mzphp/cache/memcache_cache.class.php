<?php

class memcache_cache {

    /**
     * multi get support
     *
     * @var bool
     */
    private $support_getmulti;
    /**
     * memcache link
     *
     * @var Memcache|null
     */
    private $link = NULL;
    /**
     * how many servers connect
     *
     * @var int
     */
    private $servers = 0;
    /**
     * @var int
     */
    private $memcached = 0;

    /**
     * @param $conf
     * @throws Exception
     */
    function __construct(&$conf) {
        $this->support_getmulti = false;
        if (extension_loaded('Memcached')) {
            $this->link = new Memcached;
            $this->support_getmulti = true;
            $this->memcached = 1;
        } elseif (extension_loaded('Memcache')) {
            $this->link = new Memcache;
        } else {
            throw new Exception('Memcache Extension not loaded.');
        }

        $hosts = $conf['host'];
        if (!is_array($hosts)) {
            $hosts = explode('|', $conf['host']);
        }

        $this->servers = 0;
        foreach ($hosts as $host) {
            $host = $this->get_host_by_str($host);
            if ($this->link->addServer($host['host'], $host['port'])) {
                $this->servers++;
            }
        }

        if ($this->servers) {
            return $this->link;
        }

        return false;
    }

    /**
     * @param $host
     * @return array
     */
    private function get_host_by_str($host) {
        list($host, $port) = explode(':', $host);
        return array(
            'host' => $host,
            'port' => $port ? $port : 11211,
        );
    }

    /**
     * @return bool
     */
    public function init() {
        return $this->link === false ? false : true;
    }

    /**
     * @param $key
     * @return array|string
     */
    public function get($key) {
        $data = array();
        if (is_array($key)) {
            // 安装的时候要判断 Memcached 版本！ getMulti()
            if ($this->support_getmulti) {
                $arrlist = $this->link->getMulti($key);
                // 会丢失 key!，补上 key
                foreach ($key as $k) {
                    !isset($arrlist[$k]) && $arrlist[$k] = FALSE;
                }
                return $arrlist;
            } else {
                foreach ($key as $k) {
                    $arr = $this->link->get($k);
                    $data[$k] = $arr;
                }
                return $data;
            }
        } else {
            $data = $this->link->get($key);
            return $data;
        }
    }

    /**
     * @param     $key
     * @param     $value
     * @param int $life
     * @return bool
     */
    public function set($key, $value, $life = 0) {
        if ($this->memcached) {
            return $this->link->set($key, $value, $life);
        } else {
            return $this->link->set($key, $value, 0, $life);
        }
    }

    /**
     * @param $key
     * @param $value
     * @return bool|int
     */
    public function update($key, $value) {
        $arr = $this->get($key);
        if ($arr !== FALSE) {
            is_array($arr) && is_array($value) && $arr = array_merge($arr, $value);
            return $this->set($key, $arr);
        }
        return 0;
    }

    /**
     * @param $key
     * @return bool
     */
    public function delete($key) {
        return $this->link->delete($key);
    }

    /**
     * @param string $pre
     * @return bool
     */
    public function truncate($pre = '') {
        return $this->link->flush();
    }

}

?>