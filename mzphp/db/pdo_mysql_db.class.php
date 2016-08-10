<?php

//可能没有安装 PDO 用常量来表示
define('PDO_MYSQL_FETCH_ASSOC', 2);

class pdo_mysql_db {

    var $querynum = 0;
    var $charset;

    var $conf = array();

    /**
     * __construct
     *
     * @param $db_conf
     */
    function __construct(&$db_conf) {
        if (!class_exists('PDO')) {
            throw new Exception('PDO extension was not installed!');
        }
        $this->conf = $db_conf;
    }


    /**
     * get link
     *
     * @param $var
     *
     * @return PDO|void
     */
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
     *
     * @return PDO|void
     */
    function connect(&$db_conf, $server) {
        $host = $db_conf['host'];
        if (strpos($host, ':') !== FALSE) {
            list($host, $port) = explode(':', $host);
        } else {
            $port = 3306;
        }
        if ($db_conf['charset']) {
            $init_sql = 'SET NAMES ' . $db_conf['charset'] . ', sql_mode=""';
        } else {
            $init_sql = 'SET sql_mode=""';
        }
        try {
            $init_array = array(
                PDO::ATTR_PERSISTENT => $db_conf['pconnect'],
            );
            $link       = new PDO("mysql:host={$host};port={$port};dbname={$db_conf['name']}", $db_conf['user'], $db_conf['pass'], $init_array);
            //$link->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            // set encoding + sql mode
            $link->query($init_sql);
        } catch (Exception $e) {
            exit('[pdo_mysql]Cant Connect Pdo_mysql:' . $e->getMessage());
        }
        //$link->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        return $link;
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
    public function exec($sql, $link = NULL) {
        $link = $link ? $link : $this->get_link($sql);
        $n    = $link->exec($sql);
        return $n;
    }

    /**
     * query sql
     *
     * @param $sql
     *
     * @return mixed
     * @throws Exception
     */
    function query($sql, $link = 0) {
        if (DEBUG) {
            $mtime        = explode(' ', microtime());
            $sqlstarttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
        }
        $link = $link ? $link : $this->get_link($sql);

        $type = strtolower(substr(trim($sql), 0, 4));
        if ($type == 'sele' || $type == 'show') {
            $result = $link->query($sql);
        } else {
            $result = $this->exec($sql, $link);
        }

        if ($result === FALSE) {
            $error = $this->error($link);
            throw new Exception('[pdo_mysql]Query Error:' . (isset($error[2]) ? "$error[2]" : '') . ',' . (DEBUG ? $sql : ''));
        }

        if (DEBUG) {
            $mtime       = explode(' ', microtime());
            $sqlendttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
            $sqltime     = round(($sqlendttime - $sqlstarttime), 3);
            $explain     = array();
            $info        = array();
            if ($result && $type == 'sele') {
                $explain_query = $link->query('EXPLAIN ' . $sql);
                $explain       = $this->fetch_array($explain_query);
            }
            $sql               = ($this->is_slave($sql) ? '[slave]' : '[master]') . $sql;
            $_SERVER['sqls'][] = array('sql' => $sql, 'type' => 'mysql', 'time' => $sqltime, 'info' => $info, 'explain' => $explain);
        }

        $this->querynum++;

        return $result;
    }

    /**
     * fetch array
     *
     * @param     $query
     * @param int $result_type
     *
     * @return mixed
     */
    function fetch_array($query, $result_type = PDO_MYSQL_FETCH_ASSOC/*PDO::FETCH_ASSOC*/) {
        return $query->fetch($result_type);
    }

    /**
     * fetch all records
     *
     * @param     $query
     * @param int $result_type
     *
     * @return mixed
     */
    function fetch_all($query, $result_type = PDO_MYSQL_FETCH_ASSOC) {
        return $query->fetchAll($result_type);
    }

    /**
     * fetch first column
     *
     * @param $query
     *
     * @return mixed
     */
    function result($query) {
        return $query->fetchColumn(0);
    }

    /**
     * get affected rows
     *
     * @return mixed
     */
    function affected_rows() {
        return $this->write_link->rowCount();
    }


    /**
     * the error  message
     *
     * @return int
     */
    function error($link) {
        return (($link) ? $link->errorInfo() : 0);
    }

    /**
     * the error number
     *
     * @return int
     */
    function errno($link) {
        return intval(($link) ? $link->errorCode() : 0);
    }


    /**
     * get last insert id
     *
     * @return mixed
     * @throws Exception
     */
    function insert_id() {
        $link = $this->write_link;
        return ($id = $link->lastInsertId()) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()", $link), 0);
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
        $field_sql = '*';
        if (is_array($fields)) {
            $field_sql = implode(',', $fields);
        } else if ($fields) {
            $field_sql = $fields;
        } else {
            $field_sql = '*';
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

        $sql   = 'SELECT ' . $field_sql . ' FROM ' . $table . $where_sql . $order_sql . $limit_sql;
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
        } else if ($where) {
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
            //^(\w+(?:[\+\-\*\/]\s*?\w)+)$
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