<?php

class WSAL_Adapters_MySQL_TmpUser extends WSAL_Adapters_MySQL_ActiveRecord {

    protected $_table = 'wsal_tmp_users';

    public function GetModel()
    {
        return new WSAL_Models_Meta();
    }
    
    public function __construct($conn)
    {
        parent::__construct($conn);
    }
    
    /**
     * @return string Must return SQL for creating table.
     */
    protected function _GetInstallQuery($prefix = false)
    {
        $_wpdb = $this->connection;
        $table_name = ($prefix) ? $this->GetWPTable() : $this->GetTable();
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table_name . ' (' . PHP_EOL;
        $sql .= 'ID BIGINT NOT NULL,' . PHP_EOL;
        $sql .= 'user_login VARCHAR(60) NOT NULL,' . PHP_EOL;
        $sql .= 'INDEX (ID)' . PHP_EOL;
        $sql .= ')';
        if (!empty($_wpdb->charset)) {
            $sql .= ' DEFAULT CHARACTER SET ' . $_wpdb->charset;
        }
        return $sql;
    }
}
