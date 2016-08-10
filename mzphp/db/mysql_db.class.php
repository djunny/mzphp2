<?php

class mysql_db {

    var $queries = 0;
    //var $link;
    var $charset;

    var $conf = array();

    /**
     * __construct
     *
     * @param $db_conf
     */
    function __construct(&$db_conf) {
        if (!function_exists('mysql_connect')) {
            throw new Exception('mysql extension was not installed!');
        }
        $this->conf = $db_conf;
        //$this->connect($db_conf);
    }

    public function __get($var) {
        if ($var == 'write_link') {
            // 默认带 master 下标
            if (isset($this->conf['master'])) {
                $conf = $this->conf['master'];
            } else {
                $conf = $this->conf;
            }

            empty($conf['engine']) && $conf['engine'] = '';
            $this->write_link = $this->connect($conf, 'master');

            return $this->write_link;
        } else if ($var == 'read_link') {
            $slave_count = count($this->conf['slaves']);
            // 指定主库
            if (!$slave_count) {
                $this->read_link = $this->write_link;
                return $this->read_link;
            }
            // 随机拿从库
            $slaves = $this->conf['slaves'];
            $slave  = $slaves[rand(0, $slave_count - 1)];
            empty($slave['engine']) && $slave['engine'] = '';
            $this->read_link = $this->connect($slave, 'slave');
            return $this->read_link;
        }
    }


    /**
     * connect db
     *
     * @param $db_conf
     */
    function connect(&$db_conf, $server = '') {
        if (isset($db_conf['pconnect']) && $db_conf['pconnect']) {
            $link = mysql_pconnect($db_conf['host'], $db_conf['user'], $db_conf['pass']);
        } else {
            $link = mysql_connect($db_conf['host'], $db_conf['user'], $db_conf['pass'], 1);
        }
        if (!$link) {
            throw new Exception('[mysql]Can not connect to MySQL server, error=' . $this->errno() . ':' . $this->error());
        }

        //INNODB
        if (strtoupper($db_conf['engine']) == 'INNODB') {
            $this->query("SET innodb_flush_log_at_trx_commit=no", '', $link);
        }

        $version = $this->version($link);
        if ($version > '4.1') {
            if (isset($db_conf['charset'])) {
                $this->query("SET character_set_connection={$db_conf['charset']}, character_set_results={$db_conf['charset']}, character_set_client=binary", '', $link);
            }
            if ($version > '5.0.1') {
                $this->query("SET sql_mode=''", '', $link);
            }
        }
        $this->select_db($db_conf['name'], $link);
        return $link;

    }

    /**
     * select db
     *
     * @param $dbname
     * @param $link
     *
     * @return bool
     */
    function select_db($dbname, $link) {
        return mysql_select_db($dbname, $link);
    }


    /**
     * get master or slave link  by sql
     *
     * @param $sql
     *
     * @return resource
     */
    function get_link($sql) {
        return $this->is_slave($sql) ? $this->read_link : $this->write_link;
    }

    /**
     * check is slave
     *
     * @param $sql
     *
     * @return bool
     */
    function is_slave($sql) {
        // select / set / show
        $slave_array = array('sele', 'set ', 'show');
        return in_array(strtolower(substr($sql, 0, 4)), $slave_array);
    }

    /**
     * execute sql
     *
     * @param      $sql
     * @param null $link
     *
     * @return mixed
     */
    function exec($sql) {
        $link = $this->get_link($sql);
        return mysql_query($sql, $link);
    }

    /**
     * query sql
     *
     * @param $sql
     *
     * @return mixed
     * @throws Exception
     */
    function query($sql, $type = '', $link = false) {
        if (DEBUG) {
            $sqlendttime  = 0;
            $mtime        = explode(' ', microtime());
            $sqlstarttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
        }
        static $unbuffered_exists = NULL;
        if ($type == 'UNBUFFERED' && $unbuffered_exists == NULL) {
            $unbuffered_exists = function_exists('mysql_unbuffered_query') ? 1 : 0;
        }
        $func  = ($type == 'UNBUFFERED' && $unbuffered_exists) ? 'mysql_unbuffered_query' : 'mysql_query';
        $link  = $link ? $link : $this->get_link($sql);
        $query = $func($sql, $link);
        if ($query === false) {
            throw new Exception('MySQL Query Error, error=' . $this->errno($link) . ':' . $this->error($link) . "\r\n" . (DEBUG ? $sql : ''));
        }
        if (DEBUG) {
            $mtime       = explode(' ', microtime());
            $sqlendttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
            $sqltime     = round(($sqlendttime - $sqlstarttime), 3);
            $explain     = array();
            $info        = mysql_info();
            if ($query && preg_match("/^(select )/i", $sql)) {
                $explain = mysql_fetch_assoc(mysql_query('EXPLAIN ' . $sql, $link));
            }
            $sql               = ($this->is_slave($sql) ? '[slave]' : '[master]') . $sql;
            $_SERVER['sqls'][] = array('sql' => $sql, 'type' => 'mysql', 'time' => $sqltime, 'info' => $info, 'explain' => $explain);
        }
        $this->queries++;
        return $query;
    }

    /**
     * fetch array
     *
     * @param     $query
     * @param int $result_type
     *
     * @return array
     */
    function fetch_array($query, $result_type = MYSQL_ASSOC) {
        return mysql_fetch_array($query, $result_type);
    }

    /**
     * fetch all records
     *
     * @param     $query
     * @param int $result_type
     *
     * @return mixed
     */
    function fetch_all($query) {
        $list = array();
        while ($val = $this->fetch_array($query)) {
            $list[] = $val;
        }
        return $list;
    }

    /**
     * get affected row number
     *
     * @return int
     */
    function affected_rows() {
        return mysql_affected_rows($this->write_link);
    }

    /**
     * error information
     *
     * @return string
     */
    function error($link) {
        return ($link ? mysql_error($link) : mysql_error());
    }

    /**
     * error number
     *
     * @return int
     */
    function errno($link) {
        return intval($link ? mysql_errno($link) : mysql_errno());
    }

    /**
     * fetch first column
     *
     * @param $query
     *
     * @return mixed
     */
    function result($query, $row = 0) {
        $query = @mysql_result($query, $row);
        return $query;
    }

    /**
     * free result
     *
     * @param $query
     *
     * @return bool
     */
    function free_result($query) {
        return mysql_free_result($query);
    }

    /**
     * get last insert id
     *
     * @return mixed
     * @throws Exception
     */
    function insert_id() {
        $link = $this->write_link;
        return ($id = mysql_insert_id($link)) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()", '', $link), 0);
    }


    function fetch_fields($query) {
        return mysql_fetch_field($query);
    }

    function version($link) {
        return mysql_get_server_info($link);
    }

    function close($link) {
        return mysql_close($link);
    }


    /**
     * select table by condition
     *
     * @param     $table   forexample:
     *                     article
     *                     article:id,title
     *                     article:*
     * @param     $where   forexample:
     *                     'a>1'
     *                     array('a'=>1)
     * @param     $order   forexample:
     *                     ' id DESC'
     *                     array(' id DESC', ' name ASC')
     * @param int $perpage limit for perpage show number,
     *                     first of row: perpage = 0
     *                     fetch all: perpage = -1
     *                     count of all: perpage = -2
     * @param int $page    if perpage large than 0 for select page
     *                     (page - 1) * perpage
     *
     * @return mixed
     */
    function select($table, $where, $order = array(), $perpage = -1, $page = 1, $fields = array()) {
        $where_sql = $this->build_where_sql($where);
        $selectsql = '*';
        if (is_array($fields)) {
            $selectsql = implode(',', $fields);
        } else {
            $selectsql = $fields;
        }
        $start       = ($page - 1) * $perpage;
        $fetch_first = $perpage == 0 ? true : false;
        $fetch_all   = $perpage == -1 ? true : false;
        $fetch_count = $perpage == -2 ? true : false;
        $limit_sql   = '';
        if (!$fetch_first && !$fetch_all && !$fetch_count) {
            $limit_sql = ' LIMIT ' . $start . ',' . $perpage;
        }

        $order_sql = '';
        if ($order) {
            $order_sql = $this->build_order_sql($order);
        }

        $sql   = 'SELECT ' . $selectsql . ' FROM ' . $table . $where_sql . $order_sql . $limit_sql;
        $query = $this->query($sql);;
        if ($fetch_first) {
            return $this->fetch_array($query);
        } else {
            return $this->fetch_all($query);
        }
    }

    /**
     * insert or replace data
     *
     * @param $table
     * @param $data
     * @param $return_id
     *
     * @return mixed
     */
    function insert($table, $data, $return_id, $replace = false) {
        $data_sql = $this->build_set_sql($data);
        if (!$data_sql) {
            return 0;
        }
        $method = $replace ? 'REPLACE' : 'INSERT';
        $sql    = $method . ' INTO ' . $table . ' ' . $data_sql;
        $res    = $this->query($sql);
        if ($replace) {
            return $res;
        } else {
            return $return_id ? $this->insert_id() : $res;
        }
    }

    /**
     * replace data
     *
     * @param $table
     * @param $data
     *
     * @return mixed
     */
    function replace($table, $data) {
        return $this->insert($table, $data, 0, true);
    }

    /**
     * update data
     *
     * @param $table
     * @param $data
     * @param $where
     *
     * @return int|mixed
     * @throws Exception
     */
    function update($table, $data, $where) {
        $data_sql  = $this->build_set_sql($data);
        $where_sql = $this->build_where_sql($where);
        if ($where_sql) {
            $sql = 'UPDATE ' . $table . $data_sql . $where_sql;
            return $this->query($sql);
        } else {
            return 0;
        }
    }


    /**
     * delete data
     *
     * @param $table
     * @param $where
     *
     * @return int|mixed
     * @throws Exception
     */
    function delete($table, $where) {
        $where_sql = $this->build_where_sql($where);
        if ($where_sql) {
            $sql = 'DELETE FROM ' . $table . $where_sql;
            return $this->query($sql);
        } else {
            return 0;
        }
    }

    /**
     * build order sql
     *
     * @param $order
     *
     * @return string
     */
    function build_order_sql($order) {
        $order_sql = '';
        if (is_array($order)) {
            $order_sql = implode(', ', $order);
        } else if ($order) {
            $order_sql = $order;
        }
        if ($order_sql) {
            $order_sql = ' ORDER BY ' . $order_sql . ' ';
        }
        return $order_sql;
    }


    /**
     * build where sql
     *
     * @param $where
     *
     * @return string
     */
    function build_where_sql($where) {
        $where_sql = '';
        if (is_array($where)) {
            foreach ($where as $key => $value) {
                if (is_array($value)) {
                    $value = array_map('addslashes', $value);
                    $where_sql .= ' AND ' . $key . ' IN (\'' . implode("', '", $value) . '\')';
                } elseif (strlen($value) > 0) {
                    switch (substr($value, 0, 1)) {
                        case '>':
                        case '<':
                        case '=':
                            $where_sql .= ' AND ' . $key . $this->fix_where_sql($value) . '';
                            break;
                        default:
                            $where_sql .= ' AND ' . $key . ' = \'' . addslashes($value) . '\'';
                            break;
                    }
                } elseif ($key) {
                    if (strpos($key, '=') !== false) {
                        $where_sql .= ' AND ' . $key;
                    }
                }
            }
        } elseif ($where) {
            $where_sql = ' AND ' . $where;
        }
        return $where_sql ? ' WHERE 1 ' . $where_sql . ' ' : '';
    }

    /**
     * fix where sql
     *
     * @param $value
     *
     * @return mixed
     */
    function fix_where_sql($value) {
        $value = preg_replace('/^((?:[><]=?)|=)?\s*(.+)\s*/is', '$1\'$2\'', $value);
        return $value;
    }

    /**
     * sql quot
     *
     * @param $sql
     *
     * @return mixed
     */
    function sql_quot($sql) {
        $sql = str_replace(array('\\', "\0", "\n", "\r", "'", "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\Z'), $sql);
        return $sql;
    }

    /**
     * build set sql
     *
     * @param $data
     *
     * @return string
     */
    function build_set_sql($data) {
        $setkeysql = $comma = '';
        foreach ($data as $set_key => $set_value) {
            if (!preg_match('#^' . $set_key . '\s*?[\+\-\*\/]\s*?\d+$#is', $set_value)) {
                $set_value = '\'' . $this->sql_quot($set_value) . '\'';
            }
            $setkeysql .= $comma . '`' . $set_key . '`=' . $set_value . '';
            $comma = ',';
        }
        return ' SET ' . $setkeysql . ' ';
    }
}

?>