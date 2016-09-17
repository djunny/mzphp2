<?php

//可能没有安装 PDO 用常量来表示
define('PDO_SQLITE_FETCH_ASSOC', 2);

class pdo_sqlite_db {

    var $querynum = 0;
    var $link;
    var $charset;
    var $init_db  = 0;

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
     *
     * @return PDO|void
     */
    function connect(&$db_conf) {
        if ($this->init_db) {
            return;
        }
        $sqlitedb = "sqlite:{$db_conf['host']}";
        try {
            $link = new PDO($sqlitedb);//连接sqlite
            $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            exit('[pdo_sqlite]cant connect sqlite:' . $e->getMessage() . $sqlitedb);
        }
        $this->link = $link;
        return $link;
    }

    /**
     * exec for sql
     *
     * @param      $sql
     * @param null $link
     *
     * @return mixed
     */
    public function exec($sql, $link = NULL) {
        empty($link) && $link = $this->link;
        $n = $link->exec($sql);
        return $n;
    }

    /**
     * query table
     *
     * @param $sql
     *
     * @return mixed
     * @throws Exception
     */
    function query($sql) {
        if (DEBUG) {
            $sqlstarttime = $sqlendttime = 0;
            $mtime        = explode(' ', microtime());
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
            $mtime       = explode(' ', microtime());
            $sqlendttime = number_format(($mtime[1] + $mtime[0] - $_SERVER['starttime']), 6) * 1000;
            $sqltime     = round(($sqlendttime - $sqlstarttime), 3);
            $explain     = array();
            $info        = array();
            if ($result && $type == 'sele') {
                $explain = $this->fetch_array($link->query('EXPLAIN QUERY PLAN ' . $sql));
            }
            $_SERVER['sqls'][] = array('sql' => $sql, 'type' => 'sqlite', 'time' => $sqltime, 'info' => $info, 'explain' => $explain);
        }

        if ($result === FALSE) {
            $error = $this->error();
            throw new Exception('[pdo_sqlite]Query Error:' . $sql . ' ' . (isset($error[2]) ? "Errstr: $error[2]" : ''));
        }
        $this->querynum++;

        return $result;
    }

    /**
     * fetch first row for array
     *
     * @param     $query
     * @param int $result_type
     *
     * @return mixed
     */
    function fetch_array($query, $result_type = PDO_SQLITE_FETCH_ASSOC/*PDO::FETCH_ASSOC*/) {
        return $query->fetch($result_type);
    }

    /**
     * fetch all records
     *
     * @param string $query
     * @param string $index
     *
     * @return mixed
     */
    function fetch_all($query, $index = '') {
        $list = array();
        while ($val = $query->fetch(PDO_MYSQL_FETCH_ASSOC)) {
            if ($index) {
                $list[$val[$index]] = $val;
            } else {
                $list[] = $val;
            }
        }
        return $list;
    }

    /**
     * get first column
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
        return $this->link->rowCount();
    }


    /**
     * get error
     *
     * @return int
     */
    function error() {
        return (($this->link) ? $this->link->errorInfo() : 0);
    }

    /**
     * get error number
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
     * select table
     *
     * @param        $table
     * @param        $where
     * @param array  $order
     * @param int    $perpage
     * @param int    $page
     * @param array  $fields
     * @param string $index
     *
     * @return mixed
     * @throws Exception
     */
    function select($table, $where, $order = array(), $perpage = -1, $page = 1, $fields = array(), $index = '') {
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
            return $this->fetch_all($query, $index);
        }
    }

    /**
     * insert record
     *
     * @param $table
     * @param $data
     * @param $return_id
     *
     * @return int
     * @throws Exception
     */
    function insert($table, $data, $return_id) {
        $data_sql = $this->build_insert_sql($data);
        if (!$data_sql) {
            return 0;
        }
        $sql = 'INSERT INTO ' . $table . ' ' . $data_sql;
        $res = $this->query($sql);
        return $return_id ? $this->insert_id() : $res;
    }


    /**
     * update record
     *
     * @param $table
     * @param $data
     * @param $where
     *
     * @return int
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
     * delete record
     *
     * @param $table
     * @param $where
     *
     * @return int
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
     * sql quote
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
     * build update set sql
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

    /**
     * build insert sql
     *
     * @param $data
     *
     * @return string
     */
    function build_insert_sql($data) {
        $setkeyvar = $setkeyval = $comma = '';
        foreach ($data as $set_key => $set_value) {
            $setkeyvar .= $comma . '`' . $set_key . '`';
            $setkeyval .= $comma . '\'' . $this->sql_quot($set_value) . '\'';
            $comma = ',';
        }
        return '(' . $setkeyvar . ') VALUES(' . $setkeyval . ')';
    }

}

?>