<?php
/**
 * ----------------------
 * Database.php
 * 
 * User: jian0307@icloud.com
 * Date: 2015/7/13
 * Time: 16:23
 * ----------------------
 */
namespace swallow\library;

/**
 * 数据库类
 */
class Database
{
    protected $table;
    protected $where;
    protected $joins;
    protected $order;
    protected $groups;
    protected $having;
    protected $distinct;
    protected $limit;
    protected $offset;
    protected $sql;
    protected $db;
    protected $db_type;
    protected $cache;
    protected $cache_type;
    protected $stats;
    protected $query_time;
    protected $class;

    //支持的数据库类型
    protected static $db_types = array(
        'pdo', 'mysqli', 'mysql', 'pgsql', 'sqlite', 'sqlite3'
    );

    //支持的缓存类型
    protected static $cache_types = array(
        'memcached', 'memcache', 'xcache'
    );

    public $last_query;
    public $num_rows;
    public $insert_id;
    public $affected_rows;
    public $is_cached = false;
    public $stats_enabled = false;
    public $show_sql = false;
    public $key_prefix = '';

    public function __construct()
    {
    }

    /**
     * 拼凑SQL
     *
     * @param string $sql sql语句
     * @param string $input 追加输入字符串
     * @return string 新的Sql语句
     */
    public function build($sql, $input)
    {
        return (strlen($input) > 0) ? ($sql.' '.$input) : $sql;
    }

    /**
     * 解析连接字符串转换为对象
     *
     * @param string $connection 连接字符串
     * @return array 连接信息
     * @throws Exception 无效的连接字符串
     */
    public function parseConnection($connection)
    {
        $url = parse_url($connection);
        if (empty($url)) {
            throw new Exception('Invalid connection string.');
        }
        $cfg = array();
        $cfg['type'] = isset($url['scheme']) ? $url['scheme'] : $url['path'];
        $cfg['hostname'] = isset($url['host']) ? $url['host'] : null;
        $cfg['database'] = isset($url['path']) ? substr($url['path'],1) : null;
        $cfg['username'] = isset($url['user']) ? $url['user'] : null;
        $cfg['password'] = isset($url['pass']) ? $url['pass'] : null;
        $cfg['port'] = isset($url['port']) ? $url['port'] : null;
        return $cfg;
    }

    /**
     * 获取查询统计
     */
    public function getStats()
    {
        $this->stats['total_time'] = 0;
        $this->stats['num_queries'] = 0;
        $this->stats['num_rows'] = 0;
        $this->stats['num_changes'] = 0;
        if (isset($this->stats['queries'])) {
            foreach ($this->stats['queries'] as $query) {
                $this->stats['total_time'] += $query['time'];
                $this->stats['num_queries'] += 1;
                $this->stats['num_rows'] += $query['rows'];
                $this->stats['num_changes'] += $query['changes'];
            }
        }
        $this->stats['avg_query_time'] =
            $this->stats['total_time'] /
            (float)(($this->stats['num_queries'] > 0) ? $this->stats['num_queries'] : 1);
        return $this->stats;
    }

    /**
     * 检查表属性是否已设置
     */
    public function checkTable()
    {
        if (!$this->table) {
            throw new Exception('Table is not defined.');
        }
    }

    /**
     * 检查类属性是否已设置
     */
    public function checkClass()
    {
        if (!$this->class) {
            throw new Exception('Class is not defined.');
        }
    }

    /**
     * 重置类属性
     */
    public function reset()
    {
        $this->where = '';
        $this->joins = '';
        $this->order = '';
        $this->groups = '';
        $this->having = '';
        $this->distinct = '';
        $this->limit = '';
        $this->offset = '';
        $this->sql = '';
    }

    /**
     * 解析一个条件语句
     *
     * @param string $field 数据库字段
     * @param string $value 条件
     * @param string $join 连接字
     * @param boolean $escape 是否转义
     * @return string 条件
     * @throws Exception
     */
    protected function parseCondition($field, $value = null, $join = '', $escape = true)
    {
        if (is_string($field)) {
            if ($value === null) return $join.' '.trim($field);
            $operator = '';
            if (strpos($field, ' ') !== false) {
                list($field, $operator) = explode(' ', $field);
            }
            if (!empty($operator)) {
                switch ($operator) {
                    case '%':
                        $condition = ' LIKE ';
                        break;
                    case '!%':
                        $condition = ' NOT LIKE ';
                        break;
                    case '@':
                        $condition = ' IN ';
                        break;
                    case '!@':
                        $condition = ' NOT IN ';
                        break;
                    default:
                        $condition = $operator;
                }
            }
            else {
                $condition = '=';
            }
            if (empty($join)) {
                $join = ($field{0} == '|') ? ' OR' : ' AND';
            }
            if (is_array($value)) {
                if (strpos($operator, '@') === false) $condition = ' IN ';
                $value = '('.implode(',', array_map(array($this, 'quote'), $value)).')';
            }
            else {
                $value = ($escape && !is_numeric($value)) ? $this->quote($value) : $value;
            }
            return $join.' '.str_replace('|', '', $field).$condition.$value;
        }
        else if (is_array($field)) {
            $str = '';
            foreach ($field as $key => $value) {
                $str .= $this->parseCondition($key, $value, $join, $escape);
                $join = '';
            }
            return $str;
        }
        else {
            throw new Exception('Invalid where condition.');
        }
    }

    /**
     * 设置表
     * @param string $table 表名
     * @param boolean $reset 是否重置类属性
     * @return object 自引用
     */
    public function from($table, $reset = true)
    {
        $this->table = $table;
        if ($reset) {
            $this->reset();
        }
        return $this;
    }

    /**
     * 增加一个表连接
     *
     * @param string $table 要连接的表
     * @param array $fields 要join on的表字段
     * @param string $type 连接的类型
     * @return object 自引用
     * @throws Exception 无效的连接类型异常
     */
    public function join($table, array $fields, $type = 'INNER')
    {
        static $joins = array(
            'INNER',
            'LEFT OUTER',
            'RIGHT OUTER',
            'FULL OUTER'
        );
        if (!in_array($type, $joins)) {
            throw new Exception('Invalid join type.');
        }
        $this->joins .= ' '.$type.' JOIN '.$table.
            $this->parseCondition($fields, null, ' ON', false);
        return $this;
    }

    /**
     * 添加一个左连接
     *
     * @param string $table 要连接的表
     * @param array $fields 要join on的表字段
     * @return object 自引用
     */
    public function leftJoin($table, array $fields)
    {
        return $this->join($table, $fields, 'LEFT OUTER');
    }

   /**
     * 增加一个右连接
     *
     * @param string $table 要连接的表
     * @param array $fields 要join on的表字段
     * @return object 自引用
     */
    public function rightJoin($table, array $fields)
    {
        return $this->join($table, $fields, 'RIGHT OUTER');
    }

    /**
     * 添加一个全连接
     *
     * @param string $table 要连接的表
     * @param array $fields 要join on的表字段
     * @return object 自引用
     */
    public function fullJoin($table, array $fields)
    {
        return $this->join($table, $fields, 'FULL OUTER');
    }

    /**
     * 添加一个where条件
     *
     * @param string|array $field 字段名或者字段和值的数组
     * @param string $value 字段值
     * @return object 自引用
     */
    public function where($field, $value = null)
    {
        $join = (empty($this->where)) ? 'WHERE' : '';
        $this->where .= $this->parseCondition($field, $value, $join);
        return $this;
    }

    /**
     * 增加了一个升序排序
     *
     * @param string $field 字段名
     * @return object 自引用
     */
    public function sortAsc($field)
    {
        return $this->orderBy($field, 'ASC');
    }

    /**
     * 增加了一个降序排序
     *
     * @param string $field 字段名
     * @return object 自引用
     */
    public function sortDesc($field)
    {
        return $this->orderBy($field, 'DESC');
    }

    /**
     * 添加排序字段
     *
     * @param string $field 字段名
     * @param string $direction 升序或降序
     * @return object 自引用
     */
    public function orderBy($field, $direction = 'ASC')
    {
        $join = (empty($this->order)) ? 'ORDER BY' : ',';
        if (is_array($field)) {
            foreach ($field as $key => $value) {
                $field[$key] = $value.' '.$direction;
            }
        }
        else {
            $field .= ' '.$direction;
        }
        $fields = (is_array($field)) ? implode(', ', $field) : $field;
        $this->order .= $join.' '.$fields;
        return $this;
    }

    /**
     * 添加group by字段
     *
     * @param string|array $field 字段名、字段数组
     * @return object 自引用
     */
    public function groupBy($field)
    {
        $join = (empty($this->order)) ? 'GROUP BY' : ',';
        $fields = (is_array($field)) ? implode(',', $field) : $field;
        $this->groups .= $join.' '.$fields;
        return $this;
    }

    /**
     * 添加having条件
     *
     * @param string|array $field 字段名或者字段和值的数组
     * @param string $value 字段值
     * @return object 自引用
     */
    public function having($field, $value = null)
    {
        $join = (empty($this->having)) ? 'HAVING' : '';
        $this->having .= $this->parseCondition($field, $value, $join);
        return $this;
    }

    /**
     * 添加一个limit限制
     *
     * @param int $limit limit值
     * @param int $offset 偏移量
     * @return object 自引用
     */
    public function limit($limit, $offset = null)
    {
        if ($limit !== null) {
            $this->limit = 'LIMIT '.$limit;
        }
        if ($offset !== null) {
            $this->offset($offset);
        }
        return $this;
    }

    /**
     * 添加偏移
     *
     * @param int $offset
     * @param int $limit
     * @return object 自引用
     */
    public function offset($offset, $limit = null)
    {
        if ($offset !== null) {
            $this->offset = 'OFFSET '.$offset;
        }
        if ($limit !== null) {
            $this->limit($limit);
        }
        return $this;
    }

    /**
     * 设置disteinct
     */
    public function distinct($value = true)
    {
        $this->distinct = ($value) ? 'DISTINCT' : '';
        return $this;
    }

    /**
     * 设置一个between条件
     *
     * @param string $field 字段
     * @param string $value1 第一个值
     * @param string $value2 第二个值
     */
    public function between($field, $value1, $value2)
    {
        $this->where(sprintf(
            '%s BETWEEN %s AND %s',
            $field,
            $this->quote($value1),
            $this->quote($value2)
        ));
    }

    /**
     *生成一个查询操作
     *
     * @param array|string $fields 查询的字段名或者数组
     * @param int $limit limit条件
     * @param int $offset 偏移条件
     * @return object 自引用
     */
    public function select($fields = '*', $limit = null, $offset = null)
    {
        $this->checkTable();
        $fields = (is_array($fields)) ? implode(',', $fields) : $fields;
        $this->limit($limit, $offset);
        $this->sql(array(
            'SELECT',
            $this->distinct,
            $fields,
            'FROM',
            $this->table,
            $this->joins,
            $this->where,
            $this->groups,
            $this->having,
            $this->order,
            $this->limit,
            $this->offset
        ));
        return $this;
    }

    /**
     * 生成一个插入操作
     *
     * @param array $data
     * @return object 自引用
     */
    public function insert(array $data)
    {
        $this->checkTable();
        if (empty($data)) return $this;
        $keys = implode(',', array_keys($data));
        $values = implode(',', array_values(
            array_map(
                array($this, 'quote'),
                $data
            )
        ));
        $this->sql(array(
            'INSERT INTO',
            $this->table,
            '('.$keys.')',
            'VALUES',
            '('.$values.')'
        ));
        return $this;
    }

    /**
     * 更新操作
     *
     * @param string|array
     * @return object 自引用
     */
    public function update($data)
    {
        $this->checkTable();
        if (empty($data)) return $this;
        $values = array();
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $values[] = (is_numeric($key)) ? $value : $key.'='.$this->quote($value);
            }
        }
        else {
            $values[] = (string)$data;
        }
        $this->sql(array(
            'UPDATE',
            $this->table,
            'SET',
            implode(',', $values),
            $this->where
        ));
        return $this;
    }

    /**
     * 删除操作
     *
     * @param array $where 条件
     * @return object 自引用
     */
    public function delete($where = null)
    {
        $this->checkTable();
        if ($where !== null) {
            $this->where($where);
        }
        $this->sql(array(
            'DELETE FROM',
            $this->table,
            $this->where
        ));
        return $this;
    }

    /**
     * 获取或者设置sql语句
     *
     * @param string|array sql语句
     * @return string sql语句
     */
    public function sql($sql = null)
    {
        if ($sql !== null) {
            $this->sql = trim(
                (is_array($sql)) ?
                    array_reduce($sql, array($this, 'build')) :
                    $sql
            );
            return $this;
        }
        return $this->sql;
    }

    /**
     * 设置数据库连接参数
     *
     * @param string|array|object $db 数据库连接参数：字符串、数组或对象
     * @throws Exception 数据库连接异常
     */
    public function setDb($db)
    {
        $this->db = null;
        if (is_string($db)) {
            $this->setDb($this->parseConnection($db));
        }
        else if (is_array($db)) {
            switch ($db['type']) {
                case 'mysqli':
                    $this->db = new mysqli(
                        $db['hostname'],
                        $db['username'],
                        $db['password'],
                        $db['database']
                    );
                    if ($this->db->connect_error) {
                        throw new Exception('Connection error: '.$this->db->connect_error);
                    }
                    break;
                case 'mysql':
                    $this->db = mysql_connect(
                        $db['hostname'],
                        $db['username'],
                        $db['password']
                    );
                    if (!$this->db) {
                        throw new Exception('Connection error: '.mysql_error());
                    }
                    mysql_select_db($db['database'], $this->db);
                    break;
                case 'pgsql':
                    $str = sprintf(
                        'host=%s dbname=%s user=%s password=%s',
                        $db['hostname'],
                        $db['database'],
                        $db['username'],
                        $db['password']
                    );
                    $this->db = pg_connect($str);
                    break;
                case 'sqlite':
                    $this->db = sqlite_open($db['database'], 0666, $error);
                    if (!$this->db) {
                        throw new Exception('Connection error: '.$error);
                    }
                    break;
                case 'sqlite3':
                    $this->db = new SQLite3($db['database']);
                    break;
                case 'pdomysql':
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;dbname=%s',
                        $db['hostname'],
                        isset($db['port']) ? $db['port'] : 3306,
                        $db['database']
                    );
                    $this->db = new PDO($dsn, $db['username'], $db['password']);
                    $db['type'] = 'pdo';
                    break;
                case 'pdopgsql':
                    $dsn = sprintf(
                        'pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s',
                        $db['hostname'],
                        isset($db['port']) ? $db['port'] : 5432,
                        $db['database'],
                        $db['username'],
                        $db['password']
                    );
                    $this->db = new PDO($dsn);
                    $db['type'] = 'pdo';
                    break;
                case 'pdosqlite':
                    $this->db = new PDO('sqlite:/'.$db['database']);
                    $db['type'] = 'pdo';
                    break;
            }
            if ($this->db == null) {
                throw new Exception('Undefined database.');
            }
            $this->db_type = $db['type'];
        }
        else {
            $type = $this->getDbType($db);
            if (!in_array($type, self::$db_types)) {
                throw new Exception('Invalid database type.');
            }
            $this->db = $db;
            $this->db_type = $type;
        }
    }

    /**
     * 获取数据库连接对象
     *
     * @return object 数据库连接对象
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * 获取数据库类型
     *
     * @param object|resource $db 数据库对象或者资源
     * @return string 数据库类型
     */
    public function getDbType($db)
    {
        if (is_object($db)) {
            return strtolower(get_class($db));
        }
        else if (is_resource($db)) {
            switch (get_resource_type($db)) {
                case 'mysql link':
                    return 'mysql';
                case 'sqlite database':
                    return 'sqlite';
                case 'pgsql link':
                    return 'pgsql';
            }
        }
        return null;
    }

    /**
     * 执行一个sql语句
     *
     * @param string $key 缓存键
     * @param int $expire 过期时间（秒）
     * @return object 执行结果
     * @throws Exception
     */
    public function execute($key = null, $expire = 0)
    {
        if (!$this->db) {
            throw new Exception('Database is not defined.');
        }
        if ($key !== null) {
            $result = $this->fetch($key);
            if ($this->is_cached) {
                return $result;
            }
        }
        $result = null;
        $this->is_cached = false;
        $this->num_rows = 0;
        $this->affected_rows = 0;
        $this->insert_id = -1;
        $this->last_query = $this->sql;
        if ($this->stats_enabled) {
            if (empty($this->stats)) {
                $this->stats = array(
                    'queries' => array()
                );
            }
            $this->query_time = microtime(true);
        }
        if (!empty($this->sql)) {
            $error = null;
            switch ($this->db_type) {
                case 'pdo':
                    try {
                        $result = $this->db->prepare($this->sql);
                        if (!$result) {
                            $error = $this->db->errorInfo();
                        }
                        else {
                            $result->execute();
                            $this->num_rows = $result->rowCount();
                            $this->affected_rows = $result->rowCount();
                            $this->insert_id = $this->db->lastInsertId();
                        }
                    }
                    catch (PDOException $ex) {
                        $error = $ex->getMessage();
                    }
                    break;
                case 'mysqli':
                    $result = $this->db->query($this->sql);
                    if (!$result) {
                        $error = $this->db->error;
                    }
                    else {
                        if (is_object($result)) {
                            $this->num_rows = $result->num_rows;
                        }
                        else {
                            $this->affected_rows = $this->db->affected_rows;
                        }
                        $this->insert_id = $this->db->insert_id;
                    }
                    break;
                case 'mysql':
                    $result = mysql_query($this->sql, $this->db);
                    if (!$result) {
                        $error = mysql_error();
                    }
                    else {
                        if (!is_bool($result)) {
                            $this->num_rows = mysql_num_rows($result);
                        }
                        else {
                            $this->affected_rows = mysql_affected_rows($this->db);
                        }
                        $this->insert_id = mysql_insert_id($this->db);
                    }
                    break;
                case 'pgsql':
                    $result = pg_query($this->db, $this->sql);
                    if (!$result) {
                        $error = pg_last_error($this->db);
                    }
                    else {
                        $this->num_rows = pg_num_rows($result);
                        $this->affected_rows = pg_affected_rows($result);
                        $this->insert_id = pg_last_oid($result);
                    }
                    break;
                case 'sqlite':
                    $result = sqlite_query($this->db, $this->sql, SQLITE_ASSOC, $error);
                    if ($result !== false) {
                        $this->num_rows = sqlite_num_rows($result);
                        $this->affected_rows = sqlite_changes($this->db);
                        $this->insert_id = sqlite_last_insert_rowid($this->db);
                    }
                    break;
                case 'sqlite3':
                    $result = $this->db->query($this->sql);
                    if ($result === false) {
                        $error = $this->db->lastErrorMsg();
                    }
                    else {
                        $this->num_rows = 0;
                        $this->affected_rows = ($result) ? $this->db->changes() : 0;
                        $this->insert_id = $this->db->lastInsertRowId();
                    }
                    break;
            }
            if ($error !== null) {
                if ($this->show_sql) {
                    $error .= "\nSQL: ".$this->sql;
                }
                throw new Exception('Database error: '.$error);
            }
        }
        if ($this->stats_enabled) {
            $time = microtime(true) - $this->query_time;
            $this->stats['queries'][] = array(
                'query' => $this->sql,
                'time' => $time,
                'rows' => (int)$this->num_rows,
                'changes' => (int)$this->affected_rows
            );
        }
        return $result;
    }

    /**
     * 取多条查询结果
     *
     * @param string $key 缓存key
     * @param int $expire 过期时间（秒）
     * @return array Rows
     */
    public function many($key = null, $expire = 0)
    {
        if (empty($this->sql)) {
            $this->select();
        }
        $data = array();
        $result = $this->execute($key, $expire);
        if ($this->is_cached) {
            $data = $result;
            if ($this->stats_enabled) {
                $this->stats['cached'][$this->key_prefix.$key] = $this->sql;
            }
        }
        else {
            switch ($this->db_type) {
                case 'pdo':
                    $data = $result->fetchAll(PDO::FETCH_ASSOC);
                    $this->num_rows = sizeof($data);

                    break;
                case 'mysqli':
                    if (function_exists('mysqli_fetch_all')) {
                        $data = $result->fetch_all(MYSQLI_ASSOC);
                    }
                    else {
                        while ($row = $result->fetch_assoc()) {
                            $data[] = $row;
                        }
                    }
                    $result->close();
                    break;

                case 'mysql':
                    while ($row = mysql_fetch_assoc($result)) {
                        $data[] = $row;
                    }
                    mysql_free_result($result);
                    break;
                case 'pgsql':
                    $data = pg_fetch_all($result);
                    pg_free_result($result);
                    break;
                case 'sqlite':
                    $data = sqlite_fetch_all($result, SQLITE_ASSOC);
                    break;
                case 'sqlite3':
                    if ($result) {
                        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                            $data[] = $row;
                        }
                        $result->finalize();
                        $this->num_rows = sizeof($data);
                    }
                    break;
            }
        }
        if (!$this->is_cached && $key !== null) {
            $this->store($key, $data, $expire);
        }
        return $data;
    }

    /**
     * 取一条查询结果
     *
     * @param string $key 缓存键
     * @param int $expire 过期时间（秒）
     * @return array Row
     */
    public function one($key = null, $expire = 0)
    {
        if (empty($this->sql)) {
            $this->limit(1)->select();
        }
        $data = $this->many($key, $expire);
        $row = (!empty($data)) ? $data[0] : array();
        return $row;
    }

    /**
     * 取一个字段的值
     *
     * @param string $name 字段名
     * @param string $key 缓存键
     * @param int $expire 过期时间（秒）
     * @return mixed 值
     */
    public function value($name, $key = null, $expire = 0)
    {
        $row = $this->one($key, $expire);
        $value = (!empty($row)) ? $row[$name] : null;
        return $value;
    }

    /**
     * 求最小值
     *
     * @param string $field 字段名
     * @param int $expire 过期时间（秒）
     * @param string $key 缓存键
     * @return object 自引用
     */
    public function min($field, $key = null, $expire = 0)
    {
        $this->select('MIN('.$field.') min_value');
        return $this->value(
            'min_value',
            $key,
            $expire
        );
    }

    /**
     * 求最大值
     *
     * @param string $field 字段名
     * @param int $expire 过期时间（秒）
     * @param string $key 缓存键
     * @return object 自引用
     */
    public function max($field, $key = null, $expire = 0)
    {
        $this->select('MAX('.$field.') max_value');
        return $this->value(
            'max_value',
            $key,
            $expire
        );
    }

    /**
     * 求和
     *
     * @param string $field 字段名
     * @param int $expire 过期时间（秒）
     * @param string $key 缓存键
     * @return object 自引用
     */
    public function sum($field, $key = null, $expire = 0)
    {
        $this->select('SUM('.$field.') sum_value');
        return $this->value(
            'sum_value',
            $key,
            $expire
        );
    }

    /**
     * 求平均值
     *
     * @param string $field 字段名
     * @param int $expire 过期时间（秒）
     * @param string $key 缓存键
     * @return object 自引用
     */
    public function avg($field, $key = null, $expire = 0)
    {
        $this->select('AVG('.$field.') avg_value');
        return $this->value(
            'avg_value',
            $key,
            $expire
        );
    }

    /**
     * 求记录数量
     *
     * @param string $field 字段
     * @param string $key 缓存键
     * @param int $expire 过期时间（秒）
     * @return object 自引用
     */
    public function count($field = '*', $key = null, $expire = 0)
    {
        $this->select('COUNT('.$field.') num_rows');
        return $this->value(
            'num_rows',
            $key,
            $expire
        );
    }

    /**
     * 转移
     *
     * @param mixed $value 原值
     * @return mixed 转移后的值
     */
    public function quote($value)
    {
        if ($value === null) return 'NULL';
        if (is_string($value)) {
            if ($this->db !== null) {
                switch ($this->db_type) {
                    case 'pdo':
                        return $this->db->quote($value);
                    case 'mysqli':
                        return "'".$this->db->real_escape_string($value)."'";
                    case 'mysql':
                        return "'".mysql_real_escape_string($value, $this->db)."'";
                    case 'pgsql':
                        return "'".pg_escape_string($this->db, $value)."'";
                    case 'sqlite':
                        return "'".sqlite_escape_string($value)."'";
                    case 'sqlite3':
                        return "'".$this->db->escapeString($value)."'";
                }
            }
            $value = str_replace(
                array('\\', "\0", "\n", "\r", "'", '"', "\x1a"),
                array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'),
                $value
            );
            return "'$value'";
        }
        return $value;
    }

    /**
     * 设置缓存连接
     *
     * @param string|object $cache 缓存连接字符串或者对象
     * @throws Exception 无效的缓存类型异常
     */
    public function setCache($cache)
    {
        $this->cache = null;
        if (is_string($cache)) {
            if ($cache{0} == '.' || $cache{0} == '/') {
                $this->cache = $cache;
                $this->cache_type = 'file';
            }
            else {
                $this->setCache($this->parseConnection($cache));
            }
        }
        else if (is_array($cache)) {
            switch ($cache['type']) {
                case 'memcache':
                    $this->cache = new Memcache;
                    $this->cache->connect(
                        $cache['hostname'],
                        $cache['port']
                    );
                    break;
                case 'memcached':
                    $this->cache = new Memcached;
                    $this->cache->addServer(
                        $cache['hostname'],
                        $cache['port']
                    );
                    break;
                default:
                    $this->cache = $cache['type'];
            }
            $this->cache_type = $cache['type'];
        }
        else if (is_object($cache)) {
            $type = strtolower(get_class($cache));
            if (!in_array($type, self::$cache_types)) {
                throw new Exception('Invalid cache type.');
            }
            $this->cache = $cache;
            $this->cache_type = $type;
        }
    }

    /**
     * 获取缓存实例
     *
     * @return object
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * 存储到缓存
     *
     * @param string $key 键
     * @param mixed $value 值
     * @param int $expire 过期时间（秒）
     */
    public function store($key, $value, $expire = 0)
    {
        $key = $this->key_prefix.$key;
        switch ($this->cache_type) {
            case 'memcached':
                $this->cache->set($key, $value, $expire);
                break;
            case 'memcache':
                $this->cache->set($key, $value, 0, $expire);
                break;
            case 'apc':
                apc_store($key, $value, $expire);
                break;
            case 'xcache':
                xcache_set($key, $value, $expire);
                break;
            case 'file':
                $file = $this->cache.'/'.md5($key);
                $data = array(
                    'value' => $value,
                    'expire' => ($expire > 0) ? (time() + $expire) : 0
                );
                file_put_contents($file, serialize($data));
                break;
            default:
                $this->cache[$key] = $value;
        }
    }

    /**
     * 从缓存里面取值
     *
     * @param string $key 键
     * @return mixed 值
     */
    public function fetch($key)
    {
        $key = $this->key_prefix.$key;
        switch ($this->cache_type) {
            case 'memcached':
                $value = $this->cache->get($key);
                $this->is_cached = ($this->cache->getResultCode() == Memcached::RES_SUCCESS);
                return $value;
            case 'memcache':
                $value = $this->cache->get($key);
                $this->is_cached = ($value !== false);
                return $value;
            case 'apc':
                return apc_fetch($key, $this->is_cached);
            case 'xcache':
                $this->is_cached = xcache_isset($key);
                return xcache_get($key);
            case 'file':
                $file = $this->cache.'/'.md5($key);
                if ($this->is_cached = file_exists($file)) {
                    $data = unserialize(file_get_contents($file));
                    if ($data['expire'] == 0 || time() < $data['expire']) {
                        return $data['value'];
                    }
                    else {
                        $this->is_cached = false;
                    }
                }
                break;
            default:
                return $this->cache[$key];
        }
        return null;
    }

    /**
     * 根据键清除一个值
     *
     * @param string $key 键
     * @return object 自引用
     */
    public function clear($key)
    {
        $key = $this->key_prefix.$key;
        switch ($this->cache_type) {
            case 'memcached':
                return $this->cache->delete($key);
            case 'memcache':
                return $this->cache->delete($key);
            case 'apc':
                return apc_delete($key);
            case 'xcache':
                return xcache_unset($key);
            case 'file':
                $file = $this->cache.'/'.md5($key);
                if (file_exists($file)) {
                    return unlink($file);
                }
                return false;
            default:
                if (isset($this->cache[$key])) {
                    unset($this->cache[$key]);
                    return true;
                }
                return false;
        }
    }

    /**
     * 刷新
     */
    public function flush()
    {
        switch ($this->cache_type) {
            case 'memcached':
                $this->cache->flush();
                break;
            case 'memcache':
                $this->cache->flush();
                break;
            case 'apc':
                apc_clear_cache();
                break;
            case 'xcache':
                if (function_exists('xcache_clear_cache') && defined(XC_TYPE_PHP)) {
                    xcache_clear_cache(XC_TYPE_PHP);
                }
                break;
            case 'file':
                if ($handle = opendir($this->cache)) {
                    while (false !== ($file = readdir($handle))) {
                        if ($file != '.' && $file != '..') {
                            unlink($this->cache.'/'.$file);
                        }
                    }
                    closedir($handle);
                }
                break;
            default:
                $this->cache = array();
                break;
        }
    }

    /**
     * 设置类
     *
     * @param string|object $class 类名或者实例
     * @return object 自引用
     */
    public function using($class)
    {
        if (is_string($class)) {
            $this->class = $class;
        }
        else if (is_object($class)) {
            $this->class = get_class($class);
        }
        $this->reset();
        return $this;
    }

    /**
     * 载入一个对象的属性
     *
     * @param object $object 类实例
     * @param array $data 属性数据
     * @return object 填充对象
     */
    public function load($object, array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }
        return $object;
    }

    /**
     * 查找并填充一个对象
     *
     * @param int|string|array 查找值
     * @param string $key 缓存键
     * @return object 填充对象
     */
    public function find($value = null, $key = null)
    {
        $this->checkClass();
        $properties = $this->getProperties();
        $this->from($properties->table, false);
        if ($value !== null) {
            if (is_int($value) && property_exists($properties, 'id_field')) {
                $this->where($properties->id_field, $value);
            }
            else if (is_string($value) && property_exists($properties, 'name_field')) {
                $this->where($properties->name_field, $value);
            }
            else if (is_array($value)) {
                $this->where($value);
            }
        }
        if (empty($this->sql)) {
            $this->select();
        }
        $data = $this->many($key);
        $objects = array();
        foreach ($data as $row) {
            $objects[] = $this->load(new $this->class, $row);
        }
        return (sizeof($objects) == 1) ? $objects[0] : $objects;
    }

    /**
     * 保存对象到数据库
     *
     * @param object $object
     * @param array $fields 要保存的数据库字段
     */
    public function save($object, array $fields = null)
    {
        $this->using($object);
        $properties = $this->getProperties();
        $this->from($properties->table);
        $data = get_object_vars($object);
        $id = $object->{$properties->id_field};
        unset($data[$properties->id_field]);
        if ($id === null) {
            $this->insert($data)
                ->execute();
            $object->{$properties->id_field} = $this->insert_id;
        }
        else {
            if ($fields !== null) {
                $keys = array_flip($fields);
                $data = array_intersect_key($data, $keys);
            }
            $this->where($properties->id_field, $id)
                ->update($data)
                ->execute();
        }
        return $this->class;
    }

    /**
     * 从数据库删除对象
     *
     * @param object $object
     */
    public function remove($object)
    {
        $this->using($object);
        $properties = $this->getProperties();
        $this->from($properties->table);
        $id = $object->{$properties->id_field};
        if ($id !== null) {
            $this->where($properties->id_field, $id)
                ->delete()
                ->execute();
        }
    }

    /**
     * 获取类属性
     *
     * @return object
     */
    public function getProperties()
    {
        static $properties = array();
        if (!$this->class) return array();
        if (!isset($properties[$this->class])) {
            static $defaults = array(
                'table' => null,
                'id_field' => null,
                'name_field' => null
            );
            $reflection = new ReflectionClass($this->class);
            $config = $reflection->getStaticProperties();
            $properties[$this->class] = (object)array_merge($defaults, $config);
        }
        return $properties[$this->class];
    }
}
