<?php

class WSAL_Adapters_MySQL_Meta extends WSAL_Adapters_MySQL_ActiveRecord implements WSAL_Adapters_MetaInterface {

    protected $_table = 'wsal_metadata';
    protected $_idkey = 'id';

    public $id = 0;
    public $occurrence_id = 0;
    public $name = '';
    public static $name_maxlength = 100;
    public $value = array(); // force mixed type

    public function GetModel()
    {
        return new WSAL_Models_Meta();
    }
    
    public function __construct($conn)
    {
        parent::__construct($conn);
    }
    
    protected function GetTableOptions(){
        return parent::GetTableOptions() . ',' . PHP_EOL
                . '    KEY occurrence_name (occurrence_id,name)';
    }

    public function DeleteByOccurenceIds($occurenceIds)
    {
        if (!empty($occurenceIds)) {
            $sql = 'DELETE FROM ' . $this->GetTable() . ' WHERE occurrence_id IN (' . implode(',', $occurenceIds) . ')';
            // execute query
            parent::DeleteQuery($sql);
        }
    }

    public function LoadByNameAndOccurenceId($metaName, $occurenceId)
    {
        return $this->Load('occurrence_id = %d AND name = %s', array($occurenceId, $metaName));
    }

    public function GetMatchingIPs($limit = null)
    {
        $_wpdb = $this->connection;
        $sql = "SELECT DISTINCT value FROM {$this->GetTable()} WHERE name = \"ClientIP\"";
        if (!is_null($limit)) $sql .= ' LIMIT ' . $limit;
        $ips = $_wpdb->get_col($sql);
        foreach ($ips as $key => $ip) {
            $ips[$key] = str_replace('"', '', $ip);
        }
        return array_unique($ips);
    }
}
