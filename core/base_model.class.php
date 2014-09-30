<?php

/*
 * XiunoPHP v1.2
 * http://www.xiuno.com/
 *
 * Copyright 2010 (c) axiuno@gmail.com
 * GNU LESSER GENERAL PUBLIC LICENSE Version 3
 * http://www.gnu.org/licenses/lgpl.html
 *
 */

/**

	架构统一了 各种 cache db 对外的接口。
	在开启 cache 的情况下：
		读取走 cache
		写入两份： cache & db
		
	// db
		$this->db_get();
		$this->db_set();
		$this->db_update();
		$this->db_delete();
		$this->db->maxid();
		$this->db->count();
		
	// cache
		$this->cache_get();
		$this->cache_set();
		$this->cache_update();
		$this->cache_delete();
		$this->cache->maxid();
		$this->cache->count();
		
	// db + cache
		$this->db_cache_get();
		$this->db_cache_set();
		$this->db_cache_update();
		$this->db_cache_delete();
		$this->db_cache_truncate();
		$this->db_cache_maxid();
		$this->db_cache_count();
		$this->db_cache_index_fetch();
	
	// db + cache + modelname (需要设置 $this->table 表示哪个 model)， 在这一层，可以直接读取 db, cache ，但是不推荐。可以在此层连接索引服务
		$this->get();
		$this->set();
		$this->create();	// 如果有 maxid, 则 +1
		$this->update();
		$this->read();
		$this->delete();	// 如果有 maxid, 则 -1
		$this->truncate();	// 清空
		$this->maxid();
		$this->count();
		$this->index_fetch();
		$this->index_maxid();
		$this->index_count();
		$this->index_update();
		$this->index_delete();
	
	// db + cache + table + 业务逻辑 (最终提供给 control 层的方法) class xxx_model extends base_model {}
		$this->xread();
		$this->xcreate();
		$this->xupdate();
		$this->xdelete();	// 关联删除，包含紧密关联的业务逻辑数据的更新（比如统计数）和删除
		$this->get_list_by_xx();// 获取列表数据
		
*/
class base_model {

	// 当前应用的配置
	public $conf = array();			// 配置文件，包含各种选项，实际是全局的 $conf
	public $old_conf = array();		// 用来保存从参数传递过来的配置参数。

	// 如果需要自动加载model，必须指定这三项！
	public $table;				// 用来标示 table
	public $primarykey = array();		// 主键中包含的字段，如 ('uid'), ('fid', 'uid')
	public $maxcol = '';			// 自增的列名字
	
	// 配置文件必须由配置文件指定
	
	static $dbinsts = array();		// 避免重复链接
	static $cacheinsts = array();		//
	
	private $unique;			// 唯一键数组，用来防止重复查询
	function __construct(&$conf) {
		$this->conf = &$conf;		// 此处引用。因为多个model，可能每个model有自己的db, cache 服务器
		$this->old_conf = array();	// 原始的 $conf，默认为空，一旦被指定，则优先加载此项。
	}

	function __get($var) {
		if($var == 'db') {
			$this->$var = $this->get_db_instance();
			return $this->$var;
		} elseif($var == 'cache') {
			$this->$var = $this->get_cache_instance();
			return $this->$var;
		} else {
			// 遍历全局的 conf，包含 model
			if(!empty($this->old_conf)) {
				$conf = &$this->old_conf;
			} else {
				$conf = &$this->conf;
			}
			$this->$var = core::model($conf, $var);
			if(!$this->$var) {
				throw new Exception('Not found model:'.$var);
			}
			return $this->$var;
		}
	}

	function __call($method, $parms) {
		throw new Exception("$method does not exists.");
	}
	
	// 多加一层 ----------------------> 用来按照条件分库分服务器
	
	// 重载此函数，用来分库，根据 key，分配到不同的 db server 上，实现任意分库，不过索引不好分，那样得做专门的索引服务器，跟存储分开。
	public function get_db_conf($key = '') {
		return $this->conf['db'];
	}
	
	// 这里都是一堆 hash 算法(php key 读取对应的是 C 的 hash 算法 )，对效率有那么一点点影响，不过 db 操作比较少，支持分布式以后这点损失微乎其微，所以可以忽略。
	public function get_db_instance($key = '', $conf = array()) {
		empty($conf) && $conf = $this->get_db_conf($key);
		$type = $conf['type'];
		if(isset($conf[$type])) {
			$c = $conf[$type];
			$master = $c['master'];
			!isset($master['tablepre']) && $master['tablepre'] = '';
			!isset($master['name']) && $master['name'] = '';
			$id = $type.'-'.$master['host'].'-'.$master['user'].'-'.$master['password'].'-'.$master['name'].'-'.$master['tablepre'];
		} else {
			$c = array();
			$id = $type;
		}
		if(isset(self::$dbinsts[$id])) {
			return self::$dbinsts[$id];
		} else {
			$dbname = 'db_'.$type;
			self::$dbinsts[$id] = new $dbname($c);
			return self::$dbinsts[$id];
		}
	}
	
	public function db_get($key) {
		return $this->get_db_instance($key)->get($key);
	}
	
	public function db_set($key, $data) {
		return $this->get_db_instance($key)->set($key, $data);
	}
	
	public function db_update($key, $data) {
		return $this->get_db_instance($key)->update($key, $data);
	}
	
	public function db_delete($key) {
		return $this->get_db_instance($key)->delete($key);
	}
	
	// 重载此函数，用来分布到不同的 cache server
	public function get_cache_conf($key = '') {
		return $this->conf['cache'];
	}
	
	public function get_cache_instance($key = '') {
		$conf = $this->get_cache_conf($key);
		!isset($conf[$conf['type']]) && $conf[$conf['type']] = array('host'=>'', 'port'=>0);
		$c = $conf[$conf['type']];
		$id = $conf['type'].'-'.$c['host'].'-'.$c['port'];
		if(isset(self::$cacheinsts[$id])) {
			return self::$cacheinsts[$id];
		} else {
			$type = $conf['type'];
			$cachename = 'cache_'.$type;
			self::$cacheinsts[$id] = new $cachename($conf[$type]);
			return self::$cacheinsts[$id];
		}
	}
	
	public function cache_get($key) {
		return $this->get_cache_instance($key)->get($key);
	}
	
	public function cache_set($key, $data) {
		return $this->get_cache_instance($key)->set($key, $data);
	}
	
	public function cache_update($key, $data) {
		return $this->get_cache_instance($key)->update($key, $data);
	}
	
	public function cache_delete($key) {
		return $this->get_cache_instance($key)->delete($key);
	}
	
	// -------------------------> 以下接口符合 db_interface & cache_interface 标准。在数据量小的情况下（数据表小于2G），直接开启可以起到很好的加速效果。
	
	public function db_cache_get($key) {
		if($this->conf['cache']['enable']) {
			$arr = $this->cache_get($key);
			if(!$arr) {
				$arrlist = $this->db_get($key);
				// 更新到 cache
				if(is_array($key)) {
					foreach((array)$arrlist as $k=>$v) {
						$this->cache_set($k, $v);
					}
				} else {
					$this->cache_set($key, $arrlist);
				}
				return $arrlist;
			} else {
				// 检查缺失，如果为 FALSE 这表示缓存里没有
				foreach($arr as $k=>&$v) {
					if($v === FALSE) {
						$v = $this->db_get($k);
						$this->cache_set($k, $v);
					}
				}
				return $arr;
			}
		} else {
			return $this->db_get($key);
		}
	}
	
	public function db_cache_set($key, $data, $life = 0) {
		$this->conf['cache']['enable'] && $this->cache_set($key, $data, $life);	// 更新缓存
		return $this->db_set($key, $data);
	}

	public function db_cache_update($key, $data) {
		$this->conf['cache']['enable'] && $this->cache_update($key, $data);	// 更新缓存
		return $this->db_update($key, $data);
	}

	public function db_cache_delete($key) {
		$this->conf['cache']['enable'] && $this->cache_delete($key);
		return $this->db_delete($key);
	}

	public function db_cache_truncate() {
		$this->conf['cache']['enable'] && $this->cache->truncate($this->table);
		return $this->db->truncate($this->table);
	}

	// $val == 0，返回最大ID，也可以为 +1
	public function db_cache_maxid($val = FALSE) {
		$key = $this->table.'-'.$this->maxcol;
		//$db = $this->get_db_instance($key, $this->conf['db']); // 返回 arbiter 实例
		if($this->conf['cache']['enable']) {
			$this->cache->maxid($key, $val);	// 更新缓存
			return $this->db->maxid($key, $val);
		} else {
			return $this->db->maxid($key, $val);
		}
	}
	
	// 返回总行数，无法 +1
	public function db_cache_count($val = FALSE) {
		$key = $this->table;
		if($this->conf['cache']['enable']) {
			$this->cache->count($key, $val);
			return $this->db->count($key, $val);
		} else {
			return $this->db->count($key, $val);
		}
	}
	
	// todo: 如果数据分服务器，则索引存取可以考虑 map-reduce 模型，如何封装还未确定，还是业务逻辑自己处理？
	public function db_cache_index_fetch($table, $keyname, $cond = array(), $orderby = array(), $start = 0, $limit = 10) {
		// 判断类型，如果为 mongodb 则直接返回结果集，如果为 mysql ， 则先取ID，然后从memcached取
		if($this->conf['db']['type'] == 'mongodb') {
			return $this->db->index_fetch($table, $keyname, $cond, $orderby, $start, $limit);
		} else {
			if($this->conf['cache']['enable']) {
				$keynames = $this->db->index_fetch_id($table, $keyname, $cond, $orderby, $start, $limit);
				return $this->db_cache_get($keynames);
			} else {
				return $this->db->index_fetch($table, $keyname, $cond, $orderby, $start, $limit);
			}
		}
	}
	
	// -----------------------------> 提供更高层次的封装，所有符合标准的表结构都可以使用以下方法
	
	/*
		获取多行数据：
		$this->user->mget(array(1, 2, 3));					// 如果 primary key 只有一列
		$this->user->mget(array(array(1, 1), array(1, 2), array(1, 3)));	// 如果 primary key 多列	
	*/
	public function mget($keys) {
		// 这里顺序应该没有问题，按照key顺序预留了NULL值。
		$arrlist = array();
		foreach($keys as $k=>$key) {
			$key = $this->to_key($key);
			$keys[$k] = $key;
			if(isset($this->unique[$key])) {
				$arrlist[$key] = $this->unique[$key];
				unset($keys[$k]);
			} else {
				$arrlist[$key] = NULL;
				$this->unique[$key] = $arrlist[$key];
			}
		}
		$arrlist2 = $this->db_cache_get($keys);
		$arrlist = array_merge($arrlist, $arrlist2);
		return $arrlist;
	}
	
	/*
		此接口中的 $key 参数格式不同于 db , cache 中的 get()
		获取一行数据：
		$this->user->get(1);			// 如果 primary key 只有一列
		$this->user->get(array(1, 2));		// 如果 primary key 多列	
	*/
	public function get($key) {
		// 支持数组
		$key = $this->to_key($key);
		if(!isset($this->unique[$key])) {
			$this->unique[$key] = $this->db_cache_get($key);
		}
		return $this->unique[$key];
	}
	
	/*
		此接口中的 $key 参数格式不同于 db , cache 中的 set()
		设置一行数据：
		$this->user->set(1, array('username'=>'zhangsan', 'email'=>'zhangsan@gmail.com'));
		$this->user->set(array(1, 2), array('username'=>'zhangsan', 'email'=>'zhangsan@gmail.com'));
	*/
	// $life 参数为过期时间，预留给 model class 重载
	public function set($key, $arr, $life = 0) {
		$key = $this->to_key($key);
		$this->unique[$key] = $arr;
		return $this->db_cache_set($key, $arr);
	}
	
	/*
		创建一行数据：
		$user = array('username'=>'abc', 'email'=>'abc@gmail.com');
		$this->user->create($user);
	*/
	public function create($arr) {
		if(!empty($this->maxcol)) {
			if(!isset($arr[$this->maxcol])) {
				$arr[$this->maxcol] = $this->maxid('+1');	// 自增
				$key = $this->get_key($arr);
			} else {
				$key = $this->get_key($arr);
				// 查询是否已经存在，防止覆盖
				$arr2 = $this->db_cache_get($key);
				if(!empty($arr2)) {
					return FALSE;
				}
			}
			$this->count('+1');
			if($this->db_cache_set($key, $arr)) {
				return $arr[$this->maxcol];
			} else {
				$this->maxid('-1');
				$this->count('-1');
				return FALSE;
			}
		} else {
			// 如果没有设置 maxcol, 则执行处理 count(), maxid()
			$key = $this->get_key($arr);
			$this->db_cache_set($key, $arr);
			return TRUE;
		}
	}
	
	/*
		更新一行数据：
		$user = $this->user->read(1);
		$user['username'] = 'abc';
		$this->user->update($user);
	*/
	public function update($arr) {
		$key = $this->get_key($arr);
		$this->unique[$key] = $arr;
		return $this->db_cache_update($key, $arr);
	}
	
	/*
		读取一行数据:
		$this->user->read(1);			// 如果 primary key 只有一列
		$this->user->read(array(1, 2));		// 如果 primary key 多列	
		$this->user->read(1, 2);		// 如果 primary key 多列 （更简洁的写法，最多支持4列）
	*/
	public function read($key, $arg2 = FALSE, $arg3 = FALSE, $arg4 = FALSE) {
		// func_get_args() 这个函数有些环境不支持
		$key = (array)$key;
		$arg2 !== FALSE && array_push($key, $arg2);
		$arg3 !== FALSE && array_push($key, $arg3);
		$arg4 !== FALSE && array_push($key, $arg4);
		
		$key = $this->to_key($key);
		return $this->db_cache_get($key);
	}
	
	/*
		删除一行数据:
		$this->user->delete(1);			// 如果 primary key 只有一列
		$this->user->delete(array(1, 2));	// 如果 primary key 多列	
		$this->user->delete(1, 2);		// 如果 primary key 多列 （更简洁的写法，最多支持4列）
	*/
	public function delete($key, $arg2 = FALSE, $arg3 = FALSE, $arg4 = FALSE) {
		// func_get_args() 这个函数有些环境不支持
		$key = (array)$key;
		$arg2 !== FALSE && array_push($key, $arg2);
		$arg3 !== FALSE && array_push($key, $arg3);
		$arg4 !== FALSE && array_push($key, $arg4);
		
		$key = $this->to_key($key);
		unset($this->unique[$key]);
		
		if(!empty($this->maxcol)) {
			// 如果 key 存在，则 -1， 防止误操作。
			if($this->db_cache_get($key)) {
				$this->count('-1');
			}
		}
		return $this->db_cache_delete($key);
	}
	
	// 清空数据
	public function truncate() {
		return $this->db_cache_truncate();
	}
	
	/*
		获取/设置最大的 maxid
		$this->user->maxid();
		$this->user->maxid(100);
		$this->user->maxid('+1');
		$this->user->maxid('-1');
	*/
	public function maxid($val = FALSE) {
		return $this->db_cache_maxid($val);
	}
	
	/*
		自行计数, 获取/设置最大的 count
		$this->user->count();
		$this->user->count(100);
		$this->user->count('+1');
		$this->user->count('-1');
	*/
	public function count($val = FALSE) {
		$key = $this->table;
		return $this->db_cache_count($val);
	}
	
	public function index_fetch($cond = array(), $orderby = array(), $start = 0, $limit = 10) {
		return (array)$this->db_cache_index_fetch($this->table, $this->primarykey, $cond, $orderby, $start, $limit);	
	}
	
	public function index_fetch_id($cond = array(), $orderby = array(), $start = 0, $limit = 10) {
		return (array)$this->db->index_fetch_id($this->table, $this->primarykey, $cond, $orderby, $start, $limit);	
	}
	
	// 2.4 新增接口，按照条件更新，不鼓励使用
	public function index_update($cond, $update, $lowprority = FALSE) {
		$n = $this->index_count($cond);
		$m = 0;
		if(!empty($this->conf['cache']['enable'])) {
			if($n > 2000) {
				// 清空缓存
				$this->unique = array();
				$this->cache->truncate($this->table);
				$m = $this->db->index_update($this->table, $cond, $update, $lowprority);
			
			} else {
				// 一条一条的删除缓存
				$keys = $this->index_fetch_id($cond);
				foreach($keys as $key) {
					unset($this->unique[$key]);
					$this->cache_delete($key);
				}
				$m = $this->db->index_update($this->table, $cond, $update, $lowprority);
			}
		} else {
			$this->unique = array();
			$m = $this->db->index_update($this->table, $cond, $update, $lowprority);
		}
		$n = $m ? $m : $n; // 大并发插入下，这个值可能不准，$m != $n，需要定期手工校对。
		return $n;
	}
	
	// 2.4 新增接口，按照条件删除，不鼓励使用
	public function index_delete($cond, $lowprority = FALSE) {
		// 影响的行数
		$n = $this->index_count($cond);
		$m = 0;
		if($n == 0) return 0;
		if(!empty($this->conf['cache']['enable'])) {
			// 判断影响的行数，如果超过2000行，则清空缓存，否则一条一条的删除
			if($n > 2000) {
				// 清空缓存
				$this->unique = array();
				$this->cache->truncate($this->table);
				$m = $this->db->index_delete($this->table, $cond, $lowprority);
		
			} else {
				// 一条一条的删除
				$keys = $this->index_fetch_id($cond);
				foreach($keys as $key) {
					unset($this->unique[$key]);
					$this->db_cache_delete($key);
				}
				$m = $this->db->index_delete($this->table, $cond, $lowprority);
			}
		} else {
			$this->unique = array();
			$m = $this->db->index_delete($this->table, $cond, $lowprority);
		}
		$n = $m ? $m : $n; // 大并发插入下，这个值可能不准，$m != $n，需要定期手工校对。
		if($n > 0 && !empty($this->maxcol)) {
			$this->count('-'.$n);
		}
		return $n;
	}
	
	// 原生的API，准确，但速度慢，仅仅在统计或者同步的时候调用。
	public function index_maxid() {
		return isset($this->maxcol) ? $this->db->index_maxid($this->table.'-'.$this->maxcol) : 0;
	}
	
	// 原生的API，准确，但速度慢，仅仅在统计或者同步的时候调用。
	public function index_count($cond = array()) {
		return $this->db->index_count($this->table, $cond);
	}
	
	// $index = array('uid'=>1, 'dateline'=>-1)
	public function index_create($index) {
		$this->db->index_create($this->table, $index);
	}
	
	public function index_drop($index) {
		$this->db->index_drop($this->table, $index);
	}
	
	// 从 arr 中提取 key string
	/*
		array('uid'=>123, 'username'=>'abc', 'email'=>'xx@xx.com')
		'user-uid-123'
	*/
	public function get_key($arr) {
		$s = $this->table;
		foreach($this->primarykey as $v) {
			$s .= "-$v-".$arr[$v];
		}
		return $s;
	}
	
	// 数组 to key
	/*
		array(1, 2)
		'thread-fid-1-tid-2'
	*/
	public function to_key($key) {
		$s = $this->table;
		foreach((array)$key as $k=>$v) {
			$s .= '-'.$this->primarykey[$k].'-'.$v;
		}
		return $s;
	}
}
?>