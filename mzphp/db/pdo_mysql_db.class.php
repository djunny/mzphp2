<?php

//可能没有安装 PDO 用常量来表示
define('PDO_MYSQL_FETCH_ASSOC', 2);

class pdo_mysql_db {

    var $querynum = 0;
    var $link;
    var $charset;
    var $init_db = 0;

    /**
     * __construct
     *
     * @param $db_conf
     */
    function __construct(&$db_conf) {
        if (!class_exists('PDO')) {
            throw new Exception('PDO extension was not installed!');
        }
        $this->connect($db_conf);
    }

    /**
     * connect db
     *
     * @param $db_conf
     * @return PDO|void
     */
    function connect(&$db_conf) {
        if ($this->init_db) {
            return;
        }
        $host = $db_conf['host'];
        if (strpos($host, ':') !== FALSE) {
            list($host, $port) = explode(':', $host);
        } else {
            $port = 3306;
        }
        try {
            $link = new PDO("mysql:host={$host};port={$port};dbname={$db_conf['name']}", $db_conf['user'], $db_conf['pass']);
            //$link->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        } catch (Exception $e) {
            exit('[pdo_mysql]Cant Connect Pdo_mysql:' . $e->getMessage());
        }
        $link->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $this->link = $link;

        if ($db_conf['charset']) {
            $link->query('SET NAMES ' . $db_conf['charset'] . ', sql_mode=""');
        } else {
            $link->query('SET sql_mode=""');
        }
        return $link;
    }


    /**
     * execute sql
     *
     * @param      $sql
     * @param null $link
     * @return mixed
     */
    public function exec($sql, $link = NULL) {
        empty($link) && $link = $this->link;
        $n = $link->exec($sql);
        return $n;
    }

    /**
     * query sql
     *
     * @param $sql
     * @return mixed
     * @throws Exception
     */
    function query($sql) {
        if (DEBUG) {
            $sqlendttime = 0;
            $mtime = explode(' ', microtime());
            $sqlstarttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
        }
        $link = &$this->link;

        $type = strtolower(substr(trim($sql), 0, 4));
        if ($type == 'sele' || $type == 'show') {
            $result = $link->query($sql);
        } else {
            $result = $this->exec($sql, $link);
        }

        if (DEBUG) {
            $mtime = explode(' ', microtime());
            $sqlendttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
            $sqltime = round(($sqlendttime - $sqlstarttime), 3);
            $explain = array();
            $info = array();
            //$info = mysql_info();
            if ($result && $type == 'sele') {
                $explain = $this->fetch_array($link->query('EXPLAIN ' . $sql));
            }
            $_SERVER['sqls'][] = array('sql' => $sql, 'type' => 'mysql', 'time' => $sqltime, 'info' => $info, 'explain' => $explain);
        }

        if ($result === FALSE) {
            $error = $this->error();
            throw new Exception('[pdo_mysql]Query Error:' . $sql . ' ' . (isset($error[2]) ? "Errstr: $error[2]" : ''));
        }
        $this->querynum++;

        return $result;
    }

    /**
     * fetch array
     *
     * @param     $query
     * @param int $result_type
     * @return mixed
     */
    function fetch_array(&$query, $result_type = PDO_MYSQL_FETCH_ASSOC/*PDO::FETCH_ASSOC*/) {
        return $query->fetch($result_type);
    }

    /**
     * fetch all records
     *
     * @param     $query
     * @param int $result_type
     * @return mixed
     */
    function fetch_all(&$query, $result_type = PDO_MYSQL_FETCH_ASSOC) {
        return $query->fetchAll($result_type);
    }

    /**
     * fetch first column
     *
     * @param $query
     * @return mixed
     */
    function result(&$query) {
        return $query->fetchColumn(0);
    }

    /**
     * get affected rows
     *
     * @return mixed
     */
    function affected_rows() {
        return $this->link->rowCount();
    }


    /**
     * the error  message
     *
     * @return int
     */
    function error() {
        return (($this->link) ? $this->link->errorInfo() : 0);
    }

    /**
     * the error number
     *
     * @return int
     */
    function errno() {
        return intval(($this->link) ? $this->link->errorCode() : 0);
    }


    /**
     * get last insert id
     *
     * @return mixed
     * @throws Exception
     */
    function insert_id() {
        return ($id = $this->link->lastInsertId()) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
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
        $start = ($page - 1) * $perpage;
        $fetch_first = $perpage == 0 ? true : false;
        $fetch_all = $perpage == -1 ? true : false;
        $fetch_count = $perpage == -2 ? true : false;
        $limit_sql = '';
        if (!$fetch_first && !$fetch_all && !$fetch_count) {
            $limit_sql = ' LIMIT ' . $start . ',' . $perpage;
        }

        $order_sql = '';
        if ($order) {
            $order_sql = $this->build_order_sql($order);
        }

        $sql = 'SELECT ' . $field_sql . ' FROM ' . $table . $where_sql . $order_sql . $limit_sql;
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
     * @return mixed
     */
    function insert($table, $data, $return_id, $replace = false) {
        $data_sql = $this->build_set_sql($data);
        if (!$data_sql) {
            return 0;
        }
        $method = $replace ? 'REPLACE' : 'INSERT';
        $sql = $method . ' INTO ' . $table . ' ' . $data_sql;
        $this->query($sql);
        if ($replace) {
            return 0;
        } else {
            return $return_id ? $this->insert_id() : 0;
        }
    }

    /**
     * replace data
     *
     * @param $table
     * @param $data
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
     * @return int|mixed
     * @throws Exception
     */
    function update($table, $data, $where) {
        $data_sql = $this->build_set_sql($data);
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