<?php

class WSAL_Adapters_MySQL_ActiveRecord implements WSAL_Adapters_ActiveRecordInterface
{
    protected $connection;

    /**
     * Contains the table name
     * @var string
     */
    protected $_table;

    /**
     * Contains primary key column name, override as required.
     * @var string
     */
    protected $_idkey = '';

    public function __construct($conn)
    {
        $this->connection = $conn;
    }

    public function GetModel()
    {
        return new WSAL_Models_ActiveRecord();
    }
    
    /**
     * @return string Returns table name.
     */
    public function GetTable()
    {
        $_wpdb = $this->connection;
        return $_wpdb->base_prefix . $this->_table;
    }
    
    /**
     * Used for WordPress prefix
     * @return string Returns table name of WordPress.
     */
    public function GetWPTable()
    {
        global $wpdb;
        return $wpdb->base_prefix . $this->_table;
    }

    /**
     * @return string SQL table options (constraints, foreign keys, indexes etc).
     */
    protected function GetTableOptions()
    {
        return '    PRIMARY KEY  (' . $this->_idkey . ')';
    }
    
    /**
     * @return array Returns this records' columns.
     */
    public function GetColumns()
    {
        $model = $this->GetModel();
        
        if (!isset($this->_column_cache)) {
            $this->_column_cache = array();
            foreach (array_keys(get_object_vars($model)) as $col)
                if (trim($col) && $col[0] != '_')
                    $this->_column_cache[] = $col;
        }
        return $this->_column_cache;
    }
    
    /**
     * @deprecated
     * @return boolean Returns whether table structure is installed or not.
     */
    public function IsInstalled(){
        //global $wpdb;
        $_wpdb = $this->connection;
        $sql = 'SHOW TABLES LIKE "' . $this->GetTable() . '"';
        return strtolower($_wpdb->get_var($sql)) == strtolower($this->GetTable());
    }
    
    /**
     * Install this ActiveRecord structure into DB.
     */
    public function Install(){
        $_wpdb = $this->connection;
        $_wpdb->query($this->_GetInstallQuery());
    }
    
     /**
     * Install this ActiveRecord structure into DB WordPress.
     */
    public function InstallOriginal(){
        global $wpdb;
        $wpdb->query($this->_GetInstallQuery(true));
    }

    /**
     * Remove this ActiveRecord structure into DB.
     */
    public function Uninstall()
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $_wpdb->query($this->_GetUninstallQuery());
    }
    
    /**
     * Save an active record to DB.
     * @return integer|boolean Either the number of modified/inserted rows or false on failure.
     */
    public function Save($activeRecord)
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $copy = $activeRecord;
        $data = array();
        $format = array();

        foreach ($this->GetColumns() as $index => $key) {
            if ($key == $this->_idkey) {
                $_idIndex = $index;
            }

            $val = $copy->$key;
            $deffmt = '%s';
            if (is_int($copy->$key)) {
                $deffmt = '%d';
            }
            if (is_float($copy->$key)) {
                $deffmt = '%f';
            }
            if (is_array($copy->$key) || is_object($copy->$key)) {
                $data[$key] = WSAL_Helpers_DataHelper::JsonEncode($val);
            } else {
                $data[$key] = $val;
            }
            $format[] = $deffmt;
        }
        
        if (isset($data[$this->_idkey]) && empty($data[$this->_idkey])) {
            unset($data[$this->_idkey]);
            unset($format[$_idIndex]);
        }

        $result = $_wpdb->replace($this->GetTable(), $data, $format);
            
        if ($result !== false) {
            if ($_wpdb->insert_id) {
                $copy->setId($_wpdb->insert_id);
            }
        }
        return $result;
    }
    
    /**
     * Load record from DB.
     * @param string $cond (Optional) Load condition.
     * @param array $args (Optional) Load condition arguments.
     */
    public function Load($cond = '%d', $args = array(1))
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        
        $sql = $_wpdb->prepare('SELECT * FROM '.$this->GetTable().' WHERE '. $cond, $args);
        $data = $_wpdb->get_row($sql, ARRAY_A);

        return $data;
    }

    public function LoadArray($cond, $args = array())
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $result = array();
        $sql = $_wpdb->prepare('SELECT * FROM '.$this->GetTable().' WHERE '. $cond, $args);
        foreach ($_wpdb->get_results($sql, ARRAY_A) as $data) {
            $result[] = $this->getModel()->LoadData($data);
        }
        return $result;
    }
    
    /**
     * Delete DB record.
     * @return int|boolean Either the amount of deleted rows or False on error.
     */
    public function Delete($activeRecord)
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $result = $_wpdb->delete(
            $this->GetTable(),
            $activeRecord->getId()
        );
        return $result;
    }
    
    /**
     * Delete records in DB matching a query.
     * @param string $query Full SQL query.
     * @param array $args (Optional) Query arguments.
     */
    public function DeleteQuery($query, $args = array())
    {
        $_wpdb = $this->connection;
        $sql = count($args) ? $_wpdb->prepare($query, $args) : $query;
        $result = $_wpdb->query($sql);
        return $result;
    }
    
    /**
     * Load multiple records from DB.
     * @param string $cond (Optional) Load condition (eg: 'some_id = %d' ).
     * @param array $args (Optional) Load condition arguments (rg: array(45) ).
     * @return self[] List of loaded records.
     */
    public function LoadMulti($cond, $args = array())
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $result = array();
        $sql = (!is_array($args) || !count($args)) // do we really need to prepare() or not?
            ? ($cond)
            : $_wpdb->prepare($cond, $args)
        ;
        foreach ($_wpdb->get_results($sql, ARRAY_A) as $data) {
            $result[] = $this->getModel()->LoadData($data);
        }
        return $result;
    }
    
    /**
     * Load multiple records from DB and call a callback for each record.
     * This function is very memory-efficient, it doesn't load records in bulk.
     * @param callable $callback The callback to invoke.
     * @param string $cond (Optional) Load condition.
     * @param array $args (Optional) Load condition arguments.
     */
    public function LoadAndCallForEach($callback, $cond = '%d', $args = array(1))
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $class = get_called_class();
        $sql = $_wpdb->prepare('SELECT * FROM ' . $this->GetTable() . ' WHERE '.$cond, $args);
        foreach ($_wpdb->get_results($sql, ARRAY_A) as $data) {
            call_user_func($callback, new $class($data));
        }
    }
    
    /**
     * Count records in the DB matching a condition.
     * If no parameters are given, this counts the number of records in the DB table.
     * @param string $cond (Optional) Query condition.
     * @param array $args (Optional) Condition arguments.
     * @return int Number of matching records.
     */
    public function Count($cond = '%d', $args = array(1))
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $class = get_called_class();
        $sql = $_wpdb->prepare('SELECT COUNT(*) FROM ' . $this->GetTable() . ' WHERE ' . $cond, $args);
        return (int)$_wpdb->get_var($sql);
    }
    
    /**
     * Count records in the DB matching a query.
     * @param string $query Full SQL query.
     * @param array $args (Optional) Query arguments.
     * @return int Number of matching records.
     */
    public function CountQuery($query, $args = array())
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $sql = count($args) ? $_wpdb->prepare($query, $args) : $query;
        return (int)$_wpdb->get_var($sql);
    }
    
    /**
     * Similar to LoadMulti but allows the use of a full SQL query.
     * @param string $query Full SQL query.
     * @param array $args (Optional) Query arguments.
     * @return self[] List of loaded records.
     */
    public function LoadMultiQuery($query, $args = array())
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $class = get_called_class();
        $result = array();
        $sql = count($args) ? $_wpdb->prepare($query, $args) :  $query;
        foreach ($_wpdb->get_results($sql, ARRAY_A) as $data) {
            $result[] = $this->getModel()->LoadData($data);
        }
        return $result;
    }

    /**
     * @return string Must return SQL for creating table.
     */
    protected function _GetInstallQuery($prefix = false)
    {
        $_wpdb = $this->connection;
        
        $class = get_class($this);
        $copy = new $class($this->connection);
        $table_name = ($prefix) ? $this->GetWPTable() : $this->GetTable();
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table_name . ' (' . PHP_EOL;
        
        foreach ($this->GetColumns() as $key) {
            $sql .= '    ';
            switch (true) {
                case $key == $copy->_idkey:
                    $sql .= $key . ' BIGINT NOT NULL AUTO_INCREMENT,' . PHP_EOL;
                    break;
                case is_integer($copy->$key):
                    $sql .= $key . ' BIGINT NOT NULL,' . PHP_EOL;
                    break;
                case is_float($copy->$key):
                    $sql .= $key . ' DOUBLE NOT NULL,' . PHP_EOL;
                    break;
                case is_string($copy->$key):
                    $maxlength = $key . '_maxlength';
                    if (property_exists($class, $maxlength)) {
                        $sql .= $key . ' VARCHAR(' . intval($class::$$maxlength) . ') NOT NULL,' . PHP_EOL;
                    } else {
                        $sql .= $key . ' TEXT NOT NULL,' . PHP_EOL;
                    }
                    break;
                case is_bool($copy->$key):
                    $sql .= $key . ' BIT NOT NULL,' . PHP_EOL;
                    break;
                case is_array($copy->$key):
                case is_object($copy->$key):
                    $sql .= $key . ' LONGTEXT NOT NULL,' . PHP_EOL;
                    break;
            }
        }
        
        $sql .= $this->GetTableOptions() . PHP_EOL;
        
        $sql .= ')';
        
        if (!empty($_wpdb->charset)) {
            $sql .= ' DEFAULT CHARACTER SET ' . $_wpdb->charset;
        }
        
        return $sql;
        
    }

    /**
     * @return string Must return SQL for removing table (at a minimum, it should be ` 'DROP TABLE ' . $this->_table `).
     */
    protected function _GetUninstallQuery()
    {
        return  'DROP TABLE ' . $this->GetTable();
    }

    private function GetUserNames($_userId)
    {
        global $wpdb;
        
        $user_names = '0';
        if (!empty($_userId) && $_userId != "null") {
            $sql = 'SELECT user_login FROM '. $wpdb->users .' WHERE find_in_set(ID, @userId) > 0';
            $wpdb->query("SET @userId = $_userId");
            $result = $wpdb->get_results($sql, ARRAY_A);
            $aUsers = array();
            foreach ($result as $item) {
                $aUsers[] = '"'.$item['user_login'].'"';
            }
            $user_names = implode(', ', $aUsers);
        }
        return $user_names;
    }

    /**
     * Function used in WSAL reporting extension
     */
    public function GetReporting($_siteId, $_userId, $_roleName, $_alertCode, $_startTimestamp, $_endTimestamp, $_nextDate = null, $_limit = 0)
    {
        global $wpdb;
        $user_names = $this->GetUserNames($_userId);

        $_wpdb = $this->connection;
        $_wpdb->set_charset($_wpdb->dbh, 'utf8mb4', 'utf8mb4_general_ci');
        // tables
        $meta = new WSAL_Adapters_MySQL_Meta($this->connection);
        $tableMeta = $meta->GetTable(); // metadata
        $occurrence = new WSAL_Adapters_MySQL_Occurrence($this->connection);
        $tableOcc = $occurrence->GetTable(); // occurrences
        
        $conditionDate = !empty($_nextDate) ? ' AND occ.created_on < '.$_nextDate : '';

        $sql = "SELECT DISTINCT
            occ.id, 
            occ.alert_id, 
            occ.site_id, 
            occ.created_on,
            replace(replace(replace((
                SELECT t1.value FROM $tableMeta AS t1 WHERE t1.name = 'CurrentUserRoles' AND t1.occurrence_id = occ.id LIMIT 1), '[', ''), ']', ''), '\\'', '') AS roles,
            (SELECT replace(t2.value, '\"','') FROM $tableMeta as t2 WHERE t2.name = 'ClientIP' AND t2.occurrence_id = occ.id LIMIT 1) AS ip,
            (SELECT replace(t3.value, '\"', '') FROM $tableMeta as t3 WHERE t3.name = 'UserAgent' AND t3.occurrence_id = occ.id LIMIT 1) AS ua,
            COALESCE(
                (SELECT replace(t4.value, '\"', '') FROM $tableMeta as t4 WHERE t4.name = 'Username' AND t4.occurrence_id = occ.id LIMIT 1),
                (SELECT replace(t5.value, '\"', '') FROM $tableMeta as t5 WHERE t5.name = 'CurrentUserID' AND t5.occurrence_id = occ.id LIMIT 1)
            ) as user_id
            FROM $tableOcc AS occ
            JOIN $tableMeta AS meta ON meta.occurrence_id = occ.id
            WHERE
                (@siteId is NULL OR find_in_set(occ.site_id, @siteId) > 0)
                AND (@userId is NULL OR (
                    (meta.name = 'CurrentUserID' AND find_in_set(meta.value, @userId) > 0)
                OR (meta.name = 'Username' AND replace(meta.value, '\"', '') IN ($user_names))  
                ))
                AND (@roleName is NULL OR (meta.name = 'CurrentUserRoles'
                AND replace(replace(replace(meta.value, ']', ''), '[', ''), '\\'', '') REGEXP @roleName
                ))
                AND (@alertCode is NULL OR find_in_set(occ.alert_id, @alertCode) > 0)
                AND (@startTimestamp is NULL OR occ.created_on >= @startTimestamp)
                AND (@endTimestamp is NULL OR occ.created_on <= @endTimestamp)
                {$conditionDate}
            ORDER BY
                created_on DESC
        ";

        $_wpdb->query("SET @siteId = $_siteId");
        $_wpdb->query("SET @userId = $_userId");
        $_wpdb->query("SET @roleName = $_roleName");
        $_wpdb->query("SET @alertCode = $_alertCode");
        $_wpdb->query("SET @startTimestamp = $_startTimestamp");
        $_wpdb->query("SET @endTimestamp = $_endTimestamp");

        if (!empty($_limit)) {
            $sql .= " LIMIT {$_limit}";
        }
        $results = $_wpdb->get_results($sql);
        if (!empty($results)) {
            foreach ($results as $row) {
                $sql = "SELECT t6.ID FROM $wpdb->users AS t6 WHERE t6.user_login = \"$row->user_id\"";
                $userId = $wpdb->get_var($sql);
                if ($userId == null) {
                    $sql = "SELECT t4.ID FROM $wpdb->users AS t4 WHERE t4.ID = \"$row->user_id\"";
                    $userId = $wpdb->get_var($sql);
                }
                $row->user_id = $userId;
                $results['lastDate'] = $row->created_on;
            }
        }
        
        return $results;
    }

    /**
     * Function used in WSAL reporting extension
     * Check if criteria are matching in the DB
     */
    public function CheckMatchReportCriteria($criteria)
    {
        $_siteId = $criteria['siteId'];
        $_userId = $criteria['userId'];
        $_roleName = $criteria['roleName'];
        $_alertCode = $criteria['alertCode'];
        $_startTimestamp = $criteria['startTimestamp'];
        $_endTimestamp = $criteria['endTimestamp'];
        $_ipAddress = $criteria['ipAddress'];

        $_wpdb = $this->connection;
        $_wpdb->set_charset($_wpdb->dbh, 'utf8mb4', 'utf8mb4_general_ci');
        // tables
        $meta = new WSAL_Adapters_MySQL_Meta($this->connection);
        $tableMeta = $meta->GetTable(); // metadata
        $occurrence = new WSAL_Adapters_MySQL_Occurrence($this->connection);
        $tableOcc = $occurrence->GetTable(); // occurrences

        $user_names = $this->GetUserNames($_userId);

        $sql = "SELECT COUNT(DISTINCT occ.id) FROM $tableOcc AS occ 
            JOIN $tableMeta AS meta ON meta.occurrence_id = occ.id
            WHERE
                (@siteId is NULL OR find_in_set(occ.site_id, @siteId) > 0)
                AND (@userId is NULL OR (
                    (meta.name = 'CurrentUserID' AND find_in_set(meta.value, @userId) > 0)
                OR (meta.name = 'Username' AND replace(meta.value, '\"', '') IN ($user_names))  
                ))
                AND (@roleName is NULL OR (meta.name = 'CurrentUserRoles'
                AND replace(replace(replace(meta.value, ']', ''), '[', ''), '\\'', '') REGEXP @roleName
                ))
                AND (@alertCode is NULL OR find_in_set(occ.alert_id, @alertCode) > 0)
                AND (@startTimestamp is NULL OR occ.created_on >= @startTimestamp)
                AND (@endTimestamp is NULL OR occ.created_on <= @endTimestamp)
                AND (@ipAddress is NULL OR (meta.name = 'ClientIP' AND find_in_set(meta.value, @ipAddress) > 0))
            ";

        $_wpdb->query("SET @siteId = $_siteId");
        $_wpdb->query("SET @userId = $_userId");
        $_wpdb->query("SET @roleName = $_roleName");
        $_wpdb->query("SET @alertCode = $_alertCode");
        $_wpdb->query("SET @startTimestamp = $_startTimestamp");
        $_wpdb->query("SET @endTimestamp = $_endTimestamp");
        $_wpdb->query("SET @ipAddress = $_ipAddress");

        $count = (int)$_wpdb->get_var($sql);
        return $count;
    }

    /**
     * List of unique IP addresses used by the same user
     */
    public function GetReportGrouped($_siteId, $_startTimestamp, $_endTimestamp, $_userId = 'null', $_roleName = 'null', $_ipAddress = 'null', $_alertCode = 'null', $_limit = 0)
    {
        global $wpdb;
        $user_names = $this->GetUserNames($_userId);

        $_wpdb = $this->connection;
        $_wpdb->set_charset($_wpdb->dbh, 'utf8mb4', 'utf8mb4_general_ci');
        // tables
        $meta = new WSAL_Adapters_MySQL_Meta($this->connection);
        $tableMeta = $meta->GetTable(); // metadata
        $occurrence = new WSAL_Adapters_MySQL_Occurrence($this->connection);
        $tableOcc = $occurrence->GetTable(); // occurrences
        // Get temp table `wsal_tmp_users`
        $tmp_users = new WSAL_Adapters_MySQL_TmpUser($this->connection);
        // if the table exist
        if ($tmp_users->IsInstalled()) {
            $tableUsers = $tmp_users->GetTable(); // tmp_users
            $this->TempUsers($tableUsers);
        } else {
            $tableUsers = $wpdb->users;
        }
        
        $sql = "SELECT DISTINCT * 
            FROM (SELECT DISTINCT
                    occ.site_id,
                    CONVERT((SELECT replace(t1.value, '\"', '') FROM $tableMeta as t1 WHERE t1.name = 'Username' AND t1.occurrence_id = occ.id LIMIT 1) using UTF8) AS user_login ,
                    CONVERT((SELECT replace(t3.value, '\"','') FROM $tableMeta as t3 WHERE t3.name = 'ClientIP' AND t3.occurrence_id = occ.id LIMIT 1) using UTF8) AS ip
                FROM $tableOcc AS occ
                JOIN $tableMeta AS meta ON meta.occurrence_id = occ.id
                WHERE
                    (@siteId is NULL OR find_in_set(occ.site_id, @siteId) > 0)
                    AND (@userId is NULL OR (
                        (meta.name = 'CurrentUserID' AND find_in_set(meta.value, @userId) > 0)
                        OR (meta.name = 'Username' AND replace(meta.value, '\"', '') IN ($user_names))  
                    ))
                    AND (@roleName is NULL OR (meta.name = 'CurrentUserRoles'
                    AND replace(replace(replace(meta.value, ']', ''), '[', ''), '\\'', '') REGEXP @roleName
                    ))
                    AND (@alertCode is NULL OR find_in_set(occ.alert_id, @alertCode) > 0)
                    AND (@startTimestamp is NULL OR occ.created_on >= @startTimestamp)
                    AND (@endTimestamp is NULL OR occ.created_on <= @endTimestamp)
                    AND (@ipAddress is NULL OR (meta.name = 'ClientIP' AND find_in_set(meta.value, @ipAddress) > 0))
                HAVING user_login IS NOT NULL
                UNION ALL
                SELECT DISTINCT
                occ.site_id,
                CONVERT((SELECT u.user_login
                    FROM $tableMeta as t2
                    JOIN $tableUsers AS u ON u.ID = replace(t2.value, '\"', '')
                    WHERE t2.name = 'CurrentUserID' 
                    AND t2.occurrence_id = occ.id
                    GROUP BY u.ID
                    LIMIT 1) using UTF8) AS user_login,
                CONVERT((SELECT replace(t4.value, '\"','') FROM $tableMeta as t4 WHERE t4.name = 'ClientIP' AND t4.occurrence_id = occ.id LIMIT 1) using UTF8) AS ip
                FROM $tableOcc AS occ
                JOIN $tableMeta AS meta ON meta.occurrence_id = occ.id
                WHERE
                    (@siteId is NULL OR find_in_set(occ.site_id, @siteId) > 0)
                    AND (@userId is NULL OR (
                        (meta.name = 'CurrentUserID' AND find_in_set(meta.value, @userId) > 0)
                        OR (meta.name = 'Username' AND replace(meta.value, '\"', '') IN ($user_names))  
                    ))
                    AND (@roleName is NULL OR (meta.name = 'CurrentUserRoles'
                    AND replace(replace(replace(meta.value, ']', ''), '[', ''), '\\'', '') REGEXP @roleName
                    ))
                    AND (@alertCode is NULL OR find_in_set(occ.alert_id, @alertCode) > 0)
                    AND (@startTimestamp is NULL OR occ.created_on >= @startTimestamp)
                    AND (@endTimestamp is NULL OR occ.created_on <= @endTimestamp)
                    AND (@ipAddress is NULL OR (meta.name = 'ClientIP' AND find_in_set(meta.value, @ipAddress) > 0))
                HAVING user_login IS NOT NULL) ip_logins
            WHERE user_login NOT IN ('Website Visitor', 'Plugins', 'Plugin')
                ORDER BY user_login ASC
        ";
        $_wpdb->query("SET @siteId = $_siteId");
        $_wpdb->query("SET @userId = $_userId");
        $_wpdb->query("SET @roleName = $_roleName");
        $_wpdb->query("SET @alertCode = $_alertCode");
        $_wpdb->query("SET @startTimestamp = $_startTimestamp");
        $_wpdb->query("SET @endTimestamp = $_endTimestamp");
        $_wpdb->query("SET @ipAddress = $_ipAddress");
        if (!empty($_limit)) {
            $sql .= " LIMIT {$_limit}";
        }

        $grouped_types = array();
        $results = $_wpdb->get_results($sql);
        if (!empty($results)) {
            foreach ($results as $key => $row) {
                // get the display_name only for the first row & if the user_login changed from the previous row
                if ($key == 0 || ($key > 1 && $results[($key - 1)]->user_login != $row->user_login)) {
                    $sql = "SELECT t5.display_name FROM $wpdb->users AS t5 WHERE t5.user_login = \"$row->user_login\"";
                    $displayName = $wpdb->get_var($sql);
                }
                $row->display_name = $displayName;
                
                if (!isset($grouped_types[$row->user_login])) {
                    $grouped_types[$row->user_login] = array(
                        'site_id' => $row->site_id,
                        'user_login' => $row->user_login,
                        'display_name' => $row->display_name,
                        'ips' => array()
                    );
                }

                $grouped_types[$row->user_login]['ips'][] = $row->ip;
            }
        }

        return $grouped_types;
    }

    /**
     * TRUNCATE temp table `tmp_users` and populate with users
     * It is used in the query of the above function
     */
    private function TempUsers($tableUsers)
    {
        $_wpdb = $this->connection;
        $sql = "DELETE FROM $tableUsers";
        $_wpdb->query($sql);

        $sql = "INSERT INTO $tableUsers (ID, user_login) VALUES " ;
        $users = get_users(array('fields' => array('ID', 'user_login')));
        foreach ($users as $user) {
            $sql .= '('. $user->ID .', \''. $user->user_login .'\'), ';
        }
        $sql = rtrim($sql, ", ");
        $_wpdb->query($sql);
    }
}
