<?php

/**
 * @name XiunoPHP Redis驱动类
 *
 * @author Andy Lee，由 twcms 作者空城做修正
 *
 * @copyright lostman.org
 *
 * @since redis操作参考：http://www.cnblogs.com/ikodota/archive/2012/03/05/php_redis_cn.html   暂为对muti操作提供支持。
 *
 */
if (!defined('FRAMEWORK_PATH')) {
	exit('FRAMEWORK_PATH not defined.');
}

class cache_redis implements cache_interface {
	public $conf;

	public function __construct($conf) {
		$this->conf = $conf;
	}

	public function __get($var) {
		if ($var == 'redis') {
			if (extension_loaded('Redis')) {
				$this->redis = new Redis;
			} else {
				throw new Exception('Redis Extension not loaded.');
			}
			if (!$this->redis) {
				throw new Exception('PHP.ini Error: Redis extension not loaded.');
			}
			if ($this->redis->connect($this->conf['host'], $this->conf['port'])) {
				return $this->redis;
			} else {
				throw new Exception('Can not connect to Redis host.');
			}
		}
	}

	public function get($key) {
		$data = array();
		if (is_array($key)) {
			foreach ($key as $k) {
				$arr = $this->redis->hgetall($k);
				$data[$k] = $arr;
			}
			return $data;
		} else {
			return $this->redis->hgetall($key);
		}
	}

	public function set($key, $val, $life = 0) {
		$ret = $this->redis->hmset($key, $val);
		if($life && $ret) {
			$this->redis->expire($key, $life);
		}
		return $ret;
	}

	public function update($key, $val) {
		$arr = $this->get($key);
		if($arr !== FALSE) {
			is_array($arr) && is_array($val) && $arr = array_merge($arr, $val);
			return $this->set($key, $arr);
		}
		return FALSE;
	}

	public function delete($key) {
		return $this->redis->hdel($key);
	}

	public function truncate($pre = '') {
		return $this->redis->flushdb();
	}

	public function maxid($table, $val = FALSE) {
		$key = $table . '-Auto_increment';
		if ($val === FALSE) {
			return intval($this->get($key));
		} elseif (is_string($val) && $val{0} == '+') {
			$val = intval($val);
			$val+= intval($this->get($key));
			$this->set($key, $val);
			return $val;
		} else {
			$this->set($key, $val);
			return $val;
		}
	}

	public function count($table, $val = FALSE) {
		$key = $table . '-Rows';
		if ($val === FALSE) {
			return intval($this->get($key));
		} elseif (is_string($val)) {
			if ($val{0} == '+') {
				$val = intval($val);
				$n = intval($this->get($key)) + $val;
				$this->set($key, $n);
				return $n;
			} else {
				$val = abs(intval($val));
				$n = max(0, intval($this->get($key)) - $val);
				$this->set($key, $n);
				return $n;
			}
		} else {
			$this->set($key, $val);
			return $val;
		}
	}
}
?>