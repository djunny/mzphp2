<?php

class DB {
    /**
     * type of database
     *
     * @var
     */
    private static $db_type;
    /**
     * conf of database
     *
     * @var
     */
    private static $db_conf;
    /**
     * table prefix
     *
     * @var
     */
    private static $db_table_pre;
    /**
     * database instance
     *
     * @var null
     */
    private static $instance = NULL;

    /**
     * init database by config
     *
     * @param $conf
     */
    public static function init_db_config(&$conf) {
        self::$db_conf = $conf;
    }

    /**
     * get instance
     *
     * @return null
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            //find db engine
            foreach (self::$db_conf as $type => $conf) {
                $db_enigne = $type . '_db';
                self::$db_type = $type;
                self::$db_table_pre = isset($conf['tablepre']) ? $conf['tablepre'] : '';
                self::$instance = new $db_enigne($conf);
                break;
            }
        }
        return self::$instance;
    }

    /**
     * add prefix for table
     *
     * @param $table
     * @return string
     */
    public static function table($table) {
        self::instance();
        return (self::$db_table_pre) . $table;
    }

    /**
     * query and fetch first row from db
     *
     * @param     $sql
     * @param int $fetch
     * @return mixed
     */
    public static function query($sql, $fetch = 0) {
        $query = call_user_func(array(self::instance(), 'query'), $sql);
        if ($fetch) {
            return self::fetch($query);
        } else {
            return $query;
        }
    }

    /**
     * fetch row
     *
     * @param $query
     * @return mixed
     */
    public static function fetch($query) {
        return call_user_func(array(self::instance(), 'fetch_array'), $query);
    }

    /**
     * fetch all rows
     *
     * @param $query
     * @return mixed
     */
    public static function fetch_all($query) {
        if (is_string($query)) {
            $query = self::query($query);
        }
        return call_user_func(array(self::instance(), 'fetch_all'), $query);
    }

    /**
     * alias for fetch
     *
     * @param $query
     * @return mixed
     */
    public static function fetch_array($query) {
        return self::fetch($query);
    }

    /**
     * select table
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
     *                     fetch all: perpage=1
     *                     count of all: perpage = -2
     * @param int $page    if perpage large than 0 for select page
     *                     (page - 1) * perpage
     * @return mixed
     */
    public static function select($table, $where, $order, $perpage = -1, $page = 1) {
        if (strpos($table, ':') === false) {
            $fields = '*';
        } else {
            list($table, $fields) = explode(':', $table);
        }
        if ($perpage == -2) {
            $fields = 'count(*) AS C';
        }
        $result = call_user_func(array(self::instance(), 'select'), self::table($table), $where, $order, $perpage, $page, $fields);
        if ($perpage == -2) {
            return $result[0]['C'];
        } else {
            return $result;
        }
    }

    /**
     * insert data
     *
     * @param $table
     * @param $data
     * @param $return_id
     * @return mixed
     */
    public static function insert($table, $data, $return_id) {
        return call_user_func(array(self::instance(), 'insert'), self::table($table), $data, $return_id);
    }

    /**
     * replace data
     *
     * @param $table
     * @param $data
     * @return mixed
     */
    public static function replace($table, $data) {
        return call_user_func(array(self::instance(), 'replace'), self::table($table), $data);
    }

    /**
     * update data
     *
     * @param $table
     * @param $data
     * @param $where
     * @return mixed
     */
    public static function update($table, $data, $where) {
        return call_user_func(array(self::instance(), 'update'), self::table($table), $data, $where);
    }

    /**
     * delete
     *
     * @param $table
     * @param $where
     * @return mixed
     */
    public static function delete($table, $where) {
        return call_user_func(array(self::instance(), 'delete'), self::table($table), $where);
    }

}

?>