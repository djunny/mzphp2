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

if(!defined('FRAMEWORK_PATH')) {
	exit('FRAMEWORK_PATH not defined.');
}

interface db_interface {

	public function __construct($conf);

	public function get($key);
	
	public function set($key, $data);
	
	public function update($key, $arr);

	public function delete($key);

	/**
	 * 
	 * maxid('user-uid') 返回 user 表最大 uid
	 * maxid('user-uid', '+1') maxid + 1, 占位，保证不会重复
	 * maxid('user-uid', 10000) 设置最大的 maxid 为 10000
	 *
	 */
	public function maxid($table, $val = 0);
	
	// $val == 0, 返回总行数， > 1 ，设置总行数
	public function count($table, $val = 0);

	// 清空一个表
	public function truncate($table);
	
	// 获取版本
	public function version();
	
	// ---------------------> 以下8个方法需要索引支持，否则会产生全表扫描，严重影响速度，不建议使用，强烈建议使用其他方案代替，比如小表，或者 Service。
	/**
		这是一个比较复杂的方法，用来支持条件取id，支持翻页:
		实例：
			index_fetch('user', 'uid', array('uid' => array('>'=>123)), array(), '', 0, 10);
			等价于：SELECT * FROM user WHERE uid>123 LIMIT 0, 10
			
			index_fetch('user', 'uid', array('regdate' => array('>' => 123456789)), array('uid'=>-1), 0, 10);
			等价于：SELECT * FROM user WHERE regdate > 123456789 ORDER BY uid DESC LIMIT 0, 10
			
			index_fetch('user', 'uid', array('regdate'=> array('>'=>123456789), 'groupid' =>1), array('uid' => -1, 'groupid' => 1), 0, 10);
			等价于：SELECT * FROM user where regdate > 123456789 AND groupid = 1 ORDER BY uid DESC groupid ASC LIMIT 0, 10
			
			index_fetch('user', 'uid', array('username'=>array('LIKE'=>'admin')), array(), 0, 10);
			等价于：SELECT * FROM user WHERE username LIKE '%admin%';
	*/
	// 返回ID
	public function index_fetch($table, $keyname, $cond = array(), $orderby = array(), $start = 0, $limit = 0);
	
	// 返回结果集
	public function index_fetch_id($table, $keyname, $cond = array(), $orderby = array(), $start = 0, $limit = 0);
	
	// 批量更新，$lowprority 用来指定优先级，为TRUE时，迅速返回，不返回受影响的行数。
	public function index_update($table, $cond, $update, $lowprority = FALSE);
	
	// 批量删除
	public function index_delete($table, $cond, $lowprority = FALSE);
		
	// 2.4 新增接口，原生的API，准确，但速度慢，仅仅在统计或者同步的时候调用。
	// $key = "$table-$maxcol"
	public function index_maxid($key);
	
	// 2.4 新增接口，原生的API，准确，但速度慢，仅仅在统计或者同步的时候调用。
	public function index_count($table, $cond = array());
	
	/**
		index: 1 正序，-1 倒序
		保留：$index = array('unique'=>TRUE, 'dropDups'=>TRUE) // 针对 Mongodb 有效
		例如：
			index_create('user', array('uid'=>1, 'dateline'=>-1));
			index_create('user', array('uid'=>1, 'dateline'=>-1, 'unique'=>TRUE, 'dropDups'=>TRUE));
	*/
	public function index_create($table, $index);
	
	public function index_drop($table, $index);
	
	public function table_create($table, $cols, $engineer = '');
	
	public function table_drop($table);
	
}

/*
	用法：
	
	$dbconf = array(
		// 主 MySQL Server
		'master' => array (
				'host' => '127.0.0.1',
				'user' => 'root',
				'password' => 'root',
				'name' => 'test',
				'charset' => 'utf8',
				'tablepre' => 'xn_',
				'engine'=>'MyISAM',
		),
		// 从 MySQL Server
		'slaves' => array (
		)
	);
	
	$db = new db_mysql($dbconf);
	
	// 取一条记录, uid 为主键名字
	$user = $db->get("user-uid-$uid");
	
	// 增加一条记录， +1 避免重复
	$uid = $db->maxid('user-uid', '+1');
	$db->set("user-uid-$uid", array('username'=>'admin', 'email'=>'xxx@xxx.com'));
	
	// 存一条记录，覆盖写入
	$db->set("user-uid-$uid", array('username'=>'admin', 'email'=>'xxx@xxx.com'));
	
	// 删除一条记录
	$db->delete("user-uid-$uid");
	
	// 翻页取数据
	$userlist = $db->index_fetch('user', 'uid', array('groupid' => 1), array(), 0, 10);
	$userlist = $db->index_fetch('user', 'uid', array('uid' => array('>', 123)), array(), 0, 10);
	
	// 取记录总数
	$db->count('user');
	
*/

?>