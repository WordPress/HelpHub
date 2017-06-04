<?php

class WSAL_Adapters_MySQL_Occurrence extends WSAL_Adapters_MySQL_ActiveRecord implements WSAL_Adapters_OccurrenceInterface {

    protected $_table = 'wsal_occurrences';
    protected $_idkey = 'id';
    protected $_meta;

    public $id = 0;
    public $site_id = 0;
    public $alert_id = 0;
    public $created_on = 0.0;
    public $is_read = false;
    public $is_migrated = false;

    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    protected function GetTableOptions(){
        return parent::GetTableOptions() . ',' . PHP_EOL
                . '    KEY site_alert_created (site_id,alert_id,created_on)';
    }
    
    public function GetModel()
    {
        return new WSAL_Models_Occurrence();
    }
    /**
     * Returns all meta data related to this event.
     * @return WSAL_Meta[]
     */
    public function GetMeta($occurence){
        if(!isset($this->_meta)){
            $meta = new WSAL_Adapters_MySQL_Meta($this->connection);
            $this->_meta = $meta->Load('occurrence_id = %d', array($occurence->id));
        }
        return $this->_meta;
    }

    public function GetMultiMeta($occurence){
        if(!isset($this->_meta)){
            $meta = new WSAL_Adapters_MySQL_Meta($this->connection);
            $this->_meta = $meta->LoadArray('occurrence_id = %d', array($occurence->id));
        }
        return $this->_meta;
    }

    /**
     * Loads a meta item given its name.
     * @param string $name Meta name.
     * @return WSAL_Meta The meta item, be sure to checked if it was loaded successfully.
     */
    public function GetNamedMeta($occurence, $name){
        $meta = new WSAL_Adapters_MySQL_Meta($this->connection);
        $this->_meta = $meta->Load('occurrence_id = %d AND name = %s', array($occurence->id, $name));

        return $this->_meta;
    }
    
    /**
     * Returns the first meta value from a given set of names. Useful when you have a mix of items that could provide a particular detail.
     * @param array $names List of meta names.
     * @return WSAL_Meta The first meta item that exists.
     */
    public function GetFirstNamedMeta($occurence, $names){
        $meta = new WSAL_Adapters_MySQL_Meta($this->connection);
        $query = '(' . str_repeat('name = %s OR ', count($names)).'0)';
        $query = 'occurrence_id = %d AND ' . $query . ' ORDER BY name DESC LIMIT 1';
        array_unshift($names, $occurence->id); // prepend args with occurrence id
        
        $this->_meta = $meta->Load($query, $names);
        return $meta->getModel()->LoadData($this->_meta);

        //TO DO: Do we want to reintroduce is loaded check/logic?
        //return $meta->IsLoaded() ? $meta : null;
    }
    
    /**
     * Returns newest unique occurrences.
     * @param integer $limit Maximum limit.
     * @return WSAL_Occurrence[]
     */
    public static function GetNewestUnique($limit = PHP_INT_MAX){
        $temp = new self();
        return self::LoadMultiQuery('
            SELECT *, COUNT(alert_id) as count
            FROM (
                SELECT *
                FROM ' . $temp->GetTable() . '
                ORDER BY created_on DESC
            ) AS temp_table
            GROUP BY alert_id
            LIMIT %d
        ', array($limit));
    }

    /**
     * Gets occurences of the same type by IP and Username within specified time frame
     * @param string $ipAddress
     * @param string $username
     * @param int $alertId Alert type we are lookign for
     * @param int $siteId
     * @param $startTime mktime
     * @param $endTime mktime
     */
    public function CheckKnownUsers($args = array())
    {
        $tt2 = new WSAL_Adapters_MySQL_Meta($this->connection);
        return self::LoadMultiQuery(
            'SELECT occurrence.* FROM `' . $this->GetTable() . '` occurrence 
            INNER JOIN `' . $tt2->GetTable() . '` ipMeta on ipMeta.occurrence_id = occurrence.id
            and ipMeta.name = "ClientIP"
            and ipMeta.value = %s
            INNER JOIN `' . $tt2->GetTable() . '` usernameMeta on usernameMeta.occurrence_id = occurrence.id
            and usernameMeta.name = "Username"
            and usernameMeta.value = %s
            WHERE occurrence.alert_id = %d AND occurrence.site_id = %d
            AND (created_on BETWEEN %d AND %d)
            GROUP BY occurrence.id',
            $args
        );
    }

    public function CheckUnKnownUsers($args = array()) 
    {
        $tt2 = new WSAL_Adapters_MySQL_Meta($this->connection);
        return self::LoadMultiQuery(
            'SELECT occurrence.* FROM `' . $this->GetTable() . '` occurrence 
            INNER JOIN `' . $tt2->GetTable() . '` ipMeta on ipMeta.occurrence_id = occurrence.id 
            and ipMeta.name = "ClientIP" and ipMeta.value = %s 
            WHERE occurrence.alert_id = %d AND occurrence.site_id = %d
            AND (created_on BETWEEN %d AND %d)
            GROUP BY occurrence.id',
            $args
        );
    }
    
    protected function prepareOccurrenceQuery($query)
    {
        $searchQueryParameters = array();
        $searchConditions = array();
        $conditions = $query->getConditions();

        //BUG: not all conditions are occurence related. maybe it's just a field site_id. need seperate arrays
        if (!empty($conditions)) {
            $tmp = new WSAL_Adapters_MySQL_Meta($this->connection);
            $sWhereClause = "";
            foreach ($conditions as $field => $value) {
                if (!empty($sWhereClause)) {
                    $sWhereClause .= " AND ";
                }
                $sWhereClause .= "name = %s AND value = %s";
                $searchQueryParameters[] = $field;
                $searchQueryParameters[] = $value;
            }

            $searchConditions[] = 'id IN (
                SELECT DISTINCT occurrence_id
                FROM ' . $tmp->GetTable() . '
                WHERE ' . $sWhereClause . '
            )';
        }

        //do something with search query parameters and search conditions - give them to the query adapter?
        return $searchConditions;
    }
    
    /**
     * Gets occurrence by Post_id
     * @param int $post_id
     */
    public function GetByPostID($post_id)
    {
        $tt2 = new WSAL_Adapters_MySQL_Meta($this->connection);
        return self::LoadMultiQuery(
            'SELECT occurrence.* FROM `' . $this->GetTable() . '`AS occurrence 
            INNER JOIN `' . $tt2->GetTable() . '`AS postMeta ON postMeta.occurrence_id = occurrence.id
            and postMeta.name = "PostID"
            and postMeta.value = %d
            GROUP BY occurrence.id
            ORDER BY created_on DESC',
            array($post_id)
        );
    }

    /**
     * Gets occurences of the same type by IP within specified time frame
     * @param string $ipAddress
     * @param string $username
     * @param int $alertId Alert type we are lookign for
     * @param int $siteId
     * @param $startTime mktime
     * @param $endTime mktime
     */
    public function CheckAlert404($args = array()) 
    {
        $tt2 = new WSAL_Adapters_MySQL_Meta($this->connection);
        return self::LoadMultiQuery(
            'SELECT occurrence.* FROM `' . $this->GetTable() . '` occurrence 
            INNER JOIN `' . $tt2->GetTable() . '` ipMeta on ipMeta.occurrence_id = occurrence.id 
            and ipMeta.name = "ClientIP" and ipMeta.value = %s
            INNER JOIN `' . $tt2->GetTable() . '` usernameMeta on usernameMeta.occurrence_id = occurrence.id
            and usernameMeta.name = "Username" and usernameMeta.value = %s
            WHERE occurrence.alert_id = %d AND occurrence.site_id = %d
            AND (created_on BETWEEN %d AND %d)
            GROUP BY occurrence.id',
            $args
        );
    }
}
